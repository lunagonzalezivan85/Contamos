<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Valoraciones del sistema — encuesta periódica por usuario.
 *
 * Cada 5 días desde la última calificación el usuario ve el modal de
 * 5 estrellas + reseña (ver ValoracionService::pendiente). La reseña
 * es opcional; la puntuación (1-5) es obligatoria.
 */
class Valoraciones extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'user_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'estrellas'  => ['type' => 'TINYINT', 'unsigned' => true],
            'resena'     => ['type' => 'TEXT', 'null' => true],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'user_id'], false, 'idx_val_usuario');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_val_tenant');
        $this->forge->addForeignKey('user_id', 'users', 'id', 'CASCADE', 'CASCADE', 'fk_val_user');
        $this->forge->createTable('valoraciones', true);
    }

    public function down()
    {
        $this->forge->dropTable('valoraciones', true);
    }
}
