<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * persona_negocios: +promedio_venta_dia (venta promedio por día, DECIMAL).
 */
class NegocioPromedioVenta extends Migration
{
    public function up()
    {
        $this->forge->addColumn('persona_negocios', [
            'promedio_venta_dia' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'dias_venta'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('persona_negocios', 'promedio_venta_dia');
    }
}
