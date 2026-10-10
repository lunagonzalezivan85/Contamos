<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title"><?= icon('database', 16) ?> Base de datos</h3>
        <p class="page-sub"><?= count($tablas) ?> tablas · tamaño total <?= number_format((float) array_sum(array_column($tablas, 'kb')) / 1024, 1) ?> MB.</p>
    </div>
</div>

<form method="post" action="<?= base_url('admin/base-datos/exportar') ?>" id="form-bd">
    <?= csrf_field() ?>

    <div class="card" style="display:flex; align-items:center; gap:12px; flex-wrap:wrap; margin-bottom:16px;">
        <label style="display:flex; align-items:center; gap:8px; font-weight:600; cursor:pointer;">
            <input type="checkbox" id="ck-todas" checked> Todas las tablas
        </label>
        <div style="flex:1;"></div>
        <button type="submit" name="modo" value="estructura" class="btn">
            <?= icon('file-text', 14) ?> Descargar estructura
        </button>
        <button type="submit" name="modo" value="completo" class="btn btn-primary">
            <?= icon('download', 14) ?> Backup completo (con datos)
        </button>
    </div>

    <div class="card" style="padding:0;">
        <table class="table" style="margin:0;">
            <thead>
                <tr>
                    <th style="width:40px;"></th>
                    <th>Tabla</th>
                    <th>Motor</th>
                    <th>Filas</th>
                    <th>Tamaño</th>
                    <th>Última escritura</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($tablas as $t): ?>
                    <tr>
                        <td><input type="checkbox" name="tablas[]" value="<?= esc($t['nombre']) ?>" checked></td>
                        <td><code><?= esc($t['nombre']) ?></code></td>
                        <td class="text-muted"><?= esc($t['motor'] ?? '—') ?></td>
                        <td><?= number_format((int) ($t['filas'] ?? 0)) ?></td>
                        <td><?= $t['kb'] >= 1024 ? number_format($t['kb'] / 1024, 1) . ' MB' : number_format((float) $t['kb'], 0) . ' KB' ?></td>
                        <td class="text-muted"><?= esc($t['actualizada'] ?? '—') ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</form>

<script src="<?= v_asset('js/admin-bd.js') ?>"></script>
<?= $this->endSection() ?>
