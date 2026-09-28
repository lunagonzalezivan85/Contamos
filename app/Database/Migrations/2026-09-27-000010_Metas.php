<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Plan de metas por gestor.
 *
 * meta_metricas — catálogo de métricas medibles (AUTO calculadas por el
 *                 sistema, MANUAL con avance ingresado a mano).
 * metas         — meta mensual (periodo YYYY-MM) por gestor y métrica.
 * Permiso metas.plan → admin/superadmin/gerente/supervisor.
 */
class Metas extends Migration
{
    public function up()
    {
        // ---------- Catálogo de métricas ----------
        $this->forge->addField([
            'id'         => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'  => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'codigo'     => ['type' => 'VARCHAR', 'constraint' => 40],
            'nombre'     => ['type' => 'VARCHAR', 'constraint' => 100],
            'unidad'     => ['type' => 'VARCHAR', 'constraint' => 12, 'default' => 'MONTO'], // MONTO|CANTIDAD|PORCENTAJE
            'modo'       => ['type' => 'VARCHAR', 'constraint' => 8,  'default' => 'MINIMO'], // MINIMO=al menos | MAXIMO=a lo sumo
            'calculo'    => ['type' => 'VARCHAR', 'constraint' => 8,  'default' => 'AUTO'],   // AUTO|MANUAL
            'formula'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'estado'     => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ACTIVO'],
            'created_at' => ['type' => 'DATETIME', 'null' => true],
            'updated_at' => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'codigo'], false, false, 'idx_mmetricas_tenant');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_mmetricas_tenants');
        $this->forge->createTable('meta_metricas', true);

        // ---------- Metas por gestor ----------
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'      => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'empleado_id'    => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'metrica_id'     => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true],
            'periodo'        => ['type' => 'CHAR', 'constraint' => 7], // YYYY-MM
            'meta'           => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0],
            'avance'         => ['type' => 'DECIMAL', 'constraint' => '14,2', 'default' => 0], // solo métricas MANUAL
            'notas'          => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'estado'         => ['type' => 'VARCHAR', 'constraint' => 10, 'default' => 'ACTIVO'],
            'registrado_por' => ['type' => 'BIGINT', 'constraint' => 20, 'unsigned' => true, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'empleado_id', 'metrica_id', 'periodo'], false, false, 'idx_metas_lookup');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_metas_tenants');
        $this->forge->addForeignKey('metrica_id', 'meta_metricas', 'id', 'CASCADE', 'CASCADE', 'fk_metas_metricas');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE', 'fk_metas_empleados');
        $this->forge->createTable('metas', true);

        // ---------------------------------------------------------------
        // Permiso metas.plan → admin/superadmin/gerente/supervisor
        // ---------------------------------------------------------------
        $perm = $this->db->table('permissions')->where('codigo', 'metas.plan')->get()->getRowArray();
        if (!$perm) {
            $this->db->table('permissions')->insert([
                'codigo' => 'metas.plan', 'nombre' => 'Plan de metas', 'modulo' => 'metas',
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
            ->whereIn('slug', ['admin', 'superadmin', 'gerente', 'supervisor'])
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
        $perm = $this->db->table('permissions')->where('codigo', 'metas.plan')->get()->getRowArray();
        if ($perm) {
            $this->db->table('role_permissions')->where('permission_id', $perm['id'])->delete();
            $this->db->table('permissions')->where('id', $perm['id'])->delete();
        }
        $this->forge->dropTable('metas', true);
        $this->forge->dropTable('meta_metricas', true);
    }
}
