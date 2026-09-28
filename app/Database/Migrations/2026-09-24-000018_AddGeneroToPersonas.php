<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** personas: +genero (M = Hombre, F = Mujer). */
class AddGeneroToPersonas extends Migration
{
    public function up()
    {
        $this->forge->addColumn('personas', [
            'genero' => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true, 'after' => 'apellidos'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('personas', 'genero');
    }
}
