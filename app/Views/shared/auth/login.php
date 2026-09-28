<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'CFSI') ?></title>
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/auth.css') ?>">
</head>
<body>
    <div class="auth-card">
        <div class="auth-logo">
            <div class="brand">C</div>
            <h1>Contamos</h1>
            <p>Control de Préstamos</p>
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

        <form action="<?= base_url('login') ?>" method="post">
            <?= csrf_field() ?>

            <div class="form-group">
                <label for="username">Usuario</label>
                <input type="text" id="username" name="username"
                       value="<?= esc(old('username')) ?>"
                       placeholder="Ingrese su usuario" required autofocus>
            </div>

            <div class="form-group">
                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password"
                       placeholder="Ingrese su contraseña" required>
            </div>

            <button type="submit" class="btn-submit">Ingresar</button>
        </form>

        <div class="auth-footer">
            &copy; <?= date('Y') ?> Contamos — Todos los derechos reservados
        </div>
    </div>
</body>
</html>
