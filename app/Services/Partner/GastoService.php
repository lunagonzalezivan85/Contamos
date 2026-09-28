<?php

namespace App\Services\Partner;

use App\Models\EmpleadoModel;
use App\Models\GastoCategoriaModel;
use App\Models\GastoModel;

/**
 * Lógica del control de gastos — egresos por categoría, sin detalle.
 * Reglas:
 *   - La categoría y el empleado deben pertenecer al tenant.
 *   - La fecha no puede ser futura.
 *   - Anular no borra: deja el registro con estado ANULADO fuera de los totales.
 */
class GastoService
{
    private GastoModel          $gastos;
    private GastoCategoriaModel $categorias;
    private EmpleadoModel       $empleados;

    public function __construct()
    {
        $this->gastos     = new GastoModel();
        $this->categorias = new GastoCategoriaModel();
        $this->empleados  = new EmpleadoModel();
    }

    /**
     * Categorías del tenant. Si aún no tiene, siembra las por defecto
     * (la primera vez que entra al módulo).
     */
    public function categorias(int $tenantId): array
    {
        $cats = $this->categorias->deTenant($tenantId);
        if ($cats === []) {
            $now = date('Y-m-d H:i:s');
            foreach (GastoCategoriaModel::DEFAULTS as $nombre) {
                $this->categorias->insert([
                    'tenant_id'  => $tenantId,
                    'nombre'     => $nombre,
                    'estado'     => GastoCategoriaModel::ACTIVO,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
            $cats = $this->categorias->deTenant($tenantId);
        }
        return $cats;
    }

    /** Solo activas — para el select del formulario. */
    public function categoriasActivas(int $tenantId): array
    {
        $this->categorias($tenantId); // asegura el seed
        return $this->categorias->activas($tenantId);
    }

    /** Empleados activos — opcional, "quién hizo el gasto". */
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

    /** Listado con filtros (desde/hasta/categoria_id/buscar). */
    public function listar(int $tenantId, array $filtros): array
    {
        return $this->gastos->deTenant($tenantId, $filtros);
    }

    /**
     * Métricas para las tarjetas:
     * total del filtro, n del filtro, total del mes actual, por categoría.
     */
    public function metricas(int $tenantId, array $filtros, array $rows): array
    {
        $total = round(array_sum(array_map(
            fn($g) => $g['estado'] === GastoModel::ACTIVO ? (float) $g['monto'] : 0.0, $rows)), 2);

        return [
            'total_filtro' => $total,
            'n_filtro'     => count($rows),
            'total_mes'    => $this->gastos->totalRango(
                $tenantId, date('Y-m-01'), date('Y-m-t')),
            'por_cat'      => $this->gastos->totalesPorCategoria($tenantId, $filtros),
        ];
    }

    /** Crea el gasto. @return array{ok:bool, error?:string} */
    public function guardar(int $tenantId, array $d, int $userId): array
    {
        $r = $this->validar($tenantId, $d);
        if (!$r['ok']) return $r;

        $this->gastos->insert([
            'tenant_id'      => $tenantId,
            'categoria_id'   => (int) $d['categoria_id'],
            'empleado_id'    => !empty($d['empleado_id']) ? (int) $d['empleado_id'] : null,
            'registrado_por' => $userId,
            'fecha'          => $d['fecha'],
            'concepto'       => trim((string) $d['concepto']),
            'descripcion'    => trim((string) ($d['descripcion'] ?? '')) ?: null,
            'referencia'     => trim((string) ($d['referencia'] ?? '')) ?: null,
            'monto'          => (float) $d['monto'],
            'metodo'         => $d['metodo'],
            'estado'         => GastoModel::ACTIVO,
        ]);

        return ['ok' => true];
    }

    /** Edita un gasto activo del tenant. */
    public function actualizar(int $tenantId, int $id, array $d): array
    {
        $g = $this->gastos->deTenantPorId($tenantId, $id);
        if (!$g) return ['ok' => false, 'error' => 'El gasto no existe.'];
        if ($g['estado'] === GastoModel::ANULADO) {
            return ['ok' => false, 'error' => 'No se puede editar un gasto anulado.'];
        }

        $r = $this->validar($tenantId, $d);
        if (!$r['ok']) return $r;

        $this->gastos->update($id, [
            'categoria_id' => (int) $d['categoria_id'],
            'empleado_id'  => !empty($d['empleado_id']) ? (int) $d['empleado_id'] : null,
            'fecha'        => $d['fecha'],
            'concepto'     => trim((string) $d['concepto']),
            'descripcion'  => trim((string) ($d['descripcion'] ?? '')) ?: null,
            'referencia'   => trim((string) ($d['referencia'] ?? '')) ?: null,
            'monto'        => (float) $d['monto'],
            'metodo'       => $d['metodo'],
        ]);

        return ['ok' => true];
    }

    /** Anula el gasto — queda como historial pero ya no suma. */
    public function anular(int $tenantId, int $id): array
    {
        $g = $this->gastos->deTenantPorId($tenantId, $id);
        if (!$g) return ['ok' => false, 'error' => 'El gasto no existe.'];
        if ($g['estado'] === GastoModel::ANULADO) {
            return ['ok' => false, 'error' => 'El gasto ya está anulado.'];
        }
        $this->gastos->update($id, ['estado' => GastoModel::ANULADO]);
        return ['ok' => true];
    }

    /** Nueva categoría del tenant (nombre único, reactiva si ya existía inactiva). */
    public function guardarCategoria(int $tenantId, string $nombre, string $descripcion = ''): array
    {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 80) {
            return ['ok' => false, 'error' => 'Nombre de categoría inválido.'];
        }

        $ya = $this->categorias->where('tenant_id', $tenantId)
            ->where('nombre', $nombre)->first();
        if ($ya) {
            if ($ya['estado'] === GastoCategoriaModel::INACTIVO) {
                $this->categorias->update($ya['id'], ['estado' => GastoCategoriaModel::ACTIVO]);
                return ['ok' => true, 'msg' => 'Categoría reactivada.'];
            }
            return ['ok' => false, 'error' => 'Ya existe una categoría con ese nombre.'];
        }

        $this->categorias->insert([
            'tenant_id'   => $tenantId,
            'nombre'      => $nombre,
            'descripcion' => $descripcion !== '' ? $descripcion : null,
            'estado'      => GastoCategoriaModel::ACTIVO,
        ]);
        return ['ok' => true, 'msg' => 'Categoría creada.'];
    }

    /** Activa/inactiva una categoría del tenant. */
    public function toggleCategoria(int $tenantId, int $id): array
    {
        $c = $this->categorias->where('tenant_id', $tenantId)->find($id);
        if (!$c) return ['ok' => false, 'error' => 'La categoría no existe.'];
        $nuevo = $c['estado'] === GastoCategoriaModel::ACTIVO
            ? GastoCategoriaModel::INACTIVO : GastoCategoriaModel::ACTIVO;
        $this->categorias->update($id, ['estado' => $nuevo]);
        return ['ok' => true, 'estado' => $nuevo];
    }

    // ---------------------------------------------------------------
    // Internas
    // ---------------------------------------------------------------

    /** Validación de negocio común a crear/editar. */
    private function validar(int $tenantId, array $d): array
    {
        if (empty($d['fecha']) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $d['fecha'])) {
            return ['ok' => false, 'error' => 'Fecha inválida.'];
        }
        if ((string) $d['fecha'] > date('Y-m-d')) {
            return ['ok' => false, 'error' => 'La fecha del gasto no puede ser futura.'];
        }
        if (!array_key_exists((string) $d['metodo'], GastoModel::METODOS)) {
            return ['ok' => false, 'error' => 'Método de pago inválido.'];
        }

        $cat = $this->categorias->where('tenant_id', $tenantId)
            ->where('estado', GastoCategoriaModel::ACTIVO)
            ->find((int) ($d['categoria_id'] ?? 0));
        if (!$cat) {
            return ['ok' => false, 'error' => 'La categoría no existe o está inactiva.'];
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
