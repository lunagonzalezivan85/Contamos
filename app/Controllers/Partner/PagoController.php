<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\PagoModel;
use App\Services\Partner\PagoService;

/**
 * Bandeja de pagos - capa HTTP.
 * Lógica (revisión ? aplicar a cuotas / rechazar) en Services/Partner/PagoService.
 * Permisos: pagos.ver (bandeja), pagos.aprobar (aprobar/rechazar).
 */
class PagoController extends BaseController
{
    private PagoService $svc;

    public function __construct()
    {
        $this->svc = new PagoService();
    }

    /** GET /pagos - bandeja de pagos en revisión + últimos resueltos. */
    public function index()
    {
        $tenantId = (int) session('tenant_id');
        $f        = [
            'desde' => (string) $this->request->getGet('desde'),
            'hasta' => (string) $this->request->getGet('hasta'),
        ];
        $bandeja  = $this->svc->bandeja($tenantId, $f);
        $permisos = session('permisos') ?? [];

        return view('partner/pagos/index', [
            'title'        => 'Pagos - Contamos',
            'revision'     => $bandeja['revision'],
            'recientes'    => $bandeja['recientes'],
            'promesas'     => $bandeja['promesas'],
            'metricas'     => $this->svc->metricas($tenantId),
            'creditos'     => $this->svc->creditosParaAbono($tenantId),
            'filtros'      => $f,
            'tab'          => in_array($this->request->getGet('tab'), ['revision', 'aplicados'], true)
                ? $this->request->getGet('tab') : 'revision',
            'puede_aprobar'  => in_array('pagos.aprobar', $permisos, true),
            'puede_revertir'  => in_array('pagos.revertir', $permisos, true),
            'puede_registrar' => in_array('pagos.registrar', $permisos, true),
            'lblEstado'    => PagoModel::LABEL_ESTADO,
            'metodos'      => PagoModel::METODOS_LBL,
            'mon'          => $this->svc->moneda($tenantId),
        ]);
    }

    /** POST /pagos/registrar - abono de oficina (queda EN REVISIÓN). */
    public function registrar()
    {
        $esPromesa = $this->request->getPost('tipo') === 'PROMESA';

        if (!$this->validate([
            'solicitud_id' => 'required|is_natural_no_zero',
            'monto'        => 'required|numeric|greater_than[0]',
            'metodo'       => 'permit_empty|in_list[EFECTIVO,TRANSFERENCIA]',
            'cuota_id'     => 'permit_empty|is_natural_no_zero',
            'fecha_hora'   => $esPromesa ? 'required|valid_date[Y-m-d]' : 'permit_empty|valid_date[Y-m-d\TH:i]',
            'observacion'  => 'permit_empty|max_length[255]',
        ])) {
            return redirect()->to('/pagos')
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $d = $this->request->getPost();
        $d['tipo'] = $esPromesa ? 'PROMESA' : 'PAGO';
        if ($esPromesa) {
            $d['fecha_hora']  = $d['fecha_hora'] . ' 09:00:00';
            $d['observacion'] = trim('Promesa de pago. ' . ($d['observacion'] ?? ''));
        } elseif (!empty($d['fecha_hora'])) {
            $d['fecha_hora'] = str_replace('T', ' ', (string) $d['fecha_hora']) . ':00';
        }
        $r = $this->svc->registrarPago(
            (int) session('tenant_id'), (int) $d['solicitud_id'], $d,
            null, (int) session('user_id')
        );

        if (!$r['ok']) {
            return redirect()->to('/pagos')->with('error', $r['error']);
        }
        // Pago registrado ? directo al voucher para imprimir el recibo
        if (!$esPromesa) {
            return redirect()->to('/pagos/' . (int) $r['pago_id'] . '/recibo');
        }
        return redirect()->to('/pagos')
            ->with('success', 'Promesa de pago registrada - se convierte en cobro cuando el cliente pague.');
    }

    /** POST /pagos/{id}/aprobar - confirma el pago y lo aplica a las cuotas. */
    public function aprobar(int $id)
    {
        $r = $this->svc->aprobarPago((int) session('tenant_id'), $id, (int) session('user_id'));
        return redirect()->to('/pagos')
            ->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Pago aprobado y aplicado al plan de cuotas.' : $r['error']);
    }

    /** POST /pagos/{id}/rechazar - rechaza el pago sin tocar las cuotas. */
    public function rechazar(int $id)
    {
        $r = $this->svc->rechazarPago((int) session('tenant_id'), $id, (int) session('user_id'));
        return redirect()->to('/pagos')
            ->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Pago rechazado - no se aplicó a las cuotas.' : $r['error']);
    }

    /** GET /pagos/{id}/recibo - voucher imprimible del cobro. */
    public function recibo(int $id)
    {
        $data = $this->svc->reciboPago((int) session('tenant_id'), $id);
        if (!$data || ($data['pago']['tipo'] ?? 'PAGO') !== 'PAGO') {
            return redirect()->to('/pagos')->with('error', 'Recibo no encontrado.');
        }
        $data['title']     = 'Recibo ' . PagoModel::reciboCode($data['pago'], $data['tenant']['nombre'] ?? '');
        $data['reciboNum'] = PagoModel::reciboCode($data['pago'], $data['tenant']['nombre'] ?? '');
        $data['lblEstado'] = PagoModel::LABEL_ESTADO;
        $data['metodos']   = PagoModel::METODOS_LBL;
        return view('partner/pagos/recibo', $data);
    }

    /** GET /finanzas/pagos-dia?fecha= - cobros del día desglosados en capital/interés/mora. */
    public function pagosDia()
    {
        $tenantId = (int) session('tenant_id');
        $fecha    = trim((string) $this->request->getGet('fecha'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d')) {
            $fecha = date('Y-m-d');
        }
        $data = $this->svc->pagosDelDia($tenantId, $fecha);

        return view('partner/reportes/pagos_dia', [
            'title'     => 'Pagos del día - Contamos',
            'rows'      => $data['rows'],
            'totales'   => $data['totales'],
            'fecha'     => $fecha,
            'mon'       => $this->svc->moneda($tenantId),
            'metodos'   => PagoModel::METODOS_LBL,
            'lblEstado' => PagoModel::LABEL_ESTADO,
        ]);
    }

    /** GET /finanzas/recuperacion - créditos con cuotas vencidas por gestor. */
    public function recuperacion()
    {
        $tenantId = (int) session('tenant_id');
        $buscar   = trim((string) $this->request->getGet('q'));
        $fecha    = trim((string) $this->request->getGet('fecha'));
        $data     = $this->svc->recuperacion($tenantId, $buscar, $fecha);

        return view('partner/finanzas/recuperacion', [
            'title' => 'Recuperación - Contamos',
            'filas' => $data['rows'],
            'total' => $data['total_vencido'],
            'hoy'   => $data['hoy'],
            'buscar'=> $buscar,
            'mon'   => $this->svc->moneda($tenantId),
        ]);
    }

    /** POST /pagos/{id}/revertir - genera el contra-pago negativo (queda en revisión). */
    public function revertir(int $id)
    {
        $obs = trim((string) $this->request->getPost('observacion'));
        if ($obs === '') {
            return redirect()->to('/pagos?tab=aplicados')
                ->with('error', 'La reversión requiere una observación.');
        }
        $r = $this->svc->revertirPago((int) session('tenant_id'), $id, $obs, (int) session('user_id'));

        return redirect()->to('/pagos?tab=aplicados')
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Reversión registrada - queda EN REVISIÓN hasta aprobarse.' : $r['error']);
    }

    /** POST /pagos/{id}/cumplio - la promesa se pagó: pasa a cobro en revisión. */
    public function cumplio(int $id)
    {
        $r = $this->svc->promesaACobro((int) session('tenant_id'), $id, (int) session('user_id'));
        return redirect()->to('/pagos')
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Promesa cumplida - el cobro quedó en revisión para aprobar.' : $r['error']);
    }
}
