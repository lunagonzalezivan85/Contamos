<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\ClienteModel;
use App\Models\SolicitudAnalisisModel;
use App\Models\SolicitudModel;
use App\Services\Partner\SolicitudService;

/**
 * Solicitudes de crédito del tenant — capa HTTP.
 * Toda la lógica de negocio vive en Services/Partner/SolicitudService.
 */
class SolicitudController extends BaseController
{
    private SolicitudService $svc;

    public function __construct()
    {
        $this->svc = new SolicitudService();
    }

    /** GET /credito/solicitudes — listado con filtros + paginación. */
    public function index()
    {
        $tenantId = (int) session('tenant_id');
        $f        = $this->request->getGet(['q', 'estado', 'gestor', 'ruta', 'desde', 'hasta']);
        $f        = array_map(static fn ($v) => trim((string) $v), $f);
        // Sin param en la URL → arranca en CREADA (lo que hay por procesar);
        // ?estado= vacío = "Todos"
        if ($this->request->getGet('estado') === null) {
            $f['estado'] = SolicitudModel::CREADA;
        }

        $lista    = $this->svc->listar($tenantId, $f);
        $opciones = $this->svc->opcionesFiltro($tenantId);

        return view('partner/solicitudes/index', [
            'title'     => 'Solicitudes — Contamos',
            'filas'     => $lista['filas'],
            'pager'     => $lista['pager'],
            'f'         => $lista['f'],
            'gestores'  => $opciones['gestores'],
            'rutas'     => $opciones['rutas'],
            // Orden del pipeline; "Por contactar" (leads web) y Todos al final
            'estados'   => [
                SolicitudModel::CREADA, SolicitudModel::REVISION, SolicitudModel::APROBADA,
                SolicitudModel::DESEMBOLSO, SolicitudModel::ACTIVO, SolicitudModel::LIQUIDADO,
                SolicitudModel::RECHAZADA, SolicitudModel::CONTACTO,
            ],
            'lblEstado' => SolicitudModel::LABEL_ESTADO,
            'lblFreq'   => SolicitudService::LBL_FREQ,
            'mon'       => $this->svc->moneda($tenantId),
            'puede_crear' => $this->tienePermiso('solicitudes.crear'),
        ]);
    }

    /** GET /credito/solicitudes/nueva — alta desde oficina. */
    public function nueva()
    {
        $tenantId = (int) session('tenant_id');
        $tenant   = $this->svc->tenant($tenantId);

        return view('partner/solicitudes/nueva', [
            'title'    => 'Nueva solicitud — Contamos',
            'clientes' => (new ClienteModel())->conPersona($tenantId),
            'gestores' => $this->svc->gestoresActivos($tenantId),
            'tenant'    => $tenant,
            'mon'       => $this->svc->moneda($tenantId),
            'lblFreq'   => SolicitudService::LBL_FREQ,
            'tiposCalc' => SolicitudService::TIPOS_CALCULO,
            'fechaSug'  => $this->svc->primerPagoSugerido('M'),
        ]);
    }

    /** POST /credito/solicitudes — crea la solicitud (estado CREADA). */
    public function guardar()
    {
        $r = $this->svc->crearOficina((int) session('tenant_id'), $this->request->getPost());
        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }
        return redirect()->to('/credito/solicitudes/' . $r['id'])
            ->with('success', 'Solicitud registrada. Puede continuar con el análisis.');
    }

    /** GET /credito/solicitudes/{id} — detalle + acciones de estado. */
    public function ver(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }

        $puede    = $this->svc->permisosPorEstado(session('permisos') ?? []);
        [$checklist, $faltan] = $this->svc->checklistCliente($sol);
        $editable = in_array($sol['estado'], [SolicitudModel::CONTACTO, SolicitudModel::CREADA, SolicitudModel::REVISION], true);

        return view('partner/solicitudes/ver', [
            'title'     => 'Solicitud #' . $sol['id'] . ' — Contamos',
            's'         => $sol,
            'historial' => $this->svc->historialDe($tenantId, $id),
            'checklist' => $checklist,
            'faltan'    => $faltan,
            'editable'  => $editable && ($puede[SolicitudModel::REVISION] ?? false), // REVISION usa solicitudes.editar
            'analisis'  => $this->svc->analisisDe($tenantId, $id),
            'nivelLbl'  => SolicitudAnalisisModel::NIVELES,
            'lblEstado' => SolicitudModel::LABEL_ESTADO,
            'lblFreq'   => SolicitudService::LBL_FREQ,
            'puede'     => $puede,
            'mon'       => $this->svc->moneda($tenantId),
        ]);
    }

    /** POST /credito/solicitudes/{id}/estado — mueve la solicitud de estado. */
    public function cambiarEstado(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }

        $nuevo = trim((string) $this->request->getPost('estado'));
        $r     = $this->svc->validarTransicion($sol, $nuevo, session('permisos') ?? []);

        if (isset($r['goto'])) {
            return redirect()->to('/credito/solicitudes/' . $id . '/' . $r['goto']);
        }
        if (!$r['ok']) {
            return redirect()->back()->with('error', $r['error']);
        }

        // Observación opcional — visible al gestor cuando va a REVISION
        $nota = trim((string) $this->request->getPost('observaciones'));
        $this->svc->moverEstado($sol, $nuevo, $nota !== '' ? $nota : null);

        $lbl = SolicitudModel::LABEL_ESTADO[$nuevo] ?? $nuevo;
        return redirect()->to('/credito/solicitudes/' . $id)
            ->with('success', 'Solicitud movida a ' . mb_strtolower($lbl) . '.');
    }

    /** GET /credito/solicitudes/{id}/analisis — análisis crediticio del expediente del cliente. */
    public function analisis(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }

        $secciones = $this->svc->seccionesCliente((int) ($sol['persona_id'] ?? 0));

        return view('partner/solicitudes/analisis', [
            'title'     => 'Análisis crediticio — Solicitud #' . $sol['id'] . ' — Contamos',
            's'         => $sol,
            'secciones' => $secciones,
            'metricas'  => $this->svc->metricasAnalisis($sol, $secciones),
            'analisis'  => $this->svc->analisisDe($tenantId, $id),
            'nivelLbl'  => SolicitudAnalisisModel::NIVELES,
            'puedeCalc' => in_array('solicitudes.editar', session('permisos') ?? [], true),
            'lblFreq'   => SolicitudService::LBL_FREQ,
            'lblEstado' => SolicitudModel::LABEL_ESTADO,
            'mon'       => $this->svc->moneda($tenantId),
        ]);
    }

    /** GET /credito/solicitudes/{id}/aprobar — pantalla de aprobación (solicitado vs aprobado). */
    public function aprobar(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }
        $guard = $this->guardAprobar($id, $sol);
        if ($guard) return $guard;

        return view('partner/solicitudes/aprobar', [
            'title'    => 'Aprobar solicitud #' . $sol['id'] . ' — Contamos',
            'gestores' => $this->svc->gestoresActivos($tenantId),
            's'        => $sol,
            'metricas' => $this->svc->metricasAnalisis($sol, $this->svc->seccionesCliente((int) $sol['persona_id'])),
            'analisis' => $this->svc->analisisDe($tenantId, $id),
            'nivelLbl' => SolicitudAnalisisModel::NIVELES,
            'lblFreq'  => SolicitudService::LBL_FREQ,
            'plazoMax' => (int) ($this->svc->tenant($tenantId)['plazo_meses_max'] ?? 24),
            'fechaSug' => $sol['fecha_primer_pago'] ?: $this->svc->primerPagoSugerido((string) ($sol['frecuencia'] ?? 'M')),
            'mon'      => $this->svc->moneda($tenantId),
            'tiposCalc' => \App\Services\Partner\SolicitudService::TIPOS_CALCULO,
            'tenant'    => $this->svc->tenant($tenantId),
        ]);
    }

    /** POST /credito/solicitudes/{id}/aprobar — guarda préstamo aprobado + fecha primer pago y pasa a APROBADA. */
    public function guardarAprobacion(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }
        $guard = $this->guardAprobar($id, $sol);
        if ($guard) return $guard;

        if (!$this->validate([
            'monto_aprobado'      => 'required|numeric|greater_than[0]',
            'tasa_aprobada'       => 'required|numeric|greater_than_equal_to[0]',
            'plazo_aprobado'      => 'required|numeric|greater_than_equal_to[0.5]',
            'frecuencia_aprobada' => 'required|in_list[D,DI,S,Q,M,P]',
            'dias_semana'         => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[7]',
            'paso_dias'           => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[365]',
            'tipo_calculo'        => 'permit_empty|in_list[FRANCES,FLAT,ALEMAN,ANTICIPADO]',
            'gracia_meses'        => 'permit_empty|integer|greater_than_equal_to[0]|less_than_equal_to[24]',
            'gracia_tipo'         => 'permit_empty|in_list[TOTAL,INTERES]',
            'comision'            => 'permit_empty|numeric|greater_than_equal_to[0]',
            'seguro'              => 'permit_empty|numeric|greater_than_equal_to[0]',
            'fecha_primer_pago'   => 'required|valid_date',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->guardarAprobacion($tenantId, $sol, $this->request->getPost());
        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }

        return redirect()->to('/credito/solicitudes/' . $id)
            ->with('success', 'Solicitud aprobada — primer pago el ' . $this->request->getPost('fecha_primer_pago') . '.');
    }

    /** GET /credito/solicitudes/{id}/desembolsar — define cuándo se entrega el dinero. */
    public function desembolsar(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }
        if ($sol['estado'] !== SolicitudModel::APROBADA) {
            return redirect()->to('/credito/solicitudes/' . $id)
                ->with('error', 'Solo se puede desembolsar una solicitud aprobada.');
        }

        return view('partner/solicitudes/desembolsar', [
            'title'   => 'Desembolso — Solicitud #' . $sol['id'] . ' — Contamos',
            's'       => $sol,
            'lblFreq' => SolicitudService::LBL_FREQ,
            'mon'     => $this->svc->moneda($tenantId),
        ]);
    }

    /** POST /credito/solicitudes/{id}/desembolsar — guarda fecha y pasa a DESEMBOLSO. */
    public function guardarDesembolso(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->find($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }
        if ($sol['estado'] !== SolicitudModel::APROBADA) {
            return redirect()->to('/credito/solicitudes/' . $id)
                ->with('error', 'Solo se puede desembolsar una solicitud aprobada.');
        }

        if (!$this->validate(['fecha_desembolso' => 'required|valid_date'])) {
            return redirect()->back()->withInput()
                ->with('error', 'Indique una fecha de desembolso válida.');
        }
        if ((string) $this->request->getPost('fecha_desembolso') < date('Y-m-d')) {
            return redirect()->back()->withInput()
                ->with('error', 'La fecha de desembolso no puede ser pasada.');
        }

        $this->svc->guardarDesembolso($sol, (string) $this->request->getPost('fecha_desembolso'));

        return redirect()->to('/credito/solicitudes/' . $id)
            ->with('success', 'Desembolso programado para el ' . $this->request->getPost('fecha_desembolso') . '. El gestor lo verá en su portal.');
    }

    /**
     * POST /credito/solicitudes/{id}/entregar — la oficina marca el dinero
     * como entregado (útil cuando el gestor no tiene el permiso o entregó
     * en ventanilla). Misma transacción que usa el portal del gestor.
     */
    public function entregarDesembolso(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->find($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }

        $r = (new \App\Services\Partner\PortalService())->entregarDesembolso($tenantId, null, $id);
        if (!$r['ok']) {
            return redirect()->to('/credito/solicitudes/' . $id)->with('error', $r['error']);
        }

        return redirect()->to('/credito/solicitudes/' . $id)
            ->with('success', 'Desembolso entregado — crédito ' . $r['codigo'] . ' activo.');
    }

    /**
     * GET /credito/solicitudes/{id}/documentos — plan de pago + contrato + garantías
     * (imprimible; usa valores aprobados si existen).
     */
    public function documentos(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }

        return view('partner/solicitudes/documentos', [
            'title'     => 'Documentos — Solicitud #' . $sol['id'] . ' — Contamos',
            's'         => $sol,
            'tenant'    => $this->svc->tenant($tenantId),
            'secciones' => $this->svc->seccionesCliente((int) $sol['persona_id']),
            'plan'      => $this->svc->planPagos($sol),
            'lblFreq'   => SolicitudService::LBL_FREQ,
            'lblEstado' => SolicitudModel::LABEL_ESTADO,
            'mon'       => $this->svc->moneda($tenantId),
        ]);
    }

    /** POST /credito/solicitudes/{id}/analisis/calcular — calcula y guarda el análisis financiero. */
    public function calcularAnalisis(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }

        $r = $this->svc->calcularAnalisis($tenantId, $sol);
        if (!$r['ok']) {
            return redirect()->to('/credito/solicitudes/' . $id)->with('error', $r['error']);
        }

        return redirect()->to('/credito/solicitudes/' . $id)
            ->with('success', 'Análisis financiero calculado — ' . SolicitudAnalisisModel::NIVELES[$r['nivel']] . '.');
    }

    /** GET /credito/solicitudes/{id}/editar — formulario de edición. */
    public function editar(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->detalle($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }
        $guard = $this->guardEditable($id, $sol);
        if ($guard) return $guard;

        $tenant = $this->svc->tenant($tenantId);

        return view('partner/solicitudes/editar', [
            'title'    => 'Editar solicitud #' . $sol['id'] . ' — Contamos',
            's'        => $sol,
            'gestores' => $this->svc->gestoresActivos($tenantId),
            'plazoMax'  => (int) ($tenant['plazo_meses_max'] ?? 60),
            'tasaDef'   => (float) ($tenant['tasa_interes'] ?? 3),
            'mon'       => $this->svc->moneda($tenantId),
            'tiposCalc' => SolicitudService::TIPOS_CALCULO,
            'fechaSug'  => $sol['fecha_primer_pago'] ?: $this->svc->primerPagoSugerido((string) ($sol['frecuencia'] ?? 'M')),
        ]);
    }

    /** POST /credito/solicitudes/{id}/editar — guarda los cambios. */
    public function actualizar(int $id)
    {
        $tenantId = (int) session('tenant_id');
        $sol      = $this->svc->find($tenantId, $id);
        if (!$sol) {
            return redirect()->to('/credito/solicitudes')->with('error', 'Solicitud no encontrada.');
        }
        $guard = $this->guardEditable($id, $sol, 'La solicitud ya no se puede editar.');
        if ($guard) return $guard;

        if (!$this->validate([
            'monto'        => 'required|numeric|greater_than[0]',
            'tasa_mensual' => 'permit_empty|numeric|greater_than_equal_to[0]',
            'plazo_meses'  => 'permit_empty|numeric|greater_than_equal_to[0.5]',
            'frecuencia'   => 'required|in_list[D,DI,S,Q,M]',
            'dias_semana'  => 'permit_empty|integer|greater_than_equal_to[1]|less_than_equal_to[7]',
            'destino'      => 'permit_empty|max_length[255]',
            'asignado_a'   => 'permit_empty|is_natural_no_zero',
            'tipo_calculo' => 'permit_empty|in_list[FRANCES,FLAT,ALEMAN,ANTICIPADO]',
            'fecha_primer_pago' => 'permit_empty|valid_date',
        ])) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->actualizar($tenantId, $sol, $this->request->getPost());
        if (!$r['ok']) {
            return redirect()->back()->withInput()->with('error', $r['error']);
        }

        return redirect()->to('/credito/solicitudes/' . $id)->with('success', 'Solicitud actualizada.');
    }

    // ------------------------------------------------------------------
    // Guards HTTP (redirect) — las reglas de negocio viven en el service
    // ------------------------------------------------------------------

    /** Redirect si la solicitud no está en revisión o el expediente está incompleto. */
    private function guardAprobar(int $id, array $sol)
    {
        if ($sol['estado'] !== SolicitudModel::REVISION) {
            return redirect()->to('/credito/solicitudes/' . $id)
                ->with('error', 'Solo se puede aprobar una solicitud en revisión.');
        }
        [, $faltan] = $this->svc->checklistCliente($sol);
        if ($faltan > 0) {
            return redirect()->to('/credito/solicitudes/' . $id)
                ->with('error', "Complete el expediente del cliente antes de aprobar (faltan {$faltan} datos).");
        }
        return null;
    }

    /** Redirect si la solicitud no es editable (CONTACTO|CREADA|REVISION). */
    private function guardEditable(int $id, array $sol, string $msg = 'Solo se puede editar una solicitud pendiente de aprobación.')
    {
        if (!in_array($sol['estado'], [SolicitudModel::CONTACTO, SolicitudModel::CREADA, SolicitudModel::REVISION], true)) {
            return redirect()->to('/credito/solicitudes/' . $id)->with('error', $msg);
        }
        return null;
    }

    private function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, session('permisos') ?? [], true);
    }
}
