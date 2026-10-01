<?php

namespace App\Models;

use CodeIgniter\Model;

class EmpleadoModel extends Model
{
    protected $table            = 'empleados';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = ['tenant_id', 'persona_id', 'carnet', 'pin', 'cargo', 'ruta', 'fecha_ingreso', 'estado', 'puede_desembolsar'];

    /**
     * Siguiente carnet del tenant: iniciales del nombre + consecutivo (ej. TI-0001).
     */
    public function siguienteCarnet(int $tenantId, string $tenantNombre): string
    {
        // Iniciales de la empresa: primera letra de cada palabra
        preg_match_all('/[A-Za-zÁÉÍÓÚÜÑ]/u', preg_replace('/[^A-Za-zÁÉÍÓÚÜÑ ]/u', '', $tenantNombre), $m);
        $iniciales = '';
        foreach (explode(' ', trim(preg_replace('/\s+/', ' ', $tenantNombre))) as $palabra) {
            $ini = mb_substr(trim($palabra), 0, 1);
            if ($ini !== '') $iniciales .= mb_strtoupper($ini);
        }
        $iniciales = $iniciales !== '' ? $iniciales : 'E';

        $ultimo = $this->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->first();
        $consec = $ultimo ? ((int) $ultimo['id']) + 1 : 1;

        return $iniciales . '-' . str_pad((string) $consec, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Empleados del tenant unidos a su persona (builder del modelo).
     * Usar instancia fresca; permite ->paginate() en el controller.
     */
    public function filtrar(int $tenantId, string $buscar = ''): self
    {
        $this->select('empleados.*, personas.nombres, personas.apellidos, personas.cedula, personas.telefono, personas.email')
             ->join('personas', 'personas.id = empleados.persona_id')
             ->where('empleados.tenant_id', $tenantId)
             ->where('personas.tipo', 'EMPLEADO');

        if ($buscar !== '') {
            $this->groupStart()
                ->like('personas.nombres', $buscar)
                ->orLike('personas.apellidos', $buscar)
                ->orLike('personas.cedula', $buscar)
                ->orLike('personas.telefono', $buscar)
              ->groupEnd();
        }

        return $this->orderBy('personas.apellidos', 'ASC')->orderBy('personas.nombres', 'ASC');
    }

    /** Empleados del tenant unidos a su persona (array completo). */
    public function conPersona(int $tenantId, string $buscar = ''): array
    {
        return $this->filtrar($tenantId, $buscar)->findAll();
    }
}
