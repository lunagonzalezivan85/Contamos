<?php

namespace App\Filters;

use App\Models\TenantModel;
use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Horario laboral del tenant para la app del gestor (/{slug}/connect*).
 * Cualquier endpoint fuera del rango devuelve {ok:false, code:'FUERA_HORARIO'}
 * y la app muestra la pantalla de bloqueo (estilo Family Link).
 * OPTIONS (preflight CORS) pasa siempre.
 */
class HorarioConnectFilter implements FilterInterface
{
    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtoupper($request->getMethod()) === 'OPTIONS') {
            return;
        }

        // Segmento previo a 'connect' = slug del tenant (funciona en subdirectorios)
        $seg  = $request->getUri()->getSegments();
        $i    = array_search('connect', $seg, true);
        $slug = $i > 0 ? $seg[$i - 1] : '';
        if ($slug === '') {
            return;
        }

        $tenant = (new TenantModel())->where('slug', $slug)->first();
        if (!$tenant) {
            return;
        }

        $h = en_horario($tenant);
        if ($h['ok']) {
            return;
        }

        return service('response')->setJSON([
            'ok'          => false,
            'code'        => 'FUERA_HORARIO',
            'hora_inicio' => $h['ini'],
            'hora_fin'    => $h['fin'],
            'error'       => 'Fuera de horario laboral.',
        ]);
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
    }
}
