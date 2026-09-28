<?php

namespace App\Models;

use CodeIgniter\Model;

class PlantillaModel extends Model
{
    protected $table            = 'plantillas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'nombre', 'slug', 'descripcion', 'contenido', 'estado',
    ];

    /**
     * Plantillas activas de un tenant.
     */
    public function porTenant(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)
                    ->where('estado', 'ACTIVO')
                    ->orderBy('nombre', 'ASC')
                    ->findAll();
    }

    /**
     * Una plantilla del tenant (valida pertenencia).
     */
    public function deTenant(int $tenantId, int $id): ?array
    {
        return $this->where('tenant_id', $tenantId)->find($id);
    }
}
