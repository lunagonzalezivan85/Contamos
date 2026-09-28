<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Configuración del tenant:
 * - tenants: +lema, voucher_footer, horario
 * - plantillas: plantillas de documentos por tenant (vouchers, contratos, cartas)
 */
class AddConfigFieldsAndPlantillas extends Migration
{
    public function up()
    {
        $this->forge->addColumn('tenants', [
            'lema'           => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'direccion'],
            'voucher_footer' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true, 'after' => 'lema'],
            'horario'        => ['type' => 'VARCHAR', 'constraint' => 200, 'null' => true, 'after' => 'voucher_footer'],
        ]);

        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 150],
            'slug'        => ['type' => 'VARCHAR', 'constraint' => 100],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'contenido'   => ['type' => 'TEXT'],
            'estado'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'slug'], 'uq_plantillas_tenant_slug');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_plantillas_tenants');
        $this->forge->createTable('plantillas', true);
    }

    public function down()
    {
        $this->forge->dropTable('plantillas', true);
        $this->forge->dropColumn('tenants', ['lema', 'voucher_footer', 'horario']);
    }
}
