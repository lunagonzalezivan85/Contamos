<?php

namespace App\Services\Shared;

use App\Models\NotificationModel;

/**
 * Notificaciones del usuario — panel derecho del layout.
 */
class NotificacionService
{
    private NotificationModel $notifications;

    public function __construct()
    {
        $this->notifications = new NotificationModel();
    }

    /** Lista para el panel + conteo de no leídas. */
    public function bandeja(int $tenantId, int $userId): array
    {
        $items = $this->notifications->paraUsuario($tenantId, $userId);

        return [
            'noLeidas' => $this->notifications->noLeidas($tenantId, $userId),
            'items'    => array_map([$this, 'formatear'], $items),
        ];
    }

    /**
     * Marca una notificación como leída (solo si pertenece al usuario
     * o es broadcast del tenant). False si no existe para él.
     */
    public function marcarLeida(int $tenantId, int $userId, int $id): bool
    {
        $notif = $this->notifications
            ->where('tenant_id', $tenantId)
            ->groupStart()
                ->where('user_id', $userId)
                ->orWhere('user_id', null)
            ->groupEnd()
            ->find($id);

        if (!$notif) {
            return false;
        }

        $this->notifications->update($id, ['leida' => 1]);
        return true;
    }

    public function noLeidas(int $tenantId, int $userId): int
    {
        return $this->notifications->noLeidas($tenantId, $userId);
    }

    /** Marca todas como leídas (propias + broadcast del tenant). */
    public function marcarTodas(int $tenantId, int $userId): void
    {
        $this->notifications
            ->where('tenant_id', $tenantId)
            ->where('leida', 0)
            ->groupStart()
                ->where('user_id', $userId)
                ->orWhere('user_id', null)
            ->groupEnd()
            ->set(['leida' => 1])
            ->update();
    }

    private function formatear(array $n): array
    {
        return [
            'id'      => (int) $n['id'],
            'titulo'  => $n['titulo'],
            'mensaje' => $n['mensaje'],
            'tipo'    => $n['tipo'],
            'url'     => $n['url'],
            'leida'   => (bool) $n['leida'],
            'fecha'   => $n['created_at'],
        ];
    }
}
