<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;

/**
 * Panel del administrador del sistema — estadísticas globales de la plataforma.
 */
class EstadisticasController extends BaseController
{
    public function index()
    {
        $db = \Config\Database::connect();

        $stats = [
            'tenants_activos'   => $db->table('tenants')->where('estado', 'ACTIVO')->countAllResults(),
            'tenants_total'     => $db->table('tenants')->countAllResults(),
            'usuarios_total'    => $db->table('users')->countAllResults(),
            'logins_hoy'        => $db->table('login_attempts')
                                      ->where('exito', 1)
                                      ->where('DATE(created_at)', date('Y-m-d'))
                                      ->countAllResults(),
            'intentos_fallidos' => $db->table('login_attempts')
                                      ->where('exito', 0)
                                      ->where('DATE(created_at)', date('Y-m-d'))
                                      ->countAllResults(),
        ];

        $tenants = $db->table('tenants t')
            ->select('t.id, t.nombre, t.slug, t.razon_social, t.contacto_nombre, t.estado, t.created_at, COUNT(u.id) AS usuarios')
            ->join('users u', 'u.tenant_id = t.id', 'left')
            ->groupBy('t.id')
            ->orderBy('t.created_at', 'DESC')
            ->get()
            ->getResultArray();

        return view('admin/estadisticas/index', [
            'title'   => 'Estadísticas — Admin',
            'stats'   => $stats,
            'tenants' => $tenants,
        ]);
    }
}
