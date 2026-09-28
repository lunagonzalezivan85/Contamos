<?php

namespace App\Services\Partner;

use App\Models\ClienteModel;
use App\Models\CuotaModel;
use App\Models\PagoModel;
use App\Models\SolicitudModel;

/**
 * Datos del dashboard del tenant.
 * KPIs accionables: cada chip lleva URL al módulo donde se resuelve lo que indica.
 */
class DashboardService
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    /** Contadores del tenant contra tablas reales. */
    public function contadores(int $tenantId): array
    {
        $sol   = new SolicitudModel();
        $hoy   = date('Y-m-d');
        $enProceso = [SolicitudModel::CONTACTO, SolicitudModel::CREADA,
                      SolicitudModel::REVISION, SolicitudModel::APROBADA];

        // Clientes cuyo persona no tiene ningún documento cargado
        $sinDocs = $this->db->table('clientes c')
            ->select('c.id')
            ->join('persona_documentos pd', 'pd.persona_id = c.persona_id', 'left')
            ->where('c.tenant_id', $tenantId)
            ->where('c.estado', 'ACTIVO')
            ->where('pd.id IS NULL', null, false)
            ->countAllResults();

        // Personas a cobrar: créditos con cuota pendiente/parcial ya vencida o hoy
        $porCobrar = $this->db->table('cuotas q')
            ->select('q.solicitud_id')
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->where('q.tenant_id', $tenantId)
            ->where('s.estado', SolicitudModel::ACTIVO)
            ->whereIn('q.estado', [CuotaModel::PENDIENTE, CuotaModel::PARCIAL])
            ->where('q.fecha_vence <=', $hoy)
            ->groupBy('q.solicitud_id')
            ->countAllResults();

        // Créditos con al menos una cuota vencida sin cubrir (mora)
        $enMora = $this->db->table('cuotas q')
            ->select('q.solicitud_id')
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->where('q.tenant_id', $tenantId)
            ->where('s.estado', SolicitudModel::ACTIVO)
            ->where('q.pagado < q.cuota', null, false)
            ->where('q.fecha_vence <', $hoy)
            ->groupBy('q.solicitud_id')
            ->countAllResults();

        $cobrado = $this->db->table('pagos')
            ->select('COALESCE(SUM(monto),0) AS monto', false)
            ->where('tenant_id', $tenantId)
            ->where('tipo', PagoModel::TIPO_PAGO)->where('estado', PagoModel::APLICADO)
            ->where('DATE(fecha_hora)', $hoy)
            ->get()->getRowArray();

        return [
            'clientes'         => (new ClienteModel())->where('tenant_id', $tenantId)->countAllResults(),
            'sin_docs'         => $sinDocs,
            'en_proceso'       => $sol->where('tenant_id', $tenantId)
                                      ->whereIn('estado', $enProceso)->countAllResults(),
            'por_aprobar'      => (new SolicitudModel())->where('tenant_id', $tenantId)
                                      ->where('estado', SolicitudModel::REVISION)->countAllResults(),
            'por_desembolsar'  => (new SolicitudModel())->where('tenant_id', $tenantId)
                                      ->where('estado', SolicitudModel::DESEMBOLSO)->countAllResults(),
            'por_cobrar'       => $porCobrar,
            'en_mora'          => $enMora,
            'cobrado_hoy'      => (float) ($cobrado['monto'] ?? 0),
            'pagos_revision'   => (new PagoModel())->where('tenant_id', $tenantId)
                                      ->where('tipo', PagoModel::TIPO_PAGO)
                                      ->where('estado', PagoModel::REVISION)->countAllResults(),
        ];
    }

    /** Chips-KPI del dashboard — etiqueta + valor + destino accionable. */
    public function chipsContadores(int $tenantId): array
    {
        $c   = $this->contadores($tenantId);
        $mon = number_format($c['cobrado_hoy'], 0);

        return [
            ['nombre' => 'Clientes',        'valor' => $c['clientes'],        'icono' => 'users',        'url' => base_url('socios/clientes')],
            ['nombre' => 'Sin documentos',  'valor' => $c['sin_docs'],        'icono' => 'file-text',    'url' => base_url('socios/clientes'), 'alerta' => $c['sin_docs'] > 0],
            ['nombre' => 'Por aprobar',     'valor' => $c['por_aprobar'],     'icono' => 'check-circle', 'url' => base_url('credito/solicitudes'), 'alerta' => $c['por_aprobar'] > 0],
            ['nombre' => 'Por desembolsar', 'valor' => $c['por_desembolsar'], 'icono' => 'dollar-sign',  'url' => base_url('credito/solicitudes')],
            ['nombre' => 'Por cobrar hoy',  'valor' => $c['por_cobrar'],      'icono' => 'phone-call',   'url' => base_url('pagos'), 'alerta' => $c['por_cobrar'] > 0],
            ['nombre' => 'En mora',         'valor' => $c['en_mora'],         'icono' => 'alert-circle', 'url' => base_url('finanzas/recuperacion'), 'alerta' => $c['en_mora'] > 0],
            ['nombre' => 'Cobrado hoy',     'valor' => '$' . $mon,            'icono' => 'trending-up',  'url' => base_url('pagos')],
            ['nombre' => 'Pagos revisión',  'valor' => $c['pagos_revision'],  'icono' => 'clock',        'url' => base_url('pagos'), 'alerta' => $c['pagos_revision'] > 0],
        ];
    }
}
