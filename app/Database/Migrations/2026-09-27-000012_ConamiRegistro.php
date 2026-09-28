<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Registro CONAMI (Comisión Nacional de Microfinanzas, Ley 843).
 * El tenant guarda su número de registro; se muestra como sello
 * "Registrada ante CONAMI" en el portal público y en los documentos.
 */
class ConamiRegistro extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('conami_registro', 'tenants')) {
            $this->forge->addColumn('tenants', [
                'conami_registro' => ['type' => 'VARCHAR', 'constraint' => 40, 'null' => true, 'after' => 'ruc'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('conami_registro', 'tenants')) {
            $this->forge->dropColumn('tenants', 'conami_registro');
        }
    }
}
