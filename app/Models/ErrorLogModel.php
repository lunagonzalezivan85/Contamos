<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Bitácora de errores de runtime. El detalle solo lo ve el admin
 * (/admin/auditoria/errores) — al usuario nunca se muestra crudo.
 */
class ErrorLogModel extends Model
{
    protected $table         = 'error_log';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';

    protected $allowedFields = ['tenant_id', 'user_id', 'origen', 'mensaje', 'traza'];

    /** Purga logs con más de $dias días. */
    public function purgar(int $dias): int
    {
        $limite = date('Y-m-d H:i:s', time() - max(1, $dias) * 86400);
        $this->where('created_at <', $limite)->delete();
        return (int) $this->db->affectedRows();
    }
}
