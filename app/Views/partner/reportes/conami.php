<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2 = $mon ?? 'C$';
$badgeCat = [
    'A1' => 'st-ok', 'A2' => 'st-info', 'B' => 'st-warn',
    'C1' => 'st-warn', 'C2' => 'st-bad', 'D1' => 'st-bad',
    'D2' => 'st-bad', 'E'  => 'st-bad',
];
$rangos = [];
foreach ($tabla as [$c, $min, $max, $p]) {
    $rangos[$c] = [$min === 0 && $max !== null ? "0–{$max}" : ($max === null ? "+{$min}" : "{$min}–{$max}"), $p];
}
?>

<style>
@media print {
    .list-head .detail-hero-actions, .modal-overlay, .sidebar, .topbar { display:none !important; }
    .card { box-shadow:none; border:1px solid #ddd; }
    body { background:#fff; }
}
</style>

<div class="list-head">
    <div>
        <h3 class="page-title">Clasificación de cartera por riesgo</h3>
        <p class="page-subtitle">
            Norma CONAMI · Corte al <?= esc(date('d/m/Y', strtotime($corte))) ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <a class="btn btn-outline btn-sm" href="<?= base_url('credito/reporte-conami?corte=' . $corte . '&exportar=excel') ?>"><?= icon('file-text', 14) ?> Excel</a>
        <button type="button" class="btn btn-outline btn-sm" onclick="window.print()"><?= icon('printer', 14) ?> Imprimir</button>
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('clock', 14) ?> Corte</button>
    </div>
</div>

<!-- KPIs -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= (int) $data['totales']['n'] ?></div>
        <div class="stat-lbl">Créditos activos</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format((float) $data['totales']['saldo'], 2) ?></div>
        <div class="stat-lbl">Saldo capital</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--danger, #D64545);"><?= esc($m2) ?> <?= number_format((float) $data['totales']['vencido'], 2) ?></div>
        <div class="stat-lbl">Saldo vencido</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= number_format((float) $data['pct_mora'], 1) ?>%</div>
        <div class="stat-lbl">Mora de cartera</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--primary-dark);"><?= esc($m2) ?> <?= number_format((float) $data['totales']['provision'], 2) ?></div>
        <div class="stat-lbl">Provisión requerida</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= number_format((float) $data['cobertura'], 0) ?>%</div>
        <div class="stat-lbl">Cobertura de lo vencido</div>
    </div>
</div>

<!-- Resumen por categoría -->
<div class="card">
    <h4 class="card-title">Resumen por categoría de riesgo</h4>
    <p class="card-subtitle">Clasificación por días de atraso de la cuota más antigua pendiente.</p>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Categoría</th><th>Rango días</th><th>Créditos</th>
                    <th>Saldo capital</th><th>Saldo vencido</th><th>% Prov.</th><th>Provisión</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($data['resumen'] as $cat => $r): ?>
                <tr>
                    <td><span class="badge <?= $badgeCat[$cat] ?? 'badge-soft' ?>"><?= esc($cat) ?></span></td>
                    <td style="text-align:center;"><?= esc($rangos[$cat][0] ?? '') ?></td>
                    <td style="text-align:center;"><?= (int) $r['n'] ?></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $r['saldo'], 2) ?></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $r['vencido'], 2) ?></td>
                    <td style="text-align:center;"><?= (int) ($rangos[$cat][1] ?? 0) ?>%</td>
                    <td style="text-align:right; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $r['provision'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:700; border-top:2px solid var(--border, #E5E9F0);">
                    <td>TOTAL</td><td></td>
                    <td style="text-align:center;"><?= (int) $data['totales']['n'] ?></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $data['totales']['saldo'], 2) ?></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $data['totales']['vencido'], 2) ?></td>
                    <td></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $data['totales']['provision'], 2) ?></td>
                </tr>
            </tfoot>
        </table>
    </div>
</div>

<!-- Detalle por crédito -->
<div class="card">
    <h4 class="card-title">Detalle por crédito</h4>
    <p class="card-subtitle">Toda la cartera activa ordenada por categoría y atraso.</p>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Folio</th><th>Cliente</th><th>Gestor</th><th>Ruta</th>
                    <th>Desembolso</th><th>Monto</th><th>Saldo</th><th>Vencido</th>
                    <th>Días</th><th>Cat.</th><th>Provisión</th>
                </tr>
            </thead>
            <tbody>
            <?php if (empty($data['rows'])): ?>
                <tr><td colspan="11" class="card-subtitle">Sin cartera activa al corte.</td></tr>
            <?php endif; ?>
            <?php foreach ($data['rows'] as $r): ?>
                <tr>
                    <td><a href="<?= base_url('creditos/' . (int) $r['id']) ?>"><?= esc($r['folio']) ?></a></td>
                    <td><?= esc($r['cliente']) ?><br><small class="card-subtitle"><?= esc($r['cedula']) ?></small></td>
                    <td><?= esc($r['gestor']) ?></td>
                    <td><?= esc($r['ruta'] ?: '—') ?></td>
                    <td><?= $r['desembolso'] ? esc(date('d/m/Y', strtotime($r['desembolso']))) : '—' ?></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $r['monto'], 2) ?></td>
                    <td style="text-align:right; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $r['saldo'], 2) ?></td>
                    <td style="text-align:right; <?= $r['vencido'] > 0 ? 'color:var(--danger, #D64545); font-weight:600;' : '' ?>">
                        <?= $r['vencido'] > 0 ? esc($m2) . ' ' . number_format((float) $r['vencido'], 2) : '—' ?>
                    </td>
                    <td style="text-align:center;"><?= $r['dias'] > 0 ? (int) $r['dias'] : '—' ?></td>
                    <td><span class="badge <?= $badgeCat[$r['categoria']] ?? 'badge-soft' ?>"><?= esc($r['categoria']) ?></span></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $r['provision'], 2) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <p class="card-subtitle" style="margin-top:10px;">
        % mora = saldo vencido / saldo capital. Provisión = saldo capital × % de la categoría
        (<?= implode(', ', array_map(fn ($c) => "{$c}: " . ($rangos[$c][1] ?? 0) . '%', array_keys($rangos))) ?>).
        Tabla ajustable en <code>ReporteService::CONAMI</code> según la norma vigente.
    </p>
</div>

<!-- Modal: fecha de corte -->
<div class="modal-overlay" id="modal-filtro" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4>Fecha de corte</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('credito/reporte-conami') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Corte al</label>
                    <input type="date" name="corte" value="<?= esc($corte) ?>" max="<?= date('Y-m-d') ?>">
                </div>
            </div>
            <div class="modal-foot">
                <a class="btn" href="<?= base_url('credito/reporte-conami') ?>">Hoy</a>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
