<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\IngresoModel;
use App\Services\Partner\IngresoService;

/**
 * Control de ingresos — ventas (con recibo e IVA), donaciones y otros.
 * Capa HTTP; reglas en IngresoService. Permiso: caja.ingresos.
 */
class IngresoController extends BaseController
{
    private IngresoService $svc;

    public function __construct()
    {
        $this->svc = new IngresoService();
    }

    /** GET /finanzas/ingresos — listado con filtros + métricas + modal. */
    public function index()
    {
        $tenantId = (int) session('tenant_id');

        $filtros = [
            'desde'    => (string) ($this->request->getGet('desde') ?? ''),
            'hasta'    => (string) ($this->request->getGet('hasta') ?? ''),
            'tipo'     => (string) ($this->request->getGet('tipo') ?? ''),
            'buscar'   => trim((string) ($this->request->getGet('buscar') ?? '')),
            'anulados' => (bool) $this->request->getGet('anulados'),
        ];

        $rows = $this->svc->listar($tenantId, $filtros);

        return view('partner/finanzas/ingresos', [
            'title'     => 'Ingresos — ' . (session('tenant_name') ?? ''),
            'ingresos'  => $rows,
            'filtros'   => $filtros,
            'metricas'  => $this->svc->metricas($tenantId, $rows),
            'empleados' => $this->svc->empleados($tenantId),
            'tipos'     => IngresoModel::TIPOS,
            'metodos'   => IngresoModel::METODOS,
            'lblEstado' => IngresoModel::LABEL_ESTADO,
            'mon'       => session('moneda') ?? 'C$',
        ]);
    }

    /** POST /finanzas/ingresos — registra; si es VENTA va directo al recibo. */
    public function guardar()
    {
        $d = $this->datos();
        if (!$d['ok']) {
            return redirect()->back()->withInput()->with('error', $d['error']);
        }

        $r = $this->svc->guardar((int) session('tenant_id'), $d['data'], (int) session('user_id'));
        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }

        // Venta → directo al voucher para imprimir/compartir el recibo
        if ($d['data']['tipo'] === IngresoModel::TIPO_VENTA) {
            return redirect()->to('/finanzas/ingresos/' . $r['id'] . '/recibo')
                ->with('success', 'Venta registrada — recibo generado.');
        }

        return redirect()->to($this->volver())
            ->with('success', 'Ingreso registrado.');
    }

    /** POST /finanzas/ingresos/{id}/editar — edita un ingreso activo. */
    public function actualizar(int $id)
    {
        $d = $this->datos();
        if (!$d['ok']) {
            return redirect()->back()->withInput()->with('error', $d['error']);
        }

        $r = $this->svc->actualizar((int) session('tenant_id'), $id, $d['data']);

        return redirect()->to($this->volver())
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Ingreso actualizado.' : $r['error']);
    }

    /** POST /finanzas/ingresos/{id}/anular — lo saca de los totales. */
    public function anular(int $id)
    {
        $r = $this->svc->anular((int) session('tenant_id'), $id);

        return redirect()->to($this->volver())
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Ingreso anulado.' : $r['error']);
    }

    /** GET /finanzas/ingresos/{id}/recibo — voucher imprimible (solo VENTA). */
    public function recibo(int $id)
    {
        $data = $this->svc->recibo((int) session('tenant_id'), $id);
        if (!$data) {
            return redirect()->to('/finanzas/ingresos')->with('error', 'Recibo no encontrado.');
        }
        if ($data['ingreso']['tipo'] !== IngresoModel::TIPO_VENTA) {
            return redirect()->to('/finanzas/ingresos')
                ->with('error', 'Solo las ventas generan recibo.');
        }

        $data['title']    = 'Recibo ' . $data['reciboNum'];
        $data['metodos']  = IngresoModel::METODOS;
        $data['lblTipo']  = IngresoModel::TIPOS;
        $data['mon']      = session('moneda') ?? 'C$';
        $data['volver']   = 'finanzas/ingresos';
        return view('partner/finanzas/recibo_ingreso', $data);
    }

    // ---------------------------------------------------------------
    // Internas
    // ---------------------------------------------------------------

    /** Lee y valida los campos del formulario de ingreso. */
    private function datos(): array
    {
        if (!$this->validate([
            'tipo'        => 'required|in_list[VENTA,DONACION,OTRO]',
            'fecha'       => 'required|valid_date',
            'monto'       => 'required|numeric|greater_than[0]',
            'iva_pct'     => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[100]',
            'metodo'      => 'required|in_list[EFECTIVO,TRANSFERENCIA,DEPOSITO,OTRO]',
            'concepto'    => 'required|max_length[150]',
            'descripcion' => 'permit_empty|max_length[500]',
            'referencia'  => 'permit_empty|max_length[100]',
            'empleado_id' => 'permit_empty|is_natural',
        ])) {
            return ['ok' => false,
                    'error' => 'Revise los datos: ' . implode(' ', $this->validator->getErrors())];
        }

        return ['ok' => true, 'data' => $this->request->getPost([
            'tipo', 'fecha', 'monto', 'iva_pct', 'metodo', 'concepto',
            'descripcion', 'referencia', 'empleado_id',
        ])];
    }

    /** Vuelve al listado respetando los filtros que traía la pantalla. */
    private function volver(): string
    {
        $volver = trim((string) $this->request->getPost('volver'));
        return ($volver !== '' && str_starts_with($volver, '/')) ? $volver : '/finanzas/ingresos';
    }
}
