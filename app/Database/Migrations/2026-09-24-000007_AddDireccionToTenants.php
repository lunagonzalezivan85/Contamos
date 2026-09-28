<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega direccion a tenants — datos de la empresa en Configuración.
 */
class AddDireccionToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'direccion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'telefono'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'direccion');
    }
}
