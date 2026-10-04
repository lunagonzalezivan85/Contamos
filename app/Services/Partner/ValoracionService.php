<?php

namespace App\Services\Partner;

use App\Models\ValoracionModel;

/**
 * Valoración del sistema — regla: el modal de 5 estrellas se muestra
 * cuando el usuario nunca ha calificado o su última valoración tiene
 * 5+ días (se cuenta desde la última vez que calificó).
 */
class ValoracionService
{
    private const DIAS = 5;

    private ValoracionModel $valoraciones;

    public function __construct()
    {
        $this->valoraciones = new ValoracionModel();
    }

    /** ¿Toca pedir valoración a este usuario? */
    public function pendiente(int $tenantId, int $userId): bool
    {
        if ($tenantId <= 0 || $userId <= 0) {
            return false;
        }
        $ultima = $this->ultima($tenantId, $userId);
        if (!$ultima) {
            return true;
        }
        return strtotime($ultima['created_at']) <= strtotime('-' . self::DIAS . ' days');
    }

    /** Última valoración del usuario (o null si nunca ha calificado). */
    public function ultima(int $tenantId, int $userId): ?array
    {
        return $this->valoraciones
            ->where('tenant_id', $tenantId)
            ->where('user_id', $userId)
            ->orderBy('id', 'DESC')
            ->first();
    }

    /**
     * Guarda una valoración.
     * @return array{ok: bool, error?: string}
     */
    public function guardar(int $tenantId, int $userId, int $estrellas, ?string $resena): array
    {
        if ($estrellas < 1 || $estrellas > 5) {
            return ['ok' => false, 'error' => 'Selecciona de 1 a 5 estrellas.'];
        }
        try {
            $this->valoraciones->insert([
                'tenant_id' => $tenantId,
                'user_id'   => $userId,
                'estrellas' => $estrellas,
                'resena'    => $resena !== null && $resena !== '' ? mb_substr($resena, 0, 1000) : null,
            ]);
        } catch (\Throwable) {
            return ['ok' => false, 'error' => 'No se pudo guardar la valoración. Intente de nuevo.'];
        }
        return ['ok' => true];
    }
}
