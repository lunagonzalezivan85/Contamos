<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * Cobros — cuotas del plan de pago + pagos con flujo de revisión.
 *
 * cuotas  — se generan al entregar el desembolso (PortalService::entregarDesembolso)
 *           con el plan francés aprobado. `pagado` acumula lo aplicado.
 * pagos   — TODO pago nace en REVISION (campo u oficina). Solo al aprobarse
 *           (pagos.aprobar) se aplica a cuotas en orden FIFO. Rechazado no aplica.
 */
class CuotasYPagos extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // cuotas — una fila por cuota del plan de pago
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'        => ['type' => 'BIGINT', 'unsigned' => true],
            'solicitud_id'     => ['type' => 'BIGINT', 'unsigned' => true],
            'n'                => ['type' => 'SMALLINT', 'unsigned' => true],
            'fecha_vence'      => ['type' => 'DATE'],
            'cuota'            => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'interes'          => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'capital'          => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'saldo_proyectado' => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'pagado'           => ['type' => 'DECIMAL', 'constraint' => '12,2', 'default' => 0],
            'estado'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDIENTE'],
            'fecha_pago'       => ['type' => 'DATE', 'null' => true],
            'created_at'       => ['type' => 'DATETIME', 'null' => true],
            'updated_at'       => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['solicitud_id', 'n'], false, false, 'idx_cuotas_sol');
        $this->forge->addKey(['tenant_id', 'fecha_vence'], false, false, 'idx_cuotas_vence');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_cuotas_tenants');
        $this->forge->addForeignKey('solicitud_id', 'solicitudes', 'id', 'CASCADE', 'CASCADE', 'fk_cuotas_solicitudes');
        $this->forge->createTable('cuotas', true);

        // ---------------------------------------------------------------
        // pagos — estado REVISION/APROBADO/RECHAZADO/REVERTIDO
        // empleado_id = gestor que cobró en campo (null si fue en oficina)
        // registrado_por = usuario que digitó (null si fue el gestor en portal)
        // ---------------------------------------------------------------
        $this->forge->addField([
            'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
            'tenant_id'      => ['type' => 'BIGINT', 'unsigned' => true],
            'solicitud_id'   => ['type' => 'BIGINT', 'unsigned' => true],
            'cuota_id'       => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true], // null = abono libre
            'empleado_id'    => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'registrado_por' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'aprobado_por'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
            'monto'          => ['type' => 'DECIMAL', 'constraint' => '12,2'],
            'metodo'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'EFECTIVO'],
            'fecha_hora'     => ['type' => 'DATETIME'],
            'observacion'    => ['type' => 'VARCHAR', 'constraint' => 255, 'null' => true],
            'estado'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'REVISION'],
            'resuelto_at'    => ['type' => 'DATETIME', 'null' => true],
            'created_at'     => ['type' => 'DATETIME', 'null' => true],
            'updated_at'     => ['type' => 'DATETIME', 'null' => true],
        ]);
        $this->forge->addKey('id', true);
        $this->forge->addKey(['tenant_id', 'estado'], false, false, 'idx_pagos_estado');
        $this->forge->addKey('solicitud_id', false, false, 'idx_pagos_sol');
        $this->forge->addKey(['empleado_id', 'fecha_hora'], false, false, 'idx_pagos_gestor');
        $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_pagos_tenants');
        $this->forge->addForeignKey('solicitud_id', 'solicitudes', 'id', 'CASCADE', 'CASCADE', 'fk_pagos_solicitudes');
        $this->forge->addForeignKey('cuota_id', 'cuotas', 'id', 'CASCADE', 'SET NULL', 'fk_pagos_cuotas');
        $this->forge->addForeignKey('empleado_id', 'empleados', 'id', 'CASCADE', 'SET NULL', 'fk_pagos_empleados');
        $this->forge->createTable('pagos', true);

        // ---------------------------------------------------------------
        // Permiso pagos.aprobar — quien confirma que el dinero llegó.
        // admin/superadmin (todos), gerente y supervisor.
        // ---------------------------------------------------------------
        $perm = $this->db->table('permissions')->where('codigo', 'pagos.aprobar')->get()->getRowArray();
        if (!$perm) {
            $this->db->table('permissions')->insert([
                'codigo' => 'pagos.aprobar', 'nombre' => 'Aprobar pago', 'modulo' => 'pagos',
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
        $perm = $this->db->table('permissions')->where('codigo', 'pagos.aprobar')->get()->getRowArray();
        if ($perm) {
            $this->db->table('role_permissions')->where('permission_id', $perm['id'])->delete();
            $this->db->table('permissions')->where('id', $perm['id'])->delete();
        }
        $this->forge->dropTable('pagos', true);
        $this->forge->dropTable('cuotas', true);
    }
}
