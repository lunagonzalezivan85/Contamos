<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2   = $mon ?? 'C$';
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
<div class="stats-grid">
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

<!-- Movimientos del periodo — tabs Entradas / Salidas -->
<div class="card">
    <div class="tabs">
        <button type="button" class="tab-btn on" data-tab="ent">
            <?= icon('trending-up', 15) ?> Entradas (<?= count($d['entradas']) ?>)
        </button>
        <button type="button" class="tab-btn" data-tab="sal">
            <?= icon('trending-down', 15) ?> Salidas (<?= count($d['salidas']) ?>)
        </button>
    </div>

    <!-- Entradas: pagos registrados + otros ingresos -->
    <div class="tab-panel on" data-panel="ent">
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Descripción</th><th style="text-align:right;">Monto de entrada</th></tr></thead>
                <tbody>
                <?php if (empty($d['entradas'])): ?>
                    <tr><td colspan="3" class="card-subtitle">Sin ingresos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($d['entradas'] as $e): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($e['fecha']))) ?></td>
                        <td><?= esc($e['desc']) ?></td>
                        <td style="text-align:right; font-weight:700; color:var(--primary-dark);">+ <?= esc($m2) ?> <?= number_format($e['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="2">Total entradas</th><th style="text-align:right;"><?= esc($m2) ?> <?= number_format($d['tot_ing'], 2) ?></th></tr></tfoot>
            </table>
        </div>
    </div>

    <!-- Salidas: desembolsos + gastos -->
    <div class="tab-panel" data-panel="sal">
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Fecha</th><th>Descripción</th><th style="text-align:right;">Monto de salida</th></tr></thead>
                <tbody>
                <?php if (empty($d['salidas'])): ?>
                    <tr><td colspan="3" class="card-subtitle">Sin egresos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($d['salidas'] as $e): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($e['fecha']))) ?></td>
                        <td><?= esc($e['desc']) ?></td>
                        <td style="text-align:right; font-weight:700; color:#B42318;">− <?= esc($m2) ?> <?= number_format($e['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><th colspan="2">Total salidas</th><th style="text-align:right;"><?= esc($m2) ?> <?= number_format($d['tot_egr'], 2) ?></th></tr></tfoot>
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
