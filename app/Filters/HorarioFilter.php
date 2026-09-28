<?php

namespace App\Filters;

use App\Models\TenantModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Restringe el uso del sistema al rango horario del tenant (hora_inicio–hora_fin).
 * Solo aplica al rol 'gestor' — admins y demás roles entran siempre.
 */
class HorarioFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (session('role_slug') !== 'gestor') {
            return;
        }

        $tenant = (new TenantModel())->find((int) session('tenant_id'));
        $ini    = $tenant['hora_inicio'] ?? null;
        $fin    = $tenant['hora_fin'] ?? null;

        if (!$ini || !$fin) {
            return; // sin rango configurado = sin restricción
        }

        $ahora = date('H:i:s');
        if ($ahora < $ini || $ahora > $fin) {
            return redirect()->to('/dashboard')
                ->with('error', 'El sistema está disponible de '
                    . substr($ini, 0, 5) . ' a ' . substr($fin, 0, 5) . '.');
        }
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
