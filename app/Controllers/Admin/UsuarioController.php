<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Models\UserModel;
use Config\Database;

/**
 * Usuarios de todos los tenants (vista del superadmin):
 * listado, alta, edición, activar/desactivar y reseteo de clave.
 */
class UsuarioController extends BaseController
{
    private function db() { return Database::connect(); }

    /** GET /admin/usuarios */
    public function index()
    {
        $db      = $this->db();
        $buscar  = trim((string) $this->request->getGet('q'));
        $tenantF = (int) $this->request->getGet('tenant');

        $b = $db->table('users u')
            ->select('u.id, u.username, u.email, u.nombre, u.estado, u.ultimo_login, u.created_at,
                      t.nombre AS tenant, r.nombre AS rol, r.slug AS rol_slug')
            ->join('tenants t', 't.id = u.tenant_id', 'left')
            ->join('roles r', 'r.id = u.role_id', 'left')
            ->where('u.deleted_at IS NULL', null, false);
        if ($tenantF > 0) $b->where('u.tenant_id', $tenantF);
        if ($buscar !== '') {
            $b->groupStart()
                ->like('u.username', $buscar)
                ->orLike('u.nombre', $buscar)
                ->orLike('u.email', $buscar)
                ->groupEnd();
        }
        $usuarios = $b->orderBy('u.tenant_id')->orderBy('u.username')->get()->getResultArray();

        return view('admin/usuarios/index', [
            'title'    => 'Usuarios — Admin',
            'usuarios' => $usuarios,
            'tenants'  => $db->table('tenants')->select('id, nombre')->orderBy('nombre')->get()->getResultArray(),
            'buscar'   => $buscar,
            'tenantF'  => $tenantF,
        ]);
    }

    /** GET /admin/usuarios/nuevo?tenant={id} — el tenant viene preseleccionado desde su ficha. */
    public function nuevo()
    {
        return view('admin/usuarios/nuevo', [
            'title'    => 'Nuevo usuario — Admin',
            'tenants'  => $this->tenantsActivos(),
            'roles'    => $this->rolesGlobales(),
            'tenantSel'=> (int) $this->request->getGet('tenant'),
            'back'     => $this->backTo(),
        ]);
    }

    /** POST /admin/usuarios — crea el usuario. */
    public function guardar()
    {
        $d       = $this->request->getPost();
        $user    = strtolower(trim((string) ($d['username'] ?? '')));
        $email   = trim((string) ($d['email'] ?? ''));
        $nombre  = trim((string) ($d['nombre'] ?? ''));
        $tenantId = (int) ($d['tenant_id'] ?? 0);
        $roleId  = (int) ($d['role_id'] ?? 0);
        $pass    = (string) ($d['password'] ?? '');

        if ($user === '' || strlen($user) < 3) {
            return $this->backError('El usuario debe tener al menos 3 caracteres.');
        }
        if ($pass === '' || strlen($pass) < 6) {
            return $this->backError('La contraseña debe tener al menos 6 caracteres.');
        }
        if ($pass !== (string) ($d['password2'] ?? '')) {
            return $this->backError('Las contraseñas no coinciden.');
        }
        if (!$this->db()->table('tenants')->where('id', $tenantId)->countAllResults()) {
            return $this->backError('Tenant inválido.');
        }
        // Primer usuario del tenant → siempre Administrador (regla de negocio)
        $tieneUsuarios = $this->db()->table('users')
            ->where('tenant_id', $tenantId)
            ->where('deleted_at IS NULL', null, false)
            ->countAllResults() > 0;
        if (!$tieneUsuarios) {
            $roleId = (int) ($this->db()->table('roles')
                ->where('slug', 'admin')->get()->getRowArray()['id'] ?? $roleId);
        }
        if (!$this->db()->table('roles')->where('id', $roleId)->where('estado', 'ACTIVO')->countAllResults()) {
            return $this->backError('Rol inválido.');
        }
        if ($this->db()->table('users')->where('tenant_id', $tenantId)->where('username', $user)->countAllResults()) {
            return $this->backError('Ese usuario ya existe en el tenant.');
        }
        if ($email !== '' && $this->db()->table('users')->where('tenant_id', $tenantId)->where('email', $email)->countAllResults()) {
            return $this->backError('Ese correo ya existe en el tenant.');
        }

        (new UserModel())->insert([
            'tenant_id'             => $tenantId,
            'role_id'               => $roleId,
            'username'              => $user,
            'email'                 => $email !== '' ? $email : $user . '@local',
            'nombre'                => $nombre !== '' ? $nombre : $user,
            'password_hash'         => password_hash($pass, PASSWORD_DEFAULT),
            'estado'                => 'ACTIVO',
            'debe_cambiar_password' => 1,
            'created_by'            => (int) session('user_id'),
        ]);

        // Cargo extra: usuarios activos por encima de lo incluido en el plan (+USD 3 c/u)
        helper('plan');
        $cobro  = plan_cobro_mes($tenantId);
        $aviso  = "Usuario «{$user}» creado. Debe cambiar la clave al entrar.";
        if ($cobro['extra'] > 0) {
            return redirect()->to($this->backTo() ?? 'admin/usuarios')
                ->with('warning', $aviso . " El tenant ya lleva {$cobro['extra']} usuario(s) extra — "
                    . 'se suman USD ' . number_format($cobro['monto_extra'], 2) . ' al cobro mensual.');
        }

        return redirect()->to($this->backTo() ?? 'admin/usuarios')
            ->with('success', $aviso);
    }

    /** GET /admin/usuarios/{id}/editar */
    public function editar(int $id)
    {
        $usuario = (new UserModel())->find($id);
        if (!$usuario) {
            return redirect()->to('admin/usuarios')->with('error', 'Usuario no encontrado.');
        }
        return view('admin/usuarios/editar', [
            'title'   => 'Editar usuario — Admin',
            'usuario' => $usuario,
            'tenants' => $this->tenantsActivos(),
            'roles'   => $this->rolesGlobales(),
            'back'    => $this->backTo(),
        ]);
    }

    /** POST /admin/usuarios/{id} — datos del usuario (sin clave). */
    public function actualizar(int $id)
    {
        $users = new UserModel();
        $usuario = $users->find($id);
        if (!$usuario) {
            return redirect()->to('admin/usuarios')->with('error', 'Usuario no encontrado.');
        }

        $d       = $this->request->getPost();
        $email   = trim((string) ($d['email'] ?? ''));
        $nombre  = trim((string) ($d['nombre'] ?? ''));
        $roleId  = (int) ($d['role_id'] ?? 0);
        $tenantId = (int) ($d['tenant_id'] ?? $usuario['tenant_id']);

        if (!$this->db()->table('tenants')->where('id', $tenantId)->countAllResults()) {
            return $this->backError('Tenant inválido.');
        }
        if (!$this->db()->table('roles')->where('id', $roleId)->where('estado', 'ACTIVO')->countAllResults()) {
            return $this->backError('Rol inválido.');
        }
        // Único (tenant,email) sin contar al propio usuario
        if ($email !== '' && $this->db()->table('users')
                ->where('tenant_id', $tenantId)->where('email', $email)
                ->where('id !=', $id)->countAllResults()) {
            return $this->backError('Ese correo ya lo usa otro usuario del tenant.');
        }

        $users->update($id, [
            'tenant_id'  => $tenantId,
            'role_id'    => $roleId,
            'email'      => $email !== '' ? $email : $usuario['email'],
            'nombre'     => $nombre !== '' ? $nombre : $usuario['username'],
            'updated_by' => (int) session('user_id'),
        ]);

        return redirect()->to($this->backTo() ?? 'admin/usuarios')
            ->with('success', 'Usuario actualizado.');
    }

    /** POST /admin/usuarios/{id}/toggle — ACTIVO ⇄ INACTIVO. */
    public function toggle(int $id)
    {
        $users   = new UserModel();
        $usuario = $users->find($id);
        if (!$usuario) {
            return redirect()->to('admin/usuarios')->with('error', 'Usuario no encontrado.');
        }
        // No dejarse fuera: el superadmin no se puede desactivar a sí mismo
        if ($id === (int) session('user_id')) {
            return redirect()->to('admin/usuarios')->with('error', 'No puede desactivar su propio usuario.');
        }
        $nuevo = $usuario['estado'] === 'ACTIVO' ? 'INACTIVO' : 'ACTIVO';
        $users->update($id, ['estado' => $nuevo, 'updated_by' => (int) session('user_id')]);

        return redirect()->to($this->backTo() ?? 'admin/usuarios')
            ->with('success', "Usuario «{$usuario['username']}» ahora está {$nuevo}.");
    }

    /** POST /admin/usuarios/{id}/clave — reset de contraseña. */
    public function resetClave(int $id)
    {
        $users   = new UserModel();
        $usuario = $users->find($id);
        if (!$usuario) {
            return redirect()->to('admin/usuarios')->with('error', 'Usuario no encontrado.');
        }
        $pass = (string) $this->request->getPost('password');
        if ($pass === '' || strlen($pass) < 6) {
            return $this->backError('La contraseña debe tener al menos 6 caracteres.');
        }
        if ($pass !== (string) $this->request->getPost('password2')) {
            return $this->backError('Las contraseñas no coinciden.');
        }
        $users->update($id, [
            'password_hash'         => password_hash($pass, PASSWORD_DEFAULT),
            'debe_cambiar_password' => 1,
            'updated_by'            => (int) session('user_id'),
        ]);

        return redirect()->to($this->backTo() ?? 'admin/usuarios')
            ->with('success', "Clave de «{$usuario['username']}» restablecida — debe cambiarla al entrar.");
    }

    /**
     * POST /admin/usuarios/{id}/reset-rapido — genera una clave temporal
     * aleatoria y la entrega en flashdata (se muestra una sola vez, para
     * copiarla/compartirla con el usuario).
     */
    public function resetRapido(int $id)
    {
        $users   = new UserModel();
        $usuario = $users->find($id);
        $ajax    = $this->request->isAJAX();
        $dest    = $this->backTo() ?? 'admin/usuarios';
        $fail    = fn (string $msg) => $ajax
            ? $this->response->setStatusCode(400)->setJSON(['ok' => false, 'error' => $msg])
            : redirect()->to($dest)->with('error', $msg);

        if (!$usuario) {
            return $fail('Usuario no encontrado.');
        }
        if ($id === (int) session('user_id')) {
            return $fail('Para su propia clave use «Cambiar contraseña» en su perfil.');
        }
        $pass = $this->claveTemporal();
        $users->update($id, [
            'password_hash'         => password_hash($pass, PASSWORD_DEFAULT),
            'debe_cambiar_password' => 1,
            'updated_by'            => (int) session('user_id'),
        ]);
        if ($ajax) {
            return $this->response->setJSON([
                'ok'       => true,
                'username' => $usuario['username'],
                'pass'     => $pass,
            ]);
        }
        return redirect()->to($dest)
            ->with('pass_reset', ['username' => $usuario['username'], 'pass' => $pass]);
    }

    /** Clave temporal legible, sin caracteres ambiguos (0/O, 1/l/I). */
    private function claveTemporal(): string
    {
        $chars = 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789';
        $out   = '';
        $max   = strlen($chars) - 1;
        for ($i = 0; $i < 8; $i++) {
            $out .= $chars[random_int(0, $max)];
        }
        return $out;
    }

    // -----------------------------------------------------------------

    private function tenantsActivos(): array
    {
        return $this->db()->table('tenants')->select('id, nombre')
            ->where('estado', 'ACTIVO')->orderBy('nombre')->get()->getResultArray();
    }

    private function rolesGlobales(): array
    {
        return $this->db()->table('roles')->select('id, nombre, slug')
            ->where('estado', 'ACTIVO')->orderBy('id')->get()->getResultArray();
    }

    private function backError(string $msg)
    {
        return redirect()->back()->withInput()->with('error', $msg);
    }

    /** Vuelve a la ficha del tenant si el form venía de ahí (?back= o campo back). */
    private function backTo(): ?string
    {
        $back = trim((string) ($this->request->getPost('back') ?? $this->request->getGet('back')));
        return preg_match('#^admin/tenants/\d+$#', $back) ? $back : null;
    }
}
