<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * solicitudes: campos del préstamo APROBADO (pueden diferir de lo solicitado)
 * + fecha del primer pago definida en la aprobación.
 * Lo solicitado queda en monto/tasa_mensual/plazo_meses/frecuencia.
 */
class SolicitudAprobacionFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('solicitudes', [
            'monto_aprobado'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => true, 'after' => 'origen'],
            'tasa_aprobada'       => ['type' => 'DECIMAL', 'constraint' => '5,2',  'null' => true, 'after' => 'monto_aprobado'],
            'plazo_aprobado'      => ['type' => 'INT',     'constraint' => 11,     'null' => true, 'after' => 'tasa_aprobada'],
            'frecuencia_aprobada' => ['type' => 'VARCHAR', 'constraint' => 5,      'null' => true, 'after' => 'plazo_aprobado'],
            'fecha_primer_pago'   => ['type' => 'DATE',    'null' => true, 'after' => 'frecuencia_aprobada'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('solicitudes', [
            'monto_aprobado', 'tasa_aprobada', 'plazo_aprobado', 'frecuencia_aprobada', 'fecha_primer_pago',
        ]);
    }
}
