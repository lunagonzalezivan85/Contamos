<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Contamos — App del gestor') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Serif:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/contamos.css') ?>">
    <style>
        /* Página de descarga — reusa la paleta del landing */
        .dl-hero { padding: 64px 0 40px; }
        .dl-grid { display: grid; grid-template-columns: 1.1fr .9fr; gap: 48px; align-items: center; }
        .dl-card {
            border: 1px solid #E4E7EC; border-radius: 16px; background: #fff;
            padding: 28px; box-shadow: 0 12px 32px rgba(16,24,40,.06);
        }
        .dl-card h2 { margin: 0 0 4px; font-family: 'IBM Plex Serif', serif; font-size: 22px; }
        .dl-meta { margin: 0 0 18px; font-size: 13px; color: #667085; }
        .dl-meta b { color: #344054; }
        .dl-btn {
            display: inline-flex; align-items: center; gap: 10px;
            background: #101828; color: #fff; text-decoration: none;
            padding: 14px 22px; border-radius: 10px; font-weight: 600; font-size: 15px;
        }
        .dl-btn svg { width: 20px; height: 20px; }
        .dl-note { margin-top: 14px; font-size: 12.5px; color: #667085; }
        .dl-hero-ver { display: flex; align-items: baseline; gap: 10px; margin: 0 0 14px; }
        .dl-ver-num { font-family: 'IBM Plex Serif', serif; font-size: 34px; font-weight: 600; color: #101828; }
        .dl-ver-tag { font-size: 11px; font-weight: 600; text-transform: uppercase; letter-spacing: .5px; color: #067647; background: #ECFDF3; border: 1px solid #A6F4C5; padding: 3px 10px; border-radius: 999px; }
        .dl-versions { list-style: none; margin: 0; padding: 0; }
        .dl-versions li { display: flex; justify-content: space-between; align-items: center; padding: 7px 0; border-top: 1px dashed #EAECF0; font-size: 13px; }
        .dl-versions a { color: #101828; font-weight: 600; text-decoration: none; }
        .dl-versions a:hover { text-decoration: underline; }
        .dl-versions span { color: #667085; }
        .dl-steps { margin: 22px 0 0; padding: 0; list-style: none; counter-reset: paso; }
        .dl-steps li { counter-increment: paso; display: flex; gap: 12px; padding: 10px 0; font-size: 14px; color: #344054; }
        .dl-steps li::before {
            content: counter(paso); flex: none; width: 24px; height: 24px; border-radius: 50%;
            background: #F2F4F7; color: #344054; font-weight: 600; font-size: 12px;
            display: inline-flex; align-items: center; justify-content: center;
        }
        @media (max-width: 820px) { .dl-grid { grid-template-columns: 1fr; } }
    </style>
</head>
<body>

<header class="topbar">
    <div class="wrap topbar-in">
        <a class="wordmark" href="<?= base_url() ?>"><i class="mark">C</i>Contamos<span>.</span></a>
        <nav class="topnav">
            <a href="<?= base_url() ?>">Inicio</a>
            <a href="<?= base_url('login') ?>" class="topnav-cta">Iniciar sesión</a>
        </nav>
    </div>
</header>

<section class="dl-hero">
    <div class="wrap dl-grid">
        <div>
            <h1 style="font-family:'IBM Plex Serif',serif; font-size:38px; margin:0 0 14px;">
                La app del gestor,<br><em>en la calle contigo.</em>
            </h1>
            <p class="lede" style="max-width:46ch;">
                Cobra cuotas, consulta clientes, registra solicitudes y entrega
                desembolsos desde el celular — sin papel y con todo sincronizado
                con la oficina.
            </p>
        </div>

        <div class="dl-card">
            <h2>Descargar Contamos Gestor</h2>
            <p class="dl-meta">Android</p>

            <?php if (!empty($versiones)): ?>
                <?php $actual = $versiones[0]; ?>
                <div class="dl-hero-ver">
                    <span class="dl-ver-num">v<?= esc($vigente ?? $actual['version']) ?></span>
                    <span class="dl-ver-tag"><?= $vigente && $vigente === $actual['version'] ? 'Versión actual' : 'Más reciente' ?></span>
                </div>
                <?php if (!empty($mensaje)): ?>
                    <p class="dl-meta" style="margin-bottom:10px;"><?= esc($mensaje) ?></p>
                <?php endif; ?>
                <a class="dl-btn" href="<?= esc($actual['url']) ?>">
                    <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                    Descargar v<?= esc($actual['version']) ?> (<?= esc($actual['size']) ?>)
                </a>

                <?php if (count($versiones) > 1): ?>
                    <p class="dl-meta" style="margin:18px 0 6px;"><b>Versiones anteriores</b></p>
                    <ul class="dl-versions">
                        <?php foreach (array_slice($versiones, 1) as $vx): ?>
                            <li>
                                <a href="<?= esc($vx['url']) ?>">v<?= esc($vx['version']) ?></a>
                                <span><?= esc($vx['size']) ?></span>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            <?php else: ?>
                <p class="dl-meta"><b>La descarga se habilita muy pronto.</b></p>
            <?php endif; ?>

            <ol class="dl-steps">
                <li>Descargá el APK en el celular del gestor.</li>
                <li>Al instalar, Android pedirá «permitir orígenes desconocidos».</li>
                <li>Abrí la app y escribí el código de tu empresa (ej. CONT-0001).</li>
                <li>Ingresá con tu carnet y PIN de gestor.</li>
            </ol>

            <p class="dl-note">
                La app avisa cuando hay una versión nueva — instalá la actualización
                sobre la que ya tenés, sin perder la sesión.
            </p>
        </div>
    </div>
</section>

<footer class="foot">
    <div class="wrap foot-in">
        <span class="wordmark sm"><i class="mark">C</i>Contamos<span>.</span></span>
        <span class="foot-dir">Santa Teresa, Carazo &nbsp;·&nbsp; +505 7718 7005 &nbsp;·&nbsp; ventas@softlutionic</span>
        <small>&copy; <?= date('Y') ?> Contamos — Desarrollado por Softlutionic</small>
    </div>
</footer>

</body>
</html>
