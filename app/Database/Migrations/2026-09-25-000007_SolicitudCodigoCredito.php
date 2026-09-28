<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * solicitudes: codigo_credito — código de crédito formato C-{INI}-{AAAA}-{0000}
 * (iniciales de la empresa + año + correlativo anual). Se genera al entregar
 * el desembolso (DESEMBOLSO → ACTIVO).
 */
class SolicitudCodigoCredito extends Migration
{
    public function up()
    {
        $this->forge->addColumn('solicitudes', [
            'codigo_credito' => ['type' => 'VARCHAR', 'constraint' => 25, 'null' => true, 'after' => 'fecha_desembolso'],
        ]);
        $this->forge->addKey(['tenant_id', 'codigo_credito'], false, true, 'uk_credito_codigo');
    }

    public function down()
    {
        $this->forge->dropKey('solicitudes', 'uk_credito_codigo');
        $this->forge->dropColumn('solicitudes', 'codigo_credito');
    }
}
