<?php

namespace App\Models;

use CodeIgniter\Model;

class RoleModel extends Model
{
    protected $table            = 'roles';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'nombre', 'slug', 'descripcion', 'es_sistema', 'estado',
    ];

    /**
     * Códigos de permiso asignados a un rol.
     *
     * @return string[] ej. ['solicitudes.ver', 'pagos.registrar']
     */
    public function permissionCodes(int $roleId, int $tenantId): array
    {
        $rows = $this->db->table('role_permissions rp')
            ->select('p.codigo')
            ->join('permissions p', 'p.id = rp.permission_id')
            ->where('rp.role_id', $roleId)
            ->where('rp.tenant_id', $tenantId)
            ->get()
            ->getResultArray();

        return array_column($rows, 'codigo');
    }

    /**
     * Menús (con jerarquía) asignados a un rol.
     */
    public function menus(int $roleId, int $tenantId): array
    {
        return $this->db->table('role_menus rm')
            ->select('m.id, m.parent_id, m.nombre, m.slug, m.icono, m.url, m.orden')
            ->join('menus m', 'm.id = rm.menu_id')
            ->where('rm.role_id', $roleId)
            ->where('rm.tenant_id', $tenantId)
            ->where('m.estado', 'ACTIVO')
            ->orderBy('m.orden', 'ASC')
            ->get()
            ->getResultArray();
    }
}
