<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Solicita tu crédito') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/landing.css') ?>">
    <style>
        .solic-wrap { min-height: calc(100vh - 140px); display: flex; align-items: flex-start; justify-content: center; padding: 48px 20px 60px; }
        .solic-card { width: 100%; max-width: 560px; }
        .solic-head { text-align: center; margin-bottom: 8px; }
        .solic-head h1 { font-size: 26px; font-weight: 800; color: var(--text); margin: 10px 0 6px; }
        .solic-head p { color: var(--text-muted); font-size: 14.5px; margin: 0; }
        .solic-ok { text-align: center; padding: 40px 24px; }
        .solic-ok-ico { width: 74px; height: 74px; margin: 0 auto 18px; border-radius: 50%; background: var(--primary-soft); color: var(--primary-dark); display: flex; align-items: center; justify-content: center; }
        .solic-ok h2 { font-size: 22px; margin: 0 0 8px; color: var(--text); }
        .solic-ok p { color: var(--text-muted); font-size: 14.5px; line-height: 1.6; margin: 0 0 22px; }
        .solic-aviso { display: flex; gap: 10px; align-items: flex-start; background: #FFF8E6; border: 1px solid #FDE9C8; color: #92610A; border-radius: 10px; padding: 12px 14px; font-size: 13px; margin-top: 18px; }
        .solic-hp { position: absolute; left: -9999px; opacity: 0; height: 0; overflow: hidden; }
        .flash-error { background: #FDECEA; border: 1px solid #F5C6C0; color: #B42318; border-radius: 10px; padding: 12px 14px; font-size: 13.5px; margin-bottom: 16px; }
    </style>
</head>
<body class="lp-body">

    <header class="lp-top">
        <a href="<?= base_url($slug . '/portal') ?>" class="lp-brand">
            <?php if (!empty($tenant['logo'])): ?>
                <img class="lp-logo" src="<?= base_url('public/uploads/logos/' . $tenant['logo']) ?>" alt="<?= esc($tenant['nombre']) ?>">
            <?php else: ?>
                <div class="lp-logo-fallback"><?= esc(mb_strtoupper(mb_substr($tenant['nombre'], 0, 1))) ?></div>
            <?php endif; ?>
            <span class="lp-brand-nombre"><?= esc($tenant['nombre']) ?></span>
        </a>
        <a href="<?= base_url($slug . '/portal/login') ?>" class="btn btn-primary btn-sm"><?= icon('user-check', 15) ?> Ingresar</a>
    </header>

    <div class="solic-wrap">
        <div class="card solic-card">

            <?php if ($enviada ?? false): ?>
                <div class="solic-ok">
                    <span class="solic-ok-ico"><?= icon('check', 34) ?></span>
                    <h2>¡Solicitud recibida!</h2>
                    <p>Gracias por confiar en <?= esc($tenant['nombre']) ?>.<br>
                    Un asesor te llamará pronto al número que dejaste para completar tu solicitud.</p>
                    <a href="<?= base_url($slug . '/portal') ?>" class="btn btn-outline">Volver al inicio</a>
                </div>
            <?php else: ?>

                <div class="solic-head">
                    <h1>Solicita tu crédito</h1>
                    <p>Deja tus datos y te llamamos para completar tu solicitud.</p>
                </div>

                <?php if (session()->getFlashdata('error')): ?>
                    <div class="flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
                <?php endif; ?>

                <form method="post" action="<?= base_url($slug . '/portal/solicitar') ?>" class="mt-3">
                    <?= csrf_field() ?>
                    <!-- honeypot antispam -->
                    <div class="solic-hp" aria-hidden="true"><input type="text" name="web" tabindex="-1" autocomplete="off"></div>

                    <div class="form-grid">
                        <div class="form-group">
                            <label for="nombres">Nombres *</label>
                            <input type="text" id="nombres" name="nombres" maxlength="100" required
                                   value="<?= esc(old('nombres')) ?>" placeholder="Tus nombres">
                        </div>
                        <div class="form-group">
                            <label for="apellidos">Apellidos *</label>
                            <input type="text" id="apellidos" name="apellidos" maxlength="100" required
                                   value="<?= esc(old('apellidos')) ?>" placeholder="Tus apellidos">
                        </div>
                        <div class="form-group">
                            <label for="telefono">Teléfono *</label>
                            <input type="text" id="telefono" name="telefono" maxlength="50" required
                                   value="<?= esc(old('telefono')) ?>" placeholder="8xxx xxxx">
                        </div>
                        <div class="form-group">
                            <label for="cedula">Cédula</label>
                            <input type="text" id="cedula" name="cedula" maxlength="30"
                                   value="<?= esc(old('cedula')) ?>" placeholder="001-000000-0000A">
                        </div>
                        <div class="form-group">
                            <label for="monto">¿Cuánto necesitas? (<?= esc($tenant['moneda'] ?? 'C$') ?>) *</label>
                            <input type="number" id="monto" name="monto" min="1" step="0.01" required
                                   value="<?= esc(old('monto')) ?>" placeholder="Ej: 50000">
                        </div>
                        <div class="form-group">
                            <label for="destino">¿Para qué lo usarás?</label>
                            <input type="text" id="destino" name="destino" maxlength="200"
                                   value="<?= esc(old('destino')) ?>" placeholder="Ej: Mi negocio, mercadería">
                        </div>
                    </div>

                    <button type="submit" class="btn btn-primary btn-lg" style="width:100%; margin-top:10px; justify-content:center;">
                        <?= icon('send', 16) ?> Enviar solicitud
                    </button>

                    <div class="solic-aviso">
                        <?= icon('clock', 15) ?>
                        <span>Al enviar, autorizás a <?= esc($tenant['nombre']) ?> a registrar y tratar
                        tus datos de contacto para evaluar tu solicitud de crédito (Ley No. 787,
                        Protección de Datos Personales). Un asesor te llamará para completar la
                        información.</span>
                    </div>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <footer class="lp-foot">
        <?php if (!empty($tenant['conami_registro'])): ?>
            <p class="lp-conami">
                <?= icon('shield', 15) ?> Registrada ante CONAMI · Reg. Nº <?= esc($tenant['conami_registro']) ?>
            </p>
        <?php endif; ?>
        <p>CONTAMOS - SOFTLUTIONIC - 2026</p>
    </footer>
</body>
</html>
