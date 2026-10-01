<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Logs de errores</h3>
        <p class="page-sub">Errores de runtime de todos los tenants — últimos <?= count($logs) ?> (máx. 300). El usuario solo ve un mensaje genérico.</p>
    </div>
    <a class="btn" href="<?= base_url('admin/auditoria') ?>"><?= icon('arrow-left', 14) ?> Auditoría</a>
</div>

<div class="card">
    <form method="get" action="<?= base_url('admin/auditoria/errores') ?>" class="filter-bar">
        <div class="fgroup">
            <label>Tenant</label>
            <select class="inp" name="tenant">
                <option value="">Todos</option>
                <?php foreach ($tenants as $t): ?>
                    <option value="<?= (int) $t['id'] ?>" <?= (int) $f['tenantF'] === (int) $t['id'] ? 'selected' : '' ?>>
                        <?= esc($t['nombre']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="fgroup">
            <label>Desde</label>
            <input class="inp" type="date" name="desde" value="<?= esc($f['desde']) ?>">
        </div>
        <div class="fgroup">
            <label>Hasta</label>
            <input class="inp" type="date" name="hasta" value="<?= esc($f['hasta']) ?>">
        </div>
        <div class="fgroup" style="flex:1; min-width:180px;">
            <label>Buscar</label>
            <input class="inp" type="text" name="q" value="<?= esc($f['buscar']) ?>" placeholder="Origen o mensaje">
        </div>
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="<?= base_url('admin/auditoria/errores') ?>">Limpiar</a>
    </form>

    <!-- Purga: borrar logs viejos -->
    <form method="post" action="<?= base_url('admin/auditoria/errores/purgar') ?>" class="filter-bar"
          onsubmit="return confirm('¿Eliminar los logs más viejos que esa cantidad de días?');" style="margin-bottom:14px;">
        <?= csrf_field() ?>
        <div class="fgroup" style="max-width:200px;">
            <label>Vaciar logs con más de</label>
            <select class="inp" name="dias">
                <option value="7">7 días</option>
                <option value="15">15 días</option>
                <option value="30" selected>30 días</option>
                <option value="60">60 días</option>
                <option value="90">90 días</option>
            </select>
        </div>
        <button class="btn" type="submit"><?= icon('trash-2', 14) ?> Purgar</button>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tenant</th>
                <th>Usuario</th>
                <th>Origen</th>
                <th>Mensaje</th>
                <th>Traza</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="6" class="text-muted">Sin errores registrados con ese filtro.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="text-muted" style="white-space:nowrap;"><?= esc(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                    <td><?= esc($l['tenant'] ?? '—') ?></td>
                    <td><?= esc($l['usuario'] ?? $l['username'] ?? '—') ?></td>
                    <td><span class="tag tag-info"><?= esc($l['origen']) ?></span></td>
                    <td class="fw-bold" style="max-width:340px;"><?= esc($l['mensaje']) ?></td>
                    <td>
                        <?php if (!empty($l['traza'])): ?>
                            <details>
                                <summary class="text-muted" style="cursor:pointer;">Ver</summary>
                                <pre style="font-size:11px; white-space:pre-wrap; max-width:420px; margin:6px 0 0;"><?= esc($l['traza']) ?></pre>
                            </details>
                        <?php else: ?>
                            <span class="text-muted">—</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
