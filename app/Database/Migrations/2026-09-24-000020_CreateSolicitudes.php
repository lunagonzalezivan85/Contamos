<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** Solicitudes de crédito (por cliente, gestionadas por gestor). */
class CreateSolicitudes extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'cliente_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'empleado_id'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true], // gestor que la creó
            'monto'         => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'plazo_meses'   => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'destino'       => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'estado'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDIENTE'], // PENDIENTE|APROBADA|RECHAZADA
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'estado']);
        $this->forge->addKey('cliente_id');
        $this->forge->createTable('solicitudes', true);
    }

    public function down()
    {
        $this->forge->dropTable('solicitudes', true);
    }
}
