<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Cuotas del plan de pago de un crédito (una fila por cuota proyectada).
 * `pagado` acumula lo aplicado por pagos APROBADOS; `estado` refleja
 * PENDIENTE / PARCIAL / PAGADA.
 */
class CuotaModel extends Model
{
    protected $table         = 'cuotas';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'tenant_id', 'solicitud_id', 'n', 'fecha_vence',
        'cuota', 'interes', 'capital', 'saldo_proyectado',
        'pagado', 'estado', 'fecha_pago',
        'mora_dev', 'mora', 'descuento',
    ];

    public const PENDIENTE = 'PENDIENTE';
    public const PARCIAL   = 'PARCIAL';
    public const PAGADA    = 'PAGADA';
    public const ANULADA   = 'ANULADA';   // reestructurada — conserva el pagado histórico

    /** Cuotas de un crédito en orden de vencimiento. */
    public function deCredito(int $solicitudId): array
    {
        return $this->where('solicitud_id', $solicitudId)
            ->orderBy('n', 'ASC')
            ->findAll();
    }

    /** Cuotas pendientes/parciales con vencimiento hoy o vencidas (cobros del día). */
    public function pendientesDe(int $tenantId, array $solicitudIds): array
    {
        if (!$solicitudIds) {
            return [];
        }
        return $this->where('tenant_id', $tenantId)
            ->whereIn('solicitud_id', $solicitudIds)
            ->whereIn('estado', [self::PENDIENTE, self::PARCIAL])
            ->orderBy('fecha_vence', 'ASC')
            ->orderBy('n', 'ASC')
            ->findAll();
    }

    /** Saldo vivo del crédito: Σ (cuota − pagado − descuento) de todas las cuotas. */
    public function saldoDe(int $solicitudId): float
    {
        $row = $this->selectSum('cuota', 'total')->selectSum('pagado', 'aplicado')
            ->selectSum('descuento', 'condonado')
            ->where('solicitud_id', $solicitudId)
            ->where('estado !=', self::ANULADA)
            ->first();
        return max(0, round((float) ($row['total'] ?? 0) - (float) ($row['aplicado'] ?? 0) - (float) ($row['condonado'] ?? 0), 2));
    }
}
