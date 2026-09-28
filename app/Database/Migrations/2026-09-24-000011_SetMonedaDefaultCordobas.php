<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Corrige el default de tenants.moneda: C$ (córdoba nicaragüense).
 * Opciones del sistema: C$ y USD únicamente.
 */
class SetMonedaDefaultCordobas extends Migration
{
    public function up()
    {
        $this->forge->modifyColumn('tenants', [
            'moneda' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'C$'],
        ]);
    }

    public function down()
    {
        $this->forge->modifyColumn('tenants', [
            'moneda' => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'RD$'],
        ]);
    }
}
