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

/** Cargo mensual por cada usuario activo por encima de `max_usuarios` del plan (USD). */
defined('PLAN_USD_EXTRA_USUARIO')  || define('PLAN_USD_EXTRA_USUARIO',  3.00);
/** Cargo mensual por cada empleado/cobrador ACTIVO por encima de `max_empleados`. */
defined('PLAN_USD_EXTRA_EMPLEADO') || define('PLAN_USD_EXTRA_EMPLEADO', 1.00);
/** Cargo mensual por cada crédito ACTIVO/DESEMBOLSO por encima de `max_creditos_activos`. */
defined('PLAN_USD_EXTRA_CREDITO')  || define('PLAN_USD_EXTRA_CREDITO',  0.15);
/** Cargo mensual por cada cliente registrado por encima de la base (no hay columna en planes: base fija 20). */
defined('PLAN_USD_EXTRA_CLIENTE')  || define('PLAN_USD_EXTRA_CLIENTE',  0.20);
defined('PLAN_BASE_CLIENTES')      || define('PLAN_BASE_CLIENTES',      20);

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
            // "Activo o en cobro" = ACTIVO + DESEMBOLSO (términos del plan)
            'creditos'  => (int) $db->table('solicitudes')->where('tenant_id', $tid)
                                ->whereIn('estado', ['ACTIVO', 'DESEMBOLSO'])->countAllResults(),
            'empleados' => (int) $db->table('empleados')->where('tenant_id', $tid)->where('estado', 'ACTIVO')->countAllResults(),
            'usuarios'  => (int) $db->table('users')->where('tenant_id', $tid)->where('estado', 'ACTIVO')
                                ->where('deleted_at IS NULL', null, false)->countAllResults(),
            'clientes'  => (int) $db->table('clientes')->where('tenant_id', $tid)->where('estado', 'ACTIVO')->countAllResults(),
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
        $t    = $db->table('tenants')->select('suscripcion_estado, dia_pago, gracia_dias')->where('id', $tid)->get()->getRowArray() ?? [];
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
        // Gracia: corte = día de pago + días de gracia (fecha real — puede cruzar de mes)
        $dia    = min(28, max(1, (int) ($t['dia_pago'] ?? 10)));
        $gracia = max(0, (int) ($t['gracia_dias'] ?? 4));
        $corte  = date('Y-m-d', strtotime(date('Y-m-') . str_pad((string) $dia, 2, '0', STR_PAD_LEFT) . " +{$gracia} days"));
        if (date('Y-m-d') <= $corte) {
            return ['ok' => true, 'estado' => 'PENDIENTE', 'motivo' => 'Pago del período pendiente (día ' . $dia . ' + ' . $gracia . 'd de gracia → ' . $corte . ').',
                    'proximo' => $proximo, 'ultimo_pago' => $ultimoStr];
        }
        return ['ok' => false, 'estado' => 'VENCIDA',
                'motivo' => 'Suscripción vencida — no se registró el pago del período ' . $periodo . ' (venció el ' . $corte . ').',
                'proximo' => $proximo, 'ultimo_pago' => $ultimoStr];
    }

    /**
     * Desglose del cobro mensual del tenant: precio del plan + sobreconsumo
     * de los 4 conceptos (usuarios, empleados, créditos activos, clientes).
     * Las llaves extra/monto_extra/precio_extra quedan apuntando a usuarios
     * por compatibilidad con UsuarioController y TenantService.
     *
     * @return array{plan:?string, precio:float, moneda:string, usuarios:int,
     *               incluidos:int, extra:int, precio_extra:float, monto_extra:float,
     *               recursos:array, total:float}
     */
    function plan_cobro_mes(int $tenantId): array
    {
        $plan   = plan_actual($tenantId);
        $precio = (float) ($plan['precio_mensual'] ?? 0);
        $uso    = plan_uso($tenantId);

        // concepto => [uso, incluido (-1 = ilimitado), tarifa extra]
        $defs = [
            'usuarios'  => ['Usuarios del sistema', (int) ($plan['max_usuarios'] ?? -1),         PLAN_USD_EXTRA_USUARIO],
            'empleados' => ['Empleados / cobradores', (int) ($plan['max_empleados'] ?? -1),       PLAN_USD_EXTRA_EMPLEADO],
            'creditos'  => ['Créditos activos',       (int) ($plan['max_creditos_activos'] ?? -1), PLAN_USD_EXTRA_CREDITO],
            'clientes'  => ['Clientes registrados',   PLAN_BASE_CLIENTES,                         PLAN_USD_EXTRA_CLIENTE],
        ];

        $recursos = [];
        $totalExtra = 0.0;
        foreach ($defs as $key => [$label, $incluido, $tarifa]) {
            $cant   = (int) ($uso[$key] ?? 0);
            $extra  = $incluido >= 0 ? max(0, $cant - $incluido) : 0;
            $monto  = round($extra * $tarifa, 2);
            $totalExtra += $monto;
            $recursos[$key] = [
                'label'    => $label,
                'uso'      => $cant,
                'incluido' => $incluido,
                'extra'    => $extra,
                'tarifa'   => $tarifa,
                'monto'    => $monto,
            ];
        }

        return [
            'plan'         => $plan['nombre'] ?? null,
            'precio'       => $precio,
            'moneda'       => $plan['moneda'] ?? 'USD',
            'usuarios'     => $recursos['usuarios']['uso'],
            'incluidos'    => $recursos['usuarios']['incluido'],
            'extra'        => $recursos['usuarios']['extra'],
            'precio_extra' => PLAN_USD_EXTRA_USUARIO,
            'monto_extra'  => $recursos['usuarios']['monto'],
            'recursos'     => $recursos,
            'monto_extras' => round($totalExtra, 2),
            'total'        => round($precio + $totalExtra, 2),
        ];
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

if (!function_exists('en_horario')) {
    /**
     * Horario laboral del tenant � portal gestor + app m�vil.
     * tenants.hora_inicio / hora_fin (TIME). Sin rango = sin restricci�n.
     * Soporta rangos que cruzan medianoche (p.ej. 20:00�06:00).
     * @return array{ok: bool, ini: string, fin: string}
     */
    function en_horario(array $tenant): array
    {
        $ini = (string) ($tenant['hora_inicio'] ?? '');
        $fin = (string) ($tenant['hora_fin'] ?? '');
        $out = ['ok' => true, 'ini' => substr($ini, 0, 5), 'fin' => substr($fin, 0, 5)];
        if (!$ini || !$fin) {
            return $out;
        }
        // El server corre en UTC (site4now) — el horario del tenant es hora
        // de Nicaragua; evaluar con TZ fija para no desplazar el rango +6h.
        $ahora = (new DateTime('now', new DateTimeZone('America/Managua')))->format('H:i:s');
        $out['ok'] = $ini <= $fin
            ? ($ahora >= $ini && $ahora <= $fin)   // mismo d�a
            : ($ahora >= $ini || $ahora <= $fin); // cruza medianoche
        return $out;
    }
}
