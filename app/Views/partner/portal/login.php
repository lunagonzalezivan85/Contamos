<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>

<div class="card portal-card">
    <h3 class="card-title">Portal de gestores</h3>
    <p class="card-subtitle">Ingresa con tu carnet y PIN.</p>

    <form action="<?= base_url($slug . '/portal/login') ?>" method="post" class="form mt-4">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="carnet">Carnet</label>
            <input type="text" id="carnet" name="carnet" placeholder="TI-0001" autocomplete="off"
                   value="<?= esc(old('carnet')) ?>" required>
        </div>
        <div class="form-group">
            <label for="pin">PIN</label>
            <input type="password" id="pin" name="pin" placeholder="4 dígitos" maxlength="4" inputmode="numeric" required>
        </div>
        <button type="submit" class="btn btn-primary btn-block">Entrar</button>
    </form>
    <a href="<?= base_url($slug . '/portal') ?>" class="lp-volver"><?= icon('chevron-left', 14) ?> Volver al inicio</a>
</div>

<?= $this->endSection() ?>
