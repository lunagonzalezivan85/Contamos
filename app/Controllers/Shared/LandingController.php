<?php

namespace App\Controllers\Shared;

use App\Controllers\BaseController;
use App\Services\Shared\LandingService;

class LandingController extends BaseController
{
    /**
     * GET / — Landing page pública
     */
    public function index()
    {
        if (session('logged_in')) {
            return redirect()->to('/dashboard');
        }

        return view('landing/index', [
            'title' => 'Contamos — Control de Préstamos',
        ]);
    }

    /**
     * GET /descargar — página de descarga de la app del gestor.
     * (La ruta no puede ser /app: la carpeta física public/app/ le gana
     *  en el rewrite del servidor y termina en /public/app/.)
     */
    public function descargar()
    {
        if (session('logged_in')) {
            return redirect()->to('/dashboard');
        }

        // Versión vigente según el manifest — el mismo que consulta la app
        $mani  = FCPATH . 'app/version.json';
        $v     = is_file($mani) ? (json_decode(file_get_contents($mani), true) ?: []) : [];
        $vigente = $v['versionName'] ?? null;

        // Todas las APKs versionadas en public/app (contamos-gestor-X.Y.Z.apk)
        $versiones = [];
        foreach (glob(FCPATH . 'app/contamos-gestor-*.apk') ?: [] as $f) {
            if (preg_match('/contamos-gestor-(.+)\.apk$/', basename($f), $m)) {
                $versiones[] = [
                    'version' => $m[1],
                    'size'    => number_format(filesize($f) / 1048576, 1) . ' MB',
                    'url'     => base_url('descargar/apk/' . $m[1]),
                    'actual'  => $vigente === $m[1],
                ];
            }
        }
        usort($versiones, fn($a, $b) => version_compare($b['version'], $a['version']));

        return view('landing/app', [
            'title'     => 'Contamos — App del gestor',
            'vigente'   => $vigente,
            'mensaje'   => $v['mensaje'] ?? null,
            'versiones' => $versiones,
        ]);
    }

    /**
     * GET /descargar/apk[/{version}] — sirve la APK por PHP.
     * Sin depender del MIME .apk del servidor web (IIS devuelve 404.3/500
     * con extensiones no registradas).
     */
    public function apk(?string $version = null)
    {
        $dir = FCPATH . 'app/';
        if ($version) {
            // Solo el patrón de nombre esperado — nada de paths arbitrarios
            if (!preg_match('/^\d+(\.\d+)*$/', $version)) {
                throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
            }
            $file = $dir . 'contamos-gestor-' . $version . '.apk';
        } else {
            // Sin versión → la más nueva disponible (la app la pide así)
            $lista = glob($dir . 'contamos-gestor-*.apk') ?: [];
            usort($lista, function ($a, $b) {
                preg_match('/-(.+)\.apk$/', $a, $ma);
                preg_match('/-(.+)\.apk$/', $b, $mb);
                return version_compare($mb[1] ?? '0', $ma[1] ?? '0');
            });
            $file = $lista[0] ?? null;
        }

        if (!$file || !is_file($file)) {
            throw \CodeIgniter\Exceptions\PageNotFoundException::forPageNotFound();
        }

        preg_match('/-(.+)\.apk$/', basename($file), $m);
        return $this->response->download($file, null)
            ->setFileName('contamos-gestor-' . ($m[1] ?? 'latest') . '.apk', true);
    }

    /**
     * POST /solicitar-acceso — formulario "Solicita tu usuario" de la landing
     */
    public function solicitarAcceso()
    {
        $rules = [
            'nombre'   => 'required|min_length[3]|max_length[120]',
            'negocio'  => 'required|min_length[2]|max_length[160]',
            'telefono' => 'required|min_length[7]|max_length[30]',
            'correo'   => 'permit_empty|valid_email|max_length[160]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->to('/#contacto')->withInput()
                ->with('acceso_error', 'Revisa los datos del formulario e inténtalo de nuevo.');
        }

        (new LandingService())->solicitarAcceso(
            $this->request->getPost(['nombre', 'negocio', 'telefono', 'correo'])
        );

        return redirect()->to('/#contacto')
            ->with('acceso_ok', 'Solicitud recibida — te contactaremos para crear tu usuario.');
    }

    /**
     * GET /alta — "Darte de alta": calculadora de plan a medida sobre el
     * plan Básico + formulario de activación (nombre + WhatsApp).
     */
    public function alta()
    {
        $base = db_connect()->table('planes')->where('slug', 'basico')->get()->getRowArray()
            ?: ['precio_mensual' => 19, 'moneda' => 'USD', 'max_usuarios' => 1,
                'max_empleados' => 5, 'max_creditos_activos' => 50, 'nombre' => 'Básico'];

        return view('landing/alta', [
            'title' => 'Contamos — Da de alta tu negocio',
            'base'  => $base,
        ]);
    }

    /**
     * POST /alta — guarda el lead en acceso_solicitudes con el plan estimado
     * y devuelve su código partner (PTR-######).
     */
    public function altaStore()
    {
        $rules = [
            'nombre'    => 'required|min_length[3]|max_length[160]',
            'telefono'  => 'required|min_length[7]|max_length[30]',
            'usuarios'  => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[25]',
            'clientes'  => 'required|integer|greater_than_equal_to[10]|less_than_equal_to[500]',
            'creditos'  => 'required|integer|greater_than_equal_to[20]|less_than_equal_to[1000]',
            'empleados' => 'required|integer|greater_than_equal_to[1]|less_than_equal_to[50]',
        ];

        if (!$this->validate($rules)) {
            return redirect()->to('/alta')->withInput()
                ->with('alta_error', 'Revisá los datos e intentalo de nuevo.');
        }

        $d      = $this->request->getPost(['nombre', 'telefono', 'usuarios', 'clientes', 'creditos', 'empleados']);
        $codigo = (new LandingService())->registrarAlta($d);

        return redirect()->to('/alta')
            ->with('alta_ok', 'Tu solicitud fue enviada — te contactaremos en breve para activar tu cuenta.')
            ->with('alta_codigo', $codigo);
    }
}
