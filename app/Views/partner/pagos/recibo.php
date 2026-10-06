<?php
/**
 * Voucher imprimible del recibo de pago - página standalone.
 * Espera: $pago, $tenant, $reciboNum, $lblEstado, $metodos.
 */
$p   = $pago;
$est = $p['estado'];
$m2  = $mon ?? ($tenant['moneda'] ?? 'C$');
$cliente = trim(($p['nombres'] ?? '') . ' ' . ($p['apellidos'] ?? ''));
$cobro   = trim(($p['cob_nombres'] ?? '') . ' ' . ($p['cob_apellidos'] ?? '')) ?: 'Oficina';

$nota = [
    'REVISION'  => ['warn',
        'PAGO EN REVISIÓN - Este comprobante NO confirma el abono. El cobro está pendiente '
        . 'de validación en las cuentas bancarias o caja, y se aplicará al plan de cuotas '
        . 'únicamente cuando sea aprobado por la oficina.'],
    'APLICADO'  => ['ok',   'Pago aplicado al plan de cuotas del crédito.'],
    'RECHAZADO' => ['bad',  'Pago rechazado por la oficina - este comprobante no es válido.'],
    'REVERTIDO' => ['bad',  'Pago revertido - este comprobante quedó anulado.'],
][$est] ?? ['warn', $est];
?>
<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= esc($reciboNum) ?> - <?= esc($tenant['nombre'] ?? 'Recibo') ?></title>
<style>
    * { box-sizing: border-box; margin: 0; }
    body { font-family: 'Segoe UI', Arial, sans-serif; background: #EEF2F6; color: #172B4D; padding: 24px 12px; }
    .voucher {
        max-width: 420px; margin: 0 auto; background: #fff;
        border-radius: 14px; box-shadow: 0 6px 30px rgba(15,23,42,.12);
        overflow: hidden; position: relative;
    }
    .v-head { text-align: center; padding: 22px 24px 14px; border-bottom: 2px dashed #E5EAF1; }
    .v-head .v-emp { font-size: 17px; font-weight: 800; }
    .v-head .v-sub { font-size: 12px; color: #64748B; margin-top: 2px; }
    .v-head .v-tipo { font-size: 11px; font-weight: 700; letter-spacing: .12em; color: #64748B; margin-top: 12px; }
    .v-head .v-num { font-size: 22px; font-weight: 800; letter-spacing: .5px; color: #30CB9A; margin-top: 2px; font-variant-numeric: tabular-nums; }
    .v-body { padding: 16px 24px; }
    .v-row { display: flex; justify-content: space-between; gap: 14px; padding: 8px 0; border-bottom: 1px solid #F1F5F9; font-size: 13.5px; }
    .v-row:last-child { border: none; }
    .v-row .k { color: #64748B; font-weight: 600; font-size: 11.5px; text-transform: uppercase; letter-spacing: .04em; }
    .v-row .v { font-weight: 600; text-align: right; }
    .v-monto { text-align: center; padding: 16px 24px; background: #F0FBF7; }
    .v-monto .k { font-size: 11px; font-weight: 700; color: #64748B; text-transform: uppercase; letter-spacing: .08em; }
    .v-monto .v { font-size: 30px; font-weight: 800; color: #172B4D; font-variant-numeric: tabular-nums; }
    .v-nota { margin: 0 24px 18px; padding: 12px 14px; border-radius: 10px; font-size: 12px; line-height: 1.5; }
    .v-nota-warn { background: #FFF4E5; color: #B45309; border: 1px solid #FBD9A8; }
    .v-nota-ok   { background: #E6F9F1; color: #0E8A6A; border: 1px solid #A7EBD3; }
    .v-nota-bad  { background: #FDECEA; color: #B42318; border: 1px solid #F5B7B0; }
    .v-nota b { display: block; margin-bottom: 3px; font-size: 12.5px; }
    .v-firmas { display: flex; gap: 30px; padding: 26px 24px 30px; }
    .v-firma { flex: 1; text-align: center; }
    .v-firma .linea { border-top: 1px solid #94A3B8; margin-bottom: 5px; }
    .v-firma small { font-size: 11px; color: #64748B; }
    .v-actions { text-align: center; padding: 0 0 22px; display: flex; justify-content: center; gap: 10px; }
    .v-actions a, .v-actions button {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 18px; border-radius: 10px; font-size: 13px; font-weight: 700;
        text-decoration: none; border: 1.5px solid #CBD5E1; background: #fff; color: #334155;
        cursor: pointer; font-family: inherit;
    }
    .v-actions .pri { background: #30CB9A; border-color: #30CB9A; color: #fff; }
    .anulado {
        position: absolute; top: 46%; left: 50%;
        transform: translate(-50%, -50%) rotate(-14deg);
        font-size: 46px; font-weight: 900; letter-spacing: .15em;
        color: rgba(180, 35, 24, .14); pointer-events: none; white-space: nowrap;
    }
    @media print {
        body { background: #fff; padding: 0; }
        .voucher { box-shadow: none; border-radius: 0; max-width: none; }
        .v-actions { display: none; }
    }
</style>
</head>
<body>

<div class="voucher">
    <?php if (in_array($est, ['RECHAZADO', 'REVERTIDO'], true)): ?>
        <div class="anulado">ANULADO</div>
    <?php endif; ?>

    <div class="v-head">
        <div class="v-emp"><?= esc($tenant['nombre'] ?? '') ?></div>
        <div class="v-sub"><?= esc($tenant['direccion'] ?? 'Sistema de gestión de créditos') ?><?= !empty($tenant['ruc']) ? ' · RUC ' . esc($tenant['ruc']) : '' ?></div>
        <div class="v-tipo">RECIBO DE PAGO</div>
        <div class="v-num"><?= esc($reciboNum) ?></div>
    </div>

    <div class="v-body">
        <div class="v-row"><span class="k">Cliente</span><span class="v"><?= esc($cliente) ?></span></div>
        <div class="v-row"><span class="k">Cédula</span><span class="v"><?= esc($p['cedula'] ?? '-') ?></span></div>
        <div class="v-row"><span class="k">Crédito</span><span class="v"><?= esc($p['codigo_credito'] ?? '#' . $p['solicitud_id']) ?></span></div>
        <div class="v-row"><span class="k">Fecha</span><span class="v"><?= esc(date('d/m/Y', strtotime($p['fecha_hora']))) ?></span></div>
        <div class="v-row"><span class="k">Método</span><span class="v"><?= esc($metodos[$p['metodo']] ?? $p['metodo']) ?></span></div>
        <div class="v-row"><span class="k">Recibió</span><span class="v"><?= esc($cobro) ?></span></div>
        <div class="v-row"><span class="k">Estado</span><span class="v"><?= esc($lblEstado[$est] ?? $est) ?></span></div>
        <?php if (isset($saldo) && $saldo !== null): ?>
            <div class="v-row"><span class="k">Saldo pendiente</span><span class="v"><?= esc($m2) ?> <?= number_format((float) $saldo, 2) ?></span></div>
        <?php endif; ?>
        <?php if (!empty($p['observacion'])): ?>
            <div class="v-row"><span class="k">Nota</span><span class="v"><?= esc($p['observacion']) ?></span></div>
        <?php endif; ?>
    </div>

    <div class="v-monto">
        <div class="k">Monto recibido</div>
        <div class="v"><?= esc($m2) ?> <?= number_format((float) $p['monto'], 2) ?></div>
    </div>

    <div class="v-nota v-nota-<?= $nota[0] ?>">
        <b><?= $est === 'REVISION' ? '? Importante' : 'Estado del pago' ?></b>
        <?= esc($nota[1]) ?>
    </div>

    <div class="v-firmas">
        <div class="v-firma"><div class="linea"></div><small>Firma del cliente</small></div>
        <div class="v-firma"><div class="linea"></div><small>Recibió - <?= esc($cobro) ?></small></div>
    </div>

    <div class="v-actions">
        <a href="<?= base_url($volver ?? 'pagos') ?>">? Volver</a>
        <button type="button" class="pri" onclick="window.print()">Imprimir</button>
    </div>
</div>

<?php if (isset($_GET['print'])): ?>
<script>window.addEventListener('load', function () { window.print(); });</script>
<?php endif; ?>

</body>
</html>
