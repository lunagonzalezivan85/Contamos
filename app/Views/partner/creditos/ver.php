<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<?php
$gestor = trim(($s['gestor_nombres'] ?? '') . ' ' . ($s['gestor_apellidos'] ?? '')) ?: 'Sin asignar';
?>

<div class="list-head">
    <div>
        <h3 class="page-title"><?= esc(trim($s['nombres'] . ' ' . $s['apellidos'])) ?></h3>
        <p class="page-subtitle">
            <?= esc($s['codigo_credito'] ?? 'Crédito #' . $s['id']) ?> ·
            <?= esc($lblFreq[$s['frecuencia_aprobada'] ?? $s['frecuencia']] ?? '') ?> ·
            Gestor: <?= esc($gestor) ?>
        </p>
    </div>
    <div style="display:flex; gap:8px; flex-wrap:wrap;">
        <?php if (!empty($puede_refinanciar) && $saldo > 0): ?>
            <button type="button" class="btn btn-outline btn-sm" data-modal="modal-refinanciar"><?= icon('refresh-cw', 14) ?> Refinanciar</button>
            <button type="button" class="btn btn-outline btn-sm" data-modal="modal-reestructurar"><?= icon('sliders', 14) ?> Reestructurar</button>
        <?php endif; ?>
        <a href="<?= base_url('creditos') ?>" class="btn btn-outline btn-sm"><?= icon('chevron-left', 14) ?> Volver</a>
    </div>
</div>

<!-- Resumen -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format((float) ($s['monto_aprobado'] ?: $s['monto']), 0) ?></div>
        <div class="stat-lbl">Monto aprobado</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format((float) $saldo, 0) ?></div>
        <div class="stat-lbl">Saldo pendiente</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= count(array_filter($cuotas, fn($c) => $c['estado'] === 'PAGADA')) ?> / <?= count($cuotas) ?></div>
        <div class="stat-lbl">Cuotas pagadas</div>
    </div>
    <?php if (($mora['pendiente'] ?? 0) > 0): ?>
        <div class="stat-card">
            <div class="stat-num text-danger"><?= esc($m2) ?> <?= number_format((float) $mora['pendiente'], 2) ?></div>
            <div class="stat-lbl">Mora pendiente (<?= rtrim(rtrim(number_format((float) $mora['pct'], 3), '0'), '.') ?>%/día)</div>
        </div>
    <?php endif; ?>
    <?php if (($saldo_favor ?? 0) > 0): ?>
        <div class="stat-card">
            <div class="stat-num"><?= esc($m2) ?> <?= number_format((float) $saldo_favor, 2) ?></div>
            <div class="stat-lbl">Saldo a favor del cliente</div>
        </div>
    <?php endif; ?>
</div>

<div class="card">
    <h4 class="card-title">Plan de pago</h4>
    <div class="table-wrap">
        <table class="tbl">
            <thead>
                <tr>
                    <th>#</th><th>Vence</th><th>Cuota</th><th>Interés</th><th>Capital</th><th>Pagado</th><th>Mora</th><th>Estado</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($cuotas as $c): ?>
                <tr class="<?= $c['vencida'] ? 'row-vencida' : '' ?>">
                    <td><?= (int) $c['n'] ?></td>
                    <td><?= esc($c['fecha_vence']) ?><?= $c['vencida'] ? ' ?' : '' ?></td>
                    <td><?= esc($m2) ?> <?= number_format((float) $c['cuota'], 2) ?>
                        <?php if (($c['descuento'] ?? 0) > 0): ?>
                            <br><small class="form-hint">?<?= esc($m2) ?> <?= number_format((float) $c['descuento'], 2) ?> pronto pago</small>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($m2) ?> <?= number_format((float) $c['interes'], 2) ?></td>
                    <td><?= esc($m2) ?> <?= number_format((float) $c['capital'], 2) ?></td>
                    <td><?= esc($m2) ?> <?= number_format((float) $c['pagado'], 2) ?></td>
                    <td>
                        <?php if (($c['mora_info']['pendiente'] ?? 0) > 0): ?>
                            <span class="text-danger"><?= esc($m2) ?> <?= number_format((float) $c['mora_info']['pendiente'], 2) ?></span>
                            <br><small class="form-hint"><?= (int) $c['mora_info']['dias'] ?>d vencida</small>
                        <?php elseif (($c['mora_info']['cobrada'] ?? 0) > 0): ?>
                            <small class="form-hint"><?= esc($m2) ?> <?= number_format((float) $c['mora_info']['cobrada'], 2) ?> cobrada</small>
                        <?php else: ?>-<?php endif; ?>
                    </td>
                    <td><span class="badge sol-badge-<?= strtolower($c['estado']) ?>"><?= esc($c['estado']) ?></span></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Registrar abono ? queda EN REVISIÓN -->
<?php if ($puede_pagar && $saldo > 0): ?>
<div class="card">
    <h4 class="card-title">Registrar abono</h4>
    <p class="card-subtitle">El abono queda <strong>en revisión</strong> y se aplica a las cuotas cuando se apruebe en Pagos.</p>
    <form method="post" action="<?= base_url('creditos/' . $s['id'] . '/abonar') ?>" class="sol-filters">
        <?= csrf_field() ?>
        <?php if (($mora['pendiente'] ?? 0) > 0): ?>
            <p class="card-subtitle">Incluye <strong><?= esc($m2) ?> <?= number_format((float) $mora['pendiente'], 2) ?> de mora</strong> devengada por cuotas vencidas.</p>
        <?php endif; ?>
        <?php if (($pronto['pct'] ?? 0) > 0 && $saldo > 0): ?>
            <p class="card-subtitle">
                Pronto pago activo (?<?= rtrim(rtrim(number_format((float) $pronto['pct'], 2), '0'), '.') ?>% del interés pendiente):
                liquida hoy por <strong><?= esc($m2) ?> <?= number_format((float) $pronto['liquidacion'], 2) ?></strong>
                <?= $pronto['ahorro'] > 0 ? '— ahorra ' . $m2 . ' ' . number_format((float) $pronto['ahorro'], 2) : '' ?>.
            </p>
        <?php endif; ?>
        <div class="sol-filters-row">
            <input type="number" name="monto" step="0.01" min="0.01" placeholder="Monto <?= esc($m2) ?>" required>
            <select name="cuota_id">
                <option value="">Abono libre (se reparte en orden)</option>
                <?php foreach ($cuotas as $c): if (!in_array($c['estado'], ['PENDIENTE', 'PARCIAL'], true)) continue; ?>
                    <option value="<?= (int) $c['id'] ?>">Cuota #<?= (int) $c['n'] ?> - pendiente <?= esc($m2) ?> <?= number_format((float) $c['cuota'] - (float) $c['pagado'], 2) ?></option>
                <?php endforeach; ?>
            </select>
            <select name="metodo">
                <?php foreach ($metodos as $k => $lbl): if ($k === 'INTERNO') continue; ?>
                    <option value="<?= esc($k) ?>"><?= esc($lbl) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="sol-filters-row">
            <input type="text" name="observacion" placeholder="Observación (opcional)" maxlength="255">
            <button type="submit" class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Registrar abono</button>
        </div>
    </form>
</div>
<?php endif; ?>

<!-- Historial de pagos -->
<div class="card">
    <h4 class="card-title">Historial de pagos</h4>
    <?php if (empty($pagos)): ?>
        <p class="card-subtitle">Sin pagos registrados.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Fecha</th><th>Monto</th><th>Método</th><th>Cobró</th><th>Estado</th><th></th></tr>
                </thead>
                <tbody>
                <?php foreach ($pagos as $p): ?>
                    <tr>
                        <td><?= esc(date('d/m/Y H:i', strtotime($p['fecha_hora']))) ?></td>
                        <td><?= esc($m2) ?> <?= number_format((float) $p['monto'], 2) ?></td>
                        <td><?= esc($metodos[$p['metodo']] ?? $p['metodo']) ?></td>
                        <td><?= esc(trim(($p['cob_nombres'] ?? '') . ' ' . ($p['cob_apellidos'] ?? '')) ?: 'Oficina') ?></td>
                        <td><span class="badge sol-badge-<?= strtolower($p['estado']) ?>"><?= esc($lblPago[$p['estado']] ?? $p['estado']) ?></span></td>
                        <td>
                            <?php if (($p['tipo'] ?? 'PAGO') === 'PAGO'): ?>
                                <a href="<?= base_url('pagos/' . $p['id'] . '/recibo') ?>" target="_blank"
                                   class="btn btn-outline btn-sm" title="Imprimir recibo"><?= icon('printer', 14) ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($puede_refinanciar) && $saldo > 0): ?>
<!-- Modal: refinanciar - nueva solicitud que liquida este crédito al entregarse -->
<div class="modal-overlay" id="modal-refinanciar" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('refresh-cw', 17) ?> Refinanciar crédito</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('creditos/' . $s['id'] . '/refinanciar') ?>">
            <?= csrf_field() ?>
            <div class="modal-body" style="display:block;">
                <p class="form-hint" style="margin-bottom:12px;">
                    Crea una solicitud nueva para el mismo cliente. Sigue el flujo normal
                    (análisis ? aprobación ? desembolso) y al entregarse el dinero, este
                    crédito queda <strong>liquidado</strong>.
                </p>
                <div class="form-group">
                    <label>Monto a refinanciar (<?= esc($m2) ?>)</label>
                    <input type="number" name="monto" step="0.01" min="0.01" value="<?= esc($saldo) ?>">
                    <span class="form-hint">Por defecto el saldo pendiente actual.</span>
                </div>
                <div class="form-group">
                    <label>Plazo (meses)</label>
                    <input type="number" name="plazo_meses" min="1" value="<?= esc($s['plazo_aprobado'] ?: $s['plazo_meses']) ?>">
                </div>
                <div class="form-group">
                    <label>Tasa mensual (%)</label>
                    <input type="number" name="tasa_mensual" min="0" step="0.01" value="<?= esc($s['tasa_aprobada'] ?? $s['tasa_mensual']) ?>">
                </div>
                <div class="form-group">
                    <label>Tipo de cálculo</label>
                    <select name="tipo_calculo">
                        <?php foreach ($tiposCalc as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($s['tipo_calculo'] ?? 'FRANCES') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Frecuencia</label>
                    <select name="frecuencia">
                        <?php foreach ($lblFreq as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($s['frecuencia_aprobada'] ?? $s['frecuencia']) === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('refresh-cw', 15) ?> Crear refinanciamiento</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: reestructurar - regenera el plan pendiente con nuevos términos -->
<div class="modal-overlay" id="modal-reestructurar" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('sliders', 17) ?> Reestructurar crédito</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('creditos/' . $s['id'] . '/reestructurar') ?>"
              onsubmit="return confirm('Se anularán las cuotas pendientes y se generará un plan nuevo sobre el saldo. ¿Continuar?');">
            <?= csrf_field() ?>
            <div class="modal-body" style="display:block;">
                <p class="form-hint" style="margin-bottom:12px;">
                    Las cuotas pendientes se anulan (conservan lo ya cobrado) y se genera un
                    plan nuevo sobre <strong><?= esc($m2) ?> <?= number_format((float) $saldo, 2) ?></strong>
                    con estos términos.
                </p>
                <div class="form-group">
                    <label>Nueva tasa mensual (%) *</label>
                    <input type="number" name="tasa_aprobada" min="0" step="0.01" required
                           value="<?= esc($s['tasa_aprobada'] ?? $s['tasa_mensual']) ?>">
                </div>
                <div class="form-group">
                    <label>Nuevo plazo (meses) *</label>
                    <input type="number" name="plazo_aprobado" min="1" required
                           value="<?= esc($s['plazo_aprobado'] ?: $s['plazo_meses']) ?>">
                </div>
                <div class="form-group">
                    <label>Frecuencia *</label>
                    <select name="frecuencia_aprobada" required>
                        <?php foreach ($lblFreq as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($s['frecuencia_aprobada'] ?? $s['frecuencia']) === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Tipo de cálculo</label>
                    <select name="tipo_calculo">
                        <?php foreach ($tiposCalc as $k => $lbl): ?>
                            <option value="<?= $k ?>" <?= ($s['tipo_calculo'] ?? 'FRANCES') === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Primer pago del nuevo plan *</label>
                    <input type="date" name="fecha_primer_pago" min="<?= date('Y-m-d') ?>" required
                           value="<?= date('Y-m-d', strtotime('+7 days')) ?>">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('sliders', 15) ?> Reestructurar plan</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
