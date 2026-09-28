<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Ingresos del negocio — otros ingresos que no son cobros de crédito.
 * VENTA genera recibo con desglose de IVA; DONACION/OTRO solo quedan registrados.
 * ACTIVO cuenta en los totales; ANULADO queda como registro histórico.
 */
class IngresoModel extends Model
{
    protected $table            = 'ingresos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'tipo', 'empleado_id', 'registrado_por',
        'fecha', 'concepto', 'descripcion', 'referencia',
        'monto', 'iva_pct', 'metodo', 'estado',
    ];

    public const ACTIVO  = 'ACTIVO';
    public const ANULADO = 'ANULADO';

    public const TIPO_VENTA    = 'VENTA';
    public const TIPO_DONACION = 'DONACION';
    public const TIPO_OTRO     = 'OTRO';

    public const TIPOS = [
        self::TIPO_VENTA    => 'Venta',
        self::TIPO_DONACION => 'Donación',
        self::TIPO_OTRO     => 'Otro',
    ];

    public const LABEL_ESTADO = [
        self::ACTIVO  => 'Registrado',
        self::ANULADO => 'Anulado',
    ];

    /** Medios de cobro del ingreso. */
    public const METODOS = [
        'EFECTIVO'      => 'Efectivo',
        'TRANSFERENCIA' => 'Transferencia',
        'DEPOSITO'      => 'Depósito',
        'OTRO'          => 'Otro',
    ];

    /**
     * Ingresos del tenant con empleado y quién lo registró.
     * Filtros: desde, hasta, tipo, buscar, anulados.
     */
    public function deTenant(int $tenantId, array $f = [], int $limit = 200): array
    {
        $b = $this->select('ingresos.*, personas.nombres, personas.apellidos, u.username AS reg_usuario')
            ->join('empleados emp', 'emp.id = ingresos.empleado_id', 'left')
            ->join('personas', 'personas.id = emp.persona_id', 'left')
            ->join('users u', 'u.id = ingresos.registrado_por', 'left')
            ->where('ingresos.tenant_id', $tenantId);

        if (empty($f['anulados'])) {
            $b->where('ingresos.estado', self::ACTIVO);
        }
        if (!empty($f['tipo'])) {
            $b->where('ingresos.tipo', $f['tipo']);
        }
        if (!empty($f['desde'])) {
            $b->where('ingresos.fecha >=', $f['desde']);
        }
        if (!empty($f['hasta'])) {
            $b->where('ingresos.fecha <=', $f['hasta']);
        }
        if (!empty($f['buscar'])) {
            $b->groupStart()
                ->like('ingresos.concepto', $f['buscar'])
                ->orLike('ingresos.referencia', $f['buscar'])
                ->orLike('ingresos.descripcion', $f['buscar'])
              ->groupEnd();
        }

        return $b->orderBy('ingresos.fecha', 'DESC')
            ->orderBy('ingresos.id', 'DESC')
            ->findAll($limit);
    }

    /** Ingreso del tenant por id (aislamiento multi-tenant). */
    public function deTenantPorId(int $tenantId, int $id): ?array
    {
        return $this->where('tenant_id', $tenantId)->find($id);
    }

    /** Total ingresado en un rango de fechas (ACTIVO, con IVA incluido si aplica). */
    public function totalRango(int $tenantId, string $desde, string $hasta): float
    {
        $rows = $this->select('monto, iva_pct')
            ->where('tenant_id', $tenantId)
            ->where('estado', self::ACTIVO)
            ->where('fecha >=', $desde)
            ->where('fecha <=', $hasta)
            ->findAll();
        $total = 0.0;
        foreach ($rows as $r) {
            $total += self::total($r);
        }
        return round($total, 2);
    }

    // ---------------------------------------------------------------
    // Cálculos del recibo (monto = subtotal, el IVA se suma encima)
    // ---------------------------------------------------------------

    /** IVA en dinero del ingreso. */
    public static function iva(array $i): float
    {
        return round((float) $i['monto'] * (float) ($i['iva_pct'] ?? 0) / 100, 2);
    }

    /** Total cobrado: monto + IVA. */
    public static function total(array $i): float
    {
        return round((float) $i['monto'] + self::iva($i), 2);
    }

    /** Número de recibo de venta: V-{INICIALES DEL TENANT}-{consecutivo de 7 dígitos}. */
    public static function reciboCode(array $ingreso, string $tenantNombre): string
    {
        $ini = '';
        foreach (explode(' ', trim(preg_replace('/\s+/', ' ', $tenantNombre))) as $palabra) {
            $l = mb_substr(trim($palabra), 0, 1);
            if ($l !== '') $ini .= mb_strtoupper($l);
        }
        return 'V-' . ($ini !== '' ? $ini : 'CR') . '-' . str_pad((string) ($ingreso['id'] ?? 0), 7, '0', STR_PAD_LEFT);
    }
}
