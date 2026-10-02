<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Nuevo tenant</h3>
        <p class="page-sub">Alta de empresa — se provisionan menús y permisos copiando la seguridad del tenant 1.</p>
    </div>
    <a class="btn" href="<?= base_url('admin/tenants') ?>">← Volver</a>
</div>

<form method="post" action="<?= base_url('admin/tenants') ?>">
    <?= csrf_field() ?>

    <div class="form-cols">
        <div class="card">
            <h3 class="card-title"><?= icon('briefcase', 16) ?> Datos de la empresa</h3>
            <p class="card-subtitle">Campos mínimos para operar — se pueden editar luego desde el propio tenant.</p>

            <div class="fgroup mt-4">
                <label>Nombre *</label>
                <input class="inp" type="text" name="nombre" required maxlength="150" value="<?= esc(old('nombre')) ?>">
            </div>
            <div class="fgroup">
                <label>Slug (portal público)</label>
                <div class="input-btn">
                    <input class="inp" type="text" name="slug" id="inp-slug" maxlength="100"
                           placeholder="auto si se omite" value="<?= esc(old('slug')) ?>"
                           autocomplete="off" autocapitalize="none">
                    <button type="button" class="btn" id="btn-gen-slug" title="Genera un slug libre a partir del nombre">Generar</button>
                </div>
                <small class="text-muted" id="slug-hint">Si se omite se genera del nombre.</small>
            </div>
            <div class="frow">
                <div class="fgroup">
                    <label>Email</label>
                    <input class="inp" type="email" name="email" maxlength="150" value="<?= esc(old('email')) ?>">
                </div>
                <div class="fgroup">
                    <label>Contacto</label>
                    <input class="inp" type="text" name="contacto_nombre" maxlength="150" value="<?= esc(old('contacto_nombre')) ?>">
                </div>
            </div>
            <div class="fgroup">
                <label>Moneda</label>
                <input class="inp" type="text" name="moneda" maxlength="10" value="<?= esc(old('moneda') ?: 'C$') ?>">
            </div>
        </div>

        <div class="card">
            <h3 class="card-title"><?= icon('percent', 16) ?> Plan y crédito</h3>
            <p class="card-subtitle">Plan SaaS del servicio y parámetros base para sus créditos.</p>

            <div class="fgroup mt-4">
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
            <div class="frow">
                <div class="fgroup">
                    <label>Tasa mensual (%)</label>
                    <input class="inp" type="number" name="tasa_interes" step="0.01" min="0" value="<?= esc(old('tasa_interes') ?: '3') ?>">
                </div>
                <div class="fgroup">
                    <label>Plazo máximo (meses)</label>
                    <input class="inp" type="number" name="plazo_meses_max" min="1" value="<?= esc(old('plazo_meses_max') ?: '24') ?>">
                </div>
            </div>
        </div>
    </div>

    <div class="card">
        <h3 class="card-title"><?= icon('user', 16) ?> Usuario administrador</h3>
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
    </div>

    <div class="form-actions">
        <a class="btn" href="<?= base_url('admin/tenants') ?>">Cancelar</a>
        <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Crear tenant</button>
    </div>
</form>

<script src="<?= v_asset('js/admin-tenant.js') ?>"></script>
<?= $this->endSection() ?>
