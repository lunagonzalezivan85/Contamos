<?php

namespace App\Services\Admin;

use Config\Database;

/**
 * Administración de tenants (panel /admin, solo superadmin).
 * crear() inserta el tenant, le provisiona seguridad (menús, permisos y
 * menús de rol copiados del tenant 1 — mismo criterio que TenantDemoSeeder)
 * y crea su usuario administrador.
 */
class TenantService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /** Tenants con conteo de usuarios y plan. */
    public function listar(): array
    {
        return $this->db->table('tenants t')
            ->select('t.*, pl.nombre AS plan, COUNT(DISTINCT u.id) AS usuarios')
            ->join('users u', 'u.tenant_id = t.id', 'left')
            ->join('planes pl', 'pl.id = t.plan_id', 'left')
            ->groupBy('t.id')
            ->orderBy('t.created_at', 'DESC')
            ->get()->getResultArray();
    }

    public function planes(): array
    {
        return $this->db->table('planes')->where('estado', 'ACTIVO')->orderBy('orden')->get()->getResultArray();
    }

    /**
     * Crea tenant + provision de seguridad + usuario admin.
     * @return array{ok: bool, error?: string, id?: int}
     */
    public function crear(array $d): array
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        $slug   = trim((string) ($d['slug'] ?? ''));
        $email  = trim((string) ($d['email'] ?? ''));
        if ($nombre === '') return ['ok' => false, 'error' => 'El nombre es obligatorio.'];
        if ($slug === '')   $slug = $this->slugify($nombre);
        if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
            return ['ok' => false, 'error' => 'El slug solo puede tener minúsculas, números y guiones.'];
        }
        if ($this->db->table('tenants')->where('slug', $slug)->countAllResults() > 0) {
            return ['ok' => false, 'error' => 'Ya existe un tenant con ese slug.'];
        }

        $now = date('Y-m-d H:i:s');
        // Código corto para vincular la app móvil (ej. CONT-8492)
        do {
            $codigoApp = 'CONT-' . random_int(1000, 9999);
        } while ($this->db->table('tenants')->where('app_codigo', $codigoApp)->countAllResults() > 0);

        $this->db->table('tenants')->insert([
            'nombre'          => $nombre,
            'slug'            => $slug,
            'app_codigo'      => $codigoApp,
            'email'           => $email !== '' ? $email : null,
            'moneda'          => trim((string) ($d['moneda'] ?? 'C$')) ?: 'C$',
            'tasa_interes'    => (float) ($d['tasa_interes'] ?? 3),
            'plazo_meses_max' => (int) ($d['plazo_meses_max'] ?? 24) ?: 24,
            'plan_id'         => (int) ($d['plan_id'] ?? 0) ?: null,
            'contacto_nombre' => trim((string) ($d['contacto_nombre'] ?? '')) ?: null,
            'estado'          => 'ACTIVO',
            'created_at'      => $now,
            'updated_at'      => $now,
        ]);
        $tenantId = (int) $this->db->insertID();

        $this->provisionSeguridad($tenantId);

        // Usuario administrador del tenant
        $user = trim((string) ($d['admin_username'] ?? ''));
        if ($user !== '') {
            $pass = (string) ($d['admin_password'] ?? '');
            if (strlen($pass) < 8) {
                return ['ok' => false, 'error' => 'La contraseña del admin debe tener al menos 8 caracteres.', 'id' => $tenantId];
            }
            $rol = $this->db->table('roles')->where('slug', 'admin')->get()->getRowArray();
            $this->db->table('users')->insert([
                'tenant_id'             => $tenantId,
                'role_id'               => (int) ($rol['id'] ?? 0),
                'username'              => $user,
                'email'                 => $email !== '' ? $email : $user,
                'password_hash'         => password_hash($pass, PASSWORD_DEFAULT),
                'nombre'                => trim((string) ($d['contacto_nombre'] ?? '')) ?: 'Administrador',
                'estado'                => 'ACTIVO',
                'debe_cambiar_password' => 1,
                'created_at'            => $now,
                'updated_at'            => $now,
            ]);
        }

        return ['ok' => true, 'id' => $tenantId];
    }

    /** Ficha del tenant + conteos operativos (usuarios, clientes, créditos). */
    public function detalle(int $id): ?array
    {
        $t = $this->db->table('tenants t')
            ->select('t.*, pl.nombre AS plan, pl.precio_mensual')
            ->join('planes pl', 'pl.id = t.plan_id', 'left')
            ->where('t.id', $id)->get()->getRowArray();
        if (!$t) return null;

        $t['stats'] = [
            'usuarios'   => (int) $this->db->table('users')->where('tenant_id', $id)->where('deleted_at IS NULL', null, false)->countAllResults(),
            'clientes'   => (int) $this->db->table('clientes')->where('tenant_id', $id)->countAllResults(),
            'solicitudes'=> (int) $this->db->table('solicitudes')->where('tenant_id', $id)->countAllResults(),
            'activos'    => (int) $this->db->table('solicitudes')->where('tenant_id', $id)->where('estado', 'ACTIVO')->countAllResults(),
        ];
        return $t;
    }

    /** Usuarios del tenant con su rol (para la ficha). */
    public function usuariosDe(int $tenantId): array
    {
        return $this->db->table('users u')
            ->select('u.id, u.username, u.email, u.nombre, u.estado, u.ultimo_login, r.nombre AS rol')
            ->join('roles r', 'r.id = u.role_id', 'left')
            ->where('u.tenant_id', $tenantId)
            ->where('u.deleted_at IS NULL', null, false)
            ->orderBy('u.username')->get()->getResultArray();
    }

    /**
     * Quita un usuario del tenant (soft delete).
     * El superadmin no puede quitarse a sí mismo.
     */
    public function quitarUsuario(int $tenantId, int $userId, int $porUserId): string
    {
        if ($userId === $porUserId) return 'No puede quitar su propio usuario.';
        $u = $this->db->table('users')
            ->where('id', $userId)->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)->get()->getRowArray();
        if (!$u) return 'Usuario no encontrado en este tenant.';
        (new \App\Models\UserModel())->delete($userId);
        return '';
    }

    /**
     * Desglose del cobro mensual del tenant (plan + usuarios extra × USD 3).
     * @return array{plan:?string, precio:float, moneda:string, usuarios:int,
     *               incluidos:int, extra:int, monto_extra:float, total:float,
     *               periodo:string, pagado:bool}
     */
    public function cobroMes(int $tenantId): array
    {
        helper('plan');
        $c = plan_cobro_mes($tenantId);
        $periodo = date('Y-m');
        $pago = $this->db->table('plan_pagos')
            ->where('tenant_id', $tenantId)->where('periodo', $periodo)->get()->getRowArray();
        $c['periodo'] = $periodo;
        $c['pagado']  = ($pago['estado'] ?? '') === 'PAGADO';
        return $c;
    }

    /**
     * Registra el cobro del período actual en plan_pagos (PAGADO).
     * El monto = precio del plan + usuarios extra × PLAN_USD_EXTRA_USUARIO;
     * el desglose queda en `observacion` para auditoría.
     */
    public function cobrarSuscripcion(int $tenantId, array $d, int $adminId): string
    {
        helper('plan');
        $t = $this->db->table('tenants')->where('id', $tenantId)->get()->getRowArray();
        if (!$t) return 'Tenant no encontrado.';
        if (empty($t['plan_id'])) return 'El tenant no tiene plan contratado.';

        $c = $this->cobroMes($tenantId);
        if ($c['total'] <= 0) return 'Nada que cobrar: el plan es libre y no hay usuarios extra.';
        if ($c['pagado'])   return 'El período ' . $c['periodo'] . ' ya está pagado.';

        $extraTxt = $c['extra'] > 0
            ? " + {$c['extra']} usuario(s) extra × USD " . number_format($c['precio_extra'], 2)
              . " = USD " . number_format($c['monto_extra'], 2)
            : '';
        $detalle = sprintf('Plan %s USD %s%s → Total USD %s. Usuarios activos: %d (incluidos: %s).',
            $c['plan'] ?? '—', number_format($c['precio'], 2), $extraTxt,
            number_format($c['total'], 2), $c['usuarios'],
            $c['incluidos'] < 0 ? 'ilimitados' : $c['incluidos']);
        $nota = trim((string) ($d['observacion'] ?? ''));
        if ($nota !== '') $detalle .= ' ' . $nota;

        $datos = [
            'tenant_id'      => $tenantId,
            'plan_id'        => (int) $t['plan_id'],
            'periodo'        => $c['periodo'],
            'monto'          => $c['total'],
            'moneda'         => $c['moneda'],
            'metodo'         => trim((string) ($d['metodo'] ?? '')) ?: null,
            'referencia'     => trim((string) ($d['referencia'] ?? '')) ?: null,
            'fecha_pago'     => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['fecha_pago'] ?? ''))
                ? $d['fecha_pago'] : date('Y-m-d'),
            'estado'         => 'PAGADO',
            'registrado_por' => $adminId,
            'observacion'    => $detalle,
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $existe = $this->db->table('plan_pagos')
            ->where('tenant_id', $tenantId)->where('periodo', $c['periodo'])->get()->getRowArray();
        if ($existe) {
            $this->db->table('plan_pagos')->where('id', $existe['id'])->update($datos);
        } else {
            $this->db->table('plan_pagos')->insert($datos + ['created_at' => date('Y-m-d H:i:s')]);
        }
        return '';
    }

    /** Activa/suspende un tenant. */
    public function toggle(int $id): string
    {
        $t = $this->db->table('tenants')->where('id', $id)->get()->getRowArray();
        if (!$t) return 'Tenant no encontrado.';
        $nuevo = $t['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $this->db->table('tenants')->where('id', $id)->update(['estado' => $nuevo, 'updated_at' => date('Y-m-d H:i:s')]);
        return '';
    }

    /** Suspende/reactiva la suscripción (plan_al_dia la lee — cierra panel, portal y app). */
    public function toggleSuscripcion(int $id): string
    {
        $t = $this->db->table('tenants')->where('id', $id)->get()->getRowArray();
        if (!$t) return 'Tenant no encontrado.';
        $nuevo = ($t['suscripcion_estado'] ?? 'ACTIVA') === 'ACTIVA' ? 'SUSPENDIDA' : 'ACTIVA';
        $this->db->table('tenants')->where('id', $id)
            ->update(['suscripcion_estado' => $nuevo, 'updated_at' => date('Y-m-d H:i:s')]);
        return '';
    }

    /** Condiciones de cobro del contrato: día de pago (5|10) + días de gracia. */
    public function condicionesSuscripcion(int $id, array $d): string
    {
        $dia    = (int) ($d['dia_pago'] ?? 10);
        $gracia = (int) ($d['gracia_dias'] ?? 4);
        if (!in_array($dia, [5, 10], true)) $dia = 10;

        $this->db->table('tenants')->where('id', $id)->update([
            'dia_pago'    => $dia,
            'gracia_dias' => max(0, min(30, $gracia)),
            'updated_at'  => date('Y-m-d H:i:s'),
        ]);
        return '';
    }

    /** Saldo pendiente de suscripción — lo que debe saldar antes del exporte. */
    public function saldoPendiente(int $tenantId): float
    {
        return (float) ($this->db->table('plan_pagos')
            ->selectSum('monto')->where('tenant_id', $tenantId)->where('estado', 'PENDIENTE')
            ->get()->getRowArray()['monto'] ?? 0);
    }

    /**
     * Datos del tenant para el exporte Excel (solicitud de información del
     * cliente suspendido): clientes, créditos con su plan, pagos/abonos y
     * historial de suscripción.
     */
    public function exporteDatos(int $tenantId): array
    {
        $clientes = $this->db->table('clientes c')
            ->select('p.nombres, p.apellidos, p.cedula, p.telefono, p.email,
                      c.limite_credito, c.monto_max, c.monto_min, c.estado, c.created_at')
            ->join('personas p', 'p.id = c.persona_id')
            ->where('c.tenant_id', $tenantId)->orderBy('p.apellidos')->get()->getResultArray();

        $creditos = $this->db->table('solicitudes s')
            ->select('s.codigo_credito, CONCAT(p.nombres, " ", p.apellidos) AS cliente, s.monto,
                      s.monto_aprobado, s.tasa_aprobada, s.plazo_aprobado, s.frecuencia_aprobada,
                      s.tipo_calculo, s.estado, s.fecha_desembolso, s.saldo_favor, s.created_at')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->where('s.tenant_id', $tenantId)->orderBy('s.id', 'DESC')->get()->getResultArray();

        $pagos = $this->db->table('pagos pa')
            ->select('s.codigo_credito, CONCAT(p.nombres, " ", p.apellidos) AS cliente,
                      pa.monto, pa.metodo, pa.tipo, pa.fecha_hora, pa.estado, pa.observacion')
            ->join('solicitudes s', 's.id = pa.solicitud_id', 'left')
            ->join('clientes c', 'c.id = s.cliente_id', 'left')
            ->join('personas p', 'p.id = c.persona_id', 'left')
            ->where('pa.tenant_id', $tenantId)->orderBy('pa.fecha_hora', 'DESC')->get()->getResultArray();

        $suscripcion = $this->db->table('plan_pagos')
            ->where('tenant_id', $tenantId)->orderBy('periodo', 'DESC')->get()->getResultArray();

        return compact('clientes', 'creditos', 'pagos', 'suscripcion');
    }

    /** Marca en audit_logs que se exportaron los datos del tenant. */
    public function auditarExporte(int $tenantId, int $adminId, float $saldoSaldado): void
    {
        $this->db->table('audit_logs')->insert([
            'tenant_id'    => $tenantId,
            'user_id'      => $adminId,
            'accion'       => 'EXPORTE_DATOS',
            'modulo'       => 'admin',
            'entidad'      => 'tenant',
            'entidad_id'   => $tenantId,
            'datos_nuevos' => json_encode(['saldo_saldado' => $saldoSaldado]),
            'ip'           => service('request')->getIPAddress(),
            'created_at'   => date('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Replica la seguridad del tenant 1: árbol de menús, role_menus y
     * role_permissions (mismo algoritmo que TenantDemoSeeder).
     */
    private function provisionSeguridad(int $tenantId): void
    {
        $now = date('Y-m-d H:i:s');

        // Tenant plantilla: el que ya tiene menús provisionados (antes el id
        // 1 fijo — en server el primer tenant puede tener otro id).
        $tpl = (int) ($this->db->table('menus')
            ->select('tenant_id')
            ->groupBy('tenant_id')
            ->orderBy('COUNT(*)', 'DESC')
            ->get(1)->getRowArray()['tenant_id'] ?? 0);
        if ($tpl === 0 || $tpl === $tenantId) return;

        // Menús — copia el árbol de la plantilla (padres primero, luego hijos)
        $mapMenu = [];
        $menusT1 = $this->db->table('menus')->where('tenant_id', $tpl)->orderBy('id')->get()->getResultArray();
        $idPorSlugT1 = [];
        foreach ($menusT1 as $m) {
            $idPorSlugT1[$m['id']] = $m['slug'];
        }
        foreach ([null, true] as $hijos) {
            foreach ($menusT1 as $m) {
                $esHijo = $m['parent_id'] !== null;
                if ($hijos !== null && !$esHijo) continue;
                if ($hijos === null && $esHijo) continue;
                $existe = $this->db->table('menus')
                    ->where('tenant_id', $tenantId)->where('slug', $m['slug'])->get()->getRowArray();
                if ($existe) { $mapMenu[$m['slug']] = (int) $existe['id']; continue; }
                $parentSlug = $esHijo ? ($idPorSlugT1[$m['parent_id']] ?? null) : null;
                $this->db->table('menus')->insert([
                    'tenant_id' => $tenantId,
                    'parent_id' => $parentSlug ? ($mapMenu[$parentSlug] ?? null) : null,
                    'nombre'    => $m['nombre'], 'slug' => $m['slug'], 'icono' => $m['icono'],
                    'url'       => $m['url'], 'orden' => $m['orden'], 'estado' => 'ACTIVO',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                $mapMenu[$m['slug']] = (int) $this->db->insertID();
            }
        }

        // role_permissions — replica la asignación de la plantilla
        $rpT1 = $this->db->table('role_permissions')->where('tenant_id', $tpl)->get()->getResultArray();
        foreach ($rpT1 as $rp) {
            $existe = $this->db->table('role_permissions')
                ->where('tenant_id', $tenantId)->where('role_id', $rp['role_id'])
                ->where('permission_id', $rp['permission_id'])->get()->getRowArray();
            if (!$existe) {
                $this->db->table('role_permissions')->insert([
                    'tenant_id'     => $tenantId,
                    'role_id'       => $rp['role_id'],
                    'permission_id' => $rp['permission_id'],
                    'created_at'    => $now,
                ]);
            }
        }

        // role_menus — replica por slug de menú de la plantilla
        $rmT1 = $this->db->table('role_menus rm')
            ->select('rm.role_id, m.slug')
            ->join('menus m', 'm.id = rm.menu_id AND m.tenant_id = ' . (int) $tpl)
            ->where('rm.tenant_id', $tpl)->get()->getResultArray();
        foreach ($rmT1 as $rm) {
            $menuId = $mapMenu[$rm['slug']] ?? null;
            if (!$menuId) continue;
            $existe = $this->db->table('role_menus')
                ->where('tenant_id', $tenantId)->where('role_id', $rm['role_id'])
                ->where('menu_id', $menuId)->get()->getRowArray();
            if (!$existe) {
                $this->db->table('role_menus')->insert([
                    'tenant_id' => $tenantId, 'role_id' => $rm['role_id'],
                    'menu_id'   => $menuId, 'created_at' => $now,
                ]);
            }
        }
    }

    /**
     * Sugerir slug a partir del nombre de la empresa — devuelve el primero
     * libre en tenants ("mi-empresa" → "mi-empresa-2" → ...). Usado por el
     * botón «Generar» del form de alta (GET /admin/tenants/slug-sugerir).
     */
    public function sugerirSlug(string $nombre): string
    {
        $base = $this->slugify($nombre) ?: 'empresa';
        $slug = $base;
        $i    = 2;
        while ($this->db->table('tenants')->where('slug', $slug)->countAllResults() > 0) {
            $slug = $base . '-' . $i++;
        }
        return $slug;
    }

    /** ¿Ese slug ya está tomado? (para el chequeo en vivo del form) */
    public function slugOcupado(string $slug): bool
    {
        return $slug !== '' && $this->db->table('tenants')->where('slug', $slug)->countAllResults() > 0;
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }
}
