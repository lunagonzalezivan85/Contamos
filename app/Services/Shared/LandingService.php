<?php

namespace App\Services\Shared;

use App\Models\AccesoSolicitudModel;

/**
 * Landing pública — solicitudes de acceso ("Solicita tu usuario").
 */
class LandingService
{
    private AccesoSolicitudModel $solicitudes;

    public function __construct()
    {
        $this->solicitudes = new AccesoSolicitudModel();
    }

    /** Registra la solicitud de acceso enviada desde la landing. */
    public function solicitarAcceso(array $d): void
    {
        $this->solicitudes->insert([
            'nombre'   => trim((string) $d['nombre']),
            'negocio'  => trim((string) $d['negocio']),
            'telefono' => trim((string) $d['telefono']),
            'correo'   => trim((string) ($d['correo'] ?? '')) ?: null,
        ]);
    }
}
