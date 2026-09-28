<?php

namespace App\Models;

use CodeIgniter\Model;

/** Análisis financiero persistido por solicitud (se calcula manualmente). */
class SolicitudAnalisisModel extends Model
{
    protected $table            = 'solicitud_analisis';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'solicitud_id', 'cliente_id',
        'ingresos', 'cuota', 'cuota_mes', 'activos', 'pasivos', 'patrimonio',
        'ratio', 'nivel',
    ];

    /** Niveles de capacidad según % del ingreso comprometido con la cuota. */
    public const NIVELES = [
        'BUENO'     => 'Capacidad suficiente',
        'AJUSTADO'  => 'Capacidad ajustada',
        'RIESGO'    => 'Riesgo alto',
        'SIN_DATOS' => 'Sin datos de ingresos',
    ];

    /** Análisis de una solicitud dentro del tenant (o null si no se ha calculado). */
    public function deSolicitud(int $tenantId, int $solicitudId): ?array
    {
        return $this->where('tenant_id', $tenantId)
                    ->where('solicitud_id', $solicitudId)
                    ->first();
    }
}
