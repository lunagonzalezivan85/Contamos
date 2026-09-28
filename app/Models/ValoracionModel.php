<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Valoraciones del sistema — encuesta de 5 estrellas + reseña
 * que se le pide a cada usuario cada 5 días.
 */
class ValoracionModel extends Model
{
    protected $table         = 'valoraciones';
    protected $primaryKey    = 'id';
    protected $allowedFields = ['tenant_id', 'user_id', 'estrellas', 'resena'];
    protected $useTimestamps = true;
}
