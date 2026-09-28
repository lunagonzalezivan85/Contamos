<?php

namespace App\Controllers\Admin;

use App\Controllers\BaseController;
use Config\Database;

/**
 * Auditoría global — bitácora `audit_logs` de todos los tenants.
 * Filtros: tenant, módulo, búsqueda en acción/entidad, rango de fechas.
 */
class AuditoriaController extends BaseController
{
    /** GET /admin/auditoria */
    public function index()
    {
        $db      = Database::connect();
        $tenantF = (int) $this->request->getGet('tenant');
        $modulo  = trim((string) $this->request->getGet('modulo'));
        $buscar  = trim((string) $this->request->getGet('q'));
        $desde   = (string) $this->request->getGet('desde');
        $hasta   = (string) $this->request->getGet('hasta');

        $b = $db->table('audit_logs a')
            ->select('a.*, t.nombre AS tenant, u.username, u.nombre AS usuario')
            ->join('tenants t', 't.id = a.tenant_id', 'left')
            ->join('users u', 'u.id = a.user_id', 'left');
        if ($tenantF > 0)      $b->where('a.tenant_id', $tenantF);
        if ($modulo !== '')    $b->where('a.modulo', $modulo);
        if ($buscar !== '')    $b->groupStart()->like('a.accion', $buscar)->orLike('a.entidad', $buscar)->groupEnd();
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $b->where('a.created_at >=', $desde . ' 00:00:00');
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $b->where('a.created_at <=', $hasta . ' 23:59:59');

        $logs = $b->orderBy('a.id', 'DESC')->limit(300)->get()->getResultArray();

        return view('admin/auditoria/index', [
            'title'   => 'Auditoría — Admin',
            'logs'    => $logs,
            'tenants' => $db->table('tenants')->select('id, nombre')->orderBy('nombre')->get()->getResultArray(),
            'modulos' => array_column($db->table('audit_logs')->select('modulo')->distinct()->orderBy('modulo')->get()->getResultArray(), 'modulo'),
            'f'       => compact('tenantF', 'modulo', 'buscar', 'desde', 'hasta'),
        ]);
    }
}
