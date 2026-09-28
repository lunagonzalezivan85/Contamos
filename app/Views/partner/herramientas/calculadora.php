<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? 'C$'; ?>

<div class="list-head">
    <div>
        <h3 class="page-title">Calculadora de cuotas</h3>
        <p class="page-subtitle">Simulación de cuota por tipo de cobro — no guarda nada, solo calcula.</p>
    </div>
</div>

<div class="calc-split">

    <!-- Parámetros -->
    <div class="card">
        <h4 class="card-title">Parámetros</h4>
        <p class="card-subtitle">Ajustá el monto, plazo y tasa.</p>
        <div class="calc-inner">
            <div class="form-group form-full">
                <label>Monto del crédito</label>
                <input type="number" id="c-monto" step="100" min="0" value="10000">
            </div>
            <div class="form-group">
                <label>Plazo (meses)</label>
                <input type="number" id="c-plazo" min="1" max="<?= (int) $plazoMax ?>" value="6">
            </div>
            <div class="form-group">
                <label>Cuotas por mes</label>
                <select id="c-frec">
                    <option value="1">1 — Mensual</option>
                    <option value="2">2 — Quincenal</option>
                    <option value="4" selected>4 — Semanal</option>
                    <option value="30">30 — Diaria</option>
                </select>
            </div>
            <div class="form-group">
                <label>Tipo de cobro</label>
                <select id="c-tipo">
                    <?php foreach (['FRANCES' => 'Francés (cuota fija)', 'FLAT' => 'Flat (interés fijo)', 'ALEMAN' => 'Alemán (cuota decreciente)', 'ANTICIPADO' => 'Anticipado (interés por adelantado)'] as $tv => $tl): ?>
                        <option value="<?= $tv ?>" <?= ($tipo ?? 'FLAT') === $tv ? 'selected' : '' ?>><?= $tl ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group form-full">
                <label>Tasa mensual (%)</label>
                <input type="number" id="c-tasa" step="0.1" min="0" value="<?= esc(number_format($tasa, 2, '.', '')) ?>">
            </div>
        </div>
    </div>

    <!-- Resultado -->
    <div class="card">
        <h4 class="card-title">Resultado</h4>
        <p class="card-subtitle">Cuota por pago según frecuencia elegida.</p>

        <div class="calc-inner">
            <div class="stat-card" style="grid-column:1/-1;">
                <div class="stat-num" style="font-size:2rem; color:var(--primary-dark);" id="r-cuota"><?= esc($m2) ?> 0.00</div>
                <div class="stat-lbl" id="r-cuota-lbl">Cuota semanal</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" id="r-total">—</div>
                <div class="stat-lbl">Total a pagar</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" id="r-interes">—</div>
                <div class="stat-lbl">Total intereses</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" id="r-n">—</div>
                <div class="stat-lbl">N.º de cuotas</div>
            </div>
            <div class="stat-card">
                <div class="stat-num" id="r-tasa-ef">—</div>
                <div class="stat-lbl">Tasa por cuota</div>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const mon   = '<?= esc($m2) ?>';
    const monto = document.getElementById('c-monto');
    const plazo = document.getElementById('c-plazo');
    const frec  = document.getElementById('c-frec');
    const tasa  = document.getElementById('c-tasa');
    const tipo  = document.getElementById('c-tipo');
    const frecLbl = { '1': 'mensual', '2': 'quincenal', '4': 'semanal', '30': 'diaria' };
    const tipoLbl = { FRANCES: 'Francés', FLAT: 'Flat', ALEMAN: 'Alemán', ANTICIPADO: 'Anticipado' };

    function fmt(v) { return mon + ' ' + v.toLocaleString('en-US', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    function calc() {
        const P  = parseFloat(monto.value) || 0;
        const pm = parseInt(plazo.value) || 0;
        const f  = parseInt(frec.value) || 4;
        const tm = (parseFloat(tasa.value) || 0) / 100;
        const n  = pm * f;                       // número de pagos
        const i  = tm / f;                       // tasa por periodo

        let cuota = 0, intTot = 0;
        if (P > 0 && n > 0) {
            switch (tipo.value) {
                case 'FLAT':       cuota = P / n + P * i;  intTot = P * i * n; break;
                case 'ALEMAN':     cuota = P / n + P * i;  intTot = i * P * (n + 1) / 2; break; // 1ra cuota (la mayor)
                case 'ANTICIPADO': cuota = P / n;          intTot = P * i * n; break;   // solo capital, interés por adelantado
                default: /* FRANCES */
                    cuota  = i > 0 ? P * i / (1 - Math.pow(1 + i, -n)) : P / n;
                    intTot = cuota * n - P;
            }
        }
        const total = P + intTot;
        document.getElementById('r-cuota').textContent = fmt(cuota);
        document.getElementById('r-cuota-lbl').textContent =
            (tipo.value === 'ALEMAN' ? '1ra cuota' : 'Cuota') + ' ' + (frecLbl[frec.value] || '') +
            ' · ' + (tipoLbl[tipo.value] || tipo.value) +
            (tipo.value === 'ANTICIPADO' ? ' (sin interés)' : '');
        document.getElementById('r-total').textContent = fmt(total);
        document.getElementById('r-interes').textContent = fmt(Math.max(0, intTot));
        document.getElementById('r-n').textContent = n;
        document.getElementById('r-tasa-ef').textContent = (i * 100).toFixed(3) + '%';
    }

    [monto, plazo, frec, tipo, tasa].forEach(el => el.addEventListener('input', calc));
    calc();
})();
</script>

<?= $this->endSection() ?>
