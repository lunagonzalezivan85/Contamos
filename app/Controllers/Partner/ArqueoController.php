<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\ArqueoModel;
use App\Services\Partner\ArqueoService;

/**
 * Arqueo de caja — cierre diario del efectivo del gestor.
 * Capa HTTP; el cálculo de movimientos y el cierre viven en ArqueoService.
 * Permiso: caja.arqueo.
 */
class ArqueoController extends BaseController
{
    private ArqueoService $svc;

    public function __construct()
    {
        $this->svc = new ArqueoService();
    }

    /** GET /finanzas/arqueo — selector día+gestor, resumen calculado e historial. */
    public function index()
    {
        $tenantId  = (int) session('tenant_id');
        $fecha     = (string) ($this->request->getGet('fecha') ?: date('Y-m-d'));
        $empleadoId = (int) ($this->request->getGet('empleado') ?: 0);
        $gestores  = $this->svc->gestores($tenantId);

        // Sin gestor elegido, tomar el primero de la lista
        if ($empleadoId <= 0 && $gestores) {
            $empleadoId = (int) $gestores[0]['id'];
        }

        $resumen = null;
        $gestor  = null;
        if ($empleadoId > 0) {
            $gestor  = $this->svc->empleado($tenantId, $empleadoId);
            $resumen = $gestor ? $this->svc->resumenDia($tenantId, $empleadoId, $fecha) : null;
        }

        return view('partner/finanzas/arqueo', [
            'title'      => 'Arqueo de caja — Contamos',
            'gestores'   => $gestores,
            'gestor'     => $gestor,
            'fecha'      => $fecha,
            'resumen'    => $resumen,
            'historial'  => $this->svc->historial($tenantId),
            'lblEstado'  => ArqueoModel::LABEL_ESTADO,
            'mon'        => session('moneda') ?? 'C$',
        ]);
    }

    /** POST /finanzas/arqueo — guarda el cierre del día (recalcula si ya existía). */
    public function guardar()
    {
        if (!$this->validate([
            'empleado_id' => 'required|is_natural_no_zero',
            'fecha'       => 'required|valid_date',
            'contado'     => 'required|numeric|greater_than_equal_to[0]',
            'observacion' => 'permit_empty|max_length[255]',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $d = $this->request->getPost(['empleado_id', 'fecha', 'contado', 'observacion']);
        $r = $this->svc->guardar(
            (int) session('tenant_id'), (int) $d['empleado_id'], (string) $d['fecha'],
            (float) $d['contado'], trim((string) ($d['observacion'] ?? '')), (int) session('user_id')
        );

        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }
        $msg = $r['estado'] === ArqueoModel::CUADRADO
            ? 'Arqueo cuadrado — el efectivo coincide con los movimientos.'
            : 'Arqueo guardado con diferencia de ' . number_format($r['diferencia'], 2) . '.';
        return redirect()->to('/finanzas/arqueo?fecha=' . $d['fecha'] . '&empleado=' . $d['empleado_id'])
            ->with('success', $msg);
    }
}
