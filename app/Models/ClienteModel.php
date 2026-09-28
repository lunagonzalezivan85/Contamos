<?php

namespace App\Models;

use CodeIgniter\Model;

class ClienteModel extends Model
{
    protected $table            = 'clientes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = ['tenant_id', 'persona_id', 'codigo', 'limite_credito', 'monto_max', 'monto_min', 'observaciones', 'estado'];

    /**
     * Siguiente código de cliente: "C-" + iniciales de la empresa + consecutivo (ej. C-TI-0001).
     */
    public function siguienteCodigo(int $tenantId, string $tenantNombre): string
    {
        $iniciales = '';
        foreach (explode(' ', trim(preg_replace('/\s+/', ' ', $tenantNombre))) as $palabra) {
            $ini = mb_substr(trim($palabra), 0, 1);
            if ($ini !== '') $iniciales .= mb_strtoupper($ini);
        }
        $iniciales = $iniciales !== '' ? $iniciales : 'C';

        $ultimo = $this->where('tenant_id', $tenantId)->orderBy('id', 'DESC')->first();
        $consec = $ultimo ? ((int) $ultimo['id']) + 1 : 1;

        return 'C-' . $iniciales . '-' . str_pad((string) $consec, 4, '0', STR_PAD_LEFT);
    }

    /**
     * Query del listado de clientes (acumula sobre el builder del modelo).
     * Usar instancia fresca; permite ->paginate() en el controller.
     */
    public function filtrar(int $tenantId, string $buscar = ''): self
    {
        $this->select('clientes.*, personas.nombres, personas.apellidos, personas.cedula, personas.telefono, personas.email, personas.direccion')
             ->join('personas', 'personas.id = clientes.persona_id')
             ->where('clientes.tenant_id', $tenantId)
             ->where('personas.tipo', 'CLIENTE');

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

    public function conPersona(int $tenantId, string $buscar = ''): array
    {
        return $this->filtrar($tenantId, $buscar)->findAll();
    }
}
