<?php

namespace App\Services\Partner;

use App\Models\ArqueoModel;
use App\Models\ClienteModel;
use App\Models\EmpleadoModel;
use App\Models\PersonaDetalleModel;
use App\Models\PersonaModel;
use App\Models\SolicitudModel;
use App\Models\TenantModel;

/**
 * Lógica de negocio del portal del gestor (/{slug}/portal).
 * El controller solo maneja HTTP (sesión, redirect, vista, flash).
 */
class PortalService
{
    private TenantModel      $tenants;
    private EmpleadoModel    $empleados;
    private PersonaModel     $personas;
    private ClienteModel     $clientes;
    private SolicitudModel   $solicitudes;
    private \CodeIgniter\Database\BaseConnection $db;

    public function __construct()
    {
        $this->tenants     = new TenantModel();
        $this->empleados   = new EmpleadoModel();
        $this->personas    = new PersonaModel();
        $this->clientes    = new ClienteModel();
        $this->solicitudes = new SolicitudModel();
        $this->db          = \Config\Database::connect();
    }

    // ---------------------------------------------------------------
    // Contexto / auth del portal
    // ---------------------------------------------------------------

    public function tenantPorSlug(string $slug): ?array
    {
        return $this->tenants->where('slug', $slug)->first();
    }

    /** Tenant por código de app (CONT-####) — vinculación rápida del gestor. */
    public function tenantPorCodigo(string $codigo): ?array
    {
        return $this->tenants->where('app_codigo', $codigo)
            ->where('estado', 'ACTIVO')->first();
    }

    /**
     * Contexto de gestor logueado al portal:
     * [tenant, empleado, persona] o null si la sesión no calza con el slug.
     */
    public function ctxGestor(string $slug): ?array
    {
        $tenant = $this->tenantPorSlug($slug);
        $empId  = (int) session('portal_empleado_id');
        if (!$tenant || !$empId || (int) session('portal_tenant_id') !== (int) $tenant['id']) {
            return null;
        }
        $empleado = $this->empleados->find($empId);
        $persona  = $empleado ? $this->personas->find((int) $empleado['persona_id']) : null;
        return ($empleado && $persona) ? [$tenant, $empleado, $persona] : null;
    }

    public function sesionGestorActiva(array $tenant): bool
    {
        return (int) session('portal_tenant_id') === (int) $tenant['id']
            && (bool) session('portal_empleado_id');
    }

    /**
     * Valida carnet + PIN de un gestor activo del tenant.
     * @return array{ok: bool, empleado?: array, error?: string}
     */
    public function autenticarGestor(array $tenant, string $carnet, string $pin): array
    {
        $empleado = $this->empleados
            ->where('tenant_id', $tenant['id'])
            ->where('carnet', strtoupper(trim($carnet)))
            ->where('pin', trim($pin))
            ->where('estado', 'ACTIVO')
            ->first();

        if (!$empleado) {
            return ['ok' => false, 'error' => 'Carnet o PIN incorrectos.'];
        }

        // El portal es solo para gestores
        if (mb_strpos(mb_strtolower((string) ($empleado['cargo'] ?? '')), 'gestor') === false) {
            return ['ok' => false, 'error' => 'El portal es solo para gestores.'];
        }

        return ['ok' => true, 'empleado' => $empleado];
    }

    /** Empleado por id — la app lo necesita completo (ruta) para crear solicitudes. */
    public function empleadoDeId(int $empleadoId): ?array
    {
        return $this->empleados->find($empleadoId);
    }

    /** Nombre del gestor para mostrar en la app (persona del empleado). */
    public function personaDeEmpleado(int $empleadoId): ?array
    {
        $empleado = $this->empleados->find($empleadoId);
        if (!$empleado) return null;
        $persona = $this->personas->find((int) $empleado['persona_id']);
        return [
            'nombre' => trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? '')),
        ];
    }

    // ---------------------------------------------------------------
    // App móvil (IONIC) — token firmado, sin estado en BD
    // ---------------------------------------------------------------

    /** Token de 30 días para la app: emp.tenant.exp.firma (HMAC). */
    public function tokenApp(int $empleadoId, int $tenantId): string
    {
        $exp  = time() + 60 * 60 * 24 * 30;
        $data = $empleadoId . '.' . $tenantId . '.' . $exp;
        return $data . '.' . $this->firmaApp($data);
    }

    /** Valida token de app; devuelve empleado_id si calza con el tenant. */
    public function tokenAppValido(string $token, int $tenantId): ?int
    {
        $p = explode('.', $token);
        if (count($p) !== 4) return null;
        [$emp, $tid, $exp, $sig] = $p;
        if ((int) $tid !== $tenantId || (int) $exp < time()) return null;
        if (!hash_equals($this->firmaApp($emp . '.' . $tid . '.' . $exp), $sig)) return null;
        return (int) $emp;
    }

    private function firmaApp(string $data): string
    {
        $key = (string) (config('Encryption')->key ?? env('app.baseURL'));
        return rtrim(strtr(base64_encode(hash_hmac('sha256', $data, $key, true)), '+/', '-_'), '=');
    }

    // ---------------------------------------------------------------
    // PWA manifest
    // ---------------------------------------------------------------

    public function manifiesto(array $tenant, string $slug): array
    {
        $icons = [
            ['src' => base_url('public/icons/icon-192.png'), 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => base_url('public/icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any'],
            ['src' => base_url('public/icons/icon-512.png'), 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'maskable'],
        ];
        if (!empty($tenant['logo'])) {
            $ext  = strtolower(pathinfo((string) $tenant['logo'], PATHINFO_EXTENSION));
            $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'svg' => 'image/svg+xml'][$ext] ?? 'image/png';
            $icons[] = ['src' => base_url('public/uploads/logos/' . $tenant['logo']), 'sizes' => 'any', 'type' => $mime];
        }

        return [
            'id'               => $slug . '-portal',
            'name'             => $tenant['nombre'] . ' — Portal de gestores',
            'short_name'       => mb_substr((string) $tenant['nombre'], 0, 24),
            'description'      => 'Portal móvil para gestores de ' . $tenant['nombre'],
            'start_url'        => base_url($slug . '/portal/panel'),
            'scope'            => base_url($slug . '/portal') . '/',
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'theme_color'      => '#30CB9A',
            'background_color' => '#F4F6F9',
            'icons'            => $icons,
        ];
    }

    // ---------------------------------------------------------------
    // Datos para vistas
    // ---------------------------------------------------------------

    public function clientesDeTenant(int $tenantId): array
    {
        return $this->clientes->conPersona($tenantId);
    }

    public function documentosDe(int $personaId): array
    {
        return (new PersonaDetalleModel())->para('documento')->dePersona($personaId);
    }

    /** @return array{solicitudes: array, pager: mixed} */
    public function desembolsosPendientes(int $tenantId, int $empleadoId): array
    {
        $solicitudes = $this->solicitudes
            ->select('solicitudes.*, personas.nombres, personas.apellidos, personas.telefono,
                      personas.direccion, clientes.codigo, clientes.persona_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->where('solicitudes.tenant_id', $tenantId)
            ->where('solicitudes.estado', SolicitudModel::DESEMBOLSO)
            ->where('solicitudes.asignado_a', $empleadoId)
            ->orderBy('solicitudes.fecha_desembolso', 'ASC')
            ->paginate(10);

        // Mejor dirección por persona — preferir la que tenga GPS guardado
        $perIds = array_values(array_filter(array_map(
            fn($s) => (int) ($s['persona_id'] ?? 0), $solicitudes
        )));
        $dirPorPersona = [];
        if ($perIds) {
            $dirs = (new PersonaDetalleModel('direccion'))
                ->whereIn('persona_id', $perIds)
                ->orderBy('(latitud IS NOT NULL)', 'DESC', false)
                ->orderBy('id', 'DESC')
                ->findAll();
            foreach ($dirs as $d) {
                if (!isset($dirPorPersona[$d['persona_id']])) {
                    $dirPorPersona[$d['persona_id']] = $d;
                }
            }
        }
        foreach ($solicitudes as &$s) {
            $s['dir_geo'] = $dirPorPersona[$s['persona_id']] ?? null;
        }
        unset($s);

        return [
            'solicitudes' => $solicitudes,
            'pager' => $this->solicitudes->pager,
        ];
    }

    /** @return array{clientes: array, pager: mixed} — clientes con solicitudes asignadas al gestor */
    public function carteraGestor(int $tenantId, int $empleadoId): array
    {
        $clientes = $this->clientes->filtrar($tenantId)
            ->join('solicitudes s2', 's2.cliente_id = clientes.id')
            ->where('s2.asignado_a', $empleadoId)
            ->groupBy('clientes.id')
            ->paginate(10);

        // Mejor dirección por cliente — preferir la que tenga GPS guardado
        $perIds = array_values(array_filter(array_map(
            fn($c) => (int) ($c['persona_id'] ?? 0), $clientes
        )));
        $dirPorPersona = [];
        if ($perIds) {
            $dirs = (new PersonaDetalleModel('direccion'))
                ->whereIn('persona_id', $perIds)
                ->orderBy('(latitud IS NOT NULL)', 'DESC', false)
                ->orderBy('id', 'DESC')
                ->findAll();
            foreach ($dirs as $d) {
                if (!isset($dirPorPersona[$d['persona_id']])) {
                    $dirPorPersona[$d['persona_id']] = $d;
                }
            }
        }
        foreach ($clientes as &$c) {
            $c['dir_geo'] = $dirPorPersona[$c['persona_id']] ?? null;
        }
        unset($c);

        return [
            'clientes' => $clientes,
            'pager'    => $this->clientes->pager,
        ];
    }

    /** @return array{solicitudes: array, pager: mixed} — solicitudes creadas por el gestor */
    public function actividadGestor(int $tenantId, int $empleadoId): array
    {
        return [
            'solicitudes' => $this->solicitudes
                ->select('solicitudes.*, personas.nombres, personas.apellidos, clientes.codigo')
                ->join('clientes', 'clientes.id = solicitudes.cliente_id')
                ->join('personas', 'personas.id = clientes.persona_id')
                ->where('solicitudes.tenant_id', $tenantId)
                ->where('solicitudes.empleado_id', $empleadoId)
                ->orderBy('solicitudes.id', 'DESC')
                ->paginate(10),
            'pager' => $this->solicitudes->pager,
        ];
    }

    /** Cliente de la cartera del gestor: solo si tiene solicitudes asignadas a él. */
    public function clienteDeCartera(int $tenantId, int $empleadoId, int $clienteId): ?array
    {
        return $this->clientes
            ->select('clientes.*')
            ->join('solicitudes s2', 's2.cliente_id = clientes.id')
            ->where('clientes.tenant_id', $tenantId)
            ->where('clientes.id', $clienteId)
            ->where('s2.asignado_a', $empleadoId)
            ->groupBy('clientes.id')
            ->first();
    }

    /**
     * Ficha completa para el portal: cliente + persona + secciones + solicitudes.
     * Null si el cliente no pertenece a la cartera del gestor.
     * @return array{cliente: array, persona: array, secciones: array, solicitudes: array}|null
     */
    public function fichaCliente(int $tenantId, int $empleadoId, int $clienteId): ?array
    {
        $cli = $this->clienteDeCartera($tenantId, $empleadoId, $clienteId);
        $per = $cli ? $this->personas->find((int) $cli['persona_id']) : null;
        if (!$cli || !$per) {
            return null;
        }

        // Datos complementarios por pestaña (direccion, contacto, referencia, ...)
        $secciones = [];
        foreach ((new PersonaDetalleModel())->tipos() as $tipo) {
            $secciones[$tipo] = (new PersonaDetalleModel($tipo))->dePersona((int) $per['id']);
        }

        return [
            'cliente'     => $cli,
            'persona'     => $per,
            'secciones'   => $secciones,
            'solicitudes' => $this->solicitudes
                ->where('tenant_id', $tenantId)
                ->where('cliente_id', $clienteId)
                ->orderBy('id', 'DESC')
                ->findAll(12),
            // Créditos activos con cuotas por cobrar — alimenta "Registrar pago"
            'cobros' => array_values(array_filter(
                $this->cobrosDelGestor($tenantId, $empleadoId),
                fn($c) => (int) ($c['cliente_id'] ?? 0) === $clienteId
            )),
        ];
    }

    // ---------------------------------------------------------------
    // Escrituras
    // ---------------------------------------------------------------

    /**
     * Lead web público (portal/solicitar): reutiliza la ficha del cliente si
     * la cédula o el teléfono ya existen; crea/actualiza la solicitud CONTACTO.
     */
    public function crearLeadWeb(array $tenant, array $d): void
    {
        $tenantId = (int) $tenant['id'];
        $cedula   = trim((string) ($d['cedula'] ?? ''));
        $telefono = trim((string) ($d['telefono'] ?? ''));

        $persona = null;
        if ($cedula !== '') {
            $persona = $this->personas->where('tenant_id', $tenantId)->where('cedula', $cedula)->first();
        }
        if (!$persona && $telefono !== '') {
            $persona = $this->personas->where('tenant_id', $tenantId)->where('telefono', $telefono)->first();
        }

        $this->db->transStart();

        if ($persona) {
            // Completar en su ficha los datos que le falten
            $upd = [];
            if ($cedula !== ''   && empty($persona['cedula']))   $upd['cedula']   = $cedula;
            if ($telefono !== '' && empty($persona['telefono'])) $upd['telefono'] = $telefono;
            if ($upd) {
                $this->personas->update((int) $persona['id'], $upd);
            }
            $clienteId = $this->fichaClienteId($tenantId, (int) $persona['id'], (string) $tenant['nombre']);
        } else {
            $personaId = (int) $this->personas->insert([
                'tenant_id' => $tenantId,
                'tipo'      => 'CLIENTE',
                'nombres'   => trim((string) $d['nombres']),
                'apellidos' => trim((string) $d['apellidos']),
                'cedula'    => $cedula   !== '' ? $cedula   : null,
                'telefono'  => $telefono !== '' ? $telefono : null,
                'estado'    => 'ACTIVO',
            ]);
            $clienteId = $this->fichaClienteId($tenantId, $personaId, (string) $tenant['nombre']);
        }

        // Si ya tiene una solicitud "por contactar" pendiente, se actualiza
        // en lugar de duplicar el lead.
        $pendiente = $this->solicitudes->where('tenant_id', $tenantId)
            ->where('cliente_id', $clienteId)
            ->where('estado', SolicitudModel::CONTACTO)
            ->orderBy('id', 'DESC')
            ->first();

        if ($pendiente) {
            $this->solicitudes->update((int) $pendiente['id'], [
                'monto'   => (float) $d['monto'],
                'destino' => trim((string) ($d['destino'] ?? '')) ?: null,
            ]);
        } else {
            $this->solicitudes->insert([
                'tenant_id'  => $tenantId,
                'cliente_id' => $clienteId,
                'monto'      => (float) $d['monto'],
                'frecuencia' => 'M',
                'destino'    => trim((string) ($d['destino'] ?? '')) ?: null,
                'estado'     => SolicitudModel::CONTACTO,
                'origen'     => 'WEB',
            ]);
        }

        $this->db->transComplete();
    }

    /**
     * Solicitud de crédito creada por el gestor (estado CREADA, asignada a su cartera).
     * @return array{ok: bool, error?: string}
     */
    public function crearSolicitudGestor(array $tenant, array $empleado, array $d): array
    {
        $monto = (float) ($d['monto'] ?? 0);
        if ($monto < 1000) {
            return ['ok' => false, 'error' => 'El monto mínimo a prestar es 1,000.'];
        }
        $plazoMax = (int) ($tenant['plazo_meses_max'] ?? 0);
        $plazo    = ($d['plazo_meses'] ?? '') !== '' ? (int) $d['plazo_meses'] : null;
        if ($plazo !== null && $plazoMax > 0 && $plazo > $plazoMax) {
            return ['ok' => false, 'error' => "El plazo máximo permitido es {$plazoMax} meses."];
        }

        $tenantId  = (int) $tenant['id'];
        $clienteId = null;
        $limiteCli = 10000.00; // cliente nuevo o sin límite configurado
        $this->db->transStart();
        if (($d['cli_modo'] ?? '') === 'nuevo') {
            $nom = trim((string) ($d['cli_nombres'] ?? ''));
            $ape = trim((string) ($d['cli_apellidos'] ?? ''));
            if ($nom === '' || $ape === '') {
                $this->db->transComplete();
                return ['ok' => false, 'error' => 'Complete nombres y apellidos del nuevo cliente.'];
            }
            $personaId = (int) $this->personas->insert([
                'tenant_id' => $tenantId,
                'tipo'      => 'CLIENTE',
                'nombres'   => $nom,
                'apellidos' => $ape,
                'cedula'    => trim((string) ($d['cli_cedula'] ?? '')) ?: null,
                'telefono'  => trim((string) ($d['cli_telefono'] ?? '')) ?: null,
                'direccion' => trim((string) ($d['cli_direccion'] ?? '')) ?: null,
                'estado'    => 'ACTIVO',
            ]);
            $clienteId = $this->fichaClienteId($tenantId, $personaId, (string) $tenant['nombre']);
        } else {
            $clienteId = (int) ($d['cliente_id'] ?? 0);
            $cli = $this->clientes->where('tenant_id', $tenantId)->find($clienteId);
            if (!$cli) {
                $this->db->transComplete();
                return ['ok' => false, 'error' => 'Seleccione un cliente.'];
            }
            $limiteCli = (float) ($cli['limite_credito'] ?? 0) > 0
                ? (float) $cli['limite_credito'] : 10000.00;
        }
        if ($monto > $limiteCli) {
            $this->db->transComplete();
            return ['ok' => false, 'error' => 'El monto supera el límite de crédito del cliente ('
                . number_format($limiteCli, 0) . ').'];
        }

        // Cartera: la solicitud la creó el gestor y queda asignada a él (su ruta)
        $this->solicitudes->insert([
            'tenant_id'    => $tenantId,
            'cliente_id'   => $clienteId,
            'empleado_id'  => (int) $empleado['id'],   // quién la creó
            'asignado_a'   => (int) $empleado['id'],   // a quién está asignada (cartera del gestor)
            'ruta'         => $empleado['ruta'] ?? null,
            'monto'        => $monto,
            'plazo_meses'  => $plazo,
            'frecuencia'   => ($d['frecuencia'] ?? '') ?: 'M',
            // El gestor hereda el método de cálculo configurado por la empresa
            'tipo_calculo' => in_array($tenant['tipo_calculo'] ?? '', ['FRANCES', 'FLAT', 'ALEMAN', 'ANTICIPADO'], true)
                ? $tenant['tipo_calculo'] : 'FLAT',
            // El gestor puede bajar la tasa para negociar — nunca supera la del tenant
            'tasa_mensual' => min(max(0.0, (float) ($d['tasa_mensual'] ?? ($tenant['tasa_interes'] ?? 0))),
                                  (float) ($tenant['tasa_interes'] ?? 0)),
            'dias_semana'  => ($d['frecuencia'] ?? '') === 'DI' ? (int) ($d['dias_semana'] ?? 0) : null,
            // Fecha de primer pago que negoció el gestor con la clienta (opcional)
            'fecha_primer_pago' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['fecha_primer_pago'] ?? ''))
                ? $d['fecha_primer_pago'] : null,
            'destino'      => trim((string) ($d['destino'] ?? '')) ?: null,
            'estado'       => SolicitudModel::CREADA,
        ]);

        $this->db->transComplete();
        if (!$this->db->transStatus()) {
            return ['ok' => false, 'error' => 'No se pudo guardar la solicitud. Intente de nuevo.'];
        }

        return ['ok' => true];
    }

    /**
     * El gestor marca el dinero como entregado: genera código de crédito y activa.
     * @return array{ok: bool, codigo?: string, error?: string}
     */
    public function entregarDesembolso(int $tenantId, int $empleadoId, int $solId): array
    {
        // Transacción: la lectura del MAX(codigo_credito) y el update deben ser
        // atómicos o dos desembolsos simultáneos generan el mismo código.
        $this->db->transStart();

        $sol = $this->solicitudes->where('tenant_id', $tenantId)
            ->where('asignado_a', $empleadoId)
            ->find($solId);
        if (!$sol || $sol['estado'] !== SolicitudModel::DESEMBOLSO) {
            $this->db->transComplete();
            return ['ok' => false, 'error' => 'Desembolso no encontrado o ya fue entregado.'];
        }

        // Genera el código de crédito (C-INI-AAAA-0000) solo la primera vez
        $tenant  = $this->tenants->find($tenantId);
        $codigo  = $sol['codigo_credito'] ?: $this->solicitudes->siguienteCodigoCredito($tenantId, (string) $tenant['nombre']);
        $this->solicitudes->update($solId, [
            'estado'         => SolicitudModel::ACTIVO,
            'codigo_credito' => $codigo,
            'fecha_entrega'  => date('Y-m-d H:i:s'),   // egreso real — alimenta el arqueo del gestor
        ]);

        // Plan de pago persistido: una fila en `cuotas` por cuota proyectada
        $pagos = new PagoService();
        $pagos->generarCuotas($tenantId, array_merge($sol, ['estado' => SolicitudModel::ACTIVO]));

        // Refinanciamiento: el dinero nuevo liquida el crédito anterior
        if (!empty($sol['refinancia_id'])) {
            $pagos->liquidarPorRefinanciamiento($tenantId, (int) $sol['refinancia_id'], $solId);
        }

        $this->db->transComplete();
        if (!$this->db->transStatus()) {
            return ['ok' => false, 'error' => 'No se pudo registrar el desembolso. Intente de nuevo.'];
        }

        return ['ok' => true, 'codigo' => $codigo];
    }

    /**
     * Créditos activos de la cartera del gestor con cuotas pendientes.
     * @return array
     */
    public function cobrosDelGestor(int $tenantId, int $empleadoId): array
    {
        return (new PagoService())->cobrosDelGestor($tenantId, $empleadoId);
    }

    /**
     * Notificaciones del gestor: solicitudes asignadas a él cuyo cliente
     * tiene datos incompletos (cédula, teléfono, dirección o detalles).
     * @return array<int, array>
     */
    public function notificacionesGestor(int $tenantId, int $empleadoId): array
    {
        $filas = $this->solicitudes
            ->select('solicitudes.id, solicitudes.cliente_id, solicitudes.estado,
                      solicitudes.monto, solicitudes.codigo_credito,
                      personas.id AS persona_id, personas.nombres, personas.apellidos,
                      personas.cedula, personas.telefono, personas.direccion, personas.email')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->where('solicitudes.tenant_id', $tenantId)
            ->where('solicitudes.asignado_a', $empleadoId)
            ->whereNotIn('solicitudes.estado', [SolicitudModel::LIQUIDADO, SolicitudModel::RECHAZADA])
            ->orderBy('solicitudes.id', 'DESC')
            ->findAll(60);

        // Mismos requeridos del checklist de oficina: persona (cédula, teléfono)
        // + detalles del expediente (dirección, contacto, referencia, ingreso, documento)
        $tiposReq = ['direccion' => 'dirección domiciliar', 'contacto' => 'contactos',
                     'referencia' => 'referencias', 'ingreso' => 'ingresos', 'documento' => 'documentos'];

        $perIds = array_values(array_unique(array_map(fn($f) => (int) $f['persona_id'], $filas)));
        $cuentaPorPersona = []; // [persona_id][tipo] = n
        foreach ($tiposReq as $tipo => $_) {
            foreach ((new PersonaDetalleModel($tipo))
                ->select('persona_id, COUNT(*) AS n')->whereIn('persona_id', $perIds ?: [0])
                ->groupBy('persona_id')->findAll() as $r) {
                $cuentaPorPersona[(int) $r['persona_id']][$tipo] = (int) $r['n'];
            }
        }

        $out  = [];
        $vistos = [];
        foreach ($filas as $f) {
            // Un aviso por cliente (el de su solicitud más reciente)
            if (isset($vistos[(int) $f['cliente_id']])) continue;
            $vistos[(int) $f['cliente_id']] = true;

            $pid    = (int) $f['persona_id'];
            $faltan = [];
            if (trim((string) $f['cedula'])   === '') $faltan[] = 'cédula';
            if (trim((string) $f['telefono']) === '') $faltan[] = 'teléfono';
            foreach ($tiposReq as $tipo => $lbl) {
                if (($cuentaPorPersona[$pid][$tipo] ?? 0) === 0) $faltan[] = $lbl;
            }
            if ($faltan === []) continue;

            $out[] = [
                'solicitud_id' => (int) $f['id'],
                'cliente_id'   => (int) $f['cliente_id'],
                'nombre'       => trim($f['nombres'] . ' ' . $f['apellidos']),
                'codigo'       => $f['codigo_credito'] ?: '#' . $f['id'],
                'estado'       => $f['estado'],
                'faltan'       => implode(' · ', $faltan),
            ];
        }
        return $out;
    }

    /**
     * El gestor cobra en campo — el pago queda EN REVISIÓN hasta que
     * oficina confirme que el dinero llegó.
     * @return array{ok: bool, error?: string}
     */
    public function registrarCobro(int $tenantId, int $empleadoId, int $solId, array $d): array
    {
        return (new PagoService())->registrarPago($tenantId, $solId, $d, $empleadoId, null);
    }

    /**
     * Resumen del día para Cobros del gestor: cuánto cobró hoy (lista de
     * recepcionados) y cuánto le queda pendiente por vencer hoy o antes.
     */
    public function resumenCobrosHoy(int $tenantId, int $empleadoId, array $creditos): array
    {
        $hoy = date('Y-m-d');
        $pendiente = 0;
        foreach ($creditos as $cr) {
            foreach ($cr['cuotas_pend'] ?? [] as $c) {
                if ($c['fecha_vence'] <= $hoy) {
                    $pendiente += (float) $c['pendiente'];
                }
            }
        }
        $cobrados = (new PagoService())->cobrosHoyDelGestor($tenantId, $empleadoId);
        // El total solo cuenta cobros vigentes: en revisión o aplicados.
        // Rechazados/revertidos se ven en la lista pero ya no suman.
        $vigentes = array_filter($cobrados, fn($p) => in_array($p['estado'], ['REVISION', 'APLICADO'], true));
        return [
            'pendiente_hoy' => round($pendiente, 2),
            'cobrado_hoy'   => round(array_sum(array_map(fn($p) => (float) $p['monto'], $vigentes)), 2),
            'cobrados_hoy'  => $cobrados,
        ];
    }

    /**
     * Ruta de cobro del gestor: una parada por crédito con cuota que vence
     * hoy o ya está vencida, con la mejor dirección (preferir la que tiene GPS).
     * @return array<int, array>
     */
    public function rutaCobrosHoy(int $tenantId, int $empleadoId): array
    {
        $hoy      = date('Y-m-d');
        $creditos = $this->cobrosDelGestor($tenantId, $empleadoId);

        $paradas = [];
        foreach ($creditos as $cr) {
            $monto   = 0.0;
            $vencida = false;
            foreach ($cr['cuotas_pend'] ?? [] as $c) {
                if ($c['fecha_vence'] <= $hoy) {
                    $monto   += (float) $c['pendiente'];
                    $vencida = $vencida || ($c['vencida'] ?? false);
                }
            }
            if ($monto <= 0) continue;
            $paradas[] = [
                'sol_id'     => (int) $cr['id'],
                'cliente_id' => (int) $cr['cliente_id'],
                'nombre'     => trim($cr['nombres'] . ' ' . $cr['apellidos']),
                'telefono'   => (string) ($cr['telefono'] ?? ''),
                'codigo'     => $cr['codigo_credito'] ?: ('#' . $cr['id']),
                'monto'      => round($monto, 2),
                'vencida'    => $vencida,
            ];
        }
        if (!$paradas) return [];

        // persona_id por cliente → mejor dirección (con GPS primero)
        $cliIds = array_column($paradas, 'cliente_id');
        $perPorCli = [];
        foreach ($this->clientes->select('id, persona_id')->whereIn('id', $cliIds)->findAll() as $c) {
            $perPorCli[(int) $c['id']] = (int) $c['persona_id'];
        }
        $perIds = array_values(array_filter($perPorCli));
        $dirPorPersona = [];
        if ($perIds) {
            $dirs = (new PersonaDetalleModel('direccion'))
                ->whereIn('persona_id', $perIds)
                ->orderBy('(latitud IS NOT NULL)', 'DESC', false)
                ->orderBy('id', 'DESC')
                ->findAll();
            foreach ($dirs as $d) {
                if (!isset($dirPorPersona[$d['persona_id']])) {
                    $dirPorPersona[$d['persona_id']] = $d;
                }
            }
        }

        foreach ($paradas as &$p) {
            $dir = $dirPorPersona[$perPorCli[$p['cliente_id']] ?? 0] ?? null;
            $lat = $dir['latitud']  ?? null;
            $lng = $dir['longitud'] ?? null;
            $p['lat'] = is_numeric($lat) ? (float) $lat : null;
            $p['lng'] = is_numeric($lng) ? (float) $lng : null;
            $p['dir'] = $dir
                ? trim(implode(', ', array_filter([
                    $dir['barrio'] ?? '', $dir['ciudad'] ?? '', $dir['departamento'] ?? ''])))
                : '';
            $p['detalle'] = trim((string) ($dir['detalle'] ?? ''));
        }
        unset($p);

        return $paradas;
    }

    /**
     * "Mi caja" del gestor — resumen del día + sus arqueos cerrados.
     * Solo lectura: el cierre lo hace oficina en /finanzas/arqueo.
     */
    public function arqueoGestor(int $tenantId, int $empleadoId, string $fecha): array
    {
        // Dinero por entregar: desembolsos aprobados asignados al gestor
        // que aún no tienen fecha_entrega (el gestor no los ha entregado).
        $porDesembolsar = (float) ($this->solicitudes
            ->selectSum('monto_aprobado')
            ->where('tenant_id', $tenantId)
            ->where('asignado_a', $empleadoId)
            ->where('estado', SolicitudModel::DESEMBOLSO)
            ->where('fecha_entrega IS NULL', null, false)
            ->first()['monto_aprobado'] ?? 0);

        return [
            'resumen'         => (new ArqueoService())->resumenDia($tenantId, $empleadoId, $fecha),
            'arqueos'         => (new ArqueoModel())->deGestor($tenantId, $empleadoId),
            'por_desembolsar' => round($porDesembolsar, 2),
            'lblEstado'       => ArqueoModel::LABEL_ESTADO,
        ];
    }

    /** Actualiza los datos básicos de la persona del cliente (desde el portal). */
    public function actualizarDatosCliente(array $cliente, array $d): void
    {
        $this->personas->update((int) $cliente['persona_id'], [
            'nombres'   => trim((string) $d['nombres']),
            'apellidos' => trim((string) $d['apellidos']),
            'genero'    => in_array($d['genero'] ?? null, ['M', 'F'], true) ? $d['genero'] : null,
            'cedula'    => trim((string) ($d['cedula'] ?? '')) ?: null,
            'telefono'  => trim((string) ($d['telefono'] ?? '')) ?: null,
            'email'     => trim((string) ($d['email'] ?? '')) ?: null,
            'direccion' => trim((string) ($d['direccion'] ?? '')) ?: null,
            'fecha_nac' => ($d['fecha_nac'] ?? '') !== '' ? $d['fecha_nac'] : null,
        ]);
    }

    // ---------------------------------------------------------------
    // Datos complementarios del cliente (pestañas de la ficha)
    // ---------------------------------------------------------------

    /**
     * Agrega un item de detalle (direccion, contacto, documento, ...) a la persona.
     * @param  array $d       campos del POST
     * @param  mixed $archivo UploadedFile para tipo documento (o null)
     * @return array{ok: bool, error?: string}
     */
    public function agregarDato(int $personaId, string $tipo, array $d, $archivo = null): array
    {
        $detalle = $this->detalleModelo($tipo);
        if (!$detalle) {
            return ['ok' => false, 'error' => 'Tipo de dato inválido.'];
        }
        if ($tipo === 'direccion'
            && trim((string) ($d['detalle'] ?? '')) === ''
            && trim((string) ($d['ciudad'] ?? '')) === '') {
            return ['ok' => false, 'error' => 'Escribí al menos la ciudad o el detalle de la dirección.'];
        }

        $fila = $this->filaDetalle($detalle, $tipo, $d, $archivo, $personaId);
        if ($fila === null) {
            return ['ok' => false, 'error' => 'El documento no debe superar 5 MB.'];
        }
        $detalle->insert($fila);

        return ['ok' => true];
    }

    /**
     * Edita un item de detalle — acotado a la persona del cliente.
     * @return array{ok: bool, error?: string}
     */
    public function actualizarDato(int $personaId, string $tipo, int $itemId, array $d, $archivo = null): array
    {
        $detalle = $this->detalleModelo($tipo);
        if (!$detalle) {
            return ['ok' => false, 'error' => 'Tipo de dato inválido.'];
        }

        $fila = $this->filaDetalle($detalle, $tipo, $d, $archivo, $personaId);
        if ($fila === null) {
            return ['ok' => false, 'error' => 'El documento no debe superar 5 MB.'];
        }
        unset($fila['persona_id']);   // no mover el item a otra persona
        $detalle->where('persona_id', $personaId)->update($itemId, $fila);

        return ['ok' => true];
    }

    /** Borra un item de detalle — acotado a la persona del cliente. */
    public function eliminarDato(int $personaId, string $tipo, int $itemId): array
    {
        $detalle = $this->detalleModelo($tipo);
        if (!$detalle) {
            return ['ok' => false, 'error' => 'Tipo de dato inválido.'];
        }
        $detalle->where('persona_id', $personaId)->delete($itemId);

        return ['ok' => true];
    }

    /** Modelo del tipo de detalle o null si el tipo no existe. */
    private function detalleModelo(string $tipo): ?PersonaDetalleModel
    {
        try {
            return (new PersonaDetalleModel())->para($tipo);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
    }

    /**
     * Fila de detalle construida desde los datos del POST (+ archivo si es
     * documento). Null si el archivo supera el máximo permitido.
     */
    private function filaDetalle(PersonaDetalleModel $detalle, string $tipo, array $d, $archivo, int $personaId): ?array
    {
        $fila = ['persona_id' => $personaId];
        foreach ($detalle->campos($tipo) as $campo) {
            if ($tipo === 'documento' && $campo === 'archivo') continue;
            if ($campo === 'dias_venta') {
                $dias = (array) ($d['dias_venta'] ?? []);
                $fila['dias_venta'] = $dias ? implode(',', $dias) : null;
                continue;
            }
            $fila[$campo] = trim((string) ($d[$campo] ?? '')) ?: null;
        }

        if ($tipo === 'documento' && $archivo && $archivo->isValid() && !$archivo->hasMoved()) {
            if ($archivo->getSize() > 5 * 1024 * 1024) {
                return null;
            }
            $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'documentos';
            if (!is_dir($dir)) mkdir($dir, 0775, true);
            $nombre = 'p' . $personaId . '_' . $archivo->getRandomName();
            $archivo->move($dir, $nombre);
            $fila['archivo'] = $nombre;
        }

        return $fila;
    }

    // ---------------------------------------------------------------

    /** Ficha de cliente para una persona; la crea si no existe. */
    private function fichaClienteId(int $tenantId, int $personaId, string $tenantNombre): int
    {
        $cli = $this->clientes->where('tenant_id', $tenantId)->where('persona_id', $personaId)->first();
        if ($cli) {
            return (int) $cli['id'];
        }
        return (int) $this->clientes->insert([
            'tenant_id'      => $tenantId,
            'persona_id'     => $personaId,
            'codigo'         => $this->clientes->siguienteCodigo($tenantId, $tenantNombre),
            'limite_credito' => 10000.00, // tope por defecto — oficina lo amplía en la ficha
            'estado'         => 'ACTIVO',
        ]);
    }
}
