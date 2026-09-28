<?= $this->extend('layouts/partner') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= v_asset('css/portal.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
$cliente  = trim(($s['nombres'] ?? '') . ' ' . ($s['apellidos'] ?? ''));
$freqSel  = old('frecuencia', $s['frecuencia'] ?? 'M');
$freqOps  = ['D' => 'Diario', 'DI' => 'Diario intermitente', 'S' => 'Semanal', 'Q' => 'Quincenal', 'M' => 'Mensual'];
$diasSel  = (int) old('dias_semana', $s['dias_semana'] ?? 3) ?: 3;
$gestorSel = (string) old('asignado_a', $s['asignado_a'] ?? '');
?>

<div class="page-head" style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
        <h3 class="page-title">Editar solicitud #<?= (int) $s['id'] ?></h3>
        <p class="page-subtitle"><?= esc($cliente) ?> · <?= esc($s['codigo'] ?? '—') ?></p>
    </div>
    <a class="btn btn-outline" href="<?= base_url('credito/solicitudes/' . $s['id']) ?>"><?= icon('chevron-left', 15) ?> Volver</a>
</div>

<form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/editar') ?>"
      id="sol-edit-form" data-mon="<?= esc($mon) ?>" data-cliente="<?= esc($cliente) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="sol-split" style="grid-template-columns: 1.4fr 1fr;">

        <!-- ======== Izquierda: formulario ======== -->
        <div>
            <div class="card">
                <h4 class="card-title"><?= icon('user', 16) ?> Cliente</h4>
                <p class="card-subtitle">Titular de la solicitud y gestor asignado (cartera).</p>

                <div class="sol-cliente mt-3">
                    <span class="sol-cliente-ico"><?= icon('user', 22) ?></span>
                    <div>
                        <strong><?= esc($cliente) ?></strong>
                        <p class="sol-cliente-sub"><?= esc($s['codigo'] ?? '—') ?> · <?= esc($s['cedula'] ?? 'Sin cédula') ?></p>
                    </div>
                    <a href="<?= base_url('socios/clientes/' . $s['cliente_id']) ?>" class="btn btn-outline btn-sm sol-cliente-link">
                        <?= icon('external-link', 13) ?> Ver ficha
                    </a>
                </div>

                <div class="form-grid mt-3">
                    <div class="form-group">
                        <label for="asignado_a">Gestor asignado</label>
                        <select id="asignado_a" name="asignado_a">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($gestores as $g): ?>
                                <option value="<?= esc($g['id']) ?>" <?= $gestorSel === (string) $g['id'] ? 'selected' : '' ?>>
                                    <?= esc(trim($g['nombres'] . ' ' . $g['apellidos'])) ?><?= !empty($g['ruta']) ? ' · ' . esc($g['ruta']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">Si cambiás el gestor, la ruta se actualiza a la del nuevo gestor.</small>
                    </div>
                    <div class="form-group">
                        <label for="destino">Destino del crédito</label>
                        <input type="text" id="destino" name="destino" maxlength="255"
                               value="<?= esc(old('destino', $s['destino'])) ?>" placeholder="Ej: Compra de mercadería">
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <h4 class="card-title"><?= icon('percent', 16) ?> Préstamo</h4>
                <p class="card-subtitle">Monto, tasa, plazo y frecuencia — la cuota se recalcula en vivo.</p>
                <div class="form-grid mt-4">
                    <div class="form-group form-full">
                        <label for="monto">Monto (<?= esc($mon) ?>) *</label>
                        <input type="number" id="monto" name="monto" min="1000" step="100" required
                               value="<?= esc(old('monto', $s['monto'])) ?>">
                        <div class="mini-chips" id="monto-chips">
                            <button type="button" class="mini-chip" data-m="1000">1,000</button>
                            <button type="button" class="mini-chip" data-m="5000">5,000</button>
                            <button type="button" class="mini-chip" data-m="10000">10,000</button>
                            <button type="button" class="mini-chip" data-m="20000">20,000</button>
                            <button type="button" class="mini-chip" data-m="50000">50,000</button>
                        </div>
                        <small class="form-hint">Mínimo <?= esc($mon) ?> 1,000.</small>
                    </div>
                    <div class="form-group">
                        <label for="tasa_mensual">Tasa mensual (%)</label>
                        <input type="number" id="tasa_mensual" name="tasa_mensual" min="0" max="100" step="0.01" required
                               value="<?= esc(old('tasa_mensual', $s['tasa_mensual'] ?? $tasaDef)) ?>">
                        <small class="form-hint">Sugerida por empresa: <?= number_format((float) $tasaDef, 2) ?>%.</small>
                    </div>
                    <div class="form-group">
                        <label for="plazo_meses">Plazo (meses)</label>
                        <input type="number" id="plazo_meses" name="plazo_meses" min="1" max="<?= $plazoMax ?>" step="1"
                               value="<?= esc(old('plazo_meses', $s['plazo_meses'])) ?>">
                        <small class="form-hint">Máximo <?= $plazoMax ?> meses.</small>
                    </div>
                    <div class="form-group">
                        <label>Frecuencia de pago</label>
                        <div class="seg" id="sol-freq">
                            <?php foreach ($freqOps as $k => $txt): ?>
                                <input type="radio" name="frecuencia" id="f-<?= $k ?>" value="<?= esc($k) ?>"
                                       <?= $freqSel === $k ? 'checked' : '' ?>>
                                <label for="f-<?= $k ?>"><?= esc($txt) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group" id="sol-dias-wrap" <?= $freqSel === 'DI' ? '' : 'hidden' ?>>
                        <label for="dias_semana">Días de pago por semana</label>
                        <input type="number" id="dias_semana" name="dias_semana" min="1" max="7" step="1"
                               value="<?= esc($diasSel) ?>">
                        <small class="form-hint">Solo para diario intermitente.</small>
                    </div>
                    <div class="form-group">
                        <label for="tipo_calculo">Tipo de cálculo</label>
                        <select id="tipo_calculo" name="tipo_calculo">
                            <?php foreach ($tiposCalc as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= old('tipo_calculo', $s['tipo_calculo'] ?? 'FLAT') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">Flat = tasa × meses sobre el capital prestado.</small>
                    </div>
                    <div class="form-group">
                        <label for="fecha_primer_pago">Primer pago</label>
                        <input type="date" id="fecha_primer_pago" name="fecha_primer_pago"
                               value="<?= esc(old('fecha_primer_pago', $fechaSug)) ?>">
                        <small class="form-hint">Fecha sugerida del primer cobro; el plan definitivo se arma al aprobar.</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- ======== Derecha: resumen en vivo ======== -->
        <div>
            <div class="card" style="position:sticky; top:84px;">
                <h4 class="card-title"><?= icon('file-text', 16) ?> Resumen</h4>
                <div class="calc-result calc-mini" style="margin:14px 0 6px;">
                    <span class="calc-result-label" id="rs-cuota-lbl">Cuota estimada</span>
                    <strong class="calc-result-cuota" id="rs-cuota"><?= esc($mon) ?> 0.00</strong>
                </div>
                <div class="sol-resumen" id="rs-resumen">
                    <div class="sol-row"><span>Cliente</span><strong><?= esc($cliente) ?></strong></div>
                    <div class="sol-row"><span>Gestor</span><strong id="rs-gestor">—</strong></div>
                    <div class="sol-row"><span>Monto</span><strong id="rs-monto">—</strong></div>
                    <div class="sol-row"><span>Tasa mensual</span><strong id="rs-tasa">—</strong></div>
                    <div class="sol-row"><span>Plazo</span><strong id="rs-plazo">—</strong></div>
                    <div class="sol-row"><span>Frecuencia</span><strong id="rs-freq">—</strong></div>
                    <div class="sol-row"><span>Total a pagar</span><strong id="rs-total">—</strong></div>
                    <div class="sol-row"><span>Primer pago</span><strong id="rs-fecha">—</strong></div>
                </div>
                <div class="mini-chips" style="margin-top:14px;">
                    <span class="badge badge-soft">Estado: <?= esc($s['estado'] ?? '—') ?></span>
                </div>
                <div style="display:flex; gap:10px; margin-top:18px;">
                    <button type="submit" class="btn btn-primary" style="flex:1; justify-content:center;">
                        <?= icon('check', 15) ?> Guardar cambios
                    </button>
                </div>
                <p class="form-hint" style="text-align:center; margin-top:10px;">
                    La cuota es una estimación — el plan exacto se genera al aprobar.
                </p>
            </div>
        </div>
    </div>
</form>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= v_asset('js/solicitud-editar.js') ?>"></script>
<?= $this->endSection() ?>

