<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? 'C$'; ?>
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.css">
<script src="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.js"></script>

<!-- Datos de paradas para el JS (JSON_HEX_TAG evita romper el script) -->
<script type="application/json" id="ruta-paradas"><?= json_encode($paradas, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
<div id="ruta-app" data-mon="<?= esc($m2) ?>" data-det="<?= base_url('credito/solicitudes') ?>"></div>

<?= view('partials/detail_hero', [
    'titulo'    => 'Desembolsos por entregar',
    'subtitulo' => 'Crédito → Desembolsar — ruta de entrega de efectivo',
    'icono'     => 'map-pin',
    'volver'    => '/credito/solicitudes?estado=DESEMBOLSO',
    'acciones'  => [],
]) ?>

<!-- ===== Resumen + controles ===== -->
<div class="card">
    <h4 class="card-title"><?= icon('dollar-sign', 16) ?> <?= count($paradas) ?> entrega(s) pendiente(s) · <?= esc($m2) ?> <?= number_format($total, 2) ?></h4>
    <?php if (empty($paradas)): ?>
        <p class="card-subtitle">No hay solicitudes en estado «Por desembolsar».</p>
    <?php else: ?>
        <p class="card-subtitle">
            Dinero aprobado por entregar al cliente.
            <?php if (count($paradas) > $con_gps): ?>
                <span class="ruta-warn"><?= count($paradas) - $con_gps ?> sin GPS (no salen en el mapa)</span>
            <?php endif; ?>
        </p>
        <div class="ruta-actions">
            <button type="button" class="btn btn-primary" id="ruta-mapa-btn"><?= icon('map-pin', 15) ?> Ver mapa</button>
            <button type="button" class="btn btn-outline" id="ruta-gps-btn"><?= icon('target', 15) ?> Mi ubicación</button>
            <button type="button" class="btn btn-outline" id="ruta-orden-btn"><?= icon('list', 15) ?> Orden sugerido</button>
            <button type="button" class="btn btn-outline" id="ruta-pick-btn"><?= icon('users', 15) ?> Elegir entregas</button>
            <?php if (count($gestores) > 1): ?>
                <select id="ruta-gestor" class="ruta-gestor" title="Filtrar por gestor">
                    <option value="">Todos los gestores</option>
                    <?php foreach ($gestores as $gid => $gnom): ?>
                        <option value="<?= (int) $gid ?>"><?= esc($gnom) ?></option>
                    <?php endforeach; ?>
                </select>
            <?php endif; ?>
        </div>
        <p class="geo-hint" id="ruta-gps">Activá tu ubicación o «Orden sugerido» para ordenar las paradas por distancia.</p>
    <?php endif; ?>
</div>

<?php if (!empty($paradas)): ?>
<!-- ===== Mapa (se muestra con el botón "Ver mapa") ===== -->
<div class="card" id="ruta-mapa-card" hidden>
    <div id="ruta-mapa" class="ruta-mapa"></div>
    <p class="geo-hint" id="ruta-resumen">Orden sugerido por cercanía — distancia lineal, sin tráfico.</p>
</div>

<!-- ===== Orden de entrega ===== -->
<div class="card">
    <h4 class="card-title"><?= icon('clipboard', 16) ?> Orden de entrega</h4>
    <p class="card-subtitle">Reorganizá con las flechas; el mapa y las distancias se recalculan solos.</p>
    <div id="ruta-lista" class="ruta-lista"></div>
</div>

<!-- ===== Modal: elegir entregas ===== -->
<div class="modal-overlay" id="modal-paradas" hidden>
    <div class="modal-box ruta-modal-box">
        <div class="modal-head">
            <h4><?= icon('users', 16) ?> Entregas en la ruta</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 16) ?></button>
        </div>
        <div class="modal-body">
            <div class="ruta-pick-tools">
                <button type="button" class="btn btn-outline btn-sm" id="ruta-todos">Todas</button>
                <button type="button" class="btn btn-outline btn-sm" id="ruta-ninguno">Ninguna</button>
            </div>
            <div class="ruta-pick-list">
                <?php foreach ($paradas as $p): ?>
                    <label class="ruta-pick">
                        <input type="checkbox" value="<?= (int) $p['sol_id'] ?>" checked>
                        <span class="ruta-pick-body">
                            <strong><?= esc($p['nombre']) ?></strong>
                            <small><?= esc($p['codigo']) ?> · <?= esc($m2) ?> <?= number_format($p['monto'], 2) ?>
                                · <?= esc($p['gestor']) ?><?= $p['lat'] === null ? ' · sin GPS' : '' ?></small>
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
<?php endif; ?>

<?= $this->endSection() ?>

<?= $this->section('scripts') ?>
<script src="<?= v_asset('js/desembolsos.js') ?>"></script>
<?= $this->endSection() ?>
