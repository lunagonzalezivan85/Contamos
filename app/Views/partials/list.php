<?php
/**
 * Lista One UI — filas clickeables que envían a la vista de detalle.
 *
 * Uso:  <?= view('partials/list', ['items' => $items]) ?>
 *
 * $items = [
 *   ['icono' => 'user', 'titulo' => 'Juan Pérez', 'subtitulo' => 'Cédula 001-…', 'meta' => 'Activo', 'url' => '/socios/clientes/5'],
 *   ...
 * ]
 * 'icono' es opcional (default 'menu'). 'meta' es texto a la derecha (opcional).
 */
?>
<div class="oui-list">
    <?php foreach ($items ?? [] as $it): ?>
        <a class="oui-list-item" href="<?= base_url(ltrim($it['url'] ?? '#', '/')) ?>">
            <span class="oui-list-icon"><?= icon($it['icono'] ?? 'menu', 20) ?></span>
            <span class="oui-list-body">
                <div class="oui-list-title"><?= esc($it['titulo'] ?? '') ?></div>
                <?php if (!empty($it['subtitulo'])): ?>
                    <div class="oui-list-sub"><?= esc($it['subtitulo']) ?></div>
                <?php endif; ?>
            </span>
            <?php if (isset($it['meta'])): ?>
                <span class="oui-list-meta<?= !empty($it['meta_class']) ? ' ' . esc($it['meta_class']) : '' ?>"><?= esc($it['meta']) ?></span>
            <?php endif; ?>
            <span class="oui-list-chevron"><?= icon('chevron-right', 18) ?></span>
        </a>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
        <div class="oui-list-item" style="cursor:default;">Sin registros.</div>
    <?php endif; ?>
</div>
