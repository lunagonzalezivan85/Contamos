<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\AsistenteService;

/**
 * Datos del asistente Chat-AI del dashboard.
 * GET /asistente/datos/{tipo} → { resumen, items[], vacio, ver_mas }
 */
class AsistenteController extends BaseController
{
    private const TIPOS = ['mora', 'pagos_hoy', 'por_cobrar', 'solicitudes', 'sin_docs', 'pagos_revision'];

    public function datos(string $tipo)
    {
        $tenantId = (int) session('tenant_id');
        $svc      = new AsistenteService();

        $out = match ($tipo) {
            'mora'           => $svc->enMora($tenantId),
            'pagos_hoy'      => $svc->pagosHoy($tenantId),
            'por_cobrar'     => $svc->porCobrar($tenantId),
            'solicitudes'    => $svc->solicitudes($tenantId),
            'sin_docs'       => $svc->sinDocs($tenantId),
            'pagos_revision' => $svc->pagosRevision($tenantId),
            default          => null,
        };

        if ($out === null) {
            return $this->response->setStatusCode(404)->setJSON(['error' => 'Tipo desconocido']);
        }
        return $this->response->setJSON($out);
    }
}
