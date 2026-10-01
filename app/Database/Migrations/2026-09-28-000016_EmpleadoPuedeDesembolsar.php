<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * empleados.puede_desembolsar — permiso por gestor para marcar el
 * desembolso como entregado (portal gestor y app IONIC).
 * Default 1 para no romper a los gestores existentes.
 */
class EmpleadoPuedeDesembolsar extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('puede_desembolsar', 'empleados')) {
            $this->forge->addColumn('empleados', [
                'puede_desembolsar' => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1, 'null' => false, 'after' => 'estado'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('puede_desembolsar', 'empleados')) {
            $this->forge->dropColumn('empleados', 'puede_desembolsar');
        }
    }
}
