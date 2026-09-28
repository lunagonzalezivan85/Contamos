<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\PagoModel;
use App\Services\Partner\PagoService;
use App\Services\Partner\SolicitudService;

/**
 * Créditos vigentes (solicitudes ACTIVO) - capa HTTP.
 * Plan de cuotas persistido + registro de abonos (nace en REVISION).
 * Permisos: creditos.ver / pagos.registrar.
 */
class CreditoController extends BaseController
{
    private PagoService $svc;

    public function __construct()
    {
        $this->svc = new PagoService();
    }

    /** GET /creditos - créditos activos del tenant con saldo y próxima cuota. */
    public function index()
    {
        $tenantId = (int) session('tenant_id');
        $buscar   = trim((string) $this->request->getGet('q'));
        $lista    = $this->svc->creditosActivos($tenantId, $buscar);

        return view('partner/creditos/index', [
            'title'  => 'Créditos - Contamos',
            'filas'  => $lista['rows'],
            'pager'  => $lista['pager'],
            'buscar' => $buscar,
            'mon'    => $this->svc->moneda($tenantId),
        ]);
    }

    /** GET /credito/cartera - cartera vigente con salud: saldo, vencido y días de atraso. */
    public function cartera()
    {
        $tenantId = (int) session('tenant_id');
        $buscar   = trim((string) $this->request->getGet('q'));
        $solo     = $this->request->getGet('mora') ? 'mora' : '';
        $data     = $this->svc->cartera($tenantId, $buscar, $solo);

        return view('partner/creditos/cartera', [
            'title'   => 'Cartera - Contamos',
            'filas'   => $data['rows'],
            'totales' => $data['totales'],
            'buscar'  => $buscar,
            'soloMora' => $solo === 'mora',
            'mon'     => $this->svc->moneda($tenantId),
        ]);
    }

    /** GET /creditos/{id} - plan de cuotas + historial de pagos + form de abono. */
    public function ver(int $id)
    {
        $ficha = $this->svc->fichaCredito((int) session('tenant_id'), $id);
        if (!$ficha) {
            return redirect()->to('/creditos')->with('error', 'Crédito no encontrado o no está activo.');
        }

        return view('partner/creditos/ver', [
            'title'      => 'Crédito ' . ($ficha['sol']['codigo_credito'] ?? '#' . $id) . ' - Contamos',
            's'          => $ficha['sol'],
            'cuotas'     => $ficha['cuotas'],
            'pagos'      => $ficha['pagos'],
            'saldo'      => $ficha['saldo'],
            'hoy'        => $ficha['hoy'],
            'mora'        => $ficha['mora'],
            'pronto'      => $ficha['pronto'],
            'saldo_favor' => $ficha['saldo_favor'],
            'puede_pagar' => in_array('pagos.registrar', session('permisos') ?? [], true),
            'puede_refinanciar' => in_array('solicitudes.aprobar', session('permisos') ?? [], true),
            'tiposCalc'  => SolicitudService::TIPOS_CALCULO,
            'metodos'    => PagoModel::METODOS_LBL,
            'lblPago'    => PagoModel::LABEL_ESTADO,
            'mon'        => $this->svc->moneda((int) session('tenant_id')),
            'lblFreq'    => SolicitudService::LBL_FREQ,
        ]);
    }

    /** POST /creditos/{id}/abonar - registra un abono que queda EN REVISIÓN. */
    public function abonar(int $id)
    {
        if (!$this->validate([
            'monto'      => 'required|numeric|greater_than[0]',
            'metodo'     => 'permit_empty|in_list[EFECTIVO,TRANSFERENCIA]',
            'cuota_id'   => 'permit_empty|is_natural_no_zero',
            'observacion' => 'permit_empty|max_length[255]',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->registrarPago(
            (int) session('tenant_id'), $id,
            $this->request->getPost(),
            null,                       // no cobró un gestor de campo
            (int) session('user_id')
        );

        return redirect()->to('/creditos/' . $id)
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Abono registrado - queda EN REVISIÓN hasta que se apruebe en Pagos.' : $r['error']);
    }

    /** POST /creditos/{id}/reestructurar - regenera el plan pendiente con nuevos términos. */
    public function reestructurar(int $id)
    {
        if (!$this->validate([
            'tasa_aprobada'       => 'required|numeric|greater_than_equal_to[0]',
            'plazo_aprobado'      => 'required|integer|greater_than_equal_to[1]',
            'frecuencia_aprobada' => 'required|in_list[D,DI,S,Q,M,P]',
            'tipo_calculo'        => 'permit_empty|in_list[FRANCES,FLAT,ALEMAN,ANTICIPADO]',
            'paso_dias'           => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[365]',
            'fecha_primer_pago'   => 'required|valid_date',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->reestructurar((int) session('tenant_id'), $id, $this->request->getPost());

        return redirect()->to('/creditos/' . $id)
            ->with($r['ok'] ? 'success' : 'error',
                $r['ok'] ? 'Crédito reestructurado - plan nuevo generado sobre '
                    . $this->svc->moneda((int) session('tenant_id')) . ' '
                    . number_format($r['base'] ?? 0, 2) . '.' : $r['error']);
    }

    /** POST /creditos/{id}/refinanciar - crea la solicitud de refinanciamiento. */
    public function refinanciar(int $id)
    {
        if (!$this->validate([
            'monto'        => 'permit_empty|numeric|greater_than[0]',
            'plazo_meses'  => 'permit_empty|integer|greater_than_equal_to[1]',
            'tasa_mensual' => 'permit_empty|numeric|greater_than_equal_to[0]',
            'frecuencia'   => 'permit_empty|in_list[D,DI,S,Q,M,P]',
            'tipo_calculo' => 'permit_empty|in_list[FRANCES,FLAT,ALEMAN,ANTICIPADO]',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->refinanciar((int) session('tenant_id'), $id, $this->request->getPost());
        if (!$r['ok']) {
            return redirect()->back()->with('error', $r['error']);
        }

        return redirect()->to('/credito/solicitudes/' . $r['sol_id'])
            ->with('success', 'Solicitud de refinanciamiento creada - sigue el flujo normal (análisis ? aprobación ? desembolso). Al entregarse, este crédito queda liquidado.');
    }
}
