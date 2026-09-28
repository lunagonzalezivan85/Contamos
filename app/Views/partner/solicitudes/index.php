<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<div class="list-head">
    <div>
        <h3 class="page-title">Solicitudes de crédito</h3>
        <p class="page-subtitle"><?= esc($pager->getTotal()) ?> solicitud(es)</p>
    </div>
    <?php if (!empty($puede_crear)): ?>
        <a href="<?= base_url('credito/solicitudes/nueva') ?>" class="btn btn-primary"><?= icon('file-plus', 15) ?> Nueva solicitud</a>
    <?php endif; ?>
</div>

<!-- Filtros -->
<form method="get" action="<?= base_url('credito/solicitudes') ?>" class="sol-filters">
    <div class="sol-filters-row">
        <input type="text" name="q" placeholder="Cliente, cédula o código…" value="<?= esc($f['q']) ?>">

        <select name="estado">
            <option value="">Todos los estados</option>
            <?php foreach ($estados as $e): ?>
                <option value="<?= esc($e) ?>" <?= $f['estado'] === $e ? 'selected' : '' ?>><?= esc($lblEstado[$e] ?? $e) ?></option>
            <?php endforeach; ?>
        </select>

        <select name="gestor">
            <option value="">Todos los gestores</option>
            <?php foreach ($gestores as $g): ?>
                <option value="<?= esc($g['id']) ?>" <?= (string) $f['gestor'] === (string) $g['id'] ? 'selected' : '' ?>>
                    <?= esc(trim($g['nombres'] . ' ' . $g['apellidos'])) ?>
                </option>
            <?php endforeach; ?>
        </select>

        <select name="ruta">
            <option value="">Todas las rutas</option>
            <?php foreach ($rutas as $r): ?>
                <option value="<?= esc($r) ?>" <?= $f['ruta'] === $r ? 'selected' : '' ?>><?= esc($r) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="sol-filters-row">
        <input type="date" name="desde" value="<?= esc($f['desde']) ?>" title="Desde">
        <input type="date" name="hasta" value="<?= esc($f['hasta']) ?>" title="Hasta">
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('search', 15) ?> Filtrar</button>
        <a href="<?= base_url('credito/solicitudes') ?>" class="btn btn-outline btn-sm">Limpiar</a>
    </div>
</form>

<?php
$items = array_map(function ($s) use ($lblEstado, $lblFreq, $mon) {
    $gestor = trim(($s['gestor_nombres'] ?? '') . ' ' . ($s['gestor_apellidos'] ?? '')) ?: 'Sin asignar';
    $fecha  = !empty($s['created_at']) ? date('d/m/Y', strtotime($s['created_at'])) : '';
    $freq   = $lblFreq[$s['frecuencia']] ?? $s['frecuencia'];
    $sub    = implode(' · ', array_filter([
        $s['codigo'] ?? '',
        'Gestor: ' . $gestor,
        !empty($s['ruta']) ? 'Ruta ' . $s['ruta'] : '',
        $freq,
        $fecha,
    ]));
    return [
        'icono'      => 'file-text',
        'titulo'     => '#' . $s['id'] . ' — ' . trim($s['nombres'] . ' ' . $s['apellidos']) . ' · ' . $mon . ' ' . number_format((float) $s['monto'], 0),
        'subtitulo'  => $sub,
        'meta'       => $lblEstado[$s['estado']] ?? $s['estado'],
        'meta_class' => 'badge sol-badge-' . strtolower($s['estado']),
        'url'        => '/credito/solicitudes/' . $s['id'],
    ];
}, $filas ?? []);
?>

<?= view('partials/list', ['items' => $items]) ?>

<?= $pager->links('default', 'cfsi') ?>

<?= $this->endSection() ?>
