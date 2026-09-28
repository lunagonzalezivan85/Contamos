<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Producto de crédito por tenant + trazabilidad de aplicación de pagos.
 *
 * tenants:  tipo_calculo (FRANCES|FLAT|ALEMAN|ANTICIPADO),
 *           comision_pct / seguro_pct (% sobre el monto aprobado, cobrados al aprobar).
 *
 * solicitudes: tipo_calculo (override por crédito), gracia_meses + gracia_tipo
 *              (TOTAL = desplaza plan | INTERES = cuotas de solo interés),
 *              comision / seguro (montos capturados al aprobar),
 *              refinancia_id (crédito anterior que esta solicitud liquida),
 *              paso_dias (frecuencia personalizada P = cada N días).
 *
 * pago_aplicaciones: detalle de cómo cada pago se repartió por cuota —
 *                    la reversión deshace exactamente lo que ese pago cubrió.
 */
class ProductoCredito extends Migration
{
    public function up()
    {
        // ---------- tenants ----------
        $cols = $this->db->getFieldNames('tenants');
        $add  = [];
        if (!in_array('tipo_calculo', $cols, true)) {
            $add['tipo_calculo'] = ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false, 'default' => 'FRANCES'];
        }
        if (!in_array('comision_pct', $cols, true)) {
            $add['comision_pct'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false, 'default' => 0];
        }
        if (!in_array('seguro_pct', $cols, true)) {
            $add['seguro_pct'] = ['type' => 'DECIMAL', 'constraint' => '5,2', 'null' => false, 'default' => 0];
        }
        if ($add) {
            $this->forge->addColumn('tenants', $add);
        }

        // ---------- solicitudes ----------
        $cols = $this->db->getFieldNames('solicitudes');
        $add  = [];
        if (!in_array('tipo_calculo', $cols, true)) {
            $add['tipo_calculo'] = ['type' => 'VARCHAR', 'constraint' => 12, 'null' => false, 'default' => 'FRANCES'];
        }
        if (!in_array('gracia_meses', $cols, true)) {
            $add['gracia_meses'] = ['type' => 'INT', 'constraint' => 3, 'null' => false, 'default' => 0];
        }
        if (!in_array('gracia_tipo', $cols, true)) {
            $add['gracia_tipo'] = ['type' => 'VARCHAR', 'constraint' => 10, 'null' => true];
        }
        if (!in_array('comision', $cols, true)) {
            $add['comision'] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => false, 'default' => 0];
        }
        if (!in_array('seguro', $cols, true)) {
            $add['seguro'] = ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => false, 'default' => 0];
        }
        if (!in_array('refinancia_id', $cols, true)) {
            $add['refinancia_id'] = ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'null' => true];
        }
        if (!in_array('paso_dias', $cols, true)) {
            $add['paso_dias'] = ['type' => 'INT', 'constraint' => 3, 'null' => true];
        }
        if ($add) {
            $this->forge->addColumn('solicitudes', $add);
        }

        // ---------- pago_aplicaciones ----------
        if (!$this->db->tableExists('pago_aplicaciones')) {
            $this->forge->addField([
                'id'          => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'   => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'pago_id'     => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'cuota_id'    => ['type' => 'INT', 'constraint' => 10, 'unsigned' => true],
                'monto'       => ['type' => 'DECIMAL', 'constraint' => '12,2', 'null' => false],
                'tipo'        => ['type' => 'VARCHAR', 'constraint' => 10, 'null' => false, 'default' => 'CUOTA'],
                'created_at'  => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'pago_id']);
            $this->forge->addKey('cuota_id');
            $this->forge->createTable('pago_aplicaciones');
        }
    }

    public function down()
    {
        if ($this->db->tableExists('pago_aplicaciones')) {
            $this->forge->dropTable('pago_aplicaciones');
        }
        foreach (['paso_dias', 'refinancia_id', 'seguro', 'comision', 'gracia_tipo', 'gracia_meses', 'tipo_calculo'] as $c) {
            if (in_array($c, $this->db->getFieldNames('solicitudes'), true)) {
                $this->forge->dropColumn('solicitudes', $c);
            }
        }
        foreach (['seguro_pct', 'comision_pct', 'tipo_calculo'] as $c) {
            if (in_array($c, $this->db->getFieldNames('tenants'), true)) {
                $this->forge->dropColumn('tenants', $c);
            }
        }
    }
}
