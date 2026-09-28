<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Portal') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/landing.css') ?>">
</head>
<body class="lp-body">

    <!-- ============ Barra superior ============ -->
    <header class="lp-top">
        <a href="<?= base_url($slug . '/portal') ?>" class="lp-brand">
            <?php if (!empty($tenant['logo'])): ?>
                <img class="lp-logo" src="<?= base_url('public/uploads/logos/' . $tenant['logo']) ?>" alt="<?= esc($tenant['nombre']) ?>">
            <?php else: ?>
                <div class="lp-logo-fallback"><?= esc(mb_strtoupper(mb_substr($tenant['nombre'], 0, 1))) ?></div>
            <?php endif; ?>
            <span class="lp-brand-nombre"><?= esc($tenant['nombre']) ?></span>
        </a>
        <nav style="display:flex; gap:10px; align-items:center;">
            <a href="<?= base_url($slug . '/portal/solicitar') ?>" class="btn btn-outline btn-sm"><?= icon('file-text', 15) ?> Solicitar crédito</a>
            <a href="<?= base_url($slug . '/portal/login') ?>" class="btn btn-primary btn-sm"><?= icon('user-check', 15) ?> Ingresar</a>
        </nav>
    </header>

    <!-- ============ HERO — nombre de la empresa ============ -->
    <section class="lp-hero">
        <div class="lp-hero-inner">
            <?php if (!empty($tenant['logo'])): ?>
                <img class="lp-hero-logo" src="<?= base_url('public/uploads/logos/' . $tenant['logo']) ?>" alt="<?= esc($tenant['nombre']) ?>">
            <?php endif; ?>
            <h1 class="lp-titulo"><?= esc($tenant['nombre']) ?></h1>
            <?php if (!empty($tenant['razon_social']) && $tenant['razon_social'] !== $tenant['nombre']): ?>
                <p class="lp-razon"><?= esc($tenant['razon_social']) ?></p>
            <?php endif; ?>
            <div class="lp-cta">
                <a href="<?= base_url($slug . '/portal/solicitar') ?>" class="btn btn-primary btn-lg"><?= icon('file-text', 18) ?> Solicita tu crédito</a>
                <?php if (!empty($logueado)): ?>
                    <a href="<?= base_url($slug . '/portal/panel') ?>" class="btn btn-outline btn-lg"><?= icon('user-check', 18) ?> Abrir mi portal</a>
                <?php else: ?>
                    <a href="<?= base_url($slug . '/portal/login') ?>" class="btn btn-outline btn-lg"><?= icon('user-check', 18) ?> Ingresar como asesor</a>
                <?php endif; ?>
            </div>
        </div>
        <div class="lp-hero-wave"></div>
    </section>

    <!-- ============ Quiénes somos ============ -->
    <section class="lp-sec">
        <div class="lp-wrap">
            <span class="lp-sec-tag">Sobre nosotros</span>
            <h2 class="lp-sec-titulo">Quiénes somos</h2>
            <p class="lp-about-text">
                <?= !empty($tenant['quienes_somos'])
                    ? nl2br(esc($tenant['quienes_somos']))
                    : 'Somos una institución financiera comprometida con el desarrollo económico de nuestros clientes. Brindamos soluciones de crédito ágiles, transparentes y cercanas a las comunidades que servimos.' ?>
            </p>
        </div>
    </section>

    <!-- ============ Misión / Visión / Valores ============ -->
    <section class="lp-sec lp-sec-alt">
        <div class="lp-wrap">
            <div class="lp-grid lp-grid-mvv">
                <div class="lp-card lp-mvv">
                    <span class="lp-card-ico"><?= icon('target', 22) ?></span>
                    <h3>Misión</h3>
                    <p class="lp-mvv-text"><?= !empty($tenant['mision']) ? nl2br(esc($tenant['mision'])) : 'Facilitar el acceso al crédito responsable, apoyando el crecimiento de emprendedores y familias.' ?></p>
                </div>
                <div class="lp-card lp-mvv">
                    <span class="lp-card-ico"><?= icon('eye', 22) ?></span>
                    <h3>Visión</h3>
                    <p class="lp-mvv-text"><?= !empty($tenant['vision']) ? nl2br(esc($tenant['vision'])) : 'Ser la institución financiera de referencia, reconocida por su cercanía, agilidad y compromiso social.' ?></p>
                </div>
                <div class="lp-card lp-mvv">
                    <span class="lp-card-ico"><?= icon('heart', 22) ?></span>
                    <h3>Valores</h3>
                    <p class="lp-mvv-text"><?= !empty($tenant['valores']) ? nl2br(esc($tenant['valores'])) : 'Transparencia, compromiso, responsabilidad y cercanía con nuestros clientes.' ?></p>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ Contacto ============ -->
    <section class="lp-sec">
        <div class="lp-wrap">
            <span class="lp-sec-tag">Contáctanos</span>
            <h2 class="lp-sec-titulo">Estamos para ayudarte</h2>
            <div class="lp-grid lp-grid-contact">
                <div class="lp-card">
                    <span class="lp-card-ico"><?= icon('map-pin', 22) ?></span>
                    <h3>Ubicación</h3>
                    <p><?= esc($tenant['direccion'] ?: 'Comunidad') ?></p>
                </div>
                <div class="lp-card">
                    <span class="lp-card-ico"><?= icon('phone', 22) ?></span>
                    <h3>Contacto</h3>
                    <?php if (!empty($tenant['telefono'])): ?><p><?= icon('phone', 13) ?> <?= esc($tenant['telefono']) ?></p><?php endif; ?>
                    <?php if (!empty($tenant['email'])): ?><p><?= icon('mail', 13) ?> <?= esc($tenant['email']) ?></p><?php endif; ?>
                    <?php if (!empty($tenant['contacto_nombre'])): ?><p class="lp-muted"><?= esc($tenant['contacto_nombre']) ?> <?= !empty($tenant['contacto_cargo']) ? '· ' . esc($tenant['contacto_cargo']) : '' ?></p><?php endif; ?>
                    <?php if (empty($tenant['telefono']) && empty($tenant['email']) && empty($tenant['contacto_nombre'])): ?><p>Escríbenos o visítanos.</p><?php endif; ?>
                </div>
                <div class="lp-card">
                    <span class="lp-card-ico"><?= icon('clock', 22) ?></span>
                    <h3>Horario</h3>
                    <?php if (!empty($tenant['horario'])): ?><p><?= esc($tenant['horario']) ?></p><?php endif; ?>
                    <?php if (!empty($tenant['hora_inicio']) || !empty($tenant['hora_fin'])): ?>
                        <p><?= icon('clock', 13) ?> <?= esc(substr((string) $tenant['hora_inicio'], 0, 5)) ?> – <?= esc(substr((string) $tenant['hora_fin'], 0, 5)) ?></p>
                    <?php endif; ?>
                    <?php if (empty($tenant['horario']) && empty($tenant['hora_inicio']) && empty($tenant['hora_fin'])): ?><p>Lunes a viernes, horario de oficina.</p><?php endif; ?>
                </div>
            </div>
        </div>
    </section>

    <!-- ============ CTA final ============ -->
    <section class="lp-cta-band">
        <div class="lp-wrap">
            <h2>¿Sos asesor de <?= esc($tenant['nombre']) ?>?</h2>
            <p>Ingresa a tu portal para gestionar solicitudes, cartera y recuperación.</p>
            <a href="<?= base_url($slug . '/portal/login') ?>" class="btn btn-lg lp-btn-light"><?= icon('user-check', 18) ?> Ingresar al portal</a>
        </div>
    </section>

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
