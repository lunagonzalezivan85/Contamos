<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\PagoModel;
use App\Services\Partner\PagoService;
use App\Services\Partner\PortalService;
use App\Services\Partner\SolicitudService;

/**
 * API para la app móvil (carpeta IONIC/): handshake y login del gestor.
 * Rutas: GET /{slug}/connect · POST /{slug}/connect/login · OPTIONS ambas.
 */
class ConnectController extends BaseController
{
    private PortalService $portal;

    public function __construct()
    {
        $this->portal = new PortalService();
    }

    /** GET /{slug}/connect — handshake: valida que la URL tenga un tenant activo. */
    public function index(string $slug)
    {
        $this->cors();
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => 'Empresa no encontrada.']);
        }

        return $this->response->setJSON([
            'ok'     => true,
            'tenant' => [
                'slug'   => $slug,
                'nombre' => $tenant['nombre'],
                'moneda' => $tenant['moneda'] ?? 'C$',
                'logo'   => !empty($tenant['logo'])
                    ? base_url('public/uploads/logos/' . $tenant['logo']) : null,
            ],
            'suspendida' => !plan_al_dia((int) $tenant['id'])['ok'],
        ]);
    }

    /** POST /{slug}/connect/login — carnet + PIN del gestor → token de 30 días. */
    public function login(string $slug)
    {
        $this->cors();
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => 'Empresa no encontrada.']);
        }

        // Acepta form-urlencoded o JSON (getJSON explota si el body no es JSON)
        $body   = str_contains($this->request->getHeaderLine('Content-Type'), 'json')
            ? ($this->request->getJSON(true) ?: []) : [];
        $carnet = (string) ($this->request->getPost('carnet') ?? $body['carnet'] ?? '');
        $pin    = (string) ($this->request->getPost('pin')    ?? $body['pin']    ?? '');

        $r = $this->portal->autenticarGestor($tenant, $carnet, $pin);
        if (!$r['ok']) {
            return $this->response->setStatusCode(401)
                ->setJSON(['ok' => false, 'error' => $r['error']]);
        }

        if (!plan_al_dia((int) $tenant['id'])['ok']) {
            return $this->response->setStatusCode(403)
                ->setJSON(['ok' => false, 'error' => 'Servicio suspendido — contactá a tu empresa.']);
        }

        $emp     = $r['empleado'];
        $persona = $this->portal->personaDeEmpleado((int) $emp['id']);

        return $this->response->setJSON([
            'ok'     => true,
            'token'  => $this->portal->tokenApp((int) $emp['id'], (int) $tenant['id']),
            'gestor' => [
                'id'     => (int) $emp['id'],
                'carnet' => $emp['carnet'],
                'cargo'  => $emp['cargo'] ?? '',
                'nombre' => $persona['nombre'] ?? $emp['carnet'],
            ],
            'tenant' => [
                'slug'   => $slug,
                'nombre' => $tenant['nombre'],
                'moneda' => $tenant['moneda'] ?? 'C$',
                'logo'   => !empty($tenant['logo'])
                    ? base_url('public/uploads/logos/' . $tenant['logo']) : null,
            ],
        ]);
    }

    /** GET /{slug}/connect/notificaciones — avisos del gestor (Bearer token). */
    public function notificaciones(string $slug)
    {
        $this->cors();
        [$tenant, $empleadoId] = $this->auth($slug);
        if (!$empleadoId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $items = $this->portal->notificacionesGestor((int) $tenant['id'], $empleadoId);
        return $this->response->setJSON([
            'ok'       => true,
            'noLeidas' => count($items),
            'items'    => $items,
        ]);
    }

    /** GET /{slug}/connect/home — resumen del día del gestor para el home de la app. */
    public function home(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $tid      = (int) $tenant['id'];
        $creditos = $this->portal->cobrosDelGestor($tid, $empId);
        $ruta     = $this->portal->rutaCobrosHoy($tid, $empId);

        return $this->response->setJSON([
            'ok' => true,
            'resumen' => $this->portal->resumenCobrosHoy($tid, $empId, $creditos),
            'paradas'     => count($ruta),
            'desembolsos' => count($this->portal->desembolsosPendientes($tid, $empId)['solicitudes']),
            'avisos'      => count($this->portal->notificacionesGestor($tid, $empId)),
        ]);
    }

    /** GET /{slug}/connect/cartera — clientes de la cartera del gestor. */
    public function cartera(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        return $this->response->setJSON([
            'ok'       => true,
            'clientes' => $this->portal->carteraGestor((int) $tenant['id'], $empId)['clientes'],
        ]);
    }

    /** GET /{slug}/connect/cliente/{id} — ficha del cliente (persona + secciones + cobros). */
    public function cliente(string $slug, int $id)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $ficha = $this->portal->fichaCliente((int) $tenant['id'], $empId, $id);
        if (!$ficha) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => 'Cliente no está en tu cartera.']);
        }
        return $this->response->setJSON(['ok' => true] + $ficha);
    }

    /** GET /{slug}/connect/cobros — créditos con cuotas por cobrar + resumen del día. */
    public function cobros(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $tid      = (int) $tenant['id'];
        $creditos = $this->portal->cobrosDelGestor($tid, $empId);
        return $this->response->setJSON([
            'ok'       => true,
            'creditos' => $creditos,
            'resumen'  => $this->portal->resumenCobrosHoy($tid, $empId, $creditos),
            'metodos'  => PagoModel::METODOS_LBL,
        ]);
    }

    /** POST /{slug}/connect/cobros/{sol}/abonar — cobro en campo (queda en revisión). */
    public function abonarCobro(string $slug, int $solId)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $r = $this->portal->registrarCobro((int) $tenant['id'], $empId, $solId, $this->datos());
        if (!$r['ok']) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['error']]);
        }
        return $this->response->setJSON(['ok' => true, 'pago_id' => $r['pago_id']]);
    }

    /** GET /{slug}/connect/cobros/{pago}/recibo — datos del voucher del cobro. */
    public function reciboCobro(string $slug, int $pagoId)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $data = (new PagoService())->reciboPago((int) $tenant['id'], $pagoId);
        // Solo cobros propios y vigentes (revisión o aplicados)
        if (!$data || (int) ($data['pago']['empleado_id'] ?? 0) !== $empId
            || !in_array($data['pago']['estado'], ['REVISION', 'APLICADO'], true)) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => 'Recibo no encontrado.']);
        }
        return $this->response->setJSON([
            'ok' => true, 'data' => $data,
            'reciboNum' => PagoModel::reciboCode($data['pago'], $tenant['nombre']),
            'metodos'   => PagoModel::METODOS_LBL,
            'lblEstado' => PagoModel::LABEL_ESTADO,
        ]);
    }

    /** GET /{slug}/connect/ruta — paradas de cobro del día (GPS + monto). */
    public function ruta(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        return $this->response->setJSON([
            'ok'      => true,
            'paradas' => $this->portal->rutaCobrosHoy((int) $tenant['id'], $empId),
        ]);
    }

    /** GET /{slug}/connect/desembolsos — desembolsos aprobados por entregar. */
    public function desembolsos(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        return $this->response->setJSON([
            'ok' => true,
            'solicitudes' => $this->portal->desembolsosPendientes((int) $tenant['id'], $empId)['solicitudes'],
        ]);
    }

    /** POST /{slug}/connect/desembolsos/{id}/entregar — activa el crédito al entregar el dinero. */
    public function entregarDesembolso(string $slug, int $id)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $r = $this->portal->entregarDesembolso((int) $tenant['id'], $empId, $id);
        if (!$r['ok']) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['error']]);
        }
        return $this->response->setJSON(['ok' => true, 'codigo' => $r['codigo']]);
    }

    /** POST /{slug}/connect/solicitud — crea una solicitud (CREADA) asignada al gestor. */
    public function crearSolicitud(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $empleado = $this->portal->empleadoDeId($empId);
        if (!$empleado) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $r = $this->portal->crearSolicitudGestor($tenant, $empleado, $this->datos());
        if (!$r['ok']) {
            return $this->response->setStatusCode(422)->setJSON(['ok' => false, 'error' => $r['error']]);
        }
        return $this->response->setJSON(['ok' => true]);
    }

    /** GET /{slug}/connect/actividad — solicitudes creadas por el gestor. */
    public function actividad(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        return $this->response->setJSON([
            'ok' => true,
            'solicitudes' => $this->portal->actividadGestor((int) $tenant['id'], $empId)['solicitudes'],
        ]);
    }

    /** GET /{slug}/connect/arqueo[?fecha=] — "Mi caja": resumen del día + arqueos. */
    public function arqueo(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $f     = (string) $this->request->getGet('fecha');
        $fecha = preg_match('/^\d{4}-\d{2}-\d{2}$/', $f) ? $f : date('Y-m-d');
        return $this->response->setJSON([
            'ok' => true] + $this->portal->arqueoGestor((int) $tenant['id'], $empId, $fecha));
    }

    /** GET /{slug}/connect/simulador — plan de cuotas (tasa tope del tenant, método heredado). */
    public function simulador(string $slug)
    {
        $this->cors();
        [$tenant, $empId] = $this->auth($slug);
        if (!$empId) return $this->response->setStatusCode(401)->setJSON(['ok' => false]);

        $d = $this->request->getGet();
        $sol = [
            'monto'             => (float) ($d['monto'] ?? 0),
            'tasa_mensual'      => min(max(0.0, (float) ($d['tasa_mensual'] ?? $tenant['tasa_interes'] ?? 0)),
                                       (float) ($tenant['tasa_interes'] ?? 0)),
            'plazo_meses'       => (int) ($d['plazo_meses'] ?? 1),
            'frecuencia'        => (string) ($d['frecuencia'] ?? 'M'),
            'dias_semana'       => (int) ($d['dias_semana'] ?? 3),
            'paso_dias'         => (int) ($d['paso_dias'] ?? 0),
            'tipo_calculo'      => $tenant['tipo_calculo'] ?? 'FLAT',
            'gracia_meses'      => (int) ($d['gracia_meses'] ?? 0),
            'gracia_tipo'       => (string) ($d['gracia_tipo'] ?? 'TOTAL'),
            'fecha_primer_pago' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) ($d['fecha_primer_pago'] ?? ''))
                ? $d['fecha_primer_pago'] : date('Y-m-d'),
        ];

        return $this->response->setJSON([
            'ok' => true, 'entrada' => $sol,
            'plan' => (new SolicitudService())->planPagos($sol),
        ]);
    }

    /** GET /resolve/{codigo} — código corto (CONT-8492) → tenant de la app. */
    public function resolve(string $codigo)
    {
        $this->cors();
        $tenant = $this->portal->tenantPorCodigo(strtoupper(trim($codigo)));
        if (!$tenant) {
            return $this->response->setStatusCode(404)
                ->setJSON(['ok' => false, 'error' => 'Código no reconocido — revisalo con tu empresa.']);
        }

        return $this->response->setJSON([
            'ok'   => true,
            'slug' => $tenant['slug'],
            'tenant' => [
                'slug'   => $tenant['slug'],
                'nombre' => $tenant['nombre'],
                'moneda' => $tenant['moneda'] ?? 'C$',
                'logo'   => !empty($tenant['logo'])
                    ? base_url('public/uploads/logos/' . $tenant['logo']) : null,
            ],
        ]);
    }

    /** OPTIONS /{slug}/connect[/*] — preflight CORS de la app. */
    public function opciones()
    {
        $this->cors();
        return $this->response->setStatusCode(204);
    }

    /** Body del POST como array: form-urlencoded o JSON. */
    private function datos(): array
    {
        if (str_contains($this->request->getHeaderLine('Content-Type'), 'json')) {
            return $this->request->getJSON(true) ?: [];
        }
        return $this->request->getPost() ?: [];
    }

    /** Tenant + empleado_id desde el Bearer token; [null,null] si inválido. */
    private function auth(string $slug): array
    {
        $tenant = $this->portal->tenantPorSlug($slug);
        if (!$tenant) return [null, null];
        $hdr = (string) $this->request->getHeaderLine('Authorization');
        if (!preg_match('/^Bearer\s+(\S+)$/', $hdr, $m)) return [null, null];
        $empId = $this->portal->tokenAppValido($m[1], (int) $tenant['id']);
        return $empId ? [$tenant, $empId] : [null, null];
    }

    private function cors(): void
    {
        $this->response
            ->setHeader('Access-Control-Allow-Origin', '*')
            ->setHeader('Access-Control-Allow-Methods', 'GET, POST, OPTIONS')
            ->setHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization')
            ->setHeader('Access-Control-Max-Age', '86400');
    }
}
