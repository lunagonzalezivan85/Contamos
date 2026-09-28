<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * pagos.tipo — PAGO (cobro real, va por aprobación) | PROMESA (compromiso
 * del cliente; no se aprueba hasta convertirse en cobro cuando pague).
 */
class PagosTipo extends Migration
{
    public function up()
    {
        $this->forge->addColumn('pagos', [
            'tipo' => [
                'type'       => 'VARCHAR',
                'constraint' => 10,
                'null'       => false,
                'default'    => 'PAGO',
                'after'      => 'metodo',
            ],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('pagos', 'tipo');
    }
}
