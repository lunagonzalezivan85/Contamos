<?= $this->extend('layouts/partner') ?>
<?= $this->section('content') ?>

<?php
$m2     = $mon ?? 'C$';
$volver = '/' . uri_string() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');

// Formatea un valor según la unidad de la métrica.
$fmt = static function (float $v, string $unidad) use ($m2): string {
    return match ($unidad) {
        'MONTO'      => $m2 . ' ' . number_format($v, 2),
        'PORCENTAJE' => number_format($v, 2) . '%',
        default      => number_format($v, 0),
    };
};
$periodoLbl = static fn(string $p): string => date('M Y', strtotime($p . '-01'));
$hayFiltro  = ($filtros['empleado_id'] ?? null) || ($filtros['incluir_anuladas'] ?? false);
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Plan de metas</h3>
        <p class="page-subtitle">
            Objetivos por gestor · <?= esc($periodoLbl($filtros['periodo'])) ?>
            <?php if ($hayFiltro): ?>
                · filtrado <a href="<?= base_url('finanzas/metas') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Filtrar</button>
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-metricas"><?= icon('list', 14) ?> Métricas</button>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modal-meta"><?= icon('plus', 14) ?> Nueva meta</button>
    </div>
</div>

<!-- Tarjetas resumen -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= (int) $resumen['total'] ?></div>
        <div class="stat-lbl">Metas del periodo</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--primary-dark);"><?= (int) $resumen['cumplidas'] ?></div>
        <div class="stat-lbl">Cumplidas</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:var(--text-muted);"><?= (int) $resumen['en_curso'] ?></div>
        <div class="stat-lbl">En curso</div>
    </div>
    <div class="stat-card">
        <div class="stat-num" style="color:#C0392B;"><?= (int) $resumen['incumplidas'] ?></div>
        <div class="stat-lbl">Incumplidas</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= number_format($resumen['promedio_pct'], 1) ?>%</div>
        <div class="stat-lbl">Cumplimiento promedio</div>
    </div>
</div>

<!-- Listado -->
<div class="card">
    <?php if (empty($metas)): ?>
        <p class="card-subtitle">Sin metas registradas en el periodo. Creá una con "Nueva meta".</p>
    <?php else: ?>
    <div class="table-wrap">
    <table class="tbl">
        <thead>
            <tr>
                <th>Gestor</th>
                <th>Métrica</th>
                <th>Periodo</th>
                <th>Meta</th>
                <th>Avance</th>
                <th style="min-width:150px;">Cumplimiento</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($metas as $m): ?>
            <?php
            $esMax     = $m['modo'] === 'MAXIMO';
            $barColor  = $m['cumplida'] ? 'var(--primary)' : ($m['cerrado'] ? '#B42318' : '#E8A33D');
            $estadoLbl = $m['estado'] !== 'ACTIVO' ? 'Anulada'
                : ($m['cumplida'] ? ($esMax ? 'Dentro del techo' : 'Cumplida')
                : ($m['cerrado'] ? 'Incumplida' : 'En curso'));
            $estadoCls = $m['estado'] !== 'ACTIVO' ? 'st-bad'
                : ($m['cumplida'] ? 'st-ok' : ($m['cerrado'] ? 'st-bad' : 'st-warn'));
            ?>
            <tr <?= $m['estado'] !== 'ACTIVO' ? 'style="opacity:.55;"' : '' ?>>
                <td>
                    <?= esc(trim(($m['nombres'] ?? '') . ' ' . ($m['apellidos'] ?? ''))) ?>
                    <?php if (!empty($m['cargo'])): ?>
                        <br><small class="card-subtitle"><?= esc($m['cargo']) ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?= esc($m['metrica']) ?>
                    <?= $m['calculo'] === 'MANUAL' ? '<span class="badge st-info">Manual</span>' : '' ?>
                    <br><small class="card-subtitle" title="Cómo se calcula"><?= esc($m['formula']) ?></small>
                </td>
                <td style="white-space:nowrap;"><?= esc($periodoLbl($m['periodo'])) ?></td>
                <td style="text-align:right; white-space:nowrap;">
                    <?= esc($fmt((float) $m['meta'], $m['unidad'])) ?>
                    <br><small class="card-subtitle"><?= $esMax ? 'no superar' : 'al menos' ?></small>
                </td>
                <td style="text-align:right; white-space:nowrap; font-weight:700;">
                    <?= esc($fmt((float) $m['avance_calc'], $m['unidad'])) ?>
                </td>
                <td>
                    <div style="height:6px;border-radius:3px;background:#EEF1F6;overflow:hidden;">
                        <div style="height:100%;width:<?= min(100, (float) $m['pct']) ?>%;background:<?= $barColor ?>;"></div>
                    </div>
                    <small class="card-subtitle"><?= number_format((float) $m['pct'], 1) ?>%</small>
                </td>
                <td><span class="badge <?= $estadoCls ?>"><?= $estadoLbl ?></span></td>
                <td style="white-space:nowrap; text-align:right;">
                    <?php if ($m['estado'] === 'ACTIVO'): ?>
                        <button type="button" class="btn btn-outline btn-sm" title="Editar"
                                data-meta-edit="<?= esc(json_encode($m), 'attr') ?>"><?= icon('edit', 13) ?></button>
                        <form method="post" action="<?= base_url('finanzas/metas/' . $m['id'] . '/anular') ?>"
                              onsubmit="return confirm('¿Anular esta meta?');" style="display:inline;">
                            <?= csrf_field() ?>
                            <input type="hidden" name="volver" value="<?= esc($volver, 'attr') ?>">
                            <button class="btn btn-outline btn-sm" title="Anular"><?= icon('x', 13) ?></button>
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

<!-- Modal: filtros -->
<div class="modal-overlay" id="modal-filtro" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4>Filtrar metas</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('finanzas/metas') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Periodo</label>
                    <input type="month" name="periodo" value="<?= esc($filtros['periodo']) ?>">
                </div>
                <div class="form-group">
                    <label>Gestor</label>
                    <select name="empleado">
                        <option value="">Todos</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= (int) $e['id'] ?>" <?= (string) $filtros['empleado_id'] === (string) $e['id'] ? 'selected' : '' ?>>
                                <?= esc($e['nombres'] . ' ' . $e['apellidos']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label style="display:flex;gap:8px;align-items:center;font-weight:500;">
                        <input type="checkbox" name="anuladas" value="1" <?= $filtros['incluir_anuladas'] ? 'checked' : '' ?> style="width:auto;">
                        Incluir anuladas
                    </label>
                </div>
            </div>
            <div class="modal-foot">
                <a class="btn" href="<?= base_url('finanzas/metas') ?>">Limpiar</a>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: nueva / editar meta -->
<div class="modal-overlay" id="modal-meta" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4 data-add-title="Nueva meta" data-edit-title="Editar meta">Nueva meta</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('finanzas/metas') ?>" data-add-url="<?= base_url('finanzas/metas') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="volver" value="<?= esc($volver, 'attr') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Gestor *</label>
                    <select name="empleado_id" required>
                        <option value="">— Seleccioná —</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= (int) $e['id'] ?>" <?= (string) old('empleado_id') === (string) $e['id'] ? 'selected' : '' ?>>
                                <?= esc($e['nombres'] . ' ' . $e['apellidos']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Periodo *</label>
                    <input type="month" name="periodo" required value="<?= esc(old('periodo') ?: date('Y-m')) ?>">
                </div>
                <div class="form-group form-full">
                    <label>Métrica *</label>
                    <select name="metrica_id" id="meta-metrica" required>
                        <option value="">— Seleccioná —</option>
                        <?php foreach ($metricas as $mt): ?>
                            <option value="<?= (int) $mt['id'] ?>"
                                    data-unidad="<?= esc($mt['unidad']) ?>"
                                    data-calculo="<?= esc($mt['calculo']) ?>"
                                    data-formula="<?= esc($mt['formula'] ?? '', 'attr') ?>"
                                    <?= (string) old('metrica_id') === (string) $mt['id'] ? 'selected' : '' ?>>
                                <?= esc($mt['nombre']) ?>
                                <?= $mt['calculo'] === 'MANUAL' ? '(manual)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <small id="meta-formula" class="card-subtitle" style="display:block; margin-top:6px;"></small>
                </div>
                <div class="form-group">
                    <label>Meta objetivo * <span id="meta-unidad" class="card-subtitle"></span></label>
                    <input type="number" name="meta" step="0.01" min="0" required value="<?= esc(old('meta')) ?>" placeholder="0.00">
                </div>
                <div class="form-group" id="grp-avance" hidden>
                    <label>Avance actual</label>
                    <input type="number" name="avance" step="0.01" min="0" value="<?= esc(old('avance') ?? 0) ?>">
                    <small class="card-subtitle">Solo métricas manuales; las automáticas se calculan solas.</small>
                </div>
                <div class="form-group form-full">
                    <label>Notas</label>
                    <input type="text" name="notas" maxlength="255" value="<?= esc(old('notas')) ?>" placeholder="Opcional">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check', 15) ?> Guardar meta</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: métricas -->
<div class="modal-overlay" id="modal-metricas" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('target', 17) ?> Métricas del plan</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <div class="modal-body" style="display:block;">
            <div class="oui-list">
                <?php foreach ($todas as $mt): ?>
                    <div class="oui-list-item" <?= $mt['estado'] !== 'ACTIVO' ? 'style="opacity:.55;"' : '' ?>>
                        <span class="oui-list-icon"><?= icon('target', 18) ?></span>
                        <div class="oui-list-body">
                            <div class="oui-list-title">
                                <?= esc($mt['nombre']) ?>
                                <span class="badge <?= $mt['estado'] === 'ACTIVO' ? 'st-ok' : 'st-bad' ?>">
                                    <?= $mt['estado'] === 'ACTIVO' ? 'Activa' : 'Inactiva' ?>
                                </span>
                                <span class="badge st-info"><?= $mt['calculo'] === 'AUTO' ? 'Auto' : 'Manual' ?></span>
                            </div>
                            <div class="oui-list-sub">
                                <?= esc($unidades[$mt['unidad']] ?? $mt['unidad']) ?> ·
                                <?= esc($modos[$mt['modo']] ?? $mt['modo']) ?> ·
                                <?= esc($mt['formula'] ?? '') ?>
                            </div>
                        </div>
                        <span class="oui-list-meta">
                            <form method="post" action="<?= base_url('finanzas/metas/metricas/' . $mt['id'] . '/toggle') ?>">
                                <?= csrf_field() ?>
                                <button class="btn btn-outline btn-sm">
                                    <?= $mt['estado'] === 'ACTIVO' ? 'Desactivar' : 'Activar' ?>
                                </button>
                            </form>
                        </span>
                    </div>
                <?php endforeach; ?>
            </div>

            <form method="post" action="<?= base_url('finanzas/metas/metricas') ?>"
                  style="margin-top:16px; padding-top:16px; border-top:1px solid #EDF0F5;
                         display:grid; grid-template-columns:1fr 1fr; gap:14px;">
                <?= csrf_field() ?>
                <div class="form-group form-full">
                    <label>Nueva métrica (avance manual)</label>
                    <input type="text" name="nombre" maxlength="100" required placeholder="Ej: Renovaciones cerradas">
                </div>
                <div class="form-group">
                    <label>Unidad</label>
                    <select name="unidad">
                        <?php foreach ($unidades as $k => $lbl): ?><option value="<?= $k ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Modo</label>
                    <select name="modo">
                        <?php foreach ($modos as $k => $lbl): ?><option value="<?= $k ?>"><?= esc($lbl) ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-full">
                    <label>Cómo se calcula (descripción)</label>
                    <input type="text" name="formula" maxlength="255" placeholder="Ej: Cantidad de créditos renovados en el mes — se ingresa a mano.">
                </div>
                <div class="form-full">
                    <button type="submit" class="btn btn-primary btn-sm"><?= icon('plus', 14) ?> Agregar métrica</button>
                </div>
            </form>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" data-close>Cerrar</button>
        </div>
    </div>
</div>

<script>
(function () {
    const selMetrica = document.getElementById('meta-metrica');
    const lblFormula = document.getElementById('meta-formula');
    const lblUnidad  = document.getElementById('meta-unidad');
    const grpAvance  = document.getElementById('grp-avance');
    const inputAvance = grpAvance.querySelector('input[name="avance"]');
    const unidadLbl  = { MONTO: '(<?= esc($m2) ?>)', CANTIDAD: '(cantidad)', PORCENTAJE: '(%)' };

    function syncMetrica() {
        const opt = selMetrica.options[selMetrica.selectedIndex];
        const esManual = opt && opt.dataset.calculo === 'MANUAL';
        lblFormula.textContent = opt && opt.dataset.formula ? 'Cálculo: ' + opt.dataset.formula : '';
        lblUnidad.textContent  = opt && opt.dataset.unidad ? (unidadLbl[opt.dataset.unidad] || '') : '';
        grpAvance.hidden = !esManual;
        inputAvance.disabled = !esManual;
    }
    selMetrica.addEventListener('change', syncMetrica);

    const form  = selMetrica.closest('form');
    const modal = document.getElementById('modal-meta');

    document.querySelectorAll('[data-meta-edit]').forEach(function (btn) {
        btn.addEventListener('click', function () {
            const m = JSON.parse(btn.dataset.metaEdit);
            form.action = '<?= base_url('finanzas/metas') ?>/' + m.id + '/editar';
            modal.querySelector('h4').textContent = modal.querySelector('h4').dataset.editTitle;
            form.empleado_id.value = m.empleado_id;
            form.periodo.value     = m.periodo;
            form.metrica_id.value  = m.metrica_id;
            form.meta.value        = m.meta;
            inputAvance.value      = m.avance;
            form.notas.value       = m.notas || '';
            syncMetrica();
            modal.hidden = false;
            modal.classList.add('open');
        });
    });

    // Al abrir para nueva meta, sincronizar estado de campos dependientes
    document.querySelector('[data-modal="modal-meta"]').addEventListener('click', syncMetrica);
    syncMetrica();
})();
</script>

<?= $this->endSection() ?>
