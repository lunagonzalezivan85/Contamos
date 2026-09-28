<?php

namespace App\Services\Shared;

use App\Models\RoleModel;
use App\Models\TenantModel;
use App\Models\UserModel;

/**
 * Servicio de autenticación multi-tenant.
 * Resuelve tenant, verifica credenciales con password_verify,
 * registra intentos en login_attempts y construye la sesión.
 */
class AuthService
{
    protected UserModel $users;
    protected TenantModel $tenants;
    protected RoleModel $roles;
    protected $db;

    public function __construct()
    {
        $this->users   = new UserModel();
        $this->tenants = new TenantModel();
        $this->roles   = new RoleModel();
        $this->db      = \Config\Database::connect();
    }

    /**
     * Intenta autenticar un usuario.
     * El tenant se identifica automáticamente a partir del username —
     * el usuario no selecciona empresa.
     *
     * @return array{ok: bool, error?: string, user?: array, tenant?: array}
     */
    public function attempt(string $username, string $password): array
    {
        $user = $this->users->findByUsernameGlobal(trim($username));

        // Mensaje genérico: no revelar si el usuario existe
        if (!$user || !password_verify($password, $user['password_hash'])) {
            $this->logAttempt($user['tenant_id'] ?? null, $user['id'] ?? null, $username, false);
            return ['ok' => false, 'error' => 'Credenciales incorrectas.'];
        }

        $tenant = $this->tenants->find($user['tenant_id']);

        if (!$tenant || $tenant['estado'] !== 'ACTIVO') {
            $this->logAttempt($user['tenant_id'], $user['id'], $username, false);
            return ['ok' => false, 'error' => 'Su empresa está inactiva. Contacte al administrador.'];
        }

        $this->logAttempt($tenant['id'], $user['id'], $username, true);
        $this->users->update($user['id'], ['ultimo_login' => date('Y-m-d H:i:s')]);

        return ['ok' => true, 'user' => $user, 'tenant' => $tenant];
    }

    /**
     * Construye los datos de sesión del usuario autenticado.
     * El menú NO se guarda en sesión — se resuelve por request
     * vía MenuService/menu_items() para reflejar Config/Menu.php al instante.
     */
    public function buildSession(array $user, array $tenant): array
    {
        $roleId    = (int) $user['role_id'];
        $tenantId  = (int) $tenant['id'];
        $rol       = $this->roles->find($roleId);
        $esSistema = ($rol['slug'] ?? '') === 'superadmin';

        return [
            'user_id'          => (int) $user['id'],
            'tenant_id'        => $tenantId,
            'tenant_slug'      => $tenant['slug'],
            'tenant_name'      => $tenant['nombre'],
            'username'         => $user['username'],
            'nombre'           => $user['nombre'],
            'email'            => $user['email'],
            'role_id'          => $roleId,
            'role_slug'        => $rol['slug'] ?? '',
            'es_admin_sistema' => $esSistema,
            'permisos'         => $this->roles->permissionCodes($roleId, $tenantId),
            'debe_cambiar_password' => (bool) $user['debe_cambiar_password'],
            'logged_in'        => true,
        ];
    }

    /**
     * Cambia la contraseña de un usuario verificando la actual.
     *
     * @return array{ok: bool, error?: string}
     */
    public function changePassword(int $userId, string $actual, string $nueva): array
    {
        $user = $this->users->find($userId);

        if (!$user) {
            return ['ok' => false, 'error' => 'Usuario no encontrado.'];
        }

        if (!password_verify($actual, $user['password_hash'])) {
            return ['ok' => false, 'error' => 'La contraseña actual es incorrecta.'];
        }

        $this->users->update($userId, [
            'password_hash'         => password_hash($nueva, PASSWORD_DEFAULT),
            'debe_cambiar_password' => 0,
        ]);

        return ['ok' => true];
    }

    /**
     * Verifica si el usuario en sesión tiene un permiso.
     */
    public static function can(string $permiso): bool
    {
        $permisos = session('permisos') ?? [];
        return in_array($permiso, $permisos, true);
    }

    /**
     * Registra el intento de login (auditoría).
     */
    protected function logAttempt(?int $tenantId, ?int $userId, string $username, bool $exito): void
    {
        $request = service('request');

        $this->db->table('login_attempts')->insert([
            'tenant_id'  => $tenantId,
            'user_id'    => $userId,
            'username'   => mb_substr($username, 0, 100),
            'ip'         => $request->getIPAddress(),
            'user_agent' => mb_substr((string) $request->getUserAgent(), 0, 255),
            'exito'      => $exito ? 1 : 0,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
    }
}
