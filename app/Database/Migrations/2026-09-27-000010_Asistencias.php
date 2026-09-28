<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Control de asistencia del personal — kiosco de marcación por tenant.
 *
 * asistencias — una jornada por fila:
 *   fecha (día de la jornada) + entrada + salida (NULL mientras está abierta).
 *   estado ABIERTA | CERRADA | ANULADA — abierta = entró y aún no sale;
 *   al marcar de nuevo, la abierta se cierra como salida (cubre jornadas
 *   que cruzan medianoche). ANULADA se conserva pero no cuenta.
 *   editada = 1 cuando el admin corrigió entrada/salida (editado_por).
 *
 * Permiso: empleados.asistencia → admin/superadmin/gerente/supervisor
 * (la vista de oficina /asistencia; el kiosco /{slug}/asistencia es
 * público y valida carnet+PIN en cada marcación).
 */
class Asistencias extends Migration
{
    public function up()
    {
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'empleado_id'    => ['type' => 'BIGINT', 'unsigned' => true],
            'fecha'          => ['type' => 'DATE'],
            'entrada'        => ['type' => 'DATETIME'],
            'salida'         => ['type' => 'DATETIME', 'null' => true],
            'estado'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ABIERTA'],
            'editada'        => ['type' => 'TINYINT', 'constraint' => 1, 'default' => 0],
            'editado_por'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'registrado_por' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'observacion'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'ip'             => ['type' => 'VARCHAR', 'constraint' => 45, 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'fecha'], false, false, 'idx_asist_fecha');
        $this->forge->addKey(['empleado_id', 'estado'], false, false, 'idx_asist_abierta');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_asist_tenants');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'CASCADE', 'fk_asist_empleados');
        $this->forge->createTable('asistencias', true);

        // ---------------------------------------------------------------
        // Permiso empleados.asistencia → roles administrativos
        // ---------------------------------------------------------------
        $perm = $this->db->table('permissions')->where('codigo', 'empleados.asistencia')->get()->getRowArray();
        if (!$perm) {
            $this->db->table('permissions')->insert([
                'codigo' => 'empleados.asistencia', 'nombre' => 'Control de asistencia', 'modulo' => 'empleados',
                'created_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s'),
            ]);
            $permId = (int) $this->db->insertID();
        } else {
            $permId = (int) $perm['id'];
        }

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
        $perm = $this->db->table('permissions')->where('codigo', 'empleados.asistencia')->get()->getRowArray();
        if ($perm) {
            $this->db->table('role_permissions')->where('permission_id', $perm['id'])->delete();
            $this->db->table('permissions')->where('id', $perm['id'])->delete();
        }
        $this->forge->dropTable('asistencias', true);
    }
}
