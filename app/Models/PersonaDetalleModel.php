<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Modelo genérico para las tablas hijas de persona
 * (direcciones, contactos, referencias, negocios, activos, pasivos, ingresos, documentos).
 */
class PersonaDetalleModel extends Model
{
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useTimestamps    = true;
    protected $createdField     = 'created_at';
    protected $updatedField     = '';          // las hijas solo tienen created_at

    /** Mapa tipo → tabla + campos permitidos. */
    private const TIPOS = [
        'direccion'   => ['tabla' => 'persona_direcciones',  'campos' => ['tipo','departamento','ciudad','barrio','detalle','latitud','longitud']],
        'contacto'    => ['tabla' => 'persona_contactos',    'campos' => ['tipo','valor']],
        'referencia'  => ['tabla' => 'persona_referencias',  'campos' => ['nombre','parentesco','telefono','direccion']],
        'negocio'     => ['tabla' => 'persona_negocios',     'campos' => ['nombre','actividad','sector_economico','direccion','tiempo','dias_venta','promedio_venta_dia']],
        'activo'      => ['tabla' => 'persona_activos',      'campos' => ['descripcion','valor']],
        'pasivo'      => ['tabla' => 'persona_pasivos',      'campos' => ['descripcion','acreedor','monto']],
        'ingreso'     => ['tabla' => 'persona_ingresos',     'campos' => ['fuente','monto']],
        'egreso'      => ['tabla' => 'persona_egresos',      'campos' => ['descripcion','monto']],
        'documento'   => ['tabla' => 'persona_documentos',   'campos' => ['tipo','descripcion','archivo']],
    ];

    public function __construct(?string $tipo = null)
    {
        parent::__construct();
        if ($tipo !== null) {
            $this->para($tipo);
        }
    }

    /** Configura el modelo para un tipo de detalle. */
    public function para(string $tipo): self
    {
        if (!isset(self::TIPOS[$tipo])) {
            throw new \InvalidArgumentException("Tipo de detalle desconocido: {$tipo}");
        }
        $this->table         = self::TIPOS[$tipo]['tabla'];
        $this->primaryKey    = 'id';
        $this->allowedFields = array_merge(['persona_id'], self::TIPOS[$tipo]['campos']);
        return $this;
    }

    public function tipos(): array
    {
        return array_keys(self::TIPOS);
    }

    /** Campos de un tipo (para saber qué inputs pedir). */
    public function campos(string $tipo): array
    {
        return self::TIPOS[$tipo]['campos'] ?? [];
    }

    /** Items de una persona. */
    public function dePersona(int $personaId): array
    {
        return $this->where('persona_id', $personaId)->orderBy('id', 'DESC')->findAll();
    }

    /**
     * ¿La persona ya tiene una fila idéntica a $fila? — anti-duplicado
     * server-side para agregarDato (doble-submit / retry del form).
     * Se ignoran 'archivo' (nombre random), 'id' y 'created_at'; los
     * demás campos deben coincidir (null ≡ '' y numéricos por valor).
     */
    public function existeIgual(array $fila): bool
    {
        $items = $this->where('persona_id', (int) ($fila['persona_id'] ?? 0))->findAll();
        foreach ($items as $it) {
            $igual = true;
            foreach ($fila as $campo => $val) {
                if (in_array($campo, ['id', 'created_at', 'archivo'], true)) continue;
                $a = $it[$campo] ?? null;
                if (($a === null || $a === '') && ($val === null || $val === '')) continue;
                if (is_numeric($a) && is_numeric($val)) {
                    if ((float) $a === (float) $val) continue;
                    $igual = false;
                    break;
                }
                if ((string) $a !== (string) $val) { $igual = false; break; }
            }
            if ($igual) return true;
        }
        return false;
    }
}
