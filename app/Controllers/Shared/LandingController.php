<?php

namespace App\Controllers\Shared;

use App\Controllers\BaseController;
use App\Services\Shared\LandingService;

class LandingController extends BaseController
{
    /**
     * GET / — Landing page pública
     */
    public function index()
    {
        if (session('logged_in')) {
            return redirect()->to('/dashboard');
        }

        return view('landing/index', [
            'title' => 'Contamos — Control de Préstamos',
        ]);
    }

    /**
     * POST /solicitar-acceso — formulario "Solicita tu usuario" de la landing
     */
    public function solicitarAcceso()
    {
        $rules = [
            'nombre'   => 'required|min_length[3]|max_length[120]',
            'negocio'  => 'required|min_length[2]|max_length[160]',
            'telefono' => 'required|min_length[7]|max_length[30]',
            'correo'   => 'permit_empty|valid_email|max_length[160]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->to('/#contacto')->withInput()
                ->with('acceso_error', 'Revisa los datos del formulario e inténtalo de nuevo.');
        }

        (new LandingService())->solicitarAcceso(
            $this->request->getPost(['nombre', 'negocio', 'telefono', 'correo'])
        );

        return redirect()->to('/#contacto')
            ->with('acceso_ok', 'Solicitud recibida — te contactaremos para crear tu usuario.');
    }
}
