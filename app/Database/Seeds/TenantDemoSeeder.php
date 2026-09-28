<?php

namespace App\Database\Seeds;

use App\Models\PagoModel;
use App\Models\SolicitudModel;
use App\Services\Partner\PagoService;
use CodeIgniter\Database\Seeder;

/**
 * Datos demo multi-tenant — poblado y validación de aislamiento.
 *
 *  - Provisiona la seguridad por tenant (menus, role_menus, role_permissions)
 *    copiando la estructura del tenant 1 (los roles/permisos son globales).
 *  - Rellena lo que falta en tenant 2 (menús/role_menus) y crea tenant 3
 *    completo con usuario admin.
 *  - Genera clientes, empleados, solicitudes en varios estados y créditos
 *    ACTIVO con cuotas + pagos pasando por la lógica real de aprobación.
 *
 * Ejecutar: php spark db:seed TenantDemoSeeder
 */
class TenantDemoSeeder extends Seeder
{
    private PagoService $pagos;

    public function run()
    {
        $this->pagos = new PagoService();
        $now = date('Y-m-d H:i:s');

        // ---------------------------------------------------------------
        // Tenant 3 — "Crédito Express" (mora + pronto pago activos)
        // ---------------------------------------------------------------
        $t3 = $this->db->table('tenants')->where('slug', 'credito-express')->get()->getRowArray();
        if (!$t3) {
            $this->db->table('tenants')->insert([
                'nombre'          => 'Crédito Express',
                'slug'            => 'credito-express',
                'email'           => 'admin@creditoexpress.ni',
                'moneda'          => 'C$',
                'tasa_interes'    => 4.00,
                'mora_diaria_pct' => 0.1000,
                'pronto_pago_pct' => 15.00,
                'plazo_meses_max' => 12,
                'estado'          => 'ACTIVO',
                'created_at'      => $now,
                'updated_at'      => $now,
            ]);
            $t3id = (int) $this->db->insertID();
            $this->provisionSeguridad($t3id);
            $this->crearUsuarioAdmin($t3id, 'admin-ce', 'admin@creditoexpress.ni', 'Administrador CE', $now);
            echo "Tenant 3 provisionado (id $t3id)\n";
        } else {
            $t3id = (int) $t3['id'];
            echo "Tenant 3 ya existe (id $t3id)\n";
        }

        // ---------------------------------------------------------------
        // Tenant 2 — "Tu Impulso": activar mora/pronto y completar menús
        // ---------------------------------------------------------------
        $this->db->table('tenants')->where('id', 2)->update([
            'mora_diaria_pct' => 0.1000,
            'pronto_pago_pct' => 15.00,
        ]);
        $this->provisionSeguridad(2);
        echo "Tenant 2: seguridad completada, mora/pronto activados\n";

        // ---------------------------------------------------------------
        // Datos demo
        // ---------------------------------------------------------------
        $this->demoTenant2($now);
        $this->demoTenant3($t3id, $now);

        echo "TenantDemoSeeder OK\n";
    }

    // ---------------------------------------------------------------
    // Seguridad por tenant
    // ---------------------------------------------------------------

    /**
     * Menús, role_menus y role_permissions del tenant copiando la
     * estructura del tenant 1. Idempotente: no duplica lo que ya existe.
     */
    private function provisionSeguridad(int $tenantId): void
    {
        $now = date('Y-m-d H:i:s');

        // Menús — copia el árbol del tenant 1 (padres primero, luego hijos)
        $mapMenu = []; // slug => nuevo id
        $menusT1 = $this->db->table('menus')->where('tenant_id', 1)->orderBy('id')->get()->getResultArray();
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
                if ($existe) {
                    $mapMenu[$m['slug']] = (int) $existe['id'];
                    continue;
                }
                $parentSlug = $esHijo ? ($idPorSlugT1[$m['parent_id']] ?? null) : null;
                $this->db->table('menus')->insert([
                    'tenant_id'  => $tenantId,
                    'parent_id'  => $parentSlug ? ($mapMenu[$parentSlug] ?? null) : null,
                    'nombre'     => $m['nombre'],
                    'slug'       => $m['slug'],
                    'icono'      => $m['icono'],
                    'url'        => $m['url'],
                    'orden'      => $m['orden'],
                    'estado'     => 'ACTIVO',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $mapMenu[$m['slug']] = (int) $this->db->insertID();
            }
        }

        // role_permissions — replica la asignación del tenant 1 por rol
        $rpT1 = $this->db->table('role_permissions')->where('tenant_id', 1)->get()->getResultArray();
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

        // role_menus — replica por slug de menú
        $rmT1 = $this->db->table('role_menus rm')
            ->select('rm.role_id, m.slug')
            ->join('menus m', 'm.id = rm.menu_id')
            ->where('rm.tenant_id', 1)->get()->getResultArray();
        foreach ($rmT1 as $rm) {
            $menuId = $mapMenu[$rm['slug']] ?? null;
            if (!$menuId) continue;
            $existe = $this->db->table('role_menus')
                ->where('tenant_id', $tenantId)->where('role_id', $rm['role_id'])
                ->where('menu_id', $menuId)->get()->getRowArray();
            if (!$existe) {
                $this->db->table('role_menus')->insert([
                    'tenant_id'  => $tenantId,
                    'role_id'    => $rm['role_id'],
                    'menu_id'    => $menuId,
                    'created_at' => $now,
                ]);
            }
        }
    }

    private function crearUsuarioAdmin(int $tenantId, string $username, string $email, string $nombre, string $now): void
    {
        $existe = $this->db->table('users')->where('tenant_id', $tenantId)->where('username', $username)->get()->getRowArray();
        if ($existe) return;
        $rolAdmin = $this->db->table('roles')->where('slug', 'admin')->get()->getRowArray();
        $this->db->table('users')->insert([
            'tenant_id'             => $tenantId,
            'role_id'               => (int) $rolAdmin['id'],
            'username'              => $username,
            'email'                 => $email,
            'password_hash'         => password_hash('Admin2026!', PASSWORD_DEFAULT),
            'nombre'                => $nombre,
            'estado'                => 'ACTIVO',
            'debe_cambiar_password' => 1,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);
    }

    // ---------------------------------------------------------------
    // Datos demo
    // ---------------------------------------------------------------

    private function persona(int $tenantId, string $tipo, string $nom, string $ape, string $ced, string $tel, string $now): int
    {
        $this->db->table('personas')->insert([
            'tenant_id'  => $tenantId, 'tipo' => $tipo,
            'nombres'    => $nom, 'apellidos' => $ape,
            'cedula'     => $ced, 'telefono' => $tel,
            'estado'     => 'ACTIVO', 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    private function cliente(int $tenantId, int $personaId, string $codigo, string $now): int
    {
        $this->db->table('clientes')->insert([
            'tenant_id' => $tenantId, 'persona_id' => $personaId,
            'codigo' => $codigo, 'estado' => 'ACTIVO', 'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    private function empleado(int $tenantId, int $personaId, string $cargo, string $now): int
    {
        $this->db->table('empleados')->insert([
            'tenant_id' => $tenantId, 'persona_id' => $personaId, 'cargo' => $cargo,
            'fecha_ingreso' => date('Y-m-d'), 'estado' => 'ACTIVO',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        return (int) $this->db->insertID();
    }

    /**
     * Crédito ACTIVO con cuotas generadas por el plan real.
     * $diasAtrasPrimerPago < 0 → cuotas vencidas (demo de mora).
     */
    private function creditoActivo(int $tenantId, int $clienteId, int $empleadoId, float $monto,
                                   string $freq, float $tasa, int $plazo, int $diasPrimerPago,
                                   string $codigo, string $now): int
    {
        $solId = (int) $this->db->table('solicitudes')->insert([
            'tenant_id'           => $tenantId,
            'cliente_id'          => $clienteId,
            'empleado_id'         => $empleadoId,
            'asignado_a'          => $empleadoId,
            'ruta'                => 'Ruta Centro',
            'monto'               => $monto,
            'plazo_meses'         => $plazo,
            'frecuencia'          => $freq,
            'tasa_mensual'        => $tasa,
            'destino'             => 'Capital de trabajo',
            'estado'              => SolicitudModel::ACTIVO,
            'origen'              => 'INTERNO',
            'monto_aprobado'      => $monto,
            'tasa_aprobada'       => $tasa,
            'plazo_aprobado'      => $plazo,
            'frecuencia_aprobada' => $freq,
            'fecha_primer_pago'   => date('Y-m-d', strtotime($diasPrimerPago . ' days')),
            'fecha_desembolso'    => date('Y-m-d', strtotime(($diasPrimerPago - 5) . ' days')),
            'fecha_entrega'       => $now,
            'codigo_credito'      => $codigo,
            'created_at'          => $now,
            'updated_at'          => $now,
        ], true) ? $this->db->insertID() : 0;

        $sol = (new SolicitudModel())->find($solId);
        $this->pagos->generarCuotas($tenantId, $sol);
        return $solId;
    }

    /** Usuario (admin) del tenant — para registrado_por / aprobado_por. */
    private function usuarioDelTenant(int $tenantId): int
    {
        $u = $this->db->table('users')->where('tenant_id', $tenantId)
            ->orderBy('id')->get()->getRowArray();
        return (int) ($u['id'] ?? 1);
    }

    /** Pago real: inserta en REVISION y lo aprueba con la lógica de producción. */
    private function pagoAplicado(int $tenantId, int $solId, ?int $cuotaId, int $empleadoId,
                                  float $monto, string $fechaHora): void
    {
        $uid = $this->usuarioDelTenant($tenantId);
        $pagoId = (int) $this->db->table('pagos')->insert([
            'tenant_id'      => $tenantId,
            'solicitud_id'   => $solId,
            'cuota_id'       => $cuotaId,
            'empleado_id'    => $empleadoId,
            'registrado_por' => $uid,
            'monto'          => $monto,
            'metodo'         => 'EFECTIVO',
            'tipo'           => PagoModel::TIPO_PAGO,
            'fecha_hora'     => $fechaHora,
            'estado'         => PagoModel::REVISION,
            'created_at'     => $fechaHora,
            'updated_at'     => $fechaHora,
        ], true) ? $this->db->insertID() : 0;

        $this->pagos->aprobarPago($tenantId, $pagoId, $uid);
    }

    private function demoTenant2(string $now): void
    {
        // Ya tiene 3 clientes, 2 empleados y el crédito #1 ACTIVO (cuotas futuras).
        if ($this->db->table('solicitudes')->where('tenant_id', 2)->where('codigo_credito', 'C-TI-2026-0002')->countAllResults()) {
            echo "Tenant 2: datos demo ya existen\n";
            return;
        }

        // Segundo crédito ACTIVO con cuotas VENCIDAS (demo de mora) — cliente 2, gestor 1
        $solId = $this->creditoActivo(2, 2, 1, 20000, 'S', 3.50, 2, -30, 'C-TI-2026-0002', $now);

        // Un pago aplicado que cubre la primera cuota + su mora (lógica real)
        $c1 = $this->db->table('cuotas')->where('solicitud_id', $solId)->orderBy('n')->get()->getRowArray();
        if ($c1) {
            $this->pagoAplicado(2, $solId, (int) $c1['id'], 1, (float) $c1['cuota'], date('Y-m-d H:i:s', strtotime('-2 days')));
        }

        // Pago pendiente de revisión sobre el crédito existente #1
        $cSol1 = $this->db->table('cuotas')->where('solicitud_id', 1)->orderBy('n')->get()->getRowArray();
        $this->db->table('pagos')->insert([
            'tenant_id'      => 2, 'solicitud_id' => 1,
            'cuota_id'       => $cSol1 ? (int) $cSol1['id'] : null,
            'empleado_id'    => 1, 'registrado_por' => $this->usuarioDelTenant(2),
            'monto'          => $cSol1 ? (float) $cSol1['cuota'] : 5000,
            'metodo'         => 'TRANSFERENCIA', 'tipo' => PagoModel::TIPO_PAGO,
            'fecha_hora'     => $now, 'observacion' => 'Abono por transferencia',
            'estado'         => PagoModel::REVISION, 'created_at' => $now, 'updated_at' => $now,
        ]);

        // Solicitud CREADA adicional para el pipeline
        $pNuevo = $this->persona(2, 'CLIENTE', 'Carlos', 'Rivas', '001-150386-0007K', '8765-1122', $now);
        $cliNuevo = $this->cliente(2, $pNuevo, 'C-TI-0004', $now);
        $this->db->table('solicitudes')->insert([
            'tenant_id' => 2, 'cliente_id' => $cliNuevo, 'asignado_a' => 1,
            'monto' => 15000, 'plazo_meses' => 6, 'frecuencia' => 'M',
            'tasa_mensual' => 3.5, 'destino' => 'Inventario',
            'estado' => SolicitudModel::CREADA, 'origen' => 'INTERNO',
            'created_at' => $now, 'updated_at' => $now,
        ]);
        echo "Tenant 2: datos demo agregados\n";
    }

    private function demoTenant3(int $t3, string $now): void
    {
        if ($this->db->table('clientes')->where('tenant_id', $t3)->countAllResults() > 0) {
            echo "Tenant 3: datos demo ya existen\n";
            return;
        }

        // Empleados
        $pGestor = $this->persona($t3, 'EMPLEADO', 'Ana', 'Castillo', '001-020190-0001A', '8888-0001', $now);
        $pCajero = $this->persona($t3, 'EMPLEADO', 'Luis', 'Mejia', '001-110392-0002B', '8888-0002', $now);
        $empGestor = $this->empleado($t3, $pGestor, 'Gestor', $now);
        $this->empleado($t3, $pCajero, 'Cajero', $now);

        // Clientes
        $cli1 = $this->cliente($t3, $this->persona($t3, 'CLIENTE', 'Rosa', 'Amador', '001-250485-0003C', '8888-1001', $now), 'C-CE-0001', $now);
        $cli2 = $this->cliente($t3, $this->persona($t3, 'CLIENTE', 'Jose', 'Balmaceda', '001-301288-0004D', '8888-1002', $now), 'C-CE-0002', $now);
        $cli3 = $this->cliente($t3, $this->persona($t3, 'CLIENTE', 'Karla', 'Sequeira', '001-070795-0005E', '8888-1003', $now), 'C-CE-0003', $now);

        // Pipeline: CONTACTO + CREADA
        foreach ([[$cli3, SolicitudModel::CONTACTO, 8000], [$cli2, SolicitudModel::CREADA, 25000]] as [$cli, $estado, $monto]) {
            $this->db->table('solicitudes')->insert([
                'tenant_id' => $t3, 'cliente_id' => $cli, 'asignado_a' => $empGestor,
                'monto' => $monto, 'plazo_meses' => 4, 'frecuencia' => 'Q',
                'tasa_mensual' => 4.0, 'destino' => 'Comercio',
                'estado' => $estado, 'origen' => 'INTERNO',
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        // Crédito ACTIVO con cuotas vencidas (mora) + un pago aplicado + uno en revisión
        $solId = $this->creditoActivo($t3, $cli1, $empGestor, 12000, 'S', 4.0, 2, -21, 'C-CE-2026-0001', $now);
        $c1 = $this->db->table('cuotas')->where('solicitud_id', $solId)->orderBy('n')->get()->getRowArray();
        if ($c1) {
            $this->pagoAplicado($t3, $solId, (int) $c1['id'], $empGestor, (float) $c1['cuota'], date('Y-m-d H:i:s', strtotime('-1 day')));
        }
        $this->db->table('pagos')->insert([
            'tenant_id'      => $t3, 'solicitud_id' => $solId,
            'empleado_id'    => $empGestor, 'registrado_por' => $this->usuarioDelTenant($t3),
            'monto'          => 3000, 'metodo' => 'EFECTIVO', 'tipo' => PagoModel::TIPO_PAGO,
            'fecha_hora'     => $now, 'estado' => PagoModel::REVISION,
            'created_at'     => $now, 'updated_at' => $now,
        ]);
        echo "Tenant 3: datos demo agregados\n";
    }
}
