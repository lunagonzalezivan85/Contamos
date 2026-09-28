<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Database;

/**
 * Configuración de plataforma — catálogo de planes SaaS.
 * GET  /admin/configuracion           lista planes + tenants por plan
 * POST /admin/configuracion/planes/{id} edita precio y límites
 */
class ConfiguracionController extends BaseController
{
    /** GET /admin/configuracion */
    public function index()
    {
        $db = Database::connect();

        $planes = $db->table('planes p')
            ->select('p.*, COUNT(t.id) AS tenants')
            ->join('tenants t', 't.plan_id = p.id', 'left')
            ->groupBy('p.id')->orderBy('p.orden')
            ->get()->getResultArray();

        return view('admin/configuracion/index', [
            'title'  => 'Configuración — Admin',
            'planes' => $planes,
        ]);
    }

    /** POST /admin/configuracion/planes/{id} — actualiza precio y límites (-1 = ilimitado). */
    public function guardarPlan(int $id)
    {
        $db = Database::connect();
        $plan = $db->table('planes')->where('id', $id)->get()->getRowArray();
        if (!$plan) {
            return redirect()->to('/admin/configuracion')->with('error', 'Plan no encontrado.');
        }

        $db->table('planes')->where('id', $id)->update([
            'precio_mensual'       => (float) $this->request->getPost('precio_mensual'),
            'max_creditos_activos' => (int) $this->request->getPost('max_creditos_activos'),
            'max_empleados'        => (int) $this->request->getPost('max_empleados'),
            'max_usuarios'         => (int) $this->request->getPost('max_usuarios'),
            'updated_at'           => date('Y-m-d H:i:s'),
        ]);

        return redirect()->to('/admin/configuracion')
            ->with('success', 'Plan "' . $plan['nombre'] . '" actualizado.');
    }
}
