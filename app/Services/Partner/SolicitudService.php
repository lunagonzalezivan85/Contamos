<?php

namespace App\Services\Partner;

use App\Models\ClienteModel;
use App\Models\EmpleadoModel;
use App\Models\PersonaDetalleModel;
use App\Models\SolicitudAnalisisModel;
use App\Models\SolicitudHistorialModel;
use App\Models\SolicitudModel;
use App\Models\TenantModel;

/**
 * Lógica de negocio de solicitudes de crédito del tenant.
 * El controller solo maneja HTTP (sesión, validación de form, redirect, vista).
 */
class SolicitudService
{
    private const POR_PAGINA = 15;

    public const LBL_FREQ = ['D' => 'Diario', 'DI' => 'Diario intermitente', 'S' => 'Semanal', 'Q' => 'Quincenal', 'M' => 'Mensual', 'P' => 'Personalizado'];

    private SolicitudModel           $solicitudes;
    private SolicitudAnalisisModel   $analisis;
    private SolicitudHistorialModel  $historial;
    private EmpleadoModel            $empleados;
    private TenantModel              $tenants;

    public function __construct()
    {
        $this->solicitudes = new SolicitudModel();
        $this->analisis    = new SolicitudAnalisisModel();
        $this->historial   = new SolicitudHistorialModel();
        $this->empleados   = new EmpleadoModel();
        $this->tenants     = new TenantModel();
    }

    // ---------------------------------------------------------------
    // Lectura
    // ---------------------------------------------------------------

    /** Listado con filtros + paginación (el pager queda en la instancia del modelo). */
    public function listar(int $tenantId, array $f): array
    {
        // Estado filtrado solo si es válido
        if ($f['estado'] !== '' && !in_array($f['estado'], SolicitudModel::ESTADOS, true)) {
            $f['estado'] = '';
        }

        $model = new SolicitudModel(); // instancia fresca: el pager vive en ella
        $filas = $model->filtrar($tenantId, $f)->paginate(self::POR_PAGINA);
        $model->pager->only(['q', 'estado', 'gestor', 'ruta', 'desde', 'hasta']);

        return ['filas' => $filas, 'pager' => $model->pager, 'f' => $f];
    }

    /** Opciones para los filtros del listado: gestores activos y rutas distintas. */
    public function opcionesFiltro(int $tenantId): array
    {
        $rutas = $this->empleados
            ->distinct()
            ->select('ruta')
            ->where('tenant_id', $tenantId)
            ->where('ruta IS NOT NULL')
            ->where("ruta != ''", null, false)
            ->orderBy('ruta', 'ASC')
            ->findAll();

        return [
            'gestores' => $this->gestoresActivos($tenantId),
            'rutas'    => array_column($rutas, 'ruta'),
        ];
    }

    public function detalle(int $tenantId, int $id): ?array
    {
        return $this->solicitudes->detalle($tenantId, $id);
    }

    public function find(int $tenantId, int $id): ?array
    {
        return $this->solicitudes->where('tenant_id', $tenantId)->find($id);
    }

    /** Permisos del usuario por estado destino, a partir de la sesión. */
    public function permisosPorEstado(array $permisos): array
    {
        $puede = [];
        foreach (SolicitudModel::PERMISO_ESTADO as $estado => $perm) {
            $puede[$estado] = in_array($perm, $permisos, true);
        }
        return $puede;
    }

    public function analisisDe(int $tenantId, int $solicitudId): ?array
    {
        return $this->analisis->deSolicitud($tenantId, $solicitudId);
    }

    public function moneda(int $tenantId): string
    {
        return (string) ($this->tenants->find($tenantId)['moneda'] ?? 'C$');
    }

    public function plazoMax(int $tenantId): int
    {
        return (int) ($this->tenants->find($tenantId)['plazo_meses_max'] ?? 0);
    }

    /** Gestores activos del tenant (para asignar solicitudes). */
    public function gestoresActivos(int $tenantId): array
    {
        return $this->empleados
            ->select('empleados.id, personas.nombres, personas.apellidos, empleados.ruta')
            ->join('personas', 'personas.id = empleados.persona_id')
            ->where('empleados.tenant_id', $tenantId)
            ->where('empleados.estado', 'ACTIVO')
            ->orderBy('personas.apellidos', 'ASC')
            ->findAll();
    }

    /**
     * Solicitud de crédito creada desde oficina (estado CREADA).
     * La tasa sale de la config del tenant; el tope es el límite de crédito
     * del cliente (default 10,000 — el gestor puede subirlo en la ficha).
     * @return array{ok: bool, id?: int, error?: string}
     */
    public function crearOficina(int $tenantId, array $d): array
    {
        $cli = (new ClienteModel())->where('tenant_id', $tenantId)
            ->find((int) ($d['cliente_id'] ?? 0));
        if (!$cli) {
            return ['ok' => false, 'error' => 'Seleccione un cliente.'];
        }

        $monto = (float) ($d['monto'] ?? 0);
        if ($monto < 1000) {
            return ['ok' => false, 'error' => 'El monto mínimo a prestar es 1,000.'];
        }
        $limite = (float) ($cli['limite_credito'] ?? 0) > 0 ? (float) $cli['limite_credito'] : 10000.00;
        if ($monto > $limite) {
            return ['ok' => false, 'error' => 'El monto supera el límite de crédito del cliente ('
                . number_format($limite, 0) . '). Auméntelo en la ficha del cliente.'];
        }

        $tenant   = $this->tenant($tenantId);
        $plazoMax = (int) ($tenant['plazo_meses_max'] ?? 0);
        $plazo    = ($d['plazo_meses'] ?? '') !== '' ? (int) $d['plazo_meses'] : null;
        if ($plazo !== null && $plazoMax > 0 && $plazo > $plazoMax) {
            return ['ok' => false, 'error' => "El plazo máximo permitido es {$plazoMax} meses."];
        }

        $asignado = (int) ($d['asignado_a'] ?? 0) ?: null;
        $ruta     = null;
        if ($asignado !== null) {
            $emp = $this->empleados->where('tenant_id', $tenantId)
                ->where('estado', 'ACTIVO')->find($asignado);
            if (!$emp) {
                return ['ok' => false, 'error' => 'El gestor asignado no es válido.'];
            }
            $ruta = $emp['ruta'] ?? null;
        }

        $tipo = (string) ($d['tipo_calculo'] ?? 'FLAT');
        if (!isset(self::TIPOS_CALCULO[$tipo])) $tipo = 'FLAT';
        $fechaPp = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['fecha_primer_pago'] ?? ''))
            ? (string) $d['fecha_primer_pago'] : null;

        // Anti-duplicado server-side: mismo cliente + mismo monto en los
        // últimos 5 min = reenvío del form (doble-clic / retry). Devolvemos
        // la solicitud ya creada en vez de insertar otra.
        $dup = $this->solicitudes->where('tenant_id', $tenantId)
            ->where('cliente_id', (int) $cli['id'])
            ->where('monto', $monto)
            ->whereNotIn('estado', [SolicitudModel::RECHAZADA, SolicitudModel::LIQUIDADO])
            ->where('created_at >=', date('Y-m-d H:i:s', time() - 300))
            ->orderBy('id', 'DESC')->first();
        if ($dup) {
            return ['ok' => true, 'id' => (int) $dup['id'], 'duplicada' => true];
        }

        $freq = (string) ($d['frecuencia'] ?? 'M');
        $id   = (int) $this->solicitudes->insert([
            'tenant_id'         => $tenantId,
            'cliente_id'        => (int) $cli['id'],
            'asignado_a'        => $asignado,
            'ruta'              => $ruta,
            'monto'             => $monto,
            'plazo_meses'       => $plazo,
            'frecuencia'        => $freq,
            'tasa_mensual'      => (float) ($tenant['tasa_interes'] ?? 0),
            'dias_semana'       => $freq === 'DI' ? (int) ($d['dias_semana'] ?? 0) : null,
            'destino'           => trim((string) ($d['destino'] ?? '')) ?: null,
            'tipo_calculo'      => $tipo,
            'fecha_primer_pago' => $fechaPp,
            'estado'            => SolicitudModel::CREADA,
        ]);

        if ($id > 0) {
            $this->historial->registrar($tenantId, $id, SolicitudHistorialModel::CREADO,
                SolicitudModel::CREADA, null, $this->actorOficina());
            return ['ok' => true, 'id' => $id];
        }
        return ['ok' => false, 'error' => 'No se pudo guardar la solicitud.'];
    }

    /** Detalles del expediente del cliente, agrupados por tipo de detalle. */
    public function seccionesCliente(int $personaId): array
    {
        $secciones = [];
        foreach ((new PersonaDetalleModel())->tipos() as $tipo) {
            try {
                $secciones[$tipo] = $personaId ? (new PersonaDetalleModel($tipo))->dePersona($personaId) : [];
            } catch (\Throwable $e) {
                $secciones[$tipo] = [];   // tabla hija sin migrar en el server
            }
        }
        return $secciones;
    }

    /**
     * Checklist del expediente crediticio del cliente.
     * @return array{0: array, 1: int} [items, cantidad de requeridos faltantes]
     */
    public function checklistCliente(array $sol): array
    {
        $personaId = (int) ($sol['persona_id'] ?? 0);
        $cuenta = static function (string $tipo) use ($personaId): int {
            return $personaId
                ? (int) (new PersonaDetalleModel($tipo))->where('persona_id', $personaId)->countAllResults()
                : 0;
        };
        $items = [
            ['label' => 'Cédula',                       'ok' => !empty($sol['cedula']),     'req' => true],
            ['label' => 'Teléfono',                     'ok' => !empty($sol['telefono']),   'req' => true],
            ['label' => 'Dirección domiciliar',         'ok' => $cuenta('direccion') > 0,   'req' => true],
            ['label' => 'Contactos',                    'ok' => $cuenta('contacto') > 0,    'req' => true],
            ['label' => 'Referencias personales',       'ok' => $cuenta('referencia') > 0,  'req' => true],
            ['label' => 'Ingresos declarados',          'ok' => $cuenta('ingreso') > 0,     'req' => true],
            ['label' => 'Documentos (cédula/contrato)', 'ok' => $cuenta('documento') > 0,   'req' => true],
            ['label' => 'Negocio o actividad',          'ok' => $cuenta('negocio') > 0,     'req' => false],
            ['label' => 'Activos',                      'ok' => $cuenta('activo') > 0,      'req' => false],
            ['label' => 'Pasivos',                      'ok' => $cuenta('pasivo') > 0,      'req' => false],
        ];
        $faltan = count(array_filter($items, fn($i) => !$i['ok'] && $i['req']));
        return [$items, $faltan];
    }

    /** Métricas financieras de la solicitud a partir de su expediente. */
    public function metricasAnalisis(array $sol, array $secciones): array
    {
        $freqPagos = ['D' => 30, 'DI' => 0, 'S' => 4, 'Q' => 2, 'M' => 1];
        $pagosXmes = ($sol['frecuencia'] ?? 'M') === 'DI'
            ? 4 * (int) ($sol['dias_semana'] ?? 3)
            : ($freqPagos[$sol['frecuencia'] ?? 'M'] ?? 1);
        $iP    = ((float) ($sol['tasa_mensual'] ?? 0) / 100) / max($pagosXmes, 0.01);
        $n     = max(1, round(((int) ($sol['plazo_meses'] ?? 0)) * $pagosXmes));
        $cuota = $iP > 0
            ? (float) $sol['monto'] * $iP * (1 + $iP) ** $n / ((1 + $iP) ** $n - 1)
            : (float) $sol['monto'] / $n;

        $sum      = static fn (array $rows, string $col): float =>
            array_sum(array_map(static fn ($r) => (float) ($r[$col] ?? 0), $rows));
        $ingresos = $sum($secciones['ingreso'] ?? [], 'monto');
        $egresos  = $sum($secciones['egreso']  ?? [], 'monto');   // gastos mensuales
        $neto     = $ingresos - $egresos;                          // ingreso disponible
        $activos  = $sum($secciones['activo']  ?? [], 'valor');
        $pasivos  = $sum($secciones['pasivo']  ?? [], 'monto');
        $cuotaMes = $cuota * $pagosXmes;   // lo que pagaría al mes

        return [
            'cuota'      => $cuota,
            'cuota_mes'  => $cuotaMes,
            'pagos'      => $n,
            'ingresos'   => $ingresos,
            'egresos'    => $egresos,
            'neto'       => $neto,
            'activos'    => $activos,
            'pasivos'    => $pasivos,
            'patrimonio' => $activos - $pasivos,
            'ratio'      => $neto > 0 ? $cuotaMes / $neto : null,   // % del ingreso neto comprometido
        ];
    }

    /** Tipos de cálculo del plan de pago. */
    public const TIPOS_CALCULO = [
        'FRANCES'    => 'Cuota fija (francés)',
        'FLAT'       => 'Interés fijo (flat)',
        'ALEMAN'     => 'Cuota decreciente (alemán)',
        'ANTICIPADO' => 'Interés anticipado',
    ];

    /**
     * Plan de pago del préstamo aprobado; si no existe, del solicitado.
     * Tipos: FRANCES (cuota fija), FLAT (interés sobre capital), ALEMAN
     * (capital constante, cuota decreciente), ANTICIPADO (interés descontado
     * del desembolso — cuotas de capital puro).
     * Gracia: TOTAL desplaza el plan | INTERES agrega cuotas de solo interés.
     * Frecuencias: D, DI, S, Q (15 y fin de mes), M, P (cada paso_dias).
     */
    public function planPagos(array $sol): array
    {
        $freq  = (string) ($sol['frecuencia_aprobada'] ?? $sol['frecuencia'] ?? 'M');
        $monto = (float) ($sol['monto_aprobado'] ?? $sol['monto']);
        $tasa  = (float) ($sol['tasa_aprobada'] ?? $sol['tasa_mensual'] ?? 0);
        $plazo = (int) ($sol['plazo_aprobado'] ?? $sol['plazo_meses'] ?? 1);
        $dias  = max(1, (int) ($sol['dias_semana'] ?? 3));
        $tipo  = in_array($sol['tipo_calculo'] ?? '', array_keys(self::TIPOS_CALCULO), true)
            ? (string) $sol['tipo_calculo'] : 'FRANCES';
        $pasoPersonal = max(0, (int) ($sol['paso_dias'] ?? 0));

        // Pagos por mes / número de cuotas / paso entre fechas
        $pagosXmes = match ($freq) {
            'D'  => 30,
            'DI' => 4 * $dias,
            'S'  => 4,
            'Q'  => 2,                                      // quincena real: día 15 y fin de mes
            'P'  => $pasoPersonal > 0 ? 30 / $pasoPersonal : 1,
            default => 1,
        };
        $iP = ($tasa / 100) / max($pagosXmes, 0.01);
        $n  = max(1, (int) round($plazo * $pagosXmes));
        $pasoDias = match ($freq) {
            'D'  => 1, 'S' => 7,
            'DI' => max(1, (int) round(7 / $dias)),
            'P'  => max(1, $pasoPersonal),
            default => 30,
        };

        // Siguiente fecha de cuota según la frecuencia
        $pasoFecha = function (\DateTime $f) use ($freq, $pasoDias): void {
            if ($freq === 'Q') {
                $dia    = (int) $f->format('j');
                $ultimo = (int) $f->format('t');
                if ($dia < 15) {
                    $f->setDate((int) $f->format('Y'), (int) $f->format('n'), 15);
                } elseif ($dia < $ultimo) {
                    $f->setDate((int) $f->format('Y'), (int) $f->format('n'), $ultimo);
                } else {
                    $f->modify('first day of next month')->setDate((int) $f->format('Y'), (int) $f->format('n'), 15);
                }
                return;
            }
            do {
                $f->modify('+' . $pasoDias . ' days');
            } while ($freq === 'DI' && (int) $f->format('N') === 7);   // sin domingo
        };

        // Cuota del FRANCES para amortización (también base del anticipado)
        $cuotaFrances = $iP > 0 ? $monto * $iP * (1 + $iP) ** $n / ((1 + $iP) ** $n - 1) : $monto / $n;

        // Gracia: TOTAL desplaza todo el plan; INTERES agrega cuotas solo-interés
        $graciaMeses = max(0, (int) ($sol['gracia_meses'] ?? 0));
        $graciaTipo  = $graciaMeses > 0 ? (string) ($sol['gracia_tipo'] ?? 'TOTAL') : null;
        $graciaPagos = $graciaTipo === 'INTERES' ? (int) round($graciaMeses * $pagosXmes) : 0;

        $inicio = new \DateTime((string) ($sol['fecha_primer_pago'] ?? date('Y-m-d')));
        if ($graciaTipo === 'TOTAL') {
            $inicio->modify('+' . $graciaMeses . ' months');
        }

        $esAnticipado = $tipo === 'ANTICIPADO';
        $fecha = clone $inicio;
        $saldo = $monto;
        $rows  = [];

        // Cuotas de gracia — solo interés, el capital no se mueve
        for ($g = 1; $g <= $graciaPagos; $g++) {
            $int    = round($monto * $iP, 2);
            $rows[] = [
                'n' => $g, 'fecha' => $fecha->format('Y-m-d'), 'gracia' => true,
                'cuota' => $int, 'interes' => $int, 'capital' => 0, 'saldo' => $saldo,
            ];
            $pasoFecha($fecha);
        }

        for ($i = $graciaPagos + 1; $i <= $graciaPagos + $n; $i++) {
            $last = $i === $graciaPagos + $n;
            switch ($tipo) {
                case 'FLAT':
                    $cap    = round($monto / $n, 2);
                    $int    = round($monto * $iP, 2);
                    $cuotaI = $last ? round($saldo + $int, 2) : round($cap + $int, 2);
                    break;
                case 'ALEMAN':
                    $cap    = $last ? $saldo : round($monto / $n, 2);
                    $int    = round($saldo * $iP, 2);
                    $cuotaI = round($cap + $int, 2);
                    break;
                case 'ANTICIPADO':
                    $cap    = $last ? $saldo : round($monto / $n, 2);
                    $int    = 0;                        // el interés se cobra por adelantado
                    $cuotaI = round($cap, 2);
                    break;
                default: // FRANCES
                    $int    = round($saldo * $iP, 2);
                    $cap    = $last ? $saldo : round($cuotaFrances - $int, 2);
                    $cuotaI = $last ? round($cap + $int, 2) : round($cuotaFrances, 2);
            }
            $saldo  = round($saldo - $cap, 2);
            $rows[] = [
                'n' => $i, 'fecha' => $fecha->format('Y-m-d'),
                'cuota' => $cuotaI, 'interes' => $int, 'capital' => $cap, 'saldo' => max($saldo, 0),
            ];
            $pasoFecha($fecha);
        }

        // Interés del anticipado = el que habría pagado con francés (referencia)
        $interesAnticipado = $esAnticipado
            ? round($cuotaFrances * $n - $monto, 2) : 0;

        return [
            'monto' => $monto, 'tasa' => $tasa, 'plazo' => $plazo, 'freq' => $freq, 'tipo' => $tipo,
            'cuota' => round($esAnticipado ? $monto / $n : ($tipo === 'ALEMAN' ? ($rows[$graciaPagos]['cuota'] ?? $cuotaFrances) : $cuotaFrances), 2),
            'pagos' => $graciaPagos + $n,
            'total' => round(array_sum(array_column($rows, 'cuota')), 2),
            'intereses' => round(array_sum(array_column($rows, 'interes')), 2) + $interesAnticipado,
            'interes_anticipado' => $interesAnticipado,
            'neto' => round($monto - $interesAnticipado, 2),   // desembolso neto si ANTICIPADO
            'gracia' => ['meses' => $graciaMeses, 'tipo' => $graciaTipo, 'cuotas' => $graciaPagos],
            'fecha_inicio' => (string) ($sol['fecha_primer_pago'] ?? date('Y-m-d')),
            'fecha_fin'    => $rows[count($rows) - 1]['fecha'],
            'aprobado'     => !empty($sol['monto_aprobado']),
            'rows'         => $rows,
        ];
    }

    /** Fecha sugerida del primer pago según la frecuencia. */
    public function primerPagoSugerido(string $freq): string
    {
        $dias = ['D' => 1, 'DI' => 1, 'S' => 7, 'Q' => 15, 'M' => 30][$freq] ?? 30;
        return date('Y-m-d', strtotime('+' . $dias . ' days'));
    }

    /** Verifica que el empleado pertenezca al tenant y esté activo. */
    public function esGestorDelTenant(int $tenantId, int $empleadoId): bool
    {
        return (bool) $this->empleados
            ->where('id', $empleadoId)
            ->where('tenant_id', $tenantId)
            ->where('estado', 'ACTIVO')
            ->countAllResults();
    }

    public function tenant(int $tenantId): ?array
    {
        return $this->tenants->find($tenantId);
    }

    // ---------------------------------------------------------------
    // Escritura / transiciones
    // ---------------------------------------------------------------

    /**
     * Reglas de transición de estado. Devuelve:
     *   ['ok' => true]                    → ejecuta el update de estado
     *   ['ok' => false, 'error' => '…']   → motivo para flash
     *   ['goto' => 'aprobar'|'desembolsar'] → la acción vive en su pantalla dedicada
     */
    public function validarTransicion(array $sol, string $nuevo, array $permisos): array
    {
        if (!in_array($nuevo, SolicitudModel::ESTADOS, true)) {
            return ['ok' => false, 'error' => 'Estado inválido.'];
        }
        $permitidos = SolicitudModel::PERMISO_ESTADO;
        if (!isset($permitidos[$nuevo])) {
            return ['ok' => false, 'error' => 'No se puede pasar a ese estado.'];
        }
        if (in_array($sol['estado'], [SolicitudModel::DESEMBOLSO, SolicitudModel::ACTIVO, SolicitudModel::RECHAZADA], true)) {
            return ['ok' => false, 'error' => 'La solicitud ya está cerrada.'];
        }
        // La aprobación y el desembolso van por sus pantallas dedicadas
        if ($nuevo === SolicitudModel::APROBADA)    return ['goto' => 'aprobar'];
        if ($nuevo === SolicitudModel::DESEMBOLSO)  return ['goto' => 'desembolsar'];
        // A CREADA solo se llega desde CONTACTO (tomar solicitud web)
        if ($nuevo === SolicitudModel::CREADA && $sol['estado'] !== SolicitudModel::CONTACTO) {
            return ['ok' => false, 'error' => 'No se puede pasar a ese estado.'];
        }
        if (!in_array($permitidos[$nuevo], $permisos, true)) {
            return ['ok' => false, 'error' => 'No tenés permiso para esta acción.'];
        }
        return ['ok' => true];
    }

    /**
     * Mueve la solicitud de estado + timeline.
     * $nota = observación al mandar a REVISION — queda en `nota_revision`
     * para que el gestor la vea (portal + app) y en el historial para siempre.
     */
    public function moverEstado(array $sol, string $nuevo, ?string $nota = null): void
    {
        $set = ['estado' => $nuevo];
        if ($nuevo === SolicitudModel::REVISION) {
            $set['nota_revision'] = ($nota !== null && $nota !== '') ? $nota : null;
        } elseif (($sol['estado'] ?? '') === SolicitudModel::REVISION) {
            $set['nota_revision'] = null;   // salió de revisión — la nota vive en el historial
        }
        $this->solicitudes->update((int) $sol['id'], $set);

        $this->historial->registrar((int) $sol['tenant_id'], (int) $sol['id'],
            SolicitudHistorialModel::ESTADO, $nuevo, $nota, $this->actorOficina());
    }

    /**
     * Guarda préstamo aprobado + fecha primer pago y pasa a APROBADA.
     * (La validación de formato la hace el controller con $this->validate.)
     * @return array{ok: bool, error?: string}
     */
    public function guardarAprobacion(int $tenantId, array $sol, array $d): array
    {
        $plazoMax = $this->plazoMax($tenantId);
        $plazoA   = (int) $d['plazo_aprobado'];
        if ($plazoMax > 0 && $plazoA > $plazoMax) {
            return ['ok' => false, 'error' => "El plazo aprobado no puede superar {$plazoMax} meses (parámetro del tenant)."];
        }
        if ((string) $d['fecha_primer_pago'] < date('Y-m-d')) {
            return ['ok' => false, 'error' => 'La fecha del primer pago no puede ser pasada.'];
        }

        // Gestor asignado — puede cambiarse al aprobar
        $asignado = (int) ($d['asignado_a'] ?? 0);
        if ($asignado <= 0 || !$this->esGestorDelTenant($tenantId, $asignado)) {
            $asignado = (int) ($sol['asignado_a'] ?? 0) ?: null;
        }

        $freqA      = (string) $d['frecuencia_aprobada'];
        $diasSemana = $freqA === 'DI'
            ? max(1, min(7, (int) ($d['dias_semana'] ?: 3)))
            : ($sol['dias_semana'] ?? null);
        $pasoDias   = $freqA === 'P'
            ? max(1, (int) ($d['paso_dias'] ?: 15)) : null;

        // Tipo de cálculo — override del default del tenant
        $tipoCalc = (string) ($d['tipo_calculo'] ?? '');
        if (!isset(self::TIPOS_CALCULO[$tipoCalc])) {
            $tipoCalc = (string) ($this->tenants->find($tenantId)['tipo_calculo'] ?? 'FRANCES');
        }

        // Gracia (opcional)
        $graciaMeses = max(0, min(24, (int) ($d['gracia_meses'] ?? 0)));
        $graciaTipo  = $graciaMeses > 0
            ? (in_array($d['gracia_tipo'] ?? '', ['TOTAL', 'INTERES'], true) ? $d['gracia_tipo'] : 'TOTAL')
            : null;

        // Comisión y seguro cobrados al aprobar (opcionales por tenant);
        // se descuentan del monto a entregar en el desembolso.
        $montoA = (float) $d['monto_aprobado'];
        $comision = ($d['comision'] ?? '') !== ''
            ? max(0, (float) $d['comision'])
            : round($montoA * (float) ($this->tenants->find($tenantId)['comision_pct'] ?? 0) / 100, 2);
        $seguro = ($d['seguro'] ?? '') !== ''
            ? max(0, (float) $d['seguro'])
            : round($montoA * (float) ($this->tenants->find($tenantId)['seguro_pct'] ?? 0) / 100, 2);

        $this->solicitudes->update((int) $sol['id'], [
            'asignado_a'          => $asignado,
            'monto_aprobado'      => $montoA,
            'tasa_aprobada'       => (float) $d['tasa_aprobada'],
            'plazo_aprobado'      => $plazoA,
            'frecuencia_aprobada' => $freqA,
            'dias_semana'         => $diasSemana,
            'paso_dias'           => $pasoDias,
            'tipo_calculo'        => $tipoCalc,
            'gracia_meses'        => $graciaMeses,
            'gracia_tipo'         => $graciaTipo,
            'comision'            => $comision,
            'seguro'              => $seguro,
            'fecha_primer_pago'   => (string) $d['fecha_primer_pago'],
            'estado'              => SolicitudModel::APROBADA,
        ]);

        $this->historial->registrar($tenantId, (int) $sol['id'],
            SolicitudHistorialModel::ESTADO, SolicitudModel::APROBADA, null, $this->actorOficina());

        return ['ok' => true];
    }

    /** Programa la fecha de desembolso y pasa a DESEMBOLSO. */
    public function guardarDesembolso(array $sol, string $fecha): void
    {
        $this->solicitudes->update((int) $sol['id'], [
            'fecha_desembolso' => $fecha,
            'estado'           => SolicitudModel::DESEMBOLSO,
        ]);
        $this->historial->registrar((int) $sol['tenant_id'], (int) $sol['id'],
            SolicitudHistorialModel::ESTADO, SolicitudModel::DESEMBOLSO, null, $this->actorOficina());
    }

    /**
     * Calcula y persiste el análisis financiero de la solicitud.
     * @return array{ok: bool, nivel?: string, error?: string}
     */
    public function calcularAnalisis(int $tenantId, array $sol): array
    {
        [, $faltan] = $this->checklistCliente($sol);
        if ($faltan > 0) {
            return ['ok' => false, 'error' => "Complete el expediente del cliente (faltan {$faltan} requeridos) para calcular el análisis."];
        }

        $m     = $this->metricasAnalisis($sol, $this->seccionesCliente((int) $sol['persona_id']));
        $nivel = $m['ratio'] === null
            ? 'SIN_DATOS'
            : ($m['ratio'] <= 0.30 ? 'BUENO' : ($m['ratio'] <= 0.50 ? 'AJUSTADO' : 'RIESGO'));

        $data = [
            'tenant_id'    => $tenantId,
            'solicitud_id' => (int) $sol['id'],
            'cliente_id'   => (int) $sol['cliente_id'],
            'ingresos'     => $m['ingresos'],
            'cuota'        => $m['cuota'],
            'cuota_mes'    => $m['cuota_mes'],
            'activos'      => $m['activos'],
            'pasivos'      => $m['pasivos'],
            'patrimonio'   => $m['patrimonio'],
            'ratio'        => $m['ratio'],
            'nivel'        => $nivel,
        ];

        $existe = $this->analisis->deSolicitud($tenantId, (int) $sol['id']);
        if ($existe) {
            $this->analisis->update((int) $existe['id'], $data);
        } else {
            $this->analisis->insert($data);
        }

        return ['ok' => true, 'nivel' => $nivel];
    }

    /**
     * Actualiza la solicitud (solo estados editables: CONTACTO|CREADA|REVISION).
     * (La validación de formato la hace el controller con $this->validate.)
     * @return array{ok: bool, error?: string}
     */
    public function actualizar(int $tenantId, array $sol, array $d): array
    {
        $freq = (string) $d['frecuencia'];
        $dias = $d['dias_semana'];
        if ($freq === 'DI' && !(int) $dias) {
            return ['ok' => false, 'error' => 'Indique los días de pago por semana para frecuencia diaria intermitente.'];
        }

        $plazoMax = $this->plazoMax($tenantId);
        $plazo    = ($d['plazo_meses'] ?? '') !== '' ? (int) $d['plazo_meses'] : null;
        if ($plazo !== null && $plazoMax > 0 && $plazo > $plazoMax) {
            return ['ok' => false, 'error' => "El plazo máximo permitido es {$plazoMax} meses."];
        }

        // Si se reasigna el gestor, la ruta se sincroniza con la del nuevo gestor
        $asignado = (int) ($d['asignado_a'] ?? 0);
        $ruta     = $sol['ruta'];
        if ($asignado && $asignado !== (int) $sol['asignado_a']) {
            $emp  = $this->empleados->where('tenant_id', $tenantId)->find($asignado);
            $ruta = $emp['ruta'] ?? null;
        }

        $tipo = (string) ($d['tipo_calculo'] ?? '');
        if (!isset(self::TIPOS_CALCULO[$tipo])) $tipo = $sol['tipo_calculo'] ?: 'FLAT';
        $fechaPp = preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['fecha_primer_pago'] ?? ''))
            ? (string) $d['fecha_primer_pago'] : null;

        $this->solicitudes->update((int) $sol['id'], [
            'monto'             => (float) $d['monto'],
            'tasa_mensual'      => ($d['tasa_mensual'] ?? '') !== '' ? (float) $d['tasa_mensual'] : null,
            'plazo_meses'       => $plazo,
            'frecuencia'        => $freq,
            'dias_semana'       => $freq === 'DI' ? (int) $dias : null,
            'destino'           => trim((string) ($d['destino'] ?? '')) ?: null,
            'asignado_a'        => $asignado ?: null,
            'ruta'              => $ruta,
            'tipo_calculo'      => $tipo,
            'fecha_primer_pago' => $fechaPp,
        ]);

        $this->historial->registrar($tenantId, (int) $sol['id'],
            SolicitudHistorialModel::EDITADO, null, null, $this->actorOficina());

        return ['ok' => true];
    }

    /** Timeline de la solicitud (modal "Historial" en el detalle). */
    public function historialDe(int $tenantId, int $solId): array
    {
        return $this->historial->deSolicitud($tenantId, $solId);
    }

    /** Actor de sesión de oficina para el historial ([] si no hay usuario). */
    private function actorOficina(): array
    {
        $uid = (int) session('user_id');
        return $uid > 0
            ? ['user_id' => $uid, 'nombre' => session('nombre') ?: session('username')]
            : [];
    }
}
