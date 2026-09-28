<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2 = $mon ?? 'C$';
$clsEstado = [
    'CONTACTO'    => 'st-info',
    'CREADA'      => 'st-info',
    'REVISION'    => 'st-warn',
    'APROBADA'    => 'st-ok',
    'DESEMBOLSO'  => 'st-info',
    'ACTIVO'      => 'st-ok',
    'LIQUIDADO'   => 'st-bad',
    'RECHAZADA'   => 'st-bad',
];
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Reporte de créditos</h3>
        <p class="page-subtitle">
            <?= esc(date('d/m/Y', strtotime($desde))) ?> — <?= esc(date('d/m/Y', strtotime($hasta))) ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Periodo</button>
    </div>
</div>

<!-- Resumen -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= (int) ($data['nuevas']['n'] ?? 0) ?></div>
        <div class="stat-lbl">Solicitudes nuevas</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format((float) ($data['nuevas']['monto'] ?? 0), 2) ?></div>
        <div class="stat-lbl">Monto solicitado</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= (int) ($data['desembolsos']['n'] ?? 0) ?></div>
        <div class="stat-lbl">Desembolsos del periodo</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--primary-dark);"><?= esc($m2) ?> <?= number_format((float) ($data['desembolsos']['monto'] ?? 0), 2) ?></div>
        <div class="stat-lbl">Monto colocado</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= (int) $data['cartera']['activos'] ?> <small style="font-size:14px; color:var(--text-muted);">/ <?= (int) $data['cartera']['en_mora'] ?> en mora</small></div>
        <div class="stat-lbl">Cartera activa</div>
    </div>
</div>

<div style="display:grid; grid-template-columns: 1fr 1fr; gap:16px;">
    <!-- Pipeline por estado -->
    <div class="card">
        <h4 class="card-title">Pipeline por estado</h4>
        <p class="card-subtitle">Todas las solicitudes del negocio.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Estado</th><th>Solicitudes</th><th>Solicitado</th><th>Aprobado</th></tr></thead>
                <tbody>
                <?php if (empty($data['por_estado'])): ?>
                    <tr><td colspan="4" class="card-subtitle">Sin solicitudes.</td></tr>
                <?php endif; ?>
                <?php foreach ($data['por_estado'] as $e): ?>
                    <tr>
                        <td><span class="badge <?= $clsEstado[$e['estado']] ?? 'badge-soft' ?>"><?= esc($lblEstado[$e['estado']] ?? $e['estado']) ?></span></td>
                        <td style="text-align:center;"><?= (int) $e['n'] ?></td>
                        <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $e['solicitado'], 2) ?></td>
                        <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $e['aprobado'], 2) ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>

    <!-- Por gestor -->
    <div class="card">
        <h4 class="card-title">Gestión por gestor</h4>
        <p class="card-subtitle">Solicitudes creadas en el periodo por cada gestor.</p>
        <div class="table-wrap">
            <table class="tbl">
                <thead><tr><th>Gestor</th><th>Solicitudes</th><th>Solicitado</th><th>Desemb.</th><th>Colocado</th></tr></thead>
                <tbody>
                <?php if (empty($data['por_gestor'])): ?>
                    <tr><td colspan="5" class="card-subtitle">Sin movimiento en el periodo.</td></tr>
                <?php endif; ?>
                <?php foreach ($data['por_gestor'] as $g): ?>
                    <tr>
                        <td><?= esc(trim(($g['nombres'] ?? '') . ' ' . ($g['apellidos'] ?? '')) ?: 'Sin asignar') ?></td>
                        <td style="text-align:center;"><?= (int) $g['solicitudes'] ?></td>
                        <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $g['solicitado'], 2) ?></td>
                        <td style="text-align:center;"><?= (int) $g['desembolsados'] ?></td>
                        <td style="text-align:right; font-weight:700;"><?= esc($m2) ?> <?= number_format((float) $g['colocado'], 2) ?></td>
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
        <form method="get" action="<?= base_url('credito/reporte') ?>">
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
                <a class="btn" href="<?= base_url('credito/reporte') ?>">Este mes</a>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
