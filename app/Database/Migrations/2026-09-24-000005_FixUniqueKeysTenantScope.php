<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Corrige unique keys que no incluían tenant_id:
 * el mismo rol+permiso (o rol+menú) debe poder asignarse en cada tenant.
 * Los nombres de tabla pasan por prefixTable() para respetar DBPrefix.
 */
class FixUniqueKeysTenantScope extends Migration
{
    public function up()
    {
        // Los FK de role_id usan el unique como índice — hay que crear
        // un índice propio antes de poder soltarlo.
        $rp = $this->db->prefixTable('role_permissions');
        $rm = $this->db->prefixTable('role_menus');

        $this->db->query("ALTER TABLE $rp ADD INDEX idx_rp_role (role_id)");
        $this->db->query("ALTER TABLE $rp DROP INDEX uq_role_permissions");
        $this->db->query("ALTER TABLE $rp ADD UNIQUE KEY uq_role_permissions (tenant_id, role_id, permission_id)");

        $this->db->query("ALTER TABLE $rm ADD INDEX idx_rm_role (role_id)");
        $this->db->query("ALTER TABLE $rm DROP INDEX uq_role_menus");
        $this->db->query("ALTER TABLE $rm ADD UNIQUE KEY uq_role_menus (tenant_id, role_id, menu_id)");
    }

    public function down()
    {
        $rp = $this->db->prefixTable('role_permissions');
        $rm = $this->db->prefixTable('role_menus');

        $this->db->query("ALTER TABLE $rp DROP INDEX uq_role_permissions");
        $this->db->query("ALTER TABLE $rp ADD UNIQUE KEY uq_role_permissions (role_id, permission_id)");
        $this->db->query("ALTER TABLE $rp DROP INDEX idx_rp_role");

        $this->db->query("ALTER TABLE $rm DROP INDEX uq_role_menus");
        $this->db->query("ALTER TABLE $rm ADD UNIQUE KEY uq_role_menus (role_id, menu_id)");
        $this->db->query("ALTER TABLE $rm DROP INDEX idx_rm_role");
    }
}
