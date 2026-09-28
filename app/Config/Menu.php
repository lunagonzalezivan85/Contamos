<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Menús del sistema — se construyen por código, no por BD.
 *
 * $sistema : menú del administrador de la plataforma (rol superadmin).
 *            Gestiona tenants, usuarios y estadísticas globales.
 * $tenant  : menú operativo que ve cada negocio/tenant.
 *            NO incluye gestión de usuarios (solo el admin del sistema).
 *
 * Estructura de cada item:
 *   nombre   string  Etiqueta visible
 *   icono    string  Nombre del icono (para uso futuro con librería de iconos)
 *   url      string  Ruta interna ('/ruta') o null si es agrupador
 *   permiso  string  Código 'modulo.accion' requerido (opcional, solo tenant)
 *   children array   Sub-items (opcional)
 */
class Menu extends BaseConfig
{
    /**
     * Menú del administrador del sistema (plataforma).
     * Solo lo ve el rol 'superadmin'.
     */
    public array $sistema = [
        [
            'nombre' => 'Estadísticas',
            'icono'  => 'bar-chart',
            'url'    => '/admin/estadisticas',
        ],
        [
            'nombre' => 'Tenants',
            'icono'  => 'briefcase',
            'url'    => null,
            'children' => [
                ['nombre' => 'Listado',      'icono' => 'list',        'url' => '/admin/tenants'],
                ['nombre' => 'Nuevo tenant', 'icono' => 'plus-circle', 'url' => '/admin/tenants/nuevo'],
            ],
        ],
        [
            'nombre' => 'Usuarios',
            'icono'  => 'users',
            'url'    => '/admin/usuarios',
        ],
        [
            'nombre' => 'Auditoría',
            'icono'  => 'activity',
            'url'    => '/admin/auditoria',
        ],
        [
            'nombre' => 'Configuración',
            'icono'  => 'settings',
            'url'    => '/admin/configuracion',
        ],
    ];

    /**
     * Menú del tenant (negocio).
     * Los items con 'permiso' se filtran según los permisos del rol.
     */
    public array $tenant = [
        [
            'nombre' => 'Dashboard',
            'icono'  => 'home',
            'url'    => '/dashboard',
        ],
        [
            'nombre' => 'Portal',
            'icono'  => 'external-link',
            'url'    => '/{slug}/portal',   // {slug} se reemplaza con el tenant de sesión
        ],
        [
            'nombre' => 'Socios',
            'icono'  => 'users',
            'url'    => null,
            'children' => [
                ['nombre' => 'Clientes',   'icono' => 'user',       'url' => '/socios/clientes',  'permiso' => 'clientes.ver'],
                ['nombre' => 'Empleados',  'icono' => 'user-check', 'url' => '/socios/empleados', 'permiso' => 'empleados.ver'],
                ['nombre' => 'Asistencia', 'icono' => 'clock',      'url' => '/asistencia',       'permiso' => 'empleados.asistencia'],
            ],
        ],
        [
            'nombre' => 'Crédito',
            'icono'  => 'credit-card',
            'url'    => null,
            'children' => [
                ['nombre' => 'Solicitud', 'icono' => 'file-text',   'url' => '/credito/solicitudes', 'permiso' => 'solicitudes.ver'],
                ['nombre' => 'Créditos',  'icono' => 'credit-card', 'url' => '/creditos',            'permiso' => 'creditos.ver'],
                ['nombre' => 'Cartera',   'icono' => 'briefcase',   'url' => '/credito/cartera',     'permiso' => 'creditos.ver'],
                ['nombre' => 'Reporte',   'icono' => 'bar-chart-2', 'url' => '/credito/reporte',     'permiso' => 'reportes.ver'],
                ['nombre' => 'CONAMI',    'icono' => 'shield',      'url' => '/credito/reporte-conami', 'permiso' => 'reportes.ver'],
            ],
        ],
        [
            'nombre' => 'Finanzas',
            'icono'  => 'dollar-sign',
            'url'    => null,
            'children' => [
                ['nombre' => 'Pagos',        'icono' => 'archive',      'url' => '/pagos',                 'permiso' => 'pagos.ver'],
                ['nombre' => 'Arqueo',       'icono' => 'clipboard',    'url' => '/finanzas/arqueo',       'permiso' => 'caja.arqueo'],
                ['nombre' => 'Ingresos',     'icono' => 'trending-up',  'url' => '/finanzas/ingresos',     'permiso' => 'caja.ingresos'],
                ['nombre' => 'Gastos',       'icono' => 'minus-circle', 'url' => '/finanzas/gastos',       'permiso' => 'caja.gastos'],
                ['nombre' => 'Metas',        'icono' => 'target',       'url' => '/finanzas/metas',        'permiso' => 'metas.plan'],
                ['nombre' => 'Recuperación', 'icono' => 'refresh-cw',   'url' => '/finanzas/recuperacion', 'permiso' => 'pagos.ver'],
                ['nombre' => 'Reporte',      'icono' => 'bar-chart-2',  'url' => '/finanzas/reporte',      'permiso' => 'reportes.ver'],
            ],
        ],
        [
            'nombre' => 'Reportes',
            'icono'  => 'bar-chart',
            'url'    => '/reportes',
            'permiso' => 'reportes.ver',
        ],
        [
            'nombre' => 'Herramientas',
            'icono'  => 'tool',
            'url'    => null,
            'children' => [
                ['nombre' => 'Calculadora de cuotas', 'icono' => 'percent',   'url' => '/herramientas/calculadora'],
                ['nombre' => 'Generar documentación', 'icono' => 'file-plus', 'url' => '/herramientas/documentacion', 'permiso' => 'solicitudes.documentacion'],
            ],
        ],
        [
            'nombre'  => 'Configuración',
            'icono'   => 'settings',
            'url'     => '/configuracion',
            'permiso' => 'admin.configuracion',
        ],
    ];

    /**
     * Devuelve el menú según el tipo de usuario.
     *
     * @param string $tipo 'sistema' | 'tenant'
     */
    public function para(string $tipo): array
    {
        return $tipo === 'sistema' ? $this->sistema : $this->tenant;
    }
}
