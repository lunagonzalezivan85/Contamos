<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * tenants: +tasa_interes (% mensual por defecto para créditos)
 *          +plazo_meses_max (plazo máximo permitido en solicitudes).
 */
class TenantCreditoDefaults extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'tasa_interes'   => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 3.00, 'after' => 'moneda'],
            'plazo_meses_max' => ['type' => 'INT', 'constraint' => 3, 'unsigned' => true, 'default' => 24, 'after' => 'tasa_interes'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', ['tasa_interes', 'plazo_meses_max']);
    }
}
