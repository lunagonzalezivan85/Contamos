<?php

namespace App\Models;

use CodeIgniter\Model;

class TenantModel extends Model
{
    protected $table            = 'tenants';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = true;
    protected $useTimestamps    = true;

    protected $allowedFields = [
        'nombre', 'slug', 'razon_social', 'email', 'telefono', 'direccion',
        'lema', 'quienes_somos', 'mision', 'vision', 'valores',
        'ruc', 'conami_registro', 'voucher_footer', 'horario', 'hora_inicio', 'hora_fin', 'moneda',
        'tasa_interes', 'mora_diaria_pct', 'pronto_pago_pct', 'plazo_meses_max',
        'tipo_calculo', 'comision_pct', 'seguro_pct',
        'contacto_nombre', 'contacto_cargo', 'logo', 'estado',
        'plan_id', 'dia_pago', 'suscripcion_estado',
    ];

    /**
     * Tenant por defecto (el primero activo). Para login sin selector de empresa.
     */
    public function default(): ?array
    {
        return $this->where('estado', 'ACTIVO')->orderBy('id', 'ASC')->first();
    }

    /**
     * Todos los tenants activos — para el selector de empresa en el login.
     */
    public function activos(): array
    {
        return $this->where('estado', 'ACTIVO')->orderBy('nombre', 'ASC')->findAll();
    }

    public function findBySlug(string $slug): ?array
    {
        return $this->where('slug', $slug)->where('estado', 'ACTIVO')->first();
    }
}
