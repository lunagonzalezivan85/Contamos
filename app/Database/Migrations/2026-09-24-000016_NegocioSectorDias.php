<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * persona_negocios: +sector_economico, +dias_venta (días de venta "L,X,J,V").
 */
class NegocioSectorDias extends Migration
{
    public function up()
    {
        $this->forge->addColumn('persona_negocios', [
            'sector_economico' => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true, 'after' => 'actividad'],
            'dias_venta'       => ['type' => 'VARCHAR', 'constraint' => 40,  'null' => true, 'after' => 'tiempo'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('persona_negocios', ['sector_economico', 'dias_venta']);
    }
}
