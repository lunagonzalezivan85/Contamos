<?php

namespace App\Database\Migrations;

use CodeIgniter\Database\Migration;

/**
 * SaaS por planes — catálogo global de planes, suscripción del tenant
 * (plan + día de pago mensual) y dos tablas operativas:
 *
 *  - `plan_solicitudes`: el tenant pide cambio de plan; el equipo lo
 *    aprueba/rechaza desde el portal admin.
 *  - `plan_pagos`: cobro mensual de la suscripción (1 fila por período
 *    'YYYY-MM'); `plan_helper::plan_al_dia()` valida si el tenant pagó.
 *
 * Precios: Básico USD 35 (50 créditos / 5 empleados / 3 usuarios),
 * Profesional USD 79, Empresarial USD 149 (límites -1 = ilimitado).
 */
class PlanesSuscripciones extends Migration
{
    public function up()
    {
        // ---------------------------------------------------------------
        // planes — catálogo global (compartido por todos los tenants)
        // ---------------------------------------------------------------
        if (!$this->db->tableExists('planes')) {
            $this->forge->addField([
                'id'                   => ['type' => 'INT', 'unsigned' => true, 'auto_increment' => true],
                'nombre'               => ['type' => 'VARCHAR', 'constraint' => 60],
                'slug'                 => ['type' => 'VARCHAR', 'constraint' => 40],
                'precio_mensual'       => ['type' => 'DECIMAL', 'constraint' => '10,2', 'default' => 0],
                'moneda'               => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],
                'max_creditos_activos' => ['type' => 'INT', 'default' => -1],  // -1 = ilimitado
                'max_empleados'        => ['type' => 'INT', 'default' => -1],
                'max_usuarios'         => ['type' => 'INT', 'default' => -1],
                'features'             => ['type' => 'TEXT', 'null' => true],  // JSON opcional
                'orden'                => ['type' => 'INT', 'default' => 0],
                'estado'               => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVO'],
                'created_at'           => ['type' => 'DATETIME', 'null' => true],
                'updated_at'           => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey('slug');
            $this->forge->createTable('planes', true);
        }

        // ---------------------------------------------------------------
        // tenants — suscripción: plan contratado + día de pago mensual
        // ---------------------------------------------------------------
        if (!$this->db->fieldExists('plan_id', 'tenants')) {
            $this->forge->addColumn('tenants', [
                'plan_id' => ['type' => 'INT', 'unsigned' => true, 'null' => true, 'after' => 'estado'],
            ]);
            $this->forge->addForeignKey('plan_id', 'planes', 'id', 'CASCADE', 'SET NULL', 'fk_tenants_plan');
            $this->forge->processIndexes('tenants');
        }
        foreach ([
            'dia_pago'           => ['type' => 'TINYINT', 'unsigned' => true, 'default' => 10, 'after' => 'plan_id'],
            'suscripcion_estado' => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'ACTIVA', 'after' => 'dia_pago'],
        ] as $col => $def) {
            if (!$this->db->fieldExists($col, 'tenants')) {
                $this->forge->addColumn('tenants', [$col => $def]);
            }
        }

        // ---------------------------------------------------------------
        // plan_solicitudes — solicitud de cambio de plan (tenant → admin)
        // ---------------------------------------------------------------
        if (!$this->db->tableExists('plan_solicitudes')) {
            $this->forge->addField([
                'id'               => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'        => ['type' => 'BIGINT', 'unsigned' => true],
                'plan_id'          => ['type' => 'INT', 'unsigned' => true],
                'estado'           => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDIENTE'],
                'nota'             => ['type' => 'TEXT', 'null' => true],
                'solicitado_por'   => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'resuelto_por'     => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'fecha_resolucion' => ['type' => 'DATETIME', 'null' => true],
                'created_at'       => ['type' => 'DATETIME', 'null' => true],
                'updated_at'       => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addKey(['tenant_id', 'estado'], false, 'idx_plan_sol_tenant');
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_plan_sol_tenant');
            $this->forge->addForeignKey('plan_id', 'planes', 'id', 'CASCADE', 'RESTRICT', 'fk_plan_sol_plan');
            $this->forge->createTable('plan_solicitudes', true);
        }

        // ---------------------------------------------------------------
        // plan_pagos — cobro mensual de suscripción (1 fila por período)
        // ---------------------------------------------------------------
        if (!$this->db->tableExists('plan_pagos')) {
            $this->forge->addField([
                'id'             => ['type' => 'BIGINT', 'unsigned' => true, 'auto_increment' => true],
                'tenant_id'      => ['type' => 'BIGINT', 'unsigned' => true],
                'plan_id'        => ['type' => 'INT', 'unsigned' => true, 'null' => true],
                'periodo'        => ['type' => 'CHAR', 'constraint' => 7],      // 'YYYY-MM'
                'monto'          => ['type' => 'DECIMAL', 'constraint' => '10,2'],
                'moneda'         => ['type' => 'VARCHAR', 'constraint' => 3, 'default' => 'USD'],
                'metodo'         => ['type' => 'VARCHAR', 'constraint' => 30, 'null' => true],
                'referencia'     => ['type' => 'VARCHAR', 'constraint' => 60, 'null' => true],
                'fecha_pago'     => ['type' => 'DATE', 'null' => true],
                'estado'         => ['type' => 'VARCHAR', 'constraint' => 20, 'default' => 'PENDIENTE'],
                'registrado_por' => ['type' => 'BIGINT', 'unsigned' => true, 'null' => true],
                'observacion'    => ['type' => 'TEXT', 'null' => true],
                'created_at'     => ['type' => 'DATETIME', 'null' => true],
                'updated_at'     => ['type' => 'DATETIME', 'null' => true],
            ]);
            $this->forge->addKey('id', true);
            $this->forge->addUniqueKey(['tenant_id', 'periodo'], 'uk_plan_pago_periodo');
            $this->forge->addForeignKey('tenant_id', 'tenants', 'id', 'CASCADE', 'CASCADE', 'fk_plan_pago_tenant');
            $this->forge->addForeignKey('plan_id', 'planes', 'id', 'CASCADE', 'SET NULL', 'fk_plan_pago_plan');
            $this->forge->createTable('plan_pagos', true);
        }

        // ---------------------------------------------------------------
        // Semilla de planes (idempotente)
        // ---------------------------------------------------------------
        $now = date('Y-m-d H:i:s');
        $semilla = [
            ['nombre' => 'Básico',      'slug' => 'basico',      'precio_mensual' => 35.00,
             'max_creditos_activos' => 50, 'max_empleados' => 5,  'max_usuarios' => 3,  'orden' => 1],
            ['nombre' => 'Profesional', 'slug' => 'profesional', 'precio_mensual' => 79.00,
             'max_creditos_activos' => 200, 'max_empleados' => 15, 'max_usuarios' => 8,  'orden' => 2],
            ['nombre' => 'Empresarial', 'slug' => 'empresarial', 'precio_mensual' => 149.00,
             'max_creditos_activos' => -1, 'max_empleados' => -1, 'max_usuarios' => -1, 'orden' => 3],
        ];
        foreach ($semilla as $p) {
            if (!$this->db->table('planes')->where('slug', $p['slug'])->countAllResults()) {
                $this->db->table('planes')->insert($p + [
                    'moneda' => 'USD', 'estado' => 'ACTIVO',
                    'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        // Tenants existentes → Básico (día de pago 10); CFSI (sistema) → Empresarial
        $basico = $this->db->table('planes')->where('slug', 'basico')->get()->getRowArray();
        $emp    = $this->db->table('planes')->where('slug', 'empresarial')->get()->getRowArray();
        if ($basico) {
            $this->db->table('tenants')->where('plan_id IS NULL')->where('id !=', 1)
                ->update(['plan_id' => (int) $basico['id'], 'dia_pago' => 10]);
        }
        if ($emp) {
            $this->db->table('tenants')->where('id', 1)->update(['plan_id' => (int) $emp['id']]);
        }
    }

    public function down()
    {
        foreach (['plan_pagos', 'plan_solicitudes', 'planes'] as $t) {
            $this->forge->dropTable($t, true);
        }
        if ($this->db->fieldExists('plan_id', 'tenants')) {
            $this->forge->dropForeignKey('tenants', 'fk_tenants_plan');
            $this->forge->dropColumn('tenants', ['plan_id', 'dia_pago', 'suscripcion_estado']);
        }
    }
}
