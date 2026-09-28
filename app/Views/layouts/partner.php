<?php
/**
 * Layout partner (tenant) — One UI style
 * Sidebar desplegable + paleta de comandos (Ctrl+K)
 * Menú desde menu_items() — resuelto por request desde Config/Menu.php
 */
$menus  = menu_items();
$nombre = session('nombre') ?? 'Usuario';
$tenant = session('tenant_name') ?? 'Contamos';
$tSlug  = (string) session('tenant_slug');
// Valoración del sistema — modal cada 5 días desde la última calificación
$valoracionPendiente = (new \App\Services\Partner\ValoracionService())
    ->pendiente((int) session('tenant_id'), (int) session('user_id'));
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Contamos') ?></title>
    <meta name="app-base" content="<?= base_url() ?>">
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/app.css') ?>">
    <?= $this->renderSection('head') ?>
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="dot">C</div>
        <span><?= esc($tenant) ?></span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menus as $item): ?>
            <div class="nav-item">
                <?php if (!empty($item['children'])): ?>
                    <button type="button" class="nav-link nav-toggle" data-target="nav-<?= esc(md5($item['nombre'])) ?>">
                        <span class="nav-icon"><?= icon($item['icono'] ?? 'menu') ?></span>
                        <span class="nav-text"><?= esc($item['nombre']) ?></span>
                        <span class="chevron"><?= icon('chevron-down', 14) ?></span>
                    </button>
                    <div class="nav-children" id="nav-<?= esc(md5($item['nombre'])) ?>">
                        <?php foreach ($item['children'] as $child): ?>
                            <a class="nav-link" href="<?= base_url(ltrim($child['url'] ?? '#', '/')) ?>">
                                <span class="nav-icon"><?= icon($child['icono'] ?? 'menu', 16) ?></span>
                                <span class="nav-text"><?= esc($child['nombre']) ?></span>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a class="nav-link" href="<?= base_url(ltrim($item['url'] ?? '#', '/')) ?>">
                        <span class="nav-icon"><?= icon($item['icono'] ?? 'menu') ?></span>
                        <span class="nav-text"><?= esc($item['nombre']) ?></span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>

        <!-- Portales públicos del tenant: kiosco de asistencia + portal de gestores -->
        <?php if ($tSlug !== ''): ?>
        <div class="nav-portales">
            <span class="nav-portales-label">Portales</span>
            <div class="nav-portal-row">
                <a class="nav-link" href="<?= base_url($tSlug . '/asistencia') ?>" target="_blank" rel="noopener">
                    <span class="nav-icon"><?= icon('clock', 16) ?></span>
                    <span class="nav-text">Kiosco asistencia</span>
                </a>
                <button type="button" class="nav-qr" title="Ver QR — Kiosco de asistencia"
                        data-qr="<?= base_url($tSlug . '/asistencia') ?>" data-qr-title="Kiosco de Asistencia">
                    <?= icon('qr-code', 15) ?>
                </button>
            </div>
            <div class="nav-portal-row">
                <a class="nav-link" href="<?= base_url($tSlug . '/portal') ?>" target="_blank" rel="noopener">
                    <span class="nav-icon"><?= icon('user-check', 16) ?></span>
                    <span class="nav-text">Portal gestores</span>
                </a>
                <button type="button" class="nav-qr" title="Ver QR — Portal de gestores"
                        data-qr="<?= base_url($tSlug . '/portal') ?>" data-qr-title="Portal de Gestores">
                    <?= icon('qr-code', 15) ?>
                </button>
            </div>
        </div>
        <?php endif; ?>
    </nav>
    <div class="sidebar-footer">
        <?= esc($nombre) ?> · <a href="<?= base_url('logout') ?>">Salir</a>
    </div>
</aside>
<div class="sidebar-scrim" id="sidebar-scrim" hidden></div>

<div class="main">
    <div class="topbar">
        <button type="button" class="topbar-menu" id="sidebar-toggle" aria-label="Abrir menú">
            <?= icon('menu', 20) ?>
        </button>
        <h2><?= esc($title ?? 'Contamos') ?></h2>
        <div class="topbar-actions">
            <button type="button" class="palette-trigger" id="palette-trigger" title="Acciones rápidas (Ctrl+K)">
                <span class="palette-trigger-icon">⌕</span> Buscar… <kbd>Ctrl K</kbd>
            </button>
            <button type="button" class="notif-bell" id="notif-bell" title="Notificaciones">
                <?= icon('bell', 20) ?>
                <span class="notif-badge" id="notif-badge" hidden>0</span>
            </button>
            <span class="user"><?= esc($nombre) ?></span>
        </div>
    </div>
    <div class="content">
        <?php if (session()->getFlashdata('error')): ?>
            <div class="flash flash-error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('warning')): ?>
            <div class="flash flash-warning"><?= esc(session()->getFlashdata('warning')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="flash flash-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>

        <?= $this->renderSection('content') ?>
    </div>
</div>

<!-- Paleta de comandos (Ctrl+K) — items renderizados server-side, JS solo filtra -->
<div class="palette-overlay" id="palette" hidden>
    <div class="palette-box">
        <input type="text" id="palette-input" class="palette-input"
               placeholder="Escribe una acción o página…" autocomplete="off">
        <ul class="palette-list" id="palette-list">
            <?php foreach ($menus as $item): ?>
                <?php if (!empty($item['children'])): ?>
                    <?php foreach ($item['children'] as $child): ?>
                        <li class="palette-item" data-url="<?= base_url(ltrim($child['url'] ?? '#', '/')) ?>">
                            <span class="palette-item-name"><?= esc($child['nombre']) ?></span>
                            <span class="palette-item-group"><?= esc($item['nombre']) ?></span>
                        </li>
                    <?php endforeach; ?>
                <?php else: ?>
                    <li class="palette-item" data-url="<?= base_url(ltrim($item['url'] ?? '#', '/')) ?>">
                        <span class="palette-item-name"><?= esc($item['nombre']) ?></span>
                        <span class="palette-item-group">Menú</span>
                    </li>
                <?php endif; ?>
            <?php endforeach; ?>
            <li class="palette-item" data-url="<?= base_url('perfil/cambiar-password') ?>">
                <span class="palette-item-name">Cambiar contraseña</span>
                <span class="palette-item-group">Cuenta</span>
            </li>
            <li class="palette-item" data-url="<?= base_url('logout') ?>">
                <span class="palette-item-name">Cerrar sesión</span>
                <span class="palette-item-group">Cuenta</span>
            </li>
        </ul>
        <div class="palette-empty" id="palette-empty" hidden>Sin resultados</div>
    </div>
</div>

<!-- Panel de notificaciones (derecho) -->
<div class="notif-overlay" id="notif-overlay" hidden></div>
<aside class="notif-panel" id="notif-panel" hidden>
    <div class="notif-header">
        <h3>Notificaciones</h3>
        <div class="notif-header-actions">
            <button type="button" class="notif-read-all" id="notif-read-all" title="Marcar todas como leídas">
                <?= icon('check', 16) ?>
            </button>
            <button type="button" class="notif-close" id="notif-close" title="Cerrar">
                <?= icon('x', 18) ?>
            </button>
        </div>
    </div>
    <div class="notif-body" id="notif-body">
        <div class="notif-loading">Cargando…</div>
    </div>
</aside>

<!-- Valoración del sistema — cada 5 días desde la última calificación -->
<?php if ($valoracionPendiente): ?>
<div class="modal-overlay val-overlay" id="modal-valoracion">
    <div class="modal-box val-modal">
        <button type="button" class="val-x" data-close aria-label="Cerrar">×</button>
        <div class="val-hero">
            <div class="val-hero-icon">★</div>
            <h4>¿Cómo calificas tu experiencia?</h4>
            <p>Tu opinión nos ayuda a mejorar el sistema.</p>
        </div>
        <form method="post" action="<?= base_url('valoracion') ?>" id="form-valoracion">
            <?= csrf_field() ?>
            <div class="val-stars" id="val-stars">
                <?php for ($i = 1; $i <= 5; $i++): ?>
                    <button type="button" class="val-star" data-v="<?= $i ?>" aria-label="<?= $i ?> estrellas">★</button>
                <?php endfor; ?>
            </div>
            <div class="val-label" id="val-label">Toca una estrella</div>
            <input type="hidden" name="estrellas" id="val-estrellas" value="">
            <div class="form-group val-resena">
                <textarea name="resena" rows="3" maxlength="1000"
                          placeholder="Reseña (opcional): qué te gusta, qué mejorarías…"></textarea>
            </div>
            <div class="val-actions">
                <button type="submit" class="btn btn-primary" id="val-enviar" disabled>Enviar valoración</button>
                <button type="button" class="val-later" id="val-luego">Ahora no</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- QR de los portales públicos -->
<div class="modal-overlay" id="modal-qr" hidden>
    <div class="modal-box qr-modal">
        <button type="button" class="modal-close" data-close aria-label="Cerrar">×</button>
        <h4 class="qr-title" id="qr-title">Portal</h4>
        <div class="qr-box" id="qr-box"></div>
        <p class="qr-url" id="qr-url"></p>
        <div class="qr-actions">
            <button type="button" class="btn btn-outline" id="qr-copy">Copiar enlace</button>
            <a href="#" class="btn btn-primary" id="qr-open" target="_blank" rel="noopener">Abrir</a>
        </div>
    </div>
</div>

<script src="<?= v_asset('js/vendor/qrcode.min.js') ?>"></script>
<script>
(function () {
    var modal = document.getElementById('modal-qr');
    if (!modal) return;
    var box   = document.getElementById('qr-box');
    var title = document.getElementById('qr-title');
    var urlEl = document.getElementById('qr-url');
    var open  = document.getElementById('qr-open');
    var copy  = document.getElementById('qr-copy');

    document.querySelectorAll('.nav-qr').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var url = btn.getAttribute('data-qr');
            title.textContent = btn.getAttribute('data-qr-title') || 'Portal';
            urlEl.textContent = url;
            open.href = url;
            copy.dataset.url = url;
            box.innerHTML = '';
            new QRCode(box, {
                text: url, width: 180, height: 180,
                colorDark: '#2E3542', colorLight: '#ffffff',
                correctLevel: QRCode.CorrectLevel.M
            });
            modal.hidden = false;
        });
    });
    modal.querySelector('[data-close]').addEventListener('click', function () { modal.hidden = true; });
    modal.addEventListener('click', function (e) { if (e.target === modal) modal.hidden = true; });
    copy.addEventListener('click', function () {
        navigator.clipboard && navigator.clipboard.writeText(copy.dataset.url)
            .then(function () { copy.textContent = '¡Copiado!'; setTimeout(function () { copy.textContent = 'Copiar enlace'; }, 1500); });
    });
})();
</script>
<script src="<?= v_asset('js/app.js') ?>"></script>
<?= $this->renderSection('scripts') ?>
</body>
</html>
