<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? 'C$'; ?>

<div class="list-head">
    <div>
        <h3 class="page-title">Por cobrar</h3>
        <p class="page-subtitle">
            Clientes con cuotas pendientes al <?= esc(date('d/m/Y', strtotime($hoy))) ?> — incluye lo que vence hoy y atrasos acumulados
            <?php if ($buscar !== ''): ?>
                · filtrado <a href="<?= base_url('finanzas/recuperacion') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <form method="get" action="<?= base_url('finanzas/recuperacion') ?>" style="display:flex; gap:8px; align-items:center;">
            <?php if ($buscar !== ''): ?><input type="hidden" name="q" value="<?= esc($buscar) ?>"><?php endif; ?>
            <input type="date" name="fecha" value="<?= esc($hoy) ?>" title="Cobrar al"
                   style="padding:7px 10px; border:1.5px solid #E2E8F0; border-radius:8px;">
            <button type="submit" class="btn btn-outline btn-sm">Ver</button>
            <button type="button" class="btn btn-outline btn-sm" data-modal="modal-buscar"><?= icon('search', 14) ?> Buscar</button>
        </form>
    </div>
</div>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= count($filas) ?></div>
        <div class="stat-lbl">Créditos por cobrar</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#B42318;"><?= esc($m2) ?> <?= number_format($total, 2) ?></div>
        <div class="stat-lbl">Total por recuperar</div>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <?php if (empty($filas)): ?>
        <p class="card-subtitle">Nadie por cobrar a esa fecha<?= $buscar !== '' ? ' con esa búsqueda' : '' ?> — cartera al día.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Crédito</th>
                    <th>Gestor</th>
                    <th>Cuotas pend.</th>
                    <th>Desde</th>
                    <th>Saldo por cobrar</th>
                    <th>Atraso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td>
                        <?= esc(trim($f['nombres'] . ' ' . $f['apellidos'])) ?>
                        <?php if (!empty($f['telefono'])): ?>
                            <br><small class="card-subtitle"><?= esc($f['telefono']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= esc($f['codigo_credito'] ?: '#' . $f['solicitud_id']) ?></td>
                    <td><?= esc(trim(($f['gestor_nombres'] ?? '') . ' ' . ($f['gestor_apellidos'] ?? '')) ?: '—') ?></td>
                    <td style="text-align:center;"><?= (int) $f['cuotas_vencidas'] ?></td>
                    <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($f['primera_vencida']))) ?></td>
                    <td style="text-align:right; white-space:nowrap; font-weight:700; color:#B42318;">
                        <?= esc($m2) ?> <?= number_format((float) $f['saldo_vencido'], 2) ?>
                    </td>
                    <td style="text-align:right;">
                        <?php if ((int) $f['dias_atraso'] === 0): ?>
                            <span class="badge st-ok">vence hoy</span>
                        <?php else: ?>
                            <span class="badge <?= $f['dias_atraso'] >= 30 ? 'st-bad' : 'st-warn' ?>">
                                <?= (int) $f['dias_atraso'] ?> día<?= $f['dias_atraso'] != 1 ? 's' : '' ?> atraso
                            </span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:right;">
                        <a class="btn btn-outline btn-sm" href="<?= base_url('creditos/' . $f['solicitud_id']) ?>">Ver</a>
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
            <h4>Buscar en recuperación</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('finanzas/recuperacion') ?>">
            <div class="modal-body">
                <div class="form-group form-full">
                    <label>Cliente, cédula o código</label>
                    <input type="text" name="q" value="<?= esc($buscar) ?>" placeholder="Nombre del cliente, cédula o código de crédito">
                </div>
            </div>
            <div class="modal-foot">
                <a class="btn" href="<?= base_url('finanzas/recuperacion') ?>">Limpiar</a>
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
