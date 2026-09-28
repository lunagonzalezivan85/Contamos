<?php

namespace App\Services\Partner;

use App\Models\AsistenciaModel;
use App\Models\EmpleadoModel;

/**
 * Control de asistencia — lógica de marcación y listados.
 *
 * Marcación única (kiosco): carnet + PIN → el sistema decide solo:
 *   - Si el empleado tiene una jornada ABIERTA → la marca es la SALIDA
 *     (la cierra aunque sea del día siguiente — cubre turnos que cruzan
 *     la medianoche).
 *   - Si no tiene abierta → la marca es la ENTRADA de una jornada nueva.
 * Cooldown de 90 segundos para evitar doble-marcación accidental.
 *
 * El admin puede corregir/anular marcaciones desde /asistencia.
 */
class AsistenciaService
{
    private AsistenciaModel $asistencias;
    private EmpleadoModel   $empleados;

    private const COOLDOWN_SEG = 90;

    public function __construct()
    {
        $this->asistencias = new AsistenciaModel();
        $this->empleados   = new EmpleadoModel();
    }

    /**
     * Registra una marcación del kiosco.
     * @return array{ok: bool, tipo?: string, hora?: string, empleado?: string,
     *               horas?: float, error?: string}
     */
    public function marcarPorCarnet(int $tenantId, string $carnet, string $pin, ?string $ip = null): array
    {
        $emp = $this->empleados
            ->where('tenant_id', $tenantId)
            ->where('carnet', strtoupper(trim($carnet)))
            ->where('pin', trim($pin))
            ->where('estado', 'ACTIVO')
            ->first();
        if (!$emp) {
            return ['ok' => false, 'error' => 'Carnet o PIN incorrectos.'];
        }

        $nombre = trim(($emp['nombres'] ?? '') . ' ' . ($emp['apellidos'] ?? ''));
        // Si el join de persona no viene en el modelo, lo resolvemos aparte.
        if ($nombre === '') {
            $per = \Config\Database::connect()->table('personas')
                ->where('id', (int) $emp['persona_id'])->get()->getRowArray();
            $nombre = trim(($per['nombres'] ?? '') . ' ' . ($per['apellidos'] ?? '')) ?: 'Empleado';
        }

        $ahora   = date('Y-m-d H:i:s');
        $abierta = $this->asistencias->abiertaDe((int) $emp['id']);

        // Cooldown: la última marcación fue hace menos de 90s → es doble-tap.
        if ($abierta && (strtotime($ahora) - strtotime((string) $abierta['entrada'])) < self::COOLDOWN_SEG) {
            return ['ok' => false, 'error' => 'Acabás de marcar la entrada — esperá un momento.'];
        }
        if (!$abierta) {
            $ultima = $this->asistencias->where('empleado_id', (int) $emp['id'])
                ->where('estado !=', AsistenciaModel::ANULADA)
                ->orderBy('entrada', 'DESC')->first();
            if ($ultima && !empty($ultima['salida'])
                && (strtotime($ahora) - strtotime((string) $ultima['salida'])) < self::COOLDOWN_SEG) {
                return ['ok' => false, 'error' => 'Acabás de marcar la salida — esperá un momento.'];
            }
        }

        if ($abierta) {
            // Segunda marcación → cierra la jornada (salida), aunque cruce día.
            $this->asistencias->update((int) $abierta['id'], [
                'salida' => $ahora,
                'estado' => AsistenciaModel::CERRADA,
            ]);
            $horas = round((strtotime($ahora) - strtotime((string) $abierta['entrada'])) / 3600, 2);
            return [
                'ok' => true, 'tipo' => 'SALIDA', 'hora' => date('h:i a', strtotime($ahora)),
                'empleado' => $nombre, 'horas' => $horas,
            ];
        }

        $this->asistencias->insert([
            'tenant_id'   => $tenantId,
            'empleado_id' => (int) $emp['id'],
            'fecha'       => date('Y-m-d'),
            'entrada'     => $ahora,
            'estado'      => AsistenciaModel::ABIERTA,
            'ip'          => $ip,
        ]);
        return [
            'ok' => true, 'tipo' => 'ENTRADA', 'hora' => date('h:i a', strtotime($ahora)),
            'empleado' => $nombre,
        ];
    }

    /** Longitud del PIN del empleado (para pintar las casillas del kiosco). */
    public function longitudPin(int $tenantId, string $carnet): int
    {
        $emp = $this->empleados->where('tenant_id', $tenantId)
            ->where('carnet', strtoupper(trim($carnet)))
            ->where('estado', 'ACTIVO')
            ->first();
        if (!$emp || empty($emp['pin'])) {
            return 4;
        }
        return min(10, max(4, strlen(trim((string) $emp['pin']))));
    }

    /** Marcaciones registradas hoy en el tenant (señal de actividad del kiosco). */
    public function marcasHoy(int $tenantId): int
    {
        return $this->asistencias->where('tenant_id', $tenantId)
            ->where('fecha', date('Y-m-d'))
            ->where('estado !=', AsistenciaModel::ANULADA)
            ->countAllResults();
    }

    /** Marcaciones del rango con datos del empleado (admin). */
    public function listado(int $tenantId, array $f = []): array
    {
        $q = \Config\Database::connect()->table('asistencias a')
            ->select('a.*, e.carnet, e.cargo, p.nombres, p.apellidos, u.username AS edit_usuario')
            ->join('empleados e', 'e.id = a.empleado_id')
            ->join('personas p', 'p.id = e.persona_id')
            ->join('users u', 'u.id = a.editado_por', 'left')
            ->where('a.tenant_id', $tenantId)
            ->orderBy('a.fecha', 'DESC')->orderBy('a.entrada', 'DESC');

        if (!empty($f['desde']))        $q->where('a.fecha >=', $f['desde']);
        if (!empty($f['hasta']))        $q->where('a.fecha <=', $f['hasta']);
        if (!empty($f['empleado_id']))  $q->where('a.empleado_id', (int) $f['empleado_id']);
        if (empty($f['anuladas']))      $q->where('a.estado !=', AsistenciaModel::ANULADA);
        if (!empty($f['abiertas']))     $q->where('a.estado', AsistenciaModel::ABIERTA);

        return $q->get()->getResultArray();
    }

    /** Quién está adentro ahora (jornadas abiertas) + jornadas de hoy. */
    public function metricas(int $tenantId): array
    {
        $hoy = date('Y-m-d');
        $m = new AsistenciaModel();
        return [
            'adentro'  => $m->where('tenant_id', $tenantId)->where('estado', AsistenciaModel::ABIERTA)->countAllResults(),
            'hoy'      => $m->where('tenant_id', $tenantId)->where('fecha', $hoy)
                            ->where('estado !=', AsistenciaModel::ANULADA)->countAllResults(),
            'abiertas' => $this->listado($tenantId, ['abiertas' => true]),
        ];
    }

    /** Empleados activos del tenant (para el alta manual). */
    public function empleados(int $tenantId): array
    {
        return \Config\Database::connect()->table('empleados e')
            ->select('e.id, e.carnet, e.cargo, p.nombres, p.apellidos')
            ->join('personas p', 'p.id = e.persona_id')
            ->where('e.tenant_id', $tenantId)
            ->where('e.estado', 'ACTIVO')
            ->orderBy('p.nombres')
            ->get()->getResultArray();
    }

    /**
     * Alta manual de una jornada (admin completa una marcación olvidada).
     * @return array{ok: bool, error?: string}
     */
    public function guardarManual(int $tenantId, array $d, int $userId): array
    {
        $emp = $this->empleados->where('tenant_id', $tenantId)->find((int) ($d['empleado_id'] ?? 0));
        if (!$emp) {
            return ['ok' => false, 'error' => 'Empleado no válido.'];
        }
        $entrada = trim((string) ($d['entrada'] ?? ''));
        $salida  = trim((string) ($d['salida'] ?? ''));
        if ($entrada === '') {
            return ['ok' => false, 'error' => 'La entrada es obligatoria.'];
        }
        if ($salida !== '' && $salida <= $entrada) {
            return ['ok' => false, 'error' => 'La salida no puede ser antes o igual que la entrada.'];
        }

        $this->asistencias->insert([
            'tenant_id'      => $tenantId,
            'empleado_id'    => (int) $emp['id'],
            'fecha'          => substr($entrada, 0, 10),
            'entrada'        => $entrada,
            'salida'         => $salida !== '' ? $salida : null,
            'estado'         => $salida !== '' ? AsistenciaModel::CERRADA : AsistenciaModel::ABIERTA,
            'registrado_por' => $userId,
            'editada'        => 1,
            'observacion'    => trim((string) ($d['observacion'] ?? '')) ?: null,
        ]);
        return ['ok' => true];
    }

    /**
     * Corrige una jornada (entrada/salida/observación). Marca editada + quién.
     * @return array{ok: bool, error?: string}
     */
    public function actualizar(int $tenantId, int $id, array $d, int $userId): array
    {
        $a = $this->asistencias->where('tenant_id', $tenantId)->find($id);
        if (!$a || $a['estado'] === AsistenciaModel::ANULADA) {
            return ['ok' => false, 'error' => 'Marcación no encontrada.'];
        }
        $entrada = trim((string) ($d['entrada'] ?? '')) ?: (string) $a['entrada'];
        $salida  = trim((string) ($d['salida'] ?? ''));
        $salida  = $salida !== '' ? $salida : null;
        if ($salida && $salida <= $entrada) {
            return ['ok' => false, 'error' => 'La salida no puede ser antes o igual que la entrada.'];
        }
        $this->asistencias->update($id, [
            'fecha'       => substr($entrada, 0, 10),
            'entrada'     => $entrada,
            'salida'      => $salida,
            'estado'      => $salida ? AsistenciaModel::CERRADA : AsistenciaModel::ABIERTA,
            'editada'     => 1,
            'editado_por' => $userId,
            'observacion' => trim((string) ($d['observacion'] ?? '')) ?: null,
        ]);
        return ['ok' => true];
    }

    /** Anula una jornada (queda registrada pero no cuenta). */
    public function anular(int $tenantId, int $id, int $userId): array
    {
        $a = $this->asistencias->where('tenant_id', $tenantId)->find($id);
        if (!$a || $a['estado'] === AsistenciaModel::ANULADA) {
            return ['ok' => false, 'error' => 'Marcación no encontrada.'];
        }
        $this->asistencias->update($id, [
            'estado'      => AsistenciaModel::ANULADA,
            'editado_por' => $userId,
        ]);
        return ['ok' => true];
    }
}
