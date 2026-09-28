<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\ConfiguracionService;

/**
 * Configuración del tenant — capa HTTP.
 * La lógica (datos de empresa, logo, plantillas) vive en
 * Services/Partner/ConfiguracionService.
 * Solo usuarios con permiso admin.configuracion.
 */
class ConfiguracionController extends BaseController
{
    private ConfiguracionService $svc;

    public function __construct()
    {
        $this->svc = new ConfiguracionService();
    }

    /** GET /configuracion — datos de la empresa + lista de plantillas. */
    public function index()
    {
        $tenantId = (int) session('tenant_id');

        return view('partner/configuracion/index', [
            'title'      => 'Configuración — Contamos',
            'tenant'     => $this->svc->tenant($tenantId),
            'plantillas' => $this->svc->plantillasDe($tenantId),
            'tiposCalc'  => \App\Services\Partner\SolicitudService::TIPOS_CALCULO,
            'mon'        => $this->svc->tenant($tenantId)['moneda'] ?? 'C$',
        ]);
    }

    /** POST /configuracion — guarda los datos de la empresa. */
    public function guardar()
    {
        if (!$this->validate([
            'nombre'          => 'required|max_length[150]',
            'razon_social'    => 'permit_empty|max_length[200]',
            'ruc'             => 'permit_empty|max_length[30]',
            'conami_registro' => 'permit_empty|max_length[40]',
            'email'           => 'permit_empty|valid_email|max_length[150]',
            'telefono'        => 'permit_empty|max_length[50]',
            'direccion'       => 'permit_empty|max_length[255]',
            'lema'            => 'permit_empty|max_length[200]',
            'quienes_somos'   => 'permit_empty|max_length[2000]',
            'mision'          => 'permit_empty|max_length[2000]',
            'vision'          => 'permit_empty|max_length[2000]',
            'valores'         => 'permit_empty|max_length[2000]',
            'voucher_footer'  => 'permit_empty|max_length[255]',
            'horario'         => 'permit_empty|max_length[200]',
            'hora_inicio'     => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/]',
            'hora_fin'        => 'permit_empty|regex_match[/^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/]',
            'moneda'          => 'permit_empty|max_length[10]',
            'tasa_interes'    => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[100]',
            'mora_diaria_pct' => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[5]',
            'pronto_pago_pct' => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[100]',
            'plazo_meses_max' => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[360]',
            'tipo_calculo'    => 'permit_empty|in_list[FRANCES,FLAT,ALEMAN,ANTICIPADO]',
            'comision_pct'    => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[50]',
            'seguro_pct'      => 'permit_empty|numeric|greater_than_equal_to[0]|less_than_equal_to[50]',
            'contacto_nombre' => 'permit_empty|max_length[150]',
            'contacto_cargo'  => 'permit_empty|max_length[100]',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->guardarDatos(
            (int) session('tenant_id'),
            $this->request->getPost(),
            $this->request->getFile('logo')
        );

        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }

        // Refrescar el nombre en sesión si cambió
        session()->set('tenant_name', $r['nombre']);

        return redirect()->to('/configuracion')
            ->with('success', 'Datos de la empresa guardados correctamente.');
    }

    /** GET /configuracion/plantilla/{id} — editor de la plantilla. */
    public function plantilla(int $id)
    {
        $plantilla = $this->svc->plantilla((int) session('tenant_id'), $id);
        if (!$plantilla) {
            return redirect()->to('/configuracion')
                ->with('error', 'Plantilla no encontrada.');
        }

        return view('partner/configuracion/plantilla', [
            'title'     => $plantilla['nombre'] . ' — Contamos',
            'plantilla' => $plantilla,
        ]);
    }

    /* ── Categorías de reportes ─────────────────────────────────────── */

    /** GET /configuracion/reportes — categorías + asignación reporte→categoría. */
    public function reportes()
    {
        $tenantId = (int) session('tenant_id');
        $rsvc     = new \App\Services\Partner\ReporteService();

        return view('partner/configuracion/reportes', [
            'title'        => 'Reportes — Contamos',
            'categorias'   => $rsvc->categoriasReporte($tenantId),
            'catalogo'     => config('Reportes')->catalogo,
            'asignaciones' => $rsvc->asignacionesReporte($tenantId),
        ]);
    }

    /** POST /configuracion/reportes/categoria — crea una categoría. */
    public function crearCategoriaReporte()
    {
        $id = (new \App\Services\Partner\ReporteService())->crearCategoriaReporte(
            (int) session('tenant_id'),
            (string) $this->request->getPost('nombre'),
            (int) $this->request->getPost('orden')
        );
        return redirect()->to('/configuracion/reportes')
            ->with($id ? 'success' : 'error', $id ? 'Categoría creada.' : 'Nombre inválido.');
    }

    /** POST /configuracion/reportes/categoria/{id} — actualiza nombre/orden/activo. */
    public function guardarCategoriaReporte(int $id)
    {
        (new \App\Services\Partner\ReporteService())->guardarCategoriaReporte(
            (int) session('tenant_id'), $id, $this->request->getPost()
        );
        return redirect()->to('/configuracion/reportes')->with('success', 'Categoría actualizada.');
    }

    /** POST /configuracion/reportes/categoria/{id}/eliminar */
    public function eliminarCategoriaReporte(int $id)
    {
        (new \App\Services\Partner\ReporteService())->eliminarCategoriaReporte(
            (int) session('tenant_id'), $id
        );
        return redirect()->to('/configuracion/reportes')->with('success', 'Categoría eliminada.');
    }

    /** POST /configuracion/reportes/asignar — guarda reporte→categoría. */
    public function asignarReportes()
    {
        (new \App\Services\Partner\ReporteService())->guardarAsignaciones(
            (int) session('tenant_id'),
            (array) $this->request->getPost('cat')
        );
        return redirect()->to('/configuracion/reportes')->with('success', 'Asignaciones guardadas.');
    }

    /** POST /configuracion/plantilla/{id} — guarda el contenido de la plantilla. */
    public function guardarPlantilla(int $id)
    {
        $plantilla = $this->svc->plantilla((int) session('tenant_id'), $id);
        if (!$plantilla) {
            return redirect()->to('/configuracion')
                ->with('error', 'Plantilla no encontrada.');
        }

        $this->svc->guardarPlantilla($id, (string) $this->request->getPost('contenido'));

        return redirect()->to('/configuracion')
            ->with('success', 'Plantilla "' . $plantilla['nombre'] . '" guardada.');
    }
}
