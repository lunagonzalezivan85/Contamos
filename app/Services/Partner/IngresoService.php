<?php

namespace App\Services\Partner;

use App\Models\EmpleadoModel;
use App\Models\IngresoModel;
use App\Models\TenantModel;

/**
 * Lógica del control de ingresos — ventas, donaciones y otros ingresos.
 * Reglas:
 *   - Solo VENTA lleva IVA (en el resto se fuerza iva_pct = 0) y genera recibo.
 *   - El empleado debe pertenecer al tenant; la fecha no puede ser futura.
 *   - Anular no borra: queda como ANULADO fuera de los totales.
 */
class IngresoService
{
    private IngresoModel  $ingresos;
    private EmpleadoModel $empleados;
    private TenantModel   $tenants;

    public function __construct()
    {
        $this->ingresos  = new IngresoModel();
        $this->empleados = new EmpleadoModel();
        $this->tenants   = new TenantModel();
    }

    /** Empleados activos — opcional, "quién cobró". */
    public function empleados(int $tenantId): array
    {
        return $this->empleados
            ->select('empleados.id, personas.nombres, personas.apellidos')
            ->join('personas', 'personas.id = empleados.persona_id')
            ->where('empleados.tenant_id', $tenantId)
            ->where('empleados.estado', 'ACTIVO')
            ->orderBy('personas.apellidos', 'ASC')
            ->findAll();
    }

    /** Listado con filtros (desde/hasta/tipo/buscar/anulados). */
    public function listar(int $tenantId, array $filtros): array
    {
        return $this->ingresos->deTenant($tenantId, $filtros);
    }

    /**
     * Métricas para las tarjetas:
     * total del filtro (con IVA), n del filtro, total del mes, desglose por tipo.
     */
    public function metricas(int $tenantId, array $rows): array
    {
        $total = 0.0;
        $porTipo = [];
        foreach ($rows as $i) {
            if ($i['estado'] !== IngresoModel::ACTIVO) continue;
            $t = IngresoModel::total($i);
            $total += $t;
            $porTipo[$i['tipo']] = ($porTipo[$i['tipo']] ?? 0) + $t;
        }

        return [
            'total_filtro' => round($total, 2),
            'n_filtro'     => count($rows),
            'total_mes'    => $this->ingresos->totalRango(
                $tenantId, date('Y-m-01'), date('Y-m-t')),
            'por_tipo'     => $porTipo,
        ];
    }

    /** Crea el ingreso. @return array{ok:bool, id?:int, error?:string} */
    public function guardar(int $tenantId, array $d, int $userId): array
    {
        $r = $this->validar($tenantId, $d);
        if (!$r['ok']) return $r;

        $id = $this->ingresos->insert([
            'tenant_id'      => $tenantId,
            'tipo'           => $d['tipo'],
            'empleado_id'    => !empty($d['empleado_id']) ? (int) $d['empleado_id'] : null,
            'registrado_por' => $userId,
            'fecha'          => $d['fecha'],
            'concepto'       => trim((string) $d['concepto']),
            'descripcion'    => trim((string) ($d['descripcion'] ?? '')) ?: null,
            'referencia'     => trim((string) ($d['referencia'] ?? '')) ?: null,
            'monto'          => (float) $d['monto'],
            'iva_pct'        => $d['tipo'] === IngresoModel::TIPO_VENTA ? (float) ($d['iva_pct'] ?? 0) : 0,
            'metodo'         => $d['metodo'],
            'estado'         => IngresoModel::ACTIVO,
        ]);

        return ['ok' => true, 'id' => (int) $id];
    }

    /** Edita un ingreso activo del tenant. */
    public function actualizar(int $tenantId, int $id, array $d): array
    {
        $i = $this->ingresos->deTenantPorId($tenantId, $id);
        if (!$i) return ['ok' => false, 'error' => 'El ingreso no existe.'];
        if ($i['estado'] === IngresoModel::ANULADO) {
            return ['ok' => false, 'error' => 'No se puede editar un ingreso anulado.'];
        }

        $r = $this->validar($tenantId, $d);
        if (!$r['ok']) return $r;

        $this->ingresos->update($id, [
            'tipo'        => $d['tipo'],
            'empleado_id' => !empty($d['empleado_id']) ? (int) $d['empleado_id'] : null,
            'fecha'       => $d['fecha'],
            'concepto'    => trim((string) $d['concepto']),
            'descripcion' => trim((string) ($d['descripcion'] ?? '')) ?: null,
            'referencia'  => trim((string) ($d['referencia'] ?? '')) ?: null,
            'monto'       => (float) $d['monto'],
            'iva_pct'     => $d['tipo'] === IngresoModel::TIPO_VENTA ? (float) ($d['iva_pct'] ?? 0) : 0,
            'metodo'      => $d['metodo'],
        ]);

        return ['ok' => true];
    }

    /** Anula el ingreso — queda como historial pero ya no suma. */
    public function anular(int $tenantId, int $id): array
    {
        $i = $this->ingresos->deTenantPorId($tenantId, $id);
        if (!$i) return ['ok' => false, 'error' => 'El ingreso no existe.'];
        if ($i['estado'] === IngresoModel::ANULADO) {
            return ['ok' => false, 'error' => 'El ingreso ya está anulado.'];
        }
        $this->ingresos->update($id, ['estado' => IngresoModel::ANULADO]);
        return ['ok' => true];
    }

    /** Datos para el voucher imprimible (solo tipo VENTA). */
    public function recibo(int $tenantId, int $id): ?array
    {
        $i = $this->ingresos
            ->select('ingresos.*, personas.nombres, personas.apellidos, u.username AS reg_usuario')
            ->join('empleados emp', 'emp.id = ingresos.empleado_id', 'left')
            ->join('personas', 'personas.id = emp.persona_id', 'left')
            ->join('users u', 'u.id = ingresos.registrado_por', 'left')
            ->where('ingresos.tenant_id', $tenantId)
            ->find($id);
        if (!$i) return null;

        return [
            'ingreso'   => $i,
            'tenant'    => $this->tenants->find($tenantId),
            'iva'       => IngresoModel::iva($i),
            'total'     => IngresoModel::total($i),
            'reciboNum' => IngresoModel::reciboCode($i, $this->tenants->find($tenantId)['nombre'] ?? ''),
        ];
    }

    // ---------------------------------------------------------------
    // Internas
    // ---------------------------------------------------------------

    /** Validación de negocio común a crear/editar. */
    private function validar(int $tenantId, array $d): array
    {
        if (!array_key_exists((string) ($d['tipo'] ?? ''), IngresoModel::TIPOS)) {
            return ['ok' => false, 'error' => 'Tipo de ingreso inválido.'];
        }
        if (empty($d['fecha']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['fecha'])) {
            return ['ok' => false, 'error' => 'Fecha inválida.'];
        }
        if ((string) $d['fecha'] > date('Y-m-d')) {
            return ['ok' => false, 'error' => 'La fecha del ingreso no puede ser futura.'];
        }
        if (!array_key_exists((string) $d['metodo'], IngresoModel::METODOS)) {
            return ['ok' => false, 'error' => 'Método de cobro inválido.'];
        }
        $iva = (float) ($d['iva_pct'] ?? 0);
        if ($iva < 0 || $iva > 100) {
            return ['ok' => false, 'error' => 'El % de IVA debe estar entre 0 y 100.'];
        }
        if (!empty($d['empleado_id'])) {
            $emp = $this->empleados->where('tenant_id', $tenantId)
                ->find((int) $d['empleado_id']);
            if (!$emp) {
                return ['ok' => false, 'error' => 'El empleado no pertenece a este negocio.'];
            }
        }
        return ['ok' => true];
    }
}
