<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Reglas de cobro definidas por el negocio:
 *
 *  - MORA: se devenga SOLO después del vencimiento, sobre el pendiente de la
 *    cuota (pendiente × mora_diaria_pct × días vencida). No durante el crédito.
 *    `cuotas.mora_dev` congela lo devengado cuando la cuota queda PAGADA;
 *    `cuotas.mora` acumula lo cobrado.
 *  - PRONTO PAGO: opcional por tenant. Si el cliente liquida el crédito de
 *    una vez, se descuenta pronto_pago_pct del interés pendiente.
 *    `cuotas.descuento` guarda lo condonado (pagado + descuento = cuota).
 *  - SOBRE-PAGO: el excedente abona la siguiente cuota (FIFO ya existente);
 *    si sobra tras cubrir todo el plan queda en `solicitudes.saldo_favor`.
 */
class MoraProntoPagoSaldoFavor extends Migration
{
    public function up()
    {
        if (!$this->db->fieldExists('mora_diaria_pct', 'tenants')) {
            $this->forge->addColumn('tenants', [
                'mora_diaria_pct' => ['type' => 'DECIMAL', 'constraint' => '6,4', 'default' => 0, 'after' => 'tasa_interes'],
                'pronto_pago_pct' => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0, 'after' => 'mora_diaria_pct'],
            ]);
        }
        if (!$this->db->fieldExists('mora_dev', 'cuotas')) {
            $this->forge->addColumn('cuotas', [
                'mora_dev'  => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'pagado'],
                'mora'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'mora_dev'],
                'descuento' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'mora'],
            ]);
        }
        if (!$this->db->fieldExists('saldo_favor', 'solicitudes')) {
            $this->forge->addColumn('solicitudes', [
                'saldo_favor' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0, 'after' => 'codigo_credito'],
            ]);
        }
    }

    public function down()
    {
        if ($this->db->fieldExists('saldo_favor', 'solicitudes')) {
            $this->forge->dropColumn('solicitudes', 'saldo_favor');
        }
        if ($this->db->fieldExists('mora_dev', 'cuotas')) {
            $this->forge->dropColumn('cuotas', ['mora_dev', 'mora', 'descuento']);
        }
        if ($this->db->fieldExists('mora_diaria_pct', 'tenants')) {
            $this->forge->dropColumn('tenants', ['mora_diaria_pct', 'pronto_pago_pct']);
        }
    }
}
