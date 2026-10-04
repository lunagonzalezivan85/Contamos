<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<?php
$cliente   = trim(($s['nombres'] ?? '') . ' ' . ($s['apellidos'] ?? ''));
$freqAprob = ($lblFreq[$s['frecuencia_aprobada']] ?? $s['frecuencia_aprobada'] ?? '—')
           . ($s['frecuencia_aprobada'] === 'DI' ? ' (' . (int) ($s['dias_semana'] ?? 3) . ' días/sem)' : '');

// Plan del préstamo aprobado (soporta todos los tipos de cálculo)
$plan   = (new \App\Services\Partner\SolicitudService())->planPagos($s);
$cuotaA = $plan['cuota'];
$nA     = $plan['pagos'];
$deduc  = [
    'Comisión'           => (float) ($s['comision'] ?? 0),
    'Seguro'             => (float) ($s['seguro'] ?? 0),
    'Interés anticipado' => (float) ($plan['interes_anticipado'] ?? 0),
];
$deduc = array_filter($deduc);
$neto  = round((float) $s['monto_aprobado'] - array_sum($deduc), 2);
?>

<?= view('partials/detail_hero', [
    'titulo'    => 'Desembolso — Solicitud #' . $s['id'] . ' · ' . $cliente,
    'subtitulo' => 'Crédito → Solicitudes → Desembolso',
    'icono'     => 'dollar-sign',
    'volver'    => '/credito/solicitudes/' . $s['id'],
    'acciones'  => [],
]) ?>

<div class="sol-detail">

    <!-- Datos del cliente -->
    <div class="card">
        <h4 class="card-title"><?= icon('user', 16) ?> Datos del cliente</h4>
        <div class="detail-grid mt-3">
            <div><label>Nombre</label><p><strong><?= esc($cliente) ?></strong></p></div>
            <div><label>Código</label><p><?= esc($s['codigo'] ?? '—') ?></p></div>
            <div><label>Cédula</label><p><?= esc($s['cedula'] ?? '—') ?></p></div>
            <div><label>Teléfono</label><p><?= esc($s['telefono'] ?? '—') ?></p></div>
            <div><label>Gestor asignado</label><p><?= esc($s['gestor'] ?? 'Sin asignar') ?> <?= !empty($s['gestor_carnet']) ? '(' . esc($s['gestor_carnet']) . ')' : '' ?></p></div>
            <div><label>Ruta</label><p><?= esc($s['ruta'] ?? '—') ?></p></div>
        </div>
    </div>

    <form method="post" action="<?= base_url('credito/solicitudes/' . $s['id'] . '/desembolsar') ?>" onsubmit="return confirm('¿Programar el desembolso en esta fecha?');">
        <?= csrf_field() ?>
        <div class="sol-split">

            <!-- Préstamo aprobado (solo lectura) -->
            <div class="card">
                <h4 class="card-title"><?= icon('check-circle', 16) ?> Préstamo aprobado</h4>
                <div class="detail-grid mt-3">
                    <div><label>Monto aprobado</label><p><strong><?= esc($mon) ?> <?= number_format((float) $s['monto_aprobado'], 2) ?></strong></p></div>
                    <div><label>Tasa mensual</label><p><?= number_format((float) ($s['tasa_aprobada'] ?? 0), 2) ?>%</p></div>
                    <div><label>Plazo</label><p><?= (float) $s['plazo_aprobado'] ?> meses</p></div>
                    <div><label>Frecuencia</label><p><?= esc($freqAprob) ?></p></div>
                    <div><label>Cuota</label><p><?= esc($mon) ?> <?= number_format($cuotaA, 2) ?> × <?= $nA ?> pagos</p></div>
                    <div><label>Primer pago</label><p><strong><?= esc($s['fecha_primer_pago'] ?? '—') ?></strong></p></div>
                </div>
            </div>

            <!-- Desembolso -->
            <div class="card apr-aprobado">
                <h4 class="card-title"><?= icon('dollar-sign', 16) ?> Desembolso</h4>
                <p class="ana-empty">Indicá cuándo se entregará el dinero al cliente. El gestor lo verá en su portal bajo "Desembolso".</p>
                <div class="form-grid mt-3">
                    <div class="form-group">
                        <label for="fd_fecha">Fecha de desembolso *</label>
                        <input type="date" id="fd_fecha" name="fecha_desembolso" min="<?= date('Y-m-d') ?>" required
                               value="<?= esc(old('fecha_desembolso', date('Y-m-d'))) ?>">
                    </div>
                    <div class="form-group">
                        <label>Total a entregar al cliente</label>
                        <p style="font-size:22px; font-weight:700; color:var(--primary-dark); margin:4px 0 0;">
                            <?= esc($mon) ?> <?= number_format($neto, 2) ?>
                        </p>
                        <?php foreach ($deduc as $lbl => $v): ?>
                            <p style="font-size:12px; color:var(--text-muted); margin:2px 0 0;">
                                − <?= esc($lbl) ?>: <?= esc($mon) ?> <?= number_format($v, 2) ?> (se descuenta del monto aprobado)
                            </p>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div>

        <div class="card apr-foot">
            <button type="submit" class="btn btn-primary"><?= icon('dollar-sign', 16) ?> Confirmar desembolso</button>
            <a href="<?= base_url('credito/solicitudes/' . $s['id']) ?>" class="btn btn-outline">Cancelar</a>
        </div>
    </form>

</div>

<?= $this->endSection() ?>
