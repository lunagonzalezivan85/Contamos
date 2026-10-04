<!DOCTYPE html>
<html lang="es">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?= esc($title ?? 'Fuera de horario') ?></title>
<link rel="stylesheet" href="<?= base_url('css/portal.css') ?>?v=2">
</head>
<body class="blk-body">
<div class="blk-card">
    <div class="blk-ico">
        <svg viewBox="0 0 24 24" width="34" height="34" fill="none" stroke="currentColor"
             stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
        </svg>
    </div>
    <h1>Fuera de horario</h1>
    <p>
        El horario de trabajo de <span class="blk-emp"><?= esc($tenant['nombre'] ?? 'la empresa') ?></span>
        es de <b><?= esc($h['ini'] ?? '--:--') ?></b> a <b><?= esc($h['fin'] ?? '--:--') ?></b>.
        Vas a poder seguir trabajando cuando vuelva a estar disponible.
    </p>
    <a class="blk-btn" href="<?= base_url($slug . '/portal/panel') ?>">Reintentar</a>
    <a class="blk-out" href="<?= base_url($slug . '/portal/salir') ?>">Cerrar sesión</a>
</div>
</body>
</html>
