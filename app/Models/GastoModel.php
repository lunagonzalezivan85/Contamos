<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Gastos operativos del negocio — un egreso por fila, sin detalle.
 * ACTIVO cuenta en los totales; ANULADO queda como registro histórico.
 */
class GastoModel extends Model
{
    protected $table            = 'gastos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'categoria_id', 'empleado_id', 'registrado_por',
        'fecha', 'concepto', 'descripcion', 'referencia',
        'monto', 'metodo', 'estado',
    ];

    public const ACTIVO  = 'ACTIVO';
    public const ANULADO = 'ANULADO';

    public const LABEL_ESTADO = [
        self::ACTIVO  => 'Registrado',
        self::ANULADO => 'Anulado',
    ];

    /** Medios de pago del gasto. */
    public const METODOS = [
        'EFECTIVO'      => 'Efectivo',
        'TRANSFERENCIA' => 'Transferencia',
        'DEPOSITO'      => 'Depósito',
        'OTRO'          => 'Otro',
    ];

    /**
     * Gastos del tenant con categoría, empleado y quién lo registró.
     * Filtros: desde, hasta (fechas Y-m-d), categoria_id, buscar, anulados.
     */
    public function deTenant(int $tenantId, array $f = [], int $limit = 200): array
    {
        $b = $this->select('gastos.*, gasto_categorias.nombre AS categoria,
                            personas.nombres, personas.apellidos, u.username AS reg_usuario')
            ->join('gasto_categorias', 'gasto_categorias.id = gastos.categoria_id')
            ->join('empleados emp', 'emp.id = gastos.empleado_id', 'left')
            ->join('personas', 'personas.id = emp.persona_id', 'left')
            ->join('users u', 'u.id = gastos.registrado_por', 'left')
            ->where('gastos.tenant_id', $tenantId);

        if (empty($f['anulados'])) {
            $b->where('gastos.estado', self::ACTIVO);
        }
        if (!empty($f['categoria_id'])) {
            $b->where('gastos.categoria_id', (int) $f['categoria_id']);
        }
        if (!empty($f['desde'])) {
            $b->where('gastos.fecha >=', $f['desde']);
        }
        if (!empty($f['hasta'])) {
            $b->where('gastos.fecha <=', $f['hasta']);
        }
        if (!empty($f['buscar'])) {
            $b->groupStart()
                ->like('gastos.concepto', $f['buscar'])
                ->orLike('gastos.referencia', $f['buscar'])
                ->orLike('gastos.descripcion', $f['buscar'])
              ->groupEnd();
        }

        return $b->orderBy('gastos.fecha', 'DESC')
            ->orderBy('gastos.id', 'DESC')
            ->findAll($limit);
    }

    /** Gasto del tenant por id (para editar/anular con aislamiento). */
    public function deTenantPorId(int $tenantId, int $id): ?array
    {
        return $this->where('tenant_id', $tenantId)->find($id);
    }

    /** Totales por categoría dentro de un rango — para las tarjetas/resumen. */
    public function totalesPorCategoria(int $tenantId, array $f = []): array
    {
        $b = $this->select('gastos.categoria_id, gasto_categorias.nombre AS categoria,
                            COUNT(*) AS n, SUM(' . $this->db->prefixTable('gastos') . '.monto) AS total')
            ->join('gasto_categorias', 'gasto_categorias.id = gastos.categoria_id')
            ->where('gastos.tenant_id', $tenantId)
            ->where('gastos.estado', self::ACTIVO);

        if (!empty($f['desde'])) $b->where('gastos.fecha >=', $f['desde']);
        if (!empty($f['hasta'])) $b->where('gastos.fecha <=', $f['hasta']);

        return $b->groupBy('gastos.categoria_id')
            ->orderBy('total', 'DESC')
            ->findAll();
    }

    /** Total gastado en un rango de fechas (ACTIVO). */
    public function totalRango(int $tenantId, string $desde, string $hasta): float
    {
        $r = $this->selectSum('monto')
            ->where('tenant_id', $tenantId)
            ->where('estado', self::ACTIVO)
            ->where('fecha >=', $desde)
            ->where('fecha <=', $hasta)
            ->first();
        return (float) ($r['monto'] ?? 0);
    }
}
