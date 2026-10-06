<?php

namespace App\Services\Partner;

use App\Models\CuotaModel;
use App\Models\PagoModel;
use App\Models\SolicitudModel;

/**
 * Listas reales para el asistente Chat-AI del dashboard.
 * Cada método devuelve { resumen, items: [{titulo, sub, valor, url}] } —
 * la forma que chat-ai.js pinta como .chat-list.
 */
class AsistenteService
{
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->db = db_connect();
    }

    private function nombre(array $row, string $n = 'nombres', string $a = 'apellidos'): string
    {
        return trim(($row[$n] ?? '') . ' ' . ($row[$a] ?? '')) ?: '—';
    }

    /* ── Créditos con cuotas vencidas (clientes en mora) ── */
    public function enMora(int $tenantId): array
    {
        // Hoy en hora del negocio (PHP/appTimezone) — no CURDATE() de MySQL
        // porque el server de BD puede correr en otra zona horaria.
        $hoy  = $this->db->escape(date('Y-m-d'));
        $rows = $this->db->table('cuotas q')
            ->select('s.id, s.codigo_credito, p.nombres, p.apellidos,
                      gp.nombres AS gestor_n, gp.apellidos AS gestor_a,
                      COUNT(*) AS cuotas_vencidas,
                      SUM(q.cuota - q.pagado - q.descuento) AS pendiente,
                      MAX(DATEDIFF(' . $hoy . ', q.fecha_vence)) AS dias_atraso', false)
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->join('empleados e', 'e.id = s.asignado_a', 'left')
            ->join('personas gp', 'gp.id = e.persona_id', 'left')
            ->where('q.tenant_id', $tenantId)
            ->where('s.estado', SolicitudModel::ACTIVO)
            ->where('q.pagado < q.cuota', null, false)
            ->where('q.fecha_vence <', date('Y-m-d'))
            ->where('q.estado !=', CuotaModel::ANULADA)
            ->groupBy('q.solicitud_id')
            ->orderBy('dias_atraso', 'DESC')
            ->limit(12)
            ->get()->getResultArray();

        $items = array_map(fn ($r) => [
            'titulo' => $this->nombre($r),
            'sub'    => ($r['codigo_credito'] ?: 'Crédito #' . $r['id']) . ' · ' .
                        ($r['gestor_n'] ? 'Gestor: ' . $this->nombre($r, 'gestor_n', 'gestor_a') : 'Sin gestor'),
            'valor'  => (int) $r['dias_atraso'] . 'd atraso · $' . number_format((float) $r['pendiente'], 0),
            'url'    => base_url('creditos/' . $r['id']),
        ], $rows);

        return [
            'resumen' => count($rows) . ' crédito(s) con cuotas vencidas',
            'items'   => $items,
            'vacio'   => 'Sin mora — toda la cartera está al día.',
            'ver_mas' => base_url('finanzas/recuperacion'),
        ];
    }

    /* ── Pagos cobrados hoy (aplicados) ── */
    public function pagosHoy(int $tenantId): array
    {
        $rows = $this->db->table('pagos pg')
            ->select('pg.id, pg.monto, pg.metodo, pg.fecha_hora, s.codigo_credito,
                      p.nombres, p.apellidos, gp.nombres AS gestor_n, gp.apellidos AS gestor_a')
            ->join('solicitudes s', 's.id = pg.solicitud_id')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->join('empleados e', 'e.id = pg.empleado_id', 'left')
            ->join('personas gp', 'gp.id = e.persona_id', 'left')
            ->where('pg.tenant_id', $tenantId)
            ->where('pg.tipo', PagoModel::TIPO_PAGO)
            ->where('pg.estado', PagoModel::APLICADO)
            ->where('DATE(pg.fecha_hora)', date('Y-m-d'))
            ->orderBy('pg.fecha_hora', 'DESC')
            ->limit(12)
            ->get()->getResultArray();

        $total = array_sum(array_column($rows, 'monto'));
        $metodos = PagoModel::METODOS_LBL;

        $items = array_map(fn ($r) => [
            'titulo' => $this->nombre($r),
            'sub'    => date('H:i', strtotime($r['fecha_hora'])) . ' · ' . ($metodos[$r['metodo']] ?? $r['metodo']) .
                        ' · Cobró: ' . ($r['gestor_n'] ? $this->nombre($r, 'gestor_n', 'gestor_a') : 'Oficina'),
            'valor'  => '$' . number_format((float) $r['monto'], 0),
            'url'    => base_url('pagos'),
        ], $rows);

        return [
            'resumen' => count($rows) . ' pago(s) hoy · $' . number_format($total, 0) . ' cobrado',
            'items'   => $items,
            'vacio'   => 'Aún no hay pagos aplicados hoy.',
            'ver_mas' => base_url('pagos'),
        ];
    }

    /* ── Cuotas por cobrar: vencidas + vencen hoy ── */
    public function porCobrar(int $tenantId): array
    {
        $hoy  = date('Y-m-d');
        $rows = $this->db->table('cuotas q')
            ->select('s.id, s.codigo_credito, p.nombres, p.apellidos, p.telefono,
                      gp.nombres AS gestor_n, gp.apellidos AS gestor_a,
                      MIN(q.fecha_vence) AS prox_vence,
                      SUM(q.cuota - q.pagado - q.descuento) AS pendiente,
                      SUM(CASE WHEN q.fecha_vence < "' . $hoy . '" THEN 1 ELSE 0 END) AS vencidas', false)
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->join('empleados e', 'e.id = s.asignado_a', 'left')
            ->join('personas gp', 'gp.id = e.persona_id', 'left')
            ->where('q.tenant_id', $tenantId)
            ->where('s.estado', SolicitudModel::ACTIVO)
            ->whereIn('q.estado', [CuotaModel::PENDIENTE, CuotaModel::PARCIAL])
            ->where('q.fecha_vence <=', $hoy)
            ->groupBy('q.solicitud_id')
            ->orderBy('prox_vence', 'ASC')
            ->limit(12)
            ->get()->getResultArray();

        $items = array_map(fn ($r) => [
            'titulo' => $this->nombre($r),
            'sub'    => (int) $r['vencidas'] . ' vencida(s) · Venció ' . date('d/m', strtotime($r['prox_vence'])) .
                        ($r['telefono'] ? ' · ' . $r['telefono'] : ''),
            'valor'  => '$' . number_format((float) $r['pendiente'], 0),
            'url'    => base_url('creditos/' . $r['id']),
        ], $rows);

        return [
            'resumen' => count($rows) . ' cliente(s) por cobrar hoy',
            'items'   => $items,
            'vacio'   => 'Nada por cobrar hoy — cartera al día.',
            'ver_mas' => base_url('pagos'),
        ];
    }

    /* ── Solicitudes pendientes de aprobación ── */
    public function solicitudes(int $tenantId): array
    {
        $rows = $this->db->table('solicitudes s')
            ->select('s.id, s.codigo_credito, s.monto, s.estado, s.created_at,
                      p.nombres, p.apellidos, gp.nombres AS gestor_n, gp.apellidos AS gestor_a')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->join('empleados e', 'e.id = s.asignado_a', 'left')
            ->join('personas gp', 'gp.id = e.persona_id', 'left')
            ->where('s.tenant_id', $tenantId)
            ->whereIn('s.estado', [SolicitudModel::CREADA, SolicitudModel::REVISION])
            ->orderBy('s.created_at', 'DESC')
            ->limit(12)
            ->get()->getResultArray();

        $lbl = SolicitudModel::LABEL_ESTADO;

        $items = array_map(fn ($r) => [
            'titulo' => $this->nombre($r),
            'sub'    => ($lbl[$r['estado']] ?? $r['estado']) . ' · ' . date('d/m', strtotime($r['created_at'])) .
                        ($r['gestor_n'] ? ' · ' . $this->nombre($r, 'gestor_n', 'gestor_a') : ''),
            'valor'  => '$' . number_format((float) $r['monto'], 0),
            'url'    => base_url('credito/solicitudes/' . $r['id']),
        ], $rows);

        return [
            'resumen' => count($rows) . ' solicitud(es) pendientes de decisión',
            'items'   => $items,
            'vacio'   => 'Sin solicitudes pendientes.',
            'ver_mas' => base_url('credito/solicitudes'),
        ];
    }

    /* ── Clientes sin documentos cargados ── */
    public function sinDocs(int $tenantId): array
    {
        $rows = $this->db->table('clientes c')
            ->select('c.id, c.codigo, p.nombres, p.apellidos, p.telefono, c.created_at')
            ->join('personas p', 'p.id = c.persona_id')
            ->join('persona_documentos pd', 'pd.persona_id = c.persona_id', 'left')
            ->where('c.tenant_id', $tenantId)
            ->where('c.estado', 'ACTIVO')
            ->where('pd.id IS NULL', null, false)
            ->orderBy('c.created_at', 'DESC')
            ->limit(12)
            ->get()->getResultArray();

        $items = array_map(fn ($r) => [
            'titulo' => $this->nombre($r),
            'sub'    => ($r['codigo'] ?: 'Cliente #' . $r['id']) . ' · Alta ' . date('d/m/Y', strtotime($r['created_at'])),
            'valor'  => 'Sin docs',
            'url'    => base_url('socios/clientes/' . $r['id']),
        ], $rows);

        return [
            'resumen' => count($rows) . ' cliente(s) sin documentación',
            'items'   => $items,
            'vacio'   => 'Todos los clientes tienen documentos cargados.',
            'ver_mas' => base_url('socios/clientes'),
        ];
    }

    /* ── Pagos en revisión esperando aprobación ── */
    public function pagosRevision(int $tenantId): array
    {
        $rows = $this->db->table('pagos pg')
            ->select('pg.id, pg.monto, pg.metodo, pg.fecha_hora, s.codigo_credito,
                      p.nombres, p.apellidos, gp.nombres AS gestor_n, gp.apellidos AS gestor_a')
            ->join('solicitudes s', 's.id = pg.solicitud_id')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->join('empleados e', 'e.id = pg.empleado_id', 'left')
            ->join('personas gp', 'gp.id = e.persona_id', 'left')
            ->where('pg.tenant_id', $tenantId)
            ->where('pg.tipo', PagoModel::TIPO_PAGO)
            ->where('pg.estado', PagoModel::REVISION)
            ->orderBy('pg.fecha_hora', 'DESC')
            ->limit(12)
            ->get()->getResultArray();

        $items = array_map(fn ($r) => [
            'titulo' => $this->nombre($r),
            'sub'    => date('d/m H:i', strtotime($r['fecha_hora'])) .
                        ' · Reportó: ' . ($r['gestor_n'] ? $this->nombre($r, 'gestor_n', 'gestor_a') : '—'),
            'valor'  => '$' . number_format((float) $r['monto'], 0),
            'url'    => base_url('pagos'),
        ], $rows);

        return [
            'resumen' => count($rows) . ' pago(s) esperando su aprobación',
            'items'   => $items,
            'vacio'   => 'No hay pagos pendientes de aprobar.',
            'ver_mas' => base_url('pagos'),
        ];
    }
}
