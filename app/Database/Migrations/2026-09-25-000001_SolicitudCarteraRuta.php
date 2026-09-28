<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cartera de clientes / rutas:
 * - empleados    + ruta
 * - solicitudes  + asignado_a (gestor que cobra) + ruta (copia de la ruta del gestor)
 */
class SolicitudCarteraRuta extends Migration
{
    public function up()
    {
        $this->forge->addColumn('empleados', [
            'ruta' => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'cargo'],
        ]);

        $this->forge->addColumn('solicitudes', [
            'asignado_a' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true, 'after' => 'empleado_id'],
            'ruta'       => ['type' => 'VARCHAR', 'constraint' => 80, 'null' => true, 'after' => 'asignado_a'],
        ]);
        $this->forge->addForeignKey('asignado_a', 'empleados', 'id', 'SET NULL', 'CASCADE');
        $this->db->table('solicitudes');
        $this->forge->processIndexes('solicitudes');
    }

    public function down()
    {
        $this->forge->dropForeignKey('solicitudes', 'solicitudes_asignado_a_foreign');
        $this->forge->dropColumn('solicitudes', ['asignado_a', 'ruta']);
        $this->forge->dropColumn('empleados', 'ruta');
    }
}
