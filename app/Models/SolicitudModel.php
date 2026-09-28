<?php

namespace App\Models;

use CodeIgniter\Model;

class SolicitudModel extends Model
{
    protected $table            = 'solicitudes';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'tenant_id', 'cliente_id', 'empleado_id', 'asignado_a', 'ruta', 'monto', 'plazo_meses',
        'frecuencia', 'tasa_mensual', 'dias_semana', 'destino', 'estado', 'origen',
        'monto_aprobado', 'tasa_aprobada', 'plazo_aprobado', 'frecuencia_aprobada', 'fecha_primer_pago',
        'fecha_desembolso', 'fecha_entrega', 'codigo_credito', 'saldo_favor',
        'tipo_calculo', 'gracia_meses', 'gracia_tipo', 'comision', 'seguro',
        'refinancia_id', 'paso_dias',
    ];

    /** Estados del flujo de solicitud. */
    public const CONTACTO   = 'CONTACTO';   // llegó por la web — hay que llamar al cliente
    public const CREADA     = 'CREADA';
    public const REVISION   = 'REVISION';
    public const APROBADA   = 'APROBADA';
    public const DESEMBOLSO = 'DESEMBOLSO';
    public const ACTIVO     = 'ACTIVO';    // dinero entregado — crédito vigente en cobro
    public const LIQUIDADO  = 'LIQUIDADO'; // refinanciado — el saldo lo absorbe el nuevo crédito
    public const RECHAZADA  = 'RECHAZADA';
    public const ESTADOS    = [self::CONTACTO, self::CREADA, self::REVISION, self::APROBADA, self::DESEMBOLSO, self::ACTIVO, self::LIQUIDADO, self::RECHAZADA];

    /** Permiso requerido para mover a cada estado. */
    public const PERMISO_ESTADO = [
        self::CREADA     => 'solicitudes.editar',   // CONTACTO → CREADA: tomar la solicitud
        self::REVISION   => 'solicitudes.editar',
        self::APROBADA   => 'solicitudes.aprobar',
        self::DESEMBOLSO => 'solicitudes.desembolsar',
        self::RECHAZADA  => 'solicitudes.rechazar',
    ];

    /** Código de crédito formato banco: C-{INI}-{AAAA}-{0000} por tenant y año. */
    public function siguienteCodigoCredito(int $tenantId, string $tenantNombre): string
    {
        $iniciales = '';
        foreach (explode(' ', trim(preg_replace('/\s+/', ' ', $tenantNombre))) as $palabra) {
            $ini = mb_substr(trim($palabra), 0, 1);
            if ($ini !== '') $iniciales .= mb_strtoupper($ini);
        }
        $iniciales = $iniciales !== '' ? $iniciales : 'CR';
        $prefijo   = 'C-' . $iniciales . '-' . date('Y') . '-';

        $ultimo = $this->where('tenant_id', $tenantId)
            ->like('codigo_credito', $prefijo, 'after')
            ->orderBy('codigo_credito', 'DESC')
            ->first();
        $consec = $ultimo ? ((int) substr($ultimo['codigo_credito'], -4)) + 1 : 1;

        return $prefijo . str_pad((string) $consec, 4, '0', STR_PAD_LEFT);
    }

    /** Etiquetas legibles por estado. */
    public const LABEL_ESTADO = [
        self::CONTACTO   => 'Por contactar',
        self::CREADA     => 'Creada',
        self::REVISION   => 'En revisión',
        self::APROBADA   => 'Aprobada',
        self::DESEMBOLSO => 'Por desembolsar',
        self::ACTIVO     => 'Crédito activo',
        self::LIQUIDADO  => 'Liquidado',
        self::RECHAZADA  => 'Rechazada',
    ];

    /**
     * Query con filtros para el listado (paginable via ->paginate()).
     * Acumula sobre el builder del modelo — usar instancia fresca por llamada.
     * Filtros: q (cliente), estado, gestor (asignado_a), ruta, desde, hasta.
     */
    public function filtrar(int $tenantId, array $f = []): self
    {
        $this->select('solicitudes.*, personas.nombres, personas.apellidos, personas.cedula, clientes.codigo,
                       pg.nombres AS gestor_nombres, pg.apellidos AS gestor_apellidos, e.carnet AS gestor_carnet')
             ->join('clientes', 'clientes.id = solicitudes.cliente_id')
             ->join('personas', 'personas.id = clientes.persona_id')
             ->join('empleados e', 'e.id = solicitudes.asignado_a', 'left')
             ->join('personas pg', 'pg.id = e.persona_id', 'left')
             ->where('solicitudes.tenant_id', $tenantId);

        if (!empty($f['estado']))  $this->where('solicitudes.estado', $f['estado']);
        if (!empty($f['gestor']))  $this->where('solicitudes.asignado_a', (int) $f['gestor']);
        if (!empty($f['ruta']))    $this->where('solicitudes.ruta', $f['ruta']);
        if (!empty($f['desde']))   $this->where('solicitudes.created_at >=', $f['desde'] . ' 00:00:00');
        if (!empty($f['hasta']))   $this->where('solicitudes.created_at <=', $f['hasta'] . ' 23:59:59');

        if (!empty($f['q'])) {
            $q = trim((string) $f['q']);
            $this->groupStart()
                 ->like('personas.nombres', $q)
                 ->orLike('personas.apellidos', $q)
                 ->orLike('personas.cedula', $q)
                 ->orLike('clientes.codigo', $q)
                 ->groupEnd();
        }

        return $this->orderBy('solicitudes.id', 'DESC');
    }

    /** Solicitud del tenant con cliente + creador + asignado (para el detalle). */
    public function detalle(int $tenantId, int $id): ?array
    {
        return $this->select('solicitudes.*,
                              personas.nombres, personas.apellidos, personas.cedula, personas.telefono, personas.email,
                              clientes.codigo, clientes.persona_id,
                              pg.nombres AS gestor_nombres, pg.apellidos AS gestor_apellidos, eg.carnet AS gestor_carnet,
                              pc.nombres AS creador_nombres, pc.apellidos AS creador_apellidos')
                    ->join('clientes', 'clientes.id = solicitudes.cliente_id')
                    ->join('personas', 'personas.id = clientes.persona_id')
                    ->join('empleados eg', 'eg.id = solicitudes.asignado_a', 'left')
                    ->join('personas pg', 'pg.id = eg.persona_id', 'left')
                    ->join('empleados ec', 'ec.id = solicitudes.empleado_id', 'left')
                    ->join('personas pc', 'pc.id = ec.persona_id', 'left')
                    ->where('solicitudes.tenant_id', $tenantId)
                    ->where('solicitudes.id', $id)
                    ->first();
    }

    /** Solicitudes del tenant con datos del cliente (persona). */
    public function conCliente(int $tenantId, string $estado = ''): array
    {
        $b = $this->select('solicitudes.*, personas.nombres, personas.apellidos, personas.cedula, clientes.codigo')
                  ->join('clientes', 'clientes.id = solicitudes.cliente_id')
                  ->join('personas', 'personas.id = clientes.persona_id')
                  ->where('solicitudes.tenant_id', $tenantId)
                  ->orderBy('solicitudes.id', 'DESC');
        if ($estado !== '') {
            $b->where('solicitudes.estado', $estado);
        }
        return $b->findAll();
    }
}
