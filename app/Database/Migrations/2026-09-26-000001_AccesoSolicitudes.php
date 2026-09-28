<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Solicitudes de acceso enviadas desde la landing pública ("Solicita tu usuario").
 */
class AccesoSolicitudes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 120],
            'negocio'    => ['type' => 'VARCHAR', 'constraint' => 160],
            'telefono'   => ['type' => 'VARCHAR', 'constraint' => 30],
            'correo'     => ['type' => 'VARCHAR', 'constraint' => 160, 'null' => true],
            'estado'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDIENTE'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->createTable('acceso_solicitudes', true);
    }

    public function down()
    {
        $this->forge->dropTable('acceso_solicitudes', true);
    }
}
