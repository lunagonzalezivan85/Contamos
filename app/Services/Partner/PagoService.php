<?php

namespace App\Services\Partner;

use App\Models\CuotaModel;
use App\Models\PagoModel;
use App\Models\SolicitudHistorialModel;
use App\Models\SolicitudModel;
use App\Models\TenantModel;
use Config\Database;

/**
 * Lógica de cobros: cuotas del plan de pago + pagos con flujo de revisión.
 *
 * Flujo: registrar → REVISION → aprobar (transacción que aplica a cuotas FIFO)
 *        o rechazar (no aplica nada). Nada se aplica mientras está en revisión.
 */
class PagoService
{
    private SolicitudModel   $solicitudes;
    private CuotaModel       $cuotas;
    private PagoModel        $pagos;
    private TenantModel      $tenants;
    private SolicitudService $solSvc;
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->solicitudes = new SolicitudModel();
        $this->cuotas      = new CuotaModel();
        $this->pagos       = new PagoModel();
        $this->tenants     = new TenantModel();
        $this->solSvc      = new SolicitudService();
        $this->db          = Database::connect();
    }

    // ---------------------------------------------------------------
    // Créditos (solicitudes ACTIVO con plan persistido)
    // ---------------------------------------------------------------

    /** Moneda del tenant (fallback C$). */
    public function moneda(int $tenantId): string
    {
        return (string) ($this->tenants->find($tenantId)['moneda'] ?? 'C$');
    }

    /** Listado de créditos activos del tenant con saldo y próxima cuota. */
    public function creditosActivos(int $tenantId, string $buscar = ''): array
    {
        $model = new SolicitudModel();
        $model->select('solicitudes.*, personas.nombres, personas.apellidos, personas.telefono,
                        personas.cedula, clientes.codigo,
                        emp_p.nombres AS gestor_nombres, emp_p.apellidos AS gestor_apellidos')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = solicitudes.asignado_a', 'left')
            ->join('personas emp_p', 'emp_p.id = emp.persona_id', 'left')
            ->where('solicitudes.tenant_id', $tenantId)
            ->where('solicitudes.estado', SolicitudModel::ACTIVO);
        if ($buscar !== '') {
            $model->groupStart()
                ->like('personas.nombres', $buscar)
                ->orLike('personas.apellidos', $buscar)
                ->orLike('personas.cedula', $buscar)
                ->orLike('solicitudes.codigo_credito', $buscar)
                ->orLike('clientes.codigo', $buscar)
                ->groupEnd();
        }
        $rows = $model->orderBy('solicitudes.id', 'DESC')->paginate(15);
        $model->pager->only(['q']);

        // Saldo + próxima cuota por crédito
        foreach ($rows as &$r) {
            $r['saldo'] = $this->saldoCredito($r);
            $r['proxima'] = $this->proximaCuota((int) $r['id']);
        }
        return ['rows' => $rows, 'pager' => $model->pager];
    }

    /**
     * Cartera vigente del tenant — como creditosActivos pero con salud de
     * cada crédito: cuotas vencidas, saldo vencido y días de atraso.
     */
    public function cartera(int $tenantId, string $buscar = '', string $solo = ''): array
    {
        $model = new SolicitudModel();
        $model->select('solicitudes.*, personas.nombres, personas.apellidos, personas.telefono,
                        personas.cedula, clientes.codigo,
                        emp_p.nombres AS gestor_nombres, emp_p.apellidos AS gestor_apellidos')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = solicitudes.asignado_a', 'left')
            ->join('personas emp_p', 'emp_p.id = emp.persona_id', 'left')
            ->where('solicitudes.tenant_id', $tenantId)
            ->where('solicitudes.estado', SolicitudModel::ACTIVO);
        if ($buscar !== '') {
            $model->groupStart()
                ->like('personas.nombres', $buscar)
                ->orLike('personas.apellidos', $buscar)
                ->orLike('personas.cedula', $buscar)
                ->orLike('solicitudes.codigo_credito', $buscar)
                ->orLike('clientes.codigo', $buscar)
                ->groupEnd();
        }
        $rows = $model->orderBy('solicitudes.id', 'DESC')->findAll();

        $hoy = date('Y-m-d');
        $totSaldo = 0.0; $totVencido = 0.0; $conMora = 0;
        foreach ($rows as &$r) {
            $r['saldo'] = $this->saldoCredito($r);
            $v = $this->db->table('cuotas')
                ->select("COUNT(*) AS n, COALESCE(SUM(cuota - pagado),0) AS vencido,
                          COALESCE(MAX(DATEDIFF('$hoy', fecha_vence)),0) AS dias", false)
                ->where('solicitud_id', (int) $r['id'])
                ->where('pagado < cuota', null, false)
                ->where('fecha_vence <', $hoy)
                ->get()->getRowArray();
            $r['vencidas']      = (int) ($v['n'] ?? 0);
            $r['saldo_vencido'] = (float) ($v['vencido'] ?? 0);
            $r['dias_atraso']   = (int) ($v['dias'] ?? 0);
            $totSaldo   += $r['saldo'];
            $totVencido += $r['saldo_vencido'];
            if ($r['vencidas'] > 0) $conMora++;
        }
        unset($r);

        if ($solo === 'mora') {
            $rows = array_values(array_filter($rows, fn($x) => $x['vencidas'] > 0));
        }
        // Mayor atraso primero
        usort($rows, fn($a, $b) => $b['dias_atraso'] <=> $a['dias_atraso']);

        return [
            'rows'   => $rows,
            'totales' => [
                'creditos'     => count($rows),
                'saldo'        => round($totSaldo, 2),
                'vencido'      => round($totVencido, 2),
                'con_mora'     => $conMora,
                'pct_vencido'  => $totSaldo > 0 ? round(100 * $totVencido / $totSaldo, 2) : 0.0,
            ],
        ];
    }

    /**
     * Recuperación — créditos con cuotas vencidas sin pagar, agrupados por
     * crédito: n de cuotas, saldo vencido, días de atraso, gestor a cargo.
     */
    public function recuperacion(int $tenantId, string $buscar = '', ?string $hasta = null): array
    {
        $hasta = $hasta && preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta) ? $hasta : date('Y-m-d');
        $rows = $this->db->table('cuotas q')
            ->select("s.id AS solicitud_id, s.codigo_credito, s.monto_aprobado,
                      p.nombres, p.apellidos, p.telefono, p.cedula,
                      emp_p.nombres AS gestor_nombres, emp_p.apellidos AS gestor_apellidos,
                      COUNT(*) AS cuotas_vencidas,
                      COALESCE(SUM(q.cuota - q.pagado),0) AS saldo_vencido,
                      MIN(q.fecha_vence) AS primera_vencida,
                      COALESCE(MAX(DATEDIFF('$hasta', q.fecha_vence)),0) AS dias_atraso", false)
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->join('empleados emp', 'emp.id = s.asignado_a', 'left')
            ->join('personas emp_p', 'emp_p.id = emp.persona_id', 'left')
            ->where('q.tenant_id', $tenantId)
            ->where('s.estado', SolicitudModel::ACTIVO)
            ->where('q.pagado < q.cuota', null, false)
            ->where('q.fecha_vence <=', $hasta)
            ->groupBy('s.id')
            ->orderBy('dias_atraso', 'DESC')
            ->get()->getResultArray();

        if ($buscar !== '') {
            $b = mb_strtolower($buscar);
            $rows = array_values(array_filter($rows, fn($x) =>
                str_contains(mb_strtolower($x['nombres'] . ' ' . $x['apellidos']), $b)
                || str_contains(mb_strtolower((string) $x['codigo_credito']), $b)
                || str_contains(mb_strtolower((string) $x['cedula']), $b)));
        }

        $tot = 0.0;
        foreach ($rows as $x) $tot += (float) $x['saldo_vencido'];

        // Cobrado en esa fecha (cobros vigentes del día) — para la barra
        // de cumplimiento: lo recibido vs lo que quedaba por recuperar.
        $cobrado = (float) ($this->pagos
            ->selectSum('monto')
            ->where('tenant_id', $tenantId)
            ->where('tipo', PagoModel::TIPO_PAGO)
            ->whereIn('estado', [PagoModel::REVISION, PagoModel::APLICADO])
            ->where('DATE(' . $this->db->prefixTable('pagos') . '.fecha_hora)', $hasta)
            ->first()['monto'] ?? 0);

        return [
            'rows'          => $rows,
            'total_vencido' => round($tot, 2),
            'cobrado'       => round($cobrado, 2),
            'hoy'           => $hasta,
        ];
    }

    /** Crédito del tenant (ACTIVO) con datos del cliente/gestor, o null. */
    public function credito(int $tenantId, int $id): ?array
    {
        return $this->solicitudes
            ->select('solicitudes.*, personas.nombres, personas.apellidos, personas.telefono,
                      personas.cedula, clientes.codigo, clientes.id AS cli_id,
                      emp_p.nombres AS gestor_nombres, emp_p.apellidos AS gestor_apellidos')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = solicitudes.asignado_a', 'left')
            ->join('personas emp_p', 'emp_p.id = emp.persona_id', 'left')
            ->where('solicitudes.tenant_id', $tenantId)
            ->find($id);
    }

    /**
     * Ficha del crédito: solicitud + cuotas persistidas + pagos + saldo.
     * Las cuotas traen `vencida`/`pendiente` calculados on-read.
     */
    public function fichaCredito(int $tenantId, int $id): ?array
    {
        $sol = $this->credito($tenantId, $id);
        if (!$sol || $sol['estado'] !== SolicitudModel::ACTIVO) {
            return null;
        }
        $hoy       = date('Y-m-d');
        $tenant    = $this->tenants->find($tenantId) ?? [];
        $pctMora   = (float) ($tenant['mora_diaria_pct'] ?? 0);
        $pctPronto = (float) ($tenant['pronto_pago_pct'] ?? 0);

        $cuotas  = $this->cuotas->deCredito($id);
        $moraTot = $descTot = $liquidacion = 0;
        foreach ($cuotas as &$c) {
            $c['pendiente']  = $this->cuotaPendiente($c);
            $c['vencida']    = in_array($c['estado'], [CuotaModel::PENDIENTE, CuotaModel::PARCIAL], true)
                               && $c['fecha_vence'] < $hoy;
            $c['mora_info']  = $this->moraDeCuota($c, $pctMora, $hoy);
            $moraTot        += $c['mora_info']['pendiente'];
            $descTot        += (float) ($c['descuento'] ?? 0);
            $liquidacion    += $c['pendiente'] + $c['mora_info']['pendiente'];
        }
        unset($c);

        // Pronto pago (opcional por tenant): descuento del pct sobre el
        // interés aún no cobrado si el cliente liquida todo hoy.
        $ahorro = $pctPronto > 0
            ? round(array_sum(array_map([$this, 'interesPendiente'], $cuotas)) * $pctPronto / 100, 2)
            : 0;

        return [
            'sol'         => $sol,
            'cuotas'      => $cuotas,
            'pagos'       => $this->pagos->deCredito($id),
            'saldo'       => $this->saldoCredito($sol),
            'hoy'         => $hoy,
            'mora'        => ['pct' => $pctMora, 'pendiente' => round($moraTot, 2)],
            'pronto'      => ['pct' => $pctPronto, 'ahorro' => $ahorro,
                              'liquidacion' => round(max(0, $liquidacion - $ahorro), 2)],
            'saldo_favor' => (float) ($sol['saldo_favor'] ?? 0),
            'descuento'   => round($descTot, 2),
        ];
    }

    // ---------------------------------------------------------------
    // Cuotas — se generan al entregar el desembolso
    // ---------------------------------------------------------------

    /**
     * Persiste el plan francés aprobado como cuotas. Idempotente:
     * no duplica si el crédito ya tiene cuotas.
     */
    public function generarCuotas(int $tenantId, array $sol, int $offsetN = 0): void
    {
        // Idempotente: solo un plan vigente; las ANULADAS no cuentan
        // (reestructuración deja el historial como ANULADA y genera uno nuevo).
        if ($this->cuotas->where('solicitud_id', (int) $sol['id'])
            ->whereNotIn('estado', [CuotaModel::ANULADA])->countAllResults() > 0) {
            return;
        }
        $plan = $this->solSvc->planPagos($sol);
        $batch = [];
        foreach ($plan['rows'] as $r) {
            $batch[] = [
                'tenant_id'        => $tenantId,
                'solicitud_id'     => (int) $sol['id'],
                'n'                => $r['n'] + $offsetN,
                'fecha_vence'      => $r['fecha'],
                'cuota'            => $r['cuota'],
                'interes'          => $r['interes'],
                'capital'          => $r['capital'],
                'saldo_proyectado' => $r['saldo'],
                'pagado'           => 0,
                'estado'           => CuotaModel::PENDIENTE,
            ];
        }
        if ($batch) {
            $this->cuotas->insertBatch($batch);
        }
    }

    // ---------------------------------------------------------------
    // Mora / pronto pago / saldo a favor (reglas del negocio)
    // ---------------------------------------------------------------

    /** Pendiente monetario de la cuota: cuota − pagado − descuento condonado. */
    private function cuotaPendiente(array $c): float
    {
        return max(0, round((float) $c['cuota'] - (float) $c['pagado'] - (float) ($c['descuento'] ?? 0), 2));
    }

    /** Interés aún no cobrado de la cuota (los pagos cubren interés primero). */
    private function interesPendiente(array $c): float
    {
        if ($this->cuotaPendiente($c) <= 0.005) {
            return 0;
        }
        return max(0, round((float) $c['interes'] - min((float) $c['pagado'], (float) $c['interes']), 2));
    }

    /**
     * Mora de la cuota — se devenga SOLO después del vencimiento y sobre el
     * pendiente (nunca durante el crédito). Cuando la cuota queda PAGADA lo
     * devengado se congela en `mora_dev`; `mora` acumula lo cobrado.
     * @return array{dias: int, dev: float, pendiente: float, cobrada: float}
     */
    private function moraDeCuota(array $c, float $pct, string $hoy): array
    {
        $cobrada = (float) ($c['mora'] ?? 0);
        $dias    = max(0, (int) floor((strtotime($hoy) - strtotime((string) $c['fecha_vence'])) / 86400));
        $dev     = $c['estado'] === CuotaModel::PAGADA
            ? (float) ($c['mora_dev'] ?? 0)                            // congelada al pagar
            : round($this->cuotaPendiente($c) * $pct / 100 * $dias, 2); // devengo dinámico
        return [
            'dias'      => $dias,
            'dev'       => $dev,
            'pendiente' => max(0, round($dev - $cobrada, 2)),
            'cobrada'   => $cobrada,
        ];
    }

    /** Próxima cuota pendiente/parcial de un crédito. */
    public function proximaCuota(int $solicitudId): ?array
    {
        return $this->cuotas->where('solicitud_id', $solicitudId)
            ->whereIn('estado', [CuotaModel::PENDIENTE, CuotaModel::PARCIAL])
            ->orderBy('fecha_vence', 'ASC')->orderBy('n', 'ASC')
            ->first();
    }

    /**
     * Saldo vivo del crédito = total del plan − lo recuperado (pagos APLICADO).
     * Con cuotas persistidas: Σ(cuota − pagado). Sin plan (crédito activado
     * antes de existir `cuotas`): plan calculado − aplicado.
     */
    public function saldoCredito(array $sol): float
    {
        $solId = (int) $sol['id'];
        if ($this->cuotas->where('solicitud_id', $solId)->countAllResults() > 0) {
            return $this->cuotas->saldoDe($solId);
        }
        $total    = (float) $this->solSvc->planPagos($sol)['total'];
        $aplicado = (float) ($this->pagos
            ->selectSum('monto')
            ->where('solicitud_id', $solId)
            ->where('estado', PagoModel::APLICADO)
            ->first()['monto'] ?? 0);
        return max(0, round($total - $aplicado, 2));
    }

    // ---------------------------------------------------------------
    // Reestructuración y refinanciamiento
    // ---------------------------------------------------------------

    /**
     * Reestructura el crédito: las cuotas pendientes/parciales se anulan
     * (conservan su pagado histórico) y se genera un plan nuevo sobre el
     * saldo pendiente con los términos indicados.
     * @return array{ok: bool, base?: float, error?: string}
     */
    public function reestructurar(int $tenantId, int $solId, array $d): array
    {
        $sol = $this->solicitudes->where('tenant_id', $tenantId)->find($solId);
        if (!$sol || $sol['estado'] !== SolicitudModel::ACTIVO) {
            return ['ok' => false, 'error' => 'Solo se puede reestructurar un crédito activo.'];
        }

        $pendientes = $this->cuotas->where('solicitud_id', $solId)
            ->whereIn('estado', [CuotaModel::PENDIENTE, CuotaModel::PARCIAL])->findAll();
        $base = 0;
        foreach ($pendientes as $c) {
            $base += $this->cuotaPendiente($c);
        }
        $base = round($base, 2);
        if ($base <= 0) {
            return ['ok' => false, 'error' => 'El crédito ya está liquidado — nada que reestructurar.'];
        }

        $fechaPrimer = (string) ($d['fecha_primer_pago'] ?? '');
        if ($fechaPrimer === '' || $fechaPrimer < date('Y-m-d')) {
            return ['ok' => false, 'error' => 'El primer pago del nuevo plan no puede ser en el pasado.'];
        }

        $db = Database::connect();
        $db->transStart();

        // 1) Anular el plan vigente — el pagado histórico queda en la fila
        $this->cuotas->where('solicitud_id', $solId)
            ->whereIn('estado', [CuotaModel::PENDIENTE, CuotaModel::PARCIAL])
            ->set(['estado' => CuotaModel::ANULADA])->update();

        // 2) Nuevos términos del crédito
        $terms = [
            'tasa_aprobada'       => (float) ($d['tasa_aprobada'] ?? $sol['tasa_aprobada']),
            'plazo_aprobado'      => max(0.5, (float) ($d['plazo_aprobado'] ?? $sol['plazo_aprobado'])),
            'frecuencia_aprobada' => (string) ($d['frecuencia_aprobada'] ?? $sol['frecuencia_aprobada']),
            'fecha_primer_pago'   => $fechaPrimer,
        ];
        if (isset(SolicitudService::TIPOS_CALCULO[$d['tipo_calculo'] ?? ''])) {
            $terms['tipo_calculo'] = (string) $d['tipo_calculo'];
        }
        $this->solicitudes->update($solId, $terms);

        // 3) Plan nuevo sobre el saldo pendiente — continúa la numeración
        $maxN = (int) ($this->cuotas->where('solicitud_id', $solId)
            ->selectMax('n')->first()['n'] ?? 0);
        $solNew = array_merge($sol, $terms, ['monto_aprobado' => $base]);
        $this->generarCuotas($tenantId, $solNew, $maxN);

        $db->transComplete();
        return $db->transStatus()
            ? ['ok' => true, 'base' => $base]
            : ['ok' => false, 'error' => 'No se pudo reestructurar el crédito.'];
    }

    /**
     * Reprograma SOLO las fechas del plan: la primera cuota pendiente toma
     * $nuevaFecha y las demás pendientes/parciales se recalculan con la
     * frecuencia del crédito. Montos, numeración e historial intactos.
     * @return array{ok: bool, movidas?: int, desde?: string, error?: string}
     */
    public function reprogramar(int $tenantId, int $solId, string $nuevaFecha): array
    {
        $sol = $this->solicitudes->where('tenant_id', $tenantId)->find($solId);
        if (!$sol || $sol['estado'] !== SolicitudModel::ACTIVO) {
            return ['ok' => false, 'error' => 'Solo se puede reprogramar un crédito activo.'];
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $nuevaFecha) || $nuevaFecha < date('Y-m-d')) {
            return ['ok' => false, 'error' => 'La nueva fecha no puede ser en el pasado.'];
        }

        $pend = $this->cuotas->where('solicitud_id', $solId)
            ->whereIn('estado', [CuotaModel::PENDIENTE, CuotaModel::PARCIAL])
            ->orderBy('n', 'ASC')->findAll();
        if (!$pend) {
            return ['ok' => false, 'error' => 'No quedan cuotas pendientes por reprogramar.'];
        }

        $freq = (string) ($sol['frecuencia_aprobada'] ?? $sol['frecuencia'] ?? 'M');
        $paso = SolicitudService::pasoDias($freq, (int) ($sol['dias_semana'] ?? 3), (int) ($sol['paso_dias'] ?? 0));

        $db = Database::connect();
        $db->transStart();

        $f = new \DateTime($nuevaFecha);
        foreach ($pend as $c) {
            $this->cuotas->update((int) $c['id'], ['fecha_vence' => $f->format('Y-m-d')]);
            SolicitudService::pasoFecha($f, $freq, $paso);
        }
        // el primer pago del crédito pasa a ser la nueva fecha
        $this->solicitudes->update($solId, ['fecha_primer_pago' => $nuevaFecha]);

        $db->transComplete();
        return $db->transStatus()
            ? ['ok' => true, 'movidas' => count($pend), 'desde' => $nuevaFecha]
            : ['ok' => false, 'error' => 'No se pudo reprogramar el plan.'];
    }

    /**
     * Refinancia el crédito: crea una solicitud nueva (estado CREADA) del
     * mismo cliente que sigue el pipeline normal. Al entregarse el dinero,
     * `liquidarPorRefinanciamiento` salda el crédito anterior.
     * @return array{ok: bool, sol_id?: int, error?: string}
     */
    public function refinanciar(int $tenantId, int $solId, array $d): array
    {
        $sol = $this->solicitudes->where('tenant_id', $tenantId)->find($solId);
        if (!$sol || $sol['estado'] !== SolicitudModel::ACTIVO) {
            return ['ok' => false, 'error' => 'Solo se puede refinanciar un crédito activo.'];
        }
        $yaHay = $this->solicitudes->where('refinancia_id', $solId)
            ->whereNotIn('estado', [SolicitudModel::RECHAZADA])->countAllResults();
        if ($yaHay > 0) {
            return ['ok' => false, 'error' => 'Ya existe una solicitud de refinanciamiento para este crédito.'];
        }

        $monto = (float) ($d['monto'] ?? 0);
        $nuevo = $this->solicitudes->insert([
            'tenant_id'     => $tenantId,
            'cliente_id'    => (int) $sol['cliente_id'],
            'asignado_a'    => $sol['asignado_a'],
            'ruta'          => $sol['ruta'],
            'monto'         => $monto > 0 ? $monto : $this->saldoCredito($sol),
            'plazo_meses'   => (float) ($d['plazo_meses'] ?? $sol['plazo_aprobado'] ?: $sol['plazo_meses']),
            'frecuencia'    => (string) ($d['frecuencia'] ?? $sol['frecuencia_aprobada'] ?? $sol['frecuencia'] ?? 'M'),
            'tasa_mensual'  => ($d['tasa_mensual'] ?? '') !== ''
                ? (float) $d['tasa_mensual'] : (float) ($sol['tasa_aprobada'] ?? $sol['tasa_mensual']),
            'tipo_calculo'  => (string) ($d['tipo_calculo'] ?? $sol['tipo_calculo'] ?? 'FRANCES'),
            'destino'       => 'Refinanciamiento de ' . ($sol['codigo_credito'] ?? ('crédito #' . $solId)),
            'refinancia_id' => $solId,
            'estado'        => SolicitudModel::CREADA,
        ]);

        if ($nuevo) {
            (new SolicitudHistorialModel())->registrar($tenantId, (int) $nuevo,
                SolicitudHistorialModel::CREADO, SolicitudModel::CREADA,
                'Refinanciamiento del crédito #' . $solId,
                ['user_id' => (int) session('user_id') ?: null,
                 'nombre'  => session('nombre') ?: 'Oficina']);
        }

        return $nuevo
            ? ['ok' => true, 'sol_id' => (int) $nuevo]
            : ['ok' => false, 'error' => 'No se pudo crear la solicitud de refinanciamiento.'];
    }

    /**
     * Al entregarse el refinanciamiento: registra un pago interno por el
     * saldo del crédito anterior, lo aplica y marca el crédito LIQUIDADO.
     * El pago es INTERNO (empleado_id null) — no entra al arqueo del gestor.
     */
    public function liquidarPorRefinanciamiento(int $tenantId, int $refId, int $newSolId): void
    {
        $old = $this->solicitudes->where('tenant_id', $tenantId)->find($refId);
        if (!$old || $old['estado'] !== SolicitudModel::ACTIVO) {
            return;
        }
        $saldo = $this->saldoCredito($old);
        if ($saldo <= 0) {
            $this->solicitudes->update($refId, ['estado' => SolicitudModel::LIQUIDADO]);
            return;
        }

        $pagoId = $this->pagos->insert([
            'tenant_id'      => $tenantId,
            'solicitud_id'   => $refId,
            'cuota_id'       => null,
            'empleado_id'    => null,
            'registrado_por' => null,
            'monto'          => $saldo,
            'metodo'         => 'INTERNO',
            'tipo'           => PagoModel::TIPO_PAGO,
            'fecha_hora'     => date('Y-m-d H:i:s'),
            'observacion'    => 'Liquidado por refinanciamiento (solicitud #' . $newSolId . ')',
            'estado'         => PagoModel::REVISION,
        ]);
        $this->aprobarPago($tenantId, (int) $pagoId, 0);
        $this->solicitudes->update($refId, ['estado' => SolicitudModel::LIQUIDADO]);
    }

    // ---------------------------------------------------------------
    // Pagos — todo pago nace en REVISION
    // ---------------------------------------------------------------

    /**
     * Registra un abono en REVISION (no mueve cuotas hasta aprobar).
     * $empleadoId = gestor que cobró en campo; $userId = usuario de oficina.
     * @return array{ok: bool, pago_id?: int, error?: string}
     */
    public function registrarPago(int $tenantId, int $solId, array $d, ?int $empleadoId, ?int $userId): array
    {
        $sol = $this->solicitudes->where('tenant_id', $tenantId)->find($solId);
        if (!$sol || $sol['estado'] !== SolicitudModel::ACTIVO) {
            return ['ok' => false, 'error' => 'Crédito no encontrado o no está activo.'];
        }
        if ($empleadoId !== null && (int) $sol['asignado_a'] !== $empleadoId) {
            return ['ok' => false, 'error' => 'Este crédito no está en tu cartera.'];
        }

        $monto = (float) ($d['monto'] ?? 0);
        if ($monto <= 0) {
            return ['ok' => false, 'error' => 'El monto debe ser mayor a cero.'];
        }
        $saldo = $this->cuotas->saldoDe($solId);
        if ($saldo <= 0 && $this->cuotas->where('solicitud_id', $solId)->countAllResults() === 0) {
            // Crédito activado antes de persistir el plan — generar cuotas ahora
            $this->generarCuotas($tenantId, $sol);
            $saldo = $this->cuotas->saldoDe($solId);
        }
        $saldo = $saldo > 0 ? $saldo : $this->saldoCredito($sol);
        if ($saldo <= 0) {
            return ['ok' => false, 'error' => 'El crédito ya está liquidado.'];
        }

        $cuotaId = (int) ($d['cuota_id'] ?? 0);
        if ($cuotaId) {
            $cuota = $this->cuotas->where('solicitud_id', $solId)->find($cuotaId);
            if (!$cuota) {
                return ['ok' => false, 'error' => 'La cuota indicada no pertenece al crédito.'];
            }
        }

        $esPromesa = ($d['tipo'] ?? '') === PagoModel::TIPO_PROMESA;

        // Anti-duplicado server-side: mismo abono (solicitud + monto + tipo +
        // quién lo cobró) en los últimos 2 min = reenvío del form
        // (doble-tap / retry). Devolvemos el pago ya registrado.
        $dupQ = $this->pagos->where('tenant_id', $tenantId)
            ->where('solicitud_id', $solId)
            ->where('monto', $monto)
            ->where('tipo', $esPromesa ? PagoModel::TIPO_PROMESA : PagoModel::TIPO_PAGO)
            ->whereIn('estado', [PagoModel::REVISION, PagoModel::APLICADO])
            ->where('created_at >=', date('Y-m-d H:i:s', time() - 120));
        $empleadoId === null
            ? $dupQ->where('empleado_id IS NULL') : $dupQ->where('empleado_id', $empleadoId);
        $userId === null
            ? $dupQ->where('registrado_por IS NULL') : $dupQ->where('registrado_por', $userId);
        if ($cuotaId) {
            $dupQ->where('cuota_id', $cuotaId);
        }
        $dup = $dupQ->first();
        if ($dup) {
            return ['ok' => true, 'pago_id' => (int) $dup['id']];
        }

        $metodo = in_array($d['metodo'] ?? '', array_keys(PagoModel::METODOS), true)
            ? $d['metodo'] : 'EFECTIVO';
        $pagoId = $this->pagos->insert([
            'tenant_id'      => $tenantId,
            'solicitud_id'   => $solId,
            'cuota_id'       => $cuotaId ?: null,
            'empleado_id'    => $empleadoId,
            'registrado_por' => $userId,
            'monto'          => $monto,
            'metodo'         => $metodo,
            'tipo'           => $esPromesa ? PagoModel::TIPO_PROMESA : PagoModel::TIPO_PAGO,
            'fecha_hora'     => ($d['fecha_hora'] ?? '') ?: date('Y-m-d H:i:s'),
            'observacion'    => trim((string) ($d['observacion'] ?? '')) ?: null,
            'estado'         => PagoModel::REVISION,
        ]);

        // Efectivo = el gestor ya tiene el dinero en mano → se aplica de
        // inmediato. Transferencia (y promesas) quedan en REVISION hasta
        // que oficina las valide.
        if (!$esPromesa && $metodo === 'EFECTIVO') {
            $this->aprobarPago($tenantId, (int) $pagoId, $userId ?? 0);
        }

        return ['ok' => true, 'pago_id' => (int) $pagoId];
    }

    /** Datos para el voucher del recibo: pago + cliente + crédito + tenant + saldo. */
    public function reciboPago(int $tenantId, int $pagoId): ?array
    {
        $pago = $this->pagos->recibo($tenantId, $pagoId);
        if (!$pago) return null;
        $tenant = (new \App\Models\TenantModel())->find($tenantId);

        // Saldo vivo del crédito (cuotas pendientes). Si el pago sigue en
        // REVISION aún no se descontó del plan — se muestra el saldo actual.
        $sol   = $this->solicitudes->where('tenant_id', $tenantId)->find((int) $pago['solicitud_id']);
        $saldo = $sol ? $this->saldoCredito($sol) : null;

        return ['pago' => $pago, 'tenant' => $tenant, 'saldo' => $saldo];
    }

    /**
     * La promesa se cumplió: el cliente pagó. Pasa a ser un cobro normal
     * en REVISIÓN — oficina aún debe aprobarlo para aplicar a cuotas.
     * @return array{ok: bool, error?: string}
     */
    public function promesaACobro(int $tenantId, int $pagoId, int $userId): array
    {
        $pago = $this->pagos->where('tenant_id', $tenantId)->find($pagoId);
        if (!$pago || $pago['estado'] !== PagoModel::REVISION || ($pago['tipo'] ?? 'PAGO') !== PagoModel::TIPO_PROMESA) {
            return ['ok' => false, 'error' => 'Promesa no encontrada o ya fue resuelta.'];
        }
        $this->pagos->update($pagoId, [
            'tipo'        => PagoModel::TIPO_PAGO,
            'fecha_hora'  => date('Y-m-d H:i:s'),
            'observacion' => trim('Promesa cumplida. ' . ($pago['observacion'] ?? '')),
        ]);
        return ['ok' => true];
    }

    /**
     * Revertir un pago aplicado: inserta el mismo pago en NEGATIVO
     * (contra-pago) que nace EN REVISIÓN con observación obligatoria.
     * Al aprobarse, aprobarPago() desaplica las cuotas y marca el
     * original como REVERTIDO.
     * @return array{ok: bool, pago_id?: int, error?: string}
     */
    public function revertirPago(int $tenantId, int $pagoId, string $obs, int $userId): array
    {
        $orig = $this->pagos->where('tenant_id', $tenantId)->find($pagoId);
        if (!$orig || $orig['estado'] !== PagoModel::APLICADO
            || (float) $orig['monto'] <= 0
            || ($orig['tipo'] ?? PagoModel::TIPO_PAGO) !== PagoModel::TIPO_PAGO) {
            return ['ok' => false, 'error' => 'Solo se pueden revertir pagos aplicados.'];
        }
        // No permitir dos reversas del mismo pago
        $ya = $this->pagos->where('revierte_id', $pagoId)
            ->whereIn('estado', [PagoModel::REVISION, PagoModel::APLICADO])
            ->countAllResults();
        if ($ya > 0) {
            return ['ok' => false, 'error' => 'Este pago ya tiene una reversión registrada.'];
        }

        $revId = $this->pagos->insert([
            'tenant_id'      => $tenantId,
            'solicitud_id'   => (int) $orig['solicitud_id'],
            'cuota_id'       => $orig['cuota_id'],
            'revierte_id'    => $pagoId,
            'empleado_id'    => $orig['empleado_id'],
            'registrado_por' => $userId,
            'monto'          => -1 * (float) $orig['monto'],
            'metodo'         => $orig['metodo'],
            'tipo'           => PagoModel::TIPO_PAGO,
            'fecha_hora'     => date('Y-m-d H:i:s'),
            'observacion'    => 'Reversión del pago #' . $pagoId . '. ' . trim($obs),
            'estado'         => PagoModel::REVISION,
        ]);

        return ['ok' => true, 'pago_id' => (int) $revId];
    }

    /**
     * Aprueba un pago en revisión — transacción: marca APLICADO y reparte
     * el monto a cuotas en orden (primero la indicada, luego FIFO por vencer).
     * Un contra-pago negativo (reversión) DESAPLICA de cuotas (LIFO) y
     * marca el pago original como REVERTIDO.
     * @return array{ok: bool, error?: string}
     */
    public function aprobarPago(int $tenantId, int $pagoId, int $userId): array
    {
        $db   = Database::connect();
        $pago = $this->pagos->where('tenant_id', $tenantId)->find($pagoId);
        if (!$pago || $pago['estado'] !== PagoModel::REVISION) {
            return ['ok' => false, 'error' => 'Pago no encontrado o ya fue resuelto.'];
        }
        if (($pago['tipo'] ?? PagoModel::TIPO_PAGO) === PagoModel::TIPO_PROMESA) {
            return ['ok' => false, 'error' => 'Es una promesa — conviértala en cobro cuando el cliente pague.'];
        }

        $db->transStart();

        $this->pagos->update($pagoId, [
            'estado'        => PagoModel::APLICADO,
            'aprobado_por'  => $userId,
            'resuelto_at'   => date('Y-m-d H:i:s'),
        ]);

        $restante = (float) $pago['monto'];

        if ($restante < 0) {
            // Contra-pago (reversión): desaplicar EXACTAMENTE lo que el pago
            // original cubrió (pago_aplicaciones); fallback LIFO si no hay
            // trazabilidad (pagos anteriores a su existencia).
            $apps = !empty($pago['revierte_id'])
                ? $db->table('pago_aplicaciones')->where('pago_id', (int) $pago['revierte_id'])
                     ->orderBy('id', 'DESC')->get()->getResultArray()
                : [];

            if ($apps) {
                foreach ($apps as $a) {
                    if ($restante >= 0) break;
                    $c = $this->cuotas->find((int) $a['cuota_id']);
                    if (!$c) continue;
                    $deshacer = min(-$restante, (float) $a['monto']);
                    $upd = [];
                    if ($a['tipo'] === 'MORA') {
                        $upd['mora'] = round(max(0, (float) ($c['mora'] ?? 0) - $deshacer), 2);
                    } else {
                        $nuevo  = round(max(0, (float) $c['pagado'] - $deshacer), 2);
                        $llena  = $nuevo >= (float) $c['cuota'] - (float) ($c['descuento'] ?? 0) - 0.005;
                        $upd    = [
                            'pagado'     => $nuevo,
                            'estado'     => $llena ? CuotaModel::PAGADA : ($nuevo <= 0.005 ? CuotaModel::PENDIENTE : CuotaModel::PARCIAL),
                            'fecha_pago' => $llena ? $c['fecha_pago'] : null,
                            'descuento'  => $llena ? $c['descuento'] : 0,
                            'mora_dev'   => $llena ? ($c['mora_dev'] ?? 0) : 0,
                        ];
                    }
                    $this->cuotas->update((int) $c['id'], $upd);
                    $restante = round($restante + $deshacer, 2);
                }
                if (!empty($pago['revierte_id'])) {
                    $this->pagos->update((int) $pago['revierte_id'], ['estado' => PagoModel::REVERTIDO]);
                }
            } else {
            $cuotas = $this->cuotas->where('solicitud_id', (int) $pago['solicitud_id'])
                ->where('estado !=', CuotaModel::ANULADA)
                ->groupStart()->where('pagado >', 0)->orWhere('mora >', 0)->groupEnd()
                ->orderBy('fecha_vence', 'DESC')->orderBy('n', 'DESC')
                ->findAll();
            if (!empty($pago['cuota_id'])) {
                usort($cuotas, fn($a, $b) => ($a['id'] == $pago['cuota_id'] ? -1 : 0) <=> ($b['id'] == $pago['cuota_id'] ? -1 : 0));
            }
            foreach ($cuotas as $c) {
                if ($restante >= 0) break;

                // Se deshace lo que el pago cubrió en orden inverso: mora → cuota
                $moraCobrada = (float) ($c['mora'] ?? 0);
                if ($moraCobrada > 0) {
                    $amora = min(-$restante, $moraCobrada);
                    $this->cuotas->update((int) $c['id'], ['mora' => round($moraCobrada - $amora, 2)]);
                    $restante = round($restante + $amora, 2);
                    if ($restante >= 0) break;
                }

                $aplicar  = max($restante, -(float) $c['pagado']);
                $nuevo    = round((float) $c['pagado'] + $aplicar, 2);
                $restante = round($restante - $aplicar, 2);

                $llena = $nuevo >= (float) $c['cuota'] - (float) ($c['descuento'] ?? 0) - 0.005;
                $this->cuotas->update((int) $c['id'], [
                    'pagado'     => $nuevo,
                    'estado'     => $llena ? CuotaModel::PAGADA : ($nuevo <= 0.005 ? CuotaModel::PENDIENTE : CuotaModel::PARCIAL),
                    'fecha_pago' => $llena ? $c['fecha_pago'] : null,
                    // Al reabrir la cuota se revierte la condonación (pronto pago)
                    // y la mora vuelve a devengar de forma dinámica.
                    'descuento'  => $llena ? $c['descuento'] : 0,
                    'mora_dev'   => $llena ? ($c['mora_dev'] ?? 0) : 0,
                ]);
            }
            // Marcar el pago original como revertido
            if (!empty($pago['revierte_id'])) {
                $this->pagos->update((int) $pago['revierte_id'], ['estado' => PagoModel::REVERTIDO]);
            }
            }   // /fallback sin pago_aplicaciones
        } else {
            // Aplicar a cuotas: primero la indicada en el pago (si existe), luego
            // en orden de vencimiento. Por cada cuota: pendiente → mora.
            // Incluye cuotas PAGADA que aún tengan mora por cobrar.
            $hoy     = date('Y-m-d');
            $tenant  = $this->tenants->find($tenantId) ?? [];
            $pctMora = (float) ($tenant['mora_diaria_pct'] ?? 0);
            $pctPro  = (float) ($tenant['pronto_pago_pct'] ?? 0);

            $todas  = $this->cuotas->where('solicitud_id', (int) $pago['solicitud_id'])
                ->orderBy('fecha_vence', 'ASC')->orderBy('n', 'ASC')
                ->findAll();
            // Solo cuotas vivas — las ANULADAS (reestructuradas) conservan
            // su pagado histórico y no pueden recibir aplicaciones nuevas.
            $cuotas = array_values(array_filter($todas, fn($c) =>
                ($c['estado'] ?? '') !== CuotaModel::ANULADA
                && ($this->cuotaPendiente($c) > 0.005
                || $this->moraDeCuota($c, $pctMora, $hoy)['pendiente'] > 0.005)));

            if (!empty($pago['cuota_id'])) {
                usort($cuotas, fn($a, $b) => ($a['id'] == $pago['cuota_id'] ? -1 : 0) <=> ($b['id'] == $pago['cuota_id'] ? -1 : 0));
            }

            // PRONTO PAGO (opcional por tenant): si el pago cubre la liquidación
            // de hoy —pendiente total + mora − descuento del interés pendiente—
            // se condona esa parte del interés y el crédito queda liquidado.
            if ($pctPro > 0 && $cuotas) {
                $liq = 0;
                foreach ($cuotas as $c) {
                    $liq += $this->cuotaPendiente($c)
                          + $this->moraDeCuota($c, $pctMora, $hoy)['pendiente']
                          - min($this->cuotaPendiente($c), round($this->interesPendiente($c) * $pctPro / 100, 2));
                }
                $liq = round($liq, 2);
                if ($restante >= $liq - 0.005) {
                    $appsPronto = [];
                    foreach ($cuotas as $c) {
                        $pend  = $this->cuotaPendiente($c);
                        $mInfo = $this->moraDeCuota($c, $pctMora, $hoy);
                        $desc  = min($pend, round($this->interesPendiente($c) * $pctPro / 100, 2));
                        $pagar = round($pend - $desc, 2);

                        $this->cuotas->update((int) $c['id'], [
                            'pagado'     => round((float) $c['pagado'] + $pagar, 2),
                            'descuento'  => round((float) ($c['descuento'] ?? 0) + $desc, 2),
                            'mora_dev'   => $mInfo['dev'],
                            'mora'       => round($mInfo['cobrada'] + $mInfo['pendiente'], 2),
                            'estado'     => CuotaModel::PAGADA,
                            'fecha_pago' => $hoy,
                        ]);
                        $appsPronto[] = ['tenant_id' => $tenantId, 'pago_id' => $pagoId,
                                         'cuota_id' => (int) $c['id'], 'monto' => $pagar,
                                         'tipo' => 'CUOTA', 'created_at' => date('Y-m-d H:i:s')];
                        if ($mInfo['pendiente'] > 0.005) {
                            $appsPronto[] = ['tenant_id' => $tenantId, 'pago_id' => $pagoId,
                                             'cuota_id' => (int) $c['id'], 'monto' => $mInfo['pendiente'],
                                             'tipo' => 'MORA', 'created_at' => date('Y-m-d H:i:s')];
                        }
                        $restante = round($restante - $pagar - $mInfo['pendiente'], 2);
                    }
                    $db->table('pago_aplicaciones')->insertBatch($appsPronto);
                    $cuotas = [];
                }
            }

            $apps = [];
            foreach ($cuotas as $c) {
                if ($restante <= 0) break;
                $pendiente = $this->cuotaPendiente($c);

                // 1) abonar al pendiente de la cuota
                if ($pendiente > 0.005) {
                    $aplicar  = min($restante, $pendiente);
                    $nuevo    = round((float) $c['pagado'] + $aplicar, 2);
                    $restante = round($restante - $aplicar, 2);
                    $llena    = $nuevo >= (float) $c['cuota'] - (float) ($c['descuento'] ?? 0) - 0.005;

                    $upd = [
                        'pagado'     => $nuevo,
                        'estado'     => $llena ? CuotaModel::PAGADA : CuotaModel::PARCIAL,
                        'fecha_pago' => $llena ? $hoy : null,
                    ];
                    // La mora se congela al pagar la cuota completa
                    if ($llena) {
                        $upd['mora_dev'] = round($pendiente * $pctMora / 100
                            * max(0, (int) floor((strtotime($hoy) - strtotime((string) $c['fecha_vence'])) / 86400)), 2);
                        $c['mora_dev'] = $upd['mora_dev'];
                    }
                    $c['pagado'] = $nuevo;
                    $c['estado'] = $upd['estado'];
                    $this->cuotas->update((int) $c['id'], $upd);
                    $apps[] = ['cuota_id' => (int) $c['id'], 'monto' => $aplicar, 'tipo' => 'CUOTA'];
                }

                // 2) cobrar mora devengada pendiente de esta cuota
                $moraPend = $this->moraDeCuota($c, $pctMora, $hoy)['pendiente'];
                if ($restante > 0.005 && $moraPend > 0.005) {
                    $amora = min($restante, $moraPend);
                    $this->cuotas->update((int) $c['id'], [
                        'mora' => round((float) ($c['mora'] ?? 0) + $amora, 2),
                    ]);
                    $restante = round($restante - $amora, 2);
                    $apps[] = ['cuota_id' => (int) $c['id'], 'monto' => $amora, 'tipo' => 'MORA'];
                }
            }
            foreach ($apps as &$a) {
                $a += ['tenant_id' => $tenantId, 'pago_id' => $pagoId, 'created_at' => date('Y-m-d H:i:s')];
            }
            if ($apps) $db->table('pago_aplicaciones')->insertBatch($apps);

            // Sobre-pago: si el pago cubre TODO el plan y sobra, el excedente
            // queda como saldo a favor del cliente (no se pierde).
            if ($restante > 0.005) {
                $sol = $this->solicitudes->find((int) $pago['solicitud_id']);
                $this->solicitudes->update((int) $pago['solicitud_id'], [
                    'saldo_favor' => round((float) ($sol['saldo_favor'] ?? 0) + $restante, 2),
                ]);
            }
        }

        $db->transComplete();

        return $db->transStatus()
            ? ['ok' => true]
            : ['ok' => false, 'error' => 'No se pudo aplicar el pago.'];
    }

    /**
     * Rechaza un pago en revisión — no mueve cuotas.
     * @return array{ok: bool, error?: string}
     */
    public function rechazarPago(int $tenantId, int $pagoId, int $userId): array
    {
        $pago = $this->pagos->where('tenant_id', $tenantId)->find($pagoId);
        if (!$pago || $pago['estado'] !== PagoModel::REVISION) {
            return ['ok' => false, 'error' => 'Pago no encontrado o ya fue resuelto.'];
        }
        $this->pagos->update($pagoId, [
            'estado'       => PagoModel::RECHAZADO,
            'aprobado_por' => $userId,
            'resuelto_at'  => date('Y-m-d H:i:s'),
        ]);
        return ['ok' => true];
    }

    /**
     * Métricas de la bandeja para las stat-cards:
     * revisión (cobrable ahora), promesas (revisión con fecha futura),
     * aplicados y revertidos.
     */
    public function metricas(int $tenantId): array
    {
        $agrupar = static fn(array $rows): array =>
            ['n' => count($rows), 'monto' => round(array_sum(array_column($rows, 'monto')), 2)];

        $revision = $this->pagos->where('tenant_id', $tenantId)
            ->where('estado', PagoModel::REVISION)
            ->where('tipo', PagoModel::TIPO_PAGO)->findAll();
        $promesas = $this->pagos->where('tenant_id', $tenantId)
            ->where('estado', PagoModel::REVISION)
            ->where('tipo', PagoModel::TIPO_PROMESA)->findAll();

        return [
            'revision'   => $agrupar($revision),
            'aplicados'  => $agrupar($this->pagos->where('tenant_id', $tenantId)
                ->where('estado', PagoModel::APLICADO)->findAll()),
            'revertidos' => $agrupar($this->pagos->where('tenant_id', $tenantId)
                ->where('estado', PagoModel::REVERTIDO)->findAll()),
            'promesas'   => $agrupar($promesas),
        ];
    }

    /** Créditos activos para el selector "registrar pago" — con su próxima cuota pendiente. */
    public function creditosParaAbono(int $tenantId): array
    {
        $rows = $this->solicitudes
            ->select('solicitudes.*, personas.nombres, personas.apellidos')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->where('solicitudes.tenant_id', $tenantId)
            ->where('solicitudes.estado', SolicitudModel::ACTIVO)
            ->orderBy('personas.apellidos', 'ASC')
            ->findAll();

        foreach ($rows as &$r) {
            $prox = $this->proximaCuota((int) $r['id']);
            $r['cuota_id']  = $prox['id'] ?? null;
            $r['pendiente'] = $prox ? round((float) $prox['cuota'] - (float) $prox['pagado'], 2) : 0;
            // Sugerencia: pendiente de la cuota, o la cuota del plan si no hay pendiente
            $r['sugerido']  = $r['pendiente'] ?: (float) $this->solSvc->planPagos($r)['cuota'];
        }
        return $rows;
    }

    /** Bandeja de aprobación + últimos resueltos para el listado /pagos.
     *  Filtros: desde/hasta (en revisión por fecha_hora, resueltos por resuelto_at). */
    public function bandeja(int $tenantId, array $f = []): array
    {
        $desde = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['desde'] ?? '')) ? $f['desde'] : null;
        $hasta = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($f['hasta'] ?? '')) ? $f['hasta'] : null;

        $revision = $this->pagos->enRevision($tenantId);
        if ($desde || $hasta) {
            $revision = array_values(array_filter($revision, fn($p) =>
                (!$desde || substr((string) $p['fecha_hora'], 0, 10) >= $desde)
                && (!$hasta || substr((string) $p['fecha_hora'], 0, 10) <= $hasta)));
        }

        $recientes = $this->pagos
            ->select('pagos.*, personas.nombres, personas.apellidos, solicitudes.codigo_credito,
                      cobrador.nombres AS cob_nombres, cobrador.apellidos AS cob_apellidos')
            ->join('solicitudes', 'solicitudes.id = pagos.solicitud_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = pagos.empleado_id', 'left')
            ->join('personas cobrador', 'cobrador.id = emp.persona_id', 'left')
            ->where('pagos.tenant_id', $tenantId)
            ->whereIn('pagos.estado', [PagoModel::APLICADO, PagoModel::RECHAZADO, PagoModel::REVERTIDO])
            ->where('pagos.tipo', PagoModel::TIPO_PAGO);
        if ($desde) $recientes->where('pagos.resuelto_at >=', $desde . ' 00:00:00');
        if ($hasta) $recientes->where('pagos.resuelto_at <=', $hasta . ' 23:59:59');
        $recientes = $recientes->orderBy('pagos.resuelto_at', 'DESC')->findAll(15);

        return [
            'revision'  => $revision,
            'recientes' => $recientes,
            'promesas'  => $this->pagos->promesas($tenantId),
        ];
    }

    // ---------------------------------------------------------------
    // Portal gestor — cobros de su cartera
    // ---------------------------------------------------------------

    /**
     * Créditos activos asignados al gestor con cuotas pendientes y
     * cuántos pagos tiene esperando aprobación.
     */
    public function cobrosDelGestor(int $tenantId, int $empleadoId): array
    {
        $creditos = $this->solicitudes
            ->select('solicitudes.id, solicitudes.codigo_credito, solicitudes.frecuencia_aprobada,
                      solicitudes.cliente_id,
                      solicitudes.monto_aprobado, solicitudes.monto, solicitudes.tasa_aprobada,
                      solicitudes.tasa_mensual, solicitudes.plazo_aprobado, solicitudes.plazo_meses,
                      solicitudes.frecuencia, solicitudes.dias_semana, solicitudes.fecha_primer_pago,
                      personas.nombres, personas.apellidos, personas.telefono, clientes.codigo')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->where('solicitudes.tenant_id', $tenantId)
            ->where('solicitudes.asignado_a', $empleadoId)
            ->where('solicitudes.estado', SolicitudModel::ACTIVO)
            ->orderBy('personas.apellidos', 'ASC')
            ->findAll();

        $hoy = date('Y-m-d');
        foreach ($creditos as &$cr) {
            $solId = (int) $cr['id'];
            $pend  = $this->cuotas->pendientesDe($tenantId, [$solId]);
            $cr['cuotas_pend'] = array_map(function ($c) use ($hoy) {
                $c['pendiente'] = max(0, round((float) $c['cuota'] - (float) $c['pagado'], 2));
                $c['vencida']   = $c['fecha_vence'] < $hoy;
                return $c;
            }, $pend);
            $cr['en_revision'] = (int) $this->pagos
                ->where('solicitud_id', $solId)
                ->where('estado', PagoModel::REVISION)
                ->where('tipo', PagoModel::TIPO_PAGO)
                ->where('monto >', 0)
                ->where('revierte_id IS NULL', null, false)
                ->countAllResults();
            $cr['saldo'] = $this->saldoCredito($cr);
            // Monto sugerido: pendiente de la cuota próxima; si el crédito
            // aún no tiene cuotas (activado antes del plan), la cuota del plan.
            $cr['cuota_sugerida'] = $cr['cuotas_pend'][0]['pendiente']
                ?? round((float) $this->solSvc->planPagos($cr)['cuota'], 2);
        }
        return $creditos;
    }

    /**
     * Cobros recepcionados hoy por el gestor — todo lo que él cobró,
     * en positivo y cualquier estado (revisión/aplicado/rechazado/revertido).
     * Los contra-pagos de reversión (negativos) no le pertenecen: son
     * movimientos internos del sistema.
     */
    public function cobrosHoyDelGestor(int $tenantId, int $empleadoId): array
    {
        $rows = $this->pagos
            ->select('pagos.*, personas.nombres, personas.apellidos, solicitudes.codigo_credito,
                      cobrador.nombres AS cob_nombres, cobrador.apellidos AS cob_apellidos')
            ->join('solicitudes', 'solicitudes.id = pagos.solicitud_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = pagos.empleado_id', 'left')
            ->join('personas cobrador', 'cobrador.id = emp.persona_id', 'left')
            ->where('pagos.tenant_id', $tenantId)
            ->where('pagos.empleado_id', $empleadoId)
            ->where('pagos.tipo', PagoModel::TIPO_PAGO)
            ->where('pagos.monto >', 0)
            // prefijo explícito: los strings raw no pasan por protectIdentifiers
            ->where($this->db->prefixTable('pagos') . '.revierte_id IS NULL', null, false)
            ->where('DATE(' . $this->db->prefixTable('pagos') . '.fecha_hora)', date('Y-m-d'))
            ->orderBy('pagos.fecha_hora', 'DESC')
            ->findAll();

        foreach ($rows as &$p) {
            $saldo = $this->cuotas->saldoDe((int) $p['solicitud_id']);
            // REVISION aún no se aplica → el saldo mostrado descuenta este pago
            if (($p['estado'] ?? '') === PagoModel::REVISION) {
                $saldo -= (float) $p['monto'];
            }
            $p['saldo']  = max(0, round($saldo, 2));
            $p['gestor'] = trim(($p['cob_nombres'] ?? '') . ' ' . ($p['cob_apellidos'] ?? ''));
        }
        return $rows;
    }

    /**
     * Pagos del día desglosados por capital / interés / mora.
     * APLICADO: desglose real según pago_aplicaciones — cada aplicación
     * 'CUOTA' se reparte en la proporción capital:interés de su cuota;
     * 'MORA' va a columna aparte.
     * REVISION (aún sin aplicar): desglose estimado por la proporción
     * de la primera cuota pendiente del crédito (marcado `estimado`).
     * @return array{rows: array, totales: array}
     */
    public function pagosDelDia(int $tenantId, string $fecha): array
    {
        $pagos = $this->pagos
            ->select('pagos.*, personas.nombres, personas.apellidos, solicitudes.codigo_credito,
                      gp.nombres AS gestor_n, gp.apellidos AS gestor_a')
            ->join('solicitudes', 'solicitudes.id = pagos.solicitud_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = pagos.empleado_id', 'left')
            ->join('personas gp', 'gp.id = emp.persona_id', 'left')
            ->where('pagos.tenant_id', $tenantId)
            ->where('pagos.tipo', PagoModel::TIPO_PAGO)
            ->where('pagos.monto >', 0)
            ->whereIn('pagos.estado', [PagoModel::REVISION, PagoModel::APLICADO])
            ->where('DATE(' . $this->db->prefixTable('pagos') . '.fecha_hora)', $fecha)
            ->orderBy('pagos.fecha_hora', 'ASC')
            ->findAll();

        if (!$pagos) {
            return ['rows' => [], 'totales' => ['n' => 0, 'capital' => 0, 'interes' => 0, 'mora' => 0, 'total' => 0]];
        }

        $appsPor = $this->aplicacionesPorPago($tenantId, array_column($pagos, 'id'));

        $rows = [];
        $tot  = ['n' => 0, 'capital' => 0, 'interes' => 0, 'mora' => 0, 'total' => 0];
        foreach ($pagos as $p) {
            $apps = $appsPor[(int) $p['id']] ?? [];
            [$cap, $int, $mora, $estimado] = $this->splitPago($p, $apps);

            $rows[] = $p + [
                'capital'  => $cap, 'interes' => $int, 'mora' => $mora,
                'estimado' => $estimado,
            ];
            $tot['n']++;
            $tot['capital'] += $cap;  $tot['interes'] += $int;
            $tot['mora']    += $mora; $tot['total']   += (float) $p['monto'];
        }
        foreach ($tot as $k => $v) $tot[$k] = is_int($v) ? $v : round($v, 2);
        return ['rows' => $rows, 'totales' => $tot];
    }

    /** Aplicaciones de varios pagos, indexadas por pago_id (con datos de cuota). */
    private function aplicacionesPorPago(int $tenantId, array $pagoIds): array
    {
        $apps = $this->db->table('pago_aplicaciones a')
            ->select('a.pago_id, a.monto, a.tipo, c.cuota, c.capital, c.interes')
            ->join('cuotas c', 'c.id = a.cuota_id')
            ->where('a.tenant_id', $tenantId)
            ->whereIn('a.pago_id', $pagoIds)
            ->get()->getResultArray();
        $por = [];
        foreach ($apps as $a) $por[(int) $a['pago_id']][] = $a;
        return $por;
    }

    /**
     * Desglose de un pago → [capital, interés, mora, estimado].
     * Sin aplicaciones (REVISION) estima con la primera cuota pendiente.
     */
    private function splitPago(array $pago, array $apps): array
    {
        $cap = $int = $mora = 0;
        foreach ($apps as $a) {
            $m = (float) $a['monto'];
            if ($a['tipo'] === 'MORA') { $mora += $m; continue; }
            $cuota = (float) $a['cuota'];
            $parte = $cuota > 0 ? min($m, round($m * (float) $a['interes'] / $cuota, 2)) : 0;
            $int += $parte;
            $cap += $m - $parte;
        }
        if ($apps || $pago['estado'] !== PagoModel::REVISION) {
            return [round($cap, 2), round($int, 2), round($mora, 2), false];
        }

        // Estimado: mismo ratio interés/cuota de la primera cuota pendiente
        $pend = $this->cuotas
            ->where('solicitud_id', (int) $pago['solicitud_id'])
            ->where('estado !=', CuotaModel::ANULADA)
            ->where('cuota > pagado + COALESCE(descuento, 0)', null, false)
            ->orderBy('fecha_vence')->orderBy('n')
            ->first();
        $ratio = $pend && (float) $pend['cuota'] > 0
            ? (float) $pend['interes'] / (float) $pend['cuota'] : 0;
        $int = min((float) $pago['monto'], round((float) $pago['monto'] * $ratio, 2));
        return [round((float) $pago['monto'] - $int, 2), $int, 0.0, true];
    }
}
