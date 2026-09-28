<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Nuevo tenant</h3>
        <p class="page-sub">Alta de empresa — se provisionan menús y permisos copiando la seguridad del tenant 1.</p>
    </div>
    <a class="btn" href="<?= base_url('admin/tenants') ?>">← Volver</a>
</div>

<div class="card" style="max-width:760px;">
    <h3 class="card-title">Datos de la empresa</h3>
    <p class="card-subtitle">Campos mínimos para operar. Se puede editar luego desde el propio tenant.</p>

    <form method="post" action="<?= base_url('admin/tenants') ?>" class="mt-4">
        <?= csrf_field() ?>

        <div class="frow">
            <div class="fgroup">
                <label>Nombre *</label>
                <input class="inp" type="text" name="nombre" required maxlength="150" value="<?= esc(old('nombre')) ?>">
            </div>
            <div class="fgroup">
                <label>Slug (portal público)</label>
                <input class="inp" type="text" name="slug" maxlength="100" placeholder="auto si se omite" value="<?= esc(old('slug')) ?>">
            </div>
            <div class="fgroup">
                <label>Email</label>
                <input class="inp" type="email" name="email" maxlength="150" value="<?= esc(old('email')) ?>">
            </div>
            <div class="fgroup">
                <label>Contacto</label>
                <input class="inp" type="text" name="contacto_nombre" maxlength="150" value="<?= esc(old('contacto_nombre')) ?>">
            </div>
            <div class="fgroup">
                <label>Plan</label>
                <select class="inp" name="plan_id">
                    <option value="">— Sin plan —</option>
                    <?php foreach ($planes as $p): ?>
                        <option value="<?= (int) $p['id'] ?>" <?= (string) old('plan_id') === (string) $p['id'] ? 'selected' : '' ?>>
                            <?= esc($p['nombre']) ?> — <?= esc($p['moneda'] ?? 'USD') ?> <?= number_format((float) $p['precio_mensual'], 2) ?>/mes
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="fgroup">
                <label>Moneda</label>
                <input class="inp" type="text" name="moneda" maxlength="10" value="<?= esc(old('moneda') ?: 'C$') ?>">
            </div>
            <div class="fgroup">
                <label>Tasa mensual (%)</label>
                <input class="inp" type="number" name="tasa_interes" step="0.01" min="0" value="<?= esc(old('tasa_interes') ?: '3') ?>">
            </div>
            <div class="fgroup">
                <label>Plazo máximo (meses)</label>
                <input class="inp" type="number" name="plazo_meses_max" min="1" value="<?= esc(old('plazo_meses_max') ?: '24') ?>">
            </div>
        </div>

        <h3 class="card-title" style="margin-top:24px;">Usuario administrador</h3>
        <p class="card-subtitle">Opcional — si se omite, se crea después. La contraseña se cambia en el primer login.</p>
        <div class="frow mt-4">
            <div class="fgroup">
                <label>Usuario</label>
                <input class="inp" type="text" name="admin_username" maxlength="100" value="<?= esc(old('admin_username')) ?>">
            </div>
            <div class="fgroup">
                <label>Contraseña (mín. 8)</label>
                <input class="inp" type="text" name="admin_password" maxlength="100" autocomplete="off">
            </div>
        </div>

        <div style="display:flex; gap:10px; margin-top:8px;">
            <button type="submit" class="btn btn-primary">Crear tenant</button>
            <a class="btn" href="<?= base_url('admin/tenants') ?>">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
