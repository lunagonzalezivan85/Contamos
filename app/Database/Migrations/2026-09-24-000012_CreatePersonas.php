<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * personas — personas físicas del tenant: CLIENTE y EMPLEADO.
 * users.persona_id la referencia (empleado con cuenta de usuario).
 */
class CreatePersonas extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'tipo'          => ['type' => 'VARCHAR', 'constraint' => 20],           // CLIENTE | EMPLEADO
            'nombres'       => ['type' => 'VARCHAR', 'constraint' => 100],
            'apellidos'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'cedula'        => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
            'telefono'      => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
            'email'         => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            'direccion'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'fecha_nac'     => ['type' => 'DATE', 'null' => true],
            'cargo'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],  // solo EMPLEADO
            'fecha_ingreso' => ['type' => 'DATE', 'null' => true],                          // solo EMPLEADO
            'estado'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
            'deleted_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'tipo'], false, false, 'idx_personas_tipo');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_personas_tenants');
        $this->forge->createTable('personas', true);
    }

    public function down()
    {
        $this->forge->dropTable('personas', true);
    }
}
