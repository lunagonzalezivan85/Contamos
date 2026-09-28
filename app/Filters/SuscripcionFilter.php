<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Suscripción al día — bloquea TODO el panel del tenant cuando el plan
 * está vencido/suspendido y redirige a la pantalla de pago
 * (/cuenta-suspendida). El superadmin y el propio endpoint de la
 * pantalla (fuera del grupo) no se ven afectados.
 */
class SuscripcionFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (!session('logged_in') || session('es_admin_sistema')) {
            return;
        }
        helper('plan');
        if (plan_al_dia()['ok']) {
            return;
        }
        return redirect()->to('/cuenta-suspendida');
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        // nada
    }
}
