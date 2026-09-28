<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Control de acceso: permissions (catálogo global), role_permissions,
 * menus dinámicos y role_menus
 */
class CreateSecurityAccess extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // permissions — catálogo global del sistema (sin tenant_id)
        // codigo formato: modulo.accion  ej. 'solicitudes.aprobar'
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'codigo'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'modulo'      => ['type' => 'VARCHAR', 'constraint' => 50],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('codigo', 'uq_permissions_codigo');
        $this->forge->addKey('modulo', false, false, 'idx_permissions_modulo');
        $this->forge->createTable('permissions', true);

        // ---------------------------------------------------------------
        // role_permissions
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'role_id'       => ['type' => 'BIGINT', 'unsigned' => true],
            'permission_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_role_permissions_tenant');
        $this->forge->addUniqueKey(['role_id', 'permission_id'], 'uq_role_permissions');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_role_permissions_tenants');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE', 'fk_role_permissions_roles');
        $this->forge->addForeignKey('permission_id', 'permissions', 'id', 'CASCADE', 'CASCADE', 'fk_role_permissions_permissions');
        $this->forge->createTable('role_permissions', true);

        // ---------------------------------------------------------------
        // menus — menú dinámico por tenant, jerárquico (parent_id)
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'parent_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'icono'      => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'orden'      => ['type' => 'INT', 'default' => 0],
            'estado'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_menus_tenant');
        $this->forge->addKey('parent_id', false, false, 'idx_menus_parent');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_menus_tenants');
        $this->forge->addForeignKey('parent_id', 'menus', 'id', 'CASCADE', 'SET NULL', 'fk_menus_parent');
        $this->forge->createTable('menus', true);

        // ---------------------------------------------------------------
        // role_menus — qué menús ve cada rol
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'role_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'menu_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_role_menus_tenant');
        $this->forge->addUniqueKey(['role_id', 'menu_id'], 'uq_role_menus');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_role_menus_tenants');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'CASCADE', 'fk_role_menus_roles');
        $this->forge->addForeignKey('menu_id', 'menus', 'id', 'CASCADE', 'CASCADE', 'fk_role_menus_menus');
        $this->forge->createTable('role_menus', true);
    }

    public function down()
    {
        $this->forge->dropTable('role_menus', true);
        $this->forge->dropTable('menus', true);
        $this->forge->dropTable('role_permissions', true);
        $this->forge->dropTable('permissions', true);
    }
}
