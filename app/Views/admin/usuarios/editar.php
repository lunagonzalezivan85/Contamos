<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Editar usuario</h3>
        <p class="page-sub"><?= esc($usuario['username']) ?> — ID #<?= (int) $usuario['id'] ?></p>
    </div>
    <a class="btn" href="<?= base_url($back ?? 'admin/usuarios') ?>">← Volver</a>
</div>

<div class="card" style="max-width:720px;">
    <h3 class="card-title">Datos del usuario</h3>
    <form method="post" action="<?= base_url('admin/usuarios/' . $usuario['id']) ?>" class="mt-4">
        <?= csrf_field() ?>
        <?php if (!empty($back)): ?><input type="hidden" name="back" value="<?= esc($back) ?>"><?php endif; ?>

        <div class="frow">
            <div class="fgroup">
                <label>Usuario</label>
                <input class="inp" type="text" value="<?= esc($usuario['username']) ?>" disabled>
            </div>
            <div class="fgroup">
                <label>Tenant *</label>
                <select class="inp" name="tenant_id" required>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (int) $usuario['tenant_id'] === (int) $t['id'] ? 'selected' : '' ?>>
                            <?= esc($t['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fgroup">
                <label>Rol *</label>
                <select class="inp" name="role_id" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (int) $usuario['role_id'] === (int) $r['id'] ? 'selected' : '' ?>>
                            <?= esc($r['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fgroup">
                <label>Nombre completo</label>
                <input class="inp" type="text" name="nombre" maxlength="150" value="<?= esc($usuario['nombre'] ?? '') ?>">
            </div>
            <div class="fgroup">
                <label>Email</label>
                <input class="inp" type="email" name="email" maxlength="150" value="<?= esc($usuario['email'] ?? '') ?>">
            </div>
            <div class="fgroup">
                <label>Estado</label>
                <input class="inp" type="text" value="<?= esc($usuario['estado']) ?>" disabled>
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
            <a class="btn" href="<?= base_url($back ?? 'admin/usuarios') ?>">Cancelar</a>
        </div>
    </form>
</div>

<div class="card" style="max-width:720px; margin-top:20px;">
    <h3 class="card-title">Restablecer contraseña</h3>
    <p class="card-subtitle">El usuario deberá cambiar la clave en su próximo login.</p>
    <form method="post" action="<?= base_url('admin/usuarios/' . $usuario['id'] . '/clave') ?>" class="mt-4">
        <?= csrf_field() ?>
        <?php if (!empty($back)): ?><input type="hidden" name="back" value="<?= esc($back) ?>"><?php endif; ?>
        <div class="frow">
            <div class="fgroup">
                <label>Nueva contraseña (mín. 6) *</label>
                <input class="inp" type="password" name="password" required minlength="6" maxlength="100" autocomplete="new-password">
            </div>
            <div class="fgroup">
                <label>Repetir contraseña *</label>
                <input class="inp" type="password" name="password2" required minlength="6" maxlength="100" autocomplete="new-password">
            </div>
        </div>
        <button type="submit" class="btn">Restablecer clave</button>
    </form>
</div>

<?= $this->endSection() ?>
