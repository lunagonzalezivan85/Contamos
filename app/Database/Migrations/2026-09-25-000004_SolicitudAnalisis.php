<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * solicitud_analisis — análisis financiero calculado para una solicitud
 * (ingresos, activos, pasivos, patrimonio, cuota mensual, ratio, nivel).
 * Se calcula manualmente desde el detalle cuando el expediente está completo.
 */
class SolicitudAnalisis extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'solicitud_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'cliente_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'ingresos'     => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'cuota'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'cuota_mes'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'activos'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'pasivos'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'patrimonio'   => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'ratio'        => ['type' => 'DECIMAL', 'constraint' => '6,3', 'null' => true], // cuota_mes / ingresos
            'nivel'        => ['type' => 'VARCHAR', 'constraint' => 20, 'null' => true],   // BUENO|AJUSTADO|RIESGO|SIN_DATOS
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'solicitud_id']);
        $this->forge->addKey('cliente_id');
        $this->forge->createTable('solicitud_analisis', true);
    }

    public function down()
    {
        $this->forge->dropTable('solicitud_analisis', true);
    }
}
