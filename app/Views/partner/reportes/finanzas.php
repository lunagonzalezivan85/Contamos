<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2 = $mon ?? 'C$';
$lblTipo = ['VENTA' => 'Venta', 'DONACION' => 'Donación', 'OTRO' => 'Otro'];
$neto = (float) $data['neto'];
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Reporte financiero</h3>
        <p class="page-subtitle">
            Cobros + otros ingresos − gastos · <?= esc(date('d/m/Y', strtotime($desde))) ?> — <?= esc(date('d/m/Y', strtotime($hasta))) ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Periodo</button>
    </div>
</div>

<!-- Resumen -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format((float) $data['cobros']['monto'], 2) ?></div>
        <div class="stat-lbl">Cobros de crédito (<?= (int) $data['cobros']['n'] ?>)</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--primary-dark);"><?= esc($m2) ?> <?= number_format($data['tot_ing'], 2) ?></div>
        <div class="stat-lbl">Otros ingresos</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#B42318;"><?= esc($m2) ?> <?= number_format($data['tot_gas'], 2) ?></div>
        <div class="stat-lbl">Gastos</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:<?= $neto >= 0 ? 'var(--primary-dark)' : '#B42318' ?>;"><?= esc($m2) ?> <?= number_format($neto, 2) ?></div>
        <div class="stat-lbl">Resultado neto del periodo</div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
    <!-- Cobros por método -->
    <div class="card">
        <h4 class="card-title">Cobros por método</h4>
        <p class="card-subtitle">Pagos aplicados en el periodo.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Método</th><th>Cobros</th><th>Monto</th></tr></thead>
                <tbody>
                <?php if (empty($data['por_metodo'])): ?>
                    <tr><td colspan="3" class="card-subtitle">Sin cobros en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($data['por_metodo'] as $x): ?>
                    <tr>
                        <td><?= esc(ucfirst(strtolower($x['metodo']))) ?></td>
                        <td style="text-align:center;"><?= (int) $x['n'] ?></td>
                        <td style="text-align:right; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $x['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Cobros por gestor -->
    <div class="card">
        <h4 class="card-title">Cobros por gestor</h4>
        <p class="card-subtitle">Pagos aplicados registrados por cada gestor.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Gestor</th><th>Cobros</th><th>Monto</th></tr></thead>
                <tbody>
                <?php if (empty($data['por_gestor'])): ?>
                    <tr><td colspan="3" class="card-subtitle">Sin cobros en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($data['por_gestor'] as $x): ?>
                    <tr>
                        <td><?= esc(trim(($x['nombres'] ?? '') . ' ' . ($x['apellidos'] ?? '')) ?: 'Sin gestor') ?></td>
                        <td style="text-align:center;"><?= (int) $x['n'] ?></td>
                        <td style="text-align:right; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $x['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Otros ingresos -->
    <div class="card">
        <h4 class="card-title">Otros ingresos</h4>
        <p class="card-subtitle">Módulo de ingresos, por tipo.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Tipo</th><th>Registros</th><th>Monto</th></tr></thead>
                <tbody>
                <?php if (empty($data['ingresos'])): ?>
                    <tr><td colspan="3" class="card-subtitle">Sin ingresos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($data['ingresos'] as $x): ?>
                    <tr>
                        <td><?= esc($lblTipo[$x['tipo']] ?? $x['tipo']) ?></td>
                        <td style="text-align:center;"><?= (int) $x['n'] ?></td>
                        <td style="text-align:right; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $x['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Gastos -->
    <div class="card">
        <h4 class="card-title">Gastos por categoría</h4>
        <p class="card-subtitle">Egresos del periodo.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Categoría</th><th>Registros</th><th>Monto</th></tr></thead>
                <tbody>
                <?php if (empty($data['gastos'])): ?>
                    <tr><td colspan="3" class="card-subtitle">Sin gastos en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($data['gastos'] as $x): ?>
                    <tr>
                        <td><?= esc($x['categoria'] ?? 'Sin categoría') ?></td>
                        <td style="text-align:center;"><?= (int) $x['n'] ?></td>
                        <td style="text-align:right; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $x['monto'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
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
        <form method="get" action="<?= base_url('finanzas/reporte') ?>">
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
                <a class="btn" href="<?= base_url('finanzas/reporte') ?>">Este mes</a>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
