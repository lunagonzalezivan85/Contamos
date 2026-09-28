<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\MetaService;

/**
 * Plan de metas por gestor (Finanzas → Metas).
 * Permiso: metas.plan. Las metas con métrica AUTO calculan su avance
 * al vuelo; las MANUAL registran avance en el formulario.
 */
class MetaController extends BaseController
{
    private MetaService $svc;

    public function __construct()
    {
        $this->svc = new MetaService();
    }

    public function index()
    {
        $tenantId = (int) session('tenant_id');
        $this->svc->asegurarMetricas($tenantId);

        $filtros = [
            'periodo'          => $this->request->getGet('periodo') ?: date('Y-m'),
            'empleado_id'      => $this->request->getGet('empleado') ?: null,
            'estado'           => $this->request->getGet('estado') ?: null,
            'incluir_anuladas' => (bool) $this->request->getGet('anuladas'),
        ];

        $metas = $this->svc->listar($tenantId, $filtros);

        return view('partner/finanzas/metas', [
            'title'     => 'Plan de metas',
            'metas'     => $metas,
            'resumen'   => $this->svc->resumen($metas),
            'empleados' => $this->svc->empleados($tenantId),
            'metricas'  => $this->svc->metricasActivas($tenantId),
            'todas'     => $this->svc->metricasTodas($tenantId),
            'unidades'  => \App\Models\MetaMetricaModel::UNIDADES,
            'modos'     => \App\Models\MetaMetricaModel::MODOS,
            'filtros'   => $filtros,
            'mon'       => session('tenant_moneda') ?? 'C$',
        ]);
    }

    public function guardar()
    {
        $tenantId = (int) session('tenant_id');
        $err = $this->svc->guardar($tenantId, $this->datos(), (int) session('user_id'));
        return $this->volver($err ?: 'Meta registrada.', $err === '');
    }

    public function actualizar(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $err = $this->svc->actualizar($tenantId, $id, $this->datos());
        return $this->volver($err ?: 'Meta actualizada.', $err === '');
    }

    public function anular(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $err = $this->svc->anular($tenantId, $id);
        return $this->volver($err ?: 'Meta anulada.', $err === '');
    }

    public function guardarMetrica()
    {
        $tenantId = (int) session('tenant_id');
        $err = $this->svc->guardarMetrica($tenantId, [
            'nombre'  => $this->request->getPost('nombre'),
            'unidad'  => $this->request->getPost('unidad'),
            'modo'    => $this->request->getPost('modo'),
            'formula' => $this->request->getPost('formula'),
        ]);
        return $this->volver($err ?: 'Métrica agregada.', $err === '');
    }

    public function toggleMetrica(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $err = $this->svc->toggleMetrica($tenantId, $id);
        return $this->volver($err ?: 'Métrica actualizada.', $err === '');
    }

    // ---------------- internos ----------------

    private function datos(): array
    {
        return [
            'empleado_id' => $this->request->getPost('empleado_id'),
            'metrica_id'  => $this->request->getPost('metrica_id'),
            'periodo'     => $this->request->getPost('periodo'),
            'meta'        => $this->request->getPost('meta'),
            'avance'      => $this->request->getPost('avance'),
            'notas'       => trim((string) $this->request->getPost('notas')),
        ];
    }

    private function volver(string $msg, bool $exito)
    {
        $url = (string) $this->request->getPost('volver');
        if ($url === '' || !str_starts_with($url, '/')) $url = '/finanzas/metas';
        return redirect()->to($url)->with($exito ? 'success' : 'error', $msg);
    }
}
