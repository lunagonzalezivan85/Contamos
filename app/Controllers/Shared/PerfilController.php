<?php

namespace App\Controllers\Shared;

use App\Controllers\BaseController;

use App\Services\Shared\AuthService;

class PerfilController extends BaseController
{
    protected AuthService $auth;

    public function __construct()
    {
        $this->auth = new AuthService();
    }

    /**
     * GET /perfil/cambiar-password
     */
    public function cambiarPassword()
    {
        return view('shared/perfil/cambiar_password', [
            'title'    => 'Cambiar Contraseña — Contamos',
            'obligado' => (bool) session('debe_cambiar_password'),
        ]);
    }

    /**
     * POST /perfil/cambiar-password
     */
    public function actualizarPassword()
    {
        $rules = [
            'password_actual'   => 'required',
            'password_nuevo'    => 'required|min_length[8]|max_length[100]',
            'password_confirm'  => 'required|matches[password_nuevo]',
        ];

        $errors = [
            'password_nuevo'   => ['min_length' => 'La nueva contraseña debe tener al menos 8 caracteres.'],
            'password_confirm' => ['matches'    => 'Las contraseñas no coinciden.'],
        ];

        if (!$this->validate($rules, $errors)) {
            return redirect()->back()->withInput()
                ->with('error', implode(' ', $this->validator->getErrors()));
        }

        $result = $this->auth->changePassword(
            (int) session('user_id'),
            (string) $this->request->getPost('password_actual'),
            (string) $this->request->getPost('password_nuevo')
        );

        if (!$result['ok']) {
            return redirect()->back()->with('error', $result['error']);
        }

        session()->set('debe_cambiar_password', false);

        return redirect()->to('/dashboard')
            ->with('success', 'Contraseña actualizada correctamente.');
    }
}
