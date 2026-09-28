<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * solicitudes: +origen (INTERNO = creada por gestor/admin, WEB = formulario público de la landing).
 * Las solicitudes WEB entran en estado CONTACTO ("Por contactar") sin gestor asignado.
 */
class SolicitudOrigenWeb extends Migration
{
    public function up()
    {
        $this->forge->addColumn('solicitudes', [
            'origen' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'INTERNO', 'after' => 'estado'],
        ]);
    }

    public function down()
    {
        $this->forge->dropColumn('solicitudes', 'origen');
    }
}
