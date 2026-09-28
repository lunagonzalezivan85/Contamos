<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Catálogo de métricas medibles del plan de metas.
 * - calculo AUTO: el avance lo calcula MetaService según 'codigo'.
 * - calculo MANUAL: el avance se ingresa a mano en cada meta.
 * - modo MINIMO: cumple si avance >= meta (recuperación, captación...).
 * - modo MAXIMO: cumple si avance <= meta (tasa de mora...).
 */
class MetaMetricaModel extends Model
{
    protected $table         = 'meta_metricas';
    protected $primaryKey    = 'id';
    protected $allowedFields = [
        'tenant_id', 'codigo', 'nombre', 'unidad', 'modo', 'calculo', 'formula', 'estado',
    ];
    protected $useTimestamps = true;
    protected $returnType    = 'array';

    public const ACTIVO   = 'ACTIVO';
    public const INACTIVO = 'INACTIVO';

    public const UNIDADES = [
        'MONTO'      => 'Monto',
        'CANTIDAD'   => 'Cantidad',
        'PORCENTAJE' => 'Porcentaje (%)',
    ];

    public const MODOS = [
        'MINIMO' => 'Alcanzar al menos',
        'MAXIMO' => 'No superar',
    ];

    /** Métricas de sistema que se siembran por tenant. */
    public const DEFAULTS = [
        [
            'codigo' => 'recuperacion', 'nombre' => 'Monto de recuperación',
            'unidad' => 'MONTO', 'modo' => 'MINIMO', 'calculo' => 'AUTO',
            'formula' => 'Suma de cobros APLICADOS registrados por el gestor dentro del mes.',
        ],
        [
            'codigo' => 'captacion', 'nombre' => 'Captación de clientes nuevos',
            'unidad' => 'CANTIDAD', 'modo' => 'MINIMO', 'calculo' => 'AUTO',
            'formula' => 'Clientes dados de alta en el mes que tienen al menos una solicitud creada por el gestor en el mes.',
        ],
        [
            'codigo' => 'solicitudes', 'nombre' => 'Solicitudes ingresadas',
            'unidad' => 'CANTIDAD', 'modo' => 'MINIMO', 'calculo' => 'AUTO',
            'formula' => 'Cantidad de solicitudes de crédito creadas por el gestor dentro del mes.',
        ],
        [
            'codigo' => 'colocacion', 'nombre' => 'Monto colocado (desembolsos)',
            'unidad' => 'MONTO', 'modo' => 'MINIMO', 'calculo' => 'AUTO',
            'formula' => 'Suma del monto aprobado de créditos del gestor con fecha de desembolso dentro del mes.',
        ],
        [
            'codigo' => 'mora', 'nombre' => 'Tasa de mora de cartera',
            'unidad' => 'PORCENTAJE', 'modo' => 'MAXIMO', 'calculo' => 'AUTO',
            'formula' => '100 × saldo vencido de cuotas impagas ÷ saldo pendiente total de la cartera del gestor, al cierre del periodo.',
        ],
    ];

    /** Métricas activas del tenant. */
    public function activas(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('estado', self::ACTIVO)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /** Todas (para el modal de gestión). */
    public function deTenant(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)
            ->orderBy('codigo', 'ASC')
            ->findAll();
    }

    public function deTenantPorId(int $tenantId, int $id): ?array
    {
        return $this->where('tenant_id', $tenantId)->find($id);
    }
}
