<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * tenants.app_codigo — código corto (ej. CONT-8492) para vincular la app
 * móvil del gestor sin escribir la URL. Lo muestra el admin en la ficha
 * del tenant; la app lo resuelve vía GET /resolve/{codigo}.
 */
class TenantAppCodigo extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('app_codigo', 'tenants')) {
            $this->forge->addColumn('tenants', [
                'app_codigo' => ['type' => 'VARCHAR', 'constraint' => 12, 'null' => true, 'after' => 'slug'],
            ]);
            $this->forge->addKey('app_codigo', true, true, 'uq_tenants_app_codigo');
            $this->forge->processIndexes('tenants');
        }

        // Código a los tenants existentes: CONT- + 4 dígitos únicos
        foreach ($this->db->table('tenants')->where('app_codigo IS NULL')->get()->getResultArray() as $t) {
            do {
                $cod = 'CONT-' . random_int(1000, 9999);
            } while ($this->db->table('tenants')->where('app_codigo', $cod)->countAllResults() > 0);
            $this->db->table('tenants')->where('id', $t['id'])->update(['app_codigo' => $cod]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('app_codigo', 'tenants')) {
            $this->forge->dropKey('tenants', 'uq_tenants_app_codigo');
            $this->forge->dropColumn('tenants', 'app_codigo');
        }
    }
}
