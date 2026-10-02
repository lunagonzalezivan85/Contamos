<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\AccesoSolicitudModel;
use App\Services\Shared\LandingService;

/**
 * Leads — solicitudes de acceso/alta capturadas en la landing pública
 * (acceso_solicitudes). Los del formulario /alta traen codigo partner,
 * plan_estimado y detalle JSON de los sliders.
 */
class LeadsController extends BaseController
{
    public function index()
    {
        $estado = strtoupper((string) $this->request->getGet('estado'));
        if (!in_array($estado, AccesoSolicitudModel::ESTADOS, true)) $estado = '';

        $m = new AccesoSolicitudModel();
        $conteos = [];
        foreach ($m->select('estado, COUNT(*) AS n')->groupBy('estado')->findAll() as $r) {
            $conteos[$r['estado']] = (int) $r['n'];
        }

        if ($estado !== '') $m->where('estado', $estado);
        $leads = $m->orderBy('id', 'DESC')->findAll(200);

        foreach ($leads as &$l) {
            $l['det'] = $l['detalle'] ? (json_decode($l['detalle'], true) ?: null) : null;
        }

        return view('admin/leads/index', [
            'title'     => 'Leads — Solicitudes de alta',
            'leads'     => $leads,
            'estado'    => $estado,
            'conteos'   => $conteos,
            'planes'    => (new LandingService())->planes(),
            'contratos' => (new \App\Services\Admin\ContratoService())->mapaPorLead(),
        ]);
    }

    /**
     * POST /admin/leads/{id}/contactar — modal «Contactando»: sliders de
     * consumo + plan elegido → recalcula plan_estimado server-side y marca
     * el lead como CONTACTADO. Es la propuesta comercial que se negocia.
     */
    public function contactar(int $id)
    {
        $m    = new AccesoSolicitudModel();
        $lead = $m->find($id);
        if (!$lead) return redirect()->to('/admin/leads');

        $svc     = new LandingService();
        $planId  = (int) $this->request->getPost('plan_id') ?: null;
        $det     = [
            'usuarios'  => max(0, (int) $this->request->getPost('usuarios')),
            'clientes'  => max(0, (int) $this->request->getPost('clientes')),
            'creditos'  => max(0, (int) $this->request->getPost('creditos')),
            'empleados' => max(0, (int) $this->request->getPost('empleados')),
        ];
        $precio  = $svc->precioEstimado($det['usuarios'], $det['clientes'], $det['creditos'], $det['empleados'], $planId);
        $det['mensual'] = $precio;

        $m->update($id, [
            'plan_estimado' => $precio,
            'plan_id'       => $planId,
            'detalle'       => json_encode($det),
            'estado'        => AccesoSolicitudModel::CONTACTADO,
        ]);

        return redirect()->to('/admin/leads')->with('success',
            "Propuesta de {$lead['nombre']}: $" . number_format($precio, 2) . ' USD/mes — queda CONTACTADO.');
    }

    /**
     * POST /admin/leads/{id}/estado — marca el lead como contactado o lo
     * devuelve a pendiente (flujo: PENDIENTE → CONTACTADO).
     */
    public function estado(int $id)
    {
        $m    = new AccesoSolicitudModel();
        $lead = $m->find($id);
        if (!$lead) return redirect()->to('/admin/leads');

        // Solo alterna entre los estados abiertos — CONTRATADO/RECHAZADO son finales
        if (in_array($lead['estado'], [AccesoSolicitudModel::PENDIENTE, AccesoSolicitudModel::CONTACTADO], true)) {
            $nuevo = $lead['estado'] === AccesoSolicitudModel::PENDIENTE
                ? AccesoSolicitudModel::CONTACTADO : AccesoSolicitudModel::PENDIENTE;
            $m->update($id, ['estado' => $nuevo]);
        } else {
            return redirect()->to('/admin/leads')->with('error', "{$lead['nombre']} está {$lead['estado']} — no se puede reabrir.");
        }

        return redirect()->to('/admin/leads')->with('success', "{$lead['nombre']} → {$nuevo}");
    }

    /** POST /admin/leads/{id}/rechazar — descarta el lead (estados abiertos). */
    public function rechazar(int $id)
    {
        $m    = new AccesoSolicitudModel();
        $lead = $m->find($id);
        if (!$lead) return redirect()->to('/admin/leads');

        if (!in_array($lead['estado'], [AccesoSolicitudModel::PENDIENTE, AccesoSolicitudModel::CONTACTADO], true)) {
            return redirect()->to('/admin/leads')->with('error', "{$lead['nombre']} está {$lead['estado']} — no se puede rechazar.");
        }

        $m->update($id, ['estado' => AccesoSolicitudModel::RECHAZADO]);
        return redirect()->to('/admin/leads')->with('success', "{$lead['nombre']} → RECHAZADO");
    }
}
