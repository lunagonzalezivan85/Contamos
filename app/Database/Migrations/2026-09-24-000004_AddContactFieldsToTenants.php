<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Agrega datos de empresa al tenant: razón social y contacto principal.
 */
class AddContactFieldsToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'razon_social'    => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'nombre'],
            'contacto_nombre' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true, 'after' => 'telefono'],
            'contacto_cargo'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'contacto_nombre'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', ['razon_social', 'contacto_nombre', 'contacto_cargo']);
    }
}
