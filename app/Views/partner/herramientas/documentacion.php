<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2 = $mon ?? 'C$';
$clsEstado = ['APROBADA' => 'st-ok', 'DESEMBOLSO' => 'st-info', 'ACTIVO' => 'st-ok', 'LIQUIDADO' => 'st-bad'];
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Generar documentación</h3>
        <p class="page-subtitle">
            Plan de pagos, contrato y garantías de solicitudes aprobadas o vigentes
            <?php if ($buscar !== ''): ?>
                · filtrado <a href="<?= base_url('herramientas/documentacion') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-buscar"><?= icon('search', 14) ?> Buscar</button>
    </div>
</div>

<div class="card">
    <?php if (empty($filas)): ?>
        <p class="card-subtitle">Sin solicitudes con documentación disponible<?= $buscar !== '' ? ' con esa búsqueda' : '' ?>.</p>
    <?php else: ?>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Crédito</th>
                    <th>Estado</th>
                    <th>Monto</th>
                    <th>Desembolso</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($filas as $f): ?>
                <tr>
                    <td>
                        <?= esc(trim($f['nombres'] . ' ' . $f['apellidos'])) ?>
                        <?php if (!empty($f['cedula'])): ?>
                            <br><small class="card-subtitle"><?= esc($f['cedula']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;"><?= esc($f['codigo_credito'] ?: '#' . $f['id']) ?></td>
                    <td><span class="badge <?= $clsEstado[$f['estado']] ?? 'badge-soft' ?>"><?= esc($lblEstado[$f['estado']] ?? $f['estado']) ?></span></td>
                    <td style="text-align:right; white-space:nowrap;">
                        <?= esc($m2) ?> <?= number_format((float) ($f['monto_aprobado'] ?? $f['monto']), 2) ?>
                    </td>
                    <td style="white-space:nowrap;"><?= $f['fecha_desembolso'] ? esc(date('d/m/Y', strtotime($f['fecha_desembolso']))) : '—' ?></td>
                    <td style="text-align:right;">
                        <a class="btn btn-primary btn-sm" href="<?= base_url('credito/solicitudes/' . $f['id'] . '/documentos') ?>">
                            <?= icon('file-text', 14) ?> Documentos
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if ($pager): ?>
        <div class="mt-4"><?= $pager->links() ?></div>
    <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Modal: buscar -->
<div class="modal-overlay" id="modal-buscar" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4>Buscar solicitud</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('herramientas/documentacion') ?>">
            <div class="modal-body">
                <div class="form-group form-full">
                    <label>Cliente, cédula o código</label>
                    <input type="text" name="q" value="<?= esc($buscar) ?>" placeholder="Nombre, cédula o código de crédito">
                </div>
            </div>
            <div class="modal-foot">
                <a class="btn" href="<?= base_url('herramientas/documentacion') ?>">Limpiar</a>
                <button type="submit" class="btn btn-primary">Buscar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
