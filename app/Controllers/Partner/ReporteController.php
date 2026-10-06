<?php

namespace App\Controllers\Partner;

use App\Controllers\BaseController;
use App\Models\SolicitudModel;
use App\Services\Partner\ReporteService;
use App\Services\Partner\PagoService;

/**
 * Reportes del tenant (permiso reportes.ver):
 * - /credito/reporte   pipeline de solicitudes, desembolsos y salud de cartera
 * - /finanzas/reporte  cobros + otros ingresos − gastos del periodo
 */
class ReporteController extends BaseController
{
    private ReporteService $svc;

    public function __construct()
    {
        $this->svc = new ReporteService();
    }

    /**
     * GET /reportes — índice con cards agrupadas por categoría
     * (personalizada en Configuración → Reportes o la default del catálogo).
     */
    public function index()
    {
        $tenantId = (int) session('tenant_id');
        return view('partner/reportes/index', [
            'title'  => 'Reportes - Contamos',
            'grupos' => $this->svc->catalogoAgrupado($tenantId, session('permisos') ?? []),
        ]);
    }

    /** GET /credito/reporte */
    public function creditos()
    {
        $tenantId = (int) session('tenant_id');
        [$desde, $hasta] = $this->rango();

        return view('partner/reportes/creditos', [
            'title'    => 'Reporte de créditos - Contamos',
            'data'     => $this->svc->creditos($tenantId, $desde, $hasta),
            'desde'    => $desde,
            'hasta'    => $hasta,
            'lblEstado'=> SolicitudModel::LABEL_ESTADO,
            'mon'      => (new PagoService())->moneda($tenantId),
        ]);
    }

    /** GET /finanzas/reporte */
    public function finanzas()
    {
        $tenantId = (int) session('tenant_id');
        [$desde, $hasta] = $this->rango();

        return view('partner/reportes/finanzas', [
            'title' => 'Reporte financiero - Contamos',
            'data'  => $this->svc->finanzas($tenantId, $desde, $hasta),
            'desde' => $desde,
            'hasta' => $hasta,
            'mon'   => (new PagoService())->moneda($tenantId),
        ]);
    }

    /**
     * GET /finanzas/flujo?desde=YYYY-MM-DD&hasta=YYYY-MM-DD
     * Comparativo ingresos vs egresos: pagos registrados + otros ingresos
     * contra desembolsos entregados + gastos → resultado del periodo.
     * ?exportar=excel descarga CSV (se abre directo en Excel).
     */
    public function flujo()
    {
        $tenantId = (int) session('tenant_id');
        [$desde, $hasta] = $this->rango();
        $data = $this->svc->flujo($tenantId, $desde, $hasta);
        $mon  = (new PagoService())->moneda($tenantId);

        if ($this->request->getGet('exportar') === 'excel') {
            return $this->flujoCsv($data, $desde, $hasta, $mon);
        }

        return view('partner/reportes/flujo', [
            'title' => 'Ingresos vs egresos - Contamos',
            'data'  => $data,
            'desde' => $desde,
            'hasta' => $hasta,
            'mon'   => $mon,
        ]);
    }

    /** CSV del comparativo ingresos vs egresos (BOM UTF-8 + ; para Excel). */
    private function flujoCsv(array $d, string $desde, string $hasta, string $mon)
    {
        $q = fn ($s) => '"' . str_replace('"', '""', (string) $s) . '"';
        $n = fn ($v) => number_format((float) $v, 2, '.', '');
        $lbl = \App\Models\IngresoModel::TIPOS;

        $out  = "\xEF\xBB\xBF";
        $out .= "Flujo de caja — {$desde} a {$hasta} ({$mon})\r\n\r\n";
        $out .= "RESUMEN\r\nConcepto;Registros;Monto\r\n";
        $out .= "Pagos registrados;" . count($d['pagos']) . ';' . $n($d['t_pagos']) . "\r\n";
        $out .= "Otros ingresos;" . count($d['ingresos']) . ';' . $n($d['t_ingresos']) . "\r\n";
        $out .= "Total ingresos;;" . $n($d['tot_ing']) . "\r\n";
        $out .= "Desembolsos entregados;" . count($d['desembolsos']) . ';' . $n($d['t_desembolsos']) . "\r\n";
        $out .= "Gastos;" . count($d['gastos']) . ';' . $n($d['t_gastos']) . "\r\n";
        $out .= "Total egresos;;" . $n($d['tot_egr']) . "\r\n";
        $out .= "RESULTADO;;" . $n($d['neto']) . ' ' . ($d['neto'] >= 0 ? 'POSITIVO' : 'NEGATIVO') . "\r\n\r\n";

        $out .= "PAGOS REGISTRADOS\r\nFecha;Cliente;Crédito;Método;Estado;Monto\r\n";
        foreach ($d['pagos'] as $p) {
            $out .= implode(';', [
                date('d/m/Y', strtotime($p['fecha_hora'])),
                $q(trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''))),
                $q($p['codigo_credito'] ?? ''), $p['metodo'], $p['estado'], $n($p['monto']),
            ]) . "\r\n";
        }
        $out .= "\r\nOTROS INGRESOS\r\nFecha;Tipo;Concepto;Método;Monto\r\n";
        foreach ($d['ingresos'] as $x) {
            $out .= implode(';', [
                date('d/m/Y', strtotime($x['fecha'])),
                $lbl[$x['tipo']] ?? $x['tipo'], $q($x['concepto']), $x['metodo'], $n($x['monto']),
            ]) . "\r\n";
        }
        $out .= "\r\nDESEMBOLSOS ENTREGADOS\r\nFecha;Cliente;Crédito;Monto\r\n";
        foreach ($d['desembolsos'] as $x) {
            $out .= implode(';', [
                date('d/m/Y', strtotime($x['fecha_entrega'])),
                $q(trim(($x['nombres'] ?? '') . ' ' . ($x['apellidos'] ?? ''))),
                $q($x['codigo_credito'] ?: '#' . $x['id']),
                $n($x['monto_aprobado'] ?: $x['monto']),
            ]) . "\r\n";
        }
        $out .= "\r\nGASTOS\r\nFecha;Categoría;Concepto;Método;Monto\r\n";
        foreach ($d['gastos'] as $x) {
            $out .= implode(';', [
                date('d/m/Y', strtotime($x['fecha'])),
                $q($x['categoria'] ?? 'Sin categoría'), $q($x['concepto']), $x['metodo'], $n($x['monto']),
            ]) . "\r\n";
        }

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="flujo-' . $desde . '_' . $hasta . '.csv"')
            ->setBody($out);
    }

    /**
     * GET /credito/reporte-conami?corte=YYYY-MM-DD
     * Clasificación de cartera por riesgo según norma CONAMI + provisión.
     * ?exportar=excel descarga CSV (se abre directo en Excel).
     */
    public function conami()
    {
        $tenantId = (int) session('tenant_id');
        $corte    = (string) $this->request->getGet('corte');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $corte) || $corte > date('Y-m-d')) {
            $corte = date('Y-m-d');
        }
        $mon  = (new PagoService())->moneda($tenantId);
        $data = $this->svc->carteraConami($tenantId, $corte);

        if ($this->request->getGet('exportar') === 'excel') {
            return $this->conamiCsv($data, $corte, $mon);
        }

        return view('partner/reportes/conami', [
            'title' => 'Cartera por riesgo (CONAMI) - Contamos',
            'data'  => $data,
            'corte' => $corte,
            'tabla' => ReporteService::CONAMI,
            'mon'   => $mon,
        ]);
    }

    /** CSV del reporte CONAMI (BOM UTF-8 + ; para Excel en español). */
    private function conamiCsv(array $d, string $corte, string $mon)
    {
        $q = fn ($s) => '"' . str_replace('"', '""', (string) $s) . '"';
        $n = fn ($v) => number_format((float) $v, 2, '.', '');

        $out  = "\xEF\xBB\xBF";
        $out .= "Clasificación de cartera por riesgo (CONAMI) al {$corte}\r\n";
        $out .= 'Folio;Cliente;Cédula;Gestor;Ruta;Desembolso;Monto;Saldo capital;Saldo vencido;Días atraso;Categoría;% Prov.;Provisión' . "\r\n";
        foreach ($d['rows'] as $r) {
            $out .= implode(';', [
                $q($r['folio']), $q($r['cliente']), $q($r['cedula']), $q($r['gestor']),
                $q($r['ruta']), $r['desembolso'], $n($r['monto']), $n($r['saldo']),
                $n($r['vencido']), $r['dias'], $r['categoria'], $r['pct'], $n($r['provision']),
            ]) . "\r\n";
        }
        $t = $d['totales'];
        $out .= implode(';', ['TOTAL', '', '', '', '', '', $n($t['saldo']), $n($t['saldo']),
            $n($t['vencido']), '', '', '', $n($t['provision'])]) . "\r\n";

        return $this->response
            ->setHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->setHeader('Content-Disposition', 'attachment; filename="cartera-conami-' . $corte . '.csv"')
            ->setBody($out);
    }

    /** Rango de fechas del filtro; por defecto el mes en curso. */
    private function rango(): array
    {
        $desde = (string) $this->request->getGet('desde');
        $hasta = (string) $this->request->getGet('hasta');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $desde)) $desde = date('Y-m-01');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $hasta)) $hasta = date('Y-m-t');
        if ($desde > $hasta) [$desde, $hasta] = [$hasta, $desde];
        return [$desde, $hasta];
    }
}
