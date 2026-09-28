<?php

namespace App\Controllers\Shared;

use App\Controllers\BaseController;

use App\Services\Shared\AuthService;

class AuthController extends BaseController
{
    protected AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    /**
     * GET /login — formulario de acceso
     */
    public function login()
    {
        if (session('logged_in')) {
            return redirect()->to($this->homeByRole());
        }

        return view('shared/auth/login', [
            'title' => 'Iniciar Sesión — Contamos',
        ]);
    }

    /**
     * POST /login — procesa credenciales
     */
    public function attempt()
    {
        $rules = [
            'username' => 'required|max_length[100]',
            'password' => 'required|max_length[100]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->back()->withInput()
                ->with('error', 'Ingrese usuario y contraseña.');
        }

        $username = (string) $this->request->getPost('username');
        $password = (string) $this->request->getPost('password');

        $result = $this->auth->attempt($username, $password);

        if (!$result['ok']) {
            return redirect()->back()->withInput(['username' => $username])
                ->with('error', $result['error']);
        }

        session()->set($this->auth->buildSession($result['user'], $result['tenant']));
        session()->regenerate(true); // previene fijación de sesión

        if ($result['user']['debe_cambiar_password']) {
            return redirect()->to('/perfil/cambiar-password')
                ->with('warning', 'Debe cambiar su contraseña antes de continuar.');
        }

        return redirect()->to($this->homeByRole());
    }

    /**
     * Página de inicio según el rol: superadmin → panel admin, resto → dashboard tenant.
     */
    protected function homeByRole(): string
    {
        return session('es_admin_sistema') ? '/admin/estadisticas' : '/dashboard';
    }

    /**
     * GET /logout
     */
    public function logout()
    {
        session()->destroy();
        return redirect()->to('/login');
    }
}
