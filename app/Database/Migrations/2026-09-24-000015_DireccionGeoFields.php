<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * persona_direcciones: +departamento, barrio, latitud, longitud para geolocalización.
 */
class DireccionGeoFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('persona_direcciones', [
            'departamento' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'tipo'],
            'barrio'       => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'ciudad'],
            'latitud'      => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'detalle'],
            'longitud'     => ['type' => 'DECIMAL', 'constraint' => '10,7', 'null' => true, 'after' => 'latitud'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('persona_direcciones', ['departamento', 'barrio', 'latitud', 'longitud']);
    }
}
