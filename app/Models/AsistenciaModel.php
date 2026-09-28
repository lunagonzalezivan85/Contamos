<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Jornadas de asistencia del personal (kiosco carnet+PIN).
 * ABIERTA = marcó entrada y aún no sale; CERRADA = jornada completa;
 * ANULADA = descartada por el admin (no cuenta en reportes).
 */
class AsistenciaModel extends Model
{
    protected $table         = 'asistencias';
    protected $primaryKey    = 'id';
    protected $useAutoIncrement = true;
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $allowedFields = [
        'tenant_id', 'empleado_id', 'fecha', 'entrada', 'salida',
        'estado', 'editada', 'editado_por', 'registrado_por',
        'observacion', 'ip',
    ];

    public const ABIERTA = 'ABIERTA';
    public const CERRADA = 'CERRADA';
    public const ANULADA = 'ANULADA';

    /** Jornada abierta del empleado (la más reciente). */
    public function abiertaDe(int $empleadoId): ?array
    {
        return $this->where('empleado_id', $empleadoId)
            ->where('estado', self::ABIERTA)
            ->orderBy('entrada', 'DESC')
            ->first();
    }

    /** Horas trabajadas de la jornada (0 si sigue abierta). */
    public static function horas(array $a): float
    {
        if (empty($a['salida']) || empty($a['entrada'])) {
            return 0.0;
        }
        return max(0, round((strtotime($a['salida']) - strtotime($a['entrada'])) / 3600, 2));
    }
}
