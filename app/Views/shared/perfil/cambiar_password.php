<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Cambiar Contraseña — Contamos') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/auth.css') ?>">
</head>
<body>
    <div class="auth-card">
        <div class="auth-logo">
            <div class="brand">C</div>
            <h1>Cambiar Contraseña</h1>
            <p>Contamos — Control de Préstamos</p>
        </div>

        <?php if (session()->getFlashdata('error')): ?>
            <div class="alert alert-error"><?= esc(session()->getFlashdata('error')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('warning')): ?>
            <div class="alert alert-warning"><?= esc(session()->getFlashdata('warning')) ?></div>
        <?php endif; ?>
        <?php if (session()->getFlashdata('success')): ?>
            <div class="alert alert-success"><?= esc(session()->getFlashdata('success')) ?></div>
        <?php endif; ?>

        <?php if (!empty($obligado)): ?>
            <div class="notice">
                Por seguridad debe establecer una nueva contraseña antes de continuar.
            </div>
        <?php endif; ?>

        <form action="<?= base_url('perfil/cambiar-password') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="password_actual">Contraseña actual</label>
                <input type="password" id="password_actual" name="password_actual" required autofocus>
            </div>

            <div class="form-group">
                <label for="password_nuevo">Nueva contraseña</label>
                <input type="password" id="password_nuevo" name="password_nuevo" required minlength="8">
                <small>Mínimo 8 caracteres.</small>
            </div>

            <div class="form-group">
                <label for="password_confirm">Confirmar nueva contraseña</label>
                <input type="password" id="password_confirm" name="password_confirm" required>
            </div>

            <button type="submit" class="btn-submit">Actualizar Contraseña</button>
        </form>

        <?php if (empty($obligado)): ?>
            <div class="auth-footer">
                <a href="<?= base_url('dashboard') ?>">← Volver al dashboard</a>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
