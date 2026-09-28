<?= $this->extend('layouts/partner') ?>

<?= $this->section('head') ?>
<link rel="stylesheet" href="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.css">
<script src="https://unpkg.com/maplibre-gl@4.1.2/dist/maplibre-gl.js"></script>
<?= $this->endSection() ?>

<?= $this->section('content') ?>

<?php
// Configuración de las pestañas: columnas + campos del form
$tabs = [
    'datos'      => ['nombre' => 'Datos',       'icono' => 'user'],
    'direccion'  => ['nombre' => 'Direcciones', 'icono' => 'map-pin', 'geo' => true,
        'cols' => ['tipo' => 'Tipo', 'departamento' => 'Departamento', 'ciudad' => 'Ciudad', 'barrio' => 'Barrio', 'detalle' => 'Detalle']],
    'contacto'   => ['nombre' => 'Contactos',   'icono' => 'phone',
        'cols' => ['tipo' => 'Tipo', 'valor' => 'Valor']],
    'referencia' => ['nombre' => 'Referencias', 'icono' => 'users',
        'cols' => ['nombre' => 'Nombre', 'parentesco' => 'Parentesco', 'telefono' => 'Teléfono', 'direccion' => 'Dirección']],
    'negocio'    => ['nombre' => 'Negocios',    'icono' => 'briefcase',
        'cols' => ['nombre' => 'Nombre', 'actividad' => 'Actividad', 'sector_economico' => 'Sector', 'direccion' => 'Dirección', 'tiempo' => 'Tiempo', 'dias_venta' => 'Días de venta', 'promedio_venta_dia' => 'Venta/día']],
    'activo'     => ['nombre' => 'Activos',     'icono' => 'trending-up',
        'cols' => ['descripcion' => 'Descripción', 'valor' => 'Valor']],
    'pasivo'     => ['nombre' => 'Pasivos',     'icono' => 'trending-down',
        'cols' => ['descripcion' => 'Descripción', 'acreedor' => 'Acreedor', 'monto' => 'Monto']],
    'ingreso'    => ['nombre' => 'Ingresos',    'icono' => 'dollar-sign',
        'cols' => ['fuente' => 'Fuente', 'monto' => 'Monto']],
    'documento'  => ['nombre' => 'Documentos',  'icono' => 'file-text',
        'cols' => ['tipo' => 'Tipo', 'descripcion' => 'Descripción', 'archivo' => 'Archivo']],
];
// Campos que se renderizan como <select> con opciones (tipo.campo → opciones)
$fieldSelects = [
    'contacto.tipo'          => ['telefono' => 'Teléfono', 'correo' => 'Correo', 'whatsapp' => 'WhatsApp', 'otro' => 'Otro'],
    'documento.tipo'         => ['cedula' => 'Cédula', 'pasaporte' => 'Pasaporte', 'contrato' => 'Contrato', 'cartas' => 'Cartas', 'otro' => 'Otro'],
    'negocio.sector_economico' => ['comercio' => 'Comercio', 'servicios' => 'Servicios', 'alimentos' => 'Alimentos y bebidas', 'agricultura' => 'Agricultura', 'artesanias' => 'Artesanías', 'transporte' => 'Transporte', 'otro' => 'Otro'],
];
// Campos numéricos (input type=number)
$fieldNumber = ['negocio.promedio_venta_dia', 'activo.valor', 'pasivo.monto', 'ingreso.monto'];
$empId = (int) $cliente['id'];
?>

<?= view('partials/detail_hero', [
    'titulo'    => trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? '')),
    'subtitulo' => trim(($cliente['codigo'] ?? 'Cliente') . ' · ' . ($persona['cedula'] ?? 'Sin cédula')),
    'icono'     => 'user',
    'volver'    => '/socios/clientes',
    'acciones'  => ($puede_editar ?? false) ? [
        ['nombre' => 'Editar datos', 'url' => '/socios/clientes/' . $empId . '/editar', 'icono' => 'edit'],
    ] : [],
]) ?>

<!-- Card de persona -->
<div class="card persona-card">
    <div class="persona-avatar"><?= icon('user', 30) ?></div>
    <div class="persona-basico">
        <h3 class="persona-nombre"><?= esc(trim(($persona['nombres'] ?? '') . ' ' . ($persona['apellidos'] ?? ''))) ?></h3>
        <span class="badge badge-soft"><?= esc($cliente['codigo'] ?? '—') ?></span>
        <span class="badge"><?= esc($cliente['estado'] ?? 'ACTIVO') ?></span>
        <?php if (!empty($persona['genero'])): ?>
            <span class="badge badge-soft"><?= esc($persona['genero'] === 'F' ? 'Mujer' : 'Hombre') ?></span>
        <?php endif; ?>
    </div>
    <div class="persona-meta">
        <span><?= icon('user', 13) ?> <?= esc($persona['cedula'] ?? '—') ?></span>
        <span><?= icon('phone', 13) ?> <?= esc($persona['telefono'] ?? '—') ?></span>
        <span><?= icon('mail', 13) ?> <?= esc($persona['email'] ?? '—') ?></span>
        <span><?= icon('map-pin', 13) ?> <?= esc($persona['direccion'] ?? '—') ?></span>
    </div>
</div>

<!-- Pestañas -->
<div class="tabs" id="persona-tabs">
    <?php foreach ($tabs as $slug => $t): ?>
        <button type="button" class="tab-btn <?= $tabActiva === $slug ? 'on' : '' ?>" data-tab="<?= esc($slug) ?>">
            <?= icon($t['icono'], 15) ?> <?= esc($t['nombre']) ?>
        </button>
    <?php endforeach; ?>
</div>

<!-- Paneles -->
<?php foreach ($tabs as $slug => $t): ?>
<div class="tab-panel <?= $tabActiva === $slug ? 'on' : '' ?>" data-panel="<?= esc($slug) ?>">

    <?php if ($slug === 'datos'): ?>
        <div class="card">
            <div class="detail-grid">
                <div><label>Nombres</label><p><?= esc($persona['nombres'] ?? '—') ?></p></div>
                <div><label>Apellidos</label><p><?= esc($persona['apellidos'] ?? '—') ?></p></div>
                <div><label>Cédula</label><p><?= esc($persona['cedula'] ?? '—') ?></p></div>
                <div><label>Teléfono</label><p><?= esc($persona['telefono'] ?? '—') ?></p></div>
                <div><label>Correo</label><p><?= esc($persona['email'] ?? '—') ?></p></div>
                <div><label>Género</label><p><?= esc(($persona['genero'] ?? '') === 'F' ? 'Mujer' : (($persona['genero'] ?? '') === 'M' ? 'Hombre' : '—')) ?></p></div>
                <div><label>Fecha de nacimiento</label><p><?= esc($persona['fecha_nac'] ?? '—') ?></p></div>
                <div><label>Código</label><p><?= esc($cliente['codigo'] ?? '—') ?></p></div>
                <div><label>Límite de crédito</label><p><?= esc($cliente['limite_credito'] ?? '—') ?></p></div>
                <div><label>Monto mínimo préstamo</label><p><?= esc($cliente['monto_min'] ?? '—') ?></p></div>
                <div><label>Monto máximo préstamo</label><p><?= esc($cliente['monto_max'] ?? '—') ?></p></div>
                <div><label>Estado</label><p><?= esc($cliente['estado'] ?? 'ACTIVO') ?></p></div>
                <div style="grid-column:1/-1"><label>Observaciones</label><p><?= esc($cliente['observaciones'] ?? '—') ?></p></div>
            </div>
        </div>
    <?php else: ?>
        <div class="card">
            <div class="card-head">
                <h4 class="card-title"><?= icon($t['icono'], 16) ?> <?= esc($t['nombre']) ?></h4>
                <?php if ($puede_editar ?? false): ?>
                    <button type="button" class="btn btn-primary btn-sm" data-modal="modal-<?= esc($slug) ?>">
                        <?= icon('plus', 14) ?> Agregar
                    </button>
                <?php endif; ?>
            </div>

            <?php $items = $secciones[$slug] ?? []; ?>
            <?php if (empty($items)): ?>
                <p class="card-subtitle">Sin <?= mb_strtolower($t['nombre']) ?> registrados.</p>
            <?php else: ?>
                <div class="oui-list mt-2">
                <?php foreach ($items as $it):
                    // Campos a editar = columnas + geo (direccion lleva latitud/longitud)
                    $editCampos = array_merge(array_keys($t['cols']), $slug === 'direccion' ? ['latitud','longitud'] : []);
                    $campos = [];
                    foreach ($editCampos as $c) { $campos[$c] = $it[$c] ?? ''; }
                    $updateUrl = base_url('socios/clientes/' . $empId . '/dato/' . $slug . '/' . $it['id'] . '/actualizar');

                    if ($slug === 'direccion') {
                        // Diseño: Barrio como título, detalle como sub, Ver mapa como meta
                        $ubic = implode(' · ', array_filter([$it['departamento'] ?? '', $it['ciudad'] ?? '']));
                        $titulo = $it['barrio'] ?? $it['ciudad'] ?? 'Dirección';
                        $sub    = $it['detalle'] ?? '';
                        $meta   = (!empty($it['latitud']) && !empty($it['longitud']))
                            ? '<a class="oui-map" href="https://www.openstreetmap.org/?mlat=' . $it['latitud'] . '&mlon=' . $it['longitud'] . '#map=16/' . $it['latitud'] . '/' . $it['longitud'] . '" target="_blank" title="Ver en mapa">' . icon('map-pin', 14) . ' Mapa</a>'
                            : '';
                        $chipTipo = $it['tipo'] ? '<span class="dir-tipo">' . esc($it['tipo']) . '</span>' : '';
                    } else {
                        $vals = [];
                        foreach ($t['cols'] as $col => $label) {
                            $v = trim((string) ($it[$col] ?? ''));
                            if ($v === '') continue;
                            $vals[] = ($slug === 'documento' && $col === 'archivo')
                                ? '<a href="' . base_url('public/uploads/documentos/' . $v) . '" target="_blank">Ver archivo</a>'
                                : esc($v);
                        }
                        $titulo = strip_tags($vals[0] ?? '—');
                        $sub    = implode(' &nbsp;·&nbsp; ', array_slice($vals, 1));
                        $meta   = '';
                        $chipTipo = '';
                        $ubic = '';
                    }
                ?>
                    <div class="oui-list-item oui-clickable" role="button"
                         data-tipo="<?= esc($slug) ?>"
                         data-editar='<?= esc(json_encode(["campos" => $campos, "url" => $updateUrl])) ?>'>
                        <span class="oui-icon"><?= icon($t['icono'], 18) ?></span>
                        <span class="oui-body">
                            <span class="oui-title"><?= esc($titulo) ?> <?= $chipTipo ?></span>
                            <?php if ($ubic !== ''): ?><span class="oui-sub oui-sub-ubic"><?= icon('map-pin', 12) ?> <?= esc($ubic) ?></span><?php endif; ?>
                            <?php if ($sub !== ''): ?><span class="oui-sub"><?= $sub ?></span><?php endif; ?>
                        </span>
                        <?= $meta ?>
                        <?php if ($puede_editar ?? false): ?>
                        <form class="oui-meta" action="<?= base_url('socios/clientes/' . $empId . '/dato/' . $slug . '/' . $it['id'] . '/eliminar') ?>" method="post">
                            <?= csrf_field() ?>
                            <button type="submit" class="icon-btn" title="Eliminar"><?= icon('trash', 15) ?></button>
                        </form>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
                </div>
            <?php endif; ?>

        </div>
    <?php endif; ?>
</div>
<?php endforeach; ?>

<!-- Modales de "Agregar" por pestaña -->
<?php foreach ($tabs as $slug => $t): if ($slug === 'datos') continue; ?>
<div class="modal-overlay" id="modal-<?= esc($slug) ?>" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4 data-add-title="Agregar <?= esc(mb_strtolower($t['nombre'])) ?>" data-edit-title="Editar <?= esc(mb_strtolower($t['nombre'])) ?>"><?= icon($t['icono'], 17) ?> Agregar <?= esc(mb_strtolower($t['nombre'])) ?></h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form action="<?= base_url('socios/clientes/' . $empId . '/dato/' . $slug) ?>" method="post" data-add-url="<?= base_url('socios/clientes/' . $empId . '/dato/' . $slug) ?>" <?= $slug === 'documento' ? 'enctype="multipart/form-data"' : '' ?>>
            <?= csrf_field() ?>
            <div class="modal-body <?= $slug === 'direccion' ? 'modal-body-geo' : '' ?>">
                <?php if ($slug === 'direccion'): ?>
                    <!-- Buscador de mapa + mapa -->
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
                        <small class="geo-hint">Haz clic en el mapa para fijar la ubicación.</small>
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
                        <?php if ($slug === 'documento' && $col === 'archivo'): ?>
                            <div class="form-group form-full">
                                <label><?= esc($label) ?> *</label>
                                <input type="file" name="archivo" required>
                            </div>
                        <?php elseif (isset($fieldSelects[$slug . '.' . $col])): ?>
                            <div class="form-group">
                                <label><?= esc($label) ?></label>
                                <select name="<?= esc($col) ?>">
                                    <?php foreach ($fieldSelects[$slug . '.' . $col] as $ov => $ol): ?>
                                        <option value="<?= esc($ov) ?>"><?= esc($ol) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php elseif ($slug === 'negocio' && $col === 'dias_venta'): ?>
                            <div class="form-group form-full">
                                <label><?= esc($label) ?></label>
                                <div class="dias-venta">
                                    <?php foreach (['L'=>'L','M'=>'M','X'=>'X','J'=>'J','V'=>'V','S'=>'S','D'=>'D'] as $d): ?>
                                        <label class="dia"><input type="checkbox" name="dias_venta[]" value="<?= $d ?>"><span><?= $d ?></span></label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php elseif (in_array($slug . '.' . $col, $fieldNumber, true)): ?>
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
