<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Núcleo de seguridad multi-tenant: tenants, roles, users
 * Convenciones: docs/CONVENCIONES.md
 */
class CreateSecurityCore extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // tenants — raíz del multi-tenant (sin tenant_id propio)
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'      => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'telefono'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'logo'       => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'estado'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
            'deleted_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey('slug', 'uq_tenants_slug');
        $this->forge->createTable('tenants', true);

        // ---------------------------------------------------------------
        // roles — tenant_id NULL = rol de sistema (aplica a todos)
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 100],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'es_sistema'  => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'estado'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_roles_tenant');
        $this->forge->addUniqueKey(['tenant_id', 'slug'], 'uq_roles_tenant_slug');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_roles_tenants');
        $this->forge->createTable('roles', true);

        // ---------------------------------------------------------------
        // users
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'                    => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'             => ['type' => 'BIGINT', 'unsigned' => true],
            'role_id'               => ['type' => 'BIGINT', 'unsigned' => true],
            'persona_id'            => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'username'              => ['type' => 'VARCHAR', 'constraint' => 100],
            'email'                 => ['type' => 'VARCHAR', 'constraint' => 150],
            'password_hash'         => ['type' => 'VARCHAR', 'constraint' => 255],
            'nombre'                => ['type' => 'VARCHAR', 'constraint' => 150],
            'estado'                => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'debe_cambiar_password' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'ultimo_login'          => ['type' => 'DATETIME', 'null' => true],
            'created_at'            => ['type' => 'DATETIME', 'null' => true],
            'updated_at'            => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'            => ['type' => 'DATETIME', 'null' => true],
            'created_by'            => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'updated_by'            => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_users_tenant');
        $this->forge->addKey('role_id', false, false, 'idx_users_role');
        $this->forge->addUniqueKey(['tenant_id', 'username'], 'uq_users_tenant_username');
        $this->forge->addUniqueKey(['tenant_id', 'email'], 'uq_users_tenant_email');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'RESTRICT', 'fk_users_tenants');
        $this->forge->addForeignKey('role_id', 'roles', 'id', 'CASCADE', 'RESTRICT', 'fk_users_roles');
        $this->forge->createTable('users', true);
    }

    public function down()
    {
        $this->forge->dropTable('users', true);
        $this->forge->dropTable('roles', true);
        $this->forge->dropTable('tenants', true);
    }
}
