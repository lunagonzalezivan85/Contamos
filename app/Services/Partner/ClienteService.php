<?php

namespace App\Services\Partner;

use App\Models\ClienteModel;
use App\Models\PersonaModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Lógica de negocio de clientes del tenant:
 * cliente (rol) ligado a persona (identidad en PersonaService).
 */
class ClienteService
{
    private const TIPO = 'CLIENTE';

    private ClienteModel   $clientes;
    private PersonaModel   $personas;
    private PersonaService $personaSvc;

    public function __construct()
    {
        $this->clientes   = new ClienteModel();
        $this->personas   = new PersonaModel();
        $this->personaSvc = new PersonaService();
    }

    /** Listado paginado (el pager queda en la instancia del modelo). */
    public function listar(int $tenantId, string $buscar): array
    {
        $model = new ClienteModel();
        $rows  = $model->filtrar($tenantId, $buscar)->paginate(15);
        $model->pager->only(['q']);
        return ['rows' => $rows, 'pager' => $model->pager];
    }

    public function codigoSugerido(int $tenantId, string $tenantNombre): string
    {
        return $this->clientes->siguienteCodigo($tenantId, $tenantNombre);
    }

    /**
     * Cliente + su persona. Null si no existe o no es del tenant.
     * @return array{cliente: array, persona: array}|null
     */
    public function clienteConPersona(int $tenantId, int $id): ?array
    {
        $cliente = $this->clientes->find($id);
        $persona = $cliente
            ? $this->personas->deTenant($tenantId, (int) $cliente['persona_id'], self::TIPO)
            : null;
        return ($cliente && $persona) ? ['cliente' => $cliente, 'persona' => $persona] : null;
    }

    /** Ficha completa: cliente + persona + todas las secciones del expediente. */
    public function ficha(int $tenantId, int $id): ?array
    {
        $cp = $this->clienteConPersona($tenantId, $id);
        if (!$cp) {
            return null;
        }
        return $cp + ['secciones' => $this->personaSvc->seccionesDe((int) $cp['persona']['id'])];
    }

    /**
     * Alta de cliente: persona + cliente con código correlativo.
     * @return array{cliente_id: int, codigo: string}
     */
    public function registrar(int $tenantId, string $tenantNombre, array $d): array
    {
        $codigo    = $this->clientes->siguienteCodigo($tenantId, $tenantNombre);
        $personaId = $this->personaSvc->crear($tenantId, self::TIPO, $d);

        $clienteId = $this->clientes->insert([
            'tenant_id'      => $tenantId,
            'persona_id'     => $personaId,
            'codigo'         => $codigo,
            'limite_credito' => ($d['limite_credito'] ?? '') !== '' ? $d['limite_credito'] : null,
            'monto_max'      => ($d['monto_max'] ?? '') !== '' ? $d['monto_max'] : null,
            'monto_min'      => ($d['monto_min'] ?? '') !== '' ? $d['monto_min'] : null,
            'observaciones'  => trim((string) ($d['observaciones'] ?? '')) ?: null,
            'estado'         => ($d['estado'] ?? '') ?: 'ACTIVO',
        ]);

        return ['cliente_id' => (int) $clienteId, 'codigo' => $codigo];
    }

    /** Actualiza persona + cliente (límites, montos, observaciones, estado). */
    public function actualizar(array $cliente, array $persona, array $d): void
    {
        $this->personaSvc->actualizar((int) $persona['id'], self::TIPO, $d);

        $this->clientes->update((int) $cliente['id'], [
            'limite_credito' => ($d['limite_credito'] ?? '') !== '' ? $d['limite_credito'] : null,
            'monto_max'      => ($d['monto_max'] ?? '') !== '' ? $d['monto_max'] : null,
            'monto_min'      => ($d['monto_min'] ?? '') !== '' ? $d['monto_min'] : null,
            'observaciones'  => trim((string) ($d['observaciones'] ?? '')) ?: null,
            'estado'         => ($d['estado'] ?? '') ?: 'ACTIVO',
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
