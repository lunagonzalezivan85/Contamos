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
