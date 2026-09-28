<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2 = $mon ?? 'C$';
$hayFiltro = $buscar !== '' || $soloMora;
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Cartera de créditos</h3>
        <p class="page-subtitle">
            Salud de la cartera vigente: saldo, cuotas vencidas y atraso por crédito
            <?php if ($hayFiltro): ?>
                · filtrado <a href="<?= base_url('credito/cartera') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <a class="btn btn-outline btn-sm" href="<?= base_url('credito/cartera' . ($soloMora ? '' : '?mora=1')) ?>">
            <?= icon('alert-circle', 14) ?> <?= $soloMora ? 'Ver toda' : 'Solo en mora' ?>
        </a>
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-buscar"><?= icon('search', 14) ?> Buscar</button>
    </div>
</div>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= (int) $totales['creditos'] ?></div>
        <div class="stat-lbl">Créditos vigentes</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format($totales['saldo'], 2) ?></div>
        <div class="stat-lbl">Saldo total de cartera</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#B42318;"><?= esc($m2) ?> <?= number_format($totales['vencido'], 2) ?></div>
        <div class="stat-lbl">Saldo vencido</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#B42318;"><?= (int) $totales['con_mora'] ?></div>
        <div class="stat-lbl">Créditos en mora</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= number_format($totales['pct_vencido'], 2) ?>%</div>
        <div class="stat-lbl">Mora sobre saldo</div>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <?php if (empty($filas)): ?>
        <p class="card-subtitle">Sin créditos vigentes<?= $soloMora ? ' en mora' : '' ?><?= $buscar !== '' ? ' con esa búsqueda' : '' ?>.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Crédito</th>
                    <th>Gestor</th>
                    <th>Monto</th>
                    <th>Saldo</th>
                    <th>Vencido</th>
                    <th>Atraso</th>
                    <th>Estado</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <?php $enMora = $f['vencidas'] > 0; ?>
                <tr>
                    <td>
                        <?= esc(trim(($f['nombres'] ?? '') . ' ' . ($f['apellidos'] ?? ''))) ?>
                        <?php if (!empty($f['telefono'])): ?>
                            <br><small class="card-subtitle"><?= esc($f['telefono']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?= esc($f['codigo_credito'] ?: '#' . $f['id']) ?>
                        <br><small class="card-subtitle"><?= esc($f['codigo'] ?? '') ?></small>
                    </td>
                    <td><?= esc(trim(($f['gestor_nombres'] ?? '') . ' ' . ($f['gestor_apellidos'] ?? '')) ?: '—') ?></td>
                    <td style="text-align:right; white-space:nowrap;"><?= esc($m2) ?> <?= number_format((float) ($f['monto_aprobado'] ?? $f['monto']), 2) ?></td>
                    <td style="text-align:right; white-space:nowrap; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $f['saldo'], 2) ?></td>
                    <td style="text-align:right; white-space:nowrap; <?= $enMora ? 'color:#B42318; font-weight:700;' : 'color:var(--text-muted);' ?>">
                        <?= esc($m2) ?> <?= number_format((float) $f['saldo_vencido'], 2) ?>
                        <?php if ($enMora): ?>
                            <br><small class="card-subtitle"><?= (int) $f['vencidas'] ?> cuota<?= $f['vencidas'] > 1 ? 's' : '' ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if ($enMora): ?>
                            <span class="badge <?= $f['dias_atraso'] >= 30 ? 'st-bad' : 'st-warn' ?>"><?= (int) $f['dias_atraso'] ?>d</span>
                        <?php else: ?>
                            <span class="card-subtitle">—</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="badge <?= $enMora ? 'st-bad' : 'st-ok' ?>"><?= $enMora ? 'En mora' : 'Al día' ?></span>
                    </td>
                    <td style="text-align:right;">
                        <a class="btn btn-outline btn-sm" href="<?= base_url('creditos/' . $f['id']) ?>">Ver</a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php endif; ?>
</div>

<!-- Modal: buscar -->
<div class="modal-overlay" id="modal-buscar" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4>Buscar en cartera</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('credito/cartera') ?>">
            <?php if ($soloMora): ?><input type="hidden" name="mora" value="1"><?php endif; ?>
            <div class="modal-body">
                <div class="form-group form-full">
                    <label>Cliente, cédula o código</label>
                    <input type="text" name="q" value="<?= esc($buscar) ?>" placeholder="Nombre, cédula, código de crédito o de cliente">
                </div>
            </div>
            <div class="modal-foot">
                <a class="btn" href="<?= base_url('credito/cartera' . ($soloMora ? '?mora=1' : '')) ?>">Limpiar</a>
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
