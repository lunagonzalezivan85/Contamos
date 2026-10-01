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
    <?php foreach ($items ?? [] as $it):
        $acts = $it['acciones'] ?? [];
        $url  = base_url(ltrim($it['url'] ?? '#', '/'));
    ?>
        <?php if ($acts): ?>
        <!-- Con acciones: fila = div, cada parte clickeable por separado -->
        <div class="oui-list-item">
            <a class="oui-list-icon" href="<?= $url ?>"><?= icon($it['icono'] ?? 'menu', 20) ?></a>
            <a class="oui-list-body" href="<?= $url ?>">
                <div class="oui-list-title"><?= esc($it['titulo'] ?? '') ?></div>
                <?php if (!empty($it['subtitulo'])): ?>
                    <div class="oui-list-sub"><?= esc($it['subtitulo']) ?></div>
                <?php endif; ?>
            </a>
            <span class="row-acts">
                <?php foreach ($acts as $a): ?>
                    <a class="row-act" href="<?= esc($a['url']) ?>" target="_blank" rel="noopener"
                       title="<?= esc($a['title'] ?? '') ?>">
                        <?= icon($a['icono'], 15) ?><span class="row-act-txt"><?= esc($a['lbl'] ?? '') ?></span>
                    </a>
                <?php endforeach; ?>
            </span>
            <?php if (isset($it['meta'])): ?>
                <span class="oui-list-meta<?= !empty($it['meta_class']) ? ' ' . esc($it['meta_class']) : '' ?>"><?= esc($it['meta']) ?></span>
            <?php endif; ?>
            <a class="oui-list-chevron" href="<?= $url ?>"><?= icon('chevron-right', 18) ?></a>
        </div>
        <?php else: ?>
        <a class="oui-list-item" href="<?= $url ?>">
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
        <?php endif; ?>
    <?php endforeach; ?>
    <?php if (empty($items)): ?>
        <div class="oui-list-item" style="cursor:default;">Sin registros.</div>
    <?php endif; ?>
</div>
