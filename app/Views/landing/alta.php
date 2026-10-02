<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Contamos — Da de alta tu negocio') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/contamos.css') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/alta.css') ?>">
</head>
<body>

<header class="topbar">
    <div class="wrap topbar-in">
        <a class="wordmark" href="<?= base_url() ?>"><i class="mark">C</i>Contamos<span>.</span></a>
        <nav class="topnav">
            <a href="<?= base_url('descargar') ?>">App</a>
            <a href="<?= base_url('#contacto') ?>">Contacto</a>
            <a href="<?= base_url('login') ?>" class="topnav-cta">Iniciar sesión</a>
        </nav>
    </div>
</header>

<section class="alta-wrap">
    <div class="calc">

        <h1 class="calc-title">Armá tu plan a medida</h1>
        <p class="calc-sub">
            Partís del <b>Plan <?= esc($base['nombre'] ?? 'Básico') ?></b> —
            USD <?= number_format((float) ($base['precio_mensual'] ?? 35), 0) ?>/mes con
            <?= (int) ($base['max_usuarios'] ?? 3) ?> usuarios,
            <?= (int) ($base['max_creditos_activos'] ?? 50) ?> créditos activos y
            <?= (int) ($base['max_empleados'] ?? 5) ?> empleados incluidos.
        </p>

        <!-- Sliders — los límites base se inyectan desde el plan real -->
        <div class="calc-campo">
            <label>Usuarios del sistema: <b id="lblUsuarios"><?= (int) ($base['max_usuarios'] ?? 3) ?></b></label>
            <input type="range" id="inpUsuarios" min="1" max="25" value="<?= (int) ($base['max_usuarios'] ?? 3) ?>">
        </div>
        <div class="calc-campo">
            <label>Clientes totales: <b id="lblClientes">20</b></label>
            <input type="range" id="inpClientes" min="10" max="500" step="5" value="20">
        </div>
        <div class="calc-campo">
            <label>Créditos activos: <b id="lblCreditos"><?= (int) ($base['max_creditos_activos'] ?? 50) ?></b></label>
            <input type="range" id="inpCreditos" min="20" max="1000" step="10" value="<?= (int) ($base['max_creditos_activos'] ?? 50) ?>">
        </div>
        <div class="calc-campo">
            <label>Empleados: <b id="lblEmpleados"><?= (int) ($base['max_empleados'] ?? 5) ?></b></label>
            <input type="range" id="inpEmpleados" min="1" max="50" value="<?= (int) ($base['max_empleados'] ?? 5) ?>">
        </div>

        <!-- Precio estimado -->
        <div class="calc-precio">
            <span class="calc-precio-lbl">Precio estimado mensual</span>
            <div class="calc-precio-num">
                $<span id="txtTotal"><?= number_format((float) ($base['precio_mensual'] ?? 35), 2) ?></span>
                <small>USD</small>
            </div>
        </div>

        <?php if (session('alta_codigo')): ?>
            <!-- Lead enviado — código partner -->
            <div class="calc-ok">
                <p class="calc-ok-t">¡Solicitud enviada con éxito!</p>
                <p><?= esc(session('alta_ok')) ?></p>
                <p class="calc-ok-codigo">Tu código de partner es <b><?= esc(session('alta_codigo')) ?></b></p>
            </div>
        <?php else: ?>
            <!-- Formulario de activación — los sliders viajan en hidden inputs -->
            <form class="calc-form" method="post" action="<?= base_url('alta') ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="usuarios"  id="hidUsuarios"  value="<?= (int) ($base['max_usuarios'] ?? 3) ?>">
                <input type="hidden" name="clientes"  id="hidClientes"  value="20">
                <input type="hidden" name="creditos"  id="hidCreditos"  value="<?= (int) ($base['max_creditos_activos'] ?? 50) ?>">
                <input type="hidden" name="empleados" id="hidEmpleados" value="<?= (int) ($base['max_empleados'] ?? 5) ?>">

                <?php if (session('alta_error')): ?>
                <p class="form-err"><?= esc(session('alta_error')) ?></p>
                <?php endif; ?>

                <input type="text" name="nombre" placeholder="Nombre o Empresa" required maxlength="160"
                       value="<?= esc(old('nombre') ?? '') ?>">
                <input type="tel" name="telefono" placeholder="WhatsApp / Teléfono" required maxlength="30"
                       value="<?= esc(old('telefono') ?? '') ?>">
                <button type="submit">Solicitar activación &amp; código partner</button>
            </form>
        <?php endif; ?>

        <p class="calc-pie">Precio referencial — el plan final lo confirmamos juntos al activar.</p>
    </div>
</section>

<script>
    // Constantes del plan Básico (inyectadas desde el server — tabla planes)
    window.ALTA_BASE = <?= json_encode([
        'precio'    => (float) ($base['precio_mensual'] ?? 35),
        'usuarios'  => (int) ($base['max_usuarios'] ?? 3),
        'clientes'  => 20,
        'creditos'  => (int) ($base['max_creditos_activos'] ?? 50),
        'empleados' => (int) ($base['max_empleados'] ?? 5),
    ]) ?>;
</script>
<script src="<?= v_asset('js/alta.js') ?>"></script>

</body>
</html>
