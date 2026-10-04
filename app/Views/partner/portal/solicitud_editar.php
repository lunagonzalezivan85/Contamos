<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>
<?php $mon = $tenant['moneda'] ?? 'C$'; ?>

<div class="app-header card portal-card">
    <a href="<?= base_url($slug . '/portal/actividad') ?>" class="app-back"><?= icon('chevron-left', 20) ?></a>
    <div>
        <h3>Editar solicitud #<?= (int) $s['id'] ?></h3>
        <p class="app-rol">Solo mientras esté creada o en revisión</p>
    </div>
</div>

<?php if (session('error')): ?>
    <div class="card portal-card"><p class="field-error"><?= esc(session('error')) ?></p></div>
<?php endif; ?>

<?php if (!empty($s['nota_revision'])): ?>
    <div class="card portal-card">
        <h4 class="card-title"><?= icon('message-square', 16) ?> Observación de oficina</h4>
        <p class="geo-hint" style="margin:0;"><?= esc($s['nota_revision']) ?></p>
    </div>
<?php endif; ?>

<div class="card portal-card">
    <h4 class="card-title"><?= icon('percent', 16) ?> Préstamo</h4>

    <form method="post" action="<?= base_url($slug . '/portal/solicitud/' . $s['id'] . '/editar') ?>">
        <?= csrf_field() ?>

        <div class="calc-field">
            <div class="calc-field-head">
                <label for="monto">Monto del préstamo (<?= esc($mon) ?>)</label>
            </div>
            <input type="number" class="calc-num" id="monto" name="monto" min="1000" step="500"
                   inputmode="decimal" value="<?= esc(old('monto', $s['monto'])) ?>" required>
        </div>

        <?php $tasaMax = (float) ($tenant['tasa_interes'] ?? 0); ?>
        <div class="calc-field">
            <div class="calc-field-head"><label for="tasa_mensual">Tasa mensual (%)</label></div>
            <input type="number" class="calc-num" id="tasa_mensual" name="tasa_mensual"
                   min="0" max="<?= esc(number_format($tasaMax, 2, '.', '')) ?>" step="0.25" inputmode="decimal"
                   value="<?= esc(old('tasa_mensual', $s['tasa_mensual'] ?? $tasaMax)) ?>">
            <p class="geo-hint">Podés bajarla — el máximo es la tasa de la empresa (<?= number_format($tasaMax, 2) ?>%).</p>
        </div>

        <div class="calc-field">
            <div class="calc-field-head"><label for="plazo_meses">Plazo (meses)</label></div>
            <input type="number" class="calc-num" id="plazo_meses" name="plazo_meses" min="0.5"
                   max="<?= (int) ($tenant['plazo_meses_max'] ?? 60) ?>" step="0.5" inputmode="decimal"
                   value="<?= esc(old('plazo_meses', $s['plazo_meses'])) ?>">
        </div>

        <div class="form-group">
            <label>Frecuencia de pago</label>
            <?php $freqs = ['D' => 'Diario', 'DI' => 'Diario intermitente', 'S' => 'Semanal', 'Q' => 'Quincenal', 'M' => 'Mensual']; ?>
            <select class="in-plain" name="frecuencia" id="frecuencia">
                <?php foreach ($freqs as $k => $lbl): ?>
                    <option value="<?= $k ?>" <?= old('frecuencia', $s['frecuencia']) === $k ? 'selected' : '' ?>><?= $lbl ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="calc-dias" id="sol-dias-wrap" <?= old('frecuencia', $s['frecuencia']) === 'DI' ? '' : 'hidden' ?>>
            <label for="dias_semana">Días de pago por semana (diario intermitente)</label>
            <input type="number" class="calc-num" id="dias_semana" name="dias_semana" min="1" max="7"
                   step="1" inputmode="numeric" value="<?= esc(old('dias_semana', $s['dias_semana'] ?? 3)) ?>">
        </div>

        <div class="form-group">
            <label for="fecha_primer_pago">Primer pago propuesto</label>
            <input type="date" id="fecha_primer_pago" name="fecha_primer_pago" class="in-plain"
                   value="<?= esc(old('fecha_primer_pago', $s['fecha_primer_pago'])) ?>">
        </div>

        <div class="form-group">
            <label for="destino">Destino del crédito</label>
            <input type="text" id="destino" name="destino" maxlength="140"
                   value="<?= esc(old('destino', $s['destino'])) ?>" placeholder="Ej: Compra de mercadería">
        </div>

        <button type="submit" class="btn btn-primary btn-block"><?= icon('save', 15) ?> Guardar cambios</button>
    </form>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
// Muestra/oculta "días por semana" según la frecuencia elegida
document.getElementById('frecuencia').addEventListener('change', function () {
    document.getElementById('sol-dias-wrap').hidden = this.value !== 'DI';
});
</script>
<?= $this->endSection() ?>
