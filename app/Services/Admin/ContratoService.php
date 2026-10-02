<?php

namespace App\Services\Admin;

use App\Models\AccesoSolicitudModel;
use App\Models\ContratoModel;
use Config\Database;

/**
 * Contratos de servicio SaaS — creación desde un lead CONTACTADO
 * y datos para la vista imprimible (T&C).
 */
class ContratoService
{
    private $db;
    private ContratoModel $contratos;
    private AccesoSolicitudModel $leads;

    public function __construct()
    {
        $this->db        = Database::connect();
        $this->contratos = new ContratoModel();
        $this->leads     = new AccesoSolicitudModel();
    }

    /**
     * Genera el contrato del lead y lo marca CONTRATADO.
     * @return array {id} o {error}
     */
    public function crear(int $leadId, array $d): array
    {
        $lead = $this->leads->find($leadId);
        if (!$lead) return ['error' => 'Lead no encontrado.'];
        if ($lead['estado'] !== AccesoSolicitudModel::CONTACTADO) {
            return ['error' => 'Solo se genera contrato de un lead CONTACTADO — primero prepará la propuesta.'];
        }
        if ($this->porLead($leadId)) {
            return ['error' => 'El lead ya tiene contrato — abrí el existente.'];
        }

        $diaPago = (int) ($d['dia_pago'] ?? 5);
        if (!in_array($diaPago, [5, 10], true)) $diaPago = 5;

        $gracia = max(0, min(30, (int) ($d['gracia_dias'] ?? 4)));
        $monto  = (float) ($d['monto'] ?? $lead['plan_estimado']);
        $inicio = ($d['fecha_inicio'] ?? '') !== '' ? $d['fecha_inicio'] : date('Y-m-d');

        if ($monto <= 0) return ['error' => 'El monto mensual debe ser mayor a cero.'];

        $id = $this->contratos->insert([
            'acceso_solicitud_id' => $leadId,
            'plan_id'             => $lead['plan_id'] ?: null,
            'monto_mensual'       => $monto,
            'dia_pago'            => $diaPago,
            'gracia_dias'         => $gracia,
            'cuenta_bancaria'     => trim((string) ($d['cuenta_bancaria'] ?? '')) ?: null,
            'fecha_inicio'        => $inicio,
        ], true);

        $this->leads->update($leadId, ['estado' => AccesoSolicitudModel::CONTRATADO]);

        return ['id' => (int) $id];
    }

    /** Contrato del lead (si existe). */
    public function porLead(int $leadId): ?array
    {
        return $this->contratos->where('acceso_solicitud_id', $leadId)->first() ?: null;
    }

    /** Mapa lead_id → contrato_id para marcar la fila en /admin/leads. */
    public function mapaPorLead(): array
    {
        $mapa = [];
        foreach ($this->contratos->select('id, acceso_solicitud_id')->findAll() as $c) {
            $mapa[(int) $c['acceso_solicitud_id']] = (int) $c['id'];
        }
        return $mapa;
    }

    /** Contrato + lead + plan para la vista imprimible. */
    public function detalle(int $id): ?array
    {
        return $this->db->table('contratos c')
            ->select('c.*, l.nombre, l.negocio, l.telefono, l.correo, l.codigo,
                      pl.nombre AS plan, pl.moneda')
            ->join('acceso_solicitudes l', 'l.id = c.acceso_solicitud_id', 'left')
            ->join('planes pl', 'pl.id = c.plan_id', 'left')
            ->where('c.id', $id)->get()->getRowArray() ?: null;
    }
}
