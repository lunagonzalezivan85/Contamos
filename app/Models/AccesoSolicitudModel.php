<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Solicitudes de acceso capturadas en la landing (POST /solicitar-acceso).
 */
class AccesoSolicitudModel extends Model
{
    protected $table         = 'acceso_solicitudes';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['nombre', 'negocio', 'telefono', 'correo', 'estado'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
