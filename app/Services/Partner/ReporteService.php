<?php

namespace App\Services\Partner;

use Config\Database;

/**
 * Reportes del tenant: pipeline de crédito y resultado financiero.
 * Solo lectura — consultas agregadas sobre solicitudes, cuotas, pagos,
 * ingresos y gastos.
 */
class ReporteService
{
    private $db;

    public function __construct()
    {
        $this->db = Database::connect();
    }

    /**
     * Reporte de crédito para /credito/reporte.
     * $desde/$hasta (Y-m-d) filtran solicitudes por created_at y
     * desembolsos por fecha_desembolso.
     */
    public function creditos(int $tenantId, string $desde, string $hasta): array
    {
        // Pipeline actual — conteo y monto por estado
        $porEstado = $this->db->table('solicitudes')
            ->select('estado, COUNT(*) AS n, COALESCE(SUM(monto),0) AS solicitado,
                      COALESCE(SUM(monto_aprobado),0) AS aprobado', false)
            ->where('tenant_id', $tenantId)
            ->groupBy('estado')
            ->orderBy('n', 'DESC')
            ->get()->getResultArray();

        // Solicitudes creadas en el rango
        $nuevas = $this->db->table('solicitudes')
            ->select('COUNT(*) AS n, COALESCE(SUM(monto),0) AS monto', false)
            ->where('tenant_id', $tenantId)
            ->where('DATE(created_at) >=', $desde)->where('DATE(created_at) <=', $hasta)
            ->get()->getRowArray();

        // Desembolsos en el rango (créditos activados/liquidados por refinan.)
        $desembolsos = $this->db->table('solicitudes')
            ->select('COUNT(*) AS n, COALESCE(SUM(monto_aprobado),0) AS monto', false)
            ->where('tenant_id', $tenantId)
            ->whereIn('estado', ['ACTIVO', 'LIQUIDADO'])
            ->where('fecha_desembolso >=', $desde)->where('fecha_desembolso <=', $hasta)
            ->get()->getRowArray();

        // Por gestor: solicitudes y desembolsos en el rango
        $porGestor = $this->db->table('solicitudes s')
            ->select('p.nombres, p.apellidos,
                      COUNT(*) AS solicitudes,
                      COALESCE(SUM(s.monto),0) AS solicitado,
                      SUM(CASE WHEN s.estado IN (\'ACTIVO\',\'LIQUIDADO\') THEN 1 ELSE 0 END) AS desembolsados,
                      COALESCE(SUM(CASE WHEN s.estado IN (\'ACTIVO\',\'LIQUIDADO\') THEN s.monto_aprobado ELSE 0 END),0) AS colocado', false)
            ->join('empleados e', 'e.id = s.empleado_id', 'left')
            ->join('personas p', 'p.id = e.persona_id', 'left')
            ->where('s.tenant_id', $tenantId)
            ->where('DATE(s.created_at) >=', $desde)->where('DATE(s.created_at) <=', $hasta)
            ->groupBy('s.empleado_id')
            ->orderBy('colocado', 'DESC')
            ->get()->getResultArray();

        // Salud de cartera: activos y en mora (a hoy, independiente del rango)
        $activos = $this->db->table('solicitudes')
            ->where('tenant_id', $tenantId)->where('estado', 'ACTIVO')
            ->countAllResults();
        $enMora = $this->db->table('cuotas q')
            ->select('q.solicitud_id')
            ->join('solicitudes s', 's.id = q.solicitud_id')
            ->where('q.tenant_id', $tenantId)
            ->where('s.estado', 'ACTIVO')
            ->where('q.pagado < q.cuota', null, false)
            ->where('q.fecha_vence <', date('Y-m-d'))
            ->groupBy('q.solicitud_id')
            ->countAllResults();

        return [
            'por_estado'  => $porEstado,
            'nuevas'      => $nuevas,
            'desembolsos' => $desembolsos,
            'por_gestor'  => $porGestor,
            'cartera'     => ['activos' => $activos, 'en_mora' => $enMora],
        ];
    }

    /**
     * Reporte financiero para /finanzas/reporte.
     * Cobros aplicados + otros ingresos − gastos, en el rango.
     */
    public function finanzas(int $tenantId, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';

        // Cobros de crédito aplicados
        $cobros = $this->db->table('pagos')
            ->select('COUNT(*) AS n, COALESCE(SUM(monto),0) AS monto', false)
            ->where('tenant_id', $tenantId)
            ->where('tipo', 'PAGO')->where('estado', 'APLICADO')
            ->where('fecha_hora >=', $ini)->where('fecha_hora <=', $fin)
            ->get()->getRowArray();

        // Cobros por método
        $porMetodo = $this->db->table('pagos')
            ->select('metodo, COUNT(*) AS n, COALESCE(SUM(monto),0) AS monto', false)
            ->where('tenant_id', $tenantId)
            ->where('tipo', 'PAGO')->where('estado', 'APLICADO')
            ->where('fecha_hora >=', $ini)->where('fecha_hora <=', $fin)
            ->groupBy('metodo')->orderBy('monto', 'DESC')
            ->get()->getResultArray();

        // Cobros por gestor (empleado que registró)
        $porGestor = $this->db->table('pagos pg')
            ->select('p.nombres, p.apellidos, COUNT(*) AS n, COALESCE(SUM(pg.monto),0) AS monto', false)
            ->join('empleados e', 'e.id = pg.empleado_id', 'left')
            ->join('personas p', 'p.id = e.persona_id', 'left')
            ->where('pg.tenant_id', $tenantId)
            ->where('pg.tipo', 'PAGO')->where('pg.estado', 'APLICADO')
            ->where('pg.fecha_hora >=', $ini)->where('pg.fecha_hora <=', $fin)
            ->groupBy('pg.empleado_id')->orderBy('monto', 'DESC')
            ->get()->getResultArray();

        // Otros ingresos (módulo ingresos) por tipo
        $ingresos = $this->db->table('ingresos')
            ->select('tipo, COUNT(*) AS n, COALESCE(SUM(monto),0) AS monto', false)
            ->where('tenant_id', $tenantId)->where('estado', 'ACTIVO')
            ->where('fecha >=', $desde)->where('fecha <=', $hasta)
            ->groupBy('tipo')->get()->getResultArray();

        // Gastos por categoría
        $gastos = $this->db->table('gastos g')
            ->select('gc.nombre AS categoria, COUNT(*) AS n, COALESCE(SUM(g.monto),0) AS monto', false)
            ->join('gasto_categorias gc', 'gc.id = g.categoria_id', 'left')
            ->where('g.tenant_id', $tenantId)->where('g.estado', 'ACTIVO')
            ->where('g.fecha >=', $desde)->where('g.fecha <=', $hasta)
            ->groupBy('g.categoria_id')->orderBy('monto', 'DESC')
            ->get()->getResultArray();

        $totIng = array_sum(array_column($ingresos, 'monto'));
        $totGas = array_sum(array_column($gastos, 'monto'));

        return [
            'cobros'     => $cobros,
            'por_metodo' => $porMetodo,
            'por_gestor' => $porGestor,
            'ingresos'   => $ingresos,
            'gastos'     => $gastos,
            'tot_ing'    => (float) $totIng,
            'tot_gas'    => (float) $totGas,
            'neto'       => round((float) $cobros['monto'] + $totIng - $totGas, 2),
        ];
    }

    /**
     * Flujo de caja del periodo para /finanzas/flujo: todos los movimientos
     * registrados — pagos de cobro (vigentes), otros ingresos, desembolsos
     * entregados (fecha_entrega = egreso real) y gastos — con el neto para
     * ver si el periodo salió en positivo o en negativo.
     */
    public function flujo(int $tenantId, string $desde, string $hasta): array
    {
        $ini = $desde . ' 00:00:00';
        $fin = $hasta . ' 23:59:59';

        // INGRESO · pagos registrados por cobro de crédito (revisión + aplicado)
        $pagos = $this->db->table('pagos pg')
            ->select('pg.id, pg.fecha_hora, pg.monto, pg.metodo, pg.estado,
                      s.codigo_credito, p.nombres, p.apellidos', false)
            ->join('solicitudes s', 's.id = pg.solicitud_id')
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->where('pg.tenant_id', $tenantId)
            ->where('pg.tipo', 'PAGO')->whereIn('pg.estado', ['REVISION', 'APLICADO'])
            ->where('pg.fecha_hora >=', $ini)->where('pg.fecha_hora <=', $fin)
            ->orderBy('pg.fecha_hora', 'DESC')
            ->get()->getResultArray();

        // INGRESO · otros ingresos registrados (venta, donación, otro)
        $ingresos = $this->db->table('ingresos')
            ->select('fecha, tipo, concepto, monto, metodo')
            ->where('tenant_id', $tenantId)->where('estado', 'ACTIVO')
            ->where('fecha >=', $desde)->where('fecha <=', $hasta)
            ->orderBy('fecha', 'DESC')
            ->get()->getResultArray();

        // EGRESO · desembolsos entregados (fecha_entrega = cuando salió el dinero)
        $desembolsos = $this->db->table('solicitudes s')
            ->select('s.id, s.fecha_entrega, s.monto_aprobado, s.monto, s.codigo_credito,
                      p.nombres, p.apellidos', false)
            ->join('clientes c', 'c.id = s.cliente_id')
            ->join('personas p', 'p.id = c.persona_id')
            ->where('s.tenant_id', $tenantId)
            ->whereIn('s.estado', ['ACTIVO', 'LIQUIDADO'])
            ->where('s.fecha_entrega >=', $ini)->where('s.fecha_entrega <=', $fin)
            ->orderBy('s.fecha_entrega', 'DESC')
            ->get()->getResultArray();

        // EGRESO · gastos registrados
        $gastos = $this->db->table('gastos g')
            ->select('g.fecha, g.concepto, g.monto, g.metodo, gc.nombre AS categoria', false)
            ->join('gasto_categorias gc', 'gc.id = g.categoria_id', 'left')
            ->where('g.tenant_id', $tenantId)->where('g.estado', 'ACTIVO')
            ->where('g.fecha >=', $desde)->where('g.fecha <=', $hasta)
            ->orderBy('g.fecha', 'DESC')
            ->get()->getResultArray();

        $tPag = array_sum(array_column($pagos, 'monto'));
        $tIng = array_sum(array_column($ingresos, 'monto'));
        $tDes = array_sum(array_map(fn($d) => (float) ($d['monto_aprobado'] ?: $d['monto']), $desembolsos));
        $tGas = array_sum(array_column($gastos, 'monto'));

        // Movimientos unificados — formato pedido: fecha, descripción y
        // el monto en columna de entrada o de salida según el tipo.
        $lblTipo = ['VENTA' => 'Venta', 'DONACION' => 'Donación', 'OTRO' => 'Otro'];
        $movs = [];
        foreach ($pagos as $p) {
            $movs[] = ['fecha' => substr($p['fecha_hora'], 0, 10), 'ent' => (float) $p['monto'], 'sal' => 0.0,
                'desc' => 'Cobro · ' . trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')) .
                          ($p['codigo_credito'] ? ' · ' . $p['codigo_credito'] : '')];
        }
        foreach ($ingresos as $x) {
            $movs[] = ['fecha' => $x['fecha'], 'ent' => (float) $x['monto'], 'sal' => 0.0,
                'desc' => $x['concepto'] . ' · ' . ($lblTipo[$x['tipo']] ?? $x['tipo'])];
        }
        foreach ($desembolsos as $x) {
            $movs[] = ['fecha' => substr($x['fecha_entrega'], 0, 10), 'ent' => 0.0,
                'sal' => (float) ($x['monto_aprobado'] ?: $x['monto']),
                'desc' => 'Desembolso · ' . trim(($x['nombres'] ?? '') . ' ' . ($x['apellidos'] ?? '')) .
                          ' · ' . ($x['codigo_credito'] ?: '#' . $x['id'])];
        }
        foreach ($gastos as $x) {
            $movs[] = ['fecha' => $x['fecha'], 'ent' => 0.0, 'sal' => (float) $x['monto'],
                'desc' => $x['concepto'] . ($x['categoria'] ? ' · ' . $x['categoria'] : '')];
        }
        usort($movs, fn($a, $b) => strcmp($b['fecha'], $a['fecha']));

        return [
            'pagos'       => $pagos,      'ingresos'    => $ingresos,
            'desembolsos' => $desembolsos, 'gastos'     => $gastos,
            'movimientos' => $movs,
            't_pagos'     => round($tPag, 2), 't_ingresos'    => round($tIng, 2),
            't_desembolsos' => round($tDes, 2), 't_gastos'      => round($tGas, 2),
            'tot_ing' => round($tPag + $tIng, 2),
            'tot_egr' => round($tDes + $tGas, 2),
            'neto'    => round($tPag + $tIng - $tDes - $tGas, 2),
        ];
    }

    /**
     * Tabla de clasificación de cartera (norma CONAMI para EMIF):
     * [categoría, días_mín, días_máx (null = sin techo), % provisión sobre saldo capital].
     * Editable si la norma vigente cambia los rangos o tasas.
     */
    public const CONAMI = [
        ['A1', 0,   30,   1],
        ['A2', 31,  90,   5],
        ['B',  91,  120,  25],
        ['C1', 121, 150,  50],
        ['C2', 151, 180,  75],
        ['D1', 181, 365,  90],
        ['D2', 366, null, 100],
    ];

    /**
     * Clasificación de cartera por riesgo (reporte CONAMI) al corte $alDia.
     * Saldo capital = capital insoluto (LEAST(capital, cuota-pagado) por cuota
     * porque el pago cubre primero interés y luego capital). Se excluyen cuotas
     * ANULADAS (reestructuraciones). Días de atraso = la cuota vencida más vieja.
     */
    public function carteraConami(int $tenantId, string $alDia): array
    {
        $creds = $this->db->table('solicitudes s')
            ->select('s.id, s.codigo_credito, s.monto_aprobado, s.fecha_desembolso, s.ruta,
                      personas.nombres, personas.apellidos, personas.cedula, clientes.codigo AS cod_cli,
                      emp_p.nombres AS gnom, emp_p.apellidos AS gapel', false)
            ->join('clientes', 'clientes.id = s.cliente_id')
            ->join('personas', 'personas.id = clientes.persona_id')
            ->join('empleados emp', 'emp.id = s.asignado_a', 'left')
            ->join('personas emp_p', 'emp_p.id = emp.persona_id', 'left')
            ->where('s.tenant_id', $tenantId)
            ->where('s.estado', 'ACTIVO')
            ->orderBy('personas.apellidos, personas.nombres')
            ->get()->getResultArray();

        $rows   = [];
        $resumen = [];
        foreach (self::CONAMI as [$cat,,, ]) {
            $resumen[$cat] = ['n' => 0, 'saldo' => 0.0, 'vencido' => 0.0, 'provision' => 0.0];
        }

        foreach ($creds as $c) {
            $agg = $this->db->table('cuotas')
                ->select("COALESCE(SUM(LEAST(capital, GREATEST(cuota - pagado, 0))),0) AS saldo_capital,
                          COALESCE(SUM(CASE WHEN fecha_vence < '{$alDia}' AND pagado < cuota
                                            THEN cuota - pagado ELSE 0 END),0) AS vencido,
                          COALESCE(MAX(CASE WHEN fecha_vence < '{$alDia}' AND pagado < cuota
                                            THEN DATEDIFF('{$alDia}', fecha_vence) END),0) AS dias_atraso", false)
                ->where('solicitud_id', (int) $c['id'])
                ->where('estado !=', 'ANULADA')
                ->get()->getRowArray();

            $dias   = (int) ($agg['dias_atraso'] ?? 0);
            $cat    = $this->categoriaConami($dias);
            $saldo  = round((float) $agg['saldo_capital'], 2);
            $venc   = round((float) $agg['vencido'], 2);
            $prov   = round($saldo * $this->pctConami($cat) / 100, 2);

            $resumen[$cat]['n']        += 1;
            $resumen[$cat]['saldo']    += $saldo;
            $resumen[$cat]['vencido']  += $venc;
            $resumen[$cat]['provision'] += $prov;

            $rows[] = [
                'id'            => (int) $c['id'],
                'folio'         => $c['codigo_credito'] ?: 'S/F',
                'cliente'       => trim(($c['nombres'] ?? '') . ' ' . ($c['apellidos'] ?? '')),
                'cedula'        => $c['cedula'] ?? '',
                'cod_cli'       => $c['cod_cli'] ?? '',
                'gestor'        => trim(($c['gnom'] ?? '') . ' ' . ($c['gapel'] ?? '')) ?: '—',
                'ruta'          => $c['ruta'] ?? '',
                'desembolso'    => $c['fecha_desembolso'],
                'monto'         => (float) $c['monto_aprobado'],
                'saldo'         => $saldo,
                'vencido'       => $venc,
                'dias'          => $dias,
                'categoria'     => $cat,
                'pct'           => $this->pctConami($cat),
                'provision'     => $prov,
            ];
        }

        // Ordenar por categoría (riesgo) y luego por días desc
        usort($rows, fn ($a, $b) =>
            array_search($a['categoria'], array_column(self::CONAMI, 0))
            <=> array_search($b['categoria'], array_column(self::CONAMI, 0))
            ?: $b['dias'] <=> $a['dias']);

        $tot = ['n' => 0, 'saldo' => 0.0, 'vencido' => 0.0, 'provision' => 0.0];
        foreach ($resumen as $r) {
            $tot['n'] += $r['n']; $tot['saldo'] += $r['saldo'];
            $tot['vencido'] += $r['vencido']; $tot['provision'] += $r['provision'];
        }

        return [
            'rows'     => $rows,
            'resumen'  => $resumen,
            'totales'  => $tot,
            'pct_mora' => $tot['saldo'] > 0 ? round($tot['vencido'] / $tot['saldo'] * 100, 2) : 0,
            'cobertura'=> $tot['vencido'] > 0 ? round($tot['provision'] / $tot['vencido'] * 100, 2) : 0,
            'corte'    => $alDia,
        ];
    }

    /* ── Categorías de reportes (índice /reportes) ───────────────── */

    /** Categorías personalizadas del tenant (ordenadas). */
    public function categoriasReporte(int $tenantId): array
    {
        return $this->db->table('reporte_categorias')
            ->where('tenant_id', $tenantId)
            ->orderBy('orden, nombre')
            ->get()->getResultArray();
    }

    /** Mapa reporte_key → categoria_id del tenant. */
    public function asignacionesReporte(int $tenantId): array
    {
        $rows = $this->db->table('reporte_asignaciones')
            ->where('tenant_id', $tenantId)
            ->get()->getResultArray();
        $map = [];
        foreach ($rows as $r) {
            $map[$r['reporte_key']] = $r['categoria_id'] !== null ? (int) $r['categoria_id'] : null;
        }
        return $map;
    }

    /**
     * Reportes del catálogo visibles para los permisos del usuario,
     * agrupados por categoría (personalizada activa o la default 'cat').
     * Devuelve [ ['nombre'=>cat, 'orden'=>n, 'custom'=>bool, 'items'=>[reportes]] ].
     */
    public function catalogoAgrupado(int $tenantId, array $permisos): array
    {
        $cats   = $this->categoriasReporte($tenantId);
        $catById = [];
        foreach ($cats as $c) {
            $catById[(int) $c['id']] = $c;
        }
        $asign  = $this->asignacionesReporte($tenantId);
        $grupos = [];   // key: 'c{id}' o 'd{Nombre}' → grupo

        foreach (config('Reportes')->catalogo as $key => $rep) {
            if (!in_array($rep['permiso'], $permisos, true)) continue;

            $gkey = 'd' . $rep['cat'];          // default
            $cid  = $asign[$key] ?? null;
            if ($cid && isset($catById[$cid])) {
                $gkey = 'c' . $cid;             // personalizada (aunque esté inactiva se agrupa igual, se marca)
            }

            if (!isset($grupos[$gkey])) {
                $grupos[$gkey] = [
                    'nombre' => $cid && isset($catById[$cid]) ? $catById[$cid]['nombre'] : $rep['cat'],
                    'orden'  => $cid && isset($catById[$cid]) ? (int) $catById[$cid]['orden'] : 999,
                    'items'  => [],
                ];
            }
            $rep['key'] = $key;
            $grupos[$gkey]['items'][] = $rep;
        }

        usort($grupos, fn ($a, $b) => $a['orden'] <=> $b['orden'] ?: strnatcasecmp($a['nombre'], $b['nombre']));
        return $grupos;
    }

    /** Crea una categoría personalizada del tenant. Devuelve id o null. */
    public function crearCategoriaReporte(int $tenantId, string $nombre, int $orden = 0): ?int
    {
        $nombre = trim($nombre);
        if ($nombre === '' || mb_strlen($nombre) > 80) return null;
        $this->db->table('reporte_categorias')->insert([
            'tenant_id'  => $tenantId,
            'nombre'     => $nombre,
            'orden'      => $orden,
            'activo'     => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ]);
        return (int) $this->db->insertID();
    }

    /** Actualiza nombre/orden/activo de una categoría del tenant. */
    public function guardarCategoriaReporte(int $tenantId, int $id, array $data): bool
    {
        $set = ['updated_at' => date('Y-m-d H:i:s')];
        if (isset($data['nombre'])) $set['nombre'] = trim((string) $data['nombre']);
        if (isset($data['orden']))  $set['orden']  = (int) $data['orden'];
        if (isset($data['activo'])) $set['activo'] = (int) (bool) $data['activo'];
        return (bool) $this->db->table('reporte_categorias')
            ->where('tenant_id', $tenantId)->where('id', $id)
            ->update($set);
    }

    /** Elimina una categoría del tenant y limpia las asignaciones que la usaban. */
    public function eliminarCategoriaReporte(int $tenantId, int $id): bool
    {
        $ok = (bool) $this->db->table('reporte_categorias')
            ->where('tenant_id', $tenantId)->where('id', $id)->delete();
        if ($ok) {
            $this->db->table('reporte_asignaciones')
                ->where('tenant_id', $tenantId)->where('categoria_id', $id)
                ->delete();
        }
        return $ok;
    }

    /**
     * Guarda las asignaciones reporte→categoría (upsert por reporte_key).
     * categoria_id 0/null = vuelve a la categoría por defecto del catálogo.
     */
    public function guardarAsignaciones(int $tenantId, array $map): void
    {
        $validos = array_keys(config('Reportes')->catalogo);
        $catsOk = array_map(fn ($c) => (int) $c['id'], $this->categoriasReporte($tenantId));
        $ahora  = date('Y-m-d H:i:s');

        foreach ($map as $key => $cid) {
            if (!in_array($key, $validos, true)) continue;
            $cid = (int) $cid;
            $cid = $cid > 0 && in_array($cid, $catsOk, true) ? $cid : null;

            $existe = $this->db->table('reporte_asignaciones')
                ->where('tenant_id', $tenantId)->where('reporte_key', $key)
                ->countAllResults();
            if ($existe) {
                $this->db->table('reporte_asignaciones')
                    ->where('tenant_id', $tenantId)->where('reporte_key', $key)
                    ->update(['categoria_id' => $cid, 'updated_at' => $ahora]);
            } else {
                $this->db->table('reporte_asignaciones')->insert([
                    'tenant_id'    => $tenantId,
                    'reporte_key'  => $key,
                    'categoria_id' => $cid,
                    'created_at'   => $ahora,
                ]);
            }
        }
    }

    /** Categoría CONAMI según días de atraso (peor cuota del crédito). */
    private function categoriaConami(int $dias): string
    {
        foreach (self::CONAMI as [$cat, $min, $max]) {
            if ($dias >= $min && ($max === null || $dias <= $max)) return $cat;
        }
        return 'A1';
    }

    /** % de provisión de la categoría. */
    private function pctConami(string $cat): int
    {
        foreach (self::CONAMI as [$c,, , $pct]) {
            if ($c === $cat) return $pct;
        }
        return 1;
    }
}
