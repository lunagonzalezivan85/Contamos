<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Admin\TenantService;

/**
 * Gestión de tenants de la plataforma (solo superadmin).
 * Listado, alta con provision automática y activar/suspender.
 */
class TenantController extends BaseController
{
    private TenantService $svc;

    public function __construct()
    {
        $this->svc = new TenantService();
    }

    /** GET /admin/tenants — listado de empresas. */
    public function index()
    {
        return view('admin/tenants/index', [
            'title'   => 'Tenants — Admin',
            'tenants' => $this->svc->listar(),
        ]);
    }

    /** GET /admin/tenants/nuevo — formulario de alta. */
    public function nuevo()
    {
        return view('admin/tenants/nuevo', [
            'title'  => 'Nuevo tenant — Admin',
            'planes' => $this->svc->planes(),
        ]);
    }

    /**
     * GET /admin/tenants/slug-sugerir?nombre=X[&slug=Y] — JSON.
     * nombre → primer slug libre derivado ("mi-empresa-2" si choca);
     * slug   → {ocupado} para el chequeo en vivo del form.
     */
    public function slugSugerir()
    {
        $nombre = trim((string) $this->request->getGet('nombre'));
        $slug   = trim((string) $this->request->getGet('slug'));

        return $this->response->setJSON([
            'ok'      => true,
            'slug'    => $nombre !== '' ? $this->svc->sugerirSlug($nombre) : null,
            'ocupado' => $slug !== '' ? $this->svc->slugOcupado($slug) : null,
        ]);
    }

    /** POST /admin/tenants — crea tenant + provision + admin. */
    public function guardar()
    {
        $r = $this->svc->crear([
            'nombre'          => $this->request->getPost('nombre'),
            'slug'            => $this->request->getPost('slug'),
            'email'           => $this->request->getPost('email'),
            'moneda'          => $this->request->getPost('moneda'),
            'tasa_interes'    => $this->request->getPost('tasa_interes'),
            'plazo_meses_max' => $this->request->getPost('plazo_meses_max'),
            'plan_id'         => $this->request->getPost('plan_id'),
            'contacto_nombre' => $this->request->getPost('contacto_nombre'),
            'admin_username'  => $this->request->getPost('admin_username'),
            'admin_password'  => $this->request->getPost('admin_password'),
        ]);

        if (!$r['ok']) {
            $msg = $r['error'];
            // Si el tenant se creó pero falló el admin, informamos ambos estados.
            if (!empty($r['id'])) {
                return redirect()->to('/admin/tenants')
                    ->with('warning', 'Tenant creado (id ' . $r['id'] . ') pero: ' . $msg);
            }
            return redirect()->back()->withInput()->with('error', $msg);
        }

        return redirect()->to('/admin/tenants')
            ->with('success', 'Tenant creado y provisionado correctamente.');
    }

    /** GET /admin/tenants/{id} — ficha del tenant con sus usuarios. */
    public function ver(int $id)
    {
        $t = $this->svc->detalle($id);
        if (!$t) {
            return redirect()->to('/admin/tenants')->with('error', 'Tenant no encontrado.');
        }
        return view('admin/tenants/ver', [
            'title'    => $t['nombre'] . ' — Admin',
            't'        => $t,
            'usuarios' => $this->svc->usuariosDe($id),
            'cobro'    => $this->svc->cobroMes($id),
            'saldo'    => $this->svc->saldoPendiente($id),
        ]);
    }

    /** POST /admin/tenants/{id}/toggle — activar/suspender. */
    public function toggle(int $id)
    {
        $err = $this->svc->toggle($id);
        return redirect()->back()
            ->with($err ? 'error' : 'success', $err ?: 'Estado del tenant actualizado.');
    }

    /** POST /admin/tenants/{id}/suscripcion — suspender/reactivar el plan (portal + app). */
    public function suscripcion(int $id)
    {
        $err = $this->svc->toggleSuscripcion($id);
        return redirect()->to('/admin/tenants/' . $id)
            ->with($err ? 'error' : 'success', $err ?: 'Suscripción actualizada.');
    }

    /** POST /admin/tenants/{id}/condiciones — día de pago + gracia (del contrato). */
    public function condiciones(int $id)
    {
        $err = $this->svc->condicionesSuscripcion($id, $this->request->getPost());
        return redirect()->to('/admin/tenants/' . $id)
            ->with($err ? 'error' : 'success', $err ?: 'Condiciones de cobro actualizadas.');
    }

    /** POST /admin/tenants/{id}/cobrar — registra el cobro del período (plan + extras). */
    public function cobrar(int $id)
    {
        $err = $this->svc->cobrarSuscripcion($id, $this->request->getPost(), (int) session('user_id'));
        return redirect()->to('/admin/tenants/' . $id)
            ->with($err ? 'error' : 'success', $err ?: 'Cobro de suscripción registrado.');
    }

    /**
     * GET /admin/tenants/{id}/exporte — Excel (SpreadsheetML) con toda la
     * cartera del tenant suspendido: clientes, créditos, pagos y suscripción.
     * Solo cuando está SUSPENDIDO y sin saldo pendiente (ya saldó).
     */
    public function exporte(int $id)
    {
        $t = $this->svc->detalle($id);
        if (!$t) return redirect()->to('/admin/tenants')->with('error', 'Tenant no encontrado.');

        if (($t['suscripcion_estado'] ?? 'ACTIVA') !== 'SUSPENDIDA') {
            return redirect()->to('/admin/tenants/' . $id)
                ->with('error', 'El exporte de datos aplica solo a tenants con suscripción suspendida.');
        }

        $pendiente = $this->svc->saldoPendiente($id);
        if ($pendiente > 0) {
            return redirect()->to('/admin/tenants/' . $id)
                ->with('error', 'Saldo pendiente USD ' . number_format($pendiente, 2) . ' — el cliente debe saldar antes del exporte.');
        }

        $d = $this->svc->exporteDatos($id);
        $xls = new \App\Libraries\SpreadsheetMl();

        $xls->hoja('Clientes', array_merge(
            [['Nombres', 'Apellidos', 'Cédula', 'Teléfono', 'Email', 'Límite crédito', 'Monto máx', 'Monto mín', 'Estado', 'Alta']],
            array_map(fn($c) => array_values($c), $d['clientes'])
        ));

        $xls->hoja('Créditos', array_merge(
            [['Código', 'Cliente', 'Monto sol.', 'Monto aprob.', 'Tasa %', 'Plazo', 'Frecuencia', 'Tipo', 'Estado', 'Desembolso', 'Saldo favor', 'Creado']],
            array_map(fn($c) => array_values($c), $d['creditos'])
        ));

        $xls->hoja('Pagos y abonos', array_merge(
            [['Crédito', 'Cliente', 'Monto', 'Método', 'Tipo', 'Fecha', 'Estado', 'Observación']],
            array_map(fn($p) => array_values($p), $d['pagos'])
        ));

        $xls->hoja('Suscripción', array_merge(
            [['Período', 'Monto', 'Moneda', 'Estado', 'Método', 'Referencia', 'Fecha pago', 'Observación']],
            array_map(fn($s) => [
                $s['periodo'], (float) $s['monto'], $s['moneda'], $s['estado'],
                $s['metodo'], $s['referencia'], $s['fecha_pago'], $s['observacion'],
            ], $d['suscripcion'])
        ));

        $this->svc->auditarExporte($id, (int) session('user_id'), 0.0);

        return $this->response
            ->setHeader('Content-Type', 'application/vnd.ms-excel; charset=UTF-8')
            ->download('exporte-' . $t['slug'] . '-' . date('Ymd') . '.xls', $xls->xml());
    }

    /** POST /admin/tenants/{tid}/usuarios/{uid}/quitar — quita usuario del tenant. */
    public function quitarUsuario(int $tenantId, int $userId)
    {
        $err = $this->svc->quitarUsuario($tenantId, $userId, (int) session('user_id'));
        return redirect()->to('/admin/tenants/' . $tenantId)
            ->with($err ? 'error' : 'success', $err ?: 'Usuario quitado del tenant.');
    }
}
