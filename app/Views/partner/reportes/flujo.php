<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2   = $mon ?? 'C$';
$lblT = \App\Models\IngresoModel::TIPOS;
$lblE = \App\Models\PagoModel::LABEL_ESTADO;
$d    = $data;
$pos  = $d['neto'] >= 0;
$max  = max($d['tot_ing'], $d['tot_egr'], 0.01);
$pctI = round($d['tot_ing'] / $max * 100);
$pctE = round($d['tot_egr'] / $max * 100);
$qs   = 'desde=' . $desde . '&hasta=' . $hasta;
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Ingresos vs egresos</h3>
        <p class="page-subtitle">
            Pagos e ingresos contra desembolsos y gastos
            · <?= esc(date('d/m/Y', strtotime($desde))) ?> — <?= esc(date('d/m/Y', strtotime($hasta))) ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Periodo</button>
        <a class="btn btn-outline btn-sm" href="<?= base_url('finanzas/flujo') ?>?<?= $qs ?>&exportar=excel">Excel</a>
    </div>
</div>

<!-- Resultado del periodo -->
<div class="card" style="text-align:center; padding:22px;">
    <p class="card-subtitle" style="margin:0;">Resultado del periodo</p>
    <p style="font-size:34px; font-weight:800; margin:6px 0 2px; color:<?= $pos ? 'var(--primary-dark)' : '#B42318' ?>;">
        <?= $pos ? '+' : '−' ?><?= esc($m2) ?> <?= number_format(abs($d['neto']), 2) ?>
    </p>
    <span class="badge <?= $pos ? 'st-ok' : 'st-bad' ?>" style="font-size:13px; padding:5px 14px;">
        <?= icon($pos ? 'trending-up' : 'trending-down', 13) ?>
        <?= $pos ? 'POSITIVO — entró más de lo que salió' : 'NEGATIVO — salió más de lo que entró' ?>
    </span>
</div>

<!-- Resumen + comparativo -->
<div class="stats-grid" style="margin-top:16px;">
    <div class="stat-card">
        <div class="stat-num" style="color:var(--primary-dark);"><?= esc($m2) ?> <?= number_format($d['tot_ing'], 2) ?></div>
        <div class="stat-lbl">Ingresos totales</div>
        <p class="card-subtitle" style="margin-top:4px;">Pagos <?= esc($m2) ?> <?= number_format($d['t_pagos'], 0) ?> · Otros <?= number_format($d['t_ingresos'], 0) ?></p>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#B42318;"><?= esc($m2) ?> <?= number_format($d['tot_egr'], 2) ?></div>
        <div class="stat-lbl">Egresos totales</div>
        <p class="card-subtitle" style="margin-top:4px;">Desembolsos <?= esc($m2) ?> <?= number_format($d['t_desembolsos'], 0) ?> · Gastos <?= number_format($d['t_gastos'], 0) ?></p>
    </div>
</div>

<div class="card">
    <h4 class="card-title">Comparativo</h4>
    <div style="display:flex; flex-direction:column; gap:12px; margin-top:6px;">
        <div>
            <div class="prog-head">
                <span class="card-subtitle" style="margin:0;">Ingresos</span>
                <strong style="color:var(--primary-dark);"><?= esc($m2) ?> <?= number_format($d['tot_ing'], 2) ?></strong>
            </div>
            <div class="prog"><div class="prog-fill" style="width:<?= $pctI ?>%"></div></div>
        </div>
        <div>
            <div class="prog-head">
                <span class="card-subtitle" style="margin:0;">Egresos</span>
                <strong style="color:#B42318;"><?= esc($m2) ?> <?= number_format($d['tot_egr'], 2) ?></strong>
            </div>
            <div class="prog"><div class="prog-fill prog-fill-out" style="width:<?= $pctE ?>%"></div></div>
        </div>
    </div>
</div>

<!-- Detalle de movimientos -->
<div style="display:grid; grid-template-columns:1fr 1fr; gap:16px;">
    <!-- Pagos registrados -->
    <div class="card">
        <h4 class="card-title"><?= icon('credit-card', 16) ?> Pagos registrados</h4>
        <p class="card-subtitle">Cobros de crédito vigentes (revisión + aplicados).</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Cliente</th><th>Estado</th><th style="text-align:right;">Monto</th></tr></thead>
                <tbody>
                <?php if (empty($d['pagos'])): ?>
                    <tr><td colspan="4" class="card-subtitle">Sin pagos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($d['pagos'] as $p): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($p['fecha_hora']))) ?></td>
                        <td>
                            <?= esc(trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''))) ?>
                            <span class="card-subtitle" style="display:block;"><?= esc($p['codigo_credito'] ?? '') ?> · <?= esc(ucfirst(strtolower($p['metodo']))) ?></span>
                        </td>
                        <td><span class="badge <?= $p['estado'] === 'APLICADO' ? 'st-ok' : 'st-warn' ?>"><?= esc($lblE[$p['estado']] ?? $p['estado']) ?></span></td>
                        <td style="text-align:right; font-weight:700; color:var(--primary-dark);">+ <?= esc($m2) ?> <?= number_format((float) $p['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="3">Total pagos</th><th style="text-align:right;"><?= esc($m2) ?> <?= number_format($d['t_pagos'], 2) ?></th></tr></tfoot>
            </table>
        </div>
    </div>

    <!-- Otros ingresos -->
    <div class="card">
        <h4 class="card-title"><?= icon('trending-up', 16) ?> Otros ingresos</h4>
        <p class="card-subtitle">Ventas, donaciones y otros registrados.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Concepto</th><th>Tipo</th><th style="text-align:right;">Monto</th></tr></thead>
                <tbody>
                <?php if (empty($d['ingresos'])): ?>
                    <tr><td colspan="4" class="card-subtitle">Sin otros ingresos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($d['ingresos'] as $x): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($x['fecha']))) ?></td>
                        <td><?= esc($x['concepto']) ?></td>
                        <td><span class="badge badge-soft"><?= esc($lblT[$x['tipo']] ?? $x['tipo']) ?></span></td>
                        <td style="text-align:right; font-weight:700; color:var(--primary-dark);">+ <?= esc($m2) ?> <?= number_format((float) $x['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="3">Total otros ingresos</th><th style="text-align:right;"><?= esc($m2) ?> <?= number_format($d['t_ingresos'], 2) ?></th></tr></tfoot>
            </table>
        </div>
    </div>

    <!-- Desembolsos -->
    <div class="card">
        <h4 class="card-title"><?= icon('cash', 16) ?> Desembolsos entregados</h4>
        <p class="card-subtitle">Dinero entregado a clientes en el periodo.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Cliente</th><th>Crédito</th><th style="text-align:right;">Monto</th></tr></thead>
                <tbody>
                <?php if (empty($d['desembolsos'])): ?>
                    <tr><td colspan="4" class="card-subtitle">Sin desembolsos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($d['desembolsos'] as $x): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($x['fecha_entrega']))) ?></td>
                        <td><?= esc(trim(($x['nombres'] ?? '') . ' ' . ($x['apellidos'] ?? ''))) ?></td>
                        <td><?= esc($x['codigo_credito'] ?: '#' . $x['id']) ?></td>
                        <td style="text-align:right; font-weight:700; color:#B42318;">− <?= esc($m2) ?> <?= number_format((float) ($x['monto_aprobado'] ?: $x['monto']), 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="3">Total desembolsos</th><th style="text-align:right;"><?= esc($m2) ?> <?= number_format($d['t_desembolsos'], 2) ?></th></tr></tfoot>
            </table>
        </div>
    </div>

    <!-- Gastos -->
    <div class="card">
        <h4 class="card-title"><?= icon('trending-down', 16) ?> Gastos</h4>
        <p class="card-subtitle">Egresos registrados en el módulo de gastos.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Concepto</th><th>Categoría</th><th style="text-align:right;">Monto</th></tr></thead>
                <tbody>
                <?php if (empty($d['gastos'])): ?>
                    <tr><td colspan="4" class="card-subtitle">Sin gastos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($d['gastos'] as $x): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($x['fecha']))) ?></td>
                        <td><?= esc($x['concepto']) ?></td>
                        <td><span class="badge badge-soft"><?= esc($x['categoria'] ?? 'Sin categoría') ?></span></td>
                        <td style="text-align:right; font-weight:700; color:#B42318;">− <?= esc($m2) ?> <?= number_format((float) $x['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="3">Total gastos</th><th style="text-align:right;"><?= esc($m2) ?> <?= number_format($d['t_gastos'], 2) ?></th></tr></tfoot>
            </table>
        </div>
    </div>
</div>

<!-- Modal: filtro -->
<div class="modal-overlay" id="modal-filtro" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4>Periodo del reporte</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('finanzas/flujo') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= esc($desde) ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= esc($hasta) ?>">
                </div>
            </div>
            <div class="modal-foot">
                <a class="btn" href="<?= base_url('finanzas/flujo') ?>">Este mes</a>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
