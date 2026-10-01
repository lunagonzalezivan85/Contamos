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

<?php
// Chips de estado — filtran por URL conservando el resto de los filtros
$chipUrl = static function (string $estado) use ($f): string {
    $q = array_filter([
        'q' => $f['q'], 'gestor' => $f['gestor'],
        'ruta' => $f['ruta'], 'desde' => $f['desde'], 'hasta' => $f['hasta'],
    ], static fn ($v) => $v !== '');
    // estado viaja siempre — vacío = "Todos"; sin param el server asume CREADA
    $q['estado'] = $estado;
    return base_url('credito/solicitudes') . '?' . http_build_query($q);
};
?>
<!-- Chips de estado — slide horizontal en móvil -->
<div class="sol-chips">
    <?php foreach ($estados as $e): ?>
        <a class="sol-chip <?= $f['estado'] === $e ? 'on' : '' ?>" href="<?= esc($chipUrl($e)) ?>"><?= esc($lblEstado[$e] ?? $e) ?></a>
    <?php endforeach; ?>
    <a class="sol-chip <?= $f['estado'] === '' ? 'on' : '' ?>" href="<?= esc($chipUrl('')) ?>">Todos</a>
</div>

<!-- Filtros — colapsables; abiertos solo si hay alguno aplicado -->
<?php
$filtrosOn = array_filter(
    [$f['q'], $f['gestor'], $f['ruta'], $f['desde'], $f['hasta']],
    static fn ($v) => $v !== ''
);
$colapsado = count($filtrosOn) === 0;
?>
<button type="button" class="sol-filter-toggle<?= $colapsado ? ' collapsed' : '' ?>"
        data-collapse="sol-filters" aria-expanded="<?= $colapsado ? 'false' : 'true' ?>">
    <?= icon('settings', 15) ?> Filtros
    <?php if (!$colapsado): ?>
        <span class="sol-filter-count"><?= count($filtrosOn) ?></span>
    <?php endif; ?>
    <span class="chevron"><?= icon('chevron-down', 14) ?></span>
</button>
<form method="get" action="<?= base_url('credito/solicitudes') ?>"
      class="sol-filters<?= $colapsado ? ' collapsed' : '' ?>" id="sol-filters">
    <input type="hidden" name="estado" value="<?= esc($f['estado']) ?>">
    <div class="sol-filters-row">
        <input type="text" name="q" placeholder="Cliente, cédula o código…" value="<?= esc($f['q']) ?>">

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

<?php if (empty($filas)): ?>
    <div class="sol-card sol-card-empty">Sin solicitudes con estos filtros.</div>
<?php endif; ?>
<div class="sol-cards">
<?php foreach ($filas ?? [] as $s):
    $nombreCli = mb_convert_case(trim(($s['nombres'] ?? '') . ' ' . ($s['apellidos'] ?? '')), MB_CASE_TITLE, 'UTF-8');
    $gestor    = trim(($s['gestor_nombres'] ?? '') . ' ' . ($s['gestor_apellidos'] ?? '')) ?: 'Sin asignar';
    $fecha     = !empty($s['created_at']) ? date('d/m/Y', strtotime($s['created_at'])) : '';
    $freq      = $lblFreq[$s['frecuencia']] ?? $s['frecuencia'];

    // Acciones rápidas: WhatsApp + Waze/Maps (GPS de la dirección o texto)
    $acts  = [];
    $telRaw = trim((string) ($s['telefono'] ?? '')) !== '' ? $s['telefono'] : ($s['contacto_tel'] ?? '');
    $tel   = preg_replace('/\D/', '', (string) $telRaw);
    if (strlen($tel) === 8) $tel = '505' . $tel;   // Nicaragua por defecto
    if ($tel !== '') {
        $acts[] = ['icono' => 'whatsapp', 'lbl' => 'WhatsApp', 'title' => 'Abrir WhatsApp',
                   'url' => 'https://wa.me/' . $tel];
    }
    $conGps = is_numeric($s['geo_lat'] ?? null) && is_numeric($s['geo_lng'] ?? null);
    $dirTxt = trim((string) ($s['direccion'] ?? ''));
    if ($conGps) {
        $urlWaze = 'https://waze.com/ul?ll=' . $s['geo_lat'] . '%2C' . $s['geo_lng'] . '&navigate=yes';
        $urlMaps = 'https://www.google.com/maps/dir/?api=1&destination=' . $s['geo_lat'] . ',' . $s['geo_lng'];
    } elseif ($dirTxt !== '') {
        $urlWaze = 'https://waze.com/ul?q=' . urlencode($dirTxt) . '&navigate=yes';
        $urlMaps = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($dirTxt);
    }
    if ($conGps || $dirTxt !== '') {
        $acts[] = ['icono' => 'waze',  'lbl' => 'Waze', 'title' => 'Ruta en Waze',        'url' => $urlWaze];
        $acts[] = ['icono' => 'gmaps', 'lbl' => 'Maps', 'title' => 'Ruta en Google Maps', 'url' => $urlMaps];
    }
?>
    <div class="sol-card">
        <!-- Toda la tarjeta lleva al detalle; las acciones quedan por encima -->
        <a class="sol-card-link" href="<?= base_url('credito/solicitudes/' . $s['id']) ?>" aria-label="Ver solicitud #<?= (int) $s['id'] ?>"></a>
        <span class="sol-card-ico"><?= icon('file-text', 18) ?></span>
        <div class="sol-card-main">
            <div class="sol-card-top">
                <span class="sol-card-nombre">#<?= (int) $s['id'] ?> · <?= esc($nombreCli) ?></span>
                <span class="sol-card-monto"><?= esc($mon) ?> <?= number_format((float) $s['monto'], 2) ?></span>
            </div>
            <div class="sol-card-mid">
                <?php if (!empty($s['codigo'])): ?><span class="sol-card-cod"><?= esc($s['codigo']) ?></span><?php endif; ?>
                <span class="badge sol-badge-<?= strtolower($s['estado']) ?>"><?= esc($lblEstado[$s['estado']] ?? $s['estado']) ?></span>
                <?php if ($acts): ?>
                <span class="row-acts sol-card-acts">
                    <?php foreach ($acts as $a): ?>
                        <a class="row-act" href="<?= esc($a['url']) ?>" target="_blank" rel="noopener"
                           title="<?= esc($a['title']) ?>">
                            <?= icon($a['icono'], 15) ?><span class="row-act-txt"><?= esc($a['lbl']) ?></span>
                        </a>
                    <?php endforeach; ?>
                </span>
                <?php endif; ?>
            </div>
            <div class="sol-card-meta">
                <span><?= icon('user', 12) ?> Gestor: <?= esc($gestor) ?></span>
                <span><?= icon('refresh-cw', 12) ?> <?= esc($freq) ?> · <?= esc($fecha) ?><?= !empty($s['ruta']) ? ' · Ruta ' . esc($s['ruta']) : '' ?></span>
            </div>
        </div>
        <span class="sol-card-chev"><?= icon('chevron-right', 18) ?></span>
    </div>
<?php endforeach; ?>
</div>

<?= $pager->links('default', 'cfsi') ?>

<?= $this->endSection() ?>
