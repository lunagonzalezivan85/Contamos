<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Moneda del tenant — símbolo/código usado en vouchers y reportes.
 */
class AddMonedaToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'moneda' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'RD$', 'after' => 'hora_fin'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'moneda');
    }
}
