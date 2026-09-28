<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\AsistenciaService;
use App\Services\Partner\PortalService;

/**
 * Asistencia del personal — dos superficies:
 *
 * 1) Kiosco público  /{slug}/asistencia — marcación por carnet+PIN,
 *    sin sesión. Una sola acción: el sistema decide entrada o salida.
 * 2) Panel de oficina /asistencia — listado, métricas y corrección
 *    de jornadas (permiso empleados.asistencia).
 */
class AsistenciaController extends BaseController
{
    private AsistenciaService $svc;
    private PortalService     $portal;

    public function __construct()
    {
        $this->svc    = new AsistenciaService();
        $this->portal = new PortalService();
    }

    /** GET /{slug}/asistencia — kiosco de marcación (pantalla pública). */
    public function kiosco(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/');
        }
        return view('partner/asistencia/kiosco', [
            'title'     => 'Asistencia — ' . $tenant['nombre'],
            'tenant'    => $tenant,
            'slug'      => $slug,
            'result'    => session()->getFlashdata('marca'),
            'error'     => session()->getFlashdata('error'),
        ]);
    }

    /** POST /{slug}/asistencia — procesa la marcación (entrada o salida). */
    public function marcar(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/');
        }

        $r = $this->svc->marcarPorCarnet(
            (int) $tenant['id'],
            (string) $this->request->getPost('carnet'),
            (string) $this->request->getPost('pin'),
            $this->request->getIPAddress()
        );

        return redirect()->to('/' . $slug . '/asistencia')
            ->with($r['ok'] ? 'marca' : 'error', $r['ok'] ? $r : ($r['error'] ?? 'No se pudo registrar.'));
    }

    /** GET /{slug}/asistencia/pin-longitud?carnet=X — n.º de casillas del PIN. */
    public function pinLongitud(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }
        $len = $this->svc->longitudPin(
            (int) $tenant['id'], (string) $this->request->getGet('carnet'));
        return $this->response->setJSON(['ok' => true, 'len' => $len]);
    }

    /** GET /asistencia — panel del admin (listado + quién está adentro). */
    public function index()
    {
        $tenantId = (int) session('tenant_id');
        $f = [
            'desde'       => (string) $this->request->getGet('desde'),
            'hasta'       => (string) $this->request->getGet('hasta'),
            'empleado_id' => (int) $this->request->getGet('empleado'),
            'abiertas'    => (bool) $this->request->getGet('abiertas'),
            'anuladas'    => (bool) $this->request->getGet('anuladas'),
        ];

        $tenant = (new \App\Models\TenantModel())->find($tenantId) ?? [];

        return view('partner/asistencia/index', [
            'title'      => 'Asistencia — Contamos',
            'filas'      => $this->svc->listado($tenantId, $f),
            'metricas'   => $this->svc->metricas($tenantId),
            'empleados'  => $this->svc->empleados($tenantId),
            'filtros'    => $f,
            'kioscoUrl'  => '/' . ($tenant['slug'] ?? '') . '/asistencia',
            'horaInicio' => substr((string) ($tenant['hora_inicio'] ?? ''), 0, 5),
        ]);
    }

    /** POST /asistencia — alta manual de una jornada olvidada. */
    public function guardar()
    {
        if (!$this->validate([
            'empleado_id' => 'required|integer',
            'entrada'     => 'required|max_length[20]',
            'salida'      => 'permit_empty|max_length[20]',
            'observacion' => 'permit_empty|max_length[255]',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->guardarManual(
            (int) session('tenant_id'), $this->request->getPost(), (int) session('user_id'));

        return redirect()->to('/asistencia')
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Marcación registrada.' : $r['error']);
    }

    /** POST /asistencia/{id}/editar — corrige entrada/salida de una jornada. */
    public function actualizar(int $id)
    {
        if (!$this->validate([
            'entrada'     => 'permit_empty|max_length[20]',
            'salida'      => 'permit_empty|max_length[20]',
            'observacion' => 'permit_empty|max_length[255]',
        ])) {
            return redirect()->back()->with('error', 'Revise los datos de la marcación.');
        }

        $r = $this->svc->actualizar(
            (int) session('tenant_id'), $id, $this->request->getPost(), (int) session('user_id'));

        return redirect()->to('/asistencia')
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Marcación corregida.' : $r['error']);
    }

    /** POST /asistencia/{id}/anular — anula la jornada (no cuenta). */
    public function anular(int $id)
    {
        $r = $this->svc->anular(
            (int) session('tenant_id'), $id, (int) session('user_id'));

        return redirect()->to('/asistencia')
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Marcación anulada.' : $r['error']);
    }
}
