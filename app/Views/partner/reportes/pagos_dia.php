<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
/**
 * Pagos del día desglosados: capital / interés / mora por cobro.
 * Espera: $rows, $totales, $fecha, $metodos, $lblEstado, $mon.
 * $rows[].estimado = true cuando el pago está en revisión y el
 * desglose es proporcional a la primera cuota pendiente.
 */
$m2 = $mon ?? 'C$';
$t  = $totales ?? ['n' => 0, 'capital' => 0, 'interes' => 0, 'mora' => 0, 'total' => 0];
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Pagos del día</h3>
        <p class="page-subtitle">
            Cobros registrados el <?= esc(date('d/m/Y', strtotime($fecha))) ?> — desglose capital / interés
            <?php if (array_sum(array_column($rows ?? [], 'estimado')) > 0): ?>
                · <em>* en revisión — desglose estimado por el plan pendiente</em>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <form method="get" action="<?= base_url('finanzas/pagos-dia') ?>" style="display:flex; gap:8px; align-items:center;">
            <input type="date" name="fecha" value="<?= esc($fecha) ?>" max="<?= esc(date('Y-m-d')) ?>"
                   title="Fecha de los cobros"
                   style="padding:7px 10px; border:1.5px solid #E2E8F0; border-radius:8px;">
            <button type="submit" class="btn btn-outline btn-sm">Ver</button>
            <a class="btn btn-outline btn-sm"
               href="<?= base_url('finanzas/pagos-dia') ?>?fecha=<?= esc($fecha) ?>&exportar=excel">Excel</a>
            <a class="btn btn-outline btn-sm" href="<?= base_url('finanzas/recuperacion') ?>">Por cobrar</a>
        </form>
    </div>
</div>

<!-- Resumen -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= (int) $t['n'] ?></div>
        <div class="stat-lbl">Cobros del día</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--primary-dark);"><?= esc($m2) ?> <?= number_format((float) $t['capital'], 2) ?></div>
        <div class="stat-lbl">A capital</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format((float) $t['interes'], 2) ?></div>
        <div class="stat-lbl">A interés</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="font-weight:800;"><?= esc($m2) ?> <?= number_format((float) $t['total'], 2) ?></div>
        <div class="stat-lbl">Total recibido<?= $t['mora'] > 0 ? ' (incl. mora)' : '' ?></div>
    </div>
</div>

<!-- Detalle -->
<div class="card">
    <?php if (empty($rows)): ?>
        <p class="card-subtitle">Sin cobros ese día — nada que desglosar.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>N° Crédito</th>
                    <th>Gestor</th>
                    <th>Método</th>
                    <th>Capital</th>
                    <th>Interés</th>
                    <?php if ($t['mora'] > 0): ?><th>Mora</th><?php endif; ?>
                    <th>Total</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td><?= esc(trim(($r['nombres'] ?? '') . ' ' . ($r['apellidos'] ?? ''))) ?></td>
                    <td style="white-space:nowrap;"><?= esc($r['codigo_credito'] ?: '#' . $r['solicitud_id']) ?></td>
                    <td><?= esc(trim(($r['gestor_n'] ?? '') . ' ' . ($r['gestor_a'] ?? '')) ?: 'Oficina') ?></td>
                    <td><?= esc($metodos[$r['metodo']] ?? $r['metodo']) ?></td>
                    <td style="text-align:right; font-weight:700;">
                        <?= esc($m2) ?> <?= number_format((float) $r['capital'], 2) ?><?= !empty($r['estimado']) ? '*' : '' ?>
                    </td>
                    <td style="text-align:right;">
                        <?= esc($m2) ?> <?= number_format((float) $r['interes'], 2) ?><?= !empty($r['estimado']) ? '*' : '' ?>
                    </td>
                    <?php if ($t['mora'] > 0): ?>
                        <td style="text-align:right; color:#B45309;">
                            <?= (float) $r['mora'] > 0 ? esc($m2) . ' ' . number_format((float) $r['mora'], 2) : '—' ?>
                        </td>
                    <?php endif; ?>
                    <td style="text-align:right; font-weight:800;"><?= esc($m2) ?> <?= number_format((float) $r['monto'], 2) ?></td>
                    <td>
                        <span class="badge <?= $r['estado'] === 'APLICADO' ? 'st-ok' : 'st-warn' ?>">
                            <?= esc($lblEstado[$r['estado']] ?? $r['estado']) ?>
                        </span>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
            <tfoot>
                <tr style="font-weight:800; border-top:2px solid #E2E8F0;">
                    <td colspan="4">TOTALES</td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $t['capital'], 2) ?></td>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $t['interes'], 2) ?></td>
                    <?php if ($t['mora'] > 0): ?>
                        <td style="text-align:right; color:#B45309;"><?= esc($m2) ?> <?= number_format((float) $t['mora'], 2) ?></td>
                    <?php endif; ?>
                    <td style="text-align:right;"><?= esc($m2) ?> <?= number_format((float) $t['total'], 2) ?></td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
    </div>
    <p class="card-subtitle" style="margin-top:8px;">
        Los cobros <strong>aplicados</strong> muestran el desglose real de lo que entró a cada cuota
        (proporción capital:interés del plan). Los que siguen <strong>en revisión</strong> se estiman
        por la primera cuota pendiente — al aprobarse el desglose queda exacto.
    </p>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
