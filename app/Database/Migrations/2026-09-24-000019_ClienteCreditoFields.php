<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * clientes: +codigo (C-INICIALES-0001), limite_credito, monto_max, monto_min, observaciones.
 */
class ClienteCreditoFields extends Migration
{
    public function up()
    {
        $this->forge->addColumn('clientes', [
            'codigo'         => ['type' => 'VARCHAR', 'constraint' => 30,    'null' => true,  'after' => 'persona_id'],
            'limite_credito' => ['type' => 'DECIMAL', 'constraint' => '12,2','null' => true],
            'monto_max'      => ['type' => 'DECIMAL', 'constraint' => '12,2','null' => true],
            'monto_min'      => ['type' => 'DECIMAL', 'constraint' => '12,2','null' => true],
            'observaciones'  => ['type' => 'TEXT',    'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('clientes', ['codigo','limite_credito','monto_max','monto_min','observaciones']);
    }
}
