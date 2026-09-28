<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<?php $backUrl = 'admin/tenants/' . (int) $t['id']; ?>

<div class="toolbar">
    <div>
        <h3 class="page-title"><?= esc($t['nombre']) ?></h3>
        <p class="page-sub">
            <code>/<?= esc($t['slug']) ?></code>
            <?= !empty($t['razon_social']) ? ' · ' . esc($t['razon_social']) : '' ?>
            <?= !empty($t['ruc']) ? ' · RUC ' . esc($t['ruc']) : '' ?>
        </p>
    </div>
    <div style="display:flex; gap:8px;">
        <a class="btn" href="<?= base_url('admin/tenants') ?>">← Tenants</a>
        <a class="btn" href="<?= base_url($t['slug'] . '/portal') ?>" target="_blank">Ver portal ↗</a>
        <a class="btn btn-primary" href="<?= base_url('admin/usuarios/nuevo?tenant=' . (int) $t['id'] . '&back=' . urlencode($backUrl)) ?>">+ Usuario</a>
        <form method="post" action="<?= base_url('admin/tenants/' . $t['id'] . '/toggle') ?>" style="display:inline;"
              onsubmit="return confirm('¿<?= $t['estado'] === 'ACTIVO' ? 'Suspender' : 'Reactivar' ?> <?= esc($t['nombre'], 'attr') ?>?');">
            <?= csrf_field() ?>
            <button class="btn btn-sm <?= $t['estado'] === 'ACTIVO' ? 'btn-danger' : 'btn-primary' ?>">
                <?= $t['estado'] === 'ACTIVO' ? 'Suspender' : 'Reactivar' ?>
            </button>
        </form>
    </div>
</div>

<?php $reset = session()->getFlashdata('pass_reset'); ?>
<?php if ($reset): ?>
<div class="pass-reset">
    <div class="pass-reset-info">
        <b>Clave temporal de «<?= esc($reset['username']) ?>»</b>
        <span class="text-muted">Debe cambiarla al entrar — cópiela y compártala con el usuario.</span>
    </div>
    <code id="pass-tmp"><?= esc($reset['pass']) ?></code>
    <button type="button" class="btn btn-sm btn-primary" onclick="copiarReset(false, this)">Copiar clave</button>
    <button type="button" class="btn btn-sm" onclick="copiarReset(true, this)">Copiar credenciales</button>
    <a class="btn btn-sm" target="_blank" rel="noopener"
       href="https://wa.me/?text=<?= urlencode("Acceso CFSI\nUsuario: {$reset['username']}\nClave temporal: {$reset['pass']}\n" . base_url('login') . "\nDebe cambiarla al entrar.") ?>">WhatsApp ↗</a>
</div>
<script>
(function () {
    var pass = document.getElementById('pass-tmp').textContent.trim();
    var txt  = 'Acceso CFSI\nUsuario: <?= esc($reset['username'], 'js') ?>\nClave temporal: ' + pass + '\n<?= base_url('login') ?>\nDebe cambiarla al entrar.';
    window.copiarReset = function (full, btn) {
        navigator.clipboard.writeText(full ? txt : pass).then(function () {
            var orig = btn.textContent;
            btn.textContent = '¡Copiada!';
            setTimeout(function () { btn.textContent = orig; }, 1800);
        });
    };
})();
</script>
<?php endif; ?>

<div class="stats-grid">
    <div class="stat-card"><div class="num"><?= (int) $t['stats']['usuarios'] ?></div><div class="lbl">Usuarios</div></div>
    <div class="stat-card"><div class="num"><?= (int) $t['stats']['clientes'] ?></div><div class="lbl">Clientes</div></div>
    <div class="stat-card"><div class="num"><?= (int) $t['stats']['solicitudes'] ?></div><div class="lbl">Solicitudes</div></div>
    <div class="stat-card"><div class="num"><?= (int) $t['stats']['activos'] ?></div><div class="lbl">Créditos activos</div></div>
</div>

<div class="card">
    <h3 class="card-title">Datos de la empresa</h3>
    <div class="frow mt-4" style="grid-template-columns:repeat(3,1fr); gap:18px 28px;">
        <div><small class="text-muted">Plan</small><div class="fw-bold"><?= esc($t['plan'] ?? '—') ?></div></div>
        <div><small class="text-muted">Suscripción</small><div>
            <span class="tag <?= ($t['suscripcion_estado'] ?? 'ACTIVA') === 'ACTIVA' ? 'tag-activo' : 'tag-warn' ?>"><?= esc($t['suscripcion_estado'] ?? '—') ?></span>
        </div></div>
        <div><small class="text-muted">Estado</small><div>
            <span class="tag <?= $t['estado'] === 'ACTIVO' ? 'tag-activo' : 'tag-inactivo' ?>"><?= esc($t['estado']) ?></span>
        </div></div>
        <div><small class="text-muted">Email</small><div><?= esc($t['email'] ?? '—') ?></div></div>
        <div><small class="text-muted">Teléfono</small><div><?= esc($t['telefono'] ?? '—') ?></div></div>
        <div><small class="text-muted">Contacto</small><div><?= esc($t['contacto_nombre'] ?? '—') ?><?= !empty($t['contacto_cargo']) ? ' · ' . esc($t['contacto_cargo']) : '' ?></div></div>
        <div><small class="text-muted">Reg. CONAMI</small><div><?= esc($t['conami_registro'] ?? '—') ?></div></div>
        <div><small class="text-muted">Moneda / Tasa</small><div><?= esc($t['moneda'] ?? 'C$') ?> · <?= number_format((float) ($t['tasa_interes'] ?? 0), 2) ?>% mensual</div></div>
        <div><small class="text-muted">Dirección</small><div><?= esc($t['direccion'] ?? '—') ?></div></div>
    </div>
</div>

<div class="card">
    <div class="toolbar" style="margin-bottom:14px;">
        <h3 class="card-title" style="margin:0;">Usuarios del tenant</h3>
        <a class="btn btn-sm btn-primary" href="<?= base_url('admin/usuarios/nuevo?tenant=' . (int) $t['id'] . '&back=' . urlencode($backUrl)) ?>">+ Agregar usuario</a>
    </div>
    <table class="table">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Rol</th>
                <th>Último acceso</th>
                <th>Estado</th>
                <th style="width:1%;"></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="6" class="text-muted">Sin usuarios — agregue el primero arriba.</td></tr>
            <?php endif; ?>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="fw-bold"><?= esc($u['username']) ?><br><small class="text-muted"><?= esc($u['email']) ?></small></td>
                    <td><?= esc($u['nombre']) ?></td>
                    <td><?= esc($u['rol'] ?? '—') ?></td>
                    <td class="text-muted text-sm"><?= $u['ultimo_login'] ? esc(date('d/m/Y H:i', strtotime($u['ultimo_login']))) : 'Nunca' ?></td>
                    <td><span class="tag <?= $u['estado'] === 'ACTIVO' ? 'tag-activo' : 'tag-inactivo' ?>"><?= esc($u['estado']) ?></span></td>
                    <td>
                        <div style="display:flex; gap:6px;">
                            <a class="btn btn-sm" href="<?= base_url('admin/usuarios/' . $u['id'] . '/editar?back=' . urlencode($backUrl)) ?>">Editar</a>
                            <form method="post" action="<?= base_url('admin/usuarios/' . $u['id'] . '/toggle') ?>" style="display:inline;"
                                  onsubmit="return confirm('¿<?= $u['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?> a <?= esc($u['username'], 'attr') ?>?');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="back" value="<?= esc($backUrl) ?>">
                                <button class="btn btn-sm"><?= $u['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?></button>
                            </form>
                            <button type="button" class="btn btn-sm"
                                    onclick="modalReset(<?= (int) $u['id'] ?>, '<?= esc($u['username'], 'js') ?>')">Reset</button>
                            <form method="post" action="<?= base_url('admin/tenants/' . $t['id'] . '/usuarios/' . $u['id'] . '/quitar') ?>" style="display:inline;"
                                  onsubmit="return confirm('¿Quitar a <?= esc($u['username'], 'attr') ?> del tenant? (baja lógica, no se borra historial)');">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-danger">Quitar</button>
                            </form>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="moverlay" id="mr" hidden onclick="if (event.target === this) mrClose()">
    <div class="modal">
        <div class="modal-head">
            <b>Resetear clave</b>
            <button type="button" class="btn btn-sm" onclick="mrClose()">✕</button>
        </div>
        <div class="modal-body">
            <div id="mr-ask">
                <p id="mr-msg"></p>
                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:14px;">
                    <button type="button" class="btn" onclick="mrClose()">Cancelar</button>
                    <button type="button" class="btn btn-primary" id="mr-go" onclick="mrSend()">Resetear</button>
                </div>
            </div>
            <div id="mr-done" hidden>
                <p class="text-muted" id="mr-sub"></p>
                <code class="mr-pass" id="mr-pass"></code>
                <div style="display:flex; gap:8px; margin-top:14px; flex-wrap:wrap;">
                    <button type="button" class="btn btn-sm btn-primary" onclick="mrCopy(false, this)">Copiar clave</button>
                    <button type="button" class="btn btn-sm" onclick="mrCopy(true, this)">Copiar credenciales</button>
                    <a class="btn btn-sm" id="mr-wa" target="_blank" rel="noopener" href="#">WhatsApp ↗</a>
                    <button type="button" class="btn btn-sm" onclick="mrClose()">Cerrar</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
var MR = { id: 0, user: '', pass: '', csrfName: '<?= csrf_token() ?>', csrf: '<?= csrf_hash() ?>' };
var MR_LOGIN_URL = '<?= base_url('login') ?>';

function modalReset(id, user) {
    MR.id = id; MR.user = user; MR.pass = '';
    document.getElementById('mr-ask').hidden  = false;
    document.getElementById('mr-done').hidden = true;
    var go = document.getElementById('mr-go');
    go.disabled = false; go.textContent = 'Resetear';
    document.getElementById('mr-msg').innerHTML =
        'Se generará una <b>clave temporal</b> para <b>' + user + '</b> — la actual dejará de servir.';
    document.getElementById('mr').hidden = false;
}

function mrClose() { document.getElementById('mr').hidden = true; }

function mrSend() {
    var go = document.getElementById('mr-go');
    go.disabled = true; go.textContent = 'Generando…';
    var fd = new FormData();
    fd.append(MR.csrfName, MR.csrf);
    fd.append('back', '<?= esc($backUrl) ?>');
    fetch('<?= base_url('admin/usuarios') ?>/' + MR.id + '/reset-rapido', {
        method: 'POST',
        headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-CSRF-TOKEN': MR.csrf },
        body: fd
    }).then(function (r) { return r.json(); }).then(function (d) {
        if (!d.ok) { mrClose(); alert(d.error || 'No se pudo resetear.'); return; }
        MR.pass = d.pass;
        document.getElementById('mr-ask').hidden  = true;
        document.getElementById('mr-done').hidden = false;
        document.getElementById('mr-sub').textContent =
            'Clave temporal de «' + d.username + '» — debe cambiarla al entrar:';
        document.getElementById('mr-pass').textContent = d.pass;
        document.getElementById('mr-wa').href = 'https://wa.me/?text=' +
            encodeURIComponent(mrTexto(d.username, d.pass));
    }).catch(function () { mrClose(); alert('Error de red al resetear.'); });
}

function mrTexto(user, pass) {
    return 'Acceso CFSI\nUsuario: ' + user + '\nClave temporal: ' + pass +
           '\n' + MR_LOGIN_URL + '\nDebe cambiarla al entrar.';
}

function mrCopy(full, btn) {
    navigator.clipboard.writeText(full ? mrTexto(MR.user, MR.pass) : MR.pass).then(function () {
        var o = btn.textContent; btn.textContent = '¡Copiada!';
        setTimeout(function () { btn.textContent = o; }, 1800);
    });
}
</script>

<?= $this->endSection() ?>
