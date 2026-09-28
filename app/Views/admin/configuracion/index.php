<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Configuración</h3>
        <p class="page-sub">Catálogo de planes SaaS — precio y límites (-1 = ilimitado).</p>
    </div>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Plan</th>
                <th>Precio/mes</th>
                <th>Créditos activos</th>
                <th>Empleados</th>
                <th>Usuarios</th>
                <th>Tenants</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($planes)): ?>
                <tr><td colspan="7" class="text-muted">No hay planes registrados.</td></tr>
            <?php endif; ?>
            <?php foreach ($planes as $p): ?>
                <tr>
                    <form method="post" action="<?= base_url('admin/configuracion/planes/' . $p['id']) ?>">
                        <?= csrf_field() ?>
                        <td>
                            <span class="fw-bold"><?= esc($p['nombre']) ?></span>
                            <?php if ($p['estado'] !== 'ACTIVO'): ?>
                                <span class="tag tag-inactivo"><?= esc($p['estado']) ?></span>
                            <?php endif; ?>
                            <br><small class="text-muted"><code><?= esc($p['slug']) ?></code></small>
                        </td>
                        <td>
                            <div style="display:flex; align-items:center; gap:6px;">
                                <?= esc($p['moneda'] ?? 'USD') ?>
                                <input class="inp" type="number" name="precio_mensual" step="0.01" min="0"
                                       value="<?= esc($p['precio_mensual']) ?>" style="width:110px;">
                            </div>
                        </td>
                        <td><input class="inp" type="number" name="max_creditos_activos" value="<?= esc($p['max_creditos_activos']) ?>" style="width:100px;"></td>
                        <td><input class="inp" type="number" name="max_empleados"        value="<?= esc($p['max_empleados']) ?>"        style="width:100px;"></td>
                        <td><input class="inp" type="number" name="max_usuarios"         value="<?= esc($p['max_usuarios']) ?>"         style="width:100px;"></td>
                        <td class="fw-bold" style="text-align:center;"><?= (int) $p['tenants'] ?></td>
                        <td><button class="btn btn-sm btn-primary">Guardar</button></td>
                    </form>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <p class="card-subtitle mt-4">
        Los límites se evalúan por <code>plan_al_dia()</code> / helpers de plan en cada alta
        (crédito, empleado, usuario). -1 significa sin tope.
    </p>
</div>

<?= $this->endSection() ?>
