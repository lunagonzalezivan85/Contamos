<?php
/**
 * Modal registrar pago / promesa de pago - mismo formulario, POST /pagos/registrar.
 *
 * Uso: <?= view('partner/pagos/_modal_abono', ['esPromesa' => false, 'creditos' => $creditos]) ?>
 *
 * Vars: $esPromesa bool, $creditos array (id, nombres, apellidos, codigo_credito,
 *       pendiente, sugerido, cuota_id), $metodos array (solo pago).
 */
$esPromesa = !empty($esPromesa);
$pref      = $esPromesa ? 'prom' : 'pago';
$modalId   = $esPromesa ? 'modal-promesa' : 'modal-pago';
$m2        = $mon ?? ($tenant['moneda'] ?? 'C$');
?>
<div class="modal-overlay" id="<?= $modalId ?>" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon($esPromesa ? 'clock' : 'dollar-sign', 17) ?> <?= $esPromesa ? 'Promesa de pago' : 'Registrar pago' ?></h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('pagos/registrar') ?>">
            <?= csrf_field() ?>
            <?php if ($esPromesa): ?><input type="hidden" name="tipo" value="PROMESA"><?php endif; ?>
            <div class="modal-body">
                <?php if ($esPromesa): ?>
                    <p class="card-subtitle" style="grid-column: 1 / -1; margin: 0;">
                        El cliente se compromete a pagar en la fecha indicada.
                        Cuando pague, se convierte en cobro desde la lista de promesas.
                    </p>
                <?php endif; ?>

                <div class="form-group form-full">
                    <label>Cliente / crédito</label>
                    <select name="solicitud_id" id="<?= $pref ?>-credito" required>
                        <option value="">- Seleccione el crédito -</option>
                        <?php foreach ($creditos as $cr): ?>
                            <option value="<?= (int) $cr['id'] ?>"
                                    data-pend="<?= esc($cr['pendiente']) ?>"
                                    data-sugerido="<?= esc($cr['sugerido']) ?>"
                                    data-cuota="<?= esc($cr['cuota_id'] ?? '') ?>">
                                <?= esc(trim($cr['nombres'] . ' ' . $cr['apellidos'])) ?> - <?= esc($cr['codigo_credito'] ?: '#' . $cr['id']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <input type="hidden" name="cuota_id" id="<?= $pref ?>-cuota">

                <div class="form-group form-full">
                    <label><?= $esPromesa ? 'Monto prometido' : 'Monto' ?></label>
                    <div class="monto-box">
                        <span class="monto-sign"><?= esc($m2) ?></span>
                        <input type="number" name="monto" id="<?= $pref ?>-monto" step="0.01" min="0.01" required placeholder="0.00">
                    </div>
                    <div class="mini-chips">
                        <button type="button" class="mini-chip" id="<?= $pref ?>-chip" hidden></button>
                    </div>
                </div>

                <?php if (!$esPromesa): ?>
                    <div class="form-group">
                        <label>Método</label>
                        <div class="seg">
                            <input type="radio" name="metodo" id="m-efe" value="EFECTIVO" checked>
                            <label for="m-efe"><?= icon('dollar-sign', 14) ?> Efectivo</label>
                            <input type="radio" name="metodo" id="m-tra" value="TRANSFERENCIA">
                            <label for="m-tra"><?= icon('refresh-cw', 14) ?> Transferencia</label>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="form-group">
                    <label><?= $esPromesa ? 'Fecha prometida' : 'Fecha del cobro' ?></label>
                    <?php if ($esPromesa): ?>
                        <input type="date" name="fecha_hora" required min="<?= date('Y-m-d') ?>"
                               value="<?= date('Y-m-d', strtotime('+1 day')) ?>">
                    <?php else: ?>
                        <input type="datetime-local" name="fecha_hora" value="<?= date('Y-m-d\TH:i') ?>">
                    <?php endif; ?>
                </div>

                <div class="form-group <?= $esPromesa ? '' : 'form-full' ?>">
                    <label>Observación</label>
                    <input type="text" name="observacion" maxlength="255"
                           placeholder="<?= $esPromesa ? 'Ej: pasa mañana después de las 5' : 'Nota del cobro (opcional)' ?>">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">
                    <?= icon($esPromesa ? 'clock' : 'check', 14) ?> <?= $esPromesa ? 'Guardar promesa' : 'Registrar pago' ?>
                </button>
            </div>
        </form>
    </div>
</div>
