<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Categorías de reportes por tenant + asignación reporte→categoría.
 * El catálogo de reportes es estático (Config/Reportes.php); estas
 * tablas solo guardan la personalización del tenant en el índice.
 */
class ReporteCategorias extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 80],
            'orden'      => ['type' => 'SMALLINT', 'unsigned' => true, 'default' => 0],
            'activo'     => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 1],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('tenant_id');
        $this->forge->createTable('reporte_categorias', true);

        $this->forge->addField([
            'id'           => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'reporte_key'  => ['type' => 'VARCHAR', 'constraint' => 60],
            'categoria_id' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'   => ['type' => 'DATETIME', 'null' => true],
            'updated_at'   => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'reporte_key']);
        $this->forge->createTable('reporte_asignaciones', true);
    }

    public function down()
    {
        $this->forge->dropTable('reporte_asignaciones', true);
        $this->forge->dropTable('reporte_categorias', true);
    }
}
