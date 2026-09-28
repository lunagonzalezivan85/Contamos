<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** solicitudes: +frecuencia de pago, tasa mensual y días/semana (diario intermitente). */
class SolicitudPagoFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('solicitudes', [
            'frecuencia'    => ['type' => 'VARCHAR', 'constraint' => 10,   'null' => true, 'after' => 'plazo_meses'],
            'tasa_mensual'  => ['type' => 'DECIMAL', 'constraint' => '6,3','null' => true],
            'dias_semana'   => ['type' => 'INT',     'constraint' => 1,    'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('solicitudes', ['frecuencia', 'tasa_mensual', 'dias_semana']);
    }
}
