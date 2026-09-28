<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Ventana horaria del sistema por tenant — gestores solo operan dentro del rango.
 * hora_inicio/hora_fin NULL = sin restricción.
 */
class AddHorarioSistemaToTenants extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'hora_inicio' => ['type' => 'TIME', 'null' => true, 'after' => 'horario'],
            'hora_fin'    => ['type' => 'TIME', 'null' => true, 'after' => 'hora_inicio'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', ['hora_inicio', 'hora_fin']);
    }
}
