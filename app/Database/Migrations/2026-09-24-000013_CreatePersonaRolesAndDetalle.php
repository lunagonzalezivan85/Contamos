<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Roles de persona + datos complementarios.
 * personas = identidad compartida; empleados/clientes = roles por persona.
 * Tablas hijas (direcciones, contactos, referencias, negocios, activos,
 * pasivos, ingresos, documentos) cuelgan de persona_id.
 */
class CreatePersonaRolesAndDetalle extends Migration
{
    public function up()
    {
        // Rol EMPLEADO
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'persona_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'cargo'         => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'fecha_ingreso' => ['type' => 'DATE', 'null' => true],
            'estado'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('persona_id', false, false, 'idx_empleados_persona');
        $this->forge->addForeignKey('persona_id', 'personas', 'id', 'CASCADE', 'CASCADE', 'fk_empleados_persona');
        $this->forge->createTable('empleados', true);

        // Rol CLIENTE (stub — se completa con el módulo de crédito)
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'unsigned' => true],
            'persona_id' => ['type' => 'BIGINT', 'unsigned' => true],
            'estado'     => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey('persona_id', false, false, 'idx_clientes_persona');
        $this->forge->addForeignKey('persona_id', 'personas', 'id', 'CASCADE', 'CASCADE', 'fk_clientes_persona');
        $this->forge->createTable('clientes', true);

        // Tablas hijas genéricas (persona_id)
        $hijas = [
            'persona_direcciones' => [
                'tipo'    => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true], // casa/trabajo
                'ciudad'  => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
                'detalle' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ],
            'persona_contactos' => [
                'tipo'  => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],   // telefono/email/whatsapp
                'valor' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
            ],
            'persona_referencias' => [
                'nombre'     => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'parentesco' => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'telefono'   => ['type' => 'VARCHAR', 'constraint' => 50, 'null' => true],
                'direccion'  => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ],
            'persona_negocios' => [
                'nombre'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'actividad' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'direccion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'tiempo'    => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
            ],
            'persona_activos' => [
                'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'valor'       => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            ],
            'persona_pasivos' => [
                'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'acreedor'    => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'monto'       => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            ],
            'persona_ingresos' => [
                'fuente' => ['type' => 'VARCHAR', 'constraint' => 150, 'null' => true],
                'monto'  => ['type' => 'DECIMAL', 'constraint' => '14,2', 'null' => true],
            ],
            'persona_documentos' => [
                'tipo'        => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true], // cedula, contrato…
                'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
                'archivo'     => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            ],
        ];

        foreach ($hijas as $tabla => $extra) {
            $campos = array_merge([
                'id'         => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'persona_id' => ['type' => 'BIGINT', 'unsigned' => true],
                'created_at' => ['type' => 'DATETIME', 'null' => true],
            ], $extra);
            $this->forge->addField($campos);
            $this->forge->addKey('id', true);
            $this->forge->addKey('persona_id', false, false, 'idx_' . $tabla);
            $this->forge->addForeignKey('persona_id', 'personas', 'id', 'CASCADE', 'CASCADE', 'fk_' . $tabla);
            $this->forge->createTable($tabla, true);
        }
    }

    public function down()
    {
        foreach (['persona_direcciones','persona_contactos','persona_referencias','persona_negocios',
                  'persona_activos','persona_pasivos','persona_ingresos','persona_documentos',
                  'clientes','empleados'] as $t) {
            $this->forge->dropTable($t, true);
        }
    }
}
