<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Timeline de una solicitud: quién la creó, editó y movió de estado.
 * `actor` trae el nombre legible desnormalizado (el timeline sobrevive
 * aunque el usuario/empleado se borre).
 */
class SolicitudHistorialModel extends Model
{
    protected $table         = 'solicitud_historial';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $useTimestamps = true;
    protected $updatedField  = '';              // solo created_at

    protected $allowedFields = [
        'tenant_id', 'solicitud_id', 'accion', 'estado', 'nota',
        'user_id', 'empleado_id', 'actor',
    ];

    public const CREADO   = 'CREADO';
    public const ESTADO   = 'ESTADO';
    public const EDITADO  = 'EDITADO';

    /**
     * Registra un evento. $actor: ['user_id'=>, 'empleado_id'=>, 'nombre'=>].
     * Nunca rompe el flujo — si la tabla no existe aún, traga el error.
     */
    public function registrar(int $tenantId, int $solId, string $accion,
                              ?string $estado, ?string $nota, array $actor = []): void
    {
        try {
            $this->insert([
                'tenant_id'    => $tenantId,
                'solicitud_id' => $solId,
                'accion'       => $accion,
                'estado'       => $estado,
                'nota'         => $nota !== '' ? $nota : null,
                'user_id'      => $actor['user_id'] ?? null,
                'empleado_id'  => $actor['empleado_id'] ?? null,
                'actor'        => ($actor['nombre'] ?? '') !== '' ? $actor['nombre'] : null,
            ]);
        } catch (\Throwable $e) {
            log_error('SolicitudHistorial::registrar', $e);
        }
    }

    /** Timeline completo de la solicitud, más reciente primero. */
    public function deSolicitud(int $tenantId, int $solId): array
    {
        try {
            return $this->where('tenant_id', $tenantId)
                ->where('solicitud_id', $solId)
                ->orderBy('id', 'DESC')
                ->findAll();
        } catch (\Throwable $e) {
            log_error('SolicitudHistorial::deSolicitud', $e);
            return [];
        }
    }
}
