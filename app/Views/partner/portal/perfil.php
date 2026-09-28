<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>

<?php
$nombre = trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? ''));
$ini    = mb_strtoupper(mb_substr(trim($persona['nombres'] ?? ''), 0, 1) . mb_substr(trim($persona['apellidos'] ?? ''), 0, 1));
?>

<!-- Encabezado con volver -->
<div class="app-header card portal-card">
    <a href="<?= base_url($slug . '/portal/panel') ?>" class="app-back"><?= icon('chevron-left', 20) ?></a>
    <div>
        <h3>Mi perfil</h3>
        <p class="app-rol"><?= esc($tenant['nombre']) ?></p>
    </div>
</div>

<!-- Hero del gestor -->
<div class="perfil-hero card portal-card">
    <span class="perfil-avatar"><?= esc($ini !== '' ? $ini : '?') ?></span>
    <h3 class="perfil-nombre"><?= esc($nombre) ?></h3>
    <p class="perfil-rol"><?= esc($empleado['cargo'] ?? 'Gestor') ?> · <?= esc($tenant['nombre']) ?></p>
    <div class="perfil-chips">
        <span class="perfil-chip"><?= icon('credit-card', 13) ?> Carnet <?= esc($empleado['carnet'] ?? '—') ?></span>
        <?php if (!empty($empleado['fecha_ingreso'])): ?>
            <span class="perfil-chip"><?= icon('clock', 13) ?> Desde <?= esc($empleado['fecha_ingreso']) ?></span>
        <?php endif; ?>
    </div>
</div>

<!-- Datos del gestor -->
<div class="card portal-card">
    <h4 class="card-title"><?= icon('user', 16) ?> Datos personales</h4>
    <div class="detail-grid">
        <div><label>Nombre</label><p><?= esc($nombre) ?></p></div>
        <div><label>Cédula</label><p><?= esc($persona['cedula'] ?? '—') ?></p></div>
        <div><label>Teléfono</label><p><?= esc($persona['telefono'] ?? '—') ?></p></div>
        <div><label>Correo</label><p><?= esc($persona['email'] ?? '—') ?></p></div>
        <div><label>Carnet</label><p><?= esc($empleado['carnet'] ?? '—') ?></p></div>
        <div><label>Cargo</label><p><?= esc($empleado['cargo'] ?? '—') ?></p></div>
        <div><label>Fecha de ingreso</label><p><?= esc($empleado['fecha_ingreso'] ?? '—') ?></p></div>
        <div class="form-full"><label>Dirección</label><p><?= esc($persona['direccion'] ?? '—') ?></p></div>
    </div>
</div>

<!-- Documentos -->
<div class="card portal-card">
    <h4 class="card-title"><?= icon('file-text', 16) ?> Mis documentos</h4>
    <?php if (empty($documentos)): ?>
        <p class="card-subtitle">No hay documentos registrados.</p>
    <?php else: ?>
        <div class="oui-list">
            <?php foreach ($documentos as $d): ?>
                <?php $href = !empty($d['archivo']) ? base_url('public/uploads/documentos/' . $d['archivo']) : '#'; ?>
                <a class="oui-list-item" href="<?= esc($href) ?>" target="_blank">
                    <span class="oui-icon"><?= icon('file-text', 18) ?></span>
                    <span class="oui-body">
                        <span class="oui-title"><?= esc($d['descripcion'] ?: $d['tipo'] ?: 'Documento') ?></span>
                        <span class="oui-sub"><?= esc($d['tipo'] ?? '') ?></span>
                    </span>
                    <span class="oui-meta"><?= icon('chevron-right', 16) ?></span>
                </a>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</div>

<?= $this->endSection() ?>
