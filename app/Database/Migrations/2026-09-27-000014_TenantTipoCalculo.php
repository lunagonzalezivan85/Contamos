<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * tenants.tipo_calculo — la columna ya existe (ProductoCredito, default
 * FRANCES). Esta migración cambia el default del tenant a FLAT
 * (interés = tasa mensual × meses sobre el capital) y normaliza los
 * tenants existentes; se puede cambiar por empresa en Configuración.
 */
class TenantTipoCalculo extends Migration
{
    public function up()
    {
        $t = $this->db->prefixTable('tenants');
        $this->db->query(
            "ALTER TABLE `{$t}` MODIFY `tipo_calculo` VARCHAR(12) NOT NULL DEFAULT 'FLAT'"
        );
        $this->db->table('tenants')
            ->where('tipo_calculo', 'FRANCES')
            ->update(['tipo_calculo' => 'FLAT']);
    }

    public function down()
    {
        $this->db->query(
            "ALTER TABLE `" . $this->db->prefixTable('tenants') . "` MODIFY `tipo_calculo` VARCHAR(12) NOT NULL DEFAULT 'FRANCES'"
        );
    }
}
