<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>

<?php
// Portal de gestor — pantalla de inicio tipo app móvil
$nombre  = trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? ''));
$hora    = (int) date('H');
$saludo  = $hora < 12 ? 'Buenos días' : ($hora < 19 ? 'Buenas tardes' : 'Buenas noches');
$opciones = [
    ['nombre' => 'Nueva solicitud',    'desc' => 'Crédito para un cliente', 'icono' => 'file-plus',   'url' => $slug . '/portal/solicitud',    'clase' => 'tile-primary'],
    ['nombre' => 'Desembolso',         'desc' => 'Dinero por entregar',     'icono' => 'dollar-sign', 'url' => $slug . '/portal/desembolso',   'clase' => ''],
    ['nombre' => 'Cobros',             'desc' => 'Cuotas por cobrar y en mora','icono' => 'credit-card','url' => $slug . '/portal/cobros',    'clase' => ''],
    ['nombre' => 'Ruta de cobro',      'desc' => 'Clientes de hoy en el mapa','icono' => 'map-pin',   'url' => $slug . '/portal/mapa',       'clase' => ''],
    ['nombre' => 'Mi caja',            'desc' => 'Arqueo del día',          'icono' => 'clipboard',  'url' => $slug . '/portal/arqueo',       'clase' => ''],
    ['nombre' => 'Cartera de clientes','desc' => 'Todos tus clientes',      'icono' => 'briefcase',  'url' => $slug . '/portal/cartera',      'clase' => ''],
    ['nombre' => 'Actividad reciente', 'desc' => 'Solicitudes y movimientos','icono' => 'activity',   'url' => $slug . '/portal/actividad',    'clase' => ''],
    ['nombre' => 'Calculadora',        'desc' => 'Cuota de un préstamo',     'icono' => 'percent',    'url' => $slug . '/portal/calculadora', 'clase' => ''],
];
?>

<!-- Saludo + salir -->
<div class="app-hero card portal-card">
    <div>
        <p class="app-saludo"><?= esc($saludo) ?>,</p>
        <h2 class="app-nombre"><?= esc($nombre) ?></h2>
        <p class="app-rol">Gestor · <?= esc($tenant['nombre']) ?></p>
    </div>
    <a href="<?= base_url($slug . '/portal/salir') ?>" class="app-salir" title="Cerrar sesión">
        <?= icon('log-out', 18) ?>
    </a>
</div>

<!-- Menú principal tipo app -->
<div class="app-grid">
    <?php foreach ($opciones as $op): ?>
        <a href="<?= base_url($op['url']) ?>" class="app-tile <?= esc($op['clase']) ?>">
            <span class="app-tile-icon"><?= icon($op['icono'], 26) ?></span>
            <span class="app-tile-nombre"><?= esc($op['nombre']) ?></span>
            <span class="app-tile-desc"><?= esc($op['desc']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<!-- Mi perfil — botón que abre la vista -->
<a href="<?= base_url($slug . '/portal/perfil') ?>" class="app-perfil card portal-card">
    <span class="app-perfil-icon"><?= icon('user', 22) ?></span>
    <span class="app-perfil-body">
        <span class="app-perfil-nombre">Mi perfil</span>
        <span class="app-perfil-sub">Datos personales y documentos</span>
    </span>
    <span class="app-perfil-flecha"><?= icon('chevron-right', 18) ?></span>
</a>

<?= $this->endSection() ?>
