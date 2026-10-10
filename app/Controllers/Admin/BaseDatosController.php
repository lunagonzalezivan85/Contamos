<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use App\Services\Admin\BackupService;

/**
 * /admin/base-datos — listado de tablas + descarga de estructura/backup.
 * Solo superadmin (filtro 'admin' del grupo de rutas).
 */
class BaseDatosController extends BaseController
{
    private BackupService $svc;

    public function __construct()
    {
        $this->svc = new BackupService();
    }

    public function index()
    {
        return view('admin/base_datos/index', [
            'tablas' => $this->svc->tablas(),
        ]);
    }

    /** POST /admin/base-datos/exportar — modo=estructura|completo, tablas[] */
    public function exportar()
    {
        $tablas   = array_map('strval', (array) ($this->request->getPost('tablas') ?? []));
        $conDatos = $this->request->getPost('modo') === 'completo';
        $nombre   = 'contamos_' . ($conDatos ? 'backup' : 'estructura')
                  . '_' . date('Ymd_His') . '.sql';

        $dir = WRITEPATH . 'backups';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $ruta = $dir . '/' . $nombre;
        $this->svc->generar($tablas, $conDatos, $ruta);

        return $this->response->download($ruta, null)->setFileName($nombre);
    }
}
