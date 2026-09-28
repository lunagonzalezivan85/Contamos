<?php

namespace App\Services\Partner;

use App\Models\PlantillaModel;
use App\Models\TenantModel;
use CodeIgniter\HTTP\Files\UploadedFile;

/**
 * Configuración del tenant — datos de la empresa, logo y plantillas.
 */
class ConfiguracionService
{
    private TenantModel     $tenants;
    private PlantillaModel  $plantillas;

    public function __construct()
    {
        $this->tenants    = new TenantModel();
        $this->plantillas = new PlantillaModel();
    }

    public function tenant(int $tenantId): ?array
    {
        return $this->tenants->find($tenantId);
    }

    public function plantillasDe(int $tenantId): array
    {
        return $this->plantillas->porTenant($tenantId);
    }

    public function plantilla(int $tenantId, int $id): ?array
    {
        return $this->plantillas->deTenant($tenantId, $id);
    }

    /**
     * Guarda los datos de la empresa (+ logo si se subió).
     * @return array{ok: bool, nombre: string, error?: string}
     */
    public function guardarDatos(int $tenantId, array $d, ?UploadedFile $logo): array
    {
        $data = [
            'nombre'          => trim((string) $d['nombre']),
            'razon_social'    => trim((string) ($d['razon_social'] ?? '')),
            'ruc'             => trim((string) ($d['ruc'] ?? '')),
            'conami_registro' => trim((string) ($d['conami_registro'] ?? '')),
            'email'           => trim((string) ($d['email'] ?? '')),
            'telefono'        => trim((string) ($d['telefono'] ?? '')),
            'direccion'       => trim((string) ($d['direccion'] ?? '')),
            'lema'            => trim((string) ($d['lema'] ?? '')),
            'quienes_somos'   => trim((string) ($d['quienes_somos'] ?? '')),
            'mision'          => trim((string) ($d['mision'] ?? '')),
            'vision'          => trim((string) ($d['vision'] ?? '')),
            'valores'         => trim((string) ($d['valores'] ?? '')),
            'voucher_footer'  => trim((string) ($d['voucher_footer'] ?? '')),
            'horario'         => trim((string) ($d['horario'] ?? '')),
            'hora_inicio'     => substr((string) ($d['hora_inicio'] ?? ''), 0, 5) ?: null,
            'hora_fin'        => substr((string) ($d['hora_fin'] ?? ''), 0, 5) ?: null,
            'moneda'          => trim((string) ($d['moneda'] ?? '')) ?: 'C$',
            'tasa_interes'    => ($d['tasa_interes'] ?? '') !== '' ? (float) $d['tasa_interes'] : 3.00,
            'mora_diaria_pct' => ($d['mora_diaria_pct'] ?? '') !== '' ? (float) $d['mora_diaria_pct'] : 0,
            'pronto_pago_pct' => ($d['pronto_pago_pct'] ?? '') !== '' ? (float) $d['pronto_pago_pct'] : 0,
            'plazo_meses_max' => (int) ($d['plazo_meses_max'] ?? 0) > 0 ? (int) $d['plazo_meses_max'] : 24,
            'tipo_calculo'    => in_array($d['tipo_calculo'] ?? '', array_keys(\App\Services\Partner\SolicitudService::TIPOS_CALCULO), true)
                ? $d['tipo_calculo'] : 'FLAT',
            'comision_pct'    => ($d['comision_pct'] ?? '') !== '' ? (float) $d['comision_pct'] : 0,
            'seguro_pct'      => ($d['seguro_pct'] ?? '') !== '' ? (float) $d['seguro_pct'] : 0,
            'contacto_nombre' => trim((string) ($d['contacto_nombre'] ?? '')),
            'contacto_cargo'  => trim((string) ($d['contacto_cargo'] ?? '')),
        ];

        // Logo — subida de imagen a public/uploads/logos
        if ($logo && $logo->isValid() && !$logo->hasMoved()) {
            if (!in_array($logo->getMimeType(), ['image/png', 'image/jpeg', 'image/webp', 'image/gif'], true)) {
                return ['ok' => false, 'nombre' => $data['nombre'], 'error' => 'El logo debe ser una imagen (PNG, JPG, WEBP o GIF).'];
            }
            if ($logo->getSize() > 2 * 1024 * 1024) {
                return ['ok' => false, 'nombre' => $data['nombre'], 'error' => 'El logo no debe superar 2 MB.'];
            }
            $dir = FCPATH . 'uploads' . DIRECTORY_SEPARATOR . 'logos';
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            $nombre = 't' . $tenantId . '_' . $logo->getRandomName();
            $logo->move($dir, $nombre);
            $data['logo'] = $nombre;
        }

        $this->tenants->update($tenantId, $data);

        return ['ok' => true, 'nombre' => $data['nombre']];
    }

    public function guardarPlantilla(int $id, string $contenido): void
    {
        $this->plantillas->update($id, ['contenido' => $contenido]);
    }
}
