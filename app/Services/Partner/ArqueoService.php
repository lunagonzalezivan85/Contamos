<?php

namespace App\Services\Partner;

use App\Models\ArqueoModel;
use App\Models\EmpleadoModel;
use App\Models\PagoModel;
use App\Models\SolicitudModel;

/**
 * Arqueo de caja del gestor — cierre diario.
 *
 *   esperado   = saldo_inicial + cobros − desembolsos
 *   diferencia = contado − esperado
 *
 * cobros      = pagos APLICADO cobrados por el gestor ese día (fecha_hora)
 * desembolsos = dinero entregado en campo ese día (solicitudes.fecha_entrega)
 * saldo_inicial = `contado` del último arqueo previo del gestor (0 si es el primero)
 */
class ArqueoService
{
    private ArqueoModel    $arqueos;
    private EmpleadoModel  $empleados;
    private PagoModel      $pagos;
    private SolicitudModel $solicitudes;

    public function __construct()
    {
        $this->arqueos     = new ArqueoModel();
        $this->empleados   = new EmpleadoModel();
        $this->pagos       = new PagoModel();
        $this->solicitudes = new SolicitudModel();
    }

    /** Gestores activos del tenant (para el selector del arqueo). */
    public function gestores(int $tenantId): array
    {
        return $this->empleados
            ->select('empleados.id, empleados.carnet, personas.nombres, personas.apellidos')
            ->join('personas', 'personas.id = empleados.persona_id')
            ->where('empleados.tenant_id', $tenantId)
            ->where('empleados.estado', 'ACTIVO')
            ->orderBy('personas.apellidos', 'ASC')
            ->findAll();
    }

    public function empleado(int $tenantId, int $empleadoId): ?array
    {
        return $this->empleados
            ->select('empleados.*, personas.nombres, personas.apellidos')
            ->join('personas', 'personas.id = empleados.persona_id')
            ->where('empleados.tenant_id', $tenantId)
            ->find($empleadoId);
    }

    /**
     * Movimientos del día del gestor.
     * @return array{cobros: array, desembolsos: array, total_cobros: float, total_desembolsos: float}
     */
    public function movimientosDia(int $tenantId, int $empleadoId, string $fecha): array
    {
        $cobros = $this->pagos
            ->select('pagos.*, personas.nombres, personas.apellidos, solicitudes.codigo_credito')
            ->join('solicitudes', 'solicitudes.id = pagos.solicitud_id')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->where('pagos.tenant_id', $tenantId)
            ->where('pagos.empleado_id', $empleadoId)
            ->where('pagos.estado', PagoModel::APLICADO)
            // Los contra-pagos de reversión (monto negativo) son corrección
            // contable — no mueven efectivo de la caja del gestor.
            ->where('pagos.revierte_id IS NULL', null, false)
            ->where('pagos.monto >', 0)
            ->where('DATE(pagos.fecha_hora)', $fecha)
            ->orderBy('pagos.fecha_hora', 'ASC')
            ->findAll();

        $desembolsos = $this->solicitudes
            ->select('solicitudes.id, solicitudes.codigo_credito, solicitudes.monto_aprobado,
                      solicitudes.fecha_entrega, personas.nombres, personas.apellidos')
            ->join('clientes', 'clientes.id = solicitudes.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->where('solicitudes.tenant_id', $tenantId)
            ->where('solicitudes.asignado_a', $empleadoId)
            ->where('solicitudes.estado', SolicitudModel::ACTIVO)
            ->where('DATE(solicitudes.fecha_entrega)', $fecha)
            ->orderBy('solicitudes.fecha_entrega', 'ASC')
            ->findAll();

        return [
            'cobros'            => $cobros,
            'desembolsos'       => $desembolsos,
            'total_cobros'      => round(array_sum(array_column($cobros, 'monto')), 2),
            'total_desembolsos' => round(array_sum(array_map(
                fn($d) => (float) ($d['monto_aprobado'] ?? 0), $desembolsos)), 2),
        ];
    }

    /** Saldo inicial = lo contado en el último arqueo previo del gestor (0 si es el primero). */
    public function saldoInicial(int $tenantId, int $empleadoId, string $fecha): float
    {
        $prev = $this->arqueos->ultimoAnterior($tenantId, $empleadoId, $fecha);
        return $prev ? (float) $prev['contado'] : 0.0;
    }

    /**
     * Resumen calculado del día: lo que debería tener el gestor en efectivo.
     * @return array{inicial: float, cobros: float, desembolsos: float, esperado: float,
     *               mov: array, arqueo: ?array}
     */
    public function resumenDia(int $tenantId, int $empleadoId, string $fecha): array
    {
        $mov     = $this->movimientosDia($tenantId, $empleadoId, $fecha);
        $inicial = $this->saldoInicial($tenantId, $empleadoId, $fecha);

        return [
            'inicial'     => $inicial,
            'cobros'      => $mov['total_cobros'],
            'desembolsos' => $mov['total_desembolsos'],
            'esperado'    => round($inicial + $mov['total_cobros'] - $mov['total_desembolsos'], 2),
            'mov'         => $mov,
            'arqueo'      => $this->arqueos->deDia($tenantId, $empleadoId, $fecha),
        ];
    }

    /**
     * Cierra el arqueo del día. Si ya existe se recalcula (upsert por
     * unique key tenant+empleado+fecha).
     * @return array{ok: bool, diferencia?: float, estado?: string, error?: string}
     */
    public function guardar(int $tenantId, int $empleadoId, string $fecha,
                            float $contado, ?string $observacion, int $userId): array
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $fecha) || $fecha > date('Y-m-d')) {
            return ['ok' => false, 'error' => 'Fecha inválida o futura.'];
        }
        if (!$this->empleado($tenantId, $empleadoId)) {
            return ['ok' => false, 'error' => 'El gestor no pertenece a este tenant.'];
        }

        $r          = $this->resumenDia($tenantId, $empleadoId, $fecha);
        $diferencia = round($contado - $r['esperado'], 2);
        $data = [
            'tenant_id'     => $tenantId,
            'empleado_id'   => $empleadoId,
            'fecha'         => $fecha,
            'saldo_inicial' => $r['inicial'],
            'cobros'        => $r['cobros'],
            'desembolsos'   => $r['desembolsos'],
            'esperado'      => $r['esperado'],
            'contado'       => $contado,
            'diferencia'    => $diferencia,
            'estado'        => abs($diferencia) < 0.01 ? ArqueoModel::CUADRADO : ArqueoModel::DIFERENCIA,
            'observacion'   => $observacion !== '' ? $observacion : null,
            'resuelto_por'  => $userId,
        ];

        $existe = $this->arqueos->deDia($tenantId, $empleadoId, $fecha);
        $existe
            ? $this->arqueos->update((int) $existe['id'], $data)
            : $this->arqueos->insert($data);

        return ['ok' => true, 'diferencia' => $diferencia, 'estado' => $data['estado']];
    }

    /** Historial de arqueos del tenant. */
    public function historial(int $tenantId, int $limit = 20): array
    {
        return $this->arqueos->historial($tenantId, $limit);
    }
}
