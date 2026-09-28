<?php
/**
 * Layout del administrador del sistema — distinto al partner.
 * Acento índigo, badge ADMIN, menú desde menu_items() (Config/Menu::$sistema).
 */
$menus  = menu_items();
$nombre = session('nombre') ?? 'Admin';
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <title><?= esc($title ?? 'Admin — Contamos') ?></title>
    <link rel="stylesheet" href="<?= v_asset('css/admin.css') ?>">
</head>
<body>

<aside class="sidebar" id="sidebar">
    <div class="sidebar-brand">
        <div class="dot">C</div>
        <span class="name">Contamos</span>
        <span class="badge">Admin</span>
    </div>
    <nav class="sidebar-nav">
        <?php foreach ($menus as $item): ?>
            <div class="nav-item">
                <?php if (!empty($item['children'])): ?>
                    <div class="nav-group-label"><?= esc($item['nombre']) ?></div>
                    <div class="nav-children">
                        <?php foreach ($item['children'] as $child): ?>
                            <a class="nav-link" href="<?= base_url(ltrim($child['url'] ?? '#', '/')) ?>">
                                <?= esc($child['nombre']) ?>
                            </a>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <a class="nav-link" href="<?= base_url(ltrim($item['url'] ?? '#', '/')) ?>">
                        <?= esc($item['nombre']) ?>
                    </a>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
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
        <h2><?= esc($title ?? 'Admin') ?></h2>
        <span class="user"><?= esc($nombre) ?> · Administrador del sistema</span>
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

<script src="<?= v_asset('js/app.js') ?>"></script>
</body>
</html>
