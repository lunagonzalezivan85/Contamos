<?php

namespace App\Models;

use CodeIgniter\Model;

class PersonaModel extends Model
{
    protected $table            = 'personas';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'tipo', 'nombres', 'apellidos', 'genero', 'cedula', 'telefono',
        'email', 'direccion', 'fecha_nac', 'cargo', 'fecha_ingreso', 'estado',
    ];

    /**
     * Personas de un tipo dentro del tenant (CLIENTE | EMPLEADO).
     */
    public function porTipo(int $tenantId, string $tipo, string $buscar = ''): array
    {
        $b = $this->where('tenant_id', $tenantId)->where('tipo', $tipo);

        if ($buscar !== '') {
            $b->groupStart()
                ->like('nombres', $buscar)
                ->orLike('apellidos', $buscar)
                ->orLike('cedula', $buscar)
                ->orLike('telefono', $buscar)
              ->groupEnd();
        }

        return $b->orderBy('apellidos', 'ASC')->orderBy('nombres', 'ASC')->findAll();
    }

    /**
     * Una persona del tenant + tipo (valida pertenencia).
     */
    public function deTenant(int $tenantId, int $id, string $tipo): ?array
    {
        return $this->where('tenant_id', $tenantId)->where('tipo', $tipo)->find($id);
    }

    public function nombreCompleto(array $p): string
    {
        return trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''));
    }
}
