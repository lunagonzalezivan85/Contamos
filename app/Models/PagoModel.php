<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Pagos/abonos de créditos — flujo de revisión:
 * REVISION → (aprobado) APLICADO → cuotas actualizadas
 *          → (rechazado) RECHAZADO → no aplica
 * APLICADO → REVERTIDO (por reversión aprobada)
 */
class PagoModel extends Model
{
    protected $table         = 'pagos';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'array';
    protected $useTimestamps = true;

    protected $allowedFields = [
        'tenant_id', 'solicitud_id', 'cuota_id', 'revierte_id', 'empleado_id',
        'registrado_por', 'aprobado_por', 'monto', 'metodo', 'tipo',
        'fecha_hora', 'observacion', 'estado', 'resuelto_at',
    ];

    public const REVISION  = 'REVISION';
    public const APLICADO  = 'APLICADO';
    public const RECHAZADO = 'RECHAZADO';
    public const REVERTIDO = 'REVERTIDO';

    public const TIPO_PAGO    = 'PAGO';
    public const TIPO_PROMESA = 'PROMESA';

    /** Métodos seleccionables al cobrar (el INTERNO solo lo crea el sistema). */
    public const METODOS = ['EFECTIVO' => 'Efectivo', 'TRANSFERENCIA' => 'Transferencia'];

    /** Etiquetas para mostrar, incluye métodos internos del sistema. */
    public const METODOS_LBL = [
        'EFECTIVO'      => 'Efectivo',
        'TRANSFERENCIA' => 'Transferencia',
        'INTERNO'       => 'Movimiento interno',
    ];

    public const LABEL_ESTADO = [
        self::REVISION  => 'En revisión',
        self::APLICADO  => 'Aplicado',
        self::RECHAZADO => 'Rechazado',
        self::REVERTIDO => 'Revertido',
    ];

    /** Pagos en revisión del tenant (bandeja de aprobación) — sin promesas. */
    public function enRevision(int $tenantId): array
    {
        return $this->select('pagos.*, personas.nombres, personas.apellidos, solicitudes.codigo_credito,
                              cobrador.nombres AS cob_nombres, cobrador.apellidos AS cob_apellidos')
            ->join('solicitudes', 'solicitudes.id = pagos.solicitud_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = pagos.empleado_id', 'left')
            ->join('personas cobrador', 'cobrador.id = emp.persona_id', 'left')
            ->where('pagos.tenant_id', $tenantId)
            ->where('pagos.estado', self::REVISION)
            ->where('pagos.tipo', self::TIPO_PAGO)
            ->orderBy('pagos.fecha_hora', 'DESC')
            ->findAll();
    }

    /** Pago con datos de cliente, crédito y quién lo registró — para el recibo. */
    public function recibo(int $tenantId, int $pagoId): ?array
    {
        return $this->select('pagos.*, personas.nombres, personas.apellidos, personas.cedula, personas.telefono,
                              solicitudes.codigo_credito, solicitudes.monto_aprobado, clientes.codigo AS cliente_codigo,
                              cobrador.nombres AS cob_nombres, cobrador.apellidos AS cob_apellidos,
                              u.username AS reg_usuario')
            ->join('solicitudes', 'solicitudes.id = pagos.solicitud_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = pagos.empleado_id', 'left')
            ->join('personas cobrador', 'cobrador.id = emp.persona_id', 'left')
            ->join('users u', 'u.id = pagos.registrado_por', 'left')
            ->where('pagos.tenant_id', $tenantId)
            ->find($pagoId);
    }

    /** Número de recibo: R-{INICIALES DEL TENANT}-{consecutivo de 7 dígitos}. */
    public static function reciboCode(array $pago, string $tenantNombre): string
    {
        $ini = '';
        foreach (explode(' ', trim(preg_replace('/\s+/', ' ', $tenantNombre))) as $palabra) {
            $l = mb_substr(trim($palabra), 0, 1);
            if ($l !== '') $ini .= mb_strtoupper($l);
        }
        return 'R-' . ($ini !== '' ? $ini : 'CR') . '-' . str_pad((string) ($pago['id'] ?? 0), 7, '0', STR_PAD_LEFT);
    }

    /** Promesas de pago pendientes del tenant, por fecha comprometida. */
    public function promesas(int $tenantId): array
    {
        return $this->select('pagos.*, personas.nombres, personas.apellidos, solicitudes.codigo_credito,
                              cobrador.nombres AS cob_nombres, cobrador.apellidos AS cob_apellidos')
            ->join('solicitudes', 'solicitudes.id = pagos.solicitud_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = pagos.empleado_id', 'left')
            ->join('personas cobrador', 'cobrador.id = emp.persona_id', 'left')
            ->where('pagos.tenant_id', $tenantId)
            ->where('pagos.estado', self::REVISION)
            ->where('pagos.tipo', self::TIPO_PROMESA)
            ->orderBy('pagos.fecha_hora', 'ASC')
            ->findAll();
    }

    /** Historial de pagos de un crédito (cualquier estado). */
    public function deCredito(int $solicitudId): array
    {
        return $this->select('pagos.*, cobrador.nombres AS cob_nombres, cobrador.apellidos AS cob_apellidos,
                              u.username AS apr_usuario')
            ->join('empleados emp', 'emp.id = pagos.empleado_id', 'left')
            ->join('personas cobrador', 'cobrador.id = emp.persona_id', 'left')
            ->join('users u', 'u.id = pagos.aprobado_por', 'left')
            ->where('pagos.solicitud_id', $solicitudId)
            ->orderBy('pagos.fecha_hora', 'DESC')
            ->orderBy('pagos.id', 'DESC')
            ->findAll();
    }
}
