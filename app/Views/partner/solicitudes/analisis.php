<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<?php
$m        = $metricas;
$cliente  = trim(($s['nombres'] ?? '') . ' ' . ($s['apellidos'] ?? ''));
$badgeCls = 'badge sol-badge-' . strtolower($s['estado']);
$sect     = [
    'negocio'     => ['titulo' => 'Negocio / Actividad',        'icono' => 'briefcase'],
    'ingreso'     => ['titulo' => 'Ingresos declarados',         'icono' => 'trending-up'],
    'activo'      => ['titulo' => 'Activos',                     'icono' => 'archive'],
    'pasivo'      => ['titulo' => 'Pasivos / Deudas',            'icono' => 'trending-down'],
    'referencia'  => ['titulo' => 'Referencias personales',      'icono' => 'users'],
    'direccion'   => ['titulo' => 'Direcciones',                 'icono' => 'map-pin'],
    'contacto'    => ['titulo' => 'Contactos',                   'icono' => 'phone'],
    'documento'   => ['titulo' => 'Documentos',                  'icono' => 'file-text'],
];
?>

<?= view('partials/detail_hero', [
    'titulo'    => 'Análisis crediticio — ' . $cliente,
    'subtitulo' => 'Solicitud #' . $s['id'] . ' · Crédito → Solicitudes',
    'icono'     => 'bar-chart-2',
    'volver'    => '/credito/solicitudes/' . $s['id'],
    'acciones'  => [],
]) ?>

<div class="sol-detail">

    <!-- Resumen: solicitud + capacidad -->
    <div class="sol-split">
        <div class="card">
            <div class="card-head">
                <h4 class="card-title"><?= icon('percent', 16) ?> Crédito solicitado</h4>
                <span class="<?= $badgeCls ?>"><?= esc($lblEstado[$s['estado']] ?? $s['estado']) ?></span>
            </div>
            <div class="detail-grid mt-3">
                <div><label>Monto</label><p><strong><?= esc($mon) ?> <?= number_format((float) $s['monto'], 2) ?></strong></p></div>
                <div><label>Tasa mensual</label><p><?= number_format((float) ($s['tasa_mensual'] ?? 0), 2) ?>%</p></div>
                <div><label>Plazo</label><p><?= esc($s['plazo_meses'] ?: '—') ?> meses</p></div>
                <div><label>Frecuencia</label><p><?= esc($lblFreq[$s['frecuencia']] ?? $s['frecuencia']) ?></p></div>
                <div><label>Núm. pagos</label><p><?= (int) $m['pagos'] ?></p></div>
                <div><label>Destino</label><p><?= esc($s['destino'] ?? '—') ?></p></div>
            </div>
        </div>

        <div class="card">
            <div class="card-head">
                <h4 class="card-title"><?= icon('activity', 16) ?> Capacidad de pago</h4>
                <?php if (!empty($analisis['nivel'])): ?>
                    <span class="badge ana-nivel-<?= strtolower($analisis['nivel']) ?>"><?= esc($nivelLbl[$analisis['nivel']] ?? $analisis['nivel']) ?></span>
                <?php endif; ?>
            </div>
            <div class="detail-grid mt-3">
                <div><label>Ingresos declarados /mes</label><p><strong><?= esc($mon) ?> <?= number_format($m['ingresos'], 2) ?></strong></p></div>
                <div><label>Cuota estimada /período</label><p><?= esc($mon) ?> <?= number_format($m['cuota'], 2) ?></p></div>
                <div><label>Cuota equivalente /mes</label><p><strong><?= esc($mon) ?> <?= number_format($m['cuota_mes'], 2) ?></strong></p></div>
                <div><label>% del ingreso comprometido</label><p><?= $m['ratio'] !== null ? number_format($m['ratio'] * 100, 1) . '%' : '—' ?></p></div>
            </div>
            <?php if ($m['ratio'] === null): ?>
                <p class="ana-empty">Sin ingresos declarados — no se puede evaluar la capacidad de pago.</p>
            <?php elseif ($m['ratio'] <= 0.30): ?>
                <p class="ana-msg ok"><?= icon('check', 14) ?> La cuota compromete menos del 30% de sus ingresos — capacidad cómoda.</p>
            <?php elseif ($m['ratio'] <= 0.50): ?>
                <p class="ana-msg warn"><?= icon('alert-circle', 14) ?> La cuota compromete entre el 30% y el 50% de sus ingresos — capacidad ajustada.</p>
            <?php else: ?>
                <p class="ana-msg bad"><?= icon('alert-circle', 14) ?> La cuota compromete más del 50% de sus ingresos — riesgo alto.</p>
            <?php endif; ?>
            <?php if (!empty($puedeCalc)): ?>
                <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/analisis/calcular') ?>" class="ana-actions">
                    <?= csrf_field() ?>
                    <button class="btn btn-outline btn-sm"><?= icon('refresh-cw', 14) ?> <?= $analisis ? 'Recalcular' : 'Calcular análisis' ?></button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <!-- Balance -->
    <div class="card">
        <h4 class="card-title"><?= icon('scale', 16) ?> Balance del cliente</h4>
        <div class="detail-grid mt-3">
            <div><label>Total activos</label><p><?= esc($mon) ?> <?= number_format($m['activos'], 2) ?></p></div>
            <div><label>Total pasivos</label><p><?= esc($mon) ?> <?= number_format($m['pasivos'], 2) ?></p></div>
            <div><label>Patrimonio</label><p><strong><?= esc($mon) ?> <?= number_format($m['patrimonio'], 2) ?></strong></p></div>
            <div><label>Monto vs patrimonio</label><p><?= $m['patrimonio'] > 0 ? number_format((float) $s['monto'] / $m['patrimonio'] * 100, 1) . '%' : '—' ?></p></div>
        </div>
    </div>

    <!-- Datos del cliente -->
    <div class="card">
        <h4 class="card-title"><?= icon('user', 16) ?> Cliente</h4>
        <div class="detail-grid mt-3">
            <div><label>Nombre</label><p><?= esc($cliente) ?></p></div>
            <div><label>Código</label><p><?= esc($s['codigo'] ?? '—') ?></p></div>
            <div><label>Cédula</label><p><?= esc($s['cedula'] ?? '—') ?></p></div>
            <div><label>Teléfono</label><p><?= esc($s['telefono'] ?? '—') ?></p></div>
            <div><label>Correo</label><p><?= esc($s['email'] ?? '—') ?></p></div>
        </div>
        <p class="mt-3"><a href="<?= base_url('socios/clientes/' . $s['cliente_id']) ?>" class="btn btn-outline btn-sm"><?= icon('external-link', 14) ?> Ver ficha del cliente</a></p>
    </div>

    <!-- Expediente detallado -->
    <div class="ana-grid">
        <?php foreach ($sect as $tipo => $cfg): ?>
            <div class="card">
                <h4 class="card-title"><?= icon($cfg['icono'], 16) ?> <?= esc($cfg['titulo']) ?></h4>
                <?php $rows = $secciones[$tipo] ?? []; ?>
                <?php if (!$rows): ?>
                    <p class="ana-empty">Sin registros.</p>
                <?php else: ?>
                    <ul class="ana-list">
                        <?php foreach ($rows as $r): ?>
                            <li class="ana-item">
                                <?php if ($tipo === 'negocio'): ?>
                                    <strong><?= esc($r['nombre'] ?? '—') ?></strong>
                                    <span><?= esc($r['actividad'] ?? '') ?><?= !empty($r['sector_economico']) ? ' · ' . esc($r['sector_economico']) : '' ?></span>
                                    <span class="ana-muted"><?= esc($r['direccion'] ?? '') ?><?= !empty($r['tiempo']) ? ' · ' . esc($r['tiempo']) : '' ?></span>
                                    <span class="ana-muted">Venta prom./día: <?= esc($mon) ?> <?= number_format((float) ($r['promedio_venta_dia'] ?? 0), 2) ?> · <?= (int) ($r['dias_venta'] ?? 0) ?> días/sem</span>
                                <?php elseif ($tipo === 'ingreso'): ?>
                                    <strong><?= esc($r['fuente'] ?? '—') ?></strong>
                                    <span><?= esc($mon) ?> <?= number_format((float) ($r['monto'] ?? 0), 2) ?>/mes</span>
                                <?php elseif ($tipo === 'activo'): ?>
                                    <strong><?= esc($r['descripcion'] ?? '—') ?></strong>
                                    <span><?= esc($mon) ?> <?= number_format((float) ($r['valor'] ?? 0), 2) ?></span>
                                <?php elseif ($tipo === 'pasivo'): ?>
                                    <strong><?= esc($r['descripcion'] ?? '—') ?></strong>
                                    <span><?= esc($r['acreedor'] ?? '') ?> · <?= esc($mon) ?> <?= number_format((float) ($r['monto'] ?? 0), 2) ?></span>
                                <?php elseif ($tipo === 'referencia'): ?>
                                    <strong><?= esc($r['nombre'] ?? '—') ?></strong>
                                    <span><?= esc($r['parentesco'] ?? '') ?> · <?= esc($r['telefono'] ?? '') ?></span>
                                    <span class="ana-muted"><?= esc($r['direccion'] ?? '') ?></span>
                                <?php elseif ($tipo === 'direccion'): ?>
                                    <strong><?= esc($r['tipo'] ?? '—') ?></strong>
                                    <span><?= esc(trim(($r['departamento'] ?? '') . ', ' . ($r['ciudad'] ?? '') . ' — ' . ($r['barrio'] ?? ''), ', — ')) ?></span>
                                    <span class="ana-muted"><?= esc($r['detalle'] ?? '') ?></span>
                                <?php elseif ($tipo === 'contacto'): ?>
                                    <strong><?= esc($r['tipo'] ?? '—') ?></strong>
                                    <span><?= esc($r['valor'] ?? '—') ?></span>
                                <?php else: /* documento */ ?>
                                    <strong><?= esc($r['tipo'] ?? '—') ?></strong>
                                    <span><?= esc($r['descripcion'] ?? '') ?></span>
                                <?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>

</div>

<?= $this->endSection() ?>
