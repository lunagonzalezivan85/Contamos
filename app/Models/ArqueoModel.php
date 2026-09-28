<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Arqueos de caja — cierre diario por gestor.
 * esperado = saldo_inicial + cobros − desembolsos
 * diferencia = contado − esperado → CUADRADO | DIFERENCIA
 */
class ArqueoModel extends Model
{
    protected $table            = 'arqueos';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'empleado_id', 'fecha',
        'saldo_inicial', 'cobros', 'desembolsos',
        'esperado', 'contado', 'diferencia',
        'estado', 'observacion', 'resuelto_por',
    ];

    public const CUADRADO   = 'CUADRADO';
    public const DIFERENCIA = 'DIFERENCIA';

    public const LABEL_ESTADO = [
        self::CUADRADO   => 'Cuadrado',
        self::DIFERENCIA => 'Con diferencia',
    ];

    /** Arqueo de un gestor en una fecha (o null si aún no se cierra). */
    public function deDia(int $tenantId, int $empleadoId, string $fecha): ?array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('empleado_id', $empleadoId)
            ->where('fecha', $fecha)
            ->first();
    }

    /** Último arqueo del gestor antes de $fecha — de ahí sale el saldo inicial. */
    public function ultimoAnterior(int $tenantId, int $empleadoId, string $fecha): ?array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('empleado_id', $empleadoId)
            ->where('fecha <', $fecha)
            ->orderBy('fecha', 'DESC')
            ->first();
    }

    /** Arqueos de un gestor (portal — solo ve los suyos). */
    public function deGestor(int $tenantId, int $empleadoId, int $limit = 15): array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('empleado_id', $empleadoId)
            ->orderBy('fecha', 'DESC')
            ->orderBy('id', 'DESC')
            ->findAll($limit);
    }

    /** Historial de arqueos del tenant con nombre del gestor y quién lo cerró. */
    public function historial(int $tenantId, int $limit = 20): array
    {
        return $this->select('arqueos.*, personas.nombres, personas.apellidos, u.username AS resuelto_por_user')
            ->join('empleados', 'empleados.id = arqueos.empleado_id')
            ->join('personas', 'personas.id = empleados.persona_id')
            ->join('users u', 'u.id = arqueos.resuelto_por', 'left')
            ->where('arqueos.tenant_id', $tenantId)
            ->orderBy('arqueos.fecha', 'DESC')
            ->orderBy('arqueos.id', 'DESC')
            ->findAll($limit);
    }
}
