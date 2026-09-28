<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<?php $mon = $tenant['moneda'] ?? 'C$'; ?>

<!-- Encabezado con volver -->
<div class="app-header card portal-card">
    <a href="<?= base_url($slug . '/portal/panel') ?>" class="app-back"><?= icon('chevron-left', 20) ?></a>
    <div>
        <h3>Nueva solicitud</h3>
        <p class="app-rol">Solicitud de crédito para un cliente</p>
    </div>
</div>

<!-- Barra de pasos -->
<div class="wiz-progress">
    <div class="wiz-step on" data-s="1"><span class="wiz-num">1</span><span class="wiz-txt">Cliente</span></div>
    <div class="wiz-step" data-s="2"><span class="wiz-num">2</span><span class="wiz-txt">Préstamo</span></div>
    <div class="wiz-step" data-s="3"><span class="wiz-num">3</span><span class="wiz-txt">Resumen</span></div>
    <div class="wiz-line"><i id="wiz-bar"></i></div>
</div>

<form method="post" action="<?= base_url($slug . '/portal/solicitud') ?>" id="sol-form" data-mon="<?= esc($mon) ?>" data-tasa="<?= esc(number_format((float) ($tenant['tasa_interes'] ?? 3), 2, '.', '')) ?>" novalidate>
    <?= csrf_field() ?>

    <!-- ===== PASO 1 · Cliente ===== -->
    <div class="card portal-card wiz-pane on" data-s="1">
        <h4 class="card-title"><?= icon('user', 16) ?> Cliente</h4>
        <p class="card-subtitle">Elige un cliente existente o regístralo aquí mismo.</p>

        <div class="calc-freq" id="cli-modo">
            <button type="button" class="freq-btn activo" data-m="existente">Cliente existente</button>
            <button type="button" class="freq-btn" data-m="nuevo"><?= icon('plus', 14) ?> Nuevo cliente</button>
        </div>
        <input type="hidden" name="cli_modo" id="cli_modo" value="existente">

        <!-- Existente -->
        <div id="cli-existente" class="mt-3">
            <?php
            // Preselección cuando el form vuelve con error (old())
            $oldCli = (int) old('cliente_id');
            $oldNom = '';
            foreach ($clientes ?? [] as $c) {
                if ((int) $c['id'] === $oldCli) { $oldNom = trim($c['nombres'] . ' ' . $c['apellidos']); break; }
            }
            ?>
            <div class="form-group">
                <label for="cli-pick-q">Selecciona el cliente *</label>
            <div class="cli-pick" id="cli-pick">
                <input type="hidden" name="cliente_id" id="cliente_id" value="<?= esc(old('cliente_id')) ?>">
                <div class="cli-pick-input <?= $oldNom !== '' ? 'sel' : '' ?>" id="cli-pick-wrap">
                    <?= icon('search', 16) ?>
                    <input type="text" id="cli-pick-q" autocomplete="off"
                           placeholder="Buscar por nombre, código o cédula."
                           value="<?= esc($oldNom) ?>" <?= $oldNom !== '' ? 'readonly' : '' ?>>
                    <button type="button" class="cli-pick-clear" id="cli-pick-clear" <?= $oldNom === '' ? 'hidden' : '' ?>><?= icon('x', 14) ?></button>
                </div>
                <div class="cli-pick-list" id="cli-pick-list" hidden>
                    <?php foreach ($clientes ?? [] as $c): ?>
                        <?php
                        $nomC = trim($c['nombres'] . ' ' . $c['apellidos']);
                        $iniC = mb_strtoupper(mb_substr(trim($c['nombres']), 0, 1) . mb_substr(trim($c['apellidos']), 0, 1));
                        ?>
                        <button type="button" class="cli-pick-item <?= (int) $c['id'] === $oldCli ? 'sel' : '' ?>"
                                data-id="<?= (int) $c['id'] ?>" data-nombre="<?= esc($nomC) ?>"
                                data-limite="<?= esc((float) ($c['limite_credito'] ?? 0) > 0 ? number_format((float) $c['limite_credito'], 2, '.', '') : '10000') ?>"
                                data-q="<?= esc(mb_strtolower($nomC . ' ' . ($c['codigo'] ?? '') . ' ' . ($c['cedula'] ?? ''))) ?>">
                            <span class="cli-avatar cli-avatar-sm"><?= esc($iniC !== '' ? $iniC : '?') ?></span>
                            <span class="cli-info">
                                <span class="cli-nombre"><?= esc($nomC) ?></span>
                                <span class="cli-sub"><?= esc($c['codigo'] ?? '-') ?> · <?= esc($c['cedula'] ?: 'Sin cédula') ?></span>
                            </span>
                            <?= icon('check', 16) ?>
                        </button>
                    <?php endforeach; ?>
                    <p class="cli-pick-empty" id="cli-pick-empty" hidden>Sin clientes que coincidan.</p>
                </div>
            </div>
            </div>
            <?php if (empty($clientes)): ?>
                <p class="geo-hint">Aún no hay clientes - usa <strong>Nuevo cliente</strong>.</p>
            <?php endif; ?>
        </div>

        <!-- Nuevo -->
        <div id="cli-nuevo" hidden>
            <div class="form-grid">
                <div class="form-group">
                    <label for="cli_nombres">Nombres *</label>
                    <input type="text" id="cli_nombres" name="cli_nombres" value="<?= esc(old('cli_nombres')) ?>" placeholder="Nombres">
                </div>
                <div class="form-group">
                    <label for="cli_apellidos">Apellidos *</label>
                    <input type="text" id="cli_apellidos" name="cli_apellidos" value="<?= esc(old('cli_apellidos')) ?>" placeholder="Apellidos">
                </div>
                <div class="form-group">
                    <label for="cli_cedula">Cédula</label>
                    <input type="text" id="cli_cedula" name="cli_cedula" value="<?= esc(old('cli_cedula')) ?>" placeholder="001-000000-0000A">
                </div>
                <div class="form-group">
                    <label for="cli_telefono">Teléfono</label>
                    <input type="text" id="cli_telefono" name="cli_telefono" value="<?= esc(old('cli_telefono')) ?>" placeholder="8xxx xxxx">
                </div>
                <div class="form-group form-full">
                    <label for="cli_direccion">Dirección</label>
                    <input type="text" id="cli_direccion" name="cli_direccion" value="<?= esc(old('cli_direccion')) ?>" placeholder="Dirección del cliente">
                </div>
            </div>
        </div>
    </div>

    <!-- ===== PASO 2 · Préstamo (dinámico) ===== -->
    <div class="card portal-card wiz-pane" data-s="2">
        <h4 class="card-title"><?= icon('percent', 16) ?> Préstamo</h4>
        <p class="card-subtitle">Monto, plazo y frecuencia de pago — la tasa la define la empresa.</p>

        <!-- Cuota en vivo -->
        <div class="calc-result calc-mini">
            <span class="calc-result-label" id="sol-cuota-label">Cuota mensual estimada</span>
            <strong class="calc-result-cuota" id="sol-cuota-prev"><?= esc($mon) ?> 0.00</strong>
        </div>

        <div class="calc-field">
            <div class="calc-field-head"><label>Monto del préstamo</label><output class="calc-val" id="sol-monto-lbl"><?= esc($mon) ?> 10,000</output></div>
            <input type="range" class="calc-range" id="monto" name="monto" min="1000" max="10000" step="500" value="<?= esc(old('monto', 10000)) ?>">
            <p class="geo-hint" id="sol-limite-lbl">Máximo para este cliente: <?= esc($mon) ?> 10,000</p>
        </div>
        <?php $tasaMax = (float) ($tenant['tasa_interes'] ?? 3); ?>
        <div class="calc-field">
            <div class="calc-field-head"><label>Tasa mensual</label><output class="calc-val" id="sol-tasa-lbl"><?= number_format($tasaMax, 2) ?>%</output></div>
            <input type="range" class="calc-range" id="tasa_mensual" name="tasa_mensual"
                   min="0" max="<?= esc(number_format($tasaMax, 2, '.', '')) ?>" step="0.25"
                   value="<?= esc(old('tasa_mensual', number_format($tasaMax, 2, '.', ''))) ?>">
            <p class="geo-hint">Podés bajarla para negociar — el máximo es la tasa de la empresa (<?= number_format($tasaMax, 2) ?>%).</p>
        </div>
        <div class="calc-field">
            <div class="calc-field-head"><label>Plazo del préstamo</label><output class="calc-val" id="sol-plazo-lbl"><?= (int) old('plazo_meses', min(12, (int) ($tenant['plazo_meses_max'] ?? 60))) ?> meses</output></div>
            <input type="range" class="calc-range" id="plazo_meses" name="plazo_meses" min="3" max="<?= (int) ($tenant['plazo_meses_max'] ?? 60) ?>" step="1" value="<?= esc(old('plazo_meses', min(12, (int) ($tenant['plazo_meses_max'] ?? 60)))) ?>">
        </div>

        <div class="form-group">
            <label>Frecuencia de pago</label>
            <div class="calc-freq" id="sol-freq">
                <button type="button" class="freq-btn" data-f="D">Diario</button>
                <button type="button" class="freq-btn" data-f="DI">Diario intermitente</button>
                <button type="button" class="freq-btn" data-f="S">Semanal</button>
                <button type="button" class="freq-btn" data-f="Q">Quincenal</button>
                <button type="button" class="freq-btn activo" data-f="M">Mensual</button>
            </div>
            <input type="hidden" name="frecuencia" id="frecuencia" value="M">
        </div>
        <div class="calc-dias" id="sol-dias-wrap" hidden>
            <label for="dias_semana">Días de pago por semana (diario intermitente)</label>
            <input type="range" class="calc-range" id="dias_semana" name="dias_semana" min="1" max="7" step="1" value="3">
            <div class="calc-dias-lbl"><output id="sol-dias-lbl">3 días / semana</output></div>
        </div>

        <!-- Primer pago propuesto + plan simulado (solo visual, para negociar) -->
        <div class="calc-field" style="margin-top:18px; padding-top:16px; border-top:1px solid #EEF2F6;">
            <div class="calc-field-head"><label>Primer pago propuesto</label></div>
            <div style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                <input type="date" id="sol-inicio" name="fecha_primer_pago"
                       style="flex:1; min-width:150px; padding:10px 12px; border:1.5px solid #E2E8F0; border-radius:10px; font-size:14px; font-family:inherit;">
                <button type="button" class="btn btn-primary" id="sol-plan-btn">
                    <?= icon('calendar', 15) ?> Ver plan de pago
                </button>
            </div>
            <p class="geo-hint">Si la clienta dice «te empiezo a pagar X día» — simulá el plan con esa fecha. Solo visual.</p>
        </div>
    </div>

    <!-- ===== PASO 3 · Resumen ===== -->
    <div class="card portal-card wiz-pane" data-s="3">
        <h4 class="card-title"><?= icon('file-text', 16) ?> Resumen</h4>
        <div class="form-group mt-3">
            <label for="destino">Destino del crédito</label>
            <input type="text" id="destino" name="destino" value="<?= esc(old('destino')) ?>" placeholder="Ej: Compra de mercadería, electrodoméstico">
        </div>
        <div class="sol-resumen" id="sol-resumen"></div>
    </div>

    <!-- Navegación del wizard -->
    <div class="wiz-nav">
        <button type="button" class="btn btn-outline" id="wiz-prev" disabled><?= icon('chevron-left', 14) ?> Atrás</button>
        <button type="button" class="btn btn-primary" id="wiz-next">Siguiente <?= icon('chevron-right', 14) ?></button>
        <button type="submit" class="btn btn-primary" id="wiz-send" hidden><?= icon('file-plus', 16) ?> Enviar solicitud</button>
    </div>
</form>

<!-- Modal: plan de pago simulado con fecha propuesta por el cliente -->
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
<script src="<?= v_asset('js/portal/solicitud.js') ?>"></script>
<?= $this->endSection() ?>
