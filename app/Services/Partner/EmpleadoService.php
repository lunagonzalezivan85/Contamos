<?php

namespace App\Services\Partner;

use App\Models\EmpleadoModel;
use App\Models\PersonaModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Lógica de negocio de empleados del tenant:
 * empleado (rol) ligado a persona (identidad en PersonaService).
 */
class EmpleadoService
{
    private const TIPO = 'EMPLEADO';

    private EmpleadoModel  $empleados;
    private PersonaModel   $personas;
    private PersonaService $personaSvc;

    public function __construct()
    {
        $this->empleados  = new EmpleadoModel();
        $this->personas   = new PersonaModel();
        $this->personaSvc = new PersonaService();
    }

    /** Listado paginado (el pager queda en la instancia del modelo). */
    public function listar(int $tenantId, string $buscar): array
    {
        $model = new EmpleadoModel();
        $rows  = $model->filtrar($tenantId, $buscar)->paginate(15);
        $model->pager->only(['q']);
        return ['rows' => $rows, 'pager' => $model->pager];
    }

    public function carnetSugerido(int $tenantId, string $tenantNombre): string
    {
        return $this->empleados->siguienteCarnet($tenantId, $tenantNombre);
    }

    /**
     * Empleado + su persona. Null si no existe o no es del tenant.
     * @return array{empleado: array, persona: array}|null
     */
    public function empleadoConPersona(int $tenantId, int $id): ?array
    {
        $empleado = $this->empleados->find($id);
        $persona  = $empleado
            ? $this->personas->deTenant($tenantId, (int) $empleado['persona_id'], self::TIPO)
            : null;
        return ($empleado && $persona) ? ['empleado' => $empleado, 'persona' => $persona] : null;
    }

    /** Ficha completa: empleado + persona + todas las secciones del expediente. */
    public function ficha(int $tenantId, int $id): ?array
    {
        $ep = $this->empleadoConPersona($tenantId, $id);
        if (!$ep) {
            return null;
        }
        return $ep + ['secciones' => $this->personaSvc->seccionesDe((int) $ep['persona']['id'])];
    }

    /**
     * Alta de empleado: persona + empleado con carnet correlativo y PIN
     * (autogenerado de 4 dígitos si viene vacío).
     * @return array{empleado_id: int, carnet: string, pin: string}
     */
    public function registrar(int $tenantId, string $tenantNombre, array $d): array
    {
        $carnet = $this->empleados->siguienteCarnet($tenantId, $tenantNombre);
        $pin    = trim((string) ($d['pin'] ?? ''));
        if ($pin === '') {
            $pin = str_pad((string) random_int(0, 9999), 4, '0', STR_PAD_LEFT);
        }

        $personaId  = $this->personaSvc->crear($tenantId, self::TIPO, $d);
        $empleadoId = $this->empleados->insert([
            'tenant_id'     => $tenantId,
            'persona_id'    => $personaId,
            'carnet'        => $carnet,
            'pin'           => $pin,
            'cargo'         => trim((string) ($d['cargo'] ?? '')) ?: null,
            'ruta'          => trim((string) ($d['ruta'] ?? '')) ?: null,
            'fecha_ingreso' => ($d['fecha_ingreso'] ?? '') ?: null,
            'estado'        => ($d['estado'] ?? '') ?: 'ACTIVO',
        ]);

        return ['empleado_id' => (int) $empleadoId, 'carnet' => $carnet, 'pin' => $pin];
    }

    /** Actualiza persona + empleado (PIN solo si se envía uno nuevo). */
    public function actualizar(array $empleado, array $persona, array $d): void
    {
        $this->personaSvc->actualizar((int) $persona['id'], self::TIPO, $d);

        $this->empleados->update((int) $empleado['id'], [
            'cargo'         => trim((string) ($d['cargo'] ?? '')) ?: null,
            'ruta'          => trim((string) ($d['ruta'] ?? '')) ?: null,
            'fecha_ingreso' => ($d['fecha_ingreso'] ?? '') ?: null,
            'estado'        => ($d['estado'] ?? '') ?: 'ACTIVO',
            'pin'           => trim((string) ($d['pin'] ?? '')) ?: $empleado['pin'],
        ]);
    }

    // --- Expediente: delega en PersonaService ---------------------------

    public function agregarDato(int $personaId, string $tipo, array $d, ?UploadedFile $file): array
    {
        return $this->personaSvc->agregarDato($personaId, $tipo, $d, $file);
    }

    public function actualizarDato(int $personaId, string $tipo, int $item, array $d, ?UploadedFile $file): array
    {
        return $this->personaSvc->actualizarDato($personaId, $tipo, $item, $d, $file);
    }

    public function eliminarDato(int $personaId, string $tipo, int $item): void
    {
        $this->personaSvc->eliminarDato($personaId, $tipo, $item);
    }
}
