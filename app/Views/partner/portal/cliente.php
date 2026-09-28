<?= $this->extend('layouts/portal') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.css">
<script src="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.js"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<?php
// Configuración de pestañas: columnas + campos del form de cada sección
$tabs = [
    'datos'       => ['nombre' => 'Datos',        'icono' => 'user'],
    'solicitudes' => ['nombre' => 'Créditos',     'icono' => 'file-text'],
    'direccion'   => ['nombre' => 'Direcciones',  'icono' => 'map-pin',
        'cols' => ['tipo' => 'Tipo', 'departamento' => 'Departamento', 'ciudad' => 'Ciudad', 'barrio' => 'Barrio', 'detalle' => 'Detalle']],
    'contacto'    => ['nombre' => 'Contactos',    'icono' => 'phone',
        'cols' => ['tipo' => 'Tipo', 'valor' => 'Valor']],
    'referencia'  => ['nombre' => 'Referencias',  'icono' => 'users',
        'cols' => ['nombre' => 'Nombre', 'parentesco' => 'Parentesco', 'telefono' => 'Teléfono', 'direccion' => 'Dirección']],
    'negocio'     => ['nombre' => 'Negocios',     'icono' => 'briefcase',
        'cols' => ['nombre' => 'Nombre', 'actividad' => 'Actividad', 'sector_economico' => 'Sector', 'direccion' => 'Dirección', 'tiempo' => 'Tiempo', 'dias_venta' => 'Días de venta', 'promedio_venta_dia' => 'Venta/día']],
    'activo'      => ['nombre' => 'Activos',      'icono' => 'trending-up',
        'cols' => ['descripcion' => 'Descripción', 'valor' => 'Valor']],
    'pasivo'      => ['nombre' => 'Pasivos',      'icono' => 'trending-down',
        'cols' => ['descripcion' => 'Descripción', 'acreedor' => 'Acreedor', 'monto' => 'Monto']],
    'ingreso'     => ['nombre' => 'Ingresos',     'icono' => 'dollar-sign',
        'cols' => ['fuente' => 'Fuente', 'monto' => 'Monto']],
    'documento'   => ['nombre' => 'Documentos',   'icono' => 'file-text',
        'cols' => ['tipo' => 'Tipo', 'descripcion' => 'Descripción', 'archivo' => 'Archivo']],
];
$fieldSelects = [
    'contacto.tipo'            => ['telefono' => 'Teléfono', 'correo' => 'Correo', 'whatsapp' => 'WhatsApp', 'otro' => 'Otro'],
    'documento.tipo'           => ['cedula' => 'Cédula', 'pasaporte' => 'Pasaporte', 'contrato' => 'Contrato', 'cartas' => 'Cartas', 'otro' => 'Otro'],
    'negocio.sector_economico' => ['comercio' => 'Comercio', 'servicios' => 'Servicios', 'alimentos' => 'Alimentos y bebidas', 'agricultura' => 'Agricultura', 'artesanias' => 'Artesanías', 'transporte' => 'Transporte', 'otro' => 'Otro'],
];
$fieldNumber = ['negocio.promedio_venta_dia', 'activo.valor', 'pasivo.monto', 'ingreso.monto'];
$cliId  = (int) $cliente['id'];
$base   = $slug . '/portal/cliente/' . $cliId;
$nombre = trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? ''));

// ---- Mapas: con GPS usa coordenadas, si no, búsqueda por texto ----
$dirTexto = fn(array $d): string => implode(', ', array_filter([
    $d['detalle'] ?? '', $d['barrio'] ?? '', $d['ciudad'] ?? '', $d['departamento'] ?? '',
]));
$tieneGps = fn(array $d): bool => is_numeric($d['latitud'] ?? null) && is_numeric($d['longitud'] ?? null);
$urlWaze = function (array $d) use ($tieneGps, $dirTexto): string {
    return $tieneGps($d)
        ? 'https://waze.com/ul?ll=' . $d['latitud'] . '%2C' . $d['longitud'] . '&navigate=yes'
        : 'https://waze.com/ul?q=' . urlencode($dirTexto($d)) . '&navigate=yes';
};
$urlMaps = function (array $d) use ($tieneGps, $dirTexto): string {
    return $tieneGps($d)
        ? 'https://www.google.com/maps/dir/?api=1&destination=' . $d['latitud'] . ',' . $d['longitud']
        : 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($dirTexto($d));
};
?>

<!-- Encabezado con volver -->
<div class="app-header card portal-card">
    <a href="<?= base_url($slug . '/portal/cartera') ?>" class="app-back"><?= icon('chevron-left', 20) ?></a>
    <div>
        <h3><?= esc($nombre) ?></h3>
        <p class="app-rol"><?= esc(($cliente['codigo'] ?? 'Cliente') . ' · ' . $cliente['estado']) ?></p>
    </div>
</div>

<!-- Pestañas (scroll horizontal con flechas) -->
<div class="tabs-wrap">
    <button type="button" class="tabs-arrow" id="tabs-prev" aria-label="Ver pestañas anteriores" hidden><?= icon('chevron-left', 16) ?></button>
    <div class="tabs tabs-scroll" id="persona-tabs">
        <?php foreach ($tabs as $key => $t): ?>
            <button type="button" class="tab-btn <?= $tabActiva === $key ? 'on' : '' ?>" data-tab="<?= esc($key) ?>">
                <?= icon($t['icono'], 15) ?> <?= esc($t['nombre']) ?>
            </button>
        <?php endforeach; ?>
    </div>
    <button type="button" class="tabs-arrow" id="tabs-next" aria-label="Ver más pestañas" hidden><?= icon('chevron-right', 16) ?></button>
</div>

<!-- Paneles -->
<?php foreach ($tabs as $key => $t): ?>
<div class="tab-panel <?= $tabActiva === $key ? 'on' : '' ?>" data-panel="<?= esc($key) ?>">

    <?php if ($key === 'datos'): ?>
        <!-- Datos del cliente + formulario para completar -->
        <div class="card portal-card">
            <div class="detail-grid">
                <div><label>Código</label><p><?= esc($cliente['codigo'] ?? '-') ?></p></div>
                <div><label>Estado</label><p><?= esc($cliente['estado'] ?? '-') ?></p></div>
                <div><label>Cédula</label><p><?= esc($persona['cedula'] ?? '-') ?></p></div>
                <div><label>Teléfono</label><p><?= esc($persona['telefono'] ?? '-') ?></p></div>
                <div><label>Género</label><p><?= esc(($persona['genero'] ?? '') === 'F' ? 'Mujer' : (($persona['genero'] ?? '') === 'M' ? 'Hombre' : '-')) ?></p></div>
                <div><label>Fecha de nacimiento</label><p><?= esc($persona['fecha_nac'] ?? '-') ?></p></div>
                <div class="form-full"><label>Correo</label><p><?= esc($persona['email'] ?? '-') ?></p></div>
                <div class="form-full"><label>Dirección</label><p><?= esc($persona['direccion'] ?? '-') ?></p></div>
            </div>
            <?php if (!empty($persona['direccion'])): ?>
                <div class="map-links">
                    <a class="map-btn map-waze" href="<?= $urlWaze(['detalle' => $persona['direccion']]) ?>" target="_blank" rel="noopener"><?= icon('waze', 14) ?> Waze</a>
                    <a class="map-btn map-gmaps" href="<?= $urlMaps(['detalle' => $persona['direccion']]) ?>" target="_blank" rel="noopener"><?= icon('gmaps', 14) ?> Google Maps</a>
                </div>
            <?php endif; ?>
        </div>

        <div class="card portal-card">
            <h4 class="card-title"><?= icon('edit', 16) ?> Completar datos</h4>
            <form action="<?= base_url($base) ?>" method="post" class="form mt-4">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="c-nombres">Nombres</label>
                    <input type="text" id="c-nombres" name="nombres" maxlength="100" required
                           value="<?= esc(old('nombres', $persona['nombres'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label for="c-apellidos">Apellidos</label>
                    <input type="text" id="c-apellidos" name="apellidos" maxlength="100" required
                           value="<?= esc(old('apellidos', $persona['apellidos'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label for="c-cedula">Cédula</label>
                    <input type="text" id="c-cedula" name="cedula" maxlength="30"
                           value="<?= esc(old('cedula', $persona['cedula'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label for="c-telefono">Teléfono</label>
                    <input type="text" id="c-telefono" name="telefono" maxlength="50"
                           value="<?= esc(old('telefono', $persona['telefono'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label for="c-genero">Género</label>
                    <select id="c-genero" name="genero">
                        <option value="">-</option>
                        <option value="M" <?= ($persona['genero'] ?? '') === 'M' ? 'selected' : '' ?>>Hombre</option>
                        <option value="F" <?= ($persona['genero'] ?? '') === 'F' ? 'selected' : '' ?>>Mujer</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="c-fnac">Fecha de nacimiento</label>
                    <input type="date" id="c-fnac" name="fecha_nac" value="<?= esc($persona['fecha_nac'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="c-email">Correo</label>
                    <input type="email" id="c-email" name="email" maxlength="150"
                           value="<?= esc(old('email', $persona['email'] ?? '')) ?>">
                </div>
                <div class="form-group">
                    <label for="c-direccion">Dirección principal</label>
                    <input type="text" id="c-direccion" name="direccion" maxlength="255"
                           value="<?= esc(old('direccion', $persona['direccion'] ?? '')) ?>">
                </div>
                <button type="submit" class="btn btn-primary btn-block"><?= icon('check', 15) ?> Guardar datos</button>
            </form>
        </div>

    <?php elseif ($key === 'solicitudes'): ?>
        <!-- Solicitudes recientes del cliente + estado -->
        <div class="card portal-card">
            <div class="card-head">
                <h4 class="card-title"><?= icon('file-text', 16) ?> Solicitudes recientes</h4>
                <?php if (!empty($cobros)): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-modal="modal-pago">
                        <?= icon('dollar-sign', 14) ?> Registrar pago
                    </button>
                <?php endif; ?>
            </div>
            <?php if (empty($solicitudes)): ?>
                <p class="card-subtitle">Este cliente no tiene solicitudes.</p>
            <?php else: ?>
                <?php $cobrosPorSol = array_column($cobros ?? [], null, 'id'); ?>
                <div class="oui-list mt-2">
                    <?php foreach ($solicitudes as $s): ?>
                        <?php $cobroSol = $cobrosPorSol[$s['id']] ?? null; ?>
                        <div class="oui-list-item">
                            <span class="oui-icon"><?= icon('file-text', 18) ?></span>
                            <span class="oui-body">
                                <span class="oui-title"><?= esc($m2) ?> <?= number_format((float) ($s['monto_aprobado'] ?: $s['monto']), 0) ?><?= !empty($s['codigo_credito']) ? ' · ' . esc($s['codigo_credito']) : '' ?></span>
                                <span class="oui-sub">
                                    <?= esc($s['destino'] ?? 'Solicitud de crédito') ?> · <?= esc(substr((string) $s['created_at'], 0, 10)) ?>
                                    <?php if ($cobroSol && $cobroSol['en_revision'] > 0): ?>
                                        · <span class="badge st-warn"><?= $cobroSol['en_revision'] ?> pago(s) en revisión</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                            <span class="badge <?= in_array($s['estado'], ['ACTIVO', 'DESEMBOLSO'], true) ? 'badge-soft' : '' ?>"><?= esc($s['estado']) ?></span>
                            <?php if ($cobroSol && !empty($cobroSol['cuotas_pend'])): ?>
                                <button type="button" class="btn btn-primary btn-sm"
                                        data-modal="modal-pago"
                                        data-sol="<?= (int) $s['id'] ?>"
                                        data-monto="<?= esc($cobroSol['cuotas_pend'][0]['pendiente']) ?>">
                                    <?= icon('dollar-sign', 14) ?> Cobrar
                                </button>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <a href="<?= base_url($slug . '/portal/solicitud') ?>" class="btn btn-outline btn-block mt-4"><?= icon('file-plus', 15) ?> Nueva solicitud</a>
        </div>

    <?php else: ?>
        <!-- Secciones complementarias (direccion, contacto, referencia, ...) -->
        <div class="card portal-card">
            <div class="card-head">
                <h4 class="card-title"><?= icon($t['icono'], 16) ?> <?= esc($t['nombre']) ?></h4>
                <button type="button" class="btn btn-primary btn-sm" data-modal="modal-<?= esc($key) ?>">
                    <?= icon('plus', 14) ?> Agregar
                </button>
            </div>

            <?php $items = $secciones[$key] ?? []; ?>
            <?php if (empty($items)): ?>
                <p class="card-subtitle">Sin <?= mb_strtolower($t['nombre']) ?> registrados.</p>
            <?php else: ?>
                <div class="oui-list mt-2">
                <?php foreach ($items as $it):
                    $editCampos = array_merge(array_keys($t['cols']), $key === 'direccion' ? ['latitud','longitud'] : []);
                    $campos = [];
                    foreach ($editCampos as $c) { $campos[$c] = $it[$c] ?? ''; }
                    $updateUrl = base_url($base . '/dato/' . $key . '/' . $it['id'] . '/actualizar');

                    if ($key === 'direccion') {
                        $ubic   = implode(' · ', array_filter([$it['departamento'] ?? '', $it['ciudad'] ?? '']));
                        $titulo = $it['barrio'] ?? $it['ciudad'] ?? 'Dirección';
                        $sub    = $it['detalle'] ?? '';
                        $meta   = '<span class="map-links">'
                            . '<a class="map-btn map-waze" href="' . $urlWaze($it) . '" target="_blank" rel="noopener">' . icon('waze', 12) . ' Waze</a>'
                            . '<a class="map-btn map-gmaps" href="' . $urlMaps($it) . '" target="_blank" rel="noopener">' . icon('gmaps', 12) . ' Maps</a>'
                            . '</span>';
                        $chipTipo = $it['tipo'] ? '<span class="dir-tipo">' . esc($it['tipo']) . '</span>' : '';
                    } else {
                        $vals = [];
                        foreach ($t['cols'] as $col => $label) {
                            $v = trim((string) ($it[$col] ?? ''));
                            if ($v === '') continue;
                            $vals[] = ($key === 'documento' && $col === 'archivo')
                                ? '<a href="' . base_url('public/uploads/documentos/' . $v) . '" target="_blank">Ver archivo</a>'
                                : esc($v);
                        }
                        $titulo   = strip_tags($vals[0] ?? '-');
                        $sub      = implode(' &nbsp;·&nbsp; ', array_slice($vals, 1));
                        $meta     = '';
                        $chipTipo = '';
                        $ubic     = '';
                    }
                ?>
                    <div class="oui-list-item oui-clickable" role="button"
                         data-tipo="<?= esc($key) ?>"
                         data-editar='<?= esc(json_encode(['campos' => $campos, 'url' => $updateUrl])) ?>'>
                        <span class="oui-icon"><?= icon($t['icono'], 18) ?></span>
                        <span class="oui-body">
                            <span class="oui-title"><?= esc($titulo) ?> <?= $chipTipo ?></span>
                            <?php if ($ubic !== ''): ?><span class="oui-sub oui-sub-ubic"><?= icon('map-pin', 12) ?> <?= esc($ubic) ?></span><?php endif; ?>
                            <?php if ($sub !== ''): ?><span class="oui-sub"><?= $sub ?></span><?php endif; ?>
                        </span>
                        <?= $meta ?>
                        <form class="oui-meta" action="<?= base_url($base . '/dato/' . $key . '/' . $it['id'] . '/eliminar') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="icon-btn" title="Eliminar"><?= icon('trash', 15) ?></button>
                        </form>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Modal: registrar pago (queda EN REVISIÓN hasta que oficina confirme) -->
<?php if (!empty($cobros)): ?>
<div class="modal-overlay" id="modal-pago" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('dollar-sign', 17) ?> Registrar pago</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url($slug . '/portal/cobros/0/abonar') ?>"
              data-action-tpl="<?= base_url($slug . '/portal/cobros/__ID__/abonar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="volver" value="<?= '/' . $slug . '/portal/cliente/' . $cliId . '?tab=solicitudes' ?>">
            <div class="modal-body">
                <div class="form-group form-full">
                    <label>Crédito</label>
                    <select name="solicitud_id" required>
                        <option value="">- Seleccioné el crédito -</option>
                        <?php foreach ($cobros as $cr): ?>
                            <option value="<?= (int) $cr['id'] ?>"
                                    data-pendiente="<?= esc($cr['cuota_sugerida'] ?? '') ?>">
                                <?= esc($cr['codigo_credito'] ?: '#' . $cr['id']) ?> · Saldo <?= esc($m2) ?> <?= number_format((float) $cr['saldo'], 0) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Monto (<?= esc($m2) ?>)</label>
                    <input type="number" name="monto" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Método</label>
                    <select name="metodo">
                        <?php foreach (($metodos ?? ['EFECTIVO' => 'Efectivo']) as $k => $lbl): if ($k === 'INTERNO') continue; ?>
                            <option value="<?= esc($k) ?>"><?= esc($lbl) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group form-full">
                    <label>Observación</label>
                    <input type="text" name="observacion" maxlength="255" placeholder="Opcional">
                </div>
                <p class="card-subtitle" style="grid-column:1/-1; margin:0;">
                    El pago queda <strong>en revisión</strong> hasta que oficina confirme que recibió el dinero.
                </p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Registrar</button>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<!-- Modales de "Agregar" por pestaña -->
<?php foreach ($tabs as $key => $t): if ($key === 'datos' || $key === 'solicitudes') continue; ?>
<div class="modal-overlay" id="modal-<?= esc($key) ?>" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4 data-add-title="Agregar <?= esc(mb_strtolower($t['nombre'])) ?>" data-edit-title="Editar <?= esc(mb_strtolower($t['nombre'])) ?>"><?= icon($t['icono'], 17) ?> Agregar <?= esc(mb_strtolower($t['nombre'])) ?></h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form action="<?= base_url($base . '/dato/' . $key) ?>" method="post" data-add-url="<?= base_url($base . '/dato/' . $key) ?>" <?= $key === 'documento' ? 'enctype="multipart/form-data"' : '' ?>>
            <?= csrf_field() ?>
            <div class="modal-body <?= $key === 'direccion' ? 'modal-body-geo' : '' ?>">
                <?php if ($key === 'direccion'): ?>
                    <div class="form-group form-full">
                        <label>Buscar ubicación</label>
                        <div class="geo-search">
                            <input type="text" id="geo-buscar" placeholder="Ej: Barrio Santa Rosa, Managua">
                            <button type="button" class="btn btn-outline btn-sm" id="geo-buscar-btn"><?= icon('search', 14) ?> Buscar</button>
                        </div>
                    </div>
                    <div class="form-group form-full">
                        <div class="geo-mapa-wrap">
                            <div id="geo-mapa" class="geo-mapa"></div>
                            <button type="button" class="geo-toggle-sat" id="geo-toggle-sat" title="Cambiar vista">Satélite</button>
                        </div>
                        <small class="geo-hint">Tocó el mapa para fijar la ubicación.</small>
                    </div>
                    <div class="form-group form-full">
                        <button type="button" class="btn btn-outline btn-block" id="geo-gps"><?= icon('map-pin', 15) ?> Usar mi ubicación GPS</button>
                    </div>
                    <div class="form-group">
                        <label>Tipo</label>
                        <select name="tipo">
                            <option value="casa">Casa</option>
                            <option value="trabajo">Trabajo</option>
                            <option value="negocio">Negocio</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Departamento</label>
                        <input type="text" name="departamento" id="geo-departamento" placeholder="Departamento">
                    </div>
                    <div class="form-group">
                        <label>Ciudad / Municipio</label>
                        <input type="text" name="ciudad" id="geo-ciudad" placeholder="Ciudad o municipio">
                    </div>
                    <div class="form-group">
                        <label>Barrio</label>
                        <input type="text" name="barrio" id="geo-barrio" placeholder="Barrio">
                    </div>
                    <div class="form-group form-full">
                        <label>Detalle / referencia de la dirección</label>
                        <input type="text" name="detalle" id="geo-detalle" placeholder="Ej: Del parque 2 cuadras al este, casa verde">
                    </div>
                    <input type="hidden" name="latitud"  id="geo-latitud">
                    <input type="hidden" name="longitud" id="geo-longitud">
                <?php else: ?>
                    <?php foreach ($t['cols'] as $col => $label): ?>
                        <?php if ($key === 'documento' && $col === 'archivo'): ?>
                            <div class="form-group form-full">
                                <label><?= esc($label) ?> *</label>
                                <input type="file" name="archivo" required>
                            </div>
                        <?php elseif (isset($fieldSelects[$key . '.' . $col])): ?>
                            <div class="form-group">
                                <label><?= esc($label) ?></label>
                                <select name="<?= esc($col) ?>">
                                    <?php foreach ($fieldSelects[$key . '.' . $col] as $ov => $ol): ?>
                                        <option value="<?= esc($ov) ?>"><?= esc($ol) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($key === 'negocio' && $col === 'dias_venta'): ?>
                            <div class="form-group form-full">
                                <label><?= esc($label) ?></label>
                                <div class="dias-venta">
                                    <?php foreach (['L'=>'L','M'=>'M','X'=>'X','J'=>'J','V'=>'V','S'=>'S','D'=>'D'] as $d): ?>
                                        <label class="dia"><input type="checkbox" name="dias_venta[]" value="<?= $d ?>"><span><?= $d ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (in_array($key . '.' . $col, $fieldNumber, true)): ?>
                            <div class="form-group">
                                <label><?= esc($label) ?></label>
                                <input type="number" name="<?= esc($col) ?>" step="0.01" min="0" placeholder="<?= esc($label) ?>">
                            </div>
                        <?php else: ?>
                            <div class="form-group">
                                <label><?= esc($label) ?></label>
                                <input type="text" name="<?= esc($col) ?>" placeholder="<?= esc($label) ?>">
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('plus', 14) ?> Guardar</button>
            </div>
        </form>
    </div>
</div>
<?php endforeach; ?>

<?= $this->endSection() ?>
