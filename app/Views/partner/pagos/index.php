<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<?php
// Helpers de celda - mismo formato en las tres tablas
$clienteNom = fn($p) => esc(trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')));
$cobroPor   = fn($p) => esc(trim(($p['cob_nombres'] ?? '') . ' ' . ($p['cob_apellidos'] ?? '')) ?: 'Oficina');
$creditoLnk = fn($p) => '<a href="' . base_url('creditos/' . $p['solicitud_id']) . '">'
    . esc($p['codigo_credito'] ?? '#' . $p['solicitud_id']) . '</a>';

// Form de acción inline (aprobar / rechazar / cumplir promesa)
$pagoBtn = function (int $id, string $accion, string $confirm, string $cls, string $ico, string $lbl): string {
    return '<form method="post" action="' . base_url('pagos/' . $id . '/' . $accion) . '"'
        . ' onsubmit="return confirm(\'' . esc($confirm, 'js') . '\');">'
        . csrf_field()
        . '<button class="btn ' . $cls . ' btn-sm">' . icon($ico, 14) . ' ' . esc($lbl) . '</button>'
        . '</form>';
};

$stats = [
    ['En revisión',      $metricas['revision']],
    ['Aplicados',        $metricas['aplicados']],
    ['Reversiones',      $metricas['revertidos']],
    ['Promesas de pago', $metricas['promesas']],
];
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Pagos</h3>
        <p class="page-subtitle">
            <?= count($revision) ?> en revisión · todo pago se aplica a las cuotas al aprobarse
            <?php if ($filtros['desde'] || $filtros['hasta']): ?>
                · filtrado <?= esc($filtros['desde'] ?: '.') ?> → <?= esc($filtros['hasta'] ?: '.') ?>
                <a href="<?= base_url('pagos') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Filtrar</button>
        <?php if ($puede_registrar): ?>
            <button type="button" class="btn btn-outline btn-sm" data-modal="modal-promesa"><?= icon('clock', 14) ?> Promesa</button>
            <button type="button" class="btn btn-primary btn-sm" data-modal="modal-pago"><?= icon('plus', 14) ?> Registrar pago</button>
        <?php endif; ?>
    </div>
</div>

<!-- Stat cards -->
<div class="stats-grid">
    <?php foreach ($stats as [$lbl, $m]): ?>
        <div class="stat-card">
            <div class="stat-num"><?= (int) $m['n'] ?></div>
            <div class="stat-lbl"><?= esc($lbl) ?> · <?= esc($m2) ?> <?= number_format((float) $m['monto'], 2) ?></div>
        </div>
    <?php endforeach; ?>
</div>

<!-- Tabs: Revisión / Aplicados -->
<div class="tabs" id="persona-tabs">
    <button type="button" class="tab-btn <?= $tab === 'revision' ? 'on' : '' ?>" data-tab="revision">
        <?= icon('clock', 15) ?> Revisión (<?= count($revision) + count($promesas ?? []) ?>)
    </button>
    <button type="button" class="tab-btn <?= $tab === 'aplicados' ? 'on' : '' ?>" data-tab="aplicados">
        <?= icon('check-circle', 15) ?> Aplicados
    </button>
</div>

<div class="tab-panel <?= $tab === 'revision' ? 'on' : '' ?>" data-panel="revision">

<!-- Bandeja de revisión -->
<div class="card">
    <h4 class="card-title">En revisión</h4>
    <?php if (empty($revision)): ?>
        <p class="card-subtitle">No hay pagos pendientes de aprobación.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Fecha</th><th>Cliente</th><th>Crédito</th><th>Cobró</th><th>Método</th><th>Monto</th><th></th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($revision as $p): ?>
                    <tr>
                        <td><?= esc(date('d/m/Y H:i', strtotime($p['fecha_hora']))) ?></td>
                        <td><?= $clienteNom($p) ?></td>
                        <td><?= $creditoLnk($p) ?></td>
                        <td><?= $cobroPor($p) ?></td>
                        <td><?= esc($metodos[$p['metodo']] ?? $p['metodo']) ?></td>
                        <td>
                            <strong><?= esc($m2) ?> <?= number_format((float) $p['monto'], 2) ?></strong>
                            <?php if ((float) $p['monto'] < 0): ?><span class="badge st-bad">Reversión</span><?php endif; ?>
                        </td>
                        <td>
                            <?php if ($puede_aprobar): ?>
                                <div style="display:flex; gap:6px;">
                                    <?= $pagoBtn((int) $p['id'], 'aprobar', '¿Aprobar y aplicar ' . $m2 . ' ' . number_format((float) $p['monto'], 2) . ' a las cuotas?', 'btn-primary', 'check', 'Aprobar') ?>
                                    <?= $pagoBtn((int) $p['id'], 'rechazar', '¿Rechazar este pago? No se aplicará a las cuotas.', 'btn-outline', 'x', 'Rechazar') ?>
                                </div>
                            <?php else: ?>
                                <span class="badge st-warn">En revisión</span>
                            <?php endif; ?>
                        </td>
                        <td>
                            <a href="<?= base_url('pagos/' . $p['id'] . '/recibo') ?>" target="_blank"
                               class="btn btn-outline btn-sm" title="Imprimir recibo"><?= icon('printer', 14) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Promesas de pago - compromiso del cliente, se cobra cuando pague -->
<?php if (!empty($promesas)): ?>
<div class="card">
    <h4 class="card-title"><?= icon('clock', 16) ?> Promesas de pago</h4>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr><th>Fecha prometida</th><th>Cliente</th><th>Crédito</th><th>Registró</th><th>Monto</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($promesas as $p):
                $vencida = substr((string) $p['fecha_hora'], 0, 10) < date('Y-m-d'); ?>
                <tr class="<?= $vencida ? 'row-vencida' : '' ?>">
                    <td>
                        <?= esc(date('d/m/Y', strtotime($p['fecha_hora']))) ?>
                        <?php if ($vencida): ?><span class="badge sol-badge-rechazada">Vencida</span><?php endif; ?>
                    </td>
                    <td><?= $clienteNom($p) ?></td>
                    <td><?= $creditoLnk($p) ?></td>
                    <td><?= $cobroPor($p) ?></td>
                    <td><strong><?= esc($m2) ?> <?= number_format((float) $p['monto'], 2) ?></strong></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <?php if ($puede_registrar): ?>
                                <?= $pagoBtn((int) $p['id'], 'cumplio', '¿El cliente pagó la promesa? El cobro pasará a revisión.', 'btn-primary', 'check', 'Cobró') ?>
                            <?php endif; ?>
                            <?php if ($puede_aprobar): ?>
                                <?= $pagoBtn((int) $p['id'], 'rechazar', '¿Marcar la promesa como no cumplida?', 'btn-outline', 'x', 'No cumplió') ?>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>
<?php endif; ?>

</div><!-- /panel revision -->

<div class="tab-panel <?= $tab === 'aplicados' ? 'on' : '' ?>" data-panel="aplicados">

<!-- Pagos resueltos: aplicados, rechazados y revertidos -->
<div class="card">
    <h4 class="card-title">Aplicados y resueltos</h4>
    <?php if (empty($recientes)): ?>
        <p class="card-subtitle">Sin pagos resueltos aún.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Fecha</th><th>Cliente</th><th>Crédito</th><th>Cobró</th><th>Monto</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($recientes as $p): ?>
                    <tr>
                        <td><?= esc(date('d/m/Y H:i', strtotime($p['fecha_hora']))) ?></td>
                        <td><?= $clienteNom($p) ?></td>
                        <td><?= $creditoLnk($p) ?></td>
                        <td><?= $cobroPor($p) ?></td>
                        <td><?= esc($m2) ?> <?= number_format((float) $p['monto'], 2) ?></td>
                        <td><span class="badge sol-badge-<?= strtolower($p['estado']) ?>"><?= esc($lblEstado[$p['estado']] ?? $p['estado']) ?></span></td>
                        <td style="white-space:nowrap;">
                            <?php if ($p['estado'] === 'APLICADO' && (float) $p['monto'] > 0): ?>
                                <button type="button" class="btn btn-outline btn-sm" title="Enviar recibo"
                                        data-modal="modal-recibo"
                                        data-recibo="<?= esc(json_encode([
                                            'num'      => \App\Models\PagoModel::reciboCode($p, session('tenant_name') ?? ''),
                                            'cliente'  => trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? '')),
                                            'credito'  => $p['codigo_credito'] ?? '#' . $p['solicitud_id'],
                                            'monto'    => number_format((float) $p['monto'], 2),
                                            'fecha'    => date('d/m/Y h:i a', strtotime($p['fecha_hora'])),
                                            'metodo'   => $metodos[$p['metodo']] ?? $p['metodo'],
                                            'estado'   => $lblEstado[$p['estado']] ?? $p['estado'],
                                            'revision' => false,
                                            'url'      => base_url('pagos/' . $p['id'] . '/recibo'),
                                        ]), 'attr') ?>">
                                    <?= icon('send', 14) ?>
                                </button>
                                <a class="btn btn-outline btn-sm" title="Imprimir recibo" target="_blank"
                                   href="<?= base_url('pagos/' . $p['id'] . '/recibo?print=1') ?>">
                                    <?= icon('printer', 14) ?>
                                </a>
                            <?php endif; ?>
                            <?php if (!empty($puede_revertir) && $p['estado'] === 'APLICADO' && (float) $p['monto'] > 0): ?>
                                <button type="button" class="btn btn-outline btn-sm"
                                        data-modal="modal-revertir"
                                        data-url="<?= base_url('pagos/' . $p['id'] . '/revertir') ?>"
                                        data-desc="<?= esc($clienteNom($p) . ' · ' . $m2 . ' ' . number_format((float) $p['monto'], 2)) ?>">
                                    <?= icon('rotate-ccw', 14) ?> Revertir
                                </button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

</div><!-- /panel aplicados -->

<!-- Modal: revertir pago - genera contra-pago negativo con observación -->
<div class="modal-overlay" id="modal-revertir" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('rotate-ccw', 17) ?> Revertir pago</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" id="rev-form" action="">
            <?= csrf_field() ?>
            <div class="modal-body">
                <p class="card-subtitle" style="grid-column: 1 / -1; margin: 0;" id="rev-desc"></p>
                <p class="card-subtitle" style="grid-column: 1 / -1; margin: 0;">
                    Se genera un contra-pago <strong>en negativo</strong> que queda en revisión.
                    Al aprobarse, se desaplica de las cuotas y el pago original queda Revertido.
                </p>
                <div class="form-group form-full">
                    <label>Motivo de la reversión *</label>
                    <input type="text" name="observacion" id="rev-obs" maxlength="255" required
                           placeholder="Ej: cobro duplicado, monto incorrecto.">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('rotate-ccw', 14) ?> Revertir pago</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: filtrar por rango de fechas -->
<div class="modal-overlay" id="modal-filtro" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('search', 17) ?> Filtrar pagos</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('pagos') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= esc($filtros['desde']) ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= esc($filtros['hasta']) ?>">
                </div>
                <p class="card-subtitle">En revisión se filtra por fecha del cobro; resueltos por fecha de aprobación.</p>
            </div>
            <div class="modal-foot">
                <a href="<?= base_url('pagos') ?>" class="btn">Limpiar</a>
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modales: registrar pago / promesa (mismo formulario, POST /pagos/registrar) -->
<?php if ($puede_registrar): ?>
    <?= view('partner/pagos/_modal_abono', ['esPromesa' => false, 'creditos' => $creditos]) ?>
    <?= view('partner/pagos/_modal_abono', ['esPromesa' => true,  'creditos' => $creditos]) ?>
<?php endif; ?>

<!-- Modal: voucher del recibo (enviar/imprimir) -->
<div class="modal-overlay" id="modal-recibo" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('file-text', 17) ?> Recibo</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <div class="modal-body" style="display:block;">
            <div class="rec-preview">
                <div class="rec-head">
                    <b id="rec-emp"><?= esc(session('tenant_name') ?? '') ?></b>
                    <small>RECIBO DE PAGO</small>
                    <b class="rec-num" id="rec-num"></b>
                </div>
                <div class="rec-rows">
                    <div><span>Cliente</span><b id="rec-cliente"></b></div>
                    <div><span>Crédito</span><b id="rec-credito"></b></div>
                    <div><span>Fecha</span><b id="rec-fecha"></b></div>
                    <div><span>Método</span><b id="rec-metodo"></b></div>
                    <div><span>Estado</span><b id="rec-estado"></b></div>
                </div>
                <div class="rec-monto"><?= esc($m2) ?> <span id="rec-monto"></span></div>
                <div class="rec-nota" id="rec-nota" hidden>
                    PAGO EN REVISIÓN - este comprobante no confirma el abono. Se aplica al
                    plan de cuotas cuando oficina lo valide en cuentas bancarias o caja.
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" data-close>Cerrar</button>
            <button type="button" class="btn btn-outline" id="rec-imprimir"><?= icon('printer', 14) ?> Imprimir</button>
            <button type="button" class="btn btn-primary" id="rec-compartir"><?= icon('send', 14) ?> Compartir</button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var fmt = function (v) { return '<?= esc($m2,'js') ?> ' + v.toLocaleString('es-NI', {minimumFractionDigits: 2, maximumFractionDigits: 2}); };

    // Sugerencia de monto por crédito: pendiente de la próxima cuota,
    // o la cuota del plan si no hay pendiente (abono libre).
    // Se usa en ambos modales: registrar pago y promesa.
    function wire(selId, montoId, cuotaId, chipId) {
        var sel   = document.getElementById(selId);
        var monto = document.getElementById(montoId);
        var cuota = document.getElementById(cuotaId);
        var chip  = document.getElementById(chipId);
        if (!sel) return;

        sel.addEventListener('change', function () {
            var op   = sel.options[sel.selectedIndex];
            var pend = parseFloat(op && op.dataset.pend) || 0;
            var sug  = parseFloat(op && op.dataset.sugerido) || pend;
            if (cuota) cuota.value = (op && op.dataset.cuota) || '';
            if (sug > 0) {
                monto.value = sug.toFixed(2);
                chip.hidden = false;
                chip.textContent = (pend > 0 ? 'Cuota pendiente ' : 'Cuota del plan ') + fmt(sug);
                chip.dataset.val = sug;
            } else {
                monto.value = '';
                chip.hidden = true;
            }
        });

        chip.addEventListener('click', function () {
            var v = parseFloat(chip.dataset.val) || 0;
            if (v > 0) monto.value = v.toFixed(2);
            monto.focus();
        });
    }

    wire('pago-credito', 'pago-monto', 'pago-cuota', 'pago-chip');
    wire('prom-credito', 'prom-monto', 'prom-cuota', 'prom-chip');

    // Modal revertir: action = /pagos/{id}/revertir + descripción del pago
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-modal="modal-revertir"]');
        if (!btn) return;
        var form = document.getElementById('rev-form');
        form.action = btn.dataset.url;
        document.getElementById('rev-desc').textContent = 'Pago: ' + btn.dataset.desc;
        document.getElementById('rev-obs').value = '';
    });
})();
</script>
<?= $this->endSection() ?>
