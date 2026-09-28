<?php
/**
 * Hero de la vista de detalle — título grande + botón Volver + dropdown de acciones.
 *
 * Uso:  <?= view('partials/detail_hero', [
 *           'titulo'   => 'Juan Pérez',
 *           'subtitulo'=> 'Cliente · Cédula 001-…',
 *           'icono'    => 'user',
 *           'volver'   => '/socios/clientes',
 *           'acciones' => [
 *               ['nombre' => 'Editar',   'url' => '/socios/clientes/5/editar', 'icono' => 'edit'],
 *               ['nombre' => 'Eliminar', 'url' => '/socios/clientes/5/eliminar', 'icono' => 'trash'],
 *           ],
 *       ]) ?>
 *
 * 'volver' es la URL del botón Volver (default: javascript:history.back()).
 * 'acciones' es opcional — si hay, se muestra el dropdown.
 */
?>
<div class="detail-hero">
    <span class="detail-hero-icon"><?= icon($icono ?? 'menu', 28) ?></span>
    <div class="detail-hero-body">
        <div class="detail-hero-title"><?= esc($titulo ?? '') ?></div>
        <?php if (!empty($subtitulo)): ?>
            <div class="detail-hero-sub"><?= esc($subtitulo) ?></div>
        <?php endif; ?>
    </div>
    <div class="detail-hero-actions">
        <a href="<?= esc($volver ?? 'javascript:history.back()') ?>" class="btn-volver">
            <?= icon('arrow-left', 16) ?> Volver
        </a>
        <?php if (!empty($acciones)): ?>
            <div class="dropdown">
                <button type="button" class="dropdown-toggle" id="detail-actions-toggle">
                    Acciones <span class="chevron"><?= icon('chevron-down', 14) ?></span>
                </button>
                <div class="dropdown-menu" id="detail-actions-menu" hidden>
                    <?php foreach ($acciones as $acc): ?>
                        <a class="dropdown-item" href="<?= base_url(ltrim($acc['url'] ?? '#', '/')) ?>">
                            <span class="nav-icon"><?= icon($acc['icono'] ?? 'menu', 16) ?></span>
                            <?= esc($acc['nombre']) ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>
