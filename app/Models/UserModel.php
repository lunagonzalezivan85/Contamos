<?php

namespace App\Models;

use CodeIgniter\Model;

class UserModel extends Model
{
    protected $table            = 'users';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = 'updated_at';
    protected $deletedField     = 'deleted_at';

    protected $allowedFields = [
        'tenant_id', 'role_id', 'persona_id', 'username', 'email',
        'password_hash', 'nombre', 'estado', 'debe_cambiar_password',
        'ultimo_login', 'created_by', 'updated_by',
    ];

    protected $validationRules = [
        'username'      => 'required|min_length[3]|max_length[100]',
        'email'         => 'required|valid_email|max_length[150]',
        'password_hash' => 'required',
        'role_id'       => 'required|integer',
        'tenant_id'     => 'required|integer',
    ];

    /**
     * Busca un usuario activo por username dentro de un tenant.
     */
    public function findByUsername(int $tenantId, string $username): ?array
    {
        return $this->where('tenant_id', $tenantId)
                    ->where('username', $username)
                    ->where('estado', 'ACTIVO')
                    ->first();
    }

    /**
     * Busca un usuario activo por username sin importar el tenant.
     * El login identifica la empresa a partir del usuario.
     * Si el username existiera en varios tenants, gana el de menor id.
     */
    public function findByUsernameGlobal(string $username): ?array
    {
        return $this->where('username', $username)
                    ->where('estado', 'ACTIVO')
                    ->orderBy('id', 'ASC')
                    ->first();
    }

    /**
     * Busca un usuario activo por email dentro de un tenant.
     */
    public function findByEmail(int $tenantId, string $email): ?array
    {
        return $this->where('tenant_id', $tenantId)
                    ->where('email', $email)
                    ->where('estado', 'ACTIVO')
                    ->first();
    }
}
