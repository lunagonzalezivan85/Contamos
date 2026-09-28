<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Categorías de gasto — catálogo por tenant ("tipo de gasto").
 * Se siembran las DEFAULTS la primera vez que el tenant entra al módulo.
 */
class GastoCategoriaModel extends Model
{
    protected $table            = 'gasto_categorias';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'nombre', 'descripcion', 'estado',
    ];

    public const ACTIVO   = 'ACTIVO';
    public const INACTIVO = 'INACTIVO';

    /** Categorías que se crean por defecto en cada tenant. */
    public const DEFAULTS = [
        'Oficina',
        'Combustible',
        'Viáticos',
        'Papelería',
        'Servicios básicos',
        'Mantenimiento',
        'Otro',
    ];

    /** Categorías activas del tenant ordenadas alfabéticamente. */
    public function activas(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)
            ->where('estado', self::ACTIVO)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }

    /** Todas las categorías del tenant (activas primero). */
    public function deTenant(int $tenantId): array
    {
        return $this->where('tenant_id', $tenantId)
            ->orderBy("estado = 'ACTIVO'", 'DESC', false)
            ->orderBy('nombre', 'ASC')
            ->findAll();
    }
}
