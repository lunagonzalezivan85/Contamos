<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Usuarios</h3>
        <p class="page-sub"><?= count($usuarios) ?> usuarios de todos los tenants.</p>
    </div>
    <a class="btn btn-primary" href="<?= base_url('admin/usuarios/nuevo') ?>">+ Nuevo usuario</a>
</div>

<div class="card">
    <form method="get" class="filter-bar">
        <div class="fgroup">
            <label>Tenant</label>
            <select class="inp" name="tenant">
                <option value="">Todos</option>
                <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= $tenantF === (int) $t['id'] ? 'selected' : '' ?>>
                        <?= esc($t['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fgroup" style="flex:1; min-width:220px;">
            <label>Buscar</label>
            <input class="inp" type="text" name="q" value="<?= esc($buscar) ?>" placeholder="Usuario, nombre o email">
        </div>
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="<?= base_url('admin/usuarios') ?>">Limpiar</a>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Nombre</th>
                <th>Tenant</th>
                <th>Rol</th>
                <th>Estado</th>
                <th>Último login</th>
                <th>Creado</th>
                <th>Acciones</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($usuarios)): ?>
                <tr><td colspan="8" class="text-muted">Sin usuarios con ese filtro.</td></tr>
            <?php endif; ?>
            <?php foreach ($usuarios as $u): ?>
                <tr>
                    <td class="fw-bold">
                        <?= esc($u['username']) ?>
                        <?php if (!empty($u['email'])): ?>
                            <br><small class="text-muted"><?= esc($u['email']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><?= esc($u['nombre'] ?? '—') ?></td>
                    <td><?= esc($u['tenant'] ?? '—') ?></td>
                    <td>
                        <span class="tag <?= in_array($u['rol_slug'], ['superadmin', 'admin'], true) ? 'tag-info' : '' ?>">
                            <?= esc($u['rol'] ?? '—') ?>
                        </span>
                    </td>
                    <td>
                        <span class="tag <?= $u['estado'] === 'ACTIVO' ? 'tag-activo' : 'tag-inactivo' ?>">
                            <?= esc($u['estado']) ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= $u['ultimo_login'] ? esc(date('d/m/Y H:i', strtotime($u['ultimo_login']))) : 'Nunca' ?></td>
                    <td class="text-muted"><?= $u['created_at'] ? esc(date('d/m/Y', strtotime($u['created_at']))) : '—' ?></td>
                    <td style="white-space:nowrap;">
                        <div class="dd">
                            <button type="button" class="btn btn-sm" data-dd>Acciones ▾</button>
                            <div class="dd-menu" hidden>
                                <a class="dd-item" href="<?= base_url('admin/usuarios/' . $u['id'] . '/editar') ?>">Editar</a>
                                <form method="post" action="<?= base_url('admin/usuarios/' . $u['id'] . '/toggle') ?>"
                                      onsubmit="return confirm('¿<?= $u['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?> a <?= esc($u['username'], 'attr') ?>?');">
                                    <?= csrf_field() ?>
                                    <button type="submit" class="dd-item"><?= $u['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?></button>
                                </form>
                            </div>
                        </div>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
