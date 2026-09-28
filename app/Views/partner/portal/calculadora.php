<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<!-- Encabezado con volver -->
<div class="app-header card portal-card">
    <a href="<?= base_url($slug . '/portal/panel') ?>" class="app-back"><?= icon('chevron-left', 20) ?></a>
    <div>
        <h3>Calculadora de préstamo</h3>
        <p class="app-rol">Simula la cuota de un crédito</p>
    </div>
</div>

<?php $mon = $tenant['moneda'] ?? 'C$'; ?>

<!-- Resultado destacado -->
<div class="calc-result" id="calc-app" data-mon="<?= esc($mon) ?>"
     data-tasa="<?= esc(number_format((float) ($tenant['tasa_interes'] ?? 3), 2, '.', '')) ?>"
     data-tipo="<?= esc($tenant['tipo_calculo'] ?? 'FLAT') ?>">
    <span class="calc-result-label" id="calc-cuota-label">Cuota mensual estimada</span>
    <strong class="calc-result-cuota" id="calc-cuota"><?= esc($mon) ?> 0.00</strong>
    <div class="calc-result-metas">
        <div class="calc-meta">
            <span>Total a pagar</span>
            <strong id="calc-total">-</strong>
        </div>
        <div class="calc-meta">
            <span>Total intereses</span>
            <strong id="calc-interes">-</strong>
        </div>
        <div class="calc-meta">
            <span>N° de pagos</span>
            <strong id="calc-cuotas">-</strong>
        </div>
    </div>
</div>

<!-- Parámetros -->
<div class="card portal-card">
    <div class="calc-field">
        <div class="calc-field-head">
            <label>Monto del préstamo</label>
            <output class="calc-val" id="calc-monto-lbl"><?= esc($mon) ?> 50,000</output>
        </div>
        <input type="number" class="calc-num" id="calc-monto" min="1000" max="500000" step="500" inputmode="decimal" value="50000">
    </div>
    <?php $tasaMax = (float) ($tenant['tasa_interes'] ?? 3); ?>
    <div class="calc-field">
        <div class="calc-field-head">
            <label>Tasa mensual</label>
            <output class="calc-val" id="calc-tasa-lbl"><?= number_format($tasaMax, 2) ?>%</output>
        </div>
        <input type="number" class="calc-num" id="calc-tasa" min="0" step="0.25" inputmode="decimal"
               max="<?= esc(number_format($tasaMax, 2, '.', '')) ?>"
               value="<?= esc(number_format($tasaMax, 2, '.', '')) ?>">
        <p class="geo-hint">Podés bajarla para negociar — máximo <?= number_format($tasaMax, 2) ?>% (configuración de la empresa).</p>
    </div>
    <div class="calc-field">
        <div class="calc-field-head">
            <label>Plazo del préstamo</label>
            <output class="calc-val" id="calc-plazo-lbl">12 meses</output>
        </div>
        <input type="number" class="calc-num" id="calc-plazo" min="3" max="60" step="1" inputmode="numeric" value="12">
    </div>

    <!-- Frecuencia de pago -->
    <div class="calc-field">
        <div class="calc-field-head"><label>Frecuencia de pago</label></div>
        <div class="calc-freq" id="calc-freq">
            <button type="button" class="freq-btn" data-f="D"  data-dias="30">Diario</button>
            <button type="button" class="freq-btn" data-f="DI">Diario intermitente</button>
            <button type="button" class="freq-btn" data-f="S"  data-dias="7">Semanal</button>
            <button type="button" class="freq-btn" data-f="Q"  data-dias="2">Quincenal</button>
            <button type="button" class="freq-btn activo" data-f="M" data-dias="1">Mensual</button>
        </div>
        <div class="calc-dias" id="calc-dias-wrap" hidden>
            <label for="calc-dias">Días de pago por semana (diario intermitente)</label>
            <input type="number" class="calc-num" id="calc-dias" min="1" max="7" step="1" inputmode="numeric" value="3">
            <div class="calc-dias-lbl"><output id="calc-dias-lbl">3 días / semana</output></div>
        </div>
    </div>

    <!-- Primer pago propuesto por el cliente + plan de pago en modal -->
    <div class="calc-field" style="margin-top:18px; padding-top:16px; border-top:1px solid #EEF2F6;">
        <div class="calc-field-head"><label>Primer pago propuesto</label></div>
        <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
            <input type="date" id="calc-inicio"
                   style="flex:1; min-width:150px; padding:10px 12px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit;">
            <button type="button" class="btn btn-primary" id="calc-plan-btn">
                <?= icon('calendar', 15) ?> Ver plan de pago
            </button>
        </div>
        <p class="geo-hint">Simulá cuándo empezaría a pagar — solo visual, para negociar con el cliente.</p>
    </div>
</div>

<!-- Modal: plan de pago simulado -->
<div class="modal-overlay" id="modal-plan" hidden>
    <div class="modal-box" style="max-width:520px;">
        <div class="modal-head">
            <h4 id="plan-title">Plan de pago</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <div class="modal-body" style="padding:0;">
            <div class="table-wrap" style="max-height:60vh; overflow-y:auto;">
                <table class="tbl" id="plan-tbl">
                    <thead>
                        <tr><th>#</th><th>Fecha</th><th style="text-align:right;">Cuota</th>
                            <th style="text-align:right;">Interés</th><th style="text-align:right;">Saldo</th></tr>
                    </thead>
                    <tbody></tbody>
                </table>
            </div>
        </div>
        <div class="modal-foot" id="plan-foot" style="justify-content:space-between; font-size:13px; color:var(--text-muted);"></div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= v_asset('js/portal/calculadora.js') ?>"></script>
<?= $this->endSection() ?>
