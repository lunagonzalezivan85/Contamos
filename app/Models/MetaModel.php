<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Metas mensuales asignadas a gestores (empleados).
 * Una meta = empleado + métrica + periodo (YYYY-MM) + valor objetivo.
 * 'avance' solo se usa cuando la métrica es MANUAL; las AUTO se calculan
 * al vuelo en MetaService.
 */
class MetaModel extends Model
{
    protected $table         = 'metas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'tenant_id', 'empleado_id', 'metrica_id', 'periodo', 'meta',
        'avance', 'notas', 'estado', 'registrado_por',
    ];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public const ACTIVO  = 'ACTIVO';
    public const ANULADO = 'ANULADO';

    /**
     * Listado del tenant con filtros.
     * $filtros: periodo (YYYY-MM), empleado_id, estado, incluir_anuladas.
     */
    public function deTenant(int $tenantId, array $filtros = []): array
    {
        $b = $this->select('metas.*, mm.codigo AS metrica_codigo, mm.nombre AS metrica,
                            mm.unidad, mm.modo, mm.calculo, mm.formula,
                            p.nombres, p.apellidos, e.cargo')
            ->join('meta_metricas mm', 'mm.id = metas.metrica_id')
            ->join('empleados e', 'e.id = metas.empleado_id')
            ->join('personas p', 'p.id = e.persona_id')
            ->where('metas.tenant_id', $tenantId);

        if (!empty($filtros['periodo'])) {
            $b->where('metas.periodo', $filtros['periodo']);
        }
        if (!empty($filtros['empleado_id'])) {
            $b->where('metas.empleado_id', (int) $filtros['empleado_id']);
        }
        if (empty($filtros['incluir_anuladas'])) {
            $b->where('metas.estado', self::ACTIVO);
        } elseif (!empty($filtros['estado'])) {
            $b->where('metas.estado', $filtros['estado']);
        }

        return $b->orderBy('metas.periodo', 'DESC')
            ->orderBy('p.apellidos', 'ASC')
            ->orderBy('mm.nombre', 'ASC')
            ->findAll();
    }

    public function deTenantPorId(int $tenantId, int $id): ?array
    {
        return $this->select('metas.*, mm.calculo')
            ->join('meta_metricas mm', 'mm.id = metas.metrica_id')
            ->where('metas.tenant_id', $tenantId)
            ->where('metas.id', $id)
            ->get()->getRowArray();
    }

    /** ¿Ya existe meta para esa combinación? */
    public function existe(int $tenantId, int $empleadoId, int $metricaId, string $periodo, ?int $exceptoId = null): bool
    {
        $b = $this->where('tenant_id', $tenantId)
            ->where('empleado_id', $empleadoId)
            ->where('metrica_id', $metricaId)
            ->where('periodo', $periodo)
            ->where('estado', self::ACTIVO);
        if ($exceptoId) $b->where('id !=', $exceptoId);
        return $b->countAllResults() > 0;
    }
}
