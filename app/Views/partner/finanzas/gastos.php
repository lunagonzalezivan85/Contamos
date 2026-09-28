<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2     = $mon ?? 'C$';
$hayFiltro = $filtros['desde'] || $filtros['hasta'] || $filtros['categoria_id'] || $filtros['buscar'] !== '';
$volver = '/' . uri_string() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Gastos</h3>
        <p class="page-subtitle">
            Control de egresos del negocio por categoría
            <?php if ($hayFiltro): ?>
                · filtrado <a href="<?= base_url('finanzas/gastos') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Filtrar</button>
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-categorias"><?= icon('list', 14) ?> Categorías</button>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modal-gasto"><?= icon('plus', 14) ?> Registrar gasto</button>
    </div>
</div>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format($metricas['total_mes'], 2) ?></div>
        <div class="stat-lbl">Gastado este mes</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format($metricas['total_filtro'], 2) ?></div>
        <div class="stat-lbl"><?= $hayFiltro ? 'Total del filtro' : 'Total listado' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= (int) $metricas['n_filtro'] ?></div>
        <div class="stat-lbl">Registros</div>
    </div>
    <?php if (!empty($metricas['por_cat'][0])): ?>
    <div class="stat-card">
        <div class="stat-num" style="font-size:1rem;"><?= esc($metricas['por_cat'][0]['categoria']) ?></div>
        <div class="stat-lbl">Mayor gasto · <?= esc($m2) ?> <?= number_format((float) $metricas['por_cat'][0]['total'], 2) ?></div>
    </div>
    <?php endif; ?>
</div>

<!-- Resumen por categoría -->
<?php if (count($metricas['por_cat']) > 1): ?>
<div class="chips">
    <?php foreach ($metricas['por_cat'] as $c): ?>
        <a class="chip" href="<?= base_url('finanzas/gastos?categoria=' . (int) $c['categoria_id']) ?>" style="text-decoration:none;">
            <span class="chip-icon"><?= icon('minus-circle', 14) ?></span>
            <span class="chip-name"><?= esc($c['categoria']) ?></span>
            <span class="chip-val"><?= esc($m2) ?> <?= number_format((float) $c['total'], 2) ?> (<?= (int) $c['n'] ?>)</span>
        </a>
    <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Listado -->
<div class="card">
    <h4 class="card-title"><?= icon('trending-down', 16) ?> Movimientos</h4>
    <?php if (empty($gastos)): ?>
        <p class="card-subtitle">Sin gastos registrados<?= $hayFiltro ? ' con este filtro' : '' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Categoría</th><th>Concepto</th><th>Método</th>
                        <th>Referencia</th><th>Monto</th>
                        <?php if ($filtros['anulados']): ?><th>Estado</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($gastos as $g): ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($g['fecha']))) ?></td>
                        <td><span class="badge badge-soft"><?= esc($g['categoria']) ?></span></td>
                        <td>
                            <strong><?= esc($g['concepto']) ?></strong>
                            <?php
                            $sub = implode(' · ', array_filter([
                                $g['descripcion'] ?? '',
                                trim(($g['nombres'] ?? '') . ' ' . ($g['apellidos'] ?? '')) ?: '',
                                $g['reg_usuario'] ? 'reg. ' . $g['reg_usuario'] : '',
                            ]));
                            if ($sub !== ''): ?>
                                <br><small class="card-subtitle"><?= esc($sub) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($metodos[$g['metodo']] ?? $g['metodo']) ?></td>
                        <td><?= esc($g['referencia'] ?: '—') ?></td>
                        <td><strong><?= esc($m2) ?> <?= number_format((float) $g['monto'], 2) ?></strong></td>
                        <?php if ($filtros['anulados']): ?>
                            <td><span class="badge <?= $g['estado'] === 'ANULADO' ? 'st-bad' : 'st-ok' ?>"><?= esc($lblEstado[$g['estado']] ?? $g['estado']) ?></span></td>
                        <?php endif; ?>
                        <td style="white-space:nowrap;">
                            <?php if ($g['estado'] === 'ACTIVO'): ?>
                                <button type="button" class="btn btn-outline btn-sm" title="Editar"
                                        data-gasto-edit="<?= esc(json_encode([
                                            'url'    => base_url('finanzas/gastos/' . $g['id'] . '/editar'),
                                            'campos' => [
                                                'categoria_id' => $g['categoria_id'],
                                                'empleado_id'  => $g['empleado_id'] ?? '',
                                                'fecha'        => $g['fecha'],
                                                'concepto'     => $g['concepto'],
                                                'descripcion'  => $g['descripcion'] ?? '',
                                                'referencia'   => $g['referencia'] ?? '',
                                                'monto'        => $g['monto'],
                                                'metodo'       => $g['metodo'],
                                            ],
                                        ]), 'attr') ?>">
                                    <?= icon('edit', 14) ?>
                                </button>
                                <form method="post" action="<?= base_url('finanzas/gastos/' . $g['id'] . '/anular') ?>"
                                      style="display:inline;"
                                      onsubmit="return confirm('¿Anular este gasto de <?= esc($m2, 'attr') ?> <?= number_format((float) $g['monto'], 2) ?>? Ya no sumará en los totales.');">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="volver" value="<?= esc($volver, 'attr') ?>">
                                    <button class="btn btn-outline btn-sm" title="Anular"><?= icon('x', 14) ?></button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<!-- Modal: filtrar -->
<div class="modal-overlay" id="modal-filtro" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('search', 17) ?> Filtrar gastos</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('finanzas/gastos') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= esc($filtros['desde']) ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= esc($filtros['hasta']) ?>" max="<?= date('Y-m-d') ?>">
                </div>
                <div class="form-group">
                    <label>Categoría</label>
                    <select name="categoria">
                        <option value="">— Todas —</option>
                        <?php foreach ($categorias as $c): ?>
                            <option value="<?= (int) $c['id'] ?>" <?= (int) $filtros['categoria_id'] === (int) $c['id'] ? 'selected' : '' ?>>
                                <?= esc($c['nombre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Buscar</label>
                    <input type="text" name="buscar" maxlength="80" value="<?= esc($filtros['buscar']) ?>"
                           placeholder="Concepto, referencia o descripción">
                </div>
                <div class="form-group form-full" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" id="f-anulados" name="anulados" value="1" <?= $filtros['anulados'] ? 'checked' : '' ?> style="width:auto;">
                    <label for="f-anulados" style="margin:0;">Incluir anulados</label>
                </div>
            </div>
            <div class="modal-foot">
                <a href="<?= base_url('finanzas/gastos') ?>" class="btn">Limpiar</a>
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: registrar / editar gasto -->
<div class="modal-overlay" id="modal-gasto" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4 data-add-title="Registrar gasto"
                data-edit-title="Editar gasto">Registrar gasto</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('finanzas/gastos') ?>" data-add-url="<?= base_url('finanzas/gastos') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="volver" value="<?= esc($volver, 'attr') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tipo de gasto *</label>
                    <select name="categoria_id" required>
                        <option value="">— Seleccioná —</option>
                        <?php foreach ($categorias as $c): if ($c['estado'] !== 'ACTIVO') continue; ?>
                            <option value="<?= (int) $c['id'] ?>"><?= esc($c['nombre']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Fecha *</label>
                    <input type="date" name="fecha" required max="<?= date('Y-m-d') ?>" value="<?= esc(old('fecha', date('Y-m-d'))) ?>">
                </div>
                <div class="form-group">
                    <label>Monto (<?= esc($m2) ?>) *</label>
                    <input type="number" name="monto" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Método</label>
                    <select name="metodo">
                        <?php foreach ($metodos as $k => $lbl): ?>
                            <option value="<?= esc($k) ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-full">
                    <label>Concepto *</label>
                    <input type="text" name="concepto" maxlength="150" required
                           placeholder="Ej: Compra de tinta para impresora">
                </div>
                <div class="form-group">
                    <label>Referencia</label>
                    <input type="text" name="referencia" maxlength="100"
                           placeholder="N° factura / recibo">
                </div>
                <div class="form-group">
                    <label>Lo pagó</label>
                    <select name="empleado_id">
                        <option value="">— Oficina / general —</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= (int) $e['id'] ?>"><?= esc(trim($e['nombres'] . ' ' . $e['apellidos'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-full">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" maxlength="500" placeholder="Detalle adicional (opcional)">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: categorías -->
<div class="modal-overlay" id="modal-categorias" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('list', 17) ?> Tipos de gasto</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <div class="modal-body" style="display:block;">
            <div class="oui-list">
                <?php foreach ($categorias as $c): ?>
                    <div class="oui-list-item" <?= $c['estado'] !== 'ACTIVO' ? 'style="opacity:.55;"' : '' ?>>
                        <span class="oui-list-icon"><?= icon('minus-circle', 18) ?></span>
                        <div class="oui-list-body">
                            <div class="oui-list-title">
                                <?= esc($c['nombre']) ?>
                                <span class="badge <?= $c['estado'] === 'ACTIVO' ? 'st-ok' : 'st-bad' ?>">
                                    <?= $c['estado'] === 'ACTIVO' ? 'Activa' : 'Inactiva' ?>
                                </span>
                            </div>
                            <?php if (!empty($c['descripcion'])): ?>
                                <div class="oui-list-sub"><?= esc($c['descripcion']) ?></div>
                            <?php endif; ?>
                        </div>
                        <span class="oui-list-meta">
                            <form method="post" action="<?= base_url('finanzas/gastos/categorias/' . $c['id'] . '/toggle') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline btn-sm">
                                    <?= $c['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="<?= base_url('finanzas/gastos/categorias') ?>"
                  style="margin-top:16px; padding-top:16px; border-top:1px solid #EDF0F5;
                         display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label>Nueva categoría</label>
                    <input type="text" name="nombre" maxlength="80" required placeholder="Ej: Publicidad">
                </div>
                <div class="form-group">
                    <label>Descripción (opcional)</label>
                    <input type="text" name="descripcion" maxlength="255">
                </div>
                <div class="form-full">
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('plus', 14) ?> Agregar categoría</button>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" data-close>Cerrar</button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    // Editar gasto — prellena el modal y apunta el form a /editar.
    // Se ejecuta después del reset global (data-modal) porque se registra después.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-gasto-edit]');
        if (!btn) return;
        var d = {};
        try { d = JSON.parse(btn.getAttribute('data-gasto-edit') || '{}'); } catch (err) {}
        var md = document.getElementById('modal-gasto');
        if (!md) return;

        var form = md.querySelector('form');
        if (form && d.url) form.action = d.url;

        var titulo = md.querySelector('[data-edit-title]');
        if (titulo && titulo.dataset.editTitle) titulo.innerHTML = titulo.dataset.editTitle;

        var campos = d.campos || {};
        for (var name in campos) {
            var inp = md.querySelector('[name="' + name + '"]');
            if (inp) inp.value = campos[name];
        }
        md.hidden = false;
    });
})();
</script>
<?= $this->endSection() ?>
