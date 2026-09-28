<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($title ?? 'Suscripción vencida') ?></title>
<link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
<style>
    * { margin: 0; box-sizing: border-box; }
    body {
        font-family: 'Segoe UI', system-ui, sans-serif;
        background: linear-gradient(135deg, #1A202C 0%, #2E3542 100%);
        min-height: 100vh; display: flex; align-items: center; justify-content: center;
        padding: 20px;
    }
    .lock-card {
        background: #fff; border-radius: 20px; max-width: 460px; width: 100%;
        padding: 40px 36px; text-align: center;
        box-shadow: 0 24px 60px rgba(0,0,0,.35);
    }
    .lock-icon {
        width: 72px; height: 72px; margin: 0 auto 18px; border-radius: 22px;
        background: #FEF3C7; display: flex; align-items: center; justify-content: center;
        font-size: 36px;
    }
    h1 { font-size: 22px; color: #1A202C; margin-bottom: 8px; }
    .sub { color: #64748B; font-size: 14px; line-height: 1.55; margin-bottom: 20px; }
    .lock-info {
        background: #F8FAFC; border: 1px solid #E5EAF1; border-radius: 12px;
        padding: 14px 16px; margin-bottom: 20px; text-align: left; font-size: 13.5px;
    }
    .lock-info .row { display: flex; justify-content: space-between; padding: 4px 0; }
    .lock-info .k { color: #94A3B8; }
    .lock-info .v { font-weight: 600; color: #2E3542; }
    .lock-info .v.danger { color: #DC2626; }
    .flash { border-radius: 10px; padding: 10px 14px; font-size: 13px; margin-bottom: 14px; }
    .flash-success { background: #D1FAE5; color: #047857; }
    .flash-error   { background: #FEE2E2; color: #B91C1C; }
    form { text-align: left; margin-bottom: 18px; }
    label { display: block; font-size: 12.5px; font-weight: 600; color: #475569; margin: 10px 0 4px; }
    select, input {
        width: 100%; padding: 10px 12px; border: 1.5px solid #E2E8F0;
        border-radius: 10px; font-size: 14px; font-family: inherit;
    }
    select:focus, input:focus { outline: none; border-color: #30CB9A; }
    .btn-pay {
        width: 100%; margin-top: 16px; padding: 13px;
        background: #30CB9A; color: #fff; border: 0; border-radius: 12px;
        font-size: 15px; font-weight: 700; cursor: pointer;
        transition: background .15s;
    }
    .btn-pay:hover { background: #27B588; }
    .lock-out { display: block; margin-top: 6px; color: #94A3B8; font-size: 13px; text-decoration: none; }
    .lock-out:hover { color: #64748B; }
</style>
</head>
<body>
<div class="lock-card">
    <div class="lock-icon">🔒</div>
    <h1>Suscripción <?= strtolower($estado['estado'] ?? 'vencida') ?></h1>
    <p class="sub">
        <?= esc($estado['motivo'] ?? 'El servicio está pausado por falta de pago.') ?>
        Realice el pago para continuar usando el sistema.
    </p>

    <?php if (session()->getFlashdata('success')): ?>
        <div class="flash flash-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('error')): ?>
        <div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>

    <div class="lock-info">
        <div class="row"><span class="k">Empresa</span><span class="v"><?= esc($tenant) ?></span></div>
        <div class="row"><span class="k">Plan</span><span class="v"><?= esc($plan['nombre'] ?? '—') ?></span></div>
        <div class="row"><span class="k">Mensualidad</span><span class="v"><?= esc($plan['moneda'] ?? 'USD') ?> <?= number_format((float) ($plan['precio_mensual'] ?? 0), 2) ?></span></div>
        <div class="row"><span class="k">Período</span><span class="v"><?= esc(date('Y-m')) ?></span></div>
        <div class="row"><span class="k">Estado</span><span class="v danger"><?= esc($estado['estado'] ?? 'VENCIDA') ?></span></div>
        <?php if (!empty($estado['ultimo_pago'])): ?>
        <div class="row"><span class="k">Último pago</span><span class="v"><?= esc($estado['ultimo_pago']) ?></span></div>
        <?php endif; ?>
    </div>

    <form method="post" action="<?= base_url('cuenta-suspendida/reportar') ?>">
        <?= csrf_field() ?>
        <label for="metodo">Método de pago</label>
        <select name="metodo" id="metodo">
            <option value="TRANSFERENCIA">Transferencia</option>
            <option value="DEPOSITO">Depósito</option>
            <option value="EFECTIVO">Efectivo</option>
        </select>
        <label for="referencia">Referencia / comprobante (opcional)</label>
        <input type="text" name="referencia" id="referencia" maxlength="60"
               placeholder="N° de transferencia, recibo, etc.">
        <button type="submit" class="btn-pay">Realizar pago para continuar</button>
    </form>

    <a class="lock-out" href="<?= base_url('logout') ?>">Cerrar sesión</a>
</div>
</body>
</html>
