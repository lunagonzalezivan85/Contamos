<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$m2     = $mon ?? 'C$';
$hayFiltro = $filtros['desde'] || $filtros['hasta'] || $filtros['tipo'] || $filtros['buscar'] !== '';
$volver = '/' . uri_string() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
$colorTipo = ['VENTA' => 'st-ok', 'DONACION' => 'st-info', 'OTRO' => 'st-warn'];
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Ingresos</h3>
        <p class="page-subtitle">
            Otros ingresos del negocio — ventas generan recibo con IVA
            <?php if ($hayFiltro): ?>
                · filtrado <a href="<?= base_url('finanzas/ingresos') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Filtrar</button>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modal-ingreso"><?= icon('plus', 14) ?> Registrar ingreso</button>
    </div>
</div>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format($metricas['total_mes'], 2) ?></div>
        <div class="stat-lbl">Ingresado este mes</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format($metricas['total_filtro'], 2) ?></div>
        <div class="stat-lbl"><?= $hayFiltro ? 'Total del filtro' : 'Total listado' ?></div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= (int) $metricas['n_filtro'] ?></div>
        <div class="stat-lbl">Registros</div>
    </div>
    <?php if (!empty($metricas['por_tipo']['VENTA'])): ?>
    <div class="stat-card">
        <div class="stat-num"><?= esc($m2) ?> <?= number_format($metricas['por_tipo']['VENTA'], 2) ?></div>
        <div class="stat-lbl">Ventas</div>
    </div>
    <?php endif; ?>
</div>

<!-- Listado -->
<div class="card">
    <h4 class="card-title"><?= icon('trending-up', 16) ?> Movimientos</h4>
    <?php if (empty($ingresos)): ?>
        <p class="card-subtitle">Sin ingresos registrados<?= $hayFiltro ? ' con este filtro' : '' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Tipo</th><th>Concepto</th><th>Método</th>
                        <th>Referencia</th><th>Monto</th>
                        <?php if ($filtros['anulados']): ?><th>Estado</th><?php endif; ?>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($ingresos as $g):
                    $tot = \App\Models\IngresoModel::total($g);
                    $iva = \App\Models\IngresoModel::iva($g);
                ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc(date('d/m/Y', strtotime($g['fecha']))) ?></td>
                        <td><span class="badge <?= $colorTipo[$g['tipo']] ?? 'badge-soft' ?>"><?= esc($tipos[$g['tipo']] ?? $g['tipo']) ?></span></td>
                        <td>
                            <strong><?= esc($g['concepto']) ?></strong>
                            <?php
                            $sub = implode(' · ', array_filter([
                                $g['descripcion'] ?? '',
                                trim(($g['nombres'] ?? '') . ' ' . ($g['apellidos'] ?? '')) ?: '',
                                $iva > 0 ? 'IVA ' . rtrim(rtrim(number_format((float) $g['iva_pct'], 2), '0'), '.') . '%' : '',
                                $g['reg_usuario'] ? 'reg. ' . $g['reg_usuario'] : '',
                            ]));
                            if ($sub !== ''): ?>
                                <br><small class="card-subtitle"><?= esc($sub) ?></small>
                            <?php endif; ?>
                        </td>
                        <td><?= esc($metodos[$g['metodo']] ?? $g['metodo']) ?></td>
                        <td><?= esc($g['referencia'] ?: '—') ?></td>
                        <td><strong><?= esc($m2) ?> <?= number_format($tot, 2) ?></strong></td>
                        <?php if ($filtros['anulados']): ?>
                            <td><span class="badge <?= $g['estado'] === 'ANULADO' ? 'st-bad' : 'st-ok' ?>"><?= esc($lblEstado[$g['estado']] ?? $g['estado']) ?></span></td>
                        <?php endif; ?>
                        <td style="white-space:nowrap;">
                            <?php if ($g['tipo'] === 'VENTA'): ?>
                                <a class="btn btn-outline btn-sm" title="Ver recibo" target="_blank"
                                   href="<?= base_url('finanzas/ingresos/' . $g['id'] . '/recibo') ?>">
                                    <?= icon('credit-card', 14) ?>
                                </a>
                            <?php endif; ?>
                            <?php if ($g['estado'] === 'ACTIVO'): ?>
                                <button type="button" class="btn btn-outline btn-sm" title="Editar"
                                        data-ingreso-edit="<?= esc(json_encode([
                                            'url'    => base_url('finanzas/ingresos/' . $g['id'] . '/editar'),
                                            'campos' => [
                                                'tipo'        => $g['tipo'],
                                                'empleado_id' => $g['empleado_id'] ?? '',
                                                'fecha'       => $g['fecha'],
                                                'concepto'    => $g['concepto'],
                                                'descripcion' => $g['descripcion'] ?? '',
                                                'referencia'  => $g['referencia'] ?? '',
                                                'monto'       => $g['monto'],
                                                'iva_pct'     => $g['iva_pct'],
                                                'metodo'      => $g['metodo'],
                                            ],
                                        ]), 'attr') ?>">
                                    <?= icon('edit', 14) ?>
                                </button>
                                <form method="post" action="<?= base_url('finanzas/ingresos/' . $g['id'] . '/anular') ?>"
                                      style="display:inline;"
                                      onsubmit="return confirm('¿Anular este ingreso de <?= esc($m2, 'attr') ?> <?= number_format($tot, 2) ?>? Ya no sumará en los totales.');">
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
            <h4><?= icon('search', 17) ?> Filtrar ingresos</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('finanzas/ingresos') ?>">
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
                    <label>Tipo</label>
                    <select name="tipo">
                        <option value="">— Todos —</option>
                        <?php foreach ($tipos as $k => $lbl): ?>
                            <option value="<?= esc($k) ?>" <?= $filtros['tipo'] === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
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
                <a href="<?= base_url('finanzas/ingresos') ?>" class="btn">Limpiar</a>
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: registrar / editar ingreso -->
<div class="modal-overlay" id="modal-ingreso" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4 data-add-title="Registrar ingreso"
                data-edit-title="Editar ingreso">Registrar ingreso</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('finanzas/ingresos') ?>" data-add-url="<?= base_url('finanzas/ingresos') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="volver" value="<?= esc($volver, 'attr') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Tipo de ingreso *</label>
                    <select name="tipo" id="ing-tipo" required>
                        <?php foreach ($tipos as $k => $lbl): ?>
                            <option value="<?= esc($k) ?>"><?= esc($lbl) ?></option>
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
                <div class="form-group" id="ing-iva-wrap">
                    <label>IVA % <small style="color:#64748B; font-weight:400;">(0 = sin IVA, se desglosa en el recibo)</small></label>
                    <input type="number" name="iva_pct" step="0.01" min="0" max="100" value="0">
                </div>
                <div class="form-group">
                    <label>Método de cobro</label>
                    <select name="metodo">
                        <?php foreach ($metodos as $k => $lbl): ?>
                            <option value="<?= esc($k) ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Referencia</label>
                    <input type="text" name="referencia" maxlength="100"
                           placeholder="N° factura / comprobante">
                </div>
                <div class="form-group form-full">
                    <label>Concepto *</label>
                    <input type="text" name="concepto" maxlength="150" required
                           placeholder="Ej: Venta de mercadería">
                </div>
                <div class="form-group form-full">
                    <label>Descripción</label>
                    <input type="text" name="descripcion" maxlength="500" placeholder="Detalle adicional (opcional)">
                </div>
                <div class="form-group form-full">
                    <label>Cobró</label>
                    <select name="empleado_id">
                        <option value="">— Oficina / general —</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= (int) $e['id'] ?>"><?= esc(trim($e['nombres'] . ' ' . $e['apellidos'])) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Guardar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    var tipoSel = document.getElementById('ing-tipo');
    var ivaWrap = document.getElementById('ing-iva-wrap');
    function toggleIva() {
        if (!tipoSel || !ivaWrap) return;
        var esVenta = tipoSel.value === 'VENTA';
        ivaWrap.style.display = esVenta ? '' : 'none';
        var inp = ivaWrap.querySelector('input');
        if (inp && !esVenta) inp.value = '0';
    }
    if (tipoSel) {
        tipoSel.addEventListener('change', toggleIva);
        toggleIva();
    }

    // Editar ingreso — prellena el modal y apunta el form a /editar.
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-ingreso-edit]');
        if (!btn) return;
        var d = {};
        try { d = JSON.parse(btn.getAttribute('data-ingreso-edit') || '{}'); } catch (err) {}
        var md = document.getElementById('modal-ingreso');
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
        toggleIva();
        md.hidden = false;
    });
})();
</script>
<?= $this->endSection() ?>
