<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<div class="list-head">
    <div>
        <h3 class="page-title">Categorías de reportes</h3>
        <p class="page-subtitle">Organice el índice /reportes por secciones propias de su empresa.</p>
    </div>
    <div class="detail-hero-actions">
        <a class="btn btn-outline btn-sm" href="<?= base_url('reportes') ?>"><?= icon('bar-chart', 14) ?> Ver índice</a>
        <a class="btn btn-outline btn-sm" href="<?= base_url('configuracion') ?>"><?= icon('arrow-left', 14) ?> Configuración</a>
    </div>
</div>

<!-- Categorías personalizadas -->
<div class="card">
    <h4 class="card-title">Categorías</h4>
    <p class="card-subtitle">Las categorías por defecto del catálogo (Créditos, Finanzas, Regulatorio) siempre existen; aquí agrega las suyas.</p>

    <form method="post" action="<?= base_url('configuracion/reportes/categoria') ?>" class="form" style="display:flex; gap:10px; align-items:flex-end; flex-wrap:wrap; margin-top:12px;">
        <?= csrf_field() ?>
        <div class="form-group" style="flex:1; min-width:200px; margin-bottom:0;">
            <label>Nombre de la categoría</label>
            <input type="text" name="nombre" maxlength="80" required placeholder="Ej: Operación diaria">
        </div>
        <div class="form-group" style="width:100px; margin-bottom:0;">
            <label>Orden</label>
            <input type="number" name="orden" value="0" min="0" max="99">
        </div>
        <button type="submit" class="btn btn-primary"><?= icon('plus', 14) ?> Agregar</button>
    </form>

    <?php if (!empty($categorias)): ?>
    <div class="table-wrap mt-4">
        <table class="tbl">
            <thead>
                <tr><th>Nombre</th><th>Orden</th><th>Activa</th><th></th></tr>
            </thead>
            <tbody>
            <?php foreach ($categorias as $c): ?>
                <tr>
                    <td colspan="4" style="padding:8px 0;">
                        <form method="post" action="<?= base_url('configuracion/reportes/categoria/' . (int) $c['id']) ?>"
                              style="display:flex; gap:10px; align-items:center; flex-wrap:wrap;">
                            <?= csrf_field() ?>
                            <input type="text" name="nombre" value="<?= esc($c['nombre']) ?>" maxlength="80" required style="flex:1; min-width:160px;">
                            <input type="number" name="orden" value="<?= (int) $c['orden'] ?>" min="0" max="99" style="width:70px;">
                            <label style="display:flex; align-items:center; gap:6px; margin:0;">
                                <input type="checkbox" name="activo" value="1" <?= $c['activo'] ? 'checked' : '' ?>> Activa
                            </label>
                            <button type="submit" class="btn btn-outline btn-sm"><?= icon('check', 14) ?> Guardar</button>
                            <button type="submit" class="btn btn-outline btn-sm" formaction="<?= base_url('configuracion/reportes/categoria/' . (int) $c['id'] . '/eliminar') ?>"
                                    onclick="return confirm('¿Eliminar esta categoría? Los reportes vuelven a su categoría por defecto.')">
                                <?= icon('trash', 14) ?> Eliminar
                            </button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
        <p class="card-subtitle" style="margin-top:12px;">Aún no hay categorías personalizadas.</p>
    <?php endif; ?>
</div>

<!-- Asignación reporte → categoría -->
<div class="card">
    <h4 class="card-title">Asignar reportes</h4>
    <p class="card-subtitle">Elija en qué categoría aparece cada reporte en el índice. «Por defecto» usa la categoría del catálogo.</p>

    <form method="post" action="<?= base_url('configuracion/reportes/asignar') ?>">
        <?= csrf_field() ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr><th>Reporte</th><th>Descripción</th><th>Default</th><th>Categoría</th></tr>
                </thead>
                <tbody>
                <?php foreach ($catalogo as $key => $r): ?>
                    <?php $sel = $asignaciones[$key] ?? null; ?>
                    <tr>
                        <td class="fw-bold"><?= esc($r['nombre']) ?></td>
                        <td class="card-subtitle"><?= esc($r['descripcion']) ?></td>
                        <td><span class="badge badge-soft"><?= esc($r['cat']) ?></span></td>
                        <td style="min-width:200px;">
                            <select name="cat[<?= esc($key) ?>]">
                                <option value="0" <?= !$sel ? 'selected' : '' ?>>— Por defecto —</option>
                                <?php foreach ($categorias as $c): ?>
                                    <option value="<?= (int) $c['id'] ?>" <?= $sel === (int) $c['id'] ? 'selected' : '' ?>>
                                        <?= esc($c['nombre']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar asignaciones</button>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
