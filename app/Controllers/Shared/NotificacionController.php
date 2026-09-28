<?php

namespace App\Controllers\Shared;

use App\Controllers\BaseController;
use App\Services\Shared\NotificacionService;

/**
 * Notificaciones del usuario — endpoint JSON para el panel derecho.
 * Lógica en Services/Shared/NotificacionService.
 */
class NotificacionController extends BaseController
{
    private NotificacionService $svc;

    public function __construct()
    {
        $this->svc = new NotificacionService();
    }

    /** GET /notificaciones — lista JSON para el panel. */
    public function index()
    {
        return $this->response->setJSON(
            ['ok' => true] + $this->svc->bandeja(
                (int) session('tenant_id'), (int) session('user_id')
            )
        );
    }

    /** POST /notificaciones/{id}/leida — marca una notificación como leída. */
    public function leida(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $userId   = (int) session('user_id');

        if (!$this->svc->marcarLeida($tenantId, $userId, $id)) {
            return $this->response->setStatusCode(404)->setJSON(['ok' => false]);
        }

        return $this->response->setJSON([
            'ok'       => true,
            'noLeidas' => $this->svc->noLeidas($tenantId, $userId),
        ]);
    }

    /** POST /notificaciones/leer-todas — marca todas como leídas. */
    public function leerTodas()
    {
        $this->svc->marcarTodas((int) session('tenant_id'), (int) session('user_id'));

        return $this->response->setJSON(['ok' => true, 'noLeidas' => 0]);
    }
}
