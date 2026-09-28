<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<div class="list-head">
    <div>
        <h3 class="page-title">Arqueo de caja</h3>
        <p class="page-subtitle">Cierre diario del efectivo del gestor — lo cobrado menos lo desembolsado debe igualar lo contado</p>
    </div>
</div>

<!-- Selector día + gestor -->
<form method="get" action="<?= base_url('finanzas/arqueo') ?>" class="sol-filters">
    <div class="sol-filters-row">
        <select name="empleado">
            <?php foreach ($gestores as $g): ?>
                <option value="<?= (int) $g['id'] ?>" <?= $gestor && (int) $gestor['id'] === (int) $g['id'] ? 'selected' : '' ?>>
                    <?= esc(trim($g['nombres'] . ' ' . $g['apellidos'])) ?><?= !empty($g['carnet']) ? ' — ' . esc($g['carnet']) : '' ?>
                </option>
            <?php endforeach; ?>
        </select>
        <input type="date" name="fecha" value="<?= esc($fecha) ?>" max="<?= date('Y-m-d') ?>">
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('search', 15) ?> Ver día</button>
    </div>
</form>

<?php if ($resumen): ?>
<?php $arq = $resumen['arqueo']; ?>

<!-- Resumen del día -->
<div class="chips">
    <div class="chip chip-stat">
        <span class="chip-icon"><?= icon('archive', 16) ?></span>
        <span class="chip-name">Saldo inicial</span>
        <span class="chip-val"><?= $mon ?> <?= number_format($resumen['inicial'], 2) ?></span>
    </div>
    <div class="chip chip-stat">
        <span class="chip-icon"><?= icon('trending-up', 16) ?></span>
        <span class="chip-name">Cobros</span>
        <span class="chip-val">+ <?= $mon ?> <?= number_format($resumen['cobros'], 2) ?></span>
    </div>
    <div class="chip chip-stat">
        <span class="chip-icon"><?= icon('trending-down', 16) ?></span>
        <span class="chip-name">Desembolsos</span>
        <span class="chip-val">− <?= $mon ?> <?= number_format($resumen['desembolsos'], 2) ?></span>
    </div>
    <div class="chip chip-stat">
        <span class="chip-icon"><?= icon('clipboard', 16) ?></span>
        <span class="chip-name">Esperado</span>
        <span class="chip-val"><?= $mon ?> <?= number_format($resumen['esperado'], 2) ?></span>
    </div>
</div>

<!-- Movimientos -->
<div class="card">
    <h4 class="card-title">Cobros del día (pagos aplicados)</h4>
    <?php if (empty($resumen['mov']['cobros'])): ?>
        <p class="card-subtitle">Sin cobros aplicados el <?= esc(date('d/m/Y', strtotime($fecha))) ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Hora</th><th>Cliente</th><th>Crédito</th><th>Método</th><th>Monto</th></tr>
                </thead>
                <tbody>
                <?php foreach ($resumen['mov']['cobros'] as $p): ?>
                    <tr>
                        <td><?= esc(date('H:i', strtotime($p['fecha_hora']))) ?></td>
                        <td><?= esc(trim($p['nombres'] . ' ' . $p['apellidos'])) ?></td>
                        <td><a href="<?= base_url('creditos/' . $p['solicitud_id']) ?>"><?= esc($p['codigo_credito'] ?? '#' . $p['solicitud_id']) ?></a></td>
                        <td><?= esc($p['metodo']) ?></td>
                        <td><strong>+ <?= $mon ?> <?= number_format((float) $p['monto'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h4 class="card-title">Desembolsos entregados</h4>
    <?php if (empty($resumen['mov']['desembolsos'])): ?>
        <p class="card-subtitle">Sin desembolsos entregados el <?= esc(date('d/m/Y', strtotime($fecha))) ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Hora</th><th>Cliente</th><th>Crédito</th><th>Monto</th></tr>
                </thead>
                <tbody>
                <?php foreach ($resumen['mov']['desembolsos'] as $d): ?>
                    <tr>
                        <td><?= esc($d['fecha_entrega'] ? date('H:i', strtotime($d['fecha_entrega'])) : '—') ?></td>
                        <td><?= esc(trim($d['nombres'] . ' ' . $d['apellidos'])) ?></td>
                        <td><a href="<?= base_url('creditos/' . $d['id']) ?>"><?= esc($d['codigo_credito'] ?? '#' . $d['id']) ?></a></td>
                        <td><strong>− <?= $mon ?> <?= number_format((float) $d['monto_aprobado'], 2) ?></strong></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Cierre -->
<div class="card">
    <h4 class="card-title"><?= icon('clipboard', 16) ?> Cierre del arqueo</h4>
    <?php if ($arq): ?>
        <p class="card-subtitle">Ya cerrado — volver a guardar recalcula con los movimientos actuales.</p>
    <?php endif; ?>
    <form method="post" action="<?= base_url('finanzas/arqueo') ?>" style="max-width:460px;">
        <?= csrf_field() ?>
        <input type="hidden" name="empleado_id" value="<?= (int) $gestor['id'] ?>">
        <input type="hidden" name="fecha" value="<?= esc($fecha) ?>">

        <div class="form-group">
            <label>Efectivo contado en campo (<?= $mon ?>)</label>
            <input type="number" name="contado" id="arq-contado" step="0.01" min="0" required
                   value="<?= esc(old('contado', $arq['contado'] ?? $resumen['esperado'])) ?>">
        </div>
        <div class="form-group">
            <label>Diferencia</label>
            <input type="text" id="arq-dif" readonly value="" style="font-weight:700;">
        </div>
        <div class="form-group">
            <label>Observación</label>
            <input type="text" name="observacion" maxlength="255"
                   value="<?= esc(old('observacion', $arq['observacion'] ?? '')) ?>"
                   placeholder="Motivo de la diferencia, billetes dañados, etc.">
        </div>
        <button class="btn btn-primary"><?= icon('check', 15) ?> Cerrar arqueo</button>
        <?php if ($arq): ?>
            <span class="badge <?= $arq['estado'] === 'CUADRADO' ? 'st-ok' : 'st-bad' ?>" style="margin-left:8px;">
                <?= esc($lblEstado[$arq['estado']] ?? $arq['estado']) ?> — dif. <?= $mon ?> <?= number_format((float) $arq['diferencia'], 2) ?>
            </span>
        <?php endif; ?>
    </form>
</div>
<?php elseif ($gestores): ?>
    <p class="card-subtitle">Gestor no válido para este negocio.</p>
<?php else: ?>
    <div class="card">
        <p class="card-subtitle">No hay gestores activos. Registre empleados en Socios → Empleados.</p>
    </div>
<?php endif; ?>

<!-- Historial -->
<div class="card">
    <h4 class="card-title">Historial de arqueos</h4>
    <?php if (empty($historial)): ?>
        <p class="card-subtitle">Aún no hay arqueos cerrados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Fecha</th><th>Gestor</th><th>Inicial</th><th>Cobros</th><th>Desembolsos</th><th>Esperado</th><th>Contado</th><th>Diferencia</th><th>Estado</th><th>Cerró</th></tr>
                </thead>
                <tbody>
                <?php foreach ($historial as $a): ?>
                    <tr>
                        <td><?= esc(date('d/m/Y', strtotime($a['fecha']))) ?></td>
                        <td><?= esc(trim($a['nombres'] . ' ' . $a['apellidos'])) ?></td>
                        <td><?= $mon ?> <?= number_format((float) $a['saldo_inicial'], 2) ?></td>
                        <td>+ <?= number_format((float) $a['cobros'], 2) ?></td>
                        <td>− <?= number_format((float) $a['desembolsos'], 2) ?></td>
                        <td><?= number_format((float) $a['esperado'], 2) ?></td>
                        <td><strong><?= number_format((float) $a['contado'], 2) ?></strong></td>
                        <td><?= number_format((float) $a['diferencia'], 2) ?></td>
                        <td><span class="badge <?= $a['estado'] === 'CUADRADO' ? 'st-ok' : 'st-bad' ?>"><?= esc($lblEstado[$a['estado']] ?? $a['estado']) ?></span></td>
                        <td><?= esc($a['resuelto_por_user'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var contado  = document.getElementById('arq-contado');
    var dif      = document.getElementById('arq-dif');
    var esperado = <?= json_encode($resumen['esperado'] ?? 0) ?>;
    if (!contado || !dif) return;
    function calc() {
        var d = (parseFloat(contado.value) || 0) - esperado;
        dif.value = (d >= 0 ? '+ ' : '− ') + Math.abs(d).toFixed(2);
        dif.style.color = Math.abs(d) < 0.01 ? 'var(--success,#0D8A64)' : 'var(--danger,#E5484D)';
    }
    contado.addEventListener('input', calc);
    calc();
})();
</script>
<?= $this->endSection() ?>
