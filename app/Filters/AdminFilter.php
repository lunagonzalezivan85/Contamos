<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Protege rutas /admin/* — solo el administrador del sistema (superadmin).
 * Debe aplicarse después de 'auth' o solo (incluye la verificación de sesión).
 */
class AdminFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session('logged_in')) {
            return redirect()->to('/login')
                ->with('error', 'Debe iniciar sesión.');
        }

        if (!session('es_admin_sistema')) {
            return redirect()->to('/dashboard')
                ->with('error', 'Acceso restringido al administrador del sistema.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }
}
