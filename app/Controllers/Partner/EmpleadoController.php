<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\EmpleadoService;

/**
 * Empleados del tenant — capa HTTP.
 * La lógica (persona + empleado + detalles por pestañas) vive en
 * Services/Partner/EmpleadoService. Permisos: empleados.ver/crear/editar.
 */
class EmpleadoController extends BaseController
{
    private EmpleadoService $svc;

    public function __construct()
    {
        $this->svc = new EmpleadoService();
    }

    /** GET /socios/empleados — lista de empleados (buscador ?q=). */
    public function index()
    {
        $tenantId = (int) session('tenant_id');
        $buscar   = trim((string) $this->request->getGet('q'));
        $lista    = $this->svc->listar($tenantId, $buscar);

        $items = array_map(fn($e) => [
            'icono'     => 'user-check',
            'titulo'    => trim(($e['nombres'] ?? '') . ' ' . ($e['apellidos'] ?? '')),
            'subtitulo' => trim(($e['cargo'] ?? 'Empleado') . ' · ' . ($e['cedula'] ?? '')),
            'meta'      => $e['estado'],
            'url'       => '/socios/empleados/' . $e['id'],
        ], $lista['rows']);

        return view('partner/empleados/index', [
            'title'       => 'Empleados — Contamos',
            'items'       => $items,
            'pager'       => $lista['pager'],
            'buscar'      => $buscar,
            'puede_crear' => $this->tienePermiso('empleados.crear'),
        ]);
    }

    /** GET /socios/empleados/crear — alta (solo datos de persona). */
    public function crear()
    {
        return view('partner/empleados/form', [
            'title'           => 'Nuevo empleado — Contamos',
            'empleado'        => null,
            'accion'          => '/socios/empleados',
            'carnet_sugerido' => $this->svc->carnetSugerido(
                (int) session('tenant_id'), (string) session('tenant_name')
            ),
        ]);
    }

    /** POST /socios/empleados — inserta persona + empleado (con carnet y pin). */
    public function guardar()
    {
        if (!$this->validar()) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $r = $this->svc->registrar(
            (int) session('tenant_id'),
            (string) session('tenant_name'),
            $this->request->getPost()
        );

        return redirect()->to('/socios/empleados/' . $r['empleado_id'])
            ->with('success', 'Empleado ' . $r['carnet'] . ' registrado (PIN: ' . $r['pin'] . '). Complete sus datos en las pestañas.');
    }

    /** GET /socios/empleados/{id} — detalle de persona con pestañas. */
    public function ver(int $id)
    {
        $ficha = $this->svc->ficha((int) session('tenant_id'), $id);
        if (!$ficha) {
            return redirect()->to('/socios/empleados')->with('error', 'Empleado no encontrado.');
        }

        return view('partner/empleados/ver', [
            'title'        => $this->nombreCompleto($ficha['persona']) . ' — Contamos',
            'empleado'     => $ficha['empleado'],
            'persona'      => $ficha['persona'],
            'secciones'    => $ficha['secciones'],
            'tabActiva'    => (string) $this->request->getGet('tab') ?: 'datos',
            'puede_editar' => $this->tienePermiso('empleados.editar'),
        ]);
    }

    /** POST /socios/empleados/{id}/dato/{tipo} — agrega un item a una pestaña. */
    public function agregarDato(int $id, string $tipo)
    {
        $ep = $this->svc->empleadoConPersona((int) session('tenant_id'), $id);
        if (!$ep) {
            return redirect()->to('/socios/empleados')->with('error', 'Empleado no encontrado.');
        }

        $r = $this->svc->agregarDato(
            (int) $ep['persona']['id'], $tipo,
            $this->request->getPost(), $this->request->getFile('archivo')
        );
        if (!$r['ok']) {
            return redirect()->to('/socios/empleados/' . $id)->with('error', $r['error']);
        }

        return redirect()->to('/socios/empleados/' . $id . '?tab=' . $tipo)
            ->with('success', 'Dato agregado.');
    }

    /** POST /socios/empleados/{id}/dato/{tipo}/{item}/actualizar — edita un item. */
    public function actualizarDato(int $id, string $tipo, int $item)
    {
        $ep = $this->svc->empleadoConPersona((int) session('tenant_id'), $id);
        if (!$ep) {
            return redirect()->to('/socios/empleados')->with('error', 'Empleado no encontrado.');
        }

        $r = $this->svc->actualizarDato(
            (int) $ep['persona']['id'], $tipo, $item,
            $this->request->getPost(), $this->request->getFile('archivo')
        );
        if (!$r['ok']) {
            return redirect()->to('/socios/empleados/' . $id)->with('error', $r['error']);
        }

        return redirect()->to('/socios/empleados/' . $id . '?tab=' . $tipo)
            ->with('success', 'Dato actualizado.');
    }

    /** POST /socios/empleados/{id}/dato/{tipo}/{item}/eliminar — borra un item. */
    public function eliminarDato(int $id, string $tipo, int $item)
    {
        $ep = $this->svc->empleadoConPersona((int) session('tenant_id'), $id);
        if ($ep) {
            $this->svc->eliminarDato((int) $ep['persona']['id'], $tipo, $item);
        }
        return redirect()->to('/socios/empleados/' . $id . '?tab=' . $tipo)->with('success', 'Dato eliminado.');
    }

    /** GET /socios/empleados/{id}/editar — formulario de edición. */
    public function editar(int $id)
    {
        $ep = $this->svc->empleadoConPersona((int) session('tenant_id'), $id);
        if (!$ep) {
            return redirect()->to('/socios/empleados')->with('error', 'Empleado no encontrado.');
        }

        return view('partner/empleados/form', [
            'title'    => 'Editar empleado — Contamos',
            'empleado' => array_merge($ep['persona'], $ep['empleado']),
            'accion'   => '/socios/empleados/' . $id . '/editar',
        ]);
    }

    /** POST /socios/empleados/{id}/editar — actualiza persona + empleado. */
    public function actualizar(int $id)
    {
        $ep = $this->svc->empleadoConPersona((int) session('tenant_id'), $id);
        if (!$ep) {
            return redirect()->to('/socios/empleados')->with('error', 'Empleado no encontrado.');
        }
        if (!$this->validar()) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $this->svc->actualizar(
            $ep['empleado'], $ep['persona'],
            $this->request->getPost()
        );

        return redirect()->to('/socios/empleados/' . $id)
            ->with('success', 'Empleado actualizado correctamente.');
    }

    // ------------------------------------------------------------------

    private function validar(): bool
    {
        return $this->validate([
            'nombres'   => 'required|max_length[100]',
            'apellidos' => 'required|max_length[100]',
            'cedula'    => 'permit_empty|max_length[30]',
            'telefono'  => 'permit_empty|max_length[50]',
            'email'     => 'permit_empty|valid_email|max_length[150]',
            'direccion' => 'permit_empty|max_length[255]',
            'cargo'     => 'permit_empty|max_length[100]',
            'ruta'      => 'permit_empty|max_length[80]',
        ]);
    }

    private function nombreCompleto(array $p): string
    {
        return trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''));
    }

    private function tienePermiso(string $permiso): bool
    {
        return in_array($permiso, session('permisos') ?? [], true);
    }
}
