<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Identidad fiscal del tenant — RUC (Registro Único de Contribuyente).
 * Se imprime en contratos y recibos de cobro (transparencia Ley 842 /
 * comprobantes que exige la operación de una microfinanciera en Nicaragua).
 */
class TenantRuc extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('ruc', 'tenants')) {
            $this->forge->addColumn('tenants', [
                'ruc' => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true, 'after' => 'razon_social'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('ruc', 'tenants')) {
            $this->forge->dropColumn('tenants', 'ruc');
        }
    }
}
