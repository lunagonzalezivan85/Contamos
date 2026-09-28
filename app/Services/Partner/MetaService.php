<?php

namespace App\Services\Partner;

use App\Models\MetaMetricaModel;
use App\Models\MetaModel;
use Config\Database;

/**
 * Plan de metas: asigna objetivos mensuales a gestores y calcula el avance.
 * Métricas AUTO → se calculan con consultas sobre pagos/solicitudes/cuotas.
 * Métricas MANUAL → el avance se ingresa a mano en la meta.
 */
class MetaService
{
    private MetaModel $metas;
    private MetaMetricaModel $metricas;
    private $db;

    public function __construct()
    {
        $this->metas    = new MetaModel();
        $this->metricas = new MetaMetricaModel();
        $this->db       = Database::connect();
    }

    /** Siembra el catálogo base si el tenant no tiene métricas aún. */
    public function asegurarMetricas(int $tenantId): void
    {
        $hay = $this->metricas->where('tenant_id', $tenantId)->countAllResults();
        if ($hay > 0) return;
        $now = date('Y-m-d H:i:s');
        foreach (MetaMetricaModel::DEFAULTS as $m) {
            $this->metricas->insert($m + [
                'tenant_id' => $tenantId, 'estado' => MetaMetricaModel::ACTIVO,
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function empleados(int $tenantId): array
    {
        return $this->db->table('empleados')
            ->select('empleados.id, personas.nombres, personas.apellidos')
            ->join('personas', 'personas.id = empleados.persona_id')
            ->where('empleados.tenant_id', $tenantId)
            ->where('empleados.estado', 'ACTIVO')
            ->orderBy('personas.apellidos', 'ASC')
            ->get()->getResultArray();
    }

    public function metricasActivas(int $tenantId): array
    {
        return $this->metricas->activas($tenantId);
    }

    public function metricasTodas(int $tenantId): array
    {
        return $this->metricas->deTenant($tenantId);
    }

    /**
     * Listado de metas con avance calculado, % de cumplimiento y resultado.
     */
    public function listar(int $tenantId, array $filtros): array
    {
        $rows = $this->metas->deTenant($tenantId, $filtros);
        $hoyPeriodo = date('Y-m');
        foreach ($rows as &$r) {
            $r['avance_calc'] = $this->avance($tenantId, $r);
            $r['cumplida']    = $this->cumplida($r, (float) $r['avance_calc']);
            $r['cerrado']     = $r['periodo'] < $hoyPeriodo;
            $r['pct']         = $this->porcentaje($r, (float) $r['avance_calc']);
        }
        unset($r);
        return $rows;
    }

    /** Tarjetas de resumen del periodo filtrado. */
    public function resumen(array $metas): array
    {
        $tot = 0; $ok = 0; $enCurso = 0; $incumplidas = 0; $sumaPct = 0.0;
        foreach ($metas as $m) {
            if ($m['estado'] !== MetaModel::ACTIVO) continue;
            $tot++;
            $sumaPct += $m['pct'];
            if ($m['cumplida']) $ok++;
            elseif ($m['cerrado']) $incumplidas++;
            else $enCurso++;
        }
        return [
            'total'        => $tot,
            'cumplidas'    => $ok,
            'en_curso'     => $enCurso,
            'incumplidas'  => $incumplidas,
            'promedio_pct' => $tot ? round($sumaPct / $tot, 1) : 0.0,
        ];
    }

    /**
     * Avance actual de una meta.
     * MANUAL → valor ingresado; AUTO → cálculo según código de métrica.
     */
    public function avance(int $tenantId, array $meta): float
    {
        if ($meta['calculo'] === 'MANUAL') {
            return (float) $meta['avance'];
        }
        [$desde, $hasta] = $this->rangoPeriodo($meta['periodo']);
        $emp = (int) $meta['empleado_id'];

        switch ($meta['metrica_codigo']) {
            case 'recuperacion':
                return (float) $this->db->table('pagos')
                    ->selectSum('monto')
                    ->where('tenant_id', $tenantId)
                    ->where('empleado_id', $emp)
                    ->where('tipo', 'PAGO')
                    ->where('estado', 'APLICADO')
                    ->where('fecha_hora >=', $desde . ' 00:00:00')
                    ->where('fecha_hora <=', $hasta . ' 23:59:59')
                    ->get()->getRow('monto') ?? 0;

            case 'captacion':
                return (float) $this->db->table('solicitudes s')
                    ->select('COUNT(DISTINCT s.cliente_id) AS n', false)
                    ->join('clientes c', 'c.id = s.cliente_id')
                    ->where('s.tenant_id', $tenantId)
                    ->where('s.empleado_id', $emp)
                    ->where('s.created_at >=', $desde . ' 00:00:00')
                    ->where('s.created_at <=', $hasta . ' 23:59:59')
                    ->where('c.created_at >=', $desde . ' 00:00:00')
                    ->where('c.created_at <=', $hasta . ' 23:59:59')
                    ->get()->getRow('n') ?? 0;

            case 'solicitudes':
                return (float) $this->db->table('solicitudes')
                    ->where('tenant_id', $tenantId)
                    ->where('empleado_id', $emp)
                    ->where('created_at >=', $desde . ' 00:00:00')
                    ->where('created_at <=', $hasta . ' 23:59:59')
                    ->countAllResults();

            case 'colocacion':
                return (float) $this->db->table('solicitudes')
                    ->selectSum('monto_aprobado')
                    ->where('tenant_id', $tenantId)
                    ->where('empleado_id', $emp)
                    ->whereIn('estado', ['ACTIVO', 'LIQUIDADO'])
                    ->where('fecha_desembolso >=', $desde)
                    ->where('fecha_desembolso <=', $hasta)
                    ->get()->getRow('monto_aprobado') ?? 0;

            case 'mora':
                return $this->tasaMora($tenantId, $emp, min($hasta, date('Y-m-d')));

            default:
                return (float) $meta['avance']; // código desconocido → manual
        }
    }

    /**
     * Tasa de mora (%) = saldo vencido / saldo pendiente de la cartera
     * del gestor a la fecha de corte. Solo créditos ACTIVO.
     */
    private function tasaMora(int $tenantId, int $empleadoId, string $corte): float
    {
        $pend = $this->db->table('cuotas q')
            ->select('COALESCE(SUM(q.cuota - q.pagado),0) AS pendiente', false)
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->where('q.tenant_id', $tenantId)
            ->where('s.empleado_id', $empleadoId)
            ->where('s.estado', 'ACTIVO')
            ->where('q.pagado < q.cuota', null, false)
            ->get()->getRow('pendiente') ?? 0;

        if ($pend <= 0) return 0.0;

        $venc = $this->db->table('cuotas q')
            ->select('COALESCE(SUM(q.cuota - q.pagado),0) AS vencido', false)
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->where('q.tenant_id', $tenantId)
            ->where('s.empleado_id', $empleadoId)
            ->where('s.estado', 'ACTIVO')
            ->where('q.pagado < q.cuota', null, false)
            ->where('q.fecha_vence <', $corte)
            ->get()->getRow('vencido') ?? 0;

        return round(100 * $venc / $pend, 2);
    }

    /** MINIMO: cumple si avance >= meta. MAXIMO: cumple si avance <= meta. */
    private function cumplida(array $m, float $avance): bool
    {
        $meta = (float) $m['meta'];
        return $m['modo'] === 'MAXIMO' ? $avance <= $meta : $avance >= $meta;
    }

    /**
     * % para la barra de progreso (0-100+).
     * MAXIMO: 100% cuando está dentro del techo; al superarlo baja según exceso.
     */
    private function porcentaje(array $m, float $avance): float
    {
        $meta = (float) $m['meta'];
        if ($m['modo'] === 'MAXIMO') {
            if ($avance <= $meta) return 100.0;
            if ($meta <= 0) return 0.0;
            return max(0.0, round(100 * (1 - ($avance - $meta) / $meta), 1));
        }
        if ($meta <= 0) return $avance > 0 ? 100.0 : 0.0;
        return round(100 * $avance / $meta, 1);
    }

    /** [primer día, último día] del periodo YYYY-MM. */
    private function rangoPeriodo(string $periodo): array
    {
        $desde = $periodo . '-01';
        $hasta = date('Y-m-t', strtotime($desde));
        return [$desde, $hasta];
    }

    // ---------------- CRUD metas ----------------

    public function guardar(int $tenantId, array $d, int $userId): string
    {
        $err = $this->validar($tenantId, $d);
        if ($err) return $err;
        if ($this->metas->existe($tenantId, (int) $d['empleado_id'], (int) $d['metrica_id'], $d['periodo'])) {
            return 'Ya existe una meta activa para ese gestor, métrica y periodo.';
        }
        $this->metas->insert([
            'tenant_id'      => $tenantId,
            'empleado_id'    => (int) $d['empleado_id'],
            'metrica_id'     => (int) $d['metrica_id'],
            'periodo'        => $d['periodo'],
            'meta'           => (float) $d['meta'],
            'avance'         => (float) ($d['avance'] ?? 0),
            'notas'          => $d['notas'] ?: null,
            'estado'         => MetaModel::ACTIVO,
            'registrado_por' => $userId,
        ]);
        return '';
    }

    public function actualizar(int $tenantId, int $id, array $d): string
    {
        $m = $this->metas->deTenantPorId($tenantId, $id);
        if (!$m || $m['estado'] !== MetaModel::ACTIVO) return 'Meta no encontrada.';
        $err = $this->validar($tenantId, $d);
        if ($err) return $err;
        if ($this->metas->existe($tenantId, (int) $d['empleado_id'], (int) $d['metrica_id'], $d['periodo'], $id)) {
            return 'Ya existe una meta activa para ese gestor, métrica y periodo.';
        }
        $this->metas->update($id, [
            'empleado_id' => (int) $d['empleado_id'],
            'metrica_id'  => (int) $d['metrica_id'],
            'periodo'     => $d['periodo'],
            'meta'        => (float) $d['meta'],
            'avance'      => (float) ($d['avance'] ?? $m['avance']),
            'notas'       => $d['notas'] ?: null,
        ]);
        return '';
    }

    public function anular(int $tenantId, int $id): string
    {
        $m = $this->metas->deTenantPorId($tenantId, $id);
        if (!$m || $m['estado'] !== MetaModel::ACTIVO) return 'Meta no encontrada.';
        $this->metas->update($id, ['estado' => MetaModel::ANULADO]);
        return '';
    }

    // ---------------- Métricas custom ----------------

    public function guardarMetrica(int $tenantId, array $d): string
    {
        $nombre = trim((string) ($d['nombre'] ?? ''));
        if ($nombre === '') return 'El nombre de la métrica es obligatorio.';
        $unidad = MetaMetricaModel::UNIDADES[$d['unidad'] ?? ''] ?? null;
        if (!$unidad) return 'Unidad inválida.';
        $modo = MetaMetricaModel::MODOS[$d['modo'] ?? ''] ?? null;
        if (!$modo) return 'Modo inválido.';

        $codigo = $this->slug($nombre);
        $existe = $this->metricas->where('tenant_id', $tenantId)->where('codigo', $codigo)->countAllResults();
        if ($existe) return 'Ya existe una métrica con ese nombre.';

        $this->metricas->insert([
            'tenant_id' => $tenantId,
            'codigo'    => $codigo,
            'nombre'    => $nombre,
            'unidad'    => $d['unidad'],
            'modo'      => $d['modo'],
            'calculo'   => 'MANUAL',
            'formula'   => trim((string) ($d['formula'] ?? '')) ?: 'Avance ingresado manualmente.',
            'estado'    => MetaMetricaModel::ACTIVO,
        ]);
        return '';
    }

    public function toggleMetrica(int $tenantId, int $id): string
    {
        $m = $this->metricas->deTenantPorId($tenantId, $id);
        if (!$m) return 'Métrica no encontrada.';
        $nuevo = $m['estado'] === MetaMetricaModel::ACTIVO
            ? MetaMetricaModel::INACTIVO : MetaMetricaModel::ACTIVO;
        $this->metricas->update($id, ['estado' => $nuevo]);
        return '';
    }

    private function slug(string $s): string
    {
        $s = mb_strtolower(trim($s));
        $s = strtr($s, ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ñ'=>'n','ü'=>'u']);
        $s = preg_replace('/[^a-z0-9]+/', '_', $s);
        return 'custom_' . trim($s, '_');
    }

    private function validar(int $tenantId, array $d): string
    {
        $emp = $this->db->table('empleados')
            ->where('id', (int) ($d['empleado_id'] ?? 0))
            ->where('tenant_id', $tenantId)->where('estado', 'ACTIVO')
            ->countAllResults();
        if (!$emp) return 'Seleccioná un gestor válido.';

        $met = $this->metricas->deTenantPorId($tenantId, (int) ($d['metrica_id'] ?? 0));
        if (!$met || $met['estado'] !== MetaMetricaModel::ACTIVO) return 'Seleccioná una métrica válida.';

        if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', (string) ($d['periodo'] ?? ''))) {
            return 'El periodo debe ser un mes válido (YYYY-MM).';
        }
        $meta = (float) ($d['meta'] ?? -1);
        if ($meta < 0) return 'La meta no puede ser negativa.';
        if ($met['unidad'] === 'PORCENTAJE' && $meta > 100) return 'La meta en % no puede superar 100.';
        return '';
    }
}
