<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Auditoría de seguridad: password_reset_tokens, login_attempts, audit_logs
 */
class CreateSecurityAudit extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // password_reset_tokens
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'token'      => ['type' => 'VARCHAR', 'constraint' => 255],
            'expires_at' => ['type' => 'DATETIME'],
            'used'       => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_prt_tenant');
        $this->forge->addKey('token', false, false, 'idx_prt_token');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_prt_tenants');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE', 'fk_prt_users');
        $this->forge->createTable('password_reset_tokens', true);

        // ---------------------------------------------------------------
        // login_attempts — auditoría de intentos (exitosos y fallidos)
        // tenant_id/user_id NULL: el intento puede ser de un usuario inexistente
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'username'   => ['type' => 'VARCHAR', 'constraint' => 100],
            'ip'         => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'user_agent' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'exito'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_login_attempts_tenant');
        $this->forge->addKey('username', false, false, 'idx_login_attempts_username');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'SET NULL', 'fk_login_attempts_tenants');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_login_attempts_users');
        $this->forge->createTable('login_attempts', true);

        // ---------------------------------------------------------------
        // audit_logs — auditoría general de acciones de negocio
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'          => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'accion'           => ['type' => 'VARCHAR', 'constraint' => 100],
            'modulo'           => ['type' => 'VARCHAR', 'constraint' => 50],
            'entidad'          => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'entidad_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'datos_anteriores' => ['type' => 'TEXT', 'null' => true],
            'datos_nuevos'     => ['type' => 'TEXT', 'null' => true],
            'ip'               => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id', false, false, 'idx_audit_logs_tenant');
        $this->forge->addKey(['entidad', 'entidad_id'], false, false, 'idx_audit_logs_entidad');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_audit_logs_tenants');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'SET NULL', 'fk_audit_logs_users');
        $this->forge->createTable('audit_logs', true);
    }

    public function down()
    {
        $this->forge->dropTable('audit_logs', true);
        $this->forge->dropTable('login_attempts', true);
        $this->forge->dropTable('password_reset_tokens', true);
    }
}
