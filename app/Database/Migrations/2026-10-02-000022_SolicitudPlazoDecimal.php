<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * solicitudes.plazo_meses / plazo_aprobado: INT → DECIMAL(5,1).
 * Permite plazos fraccionados — p.ej. 2.5 meses a frecuencia semanal
 * genera 10 cuotas (antes se truncaba a 2 meses = 8 cuotas).
 */
class SolicitudPlazoDecimal extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('solicitudes', [
            'plazo_meses'    => ['type' => 'DECIMAL', 'constraint' => '5,1', 'null' => true],
            'plazo_aprobado' => ['type' => 'DECIMAL', 'constraint' => '5,1', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('solicitudes', [
            'plazo_meses'    => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'plazo_aprobado' => ['type' => 'INT', 'constraint' => 11, 'null' => true],
        ]);
    }
}
