<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php
$hayFiltro = $filtros['desde'] || $filtros['hasta'] || $filtros['empleado_id'] || $filtros['abiertas'] || $filtros['anuladas'];
$volver    = '/' . uri_string() . (($_SERVER['QUERY_STRING'] ?? '') !== '' ? '?' . $_SERVER['QUERY_STRING'] : '');
$stCls     = ['ABIERTA' => 'st-warn', 'CERRADA' => 'st-ok', 'ANULADA' => 'st-bad'];
$stLbl     = ['ABIERTA' => 'Adentro', 'CERRADA' => 'Completa', 'ANULADA' => 'Anulada'];
$fmtHora   = fn($dt) => $dt ? date('h:i a', strtotime($dt)) : '—';
$fmtFecha  = fn($d) => date('d/m/Y', strtotime($d));
$fmtHoras  = function (array $a): string {
    $h = \App\Models\AsistenciaModel::horas($a);
    return $h > 0 ? rtrim(rtrim(number_format($h, 2), '0'), '.') . ' h' : '—';
};
?>

<div class="list-head">
    <div>
        <h3 class="page-title">Asistencia</h3>
        <p class="page-subtitle">
            Marcaciones del personal — el personal marca con carnet + PIN en el kiosco
            <?php if ($hayFiltro): ?>
                · filtrado <a href="<?= base_url('asistencia') ?>" class="badge badge-soft">quitar filtro</a>
            <?php endif; ?>
        </p>
    </div>
    <div class="detail-hero-actions">
        <a class="btn btn-outline btn-sm" href="<?= base_url(ltrim($kioscoUrl, '/')) ?>" target="_blank"><?= icon('external-link', 14) ?> Abrir kiosco</a>
        <button type="button" class="btn btn-outline btn-sm" data-modal="modal-filtro"><?= icon('search', 14) ?> Filtrar</button>
        <button type="button" class="btn btn-primary btn-sm" data-modal="modal-marca"><?= icon('plus', 14) ?> Agregar marcación</button>
    </div>
</div>

<!-- Stat cards -->
<div class="stats-grid">
    <div class="stat-card">
        <div class="stat-num"><?= (int) $metricas['adentro'] ?></div>
        <div class="stat-lbl">Adentro ahora</div>
    </div>
    <div class="stat-card">
        <div class="stat-num"><?= (int) $metricas['hoy'] ?></div>
        <div class="stat-lbl">Jornadas de hoy</div>
    </div>
</div>

<!-- Quién está adentro -->
<?php if (!empty($metricas['abiertas'])): ?>
<div class="card">
    <h4 class="card-title"><?= icon('clock', 16) ?> Adentro ahora</h4>
    <div class="chips">
        <?php foreach ($metricas['abiertas'] as $a): ?>
            <span class="chip">
                <span class="chip-icon"><?= icon('user', 14) ?></span>
                <span class="chip-name"><?= esc(trim($a['nombres'] . ' ' . $a['apellidos'])) ?></span>
                <span class="chip-val">desde <?= esc($fmtHora($a['entrada'])) ?><?= substr($a['entrada'], 0, 10) !== date('Y-m-d') ? ' · ' . esc($fmtFecha($a['entrada'])) : '' ?></span>
            </span>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Listado -->
<div class="card">
    <h4 class="card-title"><?= icon('clipboard', 16) ?> Jornadas</h4>
    <?php if (empty($filas)): ?>
        <p class="card-subtitle">Sin marcaciones<?= $hayFiltro ? ' con este filtro' : ' todavía' ?>.</p>
    <?php else: ?>
        <div class="table-wrap">
            <table class="tbl">
                <thead>
                    <tr>
                        <th>Fecha</th><th>Empleado</th><th>Entrada</th><th>Salida</th>
                        <th>Horas</th><th>Estado</th><th>Notas</th><th></th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($filas as $a): ?>
                    <?php $tarde = $horaInicio && substr((string) $a['entrada'], 11, 5) > $horaInicio && $a['estado'] !== 'ANULADA'; ?>
                    <tr>
                        <td style="white-space:nowrap;"><?= esc($fmtFecha($a['fecha'])) ?></td>
                        <td>
                            <strong><?= esc(trim($a['nombres'] . ' ' . $a['apellidos'])) ?></strong>
                            <br><small class="card-subtitle"><?= esc(($a['carnet'] ?? '') . ($a['cargo'] ? ' · ' . $a['cargo'] : '')) ?></small>
                        </td>
                        <td style="white-space:nowrap;">
                            <?= esc($fmtHora($a['entrada'])) ?>
                            <?php if ($tarde): ?>
                                <br><small class="text-danger">tarde</small>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;"><?= esc($fmtHora($a['salida'])) ?></td>
                        <td><?= esc($fmtHoras($a)) ?></td>
                        <td><span class="badge <?= $stCls[$a['estado']] ?? '' ?>"><?= esc($stLbl[$a['estado']] ?? $a['estado']) ?></span></td>
                        <td>
                            <?php if (!empty($a['editada'])): ?>
                                <small class="card-subtitle">editada<?= $a['edit_usuario'] ? ' por ' . esc($a['edit_usuario']) : '' ?></small>
                            <?php endif; ?>
                            <?php if (!empty($a['observacion'])): ?>
                                <br><small class="card-subtitle"><?= esc($a['observacion']) ?></small>
                            <?php endif; ?>
                        </td>
                        <td style="white-space:nowrap;">
                            <?php if ($a['estado'] !== 'ANULADA'): ?>
                                <button type="button" class="btn btn-outline btn-sm" title="Corregir"
                                        data-marca-edit="<?= esc(json_encode([
                                            'url'    => base_url('asistencia/' . $a['id'] . '/editar'),
                                            'campos' => [
                                                'entrada'     => str_replace(' ', 'T', substr((string) $a['entrada'], 0, 16)),
                                                'salida'      => $a['salida'] ? str_replace(' ', 'T', substr((string) $a['salida'], 0, 16)) : '',
                                                'observacion' => $a['observacion'] ?? '',
                                            ],
                                        ]), 'attr') ?>">
                                    <?= icon('edit', 14) ?>
                                </button>
                                <form method="post" action="<?= base_url('asistencia/' . $a['id'] . '/anular') ?>"
                                      style="display:inline;"
                                      onsubmit="return confirm('¿Anular esta jornada? Ya no contará en los totales.');">
                                    <?= csrf_field() ?>
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
            <h4><?= icon('search', 17) ?> Filtrar asistencia</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="get" action="<?= base_url('asistencia') ?>">
            <div class="modal-body">
                <div class="form-group">
                    <label>Desde</label>
                    <input type="date" name="desde" value="<?= esc($filtros['desde']) ?>">
                </div>
                <div class="form-group">
                    <label>Hasta</label>
                    <input type="date" name="hasta" value="<?= esc($filtros['hasta']) ?>">
                </div>
                <div class="form-group">
                    <label>Empleado</label>
                    <select name="empleado">
                        <option value="">— Todos —</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= (int) $e['id'] ?>" <?= (int) $filtros['empleado_id'] === (int) $e['id'] ? 'selected' : '' ?>>
                                <?= esc(trim($e['nombres'] . ' ' . $e['apellidos'])) ?><?= $e['carnet'] ? ' (' . esc($e['carnet']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-full" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" id="f-abiertas" name="abiertas" value="1" <?= $filtros['abiertas'] ? 'checked' : '' ?> style="width:auto;">
                    <label for="f-abiertas" style="margin:0;">Solo jornadas abiertas</label>
                </div>
                <div class="form-group form-full" style="display:flex; align-items:center; gap:8px;">
                    <input type="checkbox" id="f-anuladas" name="anuladas" value="1" <?= $filtros['anuladas'] ? 'checked' : '' ?> style="width:auto;">
                    <label for="f-anuladas" style="margin:0;">Incluir anuladas</label>
                </div>
            </div>
            <div class="modal-foot">
                <a href="<?= base_url('asistencia') ?>" class="btn">Limpiar</a>
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Aplicar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: agregar marcación manual -->
<div class="modal-overlay" id="modal-marca" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4 data-add-title="Agregar marcación">Agregar marcación</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('asistencia') ?>" data-add-url="<?= base_url('asistencia') ?>">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Empleado *</label>
                    <select name="empleado_id" required>
                        <option value="">— Seleccioná —</option>
                        <?php foreach ($empleados as $e): ?>
                            <option value="<?= (int) $e['id'] ?>">
                                <?= esc(trim($e['nombres'] . ' ' . $e['apellidos'])) ?><?= $e['carnet'] ? ' (' . esc($e['carnet']) . ')' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Entrada *</label>
                    <input type="datetime-local" name="entrada" required>
                </div>
                <div class="form-group">
                    <label>Salida (dejá vacío si sigue adentro)</label>
                    <input type="datetime-local" name="salida">
                </div>
                <div class="form-group form-full">
                    <label>Observación</label>
                    <input type="text" name="observacion" maxlength="255" placeholder="Ej: olvidó marcar la salida">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: corregir marcación -->
<div class="modal-overlay" id="modal-editar" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4>Corregir marcación</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" id="form-editar" action="">
            <?= csrf_field() ?>
            <div class="modal-body">
                <div class="form-group">
                    <label>Entrada *</label>
                    <input type="datetime-local" name="entrada" id="e-entrada" required>
                </div>
                <div class="form-group">
                    <label>Salida (vacío = sigue adentro)</label>
                    <input type="datetime-local" name="salida" id="e-salida">
                </div>
                <div class="form-group form-full">
                    <label>Observación</label>
                    <input type="text" name="observacion" id="e-observacion" maxlength="255">
                </div>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary">Guardar corrección</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>
(function () {
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-marca-edit]');
        if (!btn) return;
        var d = JSON.parse(btn.getAttribute('data-marca-edit'));
        document.getElementById('form-editar').action = d.url;
        document.getElementById('e-entrada').value    = d.campos.entrada;
        document.getElementById('e-salida').value     = d.campos.salida;
        document.getElementById('e-observacion').value = d.campos.observacion;
        var m = document.getElementById('modal-editar');
        m.hidden = false;
        document.body.classList.add('modal-open');
    });
})();
</script>
<?= $this->endSection() ?>
