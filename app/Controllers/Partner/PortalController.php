<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\PortalService;
use App\Services\Partner\PagoService;

/**
 * Portal del empleado - /{slug}/portal (capa HTTP).
 * Toda la lógica de negocio vive en Services/Partner/PortalService.
 * Sesión propia (portal_empleado_id), separada de la sesión de usuario.
 */
class PortalController extends BaseController
{
    private PortalService $portal;

    public function __construct()
    {
        $this->portal = new PortalService();
    }

    /** GET /{slug}/portal - landing page pública (info de la empresa). */
    public function index(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/')->with('error', 'Empresa no encontrada.');
        }
        return view('partner/portal/landing', [
            'title'    => $tenant['nombre'],
            'tenant'   => $tenant,
            'slug'     => $slug,
            'logueado' => $this->portal->sesionGestorActiva($tenant),
        ]);
    }

    /** GET /{slug}/portal/login - formulario de acceso del gestor. */
    public function login(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/')->with('error', 'Empresa no encontrada.');
        }
        if ($this->portal->sesionGestorActiva($tenant)) {
            return $this->destinoGestor($slug, $tenant);
        }
        return view('partner/portal/login', [
            'title'  => $tenant['nombre'] . ' - Portal de gestores',
            'tenant' => $tenant,
            'slug'   => $slug,
        ]);
    }

    /** POST /{slug}/portal/login - valida carnet + PIN. */
    public function entrar(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/')->with('error', 'Empresa no encontrada.');
        }

        $r = $this->portal->autenticarGestor(
            $tenant,
            (string) $this->request->getPost('carnet'),
            (string) $this->request->getPost('pin')
        );

        if (!$r['ok']) {
            return redirect()->to('/' . $slug . '/portal/login')
                ->withInput()
                ->with('error', $r['error']);
        }

        session()->set([
            'portal_empleado_id' => (int) $r['empleado']['id'],
            'portal_persona_id'  => (int) $r['empleado']['persona_id'],
            'portal_tenant_id'   => (int) $tenant['id'],
        ]);

        return $this->destinoGestor($slug, $tenant);
    }

    /** Destino tras login o al re-entrar logueado: suspendida → horario → panel. */
    private function destinoGestor(string $slug, array $tenant)
    {
        if (!plan_al_dia((int) $tenant['id'])['ok']) {
            return redirect()->to('/' . $slug . '/portal/suspendida');
        }
        if (!en_horario($tenant)['ok']) {
            return redirect()->to('/' . $slug . '/portal/horario');
        }
        return redirect()->to('/' . $slug . '/portal/panel');
    }

    /** GET /{slug}/portal/horario — bloqueo fuera del horario laboral (la sesión sigue activa). */
    public function horario(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/')->with('error', 'Empresa no encontrada.');
        }
        if (!$this->portal->sesionGestorActiva($tenant)) {
            return redirect()->to('/' . $slug . '/portal/login');
        }
        $h = en_horario($tenant);
        if ($h['ok']) {
            return redirect()->to('/' . $slug . '/portal/panel');
        }
        return view('partner/portal/horario', [
            'title'  => 'Fuera de horario',
            'tenant' => $tenant,
            'slug'   => $slug,
            'h'      => $h,
        ]);
    }

    /** GET /{slug}/portal/suspendida — suscripción del tenant vencida. */
    public function suspendida(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/')->with('error', 'Empresa no encontrada.');
        }
        if (!$this->portal->sesionGestorActiva($tenant)) {
            return redirect()->to('/' . $slug . '/portal/login');
        }
        $estado = plan_al_dia((int) $tenant['id']);
        if ($estado['ok']) {
            return redirect()->to('/' . $slug . '/portal/panel');
        }
        return view('partner/portal/suspendida', [
            'title'  => 'Servicio suspendido',
            'tenant' => $tenant,
            'slug'   => $slug,
            'estado' => $estado,
        ]);
    }

    /** GET /{slug}/portal/manifest.webmanifest - manifiesto PWA con la marca del tenant. */
    public function manifest(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return $this->response->setStatusCode(404);
        }

        return $this->response
            ->setContentType('application/manifest+json')
            ->setBody(json_encode(
                $this->portal->manifiesto($tenant, $slug),
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE
            ));
    }

    /** GET /{slug}/portal/salir - cierra la sesión del gestor. */
    public function salir(string $slug)
    {
        session()->remove(['portal_empleado_id', 'portal_persona_id', 'portal_tenant_id']);
        return redirect()->to('/' . $slug . '/portal')->with('success', 'Sesión cerrada.');
    }

    /** GET /{slug}/portal/solicitud - form de nueva solicitud de crédito. */
    public function solicitud(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        return view('partner/portal/solicitud', [
            'title'    => 'Nueva solicitud - ' . $tenant['nombre'],
            'tenant'   => $tenant, 'slug' => $slug, 'persona' => $persona,
            'clientes' => $this->portal->clientesDeTenant((int) $tenant['id']),
        ]);
    }

    /** GET /{slug}/portal/solicitar - formulario PÚBLICO para pedir crédito (sin login). */
    public function solicitar(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/');
        }

        return view('partner/portal/solicitar', [
            'title'   => 'Solicita tu crédito - ' . $tenant['nombre'],
            'tenant'  => $tenant,
            'slug'    => $slug,
            'enviada' => (bool) session()->getFlashdata('solicitud_ok'),
        ]);
    }

    /** POST /{slug}/portal/solicitar - guarda la solicitud web (CONTACTO). */
    public function guardarSolicitar(string $slug)
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return redirect()->to('/');
        }

        // Honeypot antispam - si viene lleno, simular éxito sin guardar
        if ($this->request->getPost('web') !== '' && $this->request->getPost('web') !== null) {
            return redirect()->to('/' . $slug . '/portal/solicitar')->with('solicitud_ok', true);
        }

        if (!$this->validate([
            'nombres'   => 'required|max_length[100]',
            'apellidos' => 'required|max_length[100]',
            'telefono'  => 'required|max_length[50]',
            'cedula'    => 'permit_empty|max_length[30]',
            'monto'     => 'required|numeric|greater_than[0]',
            'destino'   => 'permit_empty|max_length[200]',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $this->portal->crearLeadWeb($tenant, $this->request->getPost(
            ['nombres', 'apellidos', 'cedula', 'telefono', 'monto', 'destino']
        ));

        return redirect()->to('/' . $slug . '/portal/solicitar')->with('solicitud_ok', true);
    }

    /** POST /{slug}/portal/solicitud - guarda la solicitud del gestor (CREADA). */
    public function guardarSolicitud(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $r = $this->portal->crearSolicitudGestor($tenant, $empleado, $this->request->getPost());
        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }

        return redirect()->to('/' . $slug . '/portal/panel')
            ->with('success', 'Solicitud creada y asignada a tu cartera.');
    }

    /** GET /{slug}/portal/solicitud/{id}/editar - el gestor corrige su solicitud (CREADA|REVISION). */
    public function editarSolicitud(string $slug, int $id)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        $sol = $this->portal->solicitudDeGestor((int) $tenant['id'], (int) $empleado['id'], $id);
        if (!$sol) {
            return redirect()->to('/' . $slug . '/portal/actividad')
                ->with('error', 'Solicitud no encontrada en tu cartera.');
        }
        if (!in_array($sol['estado'], ['CREADA', 'REVISION'], true)) {
            return redirect()->to('/' . $slug . '/portal/actividad')
                ->with('error', 'La solicitud ya no se puede editar.');
        }

        return view('partner/portal/solicitud_editar', [
            'title' => 'Editar solicitud - ' . $tenant['nombre'],
            'tenant' => $tenant, 'slug' => $slug, 'persona' => $persona,
            's' => $sol,
        ]);
    }

    /** POST /{slug}/portal/solicitud/{id}/editar - guarda los cambios del gestor. */
    public function guardarEdicionSolicitud(string $slug, int $id)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $r = $this->portal->actualizarSolicitudGestor($tenant, $empleado, $id, $this->request->getPost());
        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }

        return redirect()->to('/' . $slug . '/portal/actividad')
            ->with('success', 'Solicitud actualizada — oficina la revisará.');
    }

    /** GET /{slug}/portal/perfil - perfil del gestor (datos + documentos). */
    public function perfil(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        return view('partner/portal/perfil', [
            'title' => 'Mi perfil - ' . $tenant['nombre'], 'tenant' => $tenant, 'slug' => $slug,
            'persona' => $persona, 'empleado' => $empleado,
            'documentos' => $this->portal->documentosDe((int) $persona['id']),
        ]);
    }

    /** GET /{slug}/portal/calculadora - calcula la cuota de un préstamo. */
    public function calculadora(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        return view('partner/portal/calculadora', [
            'title' => 'Calculadora - ' . $tenant['nombre'], 'tenant' => $tenant, 'slug' => $slug,
            'persona' => $persona,
        ]);
    }

    /** GET /{slug}/portal/recuperacion - legacy: la recuperación vive en Cobros (cuotas vencidas). */
    public function recuperacion(string $slug)
    {
        if (!$this->ctx($slug)) return redirect()->to('/' . $slug . '/portal/login');
        return redirect()->to('/' . $slug . '/portal/cobros?mora=1');
    }

    /** GET /{slug}/portal/desembolso - desembolsos pendientes de la cartera del gestor. */
    public function desembolso(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        $data = $this->portal->desembolsosPendientes((int) $tenant['id'], (int) $empleado['id']);

        return view('partner/portal/seccion', [
            'title'   => 'Desembolso - ' . $tenant['nombre'], 'tenant' => $tenant, 'slug' => $slug,
            'persona' => $persona, 'seccion' => 'desembolso',
            'solicitudes' => $data['solicitudes'], 'pager' => $data['pager'],
            'hoy'     => date('Y-m-d'),
            'puede_entregar' => !empty($empleado['puede_desembolsar']),
        ]);
    }

    /** POST /{slug}/portal/desembolso/{id}/entregar - el gestor marca el dinero como entregado. */
    public function entregarDesembolso(string $slug, int $id)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $r = $this->portal->entregarDesembolso((int) $tenant['id'], (int) $empleado['id'], $id);
        if (!$r['ok']) {
            return redirect()->to('/' . $slug . '/portal/desembolso')->with('error', $r['error']);
        }

        return redirect()->to('/' . $slug . '/portal/desembolso')
            ->with('success', 'Desembolso entregado - crédito ' . $r['codigo'] . ' activo.');
    }

    /** GET /{slug}/portal/cobros - créditos activos del gestor con cuotas por cobrar. */
    public function cobros(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $creditos = $this->portal->cobrosDelGestor((int) $tenant['id'], (int) $empleado['id']);

        // "Por cobrar" filtrado por fecha: cuotas que vencen ese día o antes
        $f = (string) $this->request->getGet('fecha');
        $fechaCobro = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : date('Y-m-d');

        return view('partner/portal/seccion', array_merge([
            'title'    => 'Cobros - ' . $tenant['nombre'],
            'tenant'   => $tenant,
            'slug'     => $slug,
            'empleado' => $empleado,
            'seccion'  => 'cobros',
            'mora'     => (bool) $this->request->getGet('mora'),
            'fecha_cobro' => $fechaCobro,
            'creditos' => $creditos,
            'metodos'  => \App\Models\PagoModel::METODOS_LBL,
            'lblEstado' => \App\Models\PagoModel::LABEL_ESTADO,
        ], $this->portal->resumenCobrosHoy((int) $tenant['id'], (int) $empleado['id'], $creditos)));
    }

    /** GET /{slug}/portal/mapa - ruta de cobro del día ordenada por distancia GPS. */
    public function mapa(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $paradas = $this->portal->rutaCobrosHoy((int) $tenant['id'], (int) $empleado['id']);

        return view('partner/portal/mapa', [
            'title'    => 'Ruta de cobro - ' . $tenant['nombre'],
            'tenant'   => $tenant,
            'slug'     => $slug,
            'empleado' => $empleado,
            'paradas'  => $paradas,
            'total'    => round(array_sum(array_column($paradas, 'monto')), 2),
            'con_gps'  => count(array_filter($paradas, fn($p) => $p['lat'] !== null)),
        ]);
    }

    /** GET /{slug}/portal/cobros/{pago}/recibo - voucher imprimible del cobro del gestor. */
    public function reciboCobro(string $slug, int $pagoId)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $data = (new PagoService())->reciboPago((int) $tenant['id'], $pagoId);
        // Solo puede ver/imprimir sus cobros vigentes (revisión o aplicados)
        if (!$data || (int) ($data['pago']['empleado_id'] ?? 0) !== (int) $empleado['id']
            || ($data['pago']['tipo'] ?? 'PAGO') !== 'PAGO'
            || !in_array($data['pago']['estado'], ['REVISION', 'APLICADO'], true)) {
            return redirect()->to('/' . $slug . '/portal/cobros')->with('error', 'Recibo no encontrado.');
        }
        $data['title']     = 'Recibo ' . \App\Models\PagoModel::reciboCode($data['pago'], $tenant['nombre']);
        $data['reciboNum'] = \App\Models\PagoModel::reciboCode($data['pago'], $tenant['nombre']);
        $data['lblEstado'] = \App\Models\PagoModel::LABEL_ESTADO;
        $data['metodos']   = \App\Models\PagoModel::METODOS_LBL;
        $data['volver']    = $slug . '/portal/cobros';
        return view('partner/pagos/recibo', $data);
    }

    /** POST /{slug}/portal/cobros/{sol}/abonar - cobro en campo (queda en revisión). */
    public function abonarPortal(string $slug, int $solId)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $r = $this->portal->registrarCobro(
            (int) $tenant['id'], (int) $empleado['id'], $solId, $this->request->getPost()
        );

        // Volver a la pantalla de origen (ficha de cliente, cobros, etc.)
        $back = (string) $this->request->getPost('volver');
        $dest = str_starts_with($back, '/' . $slug . '/portal/') ? $back : '/' . $slug . '/portal/cobros';

        if (!$r['ok']) {
            return redirect()->to($dest)->with('error', $r['error']);
        }
        return redirect()->to($dest)
            ->with('success', 'Cobro registrado - esperando confirmación de oficina.');
    }

    /** GET /{slug}/portal/arqueo - "Mi caja": resumen del día + arqueos cerrados. */
    public function arqueo(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $fecha = (string) ($this->request->getGet('fecha') ?: date('Y-m-d'));
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d')) {
            $fecha = date('Y-m-d');
        }

        return view('partner/portal/seccion', array_merge([
            'title'    => 'Mi caja - ' . $tenant['nombre'],
            'tenant'   => $tenant,
            'slug'     => $slug,
            'empleado' => $empleado,
            'seccion'  => 'arqueo',
            'fecha'    => $fecha,
        ], $this->portal->arqueoGestor((int) $tenant['id'], (int) $empleado['id'], $fecha)));
    }

    /** GET /{slug}/portal/cartera - cartera de clientes del gestor. */
    public function cartera(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        $data = $this->portal->carteraGestor((int) $tenant['id'], (int) $empleado['id']);

        return view('partner/portal/seccion', [
            'title' => 'Cartera de clientes - ' . $tenant['nombre'], 'tenant' => $tenant, 'slug' => $slug,
            'persona' => $persona, 'seccion' => 'cartera',
            'clientes' => $data['clientes'], 'pager' => $data['pager'],
        ]);
    }

    /** GET /{slug}/portal/cliente/{id} - ficha del cliente (datos + direcciones con mapas). */
    public function cliente(string $slug, int $id)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $ficha = $this->portal->fichaCliente((int) $tenant['id'], (int) $empleado['id'], $id);
        if (!$ficha) {
            return redirect()->to('/' . $slug . '/portal/cartera')
                ->with('error', 'Cliente no encontrado en tu cartera.');
        }

        return view('partner/portal/cliente', [
            'title'       => trim($ficha['persona']['nombres'] . ' ' . $ficha['persona']['apellidos']) . ' - ' . $tenant['nombre'],
            'tenant'      => $tenant, 'slug' => $slug,
            'cliente'     => $ficha['cliente'], 'persona' => $ficha['persona'],
            'bloqueado'   => $ficha['bloqueado'] ?? false,
            'secciones'   => $ficha['secciones'],
            'solicitudes' => $ficha['solicitudes'],
            'cobros'      => $ficha['cobros'] ?? [],
            'metodos'     => \App\Models\PagoModel::METODOS_LBL,
            'tabActiva'   => (string) $this->request->getGet('tab') ?: 'datos',
        ]);
    }

    /** POST /{slug}/portal/cliente/{id} - completa los datos básicos del cliente. */
    public function guardarCliente(string $slug, int $id)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado] = $ctx;

        $cli = $this->portal->clienteDeCartera((int) $tenant['id'], (int) $empleado['id'], $id);
        if (!$cli) {
            return redirect()->to('/' . $slug . '/portal/cartera')
                ->with('error', 'Cliente no encontrado en tu cartera.');
        }

        if (!$this->validate([
            'nombres'   => 'required|max_length[100]',
            'apellidos' => 'required|max_length[100]',
            'cedula'    => 'permit_empty|max_length[30]',
            'telefono'  => 'permit_empty|max_length[50]',
            'email'     => 'permit_empty|valid_email|max_length[150]',
            'direccion' => 'permit_empty|max_length[255]',
            'fecha_nac' => 'permit_empty|valid_date',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->portal->actualizarDatosCliente($cli, $this->request->getPost(
            ['nombres', 'apellidos', 'genero', 'cedula', 'telefono', 'email', 'direccion', 'fecha_nac']
        ));

        return redirect()->to('/' . $slug . '/portal/cliente/' . $id)
            ->with($r['bloqueado'] ? 'warning' : 'success',
                $r['bloqueado']
                    ? 'Datos guardados — nombre y cédula no cambiaron porque el cliente tiene un crédito vigente.'
                    : 'Datos del cliente actualizados.');
    }

    /** POST /{slug}/portal/cliente/{id}/dato/{tipo} - agrega un item a una pestaña. */
    public function agregarDatoCliente(string $slug, int $id, string $tipo)
    {
        $cli = $this->clienteDelGestor($slug, $id);
        if (!$cli) return $this->respuestaClienteInvalido($slug, $id);

        $r = $this->portal->agregarDato(
            (int) $cli['persona_id'], $tipo,
            $this->request->getPost(), $this->request->getFile('archivo')
        );

        return redirect()->to('/' . $slug . '/portal/cliente/' . $id . '?tab=' . $tipo)
            ->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Dato agregado.' : $r['error']);
    }

    /** POST /{slug}/portal/cliente/{id}/dato/{tipo}/{item}/actualizar - edita un item. */
    public function actualizarDatoCliente(string $slug, int $id, string $tipo, int $item)
    {
        $cli = $this->clienteDelGestor($slug, $id);
        if (!$cli) return $this->respuestaClienteInvalido($slug, $id);

        $r = $this->portal->actualizarDato(
            (int) $cli['persona_id'], $tipo, $item,
            $this->request->getPost(), $this->request->getFile('archivo')
        );

        return redirect()->to('/' . $slug . '/portal/cliente/' . $id . '?tab=' . $tipo)
            ->with($r['ok'] ? 'success' : 'error', $r['ok'] ? 'Dato actualizado.' : $r['error']);
    }

    /** POST /{slug}/portal/cliente/{id}/dato/{tipo}/{item}/eliminar - borra un item. */
    public function eliminarDatoCliente(string $slug, int $id, string $tipo, int $item)
    {
        $cli = $this->clienteDelGestor($slug, $id);
        if (!$cli) return $this->respuestaClienteInvalido($slug, $id);

        $this->portal->eliminarDato((int) $cli['persona_id'], $tipo, $item);

        return redirect()->to('/' . $slug . '/portal/cliente/' . $id . '?tab=' . $tipo);
    }

    /** GET /{slug}/portal/actividad - actividad reciente (solicitudes del gestor). */
    public function actividad(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        $data = $this->portal->actividadGestor((int) $tenant['id'], (int) $empleado['id']);

        // El feed de historial es complementario — si falla (tabla ausente,
        // service viejo en el server) la sección carga sin él.
        try {
            $eventos = $this->portal->historialGestor((int) $tenant['id'], (int) $empleado['id']);
        } catch (\Throwable $e) {
            $eventos = [];
        }

        return view('partner/portal/seccion', [
            'title' => 'Actividad reciente - ' . $tenant['nombre'], 'tenant' => $tenant, 'slug' => $slug,
            'persona' => $persona, 'seccion' => 'actividad',
            'solicitudes' => $data['solicitudes'], 'pager' => $data['pager'],
            'eventos' => $eventos,
        ]);
    }

    /** GET /{slug}/portal/panel - app del gestor autenticado. */
    public function panel(string $slug)
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return redirect()->to('/' . $slug . '/portal/login');
        [$tenant, $empleado, $persona] = $ctx;

        return view('partner/portal/panel', [
            'title'      => $tenant['nombre'] . ' - Portal de empleados',
            'tenant'     => $tenant,
            'slug'       => $slug,
            'persona'    => $persona,
            'documentos' => $this->portal->documentosDe((int) $persona['id']),
        ]);
    }

    // ------------------------------------------------------------------

    /** Contexto de gestor logueado [tenant, empleado, persona] o redirect a login. */
    private function ctx(string $slug): ?array
    {
        $ctx = $this->portal->ctxGestor($slug);
        // Plan vencido → bloquear todas las secciones del gestor.
        // Los callers redirigen a /portal/login, que reenvía a /portal/suspendida.
        if ($ctx && !plan_al_dia((int) $ctx[0]['id'])['ok']) {
            return null;
        }
        // Fuera de horario → igual: login reenvía a /portal/horario.
        if ($ctx && !en_horario($ctx[0])['ok']) {
            return null;
        }
        return $ctx;
    }

    /** Cliente de la cartera del gestor logueado, o null si no hay sesión/no es suyo. */
    private function clienteDelGestor(string $slug, int $id): ?array
    {
        $ctx = $this->ctx($slug);
        if (!$ctx) return null;
        [$tenant, $empleado] = $ctx;
        return $this->portal->clienteDeCartera((int) $tenant['id'], (int) $empleado['id'], $id);
    }

    /** Redirect apropiado cuando no hay sesión o el cliente no está en la cartera. */
    private function respuestaClienteInvalido(string $slug, int $id)
    {
        if (!$this->ctx($slug)) {
            return redirect()->to('/' . $slug . '/portal/login');
        }
        return redirect()->to('/' . $slug . '/portal/cartera')
            ->with('error', 'Cliente no encontrado en tu cartera.');
    }
}
