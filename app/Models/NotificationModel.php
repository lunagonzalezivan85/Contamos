<?php

namespace App\Models;

use CodeIgniter\Model;

class NotificationModel extends Model
{
    protected $table            = 'notifications';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = false;

    protected $allowedFields = [
        'tenant_id', 'user_id', 'titulo', 'mensaje', 'tipo', 'url', 'leida', 'created_at',
    ];

    /**
     * Notificaciones visibles para un usuario: las suyas + broadcast del tenant.
     */
    public function paraUsuario(int $tenantId, int $userId, int $limit = 20): array
    {
        return $this->where('tenant_id', $tenantId)
                    ->groupStart()
                        ->where('user_id', $userId)
                        ->orWhere('user_id', null)
                    ->groupEnd()
                    ->orderBy('created_at', 'DESC')
                    ->findAll($limit);
    }

    /**
     * Cantidad de no leídas para el badge de la campana.
     */
    public function noLeidas(int $tenantId, int $userId): int
    {
        return $this->where('tenant_id', $tenantId)
                    ->where('leida', 0)
                    ->groupStart()
                        ->where('user_id', $userId)
                        ->orWhere('user_id', null)
                    ->groupEnd()
                    ->countAllResults();
    }
}
