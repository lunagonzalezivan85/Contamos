<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * contratos — contrato de servicio SaaS generado desde un lead CONTACTADO
 * (/admin/leads → «Generar contrato»). Guarda las condiciones pactadas:
 * día de pago (5|10), gracia, cuenta bancaria y monto mensual.
 * tenant_id queda NULL hasta que el tenant se provisiona (alta manual).
 */
class Contratos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'                  => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
            'acceso_solicitud_id' => ['type' => 'INT', 'unsigned' => true],
            'tenant_id'           => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'plan_id'             => ['type' => 'INT', 'unsigned' => true, 'null' => true],
            'monto_mensual'       => ['type' => 'DECIMAL', 'constraint' => '10,2'],
            'dia_pago'            => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 5],
            'gracia_dias'         => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 4],
            'cuenta_bancaria'     => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true],
            'terminos'            => ['type' => 'TEXT', 'null' => true],
            'estado'              => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'fecha_inicio'        => ['type' => 'DATE'],
            'fecha_corte'         => ['type' => 'DATE', 'null' => true],
            'created_at'          => ['type' => 'DATETIME', 'null' => true],
            'updated_at'          => ['type' => 'DATETIME', 'null' => true],
        ])->addKey('id', true)
          ->addKey('acceso_solicitud_id')
          ->addKey('tenant_id')
          ->createTable('contratos', true);
    }

    public function down()
    {
        $this->forge->dropTable('contratos', true);
    }
}
