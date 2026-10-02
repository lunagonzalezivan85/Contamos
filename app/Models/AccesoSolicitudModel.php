<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Solicitudes de acceso capturadas en la landing (POST /solicitar-acceso).
 */
class AccesoSolicitudModel extends Model
{
    const PENDIENTE  = 'PENDIENTE';
    const CONTACTADO = 'CONTACTADO';
    const RECHAZADO  = 'RECHAZADO';
    const CONTRATADO = 'CONTRATADO';
    const ESTADOS    = [self::PENDIENTE, self::CONTACTADO, self::RECHAZADO, self::CONTRATADO];

    protected $table         = 'acceso_solicitudes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nombre', 'negocio', 'telefono', 'correo', 'estado',
                                'codigo', 'plan_estimado', 'detalle', 'plan_id'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
