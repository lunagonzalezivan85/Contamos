<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * solicitudes: fecha_desembolso — el día pactado para entregar el dinero.
 * Se define en la pantalla de desembolso (estado APROBADA → DESEMBOLSO).
 */
class SolicitudFechaDesembolso extends Migration
{
    public function up()
    {
        $this->forge->addColumn('solicitudes', [
            'fecha_desembolso' => ['type' => 'DATE', 'null' => true, 'after' => 'fecha_primer_pago'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('solicitudes', 'fecha_desembolso');
    }
}
