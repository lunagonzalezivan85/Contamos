<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Control de gastos del negocio — egresos operativos por categoría.
 *
 * gasto_categorias — catálogo por tenant (Oficina, Combustible, ...).
 *   El tenant puede agregar las suyas; las por defecto se siembran
 *   on-demand desde GastoService::categorias().
 *
 * gastos — un gasto por fila, sin detalle:
 *   fecha + categoría + concepto + monto + método + referencia +
 *   descripción + empleado (opcional, quién ejecutó el gasto).
 *   estado ACTIVO | ANULADO (el anulado no suma en totales).
 *
 * También asegura el permiso caja.gastos (ya está en SecuritySeeder,
 * esto lo aplica a instalaciones previas) y lo asigna a los roles
 * administrativos + cajero.
 */
class Gastos extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // gasto_categorias — tipos de gasto por tenant
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'          => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'nombre'      => ['type' => 'VARCHAR', 'constraint' => 80],
            'descripcion' => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'estado'      => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at'  => ['type' => 'DATETIME', 'null' => true],
            'updated_at'  => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'nombre'], 'uq_gasto_cat_nombre');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_gasto_cat_tenants');
        $this->forge->createTable('gasto_categorias', true);

        // ---------------------------------------------------------------
        // gastos — egreso simple (sin líneas de detalle)
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'categoria_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'empleado_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'registrado_por' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'fecha'          => ['type' => 'DATE'],
            'concepto'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'descripcion'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'referencia'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'monto'          => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'metodo'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'EFECTIVO'],
            'estado'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'fecha'], false, false, 'idx_gastos_fecha');
        $this->forge->addKey(['tenant_id', 'categoria_id'], false, false, 'idx_gastos_cat');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_gastos_tenants');
        $this->forge->addForeignKey('categoria_id', 'gasto_categorias', 'id', 'RESTRICT', 'CASCADE', 'fk_gastos_cat');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'SET NULL', 'CASCADE', 'fk_gastos_empleados');
        $this->forge->createTable('gastos', true);

        // ---------------------------------------------------------------
        // Permiso caja.gastos → admin/superadmin/gerente/supervisor/cajero
        // ---------------------------------------------------------------
        $perm = $this->db->table('permissions')->where('codigo', 'caja.gastos')->get()->getRowArray();
        if (!$perm) {
            $this->db->table('permissions')->insert([
                'codigo' => 'caja.gastos', 'nombre' => 'Registrar gastos', 'modulo' => 'caja',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $permId = (int) $this->db->insertID();
        } else {
            $permId = (int) $perm['id'];
        }

        // Otorgar en TODOS los tenants existentes (los nuevos los heredan
        // vía TenantDemoSeeder, que replica los permisos del tenant 1).
        $tenants = $this->db->table('tenants')->select('id')->get()->getResultArray();
        $roles   = $this->db->table('roles')
            ->whereIn('slug', ['admin', 'superadmin', 'gerente', 'supervisor', 'cajero'])
            ->get()->getResultArray();

        foreach ($tenants as $t) {
            foreach ($roles as $rol) {
                $yaTiene = $this->db->table('role_permissions')
                    ->where('tenant_id', $t['id'])
                    ->where('role_id', $rol['id'])
                    ->where('permission_id', $permId)
                    ->countAllResults();
                if ($yaTiene) continue;
                $this->db->table('role_permissions')->insert([
                    'tenant_id'     => (int) $t['id'],
                    'role_id'       => (int) $rol['id'],
                    'permission_id' => $permId,
                    'created_at'    => date('Y-m-d H:i:s'),
                ]);
            }
        }
    }

    public function down()
    {
        $perm = $this->db->table('permissions')->where('codigo', 'caja.gastos')->get()->getRowArray();
        if ($perm) {
            $this->db->table('role_permissions')->where('permission_id', $perm['id'])->delete();
        }
        $this->forge->dropTable('gastos', true);
        $this->forge->dropTable('gasto_categorias', true);
    }
}
