<?php
/**
 * Paginación Contamos — template 'cfsi' registrado en Config/Pager.php
 * Uso: <?= $pager->links('default', 'cfsi') ?>
 *
 * En el controlador, preservar filtros GET:
 *   $model->pager->only(['q', 'estado', 'gestor', 'ruta', 'desde', 'hasta']);
 */

/** @var \CodeIgniter\Pager\PagerRenderer $pager */
$pager->setSurroundCount(2);
?>
<?php if ($pager->getPageCount() > 1): ?>
<nav class="pager-nav" aria-label="Paginación">
    <ul class="pager">
        <?php if ($pager->hasPrevious()): ?>
            <li><a class="pager-btn" href="<?= $pager->getPrevious() ?>" aria-label="Anterior"><?= icon('chevron-left', 15) ?></a></li>
        <?php endif; ?>

        <?php foreach ($pager->links() as $link): ?>
            <li><a class="pager-num<?= $link['active'] ? ' on' : '' ?>" href="<?= $link['uri'] ?>"><?= $link['title'] ?></a></li>
        <?php endforeach; ?>

        <?php if ($pager->hasNext()): ?>
            <li><a class="pager-btn" href="<?= $pager->getNext() ?>" aria-label="Siguiente"><?= icon('chevron-right', 15) ?></a></li>
        <?php endif; ?>
    </ul>
    <p class="pager-info">Página <?= $pager->getCurrent() ?> de <?= $pager->getPageCount() ?><?= method_exists($pager, 'getTotal') ? ' · ' . $pager->getTotal() . ' registro(s)' : '' ?></p>
</nav>
<?php endif; ?>
