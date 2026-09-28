<?php

namespace App\Database\Seeds;

use CodeIgniter\Database\Seeder;

/**
 * Datos iniciales del módulo de seguridad:
 * tenant default, roles del sistema, permisos, menús y usuario admin.
 *
 * Ejecutar: php spark db:seed SecuritySeeder
 */
class SecuritySeeder extends Seeder
{
    public function run()
    {
        $now = date('Y-m-d H:i:s');

        // ---------------------------------------------------------------
        // Tenant default
        // ---------------------------------------------------------------
        $this->db->table('tenants')->insert([
            'nombre'     => 'CFSI',
            'slug'       => 'cfsi',
            'email'      => 'admin@cfsi.local',
            'estado'     => 'ACTIVO',
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        $tenantId = $this->db->insertID();

        // ---------------------------------------------------------------
        // Roles del sistema (tenant_id NULL = aplican a todos los tenants)
        // ---------------------------------------------------------------
        $roles = [
            ['nombre' => 'Super Administrador', 'slug' => 'superadmin', 'es_sistema' => 1],
            ['nombre' => 'Administrador',       'slug' => 'admin',      'es_sistema' => 1],
            ['nombre' => 'Gerente',             'slug' => 'gerente',    'es_sistema' => 1],
            ['nombre' => 'Supervisor',          'slug' => 'supervisor', 'es_sistema' => 1],
            ['nombre' => 'Gestor de Cobro',     'slug' => 'gestor',     'es_sistema' => 1],
            ['nombre' => 'Cajero',              'slug' => 'cajero',     'es_sistema' => 1],
        ];
        $roleIds = [];
        foreach ($roles as $rol) {
            $this->db->table('roles')->insert($rol + [
                'tenant_id'  => null,
                'estado'     => 'ACTIVO',
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $roleIds[$rol['slug']] = $this->db->insertID();
        }

        // ---------------------------------------------------------------
        // Permisos — catálogo global (modulo.accion)
        // ---------------------------------------------------------------
        $permisos = [
            // Solicitudes
            ['solicitudes.ver',        'Ver solicitudes',        'solicitudes'],
            ['solicitudes.crear',      'Crear solicitud',        'solicitudes'],
            ['solicitudes.editar',     'Editar solicitud',       'solicitudes'],
            ['solicitudes.aprobar',    'Aprobar solicitud',      'solicitudes'],
            ['solicitudes.rechazar',   'Rechazar solicitud',     'solicitudes'],
            ['solicitudes.desembolsar','Desembolsar solicitud',  'solicitudes'],
            ['solicitudes.documentacion', 'Generar documentación', 'solicitudes'],
            // Créditos
            ['creditos.ver',           'Ver créditos',           'creditos'],
            ['creditos.editar',        'Editar crédito',         'creditos'],
            ['creditos.plan_pago',     'Ver plan de pago',       'creditos'],
            // Pagos
            ['pagos.ver',              'Ver pagos',              'pagos'],
            ['pagos.registrar',        'Registrar pago',         'pagos'],
            ['pagos.aprobar',          'Aprobar pago',           'pagos'],
            ['pagos.revertir',         'Solicitar reversión',    'pagos'],
            ['pagos.aprobar_reversion','Aprobar reversión',      'pagos'],
            // Clientes
            ['clientes.ver',           'Ver clientes',           'clientes'],
            ['clientes.crear',         'Crear cliente',          'clientes'],
            ['clientes.editar',        'Editar cliente',         'clientes'],
            // Empleados
            ['empleados.ver',          'Ver empleados',          'empleados'],
            ['empleados.crear',        'Crear empleado',         'empleados'],
            ['empleados.editar',       'Editar empleado',        'empleados'],
            // Reportes
            ['reportes.ver',           'Ver reportes',           'reportes'],
            ['reportes.exportar',      'Exportar reportes',      'reportes'],
            // Caja
            ['caja.arqueo',            'Arqueo de caja',         'caja'],
            ['caja.gastos',            'Registrar gastos',       'caja'],
            // Administración
            ['admin.usuarios',         'Gestionar usuarios',     'admin'],
            ['admin.roles',            'Gestionar roles',        'admin'],
            ['admin.menus',            'Gestionar menús',        'admin'],
            ['admin.configuracion',    'Configuración',          'admin'],
            ['admin.tenants',          'Gestionar tenants',      'admin'],
        ];
        $permIds = [];
        foreach ($permisos as [$codigo, $nombre, $modulo]) {
            $this->db->table('permissions')->insert([
                'codigo'     => $codigo,
                'nombre'     => $nombre,
                'modulo'     => $modulo,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            $permIds[$codigo] = $this->db->insertID();
        }

        // ---------------------------------------------------------------
        // role_permissions — asignación por rol
        // ---------------------------------------------------------------
        $todosLosPermisos = array_values($permIds);

        $asignacion = [
            'superadmin' => $todosLosPermisos,
            'admin'      => $todosLosPermisos,
            'gerente'    => [
                'solicitudes.ver', 'solicitudes.aprobar', 'solicitudes.rechazar',
                'solicitudes.desembolsar', 'solicitudes.documentacion',
                'creditos.ver', 'creditos.plan_pago',
                'pagos.ver', 'pagos.aprobar', 'pagos.aprobar_reversion',
                'clientes.ver', 'empleados.ver',
                'reportes.ver', 'reportes.exportar',
            ],
            'supervisor' => [
                'solicitudes.ver', 'solicitudes.crear', 'solicitudes.editar',
                'solicitudes.documentacion',
                'creditos.ver', 'creditos.plan_pago',
                'pagos.ver', 'pagos.registrar', 'pagos.aprobar', 'pagos.revertir',
                'clientes.ver', 'clientes.crear', 'clientes.editar',
                'reportes.ver',
            ],
            'gestor'     => [
                'solicitudes.ver', 'solicitudes.crear',
                'creditos.ver', 'creditos.plan_pago',
                'pagos.ver', 'pagos.registrar',
                'clientes.ver', 'clientes.crear', 'clientes.editar',
            ],
            'cajero'     => [
                'pagos.ver', 'pagos.registrar',
                'creditos.ver', 'creditos.plan_pago',
                'clientes.ver',
                'caja.arqueo', 'caja.gastos',
            ],
        ];

        foreach ($asignacion as $slug => $codigos) {
            $roleId = $roleIds[$slug];
            $lista  = ($slug === 'superadmin' || $slug === 'admin')
                ? $codigos
                : array_map(fn($c) => $permIds[$c], $codigos);

            foreach ($lista as $permId) {
                $this->db->table('role_permissions')->insert([
                    'tenant_id'     => $tenantId,
                    'role_id'       => $roleId,
                    'permission_id' => $permId,
                    'created_at'    => $now,
                ]);
            }
        }

        // ---------------------------------------------------------------
        // Menús base (jerárquicos)
        // ---------------------------------------------------------------
        $menus = [
            // [nombre, slug, icono, url, orden, parent_slug]
            ['Dashboard',      'dashboard',      'home',            '/dashboard',            1,  null],
            ['Solicitudes',    'solicitudes',    'file-text',       '/solicitudes',          2,  null],
            ['Créditos',       'creditos',       'credit-card',     '/creditos',             3,  null],
            ['Pagos',          'pagos',          'dollar-sign',     '/pagos',                4,  null],
            ['Clientes',       'clientes',       'users',           '/clientes',             5,  null],
            ['Cartera',        'cartera',        'briefcase',       null,                    6,  null],
            ['En Mora',        'cartera-mora',   'alert-circle',    '/cartera/mora',         1,  'cartera'],
            ['Recuperación',   'cartera-recup',  'refresh-cw',      '/cartera/recuperacion', 2,  'cartera'],
            ['Reportes',       'reportes',       'bar-chart-2',     '/reportes',             7,  null],
            ['Caja',           'caja',           'archive',         null,                    8,  null],
            ['Arqueo',         'caja-arqueo',    'clipboard',       '/caja/arqueo',          1,  'caja'],
            ['Gastos',         'caja-gastos',    'minus-circle',    '/caja/gastos',          2,  'caja'],
            ['Administración', 'administracion', 'settings',        null,                    9,  null],
            ['Empleados',      'admin-empleados','user-check',      '/empleados',            1,  'administracion'],
            ['Usuarios',       'admin-usuarios', 'shield',          '/admin/usuarios',       2,  'administracion'],
            ['Roles',          'admin-roles',    'lock',            '/admin/roles',          3,  'administracion'],
            ['Menús',          'admin-menus',    'menu',            '/admin/menus',          4,  'administracion'],
            ['Configuración',  'admin-config',   'tool',            '/admin/configuracion',  5,  'administracion'],
        ];

        $menuIds = [];
        // Primera pasada: padres
        foreach ($menus as [$nombre, $slug, $icono, $url, $orden, $parentSlug]) {
            if ($parentSlug === null) {
                $this->db->table('menus')->insert([
                    'tenant_id'  => $tenantId,
                    'parent_id'  => null,
                    'nombre'     => $nombre,
                    'slug'       => $slug,
                    'icono'      => $icono,
                    'url'        => $url,
                    'orden'      => $orden,
                    'estado'     => 'ACTIVO',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $menuIds[$slug] = $this->db->insertID();
            }
        }
        // Segunda pasada: hijos
        foreach ($menus as [$nombre, $slug, $icono, $url, $orden, $parentSlug]) {
            if ($parentSlug !== null) {
                $this->db->table('menus')->insert([
                    'tenant_id'  => $tenantId,
                    'parent_id'  => $menuIds[$parentSlug],
                    'nombre'     => $nombre,
                    'slug'       => $slug,
                    'icono'      => $icono,
                    'url'        => $url,
                    'orden'      => $orden,
                    'estado'     => 'ACTIVO',
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
                $menuIds[$slug] = $this->db->insertID();
            }
        }

        // ---------------------------------------------------------------
        // role_menus — superadmin/admin ven todo; otros roles subset
        // ---------------------------------------------------------------
        $menusPorRol = [
            'superadmin' => array_keys($menuIds),
            'admin'      => array_keys($menuIds),
            'gerente'    => ['dashboard', 'solicitudes', 'creditos', 'pagos', 'clientes', 'cartera', 'cartera-mora', 'cartera-recup', 'reportes'],
            'supervisor' => ['dashboard', 'solicitudes', 'creditos', 'pagos', 'clientes', 'reportes'],
            'gestor'     => ['dashboard', 'solicitudes', 'creditos', 'pagos', 'clientes'],
            'cajero'     => ['dashboard', 'pagos', 'creditos', 'clientes', 'caja', 'caja-arqueo', 'caja-gastos'],
        ];

        foreach ($menusPorRol as $slug => $slugs) {
            foreach ($slugs as $menuSlug) {
                $this->db->table('role_menus')->insert([
                    'tenant_id'  => $tenantId,
                    'role_id'    => $roleIds[$slug],
                    'menu_id'    => $menuIds[$menuSlug],
                    'created_at' => $now,
                ]);
            }
        }

        // ---------------------------------------------------------------
        // Usuario admin inicial — password: Admin2026! (cambiar al entrar)
        // ---------------------------------------------------------------
        $this->db->table('users')->insert([
            'tenant_id'             => $tenantId,
            'role_id'               => $roleIds['superadmin'],
            'username'              => 'admin',
            'email'                 => 'admin@cfsi.local',
            'password_hash'         => password_hash('Admin2026!', PASSWORD_DEFAULT),
            'nombre'                => 'Administrador del Sistema',
            'estado'                => 'ACTIVO',
            'debe_cambiar_password' => 1,
            'created_at'            => $now,
            'updated_at'            => $now,
        ]);

        echo "SecuritySeeder OK — tenant #{$tenantId}, " . count($roleIds) . " roles, "
            . count($permIds) . " permisos, " . count($menuIds) . " menús, usuario admin\n";
    }
}
