<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<div class="list-head">
    <div>
        <h3 class="page-title">Créditos activos</h3>
        <p class="page-subtitle"><?= esc($pager->getTotal()) ?> crédito(s) en cobro</p>
    </div>
</div>

<form method="get" action="<?= base_url('creditos') ?>" class="sol-filters">
    <div class="sol-filters-row">
        <input type="text" name="q" placeholder="Cliente, cédula o código de crédito." value="<?= esc($buscar) ?>">
        <button type="submit" class="btn btn-primary btn-sm"><?= icon('search', 15) ?> Filtrar</button>
        <a href="<?= base_url('creditos') ?>" class="btn btn-outline btn-sm">Limpiar</a>
    </div>
</form>

<?php
$items = array_map(function ($s) use ($m2) {
    $prox = $s['proxima'] ?? null;
    $sub  = implode(' · ', array_filter([
        $s['codigo_credito'] ?? 'sin código',
        'Gestor: ' . (trim(($s['gestor_nombres'] ?? '') . ' ' . ($s['gestor_apellidos'] ?? '')) ?: 'Sin asignar'),
        'Saldo ' . $m2 . ' ' . number_format((float) $s['saldo'], 0),
        $prox ? 'Próx. cuota ' . $prox['fecha_vence'] . ' - ' . $m2 . ' ' . number_format((float) $prox['cuota'] - (float) $prox['pagado'], 0) : 'Sin cuotas pendientes',
    ]));
    return [
        'icono'     => 'credit-card',
        'titulo'    => trim($s['nombres'] . ' ' . $s['apellidos']) . ' · ' . $m2 . ' ' . number_format((float) ($s['monto_aprobado'] ?: $s['monto']), 0),
        'subtitulo' => $sub,
        'meta'      => $prox && $prox['fecha_vence'] < date('Y-m-d') ? 'Vencida' : 'Activo',
        'meta_class' => 'badge ' . ($prox && $prox['fecha_vence'] < date('Y-m-d') ? 'sol-badge-rechazada' : 'sol-badge-activo'),
        'url'       => '/creditos/' . $s['id'],
    ];
}, $filas ?? []);
?>

<?= view('partials/list', ['items' => $items]) ?>

<?= $pager->links('default', 'cfsi') ?>

<?= $this->endSection() ?>
