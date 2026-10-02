<?php

namespace App\Services\Shared;

use App\Models\AccesoSolicitudModel;

/**
 * Landing pública — solicitudes de acceso ("Solicita tu usuario").
 */
class LandingService
{
    private AccesoSolicitudModel $solicitudes;

    public function __construct()
    {
        $this->solicitudes = new AccesoSolicitudModel();
    }

    /** Registra la solicitud de acceso enviada desde la landing. */
    public function solicitarAcceso(array $d): void
    {
        $this->solicitudes->insert([
            'nombre'   => trim((string) $d['nombre']),
            'negocio'  => trim((string) $d['negocio']),
            'telefono' => trim((string) $d['telefono']),
            'correo'   => trim((string) ($d['correo'] ?? '')) ?: null,
        ]);
    }

    /**
     * Alta con calculadora de plan — guarda el lead + el plan estimado que
     * armó con los sliders, y devuelve su código partner (PTR-######).
     */
    public function registrarAlta(array $d): string
    {
        $precio = $this->precioEstimado(
            (int) $d['usuarios'], (int) $d['clientes'],
            (int) $d['creditos'], (int) $d['empleados']
        );
        $codigo = 'PTR-' . random_int(100000, 999999);

        $this->solicitudes->insert([
            'nombre'        => trim((string) $d['nombre']),
            'negocio'       => trim((string) $d['nombre']),   // el form pide "Nombre o Empresa"
            'telefono'      => trim((string) $d['telefono']),
            'codigo'        => $codigo,
            'plan_estimado' => $precio,
            'detalle'       => json_encode([
                'usuarios'  => (int) $d['usuarios'],
                'clientes'  => (int) $d['clientes'],
                'creditos'  => (int) $d['creditos'],
                'empleados' => (int) $d['empleados'],
                'mensual'   => $precio,
            ]),
        ]);

        return $codigo;
    }

    /** Catálogo de planes activos — select del modal Contactar en /admin/leads. */
    public function planes(): array
    {
        return db_connect()->table('planes')
            ->where('estado', 'ACTIVO')->orderBy('orden')->get()->getResultArray();
    }

    /** Plan base del cálculo — por id (modal) o el Básico por defecto (landing). */
    public function planBase(?int $planId = null): array
    {
        $q = db_connect()->table('planes')->where('estado', 'ACTIVO');
        $q = $planId ? $q->where('id', $planId) : $q->where('slug', 'basico');

        return $q->get()->getRowArray()
            ?: ['precio_mensual' => 19, 'max_usuarios' => 1, 'max_empleados' => 5, 'max_creditos_activos' => 50];
    }

    /**
     * Mismo cálculo que alta.js — el precio del cliente es solo referencia;
     * el server recalcula para que el lead guarde un monto confiable.
     * Base = plan elegido (modal Contactar) o Básico (landing /alta).
     */
    public function precioEstimado(int $usuarios, int $clientes, int $creditos, int $empleados, ?int $planId = null): float
    {
        $base = $this->planBase($planId);

        // Tarifas de sobreconsumo = mismas constantes que plan_cobro_mes
        return round(
            (float) $base['precio_mensual']
            + max(0, $usuarios  - (int) $base['max_usuarios'])         * PLAN_USD_EXTRA_USUARIO
            + max(0, $clientes  - PLAN_BASE_CLIENTES)                  * PLAN_USD_EXTRA_CLIENTE
            + max(0, $creditos  - (int) $base['max_creditos_activos']) * PLAN_USD_EXTRA_CREDITO
            + max(0, $empleados - (int) $base['max_empleados'])        * PLAN_USD_EXTRA_EMPLEADO,
            2
        );
    }
}
