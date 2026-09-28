<?= $this->extend('layouts/partner') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="<?= v_asset('css/chat.css') ?>">
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<!-- Hero estilo IA -->
<section class="hero">
    <div class="hero-icon"><?= icon('home', 34) ?></div>
    <h1 class="hero-title">Hola, <?= esc(explode(' ', session('nombre') ?? '')[0]) ?></h1>
    <p class="hero-subtitle">
        <?= esc(session('tenant_name')) ?> — ¿qué desea hacer hoy?
    </p>
</section>

<!-- Input estilo IA -->
<div class="ai-box">
    <div class="ai-input-wrap">
        <input type="text" id="ai-input" class="ai-input"
               placeholder="Pregunte algo o escriba \ para acciones…" autocomplete="off">
        <button type="button" class="ai-btn" id="ai-btn" title="Procesar">
            <?= icon('arrow-up', 18) ?>
        </button>
        <div class="ai-dropdown" id="ai-dropdown" hidden></div>
    </div>
</div>
<div class="ai-response" id="ai-response" hidden></div>

<?php
// Conocimiento del asistente: catálogo de reportes filtrado por permisos del usuario
$chatReportes = [];
foreach (config('Reportes')->catalogo as $key => $rep) {
    if (!in_array($rep['permiso'], session('permisos') ?? [], true)) continue;
    $chatReportes[] = [
        'nombre'      => $rep['nombre'],
        'descripcion' => $rep['descripcion'],
        'url'         => $rep['url'],
        'cat'         => $rep['cat'],
        'kw'          => $rep['kw'] ?? [],
    ];
}
?>
<!-- Chips-KPI accionables (una línea, scroll horizontal) -->
<div class="chips">
    <?php foreach ($chips as $chip): ?>
        <a class="chip chip-stat<?= !empty($chip['alerta']) ? ' chip-alert' : '' ?>"
           href="<?= esc($chip['url']) ?>">
            <span class="chip-icon"><?= icon($chip['icono'], 16) ?></span>
            <span class="chip-name"><?= esc($chip['nombre']) ?></span>
            <span class="chip-val"><?= esc($chip['valor']) ?></span>
        </a>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script>window.CHAT_REPORTES = <?= json_encode($chatReportes, JSON_UNESCAPED_UNICODE) ?>;</script>
<script src="<?= v_asset('js/chat-ai.js') ?>"></script>
<?= $this->endSection() ?>
