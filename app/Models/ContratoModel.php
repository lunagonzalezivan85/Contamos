<?php

namespace App\Models;

use CodeIgniter\Model;

/**
 * Contratos de servicio SaaS — se generan desde un lead CONTACTADO
 * (/admin/leads → «Generar contrato») y guardan las condiciones pactadas:
 * día de pago (5|10), periodo de gracia, cuenta bancaria y mensualidad.
 * tenant_id se llena cuando el tenant se provisiona.
 */
class ContratoModel extends Model
{
    const ACTIVO    = 'ACTIVO';
    const SUSPENDIDO = 'SUSPENDIDO';
    const CANCELADO = 'CANCELADO';

    protected $table         = 'contratos';
    protected $primaryKey    = 'id';
    protected $returnType    = 'array';
    protected $allowedFields = ['acceso_solicitud_id', 'tenant_id', 'plan_id', 'monto_mensual',
                                'dia_pago', 'gracia_dias', 'cuenta_bancaria', 'terminos',
                                'estado', 'fecha_inicio', 'fecha_corte'];

    protected $useTimestamps = true;
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';
}
