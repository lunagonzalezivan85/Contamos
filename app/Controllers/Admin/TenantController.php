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
        ]);
    }

    /** POST /admin/tenants/{id}/toggle — activar/suspender. */
    public function toggle(int $id)
    {
        $err = $this->svc->toggle($id);
        return redirect()->back()
            ->with($err ? 'error' : 'success', $err ?: 'Estado del tenant actualizado.');
    }

    /** POST /admin/tenants/{id}/cobrar — registra el cobro del período (plan + extras). */
    public function cobrar(int $id)
    {
        $err = $this->svc->cobrarSuscripcion($id, $this->request->getPost(), (int) session('user_id'));
        return redirect()->to('/admin/tenants/' . $id)
            ->with($err ? 'error' : 'success', $err ?: 'Cobro de suscripción registrado.');
    }

    /** POST /admin/tenants/{tid}/usuarios/{uid}/quitar — quita usuario del tenant. */
    public function quitarUsuario(int $tenantId, int $userId)
    {
        $err = $this->svc->quitarUsuario($tenantId, $userId, (int) session('user_id'));
        return redirect()->to('/admin/tenants/' . $tenantId)
            ->with($err ? 'error' : 'success', $err ?: 'Usuario quitado del tenant.');
    }
}
