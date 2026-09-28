<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\GastoModel;
use App\Services\Partner\GastoService;

/**
 * Control de gastos — egresos operativos por categoría.
 * Capa HTTP; reglas en GastoService. Permiso: caja.gastos.
 */
class GastoController extends BaseController
{
    private GastoService $svc;

    public function __construct()
    {
        $this->svc = new GastoService();
    }

    /** GET /finanzas/gastos — listado con filtros + métricas + modales. */
    public function index()
    {
        $tenantId = (int) session('tenant_id');

        $filtros = [
            'desde'        => (string) ($this->request->getGet('desde') ?? ''),
            'hasta'        => (string) ($this->request->getGet('hasta') ?? ''),
            'categoria_id' => (int) ($this->request->getGet('categoria') ?? 0),
            'buscar'       => trim((string) ($this->request->getGet('buscar') ?? '')),
            'anulados'     => (bool) $this->request->getGet('anulados'),
        ];

        $rows = $this->svc->listar($tenantId, $filtros);

        return view('partner/finanzas/gastos', [
            'title'      => 'Gastos — ' . (session('tenant_name') ?? ''),
            'gastos'     => $rows,
            'filtros'    => $filtros,
            'metricas'   => $this->svc->metricas($tenantId, $filtros, $rows),
            'categorias' => $this->svc->categorias($tenantId),
            'empleados'  => $this->svc->empleados($tenantId),
            'metodos'    => GastoModel::METODOS,
            'lblEstado'  => GastoModel::LABEL_ESTADO,
            'mon'        => session('moneda') ?? 'C$',
        ]);
    }

    /** POST /finanzas/gastos — registra el gasto. */
    public function guardar()
    {
        $d = $this->datos();
        if (!$d['ok']) {
            return redirect()->back()->withInput()->with('error', $d['error']);
        }

        $r = $this->svc->guardar((int) session('tenant_id'), $d['data'], (int) session('user_id'));

        return redirect()->to($this->volver())
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Gasto registrado.' : $r['error']);
    }

    /** POST /finanzas/gastos/{id}/editar — edita un gasto activo. */
    public function actualizar(int $id)
    {
        $d = $this->datos();
        if (!$d['ok']) {
            return redirect()->back()->withInput()->with('error', $d['error']);
        }

        $r = $this->svc->actualizar((int) session('tenant_id'), $id, $d['data']);

        return redirect()->to($this->volver())
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Gasto actualizado.' : $r['error']);
    }

    /** POST /finanzas/gastos/{id}/anular — lo saca de los totales. */
    public function anular(int $id)
    {
        $r = $this->svc->anular((int) session('tenant_id'), $id);

        return redirect()->to($this->volver())
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Gasto anulado.' : $r['error']);
    }

    /** POST /finanzas/gastos/categorias — crea un tipo de gasto nuevo. */
    public function guardarCategoria()
    {
        $nombre = trim((string) $this->request->getPost('nombre'));
        $desc   = trim((string) $this->request->getPost('descripcion'));

        $r = $this->svc->guardarCategoria((int) session('tenant_id'), $nombre, $desc);

        return redirect()->to('/finanzas/gastos')
            ->with($r['ok'] ? 'success' : 'error', $r['ok'] ? $r['msg'] : $r['error']);
    }

    /** POST /finanzas/gastos/categorias/{id}/toggle — activa/inactiva. */
    public function toggleCategoria(int $id)
    {
        $r = $this->svc->toggleCategoria((int) session('tenant_id'), $id);

        return redirect()->to('/finanzas/gastos')
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Categoría actualizada.' : $r['error']);
    }

    // ---------------------------------------------------------------
    // Internas
    // ---------------------------------------------------------------

    /** Lee y valida los campos del formulario de gasto. */
    private function datos(): array
    {
        if (!$this->validate([
            'categoria_id' => 'required|is_natural_no_zero',
            'fecha'        => 'required|valid_date',
            'monto'        => 'required|numeric|greater_than[0]',
            'metodo'       => 'required|in_list[EFECTIVO,TRANSFERENCIA,DEPOSITO,OTRO]',
            'concepto'     => 'required|max_length[150]',
            'descripcion'  => 'permit_empty|max_length[500]',
            'referencia'   => 'permit_empty|max_length[100]',
            'empleado_id'  => 'permit_empty|is_natural',
        ])) {
            return ['ok' => false,
                    'error' => 'Revise los datos: ' . implode(' ', $this->validator->getErrors())];
        }

        return ['ok' => true, 'data' => $this->request->getPost([
            'categoria_id', 'fecha', 'monto', 'metodo', 'concepto',
            'descripcion', 'referencia', 'empleado_id',
        ])];
    }

    /** Vuelve al listado respetando los filtros que traía la pantalla. */
    private function volver(): string
    {
        $volver = trim((string) $this->request->getPost('volver'));
        return ($volver !== '' && str_starts_with($volver, '/')) ? $volver : '/finanzas/gastos';
    }
}
