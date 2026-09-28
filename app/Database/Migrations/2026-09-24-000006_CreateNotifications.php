<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Notificaciones del sistema — por tenant y usuario.
 * user_id NULL = broadcast a todos los usuarios del tenant.
 */
class CreateNotifications extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'titulo'     => ['type' => 'VARCHAR', 'constraint' => 150],
            'mensaje'    => ['type' => 'VARCHAR', 'constraint' => 500],
            'tipo'       => ['type' => 'ENUM', 'constraint' => ['info', 'success', 'warning', 'danger'], 'default' => 'info'],
            'url'        => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'leida'      => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'user_id', 'leida'], false, false, 'idx_notifications_user');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_notifications_tenants');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE', 'fk_notifications_users');
        $this->forge->createTable('notifications', true);
    }

    public function down()
    {
        $this->forge->dropTable('notifications', true);
    }
}
