<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Services\Partner\ClienteService;

/**
 * Clientes del tenant — capa HTTP.
 * La lógica (persona + cliente + detalles por pestañas) vive en
 * Services/Partner/ClienteService. Permisos: clientes.ver/crear/editar.
 */
class ClienteController extends BaseController
{
    private ClienteService $svc;

    public function __construct()
    {
        $this->svc = new ClienteService();
    }

    /** GET /socios/clientes — lista de clientes (buscador ?q=). */
    public function index()
    {
        $tenantId = (int) session('tenant_id');
        $buscar   = trim((string) $this->request->getGet('q'));
        $lista    = $this->svc->listar($tenantId, $buscar);

        $items = array_map(fn($c) => [
            'icono'     => 'user',
            'titulo'    => trim(($c['nombres'] ?? '') . ' ' . ($c['apellidos'] ?? '')),
            'subtitulo' => trim(($c['cedula'] ?? 'Sin cédula') . ' · ' . ($c['telefono'] ?? 'sin tel.')),
            'meta'      => $c['estado'],
            'url'       => '/socios/clientes/' . $c['id'],
        ], $lista['rows']);

        return view('partner/clientes/index', [
            'title'       => 'Clientes — Contamos',
            'items'       => $items,
            'pager'       => $lista['pager'],
            'buscar'      => $buscar,
            'puede_crear' => $this->tienePermiso('clientes.crear'),
        ]);
    }

    /** GET /socios/clientes/crear — alta (solo datos de persona). */
    public function crear()
    {
        return view('partner/clientes/form', [
            'title'           => 'Nuevo cliente — Contamos',
            'cliente'         => null,
            'accion'          => '/socios/clientes',
            'codigo_sugerido' => $this->svc->codigoSugerido(
                (int) session('tenant_id'), (string) session('tenant_name')
            ),
        ]);
    }

    /** POST /socios/clientes — inserta persona + cliente. */
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

        return redirect()->to('/socios/clientes/' . $r['cliente_id'])
            ->with('success', 'Cliente ' . $r['codigo'] . ' registrado. Complete sus datos en las pestañas.');
    }

    /** GET /socios/clientes/{id} — detalle de persona con pestañas. */
    public function ver(int $id)
    {
        $ficha = $this->svc->ficha((int) session('tenant_id'), $id);
        if (!$ficha) {
            return redirect()->to('/socios/clientes')->with('error', 'Cliente no encontrado.');
        }

        return view('partner/clientes/ver', [
            'title'        => $this->nombreCompleto($ficha['persona']) . ' — Contamos',
            'cliente'      => $ficha['cliente'],
            'persona'      => $ficha['persona'],
            'secciones'    => $ficha['secciones'],
            'tabActiva'    => (string) $this->request->getGet('tab') ?: 'datos',
            'puede_editar' => $this->tienePermiso('clientes.editar'),
        ]);
    }

    /** GET /socios/clientes/{id}/editar — formulario de edición. */
    public function editar(int $id)
    {
        $cp = $this->svc->clienteConPersona((int) session('tenant_id'), $id);
        if (!$cp) {
            return redirect()->to('/socios/clientes')->with('error', 'Cliente no encontrado.');
        }

        return view('partner/clientes/form', [
            'title'   => 'Editar cliente — Contamos',
            'cliente' => array_merge($cp['persona'], $cp['cliente']),
            'accion'  => '/socios/clientes/' . $id . '/editar',
        ]);
    }

    /** POST /socios/clientes/{id}/editar — actualiza persona + cliente. */
    public function actualizar(int $id)
    {
        $cp = $this->svc->clienteConPersona((int) session('tenant_id'), $id);
        if (!$cp) {
            return redirect()->to('/socios/clientes')->with('error', 'Cliente no encontrado.');
        }
        if (!$this->validar()) {
            return redirect()->back()->withInput()
                ->with('error', 'Revise los datos: ' . implode(' ', $this->validator->getErrors()));
        }

        $this->svc->actualizar($cp['cliente'], $cp['persona'], $this->request->getPost());

        return redirect()->to('/socios/clientes/' . $id)
            ->with('success', 'Cliente actualizado correctamente.');
    }

    /** POST /socios/clientes/{id}/dato/{tipo} — agrega un item a una pestaña. */
    public function agregarDato(int $id, string $tipo)
    {
        $cp = $this->svc->clienteConPersona((int) session('tenant_id'), $id);
        if (!$cp) {
            return redirect()->to('/socios/clientes')->with('error', 'Cliente no encontrado.');
        }

        $r = $this->svc->agregarDato(
            (int) $cp['persona']['id'], $tipo,
            $this->request->getPost(), $this->request->getFile('archivo')
        );
        if (!$r['ok']) {
            return redirect()->to('/socios/clientes/' . $id)->with('error', $r['error']);
        }

        return redirect()->to('/socios/clientes/' . $id . '?tab=' . $tipo)
            ->with('success', 'Dato agregado.');
    }

    /** POST /socios/clientes/{id}/dato/{tipo}/{item}/actualizar — edita un item. */
    public function actualizarDato(int $id, string $tipo, int $item)
    {
        $cp = $this->svc->clienteConPersona((int) session('tenant_id'), $id);
        if (!$cp) {
            return redirect()->to('/socios/clientes')->with('error', 'Cliente no encontrado.');
        }

        $r = $this->svc->actualizarDato(
            (int) $cp['persona']['id'], $tipo, $item,
            $this->request->getPost(), $this->request->getFile('archivo')
        );
        if (!$r['ok']) {
            return redirect()->to('/socios/clientes/' . $id)->with('error', $r['error']);
        }

        return redirect()->to('/socios/clientes/' . $id . '?tab=' . $tipo)
            ->with('success', 'Dato actualizado.');
    }

    /** POST /socios/clientes/{id}/dato/{tipo}/{item}/eliminar — borra un item. */
    public function eliminarDato(int $id, string $tipo, int $item)
    {
        $cp = $this->svc->clienteConPersona((int) session('tenant_id'), $id);
        if ($cp) {
            $this->svc->eliminarDato((int) $cp['persona']['id'], $tipo, $item);
        }
        return redirect()->to('/socios/clientes/' . $id . '?tab=' . $tipo);
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
