<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\SolicitudModel;
use App\Services\Partner\PagoService;
use App\Services\Partner\SolicitudService;

/**
 * Herramientas del tenant:
 * - /herramientas/calculadora   simulador de cuota francesa (sin guardar)
 * - /herramientas/documentacion selector de solicitud → genera plan/contrato/garantías
 */
class HerramientasController extends BaseController
{
    /** GET /herramientas/calculadora — simulador de cuota (mismo criterio que el plan francés). */
    public function calculadora()
    {
        $tenantId = (int) session('tenant_id');
        $tenant   = db_connect()->table('tenants')->where('id', $tenantId)->get()->getRowArray() ?? [];

        return view('partner/herramientas/calculadora', [
            'title'    => 'Calculadora de cuotas - Contamos',
            'mon'      => (new PagoService())->moneda($tenantId),
            'tasa'     => (float) ($tenant['tasa_interes'] ?? 3),
            'plazoMax' => (int) ($tenant['plazo_meses_max'] ?? 24),
            'tipo'     => $tenant['tipo_calculo'] ?? 'FLAT',
        ]);
    }

    /** GET /herramientas/documentacion — elegir solicitud y abrir su paquete de documentos. */
    public function documentacion()
    {
        $tenantId = (int) session('tenant_id');
        $buscar   = trim((string) $this->request->getGet('q'));

        $model = new SolicitudModel();
        $model->select('solicitudes.id, solicitudes.codigo_credito, solicitudes.estado, solicitudes.monto,
                      solicitudes.monto_aprobado, solicitudes.fecha_desembolso, solicitudes.created_at,
                      personas.nombres, personas.apellidos, personas.cedula')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->where('solicitudes.tenant_id', $tenantId)
            ->whereIn('solicitudes.estado', [
                SolicitudModel::APROBADA, SolicitudModel::DESEMBOLSO,
                SolicitudModel::ACTIVO, SolicitudModel::LIQUIDADO,
            ]);
        if ($buscar !== '') {
            $model->groupStart()
                ->like('personas.nombres', $buscar)
                ->orLike('personas.apellidos', $buscar)
                ->orLike('personas.cedula', $buscar)
                ->orLike('solicitudes.codigo_credito', $buscar)
                ->groupEnd();
        }
        $rows = $model->orderBy('solicitudes.id', 'DESC')->paginate(15);
        $model->pager->only(['q']);

        return view('partner/herramientas/documentacion', [
            'title'    => 'Generar documentación - Contamos',
            'filas'    => $rows,
            'pager'    => $model->pager,
            'buscar'   => $buscar,
            'lblEstado'=> SolicitudModel::LABEL_ESTADO,
            'mon'      => (new PagoService())->moneda($tenantId),
        ]);
    }
}
