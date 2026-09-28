<?php
/**
 * plan_helper — validaciones SaaS por plan para el tenant en sesión.
 *
 *  - plan_actual()    → fila del plan contratado (cache por request)
 *  - plan_limite()    → tope de un recurso (-1 = ilimitado)
 *  - plan_uso()       → uso actual: créditos activos, empleados, usuarios
 *  - plan_puede()     → ¿puede crear más de ese recurso?
 *  - plan_al_dia()    → ¿suscripción pagada? (día de pago mensual del tenant)
 *  - plan_proximo_pago() → fecha del próximo corte de cobro
 *
 * Uso típico:
 *   if (!plan_puede('credito'))  → bloquear con mensaje de upgrade
 *   if (!plan_al_dia()['ok'])    → banner de suscripción vencida
 */

if (!function_exists('plan_actual')) {

    /** Plan contratado por el tenant en sesión (o el dado). */
    function plan_actual(?int $tenantId = null): ?array
    {
        static $cache = [];
        $tid = $tenantId ?? (int) session('tenant_id');
        if ($tid <= 0) return null;
        if (array_key_exists($tid, $cache)) return $cache[$tid];

        $cache[$tid] = db_connect()->table('tenants t')
            ->select('p.*')
            ->join('planes p', 'p.id = t.plan_id', 'left')
            ->where('t.id', $tid)
            ->get()->getRowArray() ?: null;
        return $cache[$tid];
    }

    /** Límite de un recurso del plan: 'creditos'|'empleados'|'usuarios'. -1 = ilimitado. */
    function plan_limite(string $recurso, ?int $tenantId = null): int
    {
        $map = [
            'credito'   => 'max_creditos_activos',
            'creditos'  => 'max_creditos_activos',
            'empleado'  => 'max_empleados',
            'empleados' => 'max_empleados',
            'usuario'   => 'max_usuarios',
            'usuarios'  => 'max_usuarios',
        ];
        $campo = $map[$recurso] ?? null;
        if (!$campo) return -1;
        $plan = plan_actual($tenantId);
        return $plan ? (int) $plan[$campo] : -1; // sin plan = sin límite
    }

    /** Uso actual del tenant en los 3 recursos limitados. */
    function plan_uso(?int $tenantId = null): array
    {
        $tid = $tenantId ?? (int) session('tenant_id');
        if ($tid <= 0) return ['creditos' => 0, 'empleados' => 0, 'usuarios' => 0];
        $db = db_connect();
        return [
            'creditos'  => (int) $db->table('solicitudes')->where('tenant_id', $tid)->where('estado', 'ACTIVO')->countAllResults(),
            'empleados' => (int) $db->table('empleados')->where('tenant_id', $tid)->where('estado', 'ACTIVO')->countAllResults(),
            'usuarios'  => (int) $db->table('users')->where('tenant_id', $tid)->where('estado', 'ACTIVO')->countAllResults(),
        ];
    }

    /**
     * ¿El tenant puede crear otro recurso sin violar su plan?
     * $recurso: 'credito'|'empleado'|'usuario'. Ilimitado (-1) siempre true.
     * Superadmin / sin plan → true.
     */
    function plan_puede(string $recurso, ?int $tenantId = null): bool
    {
        if (session('es_admin_sistema')) return true;
        $max = plan_limite($recurso, $tenantId);
        if ($max < 0) return true;
        $uso = plan_uso($tenantId);
        $key = str_ends_with($recurso, 's') ? $recurso : $recurso . 's';
        return (int) ($uso[$key] ?? 0) < $max;
    }

    /**
     * Estado de cobro de la suscripción.
     * @return array{ok: bool, estado: string, motivo: string, proximo: string, ultimo_pago: ?string}
     *   - ok=false + estado SUSPENDIDA/VENCIDA → degradar/bloquear
     *   - ok=true  + estado PENDIENTE → dentro del mes, aún no pagó (gracia hasta dia_pago)
     */
    function plan_al_dia(?int $tenantId = null): array
    {
        $tid = $tenantId ?? (int) session('tenant_id');
        if ($tid <= 0) {
            return ['ok' => true, 'estado' => 'N/A', 'motivo' => '', 'proximo' => '', 'ultimo_pago' => null];
        }
        $db   = db_connect();
        $t    = $db->table('tenants')->select('suscripcion_estado, dia_pago')->where('id', $tid)->get()->getRowArray() ?? [];
        $plan = plan_actual($tid);

        // Plan gratis / sin plan contratado → siempre al día
        if (!$plan || (float) ($plan['precio_mensual'] ?? 0) <= 0) {
            return ['ok' => true, 'estado' => 'LIBRE', 'motivo' => '', 'proximo' => '', 'ultimo_pago' => null];
        }
        if (($t['suscripcion_estado'] ?? 'ACTIVA') === 'SUSPENDIDA') {
            return ['ok' => false, 'estado' => 'SUSPENDIDA', 'motivo' => 'Suscripción suspendida. Contacte soporte.',
                    'proximo' => '', 'ultimo_pago' => null];
        }

        $periodo   = date('Y-m');
        $pago      = $db->table('plan_pagos')->where('tenant_id', $tid)->where('periodo', $periodo)
                        ->get()->getRowArray();
        $ultimo    = $db->table('plan_pagos')->where('tenant_id', $tid)->where('estado', 'PAGADO')
                        ->orderBy('periodo', 'DESC')->get()->getRowArray();
        $proximo   = plan_proximo_pago($tid);
        $ultimoStr = $ultimo['fecha_pago'] ?? null;

        if ($pago && $pago['estado'] === 'PAGADO') {
            return ['ok' => true, 'estado' => 'PAGADO', 'motivo' => '', 'proximo' => $proximo, 'ultimo_pago' => $ultimoStr];
        }
        // Gracia: aún no llega el día de corte de este mes
        $corte = date('Y-m-') . str_pad((string) min(28, max(1, (int) ($t['dia_pago'] ?? 10))), 2, '0', STR_PAD_LEFT);
        if (date('Y-m-d') <= $corte) {
            return ['ok' => true, 'estado' => 'PENDIENTE', 'motivo' => 'Pago del período pendiente (corte: ' . $corte . ').',
                    'proximo' => $proximo, 'ultimo_pago' => $ultimoStr];
        }
        return ['ok' => false, 'estado' => 'VENCIDA',
                'motivo' => 'Suscripción vencida — no se registró el pago del período ' . $periodo . '.',
                'proximo' => $proximo, 'ultimo_pago' => $ultimoStr];
    }

    /** Próxima fecha de corte: el día de pago del tenant (este mes o el siguiente). */
    function plan_proximo_pago(?int $tenantId = null): string
    {
        $tid = $tenantId ?? (int) session('tenant_id');
        $t   = db_connect()->table('tenants')->select('dia_pago')->where('id', $tid)->get()->getRowArray();
        $dia = min(28, max(1, (int) ($t['dia_pago'] ?? 10)));

        $hoy   = new DateTime('today');
        $corte = (clone $hoy)->modify('first day of this month')->setDate(
            (int) $hoy->format('Y'), (int) $hoy->format('m'), $dia);
        if ($corte < $hoy) {
            $corte->modify('first day of next month')->setDate(
                (int) $corte->format('Y'), (int) $corte->format('m'), $dia);
        }
        return $corte->format('Y-m-d');
    }
}
