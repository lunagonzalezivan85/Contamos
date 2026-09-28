<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Auditoría</h3>
        <p class="page-sub">Bitácora de acciones — últimos <?= count($logs) ?> eventos (máx. 300).</p>
    </div>
</div>

<div class="card">
    <form method="get" class="filter-bar">
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
            <label>Módulo</label>
            <select class="inp" name="modulo">
                <option value="">Todos</option>
                <?php foreach ($modulos as $m): ?>
                    <option value="<?= esc($m) ?>" <?= $f['modulo'] === $m ? 'selected' : '' ?>><?= esc($m) ?></option>
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
            <input class="inp" type="text" name="q" value="<?= esc($f['buscar']) ?>" placeholder="Acción o entidad">
        </div>
        <button class="btn" type="submit">Filtrar</button>
        <a class="btn" href="<?= base_url('admin/auditoria') ?>">Limpiar</a>
    </form>

    <table class="table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tenant</th>
                <th>Usuario</th>
                <th>Módulo</th>
                <th>Acción</th>
                <th>Entidad</th>
                <th>IP</th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($logs)): ?>
                <tr><td colspan="7" class="text-muted">Sin eventos con ese filtro.</td></tr>
            <?php endif; ?>
            <?php foreach ($logs as $l): ?>
                <tr>
                    <td class="text-muted" style="white-space:nowrap;"><?= esc(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                    <td><?= esc($l['tenant'] ?? '—') ?></td>
                    <td>
                        <?= esc($l['usuario'] ?? $l['username'] ?? 'Sistema') ?>
                        <?php if (!empty($l['username'])): ?>
                            <br><small class="text-muted"><?= esc($l['username']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td><span class="tag tag-info"><?= esc($l['modulo'] ?? '—') ?></span></td>
                    <td class="fw-bold"><?= esc($l['accion']) ?></td>
                    <td>
                        <?= esc($l['entidad'] ?? '—') ?>
                        <?php if (!empty($l['entidad_id'])): ?>
                            <small class="text-muted">#<?= (int) $l['entidad_id'] ?></small>
                        <?php endif; ?>
                    </td>
                    <td class="text-muted"><?= esc($l['ip'] ?? '—') ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
