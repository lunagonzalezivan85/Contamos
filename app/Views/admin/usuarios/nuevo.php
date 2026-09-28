<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Nuevo usuario</h3>
        <p class="page-sub">Alta de usuario en cualquier tenant — debe cambiar la clave en el primer login. Cada usuario activo por encima de los incluidos en el plan suma USD 3.00 al cobro mensual.</p>
    </div>
    <a class="btn" href="<?= base_url('admin/usuarios') ?>">← Volver</a>
</div>

<div class="card" style="max-width:720px;">
    <h3 class="card-title">Datos de acceso</h3>
    <form method="post" action="<?= base_url('admin/usuarios') ?>" class="mt-4">
        <?= csrf_field() ?>
        <?php if (!empty($back)): ?><input type="hidden" name="back" value="<?= esc($back) ?>"><?php endif; ?>

        <div class="frow">
            <div class="fgroup">
                <label>Tenant *</label>
                <select class="inp" name="tenant_id" required>
                    <option value="">— Seleccione —</option>
                    <?php foreach ($tenants as $t): ?>
                        <option value="<?= (int) $t['id'] ?>" <?= (string) old('tenant_id', $tenantSel ?? '') === (string) $t['id'] ? 'selected' : '' ?>>
                            <?= esc($t['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fgroup">
                <label>Rol *</label>
                <select class="inp" name="role_id" required>
                    <?php foreach ($roles as $r): ?>
                        <option value="<?= (int) $r['id'] ?>" <?= (string) old('role_id', '2') === (string) $r['id'] ? 'selected' : '' ?>>
                            <?= esc($r['nombre']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted">Si es el primer usuario del tenant se asigna como Administrador automáticamente.</small>
            </div>
            <div class="fgroup">
                <label>Usuario *</label>
                <input class="inp" type="text" name="username" required minlength="3" maxlength="100"
                       value="<?= esc(old('username')) ?>" placeholder="ej. mlopez" autocomplete="off">
            </div>
            <div class="fgroup">
                <label>Nombre completo</label>
                <input class="inp" type="text" name="nombre" maxlength="150" value="<?= esc(old('nombre')) ?>">
            </div>
            <div class="fgroup">
                <label>Email</label>
                <input class="inp" type="email" name="email" maxlength="150" value="<?= esc(old('email')) ?>">
            </div>
            <div class="fgroup">
                <label>Contraseña (mín. 6) *</label>
                <input class="inp" type="password" name="password" required minlength="6" maxlength="100" autocomplete="new-password">
            </div>
            <div class="fgroup">
                <label>Repetir contraseña *</label>
                <input class="inp" type="password" name="password2" required minlength="6" maxlength="100" autocomplete="new-password">
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <button type="submit" class="btn btn-primary">Crear usuario</button>
            <a class="btn" href="<?= base_url($back ?? 'admin/usuarios') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
