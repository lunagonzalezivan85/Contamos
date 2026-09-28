<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * empleados: +carnet (iniciales tenant + consecutivo, ej. TI-0001) y +pin (acceso a la plataforma).
 */
class AddCarnetPinToEmpleados extends Migration
{
    public function up()
    {
        $this->forge->addColumn('empleados', [
            'carnet' => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'persona_id'],
            'pin'    => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'carnet'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('empleados', ['carnet', 'pin']);
    }
}
