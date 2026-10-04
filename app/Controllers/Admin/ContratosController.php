<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Admin\ContratoService;

/**
 * Contratos de servicio SaaS — se generan desde /admin/leads (lead CONTACTADO)
 * y se muestran como vista imprimible con los T&C pactados.
 */
class ContratosController extends BaseController
{
    private ContratoService $svc;

    public function __construct()
    {
        $this->svc = new ContratoService();
    }

    /**
     * POST /admin/leads/{id}/contrato — genera el contrato con las
     * condiciones del modal (día de pago, gracia, cuenta, monto) y
     * marca el lead CONTRATADO. Redirige a la vista imprimible.
     */
    public function guardar(int $leadId)
    {
        $r = $this->svc->crear($leadId, $this->request->getPost());
        if (isset($r['error'])) {
            return redirect()->to('/admin/leads')->with('error', $r['error']);
        }

        return redirect()->to('/admin/contratos/' . $r['id'])
            ->with('success', 'Contrato generado — el lead quedó CONTRATADO.');
    }

    /** GET /admin/contratos/{id} — contrato imprimible con T&C. */
    public function ver(int $id)
    {
        $c = $this->svc->detalle($id);
        if (!$c) return redirect()->to('/admin/leads')->with('error', 'Contrato no encontrado.');

        return view('admin/contratos/ver', [
            'title' => 'Contrato #' . $id . ' — ' . $c['nombre'],
            'c'     => $c,
        ]);
    }
}
