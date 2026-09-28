<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Arqueo de caja del gestor — cierre diario de efectivo en campo.
 *
 * arqueos — una fila por (tenant, gestor, fecha):
 *   saldo_inicial (arrastre del arqueo anterior) + cobros (pagos APLICADO
 *   del día con empleado_id del gestor) − desembolsos (entregados ese día)
 *   = esperado  →  se compara con `contado` (lo que el gestor declara)
 *   y queda `diferencia`. Estado CUADRADO | DIFERENCIA.
 *
 * También agrega `solicitudes.fecha_entrega` — cuándo el gestor entregó
 * el dinero en campo (necesaria para contar los egresos del arqueo).
 */
class Arqueos extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // solicitudes.fecha_entrega — entrega real del dinero en campo
        // ---------------------------------------------------------------
        if (!$this->db->fieldExists('fecha_entrega', 'solicitudes')) {
            $this->forge->addColumn('solicitudes', [
                'fecha_entrega' => ['type' => 'DATETIME', 'null' => true, 'after' => 'fecha_desembolso'],
            ]);
        }

        // ---------------------------------------------------------------
        // arqueos — cierre diario por gestor
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'            => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'empleado_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'fecha'         => ['type' => 'DATE'],
            'saldo_inicial' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'cobros'        => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'desembolsos'   => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'esperado'      => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'contado'       => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'diferencia'    => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'estado'        => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'CUADRADO'],
            'observacion'   => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'resuelto_por'  => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'created_at'    => ['type' => 'DATETIME', 'null' => true],
            'updated_at'    => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addUniqueKey(['tenant_id', 'empleado_id', 'fecha'], 'uq_arqueos_dia');
        $this->forge->addKey(['tenant_id', 'fecha'], false, false, 'idx_arqueos_fecha');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_arqueos_tenants');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE', 'fk_arqueos_empleados');
        $this->forge->createTable('arqueos', true);

        // ---------------------------------------------------------------
        // Permiso caja.arqueo → admin/superadmin/gerente/supervisor
        // (el permiso ya viene en SecuritySeeder; aquí solo se asigna a roles)
        // ---------------------------------------------------------------
        $perm = $this->db->table('permissions')->where('codigo', 'caja.arqueo')->get()->getRowArray();
        if (!$perm) {
            $this->db->table('permissions')->insert([
                'codigo' => 'caja.arqueo', 'nombre' => 'Arqueo de caja', 'modulo' => 'caja',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $permId = (int) $this->db->insertID();
        } else {
            $permId = (int) $perm['id'];
        }

        $tenantDefault = (int) ($this->db->table('tenants')->selectMin('id')->get()->getRow('id') ?? 0);
        $roles = $this->db->table('roles')
            ->whereIn('slug', ['admin', 'superadmin', 'gerente', 'supervisor'])
            ->get()->getResultArray();

        foreach ($roles as $rol) {
            $yaTiene = $this->db->table('role_permissions')
                ->where('role_id', $rol['id'])->where('permission_id', $permId)
                ->countAllResults();
            if ($yaTiene) continue;
            $this->db->table('role_permissions')->insert([
                'tenant_id'     => (int) ($rol['tenant_id'] ?? 0) ?: $tenantDefault,
                'role_id'       => (int) $rol['id'],
                'permission_id' => $permId,
                'created_at'    => date('Y-m-d H:i:s'),
            ]);
        }
    }

    public function down()
    {
        $perm = $this->db->table('permissions')->where('codigo', 'caja.arqueo')->get()->getRowArray();
        if ($perm) {
            $this->db->table('role_permissions')->where('permission_id', $perm['id'])->delete();
        }
        $this->forge->dropTable('arqueos', true);
        if ($this->db->fieldExists('fecha_entrega', 'solicitudes')) {
            $this->forge->dropColumn('solicitudes', 'fecha_entrega');
        }
    }
}
