<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<div class="list-head">
    <div>
        <h3 class="page-title">Empleados</h3>
        <p class="page-subtitle"><?= $pager->getTotal() ?> registrado(s)</p>
    </div>
    <?php if ($puede_crear ?? true): ?>
        <a href="<?= base_url('socios/empleados/crear') ?>" class="btn btn-primary">
            <?= icon('plus', 16) ?> Nuevo empleado
        </a>
    <?php endif; ?>
</div>

<form method="get" action="<?= base_url('socios/empleados') ?>" class="list-search">
    <?= icon('search', 16) ?>
    <input type="text" name="q" placeholder="Buscar por nombre, cédula o teléfono…"
           value="<?= esc($buscar ?? '') ?>">
</form>

<?= view('partials/list', ['items' => $items ?? []]) ?>

<?= isset($pager) ? $pager->links('default', 'cfsi') : '' ?>

<?= $this->endSection() ?>
