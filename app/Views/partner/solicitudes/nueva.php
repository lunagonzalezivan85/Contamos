<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2   = $mon ?? 'C$';
$tasa = (float) ($tenant['tasa_interes'] ?? 3);
$pMax = (int) ($tenant['plazo_meses_max'] ?? 60);
?>

<div class="page-head" style="display:flex; align-items:flex-end; justify-content:space-between; gap:12px; flex-wrap:wrap;">
    <div>
        <h3 class="page-title">Nueva solicitud</h3>
        <p class="page-subtitle">Solicitud de crédito registrada desde oficina.</p>
    </div>
    <a class="btn btn-outline" href="<?= base_url('credito/solicitudes') ?>"><?= icon('chevron-left', 15) ?> Volver</a>
</div>

<form method="post" action="<?= base_url('credito/solicitudes') ?>" id="sol-form"
      data-mon="<?= esc($m2) ?>" data-tasa="<?= esc(number_format($tasa, 2, '.', '')) ?>" novalidate>
    <?= csrf_field() ?>

    <div class="sol-split sol-split-lg">

        <!-- ======== Izquierda: formulario ======== -->
        <div>
            <div class="card">
                <h4 class="card-title"><?= icon('user', 16) ?> Cliente</h4>
                <p class="card-subtitle">Quien solicita el crédito y a quién se asigna la ruta.</p>
                <div class="form-grid mt-4">
                    <div class="form-group form-full">
                        <label for="cliente_id">Cliente *</label>
                        <select id="cliente_id" name="cliente_id" required>
                            <option value="">— Seleccione el cliente —</option>
                            <?php foreach ($clientes as $c): ?>
                                <?php
                                $lim = (float) ($c['limite_credito'] ?? 0) > 0 ? (float) $c['limite_credito'] : 10000;
                                $nom = trim(($c['nombres'] ?? '') . ' ' . ($c['apellidos'] ?? ''));
                                ?>
                                <option value="<?= (int) $c['id'] ?>" data-limite="<?= esc($lim) ?>"
                                        data-nombre="<?= esc($nom) ?>"
                                        <?= (string) old('cliente_id') === (string) $c['id'] ? 'selected' : '' ?>>
                                    <?= esc($nom) ?> · <?= esc($c['codigo'] ?? '-') ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">¿No existe? Créelo en
                            <a href="<?= base_url('socios/clientes/nuevo') ?>">Socios → Clientes</a>.
                            El máximo a prestar sale de su límite de crédito (10,000 si no tiene).</small>
                    </div>
                    <div class="form-group">
                        <label for="asignado_a">Gestor asignado</label>
                        <select id="asignado_a" name="asignado_a">
                            <option value="">— Sin asignar —</option>
                            <?php foreach ($gestores as $g): ?>
                                <option value="<?= (int) $g['id'] ?>" <?= (string) old('asignado_a') === (string) $g['id'] ? 'selected' : '' ?>>
                                    <?= esc(trim($g['nombres'] . ' ' . $g['apellidos'])) ?><?= !empty($g['ruta']) ? ' · ' . esc($g['ruta']) : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">El gestor la verá en su cartera.</small>
                    </div>
                    <div class="form-group">
                        <label for="destino">Destino del crédito</label>
                        <input type="text" id="destino" name="destino" maxlength="255"
                               value="<?= esc(old('destino')) ?>" placeholder="Ej: Compra de mercadería">
                    </div>
                </div>
            </div>

            <div class="card mt-4">
                <h4 class="card-title"><?= icon('percent', 16) ?> Préstamo</h4>
                <p class="card-subtitle">La tasa es fija por empresa: <strong><?= number_format($tasa, 2) ?>% mensual</strong>.</p>
                <div class="form-grid mt-4">
                    <div class="form-group form-full">
                        <label for="monto">Monto (<?= esc($m2) ?>) *</label>
                        <input type="number" id="monto" name="monto" min="1000" step="100" required
                               value="<?= esc(old('monto', 10000)) ?>">
                        <div class="mini-chips" id="monto-chips">
                            <button type="button" class="mini-chip" data-m="1000">1,000</button>
                            <button type="button" class="mini-chip" data-m="5000">5,000</button>
                            <button type="button" class="mini-chip" data-m="10000">10,000</button>
                            <button type="button" class="mini-chip" data-m="20000">20,000</button>
                            <button type="button" class="mini-chip" data-m="50000">50,000</button>
                        </div>
                        <small class="form-hint" id="sol-limite-hint">Mínimo <?= esc($m2) ?> 1,000 — el máximo depende del cliente.</small>
                    </div>
                    <div class="form-group">
                        <label for="plazo_meses">Plazo (meses)</label>
                        <input type="number" id="plazo_meses" name="plazo_meses" min="1" max="<?= $pMax ?>" step="1"
                               value="<?= esc(old('plazo_meses', min(12, $pMax))) ?>">
                        <small class="form-hint">Máximo <?= $pMax ?> meses.</small>
                    </div>
                    <div class="form-group">
                        <label>Frecuencia de pago</label>
                        <div class="seg" id="sol-freq">
                            <?php foreach (['D','DI','S','Q','M'] as $i => $f): ?>
                                <input type="radio" name="frecuencia" id="f-<?= $f ?>" value="<?= $f ?>"
                                       <?= old('frecuencia', 'M') === $f ? 'checked' : '' ?>>
                                <label for="f-<?= $f ?>"><?= esc($lblFreq[$f]) ?></label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    <div class="form-group" id="dias-wrap" hidden>
                        <label for="dias_semana">Días de pago por semana</label>
                        <input type="number" id="dias_semana" name="dias_semana" min="1" max="7" step="1"
                               value="<?= esc(old('dias_semana', 3)) ?>">
                        <small class="form-hint">Solo para diario intermitente.</small>
                    </div>
                    <div class="form-group">
                        <label for="tipo_calculo">Tipo de cálculo</label>
                        <select id="tipo_calculo" name="tipo_calculo">
                            <?php foreach ($tiposCalc as $k => $lbl): ?>
                                <option value="<?= $k ?>" <?= old('tipo_calculo', $tenant['tipo_calculo'] ?? 'FLAT') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-hint">Flat = tasa × meses sobre el capital prestado.</small>
                    </div>
                    <div class="form-group">
                        <label for="fecha_primer_pago">Primer pago</label>
                        <input type="date" id="fecha_primer_pago" name="fecha_primer_pago" min="<?= date('Y-m-d') ?>"
                               value="<?= esc(old('fecha_primer_pago', $fechaSug)) ?>">
                        <button type="button" class="btn btn-outline btn-sm" id="btn-plan" data-modal="modal-plan" style="margin-top:8px;">
                            <?= icon('calendar', 13) ?> Ver plan sugerido
                        </button>
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
                    <strong class="calc-result-cuota" id="rs-cuota"><?= esc($m2) ?> 0.00</strong>
                </div>
                <div class="sol-resumen" id="rs-resumen">
                    <div class="sol-row"><span>Cliente</span><strong id="rs-cliente">—</strong></div>
                    <div class="sol-row"><span>Monto</span><strong id="rs-monto">—</strong></div>
                    <div class="sol-row"><span>Tasa mensual</span><strong><?= number_format($tasa, 2) ?>%</strong></div>
                    <div class="sol-row"><span>Plazo</span><strong id="rs-plazo">—</strong></div>
                    <div class="sol-row"><span>Frecuencia</span><strong id="rs-freq">—</strong></div>
                    <div class="sol-row"><span>Total a pagar</span><strong id="rs-total">—</strong></div>
                    <div class="sol-row"><span>Primer pago</span><strong id="rs-fecha">—</strong></div>
                    <div class="sol-row"><span>Límite del cliente</span><strong id="rs-limite">—</strong></div>
                </div>
                <div class="mini-chips" style="margin-top:14px;">
                    <span class="badge sol-badge-creada">Estado: CREADA</span>
                    <span class="badge badge-soft">Pasa a análisis</span>
                </div>
                <div style="display:flex; gap:10px; margin-top:18px;">
                    <button type="submit" class="btn btn-primary" style="flex:1; justify-content:center;">
                        <?= icon('file-plus', 15) ?> Registrar solicitud
                    </button>
                </div>
                <p class="form-hint" style="text-align:center; margin-top:10px;">
                    La cuota es una estimación — el plan exacto se genera al aprobar.
                </p>
            </div>
        </div>
    </div>
</form>

<!-- Modal: plan de pago sugerido (solo visual) -->
<div class="modal-overlay" id="modal-plan" hidden>
    <div class="modal-box plan-box">
        <div class="modal-head">
            <h4><?= icon('calendar', 16) ?> Plan de pago sugerido</h4>
            <button type="button" class="modal-close" data-close aria-label="Cerrar">&times;</button>
        </div>
        <div class="plan-body" id="plan-tabla"></div>
        <div class="modal-foot">
            <p class="form-hint" style="margin:0 auto 0 0; align-self:center;">Simulación referencial — el plan definitivo se genera al aprobar.</p>
            <button type="button" class="btn btn-outline" data-close>Cerrar</button>
        </div>
    </div>
</div>

<script src="<?= v_asset('js/plan-sugerido.js') ?>"></script>
<script>
(function () {
    var form  = document.getElementById('sol-form');
    var mon   = form.dataset.mon || 'C$';
    var TASA  = parseFloat(form.dataset.tasa) || 0;

    var cli   = document.getElementById('cliente_id');
    var monto = document.getElementById('monto');
    var plazo = document.getElementById('plazo_meses');
    var dias  = document.getElementById('dias_semana');
    var tipo  = document.getElementById('tipo_calculo');
    var fpp   = document.getElementById('fecha_primer_pago');
    var hint  = document.getElementById('sol-limite-hint');
    var dwrap = document.getElementById('dias-wrap');

    var LBL_F = { D: 'Diario', DI: 'Diario intermitente', S: 'Semanal', Q: 'Quincenal', M: 'Mensual' };
    var PPM   = { D: 30, S: 4, Q: 2, M: 1 };

    function freq() { return (form.querySelector('input[name=frecuencia]:checked') || {}).value || 'M'; }
    function fmt(n) { return mon + ' ' + n.toLocaleString('es-NI', {minimumFractionDigits: 2, maximumFractionDigits: 2}); }

    function limiteCli() {
        var opt = cli.selectedOptions[0];
        return opt && opt.value ? (parseFloat(opt.dataset.limite) || 10000) : null;
    }

    function resumen() {
        var f    = freq();
        var P    = parseFloat(monto.value) || 0;
        var mes  = parseInt(plazo.value, 10) || 0;
        var dSem = parseInt(dias.value, 10) || 3;
        var lim  = limiteCli();

        // tope del monto según el cliente
        if (lim) {
            monto.max = lim;
            hint.textContent = 'Mínimo ' + mon + ' 1,000 · máximo ' + mon + ' ' + lim.toLocaleString('es-NI') + ' (límite del cliente)';
            if (P > lim) monto.value = lim, P = lim;
        } else {
            hint.textContent = 'Mínimo ' + mon + ' 1,000 — el máximo depende del cliente.';
        }

        // ocultar días si no es intermitente
        dwrap.hidden = f !== 'DI';

        var ppm = f === 'DI' ? 4 * dSem : PPM[f];
        var iP  = (TASA / 100) / ppm;
        var n   = Math.max(1, Math.round(mes * ppm));
        var tc  = tipo.value || 'FLAT';

        // Cuota estimada según el tipo de cálculo (igual que el plan real)
        var intFlat = P * (TASA / 100) * mes;                       // FLAT/ANTICIPADO: tasa × meses
        var cuota, total, lblCuota = 'Cuota ' + LBL_F[f].toLowerCase() + ' estimada';
        if (tc === 'FLAT') {
            cuota = P > 0 ? (P + intFlat) / n : 0;
            total = P + intFlat;
        } else if (tc === 'ALEMAN') {
            cuota = P > 0 ? P / n + iP * P : 0;                     // primera cuota (la mayor)
            total = P + iP * P * (n + 1) / 2;
            lblCuota = 'Primera cuota ' + LBL_F[f].toLowerCase() + ' (baja cada pago)';
        } else if (tc === 'ANTICIPADO') {
            cuota = P > 0 ? P / n : 0;                              // solo capital — interés descontado del desembolso
            total = P + intFlat;
            lblCuota = 'Cuota de capital ' + LBL_F[f].toLowerCase();
        } else { // FRANCES
            cuota = (iP > 0 && P > 0) ? P * iP * Math.pow(1 + iP, n) / (Math.pow(1 + iP, n) - 1) : (P / n || 0);
            total = cuota * n;
        }

        var opt = cli.selectedOptions[0];
        document.getElementById('rs-cliente').textContent = opt && opt.value ? opt.dataset.nombre : '—';
        document.getElementById('rs-monto').textContent   = P > 0 ? fmt(P) : '—';
        document.getElementById('rs-plazo').textContent   = mes > 0 ? mes + (mes === 1 ? ' mes' : ' meses') : '—';
        document.getElementById('rs-freq').textContent    = LBL_F[f] + (f === 'DI' ? ' (' + dSem + ' días/sem)' : '');
        document.getElementById('rs-total').textContent   = P > 0 ? fmt(total) : '—';
        document.getElementById('rs-fecha').textContent   = fpp.value
            ? new Date(fpp.value + 'T00:00:00').toLocaleDateString('es-NI', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
        document.getElementById('rs-limite').textContent  = lim ? mon + ' ' + lim.toLocaleString('es-NI') : '—';
        document.getElementById('rs-cuota').textContent   = fmt(cuota) + (n > 0 && P > 0 ? ' × ' + n : '');
        document.getElementById('rs-cuota-lbl').textContent = lblCuota;
    }

    document.getElementById('btn-plan').addEventListener('click', function () {
        PlanSugerido.mostrar({
            mon: mon, monto: monto.value, tasa: TASA, meses: plazo.value,
            freq: freq(), dias: dias.value, tipo: tipo.value, fecha: fpp.value
        });
    });

    document.getElementById('monto-chips').addEventListener('click', function (e) {
        var b = e.target.closest('.mini-chip');
        if (!b) return;
        var lim = limiteCli();
        var v = parseFloat(b.dataset.m);
        monto.value = lim ? Math.min(v, lim) : v;
        resumen();
    });

    [cli, monto, plazo, dias, tipo, fpp].forEach(function (el) {
        el.addEventListener('change', resumen);
        el.addEventListener('input', resumen);
    });
    form.querySelectorAll('input[name=frecuencia]').forEach(function (r) {
        r.addEventListener('change', resumen);
    });

    form.addEventListener('submit', function (e) {
        if (!cli.value) { e.preventDefault(); alert('Seleccione un cliente.'); cli.focus(); return; }
        if ((parseFloat(monto.value) || 0) < 1000) { e.preventDefault(); alert('El monto mínimo a prestar es ' + mon + ' 1,000.'); monto.focus(); }
    });

    resumen();
})();
</script>

<?= $this->endSection() ?>
