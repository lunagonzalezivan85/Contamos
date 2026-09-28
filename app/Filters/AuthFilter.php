<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Protege rutas que requieren sesión autenticada.
 * Uso en Routes.php: $routes->group('', ['filter' => 'auth'], ...)
 */
class AuthFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session('logged_in')) {
            return redirect()->to('/login')
                ->with('error', 'Debe iniciar sesión.');
        }

        // Si se pasa un permiso como argumento: ['filter' => 'auth:solicitudes.aprobar']
        if ($arguments) {
            $permisos = session('permisos') ?? [];
            foreach ($arguments as $permiso) {
                if (!in_array($permiso, $permisos, true)) {
                    return redirect()->to('/dashboard')
                        ->with('error', 'No tiene permiso para acceder a esta sección.');
                }
            }
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }
}
