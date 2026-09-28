<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;

/**
 * Pantalla de bloqueo por suscripción — se muestra cuando el plan del
 * tenant está vencido/suspendido (filtro 'suscripcion'). Permite
 * reportar el pago: queda PENDIENTE hasta que el equipo lo confirme.
 */
class SuscripcionController extends BaseController
{
    /** GET /cuenta-suspendida */
    public function index()
    {
        $estado = plan_al_dia();
        if ($estado['ok']) {
            return redirect()->to('/dashboard');
        }
        return view('partner/suspendida', [
            'title'  => 'Suscripción vencida',
            'estado' => $estado,
            'plan'   => plan_actual(),
            'tenant' => session('tenant_name') ?? 'su empresa',
            'hoy'    => date('Y-m-d'),
        ]);
    }

    /** POST /cuenta-suspendida/reportar — el tenant reporta su pago del período */
    public function reportarPago()
    {
        $tenantId = (int) session('tenant_id');
        $plan     = plan_actual($tenantId);
        if (!$plan) {
            return redirect()->back()->with('error', 'No tiene un plan contratado.');
        }

        $periodo = date('Y-m');
        $datos   = [
            'tenant_id'      => $tenantId,
            'plan_id'        => (int) $plan['id'],
            'periodo'        => $periodo,
            'monto'          => (float) $plan['precio_mensual'],
            'moneda'         => $plan['moneda'] ?? 'USD',
            'metodo'         => trim((string) $this->request->getPost('metodo')) ?: 'TRANSFERENCIA',
            'referencia'     => trim((string) $this->request->getPost('referencia')) ?: null,
            'fecha_pago'     => date('Y-m-d'),
            'estado'         => 'PENDIENTE',
            'observacion'    => 'Reportado por el tenant — pendiente de confirmar.',
            'updated_at'     => date('Y-m-d H:i:s'),
        ];

        $db  = db_connect();
        $exi = $db->table('plan_pagos')->where('tenant_id', $tenantId)->where('periodo', $periodo)->get()->getRowArray();
        if ($exi) {
            if ($exi['estado'] === 'PAGADO') {
                return redirect()->back()->with('error', 'El pago de este período ya fue confirmado.');
            }
            $db->table('plan_pagos')->where('id', $exi['id'])->update($datos);
        } else {
            $db->table('plan_pagos')->insert($datos + ['created_at' => date('Y-m-d H:i:s')]);
        }

        return redirect()->to('/cuenta-suspendida')
            ->with('success', 'Pago reportado. Lo validaremos y reactivaremos su cuenta.');
    }
}
