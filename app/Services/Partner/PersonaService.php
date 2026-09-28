<?php

namespace App\Services\Partner;

use App\Models\PersonaDetalleModel;
use App\Models\PersonaModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Lógica de negocio compartida de personas (base de clientes y empleados):
 * datos de identidad + expediente por pestañas + subida de documentos.
 */
class PersonaService
{
    private PersonaModel $personas;

    public function __construct()
    {
        $this->personas = new PersonaModel();
    }

    // ---------------------------------------------------------------
    // Persona
    // ---------------------------------------------------------------

    public function crear(int $tenantId, string $tipo, array $d): int
    {
        return (int) $this->personas->insert($this->datos($tenantId, $tipo, $d));
    }

    /** Actualiza identidad (sin tocar tenant_id ni tipo). */
    public function actualizar(int $personaId, string $tipo, array $d): void
    {
        $fila = $this->datos(0, $tipo, $d);
        unset($fila['tenant_id'], $fila['tipo']);
        $this->personas->update($personaId, $fila);
    }

    /** Todas las secciones del expediente agrupadas por tipo. */
    public function seccionesDe(int $personaId): array
    {
        $secciones = [];
        foreach ((new PersonaDetalleModel())->tipos() as $tipo) {
            // Instancia nueva por tipo: el builder se reutilizaba y todas las
            // secciones consultaban la misma tabla.
            $secciones[$tipo] = (new PersonaDetalleModel($tipo))->dePersona($personaId);
        }
        return $secciones;
    }

    // ---------------------------------------------------------------
    // Expediente (persona_* por tipo)
    // ---------------------------------------------------------------

    /**
     * Agrega un item a una pestaña del expediente (con archivo si es documento).
     * @return array{ok: bool, error?: string}
     */
    public function agregarDato(int $personaId, string $tipo, array $d, ?UploadedFile $file): array
    {
        $detalle = $this->detalle($tipo);
        if (!$detalle) {
            return ['ok' => false, 'error' => 'Tipo de dato inválido.'];
        }

        $fila = ['persona_id' => $personaId] + $this->filaDetalle($tipo, $d);

        $archivo = $this->guardarArchivo($tipo, $personaId, $file);
        if ($archivo === false) {
            return ['ok' => false, 'error' => 'El documento no debe superar 5 MB.'];
        }
        if ($archivo !== null) {
            $fila['archivo'] = $archivo;
        }

        $detalle->insert($fila);
        return ['ok' => true];
    }

    /**
     * Edita un item del expediente; en documentos reemplaza el archivo
     * solo si se subió uno nuevo.
     * @return array{ok: bool, error?: string}
     */
    public function actualizarDato(int $personaId, string $tipo, int $item, array $d, ?UploadedFile $file): array
    {
        $detalle = $this->detalle($tipo);
        if (!$detalle) {
            return ['ok' => false, 'error' => 'Tipo de dato inválido.'];
        }

        $fila = $this->filaDetalle($tipo, $d);

        $archivo = $this->guardarArchivo($tipo, $personaId, $file);
        if ($archivo === false) {
            return ['ok' => false, 'error' => 'El documento no debe superar 5 MB.'];
        }
        if ($archivo !== null) {
            $fila['archivo'] = $archivo;
        }

        $detalle->where('persona_id', $personaId)->update($item, $fila);
        return ['ok' => true];
    }

    /** Borra un item del expediente (acotado a la persona). */
    public function eliminarDato(int $personaId, string $tipo, int $item): void
    {
        $detalle = $this->detalle($tipo);
        if ($detalle) {
            $detalle->where('persona_id', $personaId)->delete($item);
        }
    }

    // ------------------------------------------------------------------

    private function detalle(string $tipo): ?PersonaDetalleModel
    {
        $detalle = new PersonaDetalleModel();
        try {
            $detalle->para($tipo);
        } catch (\InvalidArgumentException $e) {
            return null;
        }
        return $detalle;
    }

    /** Campos del tipo mapeados desde el POST (dias_venta es checkbox-array). */
    private function filaDetalle(string $tipo, array $d): array
    {
        $fila = [];
        foreach ((new PersonaDetalleModel($tipo))->campos($tipo) as $campo) {
            if ($tipo === 'documento' && $campo === 'archivo') continue; // archivo = file, no texto
            if ($campo === 'dias_venta') { // checkboxes → "L,X,V"
                $dias = (array) ($d['dias_venta'] ?? []);
                $fila['dias_venta'] = $dias ? implode(',', $dias) : null;
                continue;
            }
            $fila[$campo] = trim((string) ($d[$campo] ?? '')) ?: null;
        }
        return $fila;
    }

    /** Nombre del archivo guardado; null si no se subió; false si supera 5 MB. */
    private function guardarArchivo(string $tipo, int $personaId, ?UploadedFile $file)
    {
        if ($tipo !== 'documento' || !$file || !$file->isValid() || $file->hasMoved()) {
            return null;
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return false;
        }
        $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'documentos';
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $nombre = 'p' . $personaId . '_' . $file->getRandomName();
        $file->move($dir, $nombre);
        return $nombre;
    }

    private function datos(int $tenantId, string $tipo, array $d): array
    {
        return [
            'tenant_id' => $tenantId,
            'tipo'      => $tipo,
            'nombres'   => trim((string) ($d['nombres'] ?? '')),
            'apellidos' => trim((string) ($d['apellidos'] ?? '')),
            'genero'    => in_array($d['genero'] ?? '', ['M', 'F'], true) ? $d['genero'] : null,
            'cedula'    => trim((string) ($d['cedula'] ?? '')) ?: null,
            'telefono'  => trim((string) ($d['telefono'] ?? '')) ?: null,
            'email'     => trim((string) ($d['email'] ?? '')) ?: null,
            'direccion' => trim((string) ($d['direccion'] ?? '')) ?: null,
            'fecha_nac' => ($d['fecha_nac'] ?? '') ?: null,
        ];
    }
}
