<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * acceso_solicitudes + plan_id — el modal «Contactar» de /admin/leads
 * deja elegir el plan de la propuesta (antes el cálculo siempre usaba Básico).
 */
class LeadPlanId extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('plan_id', 'acceso_solicitudes')) {
            $this->forge->addColumn('acceso_solicitudes', [
                'plan_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'detalle'],
            ]);
        }
    }

    public function down()
    {
        $this->forge->dropColumn('acceso_solicitudes', 'plan_id');
    }
}
