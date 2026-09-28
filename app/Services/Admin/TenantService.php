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
        $this->db->table('tenants')->insert([
            'nombre'          => $nombre,
            'slug'            => $slug,
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

    /** Activa/suspende un tenant. */
    public function toggle(int $id): string
    {
        $t = $this->db->table('tenants')->where('id', $id)->get()->getRowArray();
        if (!$t) return 'Tenant no encontrado.';
        $nuevo = $t['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $this->db->table('tenants')->where('id', $id)->update(['estado' => $nuevo, 'updated_at' => date('Y-m-d H:i:s')]);
        return '';
    }

    /**
     * Replica la seguridad del tenant 1: árbol de menús, role_menus y
     * role_permissions (mismo algoritmo que TenantDemoSeeder).
     */
    private function provisionSeguridad(int $tenantId): void
    {
        $now = date('Y-m-d H:i:s');

        // Menús — copia el árbol del tenant 1 (padres primero, luego hijos)
        $mapMenu = [];
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

        // role_permissions — replica la asignación del tenant 1
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
                    'tenant_id' => $tenantId, 'role_id' => $rm['role_id'],
                    'menu_id'   => $menuId, 'created_at' => $now,
                ]);
            }
        }
    }

    private function slugify(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        $s = preg_replace('/[^a-z0-9]+/', '-', $s);
        return trim($s, '-');
    }
}
