<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Control de ingresos — otros ingresos del negocio que no son cobros de crédito.
 *
 * ingresos — un ingreso por fila, sin detalle:
 *   tipo VENTA | DONACION | OTRO
 *   fecha + concepto + monto + método + referencia + descripción
 *   iva_pct — solo aplica a VENTA: % de IVA que se desglosa en el recibo
 *   estado ACTIVO | ANULADO (anulado no suma en totales)
 *
 * El tipo VENTA genera un recibo imprimible con el desglose
 * subtotal / IVA% / total (GET /finanzas/ingresos/{id}/recibo).
 *
 * Permiso: caja.ingresos → admin/superadmin/gerente/supervisor/cajero.
 */
class Ingresos extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'tipo'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'VENTA'],
            'empleado_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'registrado_por' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'fecha'          => ['type' => 'DATE'],
            'concepto'       => ['type' => 'VARCHAR', 'constraint' => 150],
            'descripcion'    => ['type' => 'VARCHAR', 'constraint' => 500, 'null' => true],
            'referencia'     => ['type' => 'VARCHAR', 'constraint' => 100, 'null' => true],
            'monto'          => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'iva_pct'        => ['type' => 'DECIMAL', 'constraint' => '5,2', 'default' => 0],
            'metodo'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'EFECTIVO'],
            'estado'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'fecha'], false, false, 'idx_ingresos_fecha');
        $this->forge->addKey(['tenant_id', 'tipo'], false, false, 'idx_ingresos_tipo');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_ingresos_tenants');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'SET NULL', 'CASCADE', 'fk_ingresos_empleados');
        $this->forge->createTable('ingresos', true);

        // ---------------------------------------------------------------
        // Permiso caja.ingresos → admin/superadmin/gerente/supervisor/cajero
        // ---------------------------------------------------------------
        $perm = $this->db->table('permissions')->where('codigo', 'caja.ingresos')->get()->getRowArray();
        if (!$perm) {
            $this->db->table('permissions')->insert([
                'codigo' => 'caja.ingresos', 'nombre' => 'Registrar ingresos', 'modulo' => 'caja',
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
        $perm = $this->db->table('permissions')->where('codigo', 'caja.ingresos')->get()->getRowArray();
        if ($perm) {
            $this->db->table('role_permissions')->where('permission_id', $perm['id'])->delete();
            $this->db->table('permissions')->where('id', $perm['id'])->delete();
        }
        $this->forge->dropTable('ingresos', true);
    }
}
