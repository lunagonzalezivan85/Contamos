<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<?php
$estado   = $s['estado'];
$badgeCls = 'badge sol-badge-' . strtolower($estado);
$cliente  = trim($s['nombres'] . ' ' . $s['apellidos']);
$creador  = trim(($s['creador_nombres'] ?? '') . ' ' . ($s['creador_apellidos'] ?? ''));
$gestor   = trim(($s['gestor_nombres'] ?? '') . ' ' . ($s['gestor_apellidos'] ?? ''));

// Cuota estimada (mismo cálculo que el wizard — sistema francés por período)
$freqPagos  = ['D' => 30, 'DI' => 0, 'S' => 4, 'Q' => 2, 'M' => 1];
$pagosXmes  = $s['frecuencia'] === 'DI' ? 4 * (int) ($s['dias_semana'] ?? 3) : ($freqPagos[$s['frecuencia']] ?? 1);
$iP         = ((float) ($s['tasa_mensual'] ?? 0) / 100) / max($pagosXmes, 0.01);
$n          = max(1, round(((int) ($s['plazo_meses'] ?? 0)) * $pagosXmes));
$cuota      = $iP > 0 ? $s['monto'] * $iP * (1 + $iP) ** $n / ((1 + $iP) ** $n - 1) : ($s['monto'] / $n);
$freqTxt    = ($lblFreq[$s['frecuencia']] ?? $s['frecuencia']) . ($s['frecuencia'] === 'DI' ? ' (' . (int) $s['dias_semana'] . ' días/sem)' : '');
?>

<?php
$accionesHero = [
    ['nombre' => 'Análisis crediticio', 'url' => '/credito/solicitudes/' . $s['id'] . '/analisis', 'icono' => 'bar-chart-2'],
    ['nombre' => 'Generar documentos', 'url' => '/credito/solicitudes/' . $s['id'] . '/documentos', 'icono' => 'printer'],
];
if ($editable ?? false) {
    $accionesHero[] = ['nombre' => 'Editar solicitud', 'url' => '/credito/solicitudes/' . $s['id'] . '/editar', 'icono' => 'edit'];
}
?>

<?= view('partials/detail_hero', [
    'titulo'    => 'Solicitud #' . $s['id'] . ' — ' . $cliente,
    'subtitulo' => 'Crédito → Solicitudes',
    'icono'     => 'file-text',
    'volver'    => '/credito/solicitudes',
    'acciones'  => $accionesHero,
]) ?>

<div class="sol-detail">

<!-- Estado + acciones -->
<div class="card">
    <div class="sol-estado-head">
        <label class="sol-lbl">Estado actual</label>
        <div class="sol-estado-badges">
            <span class="<?= $badgeCls ?> sol-badge-lg"><?= esc($lblEstado[$estado] ?? $estado) ?></span>
            <?php if (($s['origen'] ?? 'INTERNO') === 'WEB'): ?>
                <span class="badge sol-badge-web">Web</span>
            <?php endif; ?>
        </div>
    </div>
    <?php if ($estado === 'CONTACTO'): ?>
        <p class="sol-contacto-hint"><?= icon('phone', 14) ?> Llegó por la web — llama al cliente al <strong><?= esc($s['telefono'] ?: '—') ?></strong>, completa su expediente y pulsa «Iniciar gestión».</p>
    <?php endif; ?>
    <?php if ($estado === 'REVISION' && !empty($s['nota_revision'])): ?>
        <p class="sol-contacto-hint"><?= icon('message-square', 14) ?> Observación al gestor: <strong><?= esc($s['nota_revision']) ?></strong></p>
    <?php endif; ?>
    <div class="sol-acciones">
            <button type="button" class="btn btn-outline" data-modal="modal-historial">
                <?= icon('activity', 15) ?> Historial
            </button>
            <?php if ($estado === 'CONTACTO' && ($puede['CREADA'] ?? false)): ?>
                <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/estado') ?>">
                    <?= csrf_field() ?><input type="hidden" name="estado" value="CREADA">
                    <button class="btn btn-primary"><?= icon('phone', 15) ?> Iniciar gestión</button>
                </form>
            <?php endif; ?>
            <?php if ($estado === 'CREADA' && ($puede['REVISION'] ?? false)): ?>
                <button type="button" class="btn btn-primary" data-modal="modal-revision">
                    <?= icon('send', 15) ?> Enviar a revisión
                </button>
            <?php endif; ?>
            <?php if ($estado === 'REVISION' && ($puede['APROBADA'] ?? false)): ?>
                <?php if ($faltan === 0): ?>
                    <a href="<?= base_url('credito/solicitudes/' . $s['id'] . '/aprobar') ?>" class="btn btn-primary"><?= icon('check', 15) ?> Aprobar</a>
                <?php else: ?>
                    <button class="btn btn-primary" disabled title="El cliente tiene datos requeridos pendientes">
                        <?= icon('check', 15) ?> Aprobar
                    </button>
                <?php endif; ?>
            <?php endif; ?>
            <?php if ($estado === 'APROBADA' && ($puede['DESEMBOLSO'] ?? false)): ?>
                <a href="<?= base_url('credito/solicitudes/' . $s['id'] . '/desembolsar') ?>" class="btn btn-primary"><?= icon('dollar-sign', 15) ?> Desembolsar</a>
            <?php endif; ?>
            <?php if ($estado === 'DESEMBOLSO' && ($puede['DESEMBOLSO'] ?? false)): ?>
                <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/entregar') ?>"
                      onsubmit="return confirm('¿Confirmar que el dinero ya fue entregado al cliente? El crédito quedará activo.');">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary"><?= icon('check-circle', 15) ?> Marcar entregado</button>
                </form>
            <?php endif; ?>
            <?php if (!in_array($estado, ['DESEMBOLSO', 'ACTIVO', 'RECHAZADA'], true) && ($puede['RECHAZADA'] ?? false)): ?>
                <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/estado') ?>" onsubmit="return confirm('¿Rechazar esta solicitud?');">
                    <?= csrf_field() ?><input type="hidden" name="estado" value="RECHAZADA">
                    <button class="btn btn-danger"><?= icon('x', 15) ?> Rechazar</button>
                </form>
            <?php endif; ?>
    </div>
</div>

<!-- Cliente -->
<div class="card">
    <h4 class="card-title"><?= icon('user', 16) ?> Cliente</h4>
    <div class="detail-grid mt-3">
        <div><label>Nombre</label><p><?= esc($cliente) ?></p></div>
        <div><label>Código</label><p><?= esc($s['codigo'] ?? '—') ?></p></div>
        <div><label>Cédula</label><p><?= esc($s['cedula'] ?? '—') ?></p></div>
        <div><label>Teléfono</label><p><?= esc($s['telefono'] ?? '—') ?></p></div>
    </div>
    <p class="mt-3"><a href="<?= base_url('socios/clientes/' . $s['cliente_id']) ?>" class="btn btn-outline btn-sm"><?= icon('external-link', 14) ?> Ver ficha del cliente</a></p>
</div>

<!-- Checklist: expediente del cliente (colapsable — abierto si falta algo) -->
<details class="card sol-collapse"<?= $faltan > 0 ? ' open' : '' ?>>
    <summary>
        <span class="sol-collapse-title"><?= icon('clipboard', 16) ?> Expediente del cliente</span>
        <span class="sol-collapse-right">
            <?php if ($faltan > 0): ?>
                <span class="badge sol-badge-rechazada">Faltan <?= $faltan ?> requeridos</span>
            <?php else: ?>
                <span class="badge sol-badge-aprobada">Completo</span>
            <?php endif; ?>
            <span class="sol-collapse-ico"><?= icon('chevron-down', 18) ?></span>
        </span>
    </summary>
    <div class="sol-collapse-body">
        <ul class="sol-check">
            <?php foreach ($checklist as $item): ?>
                <li class="sol-check-item <?= $item['ok'] ? 'ok' : 'falta' ?>">
                    <span class="sol-check-ico"><?= icon($item['ok'] ? 'check' : 'x', 14) ?></span>
                    <span class="sol-check-lbl"><?= esc($item['label']) ?></span>
                    <?php if (!$item['req']): ?><span class="sol-check-tag">opcional</span><?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
        <?php if ($faltan > 0): ?>
            <p class="mt-3">
                <a href="<?= base_url('socios/clientes/' . $s['cliente_id']) ?>" class="btn btn-outline btn-sm">
                    <?= icon('edit', 14) ?> Completar expediente
                </a>
            </p>
        <?php endif; ?>
    </div>
</details>

<!-- Solicitud + Análisis financiero (2 columnas) -->
<div class="sol-split">

    <!-- Datos del préstamo -->
    <div class="card">
        <h4 class="card-title"><?= icon('percent', 16) ?> Préstamo</h4>
        <div class="detail-grid mt-3">
            <div><label>Monto</label><p><strong><?= esc($mon) ?> <?= number_format((float) $s['monto'], 2) ?></strong></p></div>
            <div><label>Tasa mensual</label><p><?= number_format((float) ($s['tasa_mensual'] ?? 0), 2) ?>%</p></div>
            <div><label>Plazo</label><p><?= esc($s['plazo_meses'] ?: '—') ?> <?= $s['plazo_meses'] ? 'meses' : '' ?></p></div>
            <div><label>Frecuencia</label><p><?= esc($freqTxt) ?></p></div>
            <div><label>Cuota estimada</label><p><strong><?= esc($mon) ?> <?= number_format($cuota, 2) ?></strong> × <?= $n ?> pagos</p></div>
            <div><label>Destino</label><p><?= esc($s['destino'] ?? '—') ?></p></div>
        </div>
        <?php if (!empty($s['monto_aprobado'])): ?>
            <div class="sol-aprobado">
                <label class="sol-lbl" style="margin-bottom:8px;">Préstamo aprobado</label>
                <div class="detail-grid">
                    <div><label>Monto</label><p><strong><?= esc($mon) ?> <?= number_format((float) $s['monto_aprobado'], 2) ?></strong></p></div>
                    <div><label>Tasa mensual</label><p><?= number_format((float) $s['tasa_aprobada'], 2) ?>%</p></div>
                    <div><label>Plazo</label><p><?= (int) $s['plazo_aprobado'] ?> meses</p></div>
                    <div><label>Frecuencia</label><p><?= esc($lblFreq[$s['frecuencia_aprobada']] ?? $s['frecuencia_aprobada']) ?></p></div>
                    <div><label>Primer pago</label><p><strong><?= esc($s['fecha_primer_pago'] ?? '—') ?></strong></p></div>
                    <div><label>Desembolso</label><p><?= esc($s['fecha_desembolso'] ?? '—') ?></p></div>
                    <div><label>Código de crédito</label><p><strong><?= esc($s['codigo_credito'] ?? '—') ?></strong></p></div>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Análisis financiero — se habilita cuando el expediente está completo -->
    <div class="card ana-card">
        <h4 class="card-title"><?= icon('bar-chart-2', 16) ?> Análisis financiero</h4>
        <?php if ($faltan > 0): ?>
            <p class="ana-empty">El expediente del cliente aún está incompleto. Complétalo para habilitar el análisis financiero.</p>
        <?php elseif (!$analisis): ?>
            <p class="ana-empty">Expediente completo. Calculá el análisis para evaluar la capacidad de pago del cliente.</p>
            <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/analisis/calcular') ?>" class="ana-actions">
                <?= csrf_field() ?>
                <button class="btn btn-primary"><?= icon('bar-chart-2', 15) ?> Calcular análisis</button>
            </form>
        <?php else: ?>
            <div class="detail-grid mt-3">
                <div><label>Ingresos declarados /mes</label><p><strong><?= esc($mon) ?> <?= number_format((float) $analisis['ingresos'], 2) ?></strong></p></div>
                <div><label>Cuota /mes</label><p><strong><?= esc($mon) ?> <?= number_format((float) $analisis['cuota_mes'], 2) ?></strong></p></div>
                <div><label>% del ingreso comprometido</label><p><?= $analisis['ratio'] !== null ? number_format((float) $analisis['ratio'] * 100, 1) . '%' : '—' ?></p></div>
                <div><label>Patrimonio (activos − pasivos)</label><p><?= esc($mon) ?> <?= number_format((float) $analisis['patrimonio'], 2) ?></p></div>
            </div>
            <p class="ana-nivel"><span class="badge ana-nivel-<?= strtolower($analisis['nivel'] ?? 'sin_datos') ?>"><?= esc($nivelLbl[$analisis['nivel']] ?? $analisis['nivel']) ?></span></p>
            <div class="ana-actions">
                <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/analisis/calcular') ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm"><?= icon('refresh-cw', 14) ?> Recalcular</button>
                </form>
                <a href="<?= base_url('credito/solicitudes/' . $s['id'] . '/analisis') ?>" class="btn btn-outline btn-sm"><?= icon('external-link', 14) ?> Ver análisis completo</a>
            </div>
            <p class="ana-fecha">Calculado el <?= esc($analisis['updated_at'] ?? $analisis['created_at'] ?? '—') ?></p>
        <?php endif; ?>
    </div>

</div>

<!-- Cartera / trazabilidad -->
<div class="card">
    <h4 class="card-title"><?= icon('briefcase', 16) ?> Cartera</h4>
    <div class="detail-grid mt-3">
        <div><label>Creada por</label><p><?= esc($creador ?: '—') ?></p></div>
        <div><label>Asignada a (gestor)</label><p><?= esc($gestor ?: 'Sin asignar') ?> <?= !empty($s['gestor_carnet']) ? '(' . esc($s['gestor_carnet']) . ')' : '' ?></p></div>
        <div><label>Ruta</label><p><?= esc($s['ruta'] ?? '—') ?></p></div>
        <div><label>Fecha</label><p><?= esc($s['created_at'] ?? '—') ?></p></div>
    </div>
</div>

</div><!-- /.sol-detail -->

<!-- Modal: historial de la solicitud (quién la creó, movió, aprobó…) -->
<div class="modal-overlay" id="modal-historial" hidden>
    <div class="modal-box" style="max-width:480px;">
        <div class="modal-head">
            <h4><?= icon('activity', 16) ?> Historial de la solicitud</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <div class="modal-body">
            <?php if (empty($historial)): ?>
                <p class="geo-hint">Sin movimientos registrados aún.</p>
            <?php else: ?>
                <ul class="tl">
                    <?php foreach ($historial as $h): ?>
                        <?php
                        $acc = $h['accion'] ?? 'ESTADO';
                        $txt = $acc === 'CREADO'  ? 'Creó la solicitud'
                             : ($acc === 'EDITADO' ? 'Editó los datos'
                             : ('Movió a ' . mb_strtolower($lblEstado[$h['estado']] ?? $h['estado'] ?? '')));
                        ?>
                        <li class="tl-item">
                            <div class="tl-dot tl-<?= strtolower($acc) ?>"></div>
                            <div class="tl-body">
                                <div class="tl-top">
                                    <strong><?= esc($h['actor'] ?? 'Sistema') ?></strong>
                                    <?php if (!empty($h['estado'])): ?>
                                        <span class="badge sol-badge-<?= strtolower($h['estado']) ?>"><?= esc($lblEstado[$h['estado']] ?? $h['estado']) ?></span>
                                    <?php endif; ?>
                                </div>
                                <p class="tl-txt"><?= esc($txt) ?><?= !empty($h['nota']) ? ' — ' . esc($h['nota']) : '' ?></p>
                                <small class="tl-fecha"><?= esc($h['created_at'] ?? '') ?></small>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Modal: observaciones al enviar a revisión (la ve el gestor en portal/app) -->
<div class="modal-overlay" id="modal-revision" hidden>
    <div class="modal-box" style="max-width:440px;">
        <div class="modal-head">
            <h4><?= icon('send', 16) ?> Enviar a revisión</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/estado') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="estado" value="REVISION">
            <div class="modal-body">
                <div class="form-group">
                    <label for="obs-revision">Observaciones para el gestor</label>
                    <textarea id="obs-revision" name="observaciones" rows="3" maxlength="255"
                              placeholder="Qué le falta o qué debe corregir (opcional)"></textarea>
                    <p class="geo-hint">El gestor la verá en su portal y en la app.</p>
                </div>
            </div>
            <div class="modal-foot">
                <button type="submit" class="btn btn-primary"><?= icon('send', 15) ?> Enviar</button>
            </div>
        </form>
    </div>
</div>

<?= $this->endSection() ?>
