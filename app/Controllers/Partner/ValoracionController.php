<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\ValoracionService;

class ValoracionController extends BaseController
{
    /** POST /valoracion — guarda estrellas + reseña del usuario en sesión */
    public function guardar()
    {
        $estrellas = (int) $this->request->getPost('estrellas');
        $resena    = trim((string) $this->request->getPost('resena'));

        $r = (new ValoracionService())->guardar(
            (int) session('tenant_id'),
            (int) session('user_id'),
            $estrellas,
            $resena
        );

        if (!$r['ok']) {
            return redirect()->back()->with('error', $r['error']);
        }
        return redirect()->back()->with('success', '¡Gracias por tu valoración!');
    }
}
