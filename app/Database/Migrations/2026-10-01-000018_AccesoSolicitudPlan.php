<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * acceso_solicitudes + codigo partner, plan estimado y detalle JSON —
 * el formulario /alta (calculadora de plan a medida) los necesita.
 */
class AccesoSolicitudPlan extends Migration
{
    public function up()
    {
        $cols = [
            'codigo'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true, 'after' => 'correo'],
            'plan_estimado' => ['type' => 'DECIMAL', 'constraint' => '10,2', 'null' => true, 'after' => 'codigo'],
            'detalle'       => ['type' => 'TEXT', 'null' => true, 'after' => 'plan_estimado'],
        ];
        foreach ($cols as $col => $def) {
            if (!$this->db->fieldExists($col, 'acceso_solicitudes')) {
                $this->forge->addColumn('acceso_solicitudes', [$col => $def]);
            }
        }
    }

    public function down()
    {
        $this->forge->dropColumn('acceso_solicitudes', ['codigo', 'plan_estimado', 'detalle']);
    }
}
