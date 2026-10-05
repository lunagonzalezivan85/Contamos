<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.css">
<script src="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.js"></script>

<!-- Datos de paradas para el JS (JSON_HEX_TAG evita romper el script) -->
<script type="application/json" id="ruta-paradas"><?= json_encode($paradas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<div id="ruta-app" data-mon="<?= esc($m2) ?>" data-slug="<?= esc($slug) ?>"></div>

<!-- ===== Encabezado ===== -->
<div class="card portal-card">
    <h4 class="card-title"><?= icon('map-pin', 16) ?> Ruta de cobro · <?= esc(date('d/m/Y', strtotime($fecha ?? 'now'))) ?></h4>
    <p class="card-subtitle">
        <?= count($paradas) ?> parada(s) por cobrar ·
        <strong><?= esc($m2) ?> <?= number_format($total, 2) ?></strong>
        <?php if (count($paradas) > $con_gps): ?>
            · <span class="ruta-warn"><?= count($paradas) - $con_gps ?> sin GPS (no salen en el mapa)</span>
        <?php endif; ?>
    </p>
    <div class="ruta-actions">
        <input type="date" id="ruta-fecha" class="ruta-fecha"
               value="<?= esc($fecha ?? date('Y-m-d')) ?>"
               title="Ver la ruta de otro día (adelantar cobros)">
        <button type="button" class="btn btn-primary" id="ruta-gps-btn"><?= icon('map-pin', 15) ?> Mi ubicación</button>
        <button type="button" class="btn btn-outline" id="ruta-pick-btn"><?= icon('users', 15) ?> Elegir clientes</button>
    </div>
    <p class="geo-hint" id="ruta-gps">Activa tu ubicación para ordenar las paradas por distancia.</p>
</div>

<!-- ===== Mapa ===== -->
<div class="card portal-card">
    <div id="ruta-mapa" class="ruta-mapa"></div>
    <p class="geo-hint" id="ruta-resumen">Orden sugerido por cercanía — distancia lineal, sin tráfico.</p>
</div>

<!-- ===== Lista de paradas (ordenada por el JS según tu GPS) ===== -->
<div class="card portal-card">
    <h4 class="card-title"><?= icon('clipboard', 16) ?> Paradas del día</h4>
    <div id="ruta-lista" class="ruta-lista"></div>
</div>

<!-- ===== Modal: elegir clientes ===== -->
<div class="modal-overlay" id="modal-paradas" hidden>
    <div class="modal-box" style="max-width:460px;">
        <div class="modal-head">
            <h4><?= icon('users', 16) ?> Clientes en la ruta</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 16) ?></button>
        </div>
        <div class="modal-body">
            <div class="ruta-pick-tools">
                <button type="button" class="btn btn-outline btn-sm" id="ruta-todos">Todos</button>
                <button type="button" class="btn btn-outline btn-sm" id="ruta-ninguno">Ninguno</button>
            </div>
            <div id="ruta-pick-list" class="ruta-pick-list">
                <?php foreach ($paradas as $p): ?>
                    <label class="ruta-pick">
                        <input type="checkbox" value="<?= (int) $p['sol_id'] ?>" checked>
                        <span class="ruta-pick-body">
                            <strong><?= esc($p['nombre']) ?></strong>
                            <small><?= esc($p['codigo']) ?> · <?= esc($m2) ?> <?= number_format($p['monto'], 2) ?>
                                <?= $p['lat'] === null ? ' · sin GPS' : '' ?></small>
                        </span>
                    </label>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" data-close>Cancelar</button>
            <button type="button" class="btn btn-primary" id="ruta-aplicar"><?= icon('check', 14) ?> Aplicar</button>
        </div>
    </div>
</div>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= v_asset('js/portal/mapa.js') ?>"></script>
<?= $this->endSection() ?>
