<?php
/**
 * Layout del portal de gestor — PWA instalable.
 * Móvil: navbar superior + tab bar inferior. Escritorio: sidebar lateral.
 * Espera: $tenant (id, nombre, logo), $title, $slug.
 */
$logueado = (bool) session('portal_empleado_id')
    && (int) session('portal_tenant_id') === (int) ($tenant['id'] ?? 0);

// Notificaciones del gestor: clientes con datos incompletos asignados a él
$notifs = $logueado
    ? (new \App\Services\Partner\PortalService())
        ->notificacionesGestor((int) $tenant['id'], (int) session('portal_empleado_id'))
    : [];

$seg   = service('uri')->getSegment(3) ?: 'panel';
$home  = $slug . ($logueado ? '/portal/panel' : '/portal');

// Tab activa en la barra inferior (agrupa secciones relacionadas)
$tabMap = [
    'panel'        => 'inicio',
    'actividad'    => 'inicio',
    'solicitud'    => 'nueva',
    'cartera'      => 'cartera',
    'desembolso'   => 'cartera',
    'cobros'       => 'cobros',
    'arqueo'       => 'cartera',
    'calculadora'  => 'calc',
    'perfil'       => 'perfil',
];
$tabOn = $tabMap[$seg] ?? '';

// Menú del sidebar (desktop) — una entrada por sección
$sideItems = [
    ['seg' => 'panel',        'lbl' => 'Inicio',              'ico' => 'home',        'url' => $slug . '/portal/panel'],
    ['seg' => 'solicitud',    'lbl' => 'Nueva solicitud',     'ico' => 'file-plus',   'url' => $slug . '/portal/solicitud'],
    ['seg' => 'desembolso',   'lbl' => 'Desembolso',          'ico' => 'dollar-sign', 'url' => $slug . '/portal/desembolso'],
    ['seg' => 'cobros',       'lbl' => 'Cobros',              'ico' => 'credit-card', 'url' => $slug . '/portal/cobros'],
    ['seg' => 'arqueo',       'lbl' => 'Mi caja',             'ico' => 'clipboard',   'url' => $slug . '/portal/arqueo'],
    ['seg' => 'cartera',      'lbl' => 'Cartera de clientes', 'ico' => 'briefcase',   'url' => $slug . '/portal/cartera'],
    ['seg' => 'actividad',    'lbl' => 'Actividad reciente',  'ico' => 'activity',    'url' => $slug . '/portal/actividad'],
    ['seg' => 'calculadora',  'lbl' => 'Calculadora',         'ico' => 'percent',     'url' => $slug . '/portal/calculadora'],
    ['seg' => 'perfil',       'lbl' => 'Mi perfil',           'ico' => 'user',        'url' => $slug . '/portal/perfil'],
];
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <title><?= esc($title ?? 'Portal') ?></title>

    <!-- PWA -->
    <link rel="manifest" href="<?= base_url($slug . '/portal/manifest.webmanifest') ?>">
    <meta name="theme-color" content="#30CB9A">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="default">
    <meta name="apple-mobile-web-app-title" content="<?= esc($tenant['nombre']) ?>">
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="apple-touch-icon" href="<?= base_url('public/icons/apple-touch-icon.png') ?>">

    <link rel="stylesheet" href="<?= v_asset('css/app.css') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/portal.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body class="portal-body<?= $logueado ? ' has-tabs' : '' ?>">

<!-- ============ Navbar superior (móvil) ============ -->
<header class="app-navbar">
    <div class="app-navbar-inner">
        <a href="<?= base_url($home) ?>" class="app-nav-brand">
            <?php if (!empty($tenant['logo'])): ?>
                <img class="app-nav-logo" src="<?= base_url('public/uploads/logos/' . $tenant['logo']) ?>" alt="<?= esc($tenant['nombre']) ?>">
            <?php else: ?>
                <span class="app-nav-logo-fallback"><?= esc(mb_strtoupper(mb_substr($tenant['nombre'], 0, 1))) ?></span>
            <?php endif; ?>
            <span class="app-nav-txt">
                <strong><?= esc($tenant['nombre']) ?></strong>
                <small>Portal de gestores</small>
            </span>
        </a>
        <span class="app-nav-actions">
            <button type="button" class="app-nav-btn app-install" title="Instalar app" hidden><?= icon('plus-circle', 18) ?></button>
            <?php if ($logueado): ?>
                <button type="button" class="app-nav-btn" id="notif-btn" title="Notificaciones" aria-label="Notificaciones">
                    <?= icon('bell', 18) ?>
                    <?php if (count($notifs) > 0): ?>
                        <span class="app-nav-badge"><?= count($notifs) ?></span>
                    <?php endif; ?>
                </button>
            <?php endif; ?>
            <?php if ($logueado): ?>
                <a href="<?= base_url($slug . '/portal/salir') ?>" class="app-nav-btn" title="Cerrar sesión"><?= icon('log-out', 18) ?></a>
            <?php else: ?>
                <a href="<?= base_url($slug . '/portal/login') ?>" class="app-nav-btn" title="Ingresar"><?= icon('user-check', 18) ?></a>
            <?php endif; ?>
        </span>
    </div>
</header>

<!-- Panel de notificaciones (gestor) -->
<?php if ($logueado): ?>
<div class="app-notif-panel" id="notif-panel" hidden>
    <div class="app-notif-head">
        <strong>Datos incompletos</strong>
        <small><?= count($notifs) ?> solicitud(es) por completar</small>
    </div>
    <?php if (empty($notifs)): ?>
        <p class="app-notif-empty">Todo al día — ningún cliente asignado tiene datos pendientes.</p>
    <?php else: ?>
        <div class="app-notif-list">
            <?php foreach ($notifs as $n): ?>
                <a class="app-notif-item" href="<?= base_url($slug . '/portal/cliente/' . $n['cliente_id']) ?>">
                    <span class="oui-icon"><?= icon('alert-circle', 16) ?></span>
                    <span class="oui-body">
                        <span class="oui-title"><?= esc($n['nombre']) ?></span>
                        <span class="oui-sub"><?= esc($n['codigo']) ?> · falta: <?= esc($n['faltan']) ?></span>
                    </span>
                    <?= icon('chevron-right', 15) ?>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>
<?php endif; ?>

<!-- ============ Sidebar lateral (desktop) ============ -->
<?php if ($logueado): ?>
<aside class="app-sidebar">
    <a href="<?= base_url($home) ?>" class="app-side-brand">
        <?php if (!empty($tenant['logo'])): ?>
            <img class="app-nav-logo" src="<?= base_url('public/uploads/logos/' . $tenant['logo']) ?>" alt="<?= esc($tenant['nombre']) ?>">
        <?php else: ?>
            <span class="app-nav-logo-fallback"><?= esc(mb_strtoupper(mb_substr($tenant['nombre'], 0, 1))) ?></span>
        <?php endif; ?>
        <span class="app-nav-txt">
            <strong><?= esc($tenant['nombre']) ?></strong>
            <small>Portal de gestores</small>
        </span>
    </a>

    <nav class="app-side-nav">
        <?php foreach ($sideItems as $it): ?>
            <a href="<?= base_url($it['url']) ?>" class="app-side-link <?= $seg === $it['seg'] ? 'on' : '' ?>">
                <?= icon($it['ico'], 18) ?><span><?= esc($it['lbl']) ?></span>
            </a>
        <?php endforeach; ?>
    </nav>

    <div class="app-side-foot">
        <button type="button" class="app-side-link app-install" hidden><?= icon('plus-circle', 18) ?><span>Instalar app</span></button>
        <a href="<?= base_url($slug . '/portal/salir') ?>" class="app-side-link app-side-out"><?= icon('log-out', 18) ?><span>Cerrar sesión</span></a>
    </div>
</aside>
<div class="app-side-scrim" id="app-side-scrim" hidden></div>
<?php endif; ?>

<div class="portal-shell">
    <?php if (session()->getFlashdata('error')): ?>
        <div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
    <?php endif; ?>
    <?php if (session()->getFlashdata('success')): ?>
        <div class="flash flash-success"><?= esc(session()->getFlashdata('success')) ?></div>
    <?php endif; ?>

    <?= $this->renderSection('content') ?>

    <p class="portal-foot">Acceso seguro con carnet y PIN · <?= esc($tenant['nombre']) ?></p>
</div>

<!-- ============ Tab bar inferior (móvil) ============ -->
<?php if ($logueado): ?>
<nav class="app-tabbar">
    <a href="<?= base_url($slug . '/portal/panel') ?>" class="app-tab <?= $tabOn === 'inicio' ? 'on' : '' ?>">
        <span class="app-tab-ico"><?= icon('home', 20) ?></span>
        <span class="app-tab-lbl">Inicio</span>
    </a>
    <a href="<?= base_url($slug . '/portal/cartera') ?>" class="app-tab <?= $tabOn === 'cartera' ? 'on' : '' ?>">
        <span class="app-tab-ico"><?= icon('briefcase', 20) ?></span>
        <span class="app-tab-lbl">Cartera</span>
    </a>
    <a href="<?= base_url($slug . '/portal/solicitud') ?>" class="app-tab app-tab-cta <?= $tabOn === 'nueva' ? 'on' : '' ?>">
        <span class="app-tab-fab"><?= icon('file-plus', 22) ?></span>
        <span class="app-tab-lbl">Nueva</span>
    </a>
    <a href="<?= base_url($slug . '/portal/cobros') ?>" class="app-tab <?= $tabOn === 'cobros' ? 'on' : '' ?>">
        <span class="app-tab-ico"><?= icon('credit-card', 20) ?></span>
        <span class="app-tab-lbl">Cobrar</span>
    </a>
    <button type="button" class="app-tab" id="app-menu-btn" aria-label="Abrir menú">
        <span class="app-tab-ico"><?= icon('menu', 20) ?></span>
        <span class="app-tab-lbl">Menú</span>
    </button>
</nav>
<?php endif; ?>

<!-- PWA — service worker + botón instalar -->
<script>
if ('serviceWorker' in navigator) {
    // /cfsi/sw.js reescribe a public/sw.js → el scope por defecto ya cubre {slug}/portal/
    navigator.serviceWorker.register('<?= base_url('sw.js') ?>').catch(() => {});
}
let _pwaPrompt = null;
window.addEventListener('beforeinstallprompt', (e) => {
    e.preventDefault();
    _pwaPrompt = e;
    document.querySelectorAll('.app-install').forEach((b) => { b.hidden = false; });
});
document.querySelectorAll('.app-install').forEach((b) => {
    b.addEventListener('click', async () => {
        if (!_pwaPrompt) return;
        _pwaPrompt.prompt();
        await _pwaPrompt.userChoice;
        _pwaPrompt = null;
        document.querySelectorAll('.app-install').forEach((x) => { x.hidden = true; });
    });
});

/* Drawer del menú lateral en móvil (botón "Menú" del tab bar) */
const _side   = document.querySelector('.app-sidebar');
const _scrim  = document.getElementById('app-side-scrim');
const _mBtn   = document.getElementById('app-menu-btn');
if (_side && _scrim && _mBtn) {
    const _cerrar = () => { _side.classList.remove('open'); _scrim.hidden = true; };
    _mBtn.addEventListener('click', () => {
        const abrir = !_side.classList.contains('open');
        _side.classList.toggle('open', abrir);
        _scrim.hidden = !abrir;
    });
    _scrim.addEventListener('click', _cerrar);
    _side.querySelectorAll('.app-side-link[href]').forEach((a) => {
        a.addEventListener('click', _cerrar);
    });
}

/* Panel de notificaciones (campana del navbar) */
const _nBtn   = document.getElementById('notif-btn');
const _nPanel = document.getElementById('notif-panel');
if (_nBtn && _nPanel) {
    _nBtn.addEventListener('click', (e) => {
        e.stopPropagation();
        _nPanel.hidden = !_nPanel.hidden;
    });
    document.addEventListener('click', (e) => {
        if (!e.target.closest('#notif-panel') && !e.target.closest('#notif-btn')) {
            _nPanel.hidden = true;
        }
    });
}
</script>

<script src="<?= v_asset('js/portal.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
