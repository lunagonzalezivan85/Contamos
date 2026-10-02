<?php

namespace App\Commands;

use CodeIgniter\CLI\BaseCommand;
use CodeIgniter\CLI\CLI;

/**
 * Cobranza SaaS — correr diario (cron/Task Scheduler):
 *   php spark cobros:generar
 *
 * 1) Genera el cargo PENDIENTE del período actual (YYYY-MM) para cada tenant
 *    con plan pago y suscripción ACTIVA — idempotente por la unique key
 *    plan_pagos(tenant_id, periodo).
 * 2) Suspende (suscripcion_estado = SUSPENDIDA) a todo tenant con un cargo
 *    PENDIENTE cuyo corte ya venció: dia_pago + gracia_dias del tenant.
 *    El bloqueo real lo aplican SuscripcionFilter (panel partner) y
 *    ConnectController (portal/app del gestor) vía plan_al_dia().
 */
class CobrosGenerar extends BaseCommand
{
    protected $group       = 'Cobranza';
    protected $name        = 'cobros:generar';
    protected $description = 'Genera los cargos del período y suspende tenants morosos.';

    public function run(array $params)
    {
        helper('plan');
        $db      = db_connect();
        $periodo = date('Y-m');
        $hoy     = date('Y-m-d');

        // Tenants que pagan plan: activos, suscripción vigente, plan con precio
        $tenants = $db->table('tenants t')
            ->select('t.id, t.nombre, t.plan_id, t.dia_pago, t.gracia_dias, pl.precio_mensual, pl.nombre AS plan')
            ->join('planes pl', 'pl.id = t.plan_id')
            ->where('t.estado', 'ACTIVO')
            ->where('t.suscripcion_estado', 'ACTIVA')
            ->where('pl.precio_mensual >', 0)
            ->get()->getResultArray();

        // 1) Cargo del período — solo si no existe (unique key lo respalda)
        $generados = 0;
        foreach ($tenants as $t) {
            $yaExiste = $db->table('plan_pagos')
                ->where('tenant_id', $t['id'])->where('periodo', $periodo)->countAllResults() > 0;
            if ($yaExiste) continue;

            $cobro = plan_cobro_mes((int) $t['id']);
            $db->table('plan_pagos')->insert([
                'tenant_id'   => $t['id'],
                'plan_id'     => $t['plan_id'],
                'periodo'     => $periodo,
                'monto'       => $cobro['total'],
                'moneda'      => $cobro['moneda'] ?? 'USD',
                'estado'      => 'PENDIENTE',
                'observacion' => 'Cargo automático del período (cobros:generar).',
                'created_at'  => date('Y-m-d H:i:s'),
            ]);
            $generados++;
            CLI::write("  + {$t['nombre']}: cargo {$periodo} USD " . number_format($cobro['total'], 2), 'green');
        }

        // 2) Morosos: cargo PENDIENTE con corte (dia_pago + gracia) ya vencido
        $suspendidos = 0;
        foreach ($tenants as $t) {
            $dia    = min(28, max(1, (int) ($t['dia_pago'] ?? 10)));
            $gracia = max(0, (int) ($t['gracia_dias'] ?? 4));

            $pendientes = $db->table('plan_pagos')
                ->where('tenant_id', $t['id'])->where('estado', 'PENDIENTE')->get()->getResultArray();

            foreach ($pendientes as $p) {
                $corte = date('Y-m-d', strtotime($p['periodo'] . '-' . str_pad((string) $dia, 2, '0', STR_PAD_LEFT) . " +{$gracia} days"));
                if ($hoy > $corte) {
                    $db->table('tenants')->where('id', $t['id'])
                        ->update(['suscripcion_estado' => 'SUSPENDIDA', 'updated_at' => date('Y-m-d H:i:s')]);
                    $suspendidos++;
                    CLI::write("  ! {$t['nombre']}: SUSPENDIDA (período {$p['periodo']} venció {$corte})", 'yellow');
                    break; // una suspensión basta
                }
            }
        }

        CLI::write("Listo — {$generados} cargo(s) del período {$periodo}, {$suspendidos} suspensión(es) por mora.", 'cyan');
    }
}
