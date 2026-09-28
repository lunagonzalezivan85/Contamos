<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/** tenants: +quienes_somos, vision, mision, valores (contenido institucional para la landing). */
class TenantInstitucional extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'quienes_somos' => ['type' => 'TEXT', 'null' => true, 'after' => 'lema'],
            'mision'        => ['type' => 'TEXT', 'null' => true],
            'vision'        => ['type' => 'TEXT', 'null' => true],
            'valores'       => ['type' => 'TEXT', 'null' => true],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', ['quienes_somos', 'mision', 'vision', 'valores']);
    }
}
