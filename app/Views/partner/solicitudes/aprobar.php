<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<?php
$cliente = trim(($s['nombres'] ?? '') . ' ' . ($s['apellidos'] ?? ''));
$freqTxt = ($lblFreq[$s['frecuencia']] ?? $s['frecuencia']) . ($s['frecuencia'] === 'DI' ? ' (' . (int) ($s['dias_semana'] ?? 3) . ' días/sem)' : '');
$m       = $metricas;
$monedaJs = esc($mon, 'js');
?>

<?= view('partials/detail_hero', [
    'titulo'    => 'Aprobar solicitud #' . $s['id'] . ' — ' . $cliente,
    'subtitulo' => 'Crédito → Solicitudes → Aprobación',
    'icono'     => 'check-circle',
    'volver'    => '/credito/solicitudes/' . $s['id'],
    'acciones'  => [
        ['nombre' => 'Análisis crediticio', 'url' => '/credito/solicitudes/' . $s['id'] . '/analisis', 'icono' => 'bar-chart-2'],
    ],
]) ?>

<div class="sol-detail">

    <!-- Datos del cliente -->
    <div class="card">
        <h4 class="card-title"><?= icon('user', 16) ?> Datos del cliente</h4>
        <div class="detail-grid mt-3">
            <div><label>Nombre</label><p><strong><?= esc($cliente) ?></strong></p></div>
            <div><label>Código</label><p><?= esc($s['codigo'] ?? '—') ?></p></div>
            <div><label>Cédula</label><p><?= esc($s['cedula'] ?? '—') ?></p></div>
            <div><label>Teléfono</label><p><?= esc($s['telefono'] ?? '—') ?></p></div>
            <div><label>Ingresos declarados /mes</label><p><?= esc($mon) ?> <?= number_format($m['ingresos'], 2) ?></p></div>
            <div><label>Nivel de capacidad</label><p>
                <?php if (!empty($analisis['nivel'])): ?>
                    <span class="badge ana-nivel-<?= strtolower($analisis['nivel']) ?>"><?= esc($nivelLbl[$analisis['nivel']] ?? $analisis['nivel']) ?></span>
                    <span style="font-size:12px; color:var(--text-muted);">(<?= number_format((float) $analisis['ratio'] * 100, 1) ?>% del ingreso)</span>
                <?php else: ?>
                    <a href="<?= base_url('credito/solicitudes/' . $s['id'] . '/analisis') ?>" class="badge badge-soft" style="text-decoration:none;">Sin análisis calculado → ver expediente</a>
                <?php endif; ?>
            </p></div>
        </div>
    </div>

    <!-- Solicitado vs Aprobado -->
    <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/aprobar') ?>" onsubmit="return confirm('¿Aprobar la solicitud con estos valores?');">
        <?= csrf_field() ?>
        <div class="sol-split">

            <!-- Préstamo solicitado (solo lectura) -->
            <div class="card apr-solicitado">
                <h4 class="card-title"><?= icon('file-text', 16) ?> Préstamo solicitado</h4>
                <div class="detail-grid mt-3">
                    <div><label>Monto</label><p><strong><?= esc($mon) ?> <?= number_format((float) $s['monto'], 2) ?></strong></p></div>
                    <div><label>Tasa mensual</label><p><?= number_format((float) ($s['tasa_mensual'] ?? 0), 2) ?>%</p></div>
                    <div><label>Plazo</label><p><?= esc($s['plazo_meses'] ?: '—') ?> <?= $s['plazo_meses'] ? 'meses' : '' ?></p></div>
                    <div><label>Frecuencia</label><p><?= esc($freqTxt) ?></p></div>
                    <div><label>Cuota estimada</label><p><?= esc($mon) ?> <?= number_format($m['cuota'], 2) ?> × <?= (int) $m['pagos'] ?> pagos</p></div>
                    <div><label>Destino</label><p><?= esc($s['destino'] ?? '—') ?></p></div>
                </div>
            </div>

            <!-- Préstamo aprobado (editable — se guarda aparte) -->
            <div class="card apr-aprobado">
                <h4 class="card-title"><?= icon('check-circle', 16) ?> Préstamo aprobado</h4>
                <div class="form-grid mt-3">
                    <div class="form-group">
                        <label for="ap_monto">Monto aprobado (<?= esc($mon) ?>) *</label>
                        <input type="number" id="ap_monto" name="monto_aprobado" min="1" step="0.01" required
                               value="<?= esc(old('monto_aprobado', $s['monto'])) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap_tasa">Tasa mensual (%) *</label>
                        <input type="number" id="ap_tasa" name="tasa_aprobada" min="0" step="0.01" required
                               value="<?= esc(old('tasa_aprobada', $s['tasa_mensual'] ?? 0)) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap_plazo">Plazo (meses, máx <?= (int) $plazoMax ?>) *</label>
                        <input type="number" id="ap_plazo" name="plazo_aprobado" min="1" max="<?= (int) $plazoMax ?>" required
                               value="<?= esc(old('plazo_aprobado', $s['plazo_meses'] ?: $plazoMax)) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap_freq">Frecuencia *</label>
                        <select id="ap_freq" name="frecuencia_aprobada" required>
                            <?php foreach (['D','DI','S','Q','M','P'] as $f): ?>
                                <option value="<?= $f ?>" <?= old('frecuencia_aprobada', $s['frecuencia']) === $f ? 'selected' : '' ?>><?= esc($lblFreq[$f] ?? $f) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group" id="ap_dias_wrap" style="display:<?= old('frecuencia_aprobada', $s['frecuencia']) === 'DI' ? 'block' : 'none' ?>;">
                        <label for="ap_dias">Días de pago/semana (diario intermitente)</label>
                        <input type="number" id="ap_dias" name="dias_semana" min="1" max="7"
                               value="<?= esc(old('dias_semana', $s['dias_semana'] ?? 3)) ?>">
                    </div>
                    <div class="form-group" id="ap_paso_wrap" style="display:none;">
                        <label for="ap_paso">Cada cuántos días paga (personalizado)</label>
                        <input type="number" id="ap_paso" name="paso_dias" min="1" max="365"
                               value="<?= esc(old('paso_dias', $s['paso_dias'] ?? 15)) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap_tipo">Tipo de cálculo</label>
                        <select id="ap_tipo" name="tipo_calculo">
                            <?php foreach ($tiposCalc as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= old('tipo_calculo', $s['tipo_calculo'] ?: ($tenant['tipo_calculo'] ?? 'FRANCES')) === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ap_gracia_m">Meses de gracia (opcional)</label>
                        <input type="number" id="ap_gracia_m" name="gracia_meses" min="0" max="24"
                               value="<?= esc(old('gracia_meses', $s['gracia_meses'] ?? 0)) ?>">
                    </div>
                    <div class="form-group" id="ap_gracia_t_wrap" style="display:none;">
                        <label for="ap_gracia_t">Tipo de gracia</label>
                        <select id="ap_gracia_t" name="gracia_tipo">
                            <option value="TOTAL" <?= old('gracia_tipo', $s['gracia_tipo']) === 'TOTAL' ? 'selected' : '' ?>>Total — se desplaza el plan</option>
                            <option value="INTERES" <?= old('gracia_tipo', $s['gracia_tipo']) === 'INTERES' ? 'selected' : '' ?>>Solo interés — paga interés en gracia</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label for="ap_comision">Comisión al aprobar (<?= esc($mon) ?>, descontada de la entrega)</label>
                        <input type="number" id="ap_comision" name="comision" min="0" step="0.01"
                               value="<?= esc(old('comision', round((float) $s['monto'] * (float) ($tenant['comision_pct'] ?? 0) / 100, 2))) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap_seguro">Seguro al aprobar (<?= esc($mon) ?>, descontado de la entrega)</label>
                        <input type="number" id="ap_seguro" name="seguro" min="0" step="0.01"
                               value="<?= esc(old('seguro', round((float) $s['monto'] * (float) ($tenant['seguro_pct'] ?? 0) / 100, 2))) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap_fecha">Fecha del primer pago *</label>
                        <input type="date" id="ap_fecha" name="fecha_primer_pago" min="<?= date('Y-m-d') ?>" required
                               value="<?= esc(old('fecha_primer_pago', $fechaSug)) ?>">
                    </div>
                    <div class="form-group">
                        <label for="ap_gestor">Gestor asignado</label>
                        <select id="ap_gestor" name="asignado_a">
                            <option value="">Sin asignar</option>
                            <?php foreach ($gestores ?? [] as $g): ?>
                                <option value="<?= (int) $g['id'] ?>" <?= (int) old('asignado_a', $s['asignado_a'] ?? 0) === (int) $g['id'] ? 'selected' : '' ?>>
                                    <?= esc(trim($g['nombres'] . ' ' . $g['apellidos'])) ?><?= !empty($g['ruta']) ? ' — ' . esc($g['ruta']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>
                <div class="apr-cuota">
                    Cuota estimada aprobada: <strong id="ap_cuota">—</strong>
                    <span id="ap_pagos" style="color:var(--text-muted); font-size:12.5px;"></span>
                </div>
            </div>

        </div>

        <div class="card apr-foot">
            <button type="submit" class="btn btn-primary"><?= icon('check', 16) ?> Aprobar solicitud</button>
            <a href="<?= base_url('credito/solicitudes/' . $s['id']) ?>" class="btn btn-outline">Cancelar</a>
        </div>
    </form>

</div>

<script>
(function () {
    var moneda = '<?= $monedaJs ?>';
    var freqPagos = { D: 30, DI: 0, S: 4, Q: 2, M: 1, P: 0 };
    var monto = document.getElementById('ap_monto');
    var tasa  = document.getElementById('ap_tasa');
    var plazo = document.getElementById('ap_plazo');
    var freq  = document.getElementById('ap_freq');
    var dias  = document.getElementById('ap_dias');
    var dWrap = document.getElementById('ap_dias_wrap');
    var paso  = document.getElementById('ap_paso');
    var pWrap = document.getElementById('ap_paso_wrap');
    var graciaM  = document.getElementById('ap_gracia_m');
    var gWrap    = document.getElementById('ap_gracia_t_wrap');
    var tipo = document.getElementById('ap_tipo');
    var outC  = document.getElementById('ap_cuota');
    var outP  = document.getElementById('ap_pagos');

    function recalc() {
        dWrap.style.display = freq.value === 'DI' ? 'block' : 'none';
        pWrap.style.display = freq.value === 'P' ? 'block' : 'none';
        gWrap.style.display = (parseInt(graciaM.value) || 0) > 0 ? 'block' : 'none';
        var pxm = freq.value === 'DI' ? 4 * Math.max(1, parseInt(dias.value) || 3)
              : freq.value === 'P' ? 30 / Math.max(1, parseInt(paso.value) || 15)
              : (freqPagos[freq.value] || 1);
        var iP  = (parseFloat(tasa.value) || 0) / 100 / Math.max(pxm, 0.01);
        var n   = Math.max(1, Math.round((parseInt(plazo.value) || 1) * pxm));
        var M   = parseFloat(monto.value) || 0;
        var cuota;
        if (tipo.value === 'ALEMAN') {
            cuota = M / n + M * iP;                       // primera cuota (la más alta)
        } else if (tipo.value === 'FLAT' || tipo.value === 'ANTICIPADO') {
            cuota = M / n + (tipo.value === 'FLAT' ? M * iP : 0);
        } else {
            cuota = iP > 0 ? M * iP * Math.pow(1 + iP, n) / (Math.pow(1 + iP, n) - 1) : M / n;
        }
        outC.textContent = cuota > 0 ? moneda + ' ' + cuota.toFixed(2).replace(/\B(?=(\d{3})+(?!\d))/g, ',') : '—';
        outP.textContent = cuota > 0 ? '× ' + n + ' pagos' + (tipo.value === 'ALEMAN' ? ' (decreciente)' : '') : '';
    }

    [monto, tasa, plazo, freq, dias, paso, graciaM, tipo].forEach(function (el) {
        el.addEventListener('input', recalc);
        el.addEventListener('change', recalc);
    });
    recalc();
})();
</script>

<?= $this->endSection() ?>
