<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Fase 4 — cobranza SaaS:
 * - tenants.gracia_dias: días de gracia tras el dia_pago (del contrato) antes
 *   de que plan_al_dia() declare la suscripción VENCIDA.
 * - plan_pagos(tenant_id, periodo) UNIQUE: un cargo por tenant por período —
 *   el comando cobros:generar es idempotente gracias a esto.
 */
class TenantGraciaPlanPagosUnique extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('gracia_dias', 'tenants')) {
            $this->forge->addColumn('tenants', [
                'gracia_dias' => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 4, 'after' => 'dia_pago'],
            ]);
        }

        // Un solo cargo por tenant por período (dedupe por si quedó doble)
        $dup = $this->db->query('SELECT tenant_id, periodo, MIN(id) AS keep_id
            FROM plan_pagos GROUP BY tenant_id, periodo HAVING COUNT(*) > 1')->getResultArray();
        foreach ($dup as $d) {
            $this->db->table('plan_pagos')
                ->where('tenant_id', $d['tenant_id'])->where('periodo', $d['periodo'])
                ->where('id !=', $d['keep_id'])->delete();
        }

        $idx = $this->db->query("SHOW INDEX FROM plan_pagos WHERE Key_name = 'uq_tenant_periodo'")->getRowArray();
        if (!$idx) {
            $this->db->query('ALTER TABLE plan_pagos ADD UNIQUE KEY uq_tenant_periodo (tenant_id, periodo)');
        }
    }

    public function down()
    {
        $this->forge->dropColumn('tenants', 'gracia_dias');
        $this->db->query('ALTER TABLE plan_pagos DROP INDEX uq_tenant_periodo');
    }
}
