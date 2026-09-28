<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reversión de pagos — `pagos.revierte_id` liga el contra-pago (monto
 * negativo en REVISION) con el pago original APLICADO que anula.
 *
 * Flujo: revertir → contra-pago REVISION (monto −X, observación obligatoria)
 *      → aprobar: desaplica monto de cuotas (pagado −X), marca contra-pago
 *        APLICADO y el original REVERTIDO.
 */
class PagoRevierte extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('revierte_id', 'pagos')) {
            $this->forge->addColumn('pagos', [
                'revierte_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true, 'after' => 'cuota_id'],
            ]);
            $this->forge->addForeignKey('revierte_id', 'pagos', 'id', 'CASCADE', 'SET NULL', 'fk_pagos_revierte');
            $this->forge->processIndexes('pagos');
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('revierte_id', 'pagos')) {
            $this->forge->dropForeignKey('pagos', 'fk_pagos_revierte');
            $this->forge->dropColumn('pagos', 'revierte_id');
        }
    }
}
