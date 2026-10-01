<?= $this->extend('layouts/portal') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<?php
$titulos = [
    'desembolso'   => ['Desembolso', 'dollar-sign', 'Dinero por entregar a tus clientes'],
    'cobros'       => ['Cobros', 'credit-card', 'Cuotas por cobrar de tu cartera'],
    'arqueo'       => ['Mi caja', 'clipboard', 'Cierre diario - lo que debés tener en efectivo'],
    'recuperacion' => ['Recuperación', 'refresh-cw', 'Clientes en seguimiento y cobros'],
    'cartera'      => ['Cartera de clientes', 'briefcase', 'Todos tus clientes'],
    'actividad'    => ['Actividad reciente', 'activity', 'Solicitudes y movimientos'],
];
[$tit, $ico, $desc] = $titulos[$seccion] ?? ['Sección', 'file-text', ''];
?>

<!-- Encabezado con volver -->
<div class="app-header card portal-card">
    <a href="<?= base_url($slug . '/portal/panel') ?>" class="app-back"><?= icon('chevron-left', 20) ?></a>
    <div>
        <h3><?= esc($tit) ?></h3>
        <p class="app-rol"><?= esc($desc) ?></p>
    </div>
</div>

<?php if (!in_array($seccion, ['arqueo', 'cobros'], true)): ?><div class="card portal-card"><?php endif; ?>
    <?php if ($seccion === 'desembolso'): ?>
        <?php if (empty($solicitudes)): ?>
            <p class="card-subtitle">No tenés desembolsos pendientes en tu cartera.</p>
        <?php else: ?>
            <div class="oui-list">
                <?php foreach ($solicitudes as $s): ?>
                    <?php
                    $fDes = (string) ($s['fecha_desembolso'] ?? '');
                    $vencio = $fDes !== '' && $fDes <= ($hoy ?? date('Y-m-d'));

                    // Contacto: WhatsApp + Waze/Maps (mismo patrón que cartera)
                    $tel = preg_replace('/\D/', '', (string) ($s['telefono'] ?? ''));
                    if (strlen($tel) === 8) $tel = '505' . $tel;   // Nicaragua por defecto

                    $dirGeo = $s['dir_geo'] ?? null;
                    $conGps = $dirGeo && is_numeric($dirGeo['latitud'] ?? null) && is_numeric($dirGeo['longitud'] ?? null);
                    $dirTxt = '';
                    if (!$conGps) {
                        $dirTxt = trim(implode(', ', array_filter([
                            $dirGeo['detalle'] ?? '', $dirGeo['barrio'] ?? '',
                            $dirGeo['ciudad'] ?? '', $dirGeo['departamento'] ?? '',
                        ])));
                        if ($dirTxt === '') $dirTxt = trim((string) ($s['direccion'] ?? ''));
                    }
                    if ($conGps) {
                        $urlWaze = 'https://waze.com/ul?ll=' . $dirGeo['latitud'] . '%2C' . $dirGeo['longitud'] . '&navigate=yes';
                        $urlMaps = 'https://www.google.com/maps/dir/?api=1&destination=' . $dirGeo['latitud'] . ',' . $dirGeo['longitud'];
                    } elseif ($dirTxt !== '') {
                        $urlWaze = 'https://waze.com/ul?q=' . urlencode($dirTxt) . '&navigate=yes';
                        $urlMaps = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($dirTxt);
                    } else {
                        $urlWaze = $urlMaps = null;
                    }
                    ?>
                    <div class="cli-item">
                        <div class="oui-list-item">
                            <span class="oui-icon"><?= icon('dollar-sign', 18) ?></span>
                            <span class="oui-body">
                                <span class="oui-title"><?= esc(trim($s['nombres'] . ' ' . $s['apellidos'])) ?> - <?= esc($m2) ?> <?= number_format((float) ($s['monto_aprobado'] ?: $s['monto']), 0) ?></span>
                                <span class="oui-sub"><?= esc($s['destino'] ?? 'Préstamo aprobado') ?> · Entrega: <?= esc($fDes ?: 'sin fecha') ?></span>
                            </span>
                            <span class="badge <?= $vencio ? 'badge-soft' : '' ?>"><?= $vencio ? 'Hoy' : 'Pendiente' ?></span>
                            <?php if ($puede_entregar ?? true): ?>
                            <form method="post" action="<?= base_url($slug . '/portal/desembolso/' . $s['id'] . '/entregar') ?>"
                                  onsubmit="return confirm('¿Confirmar que entregaste el dinero?');">
                                <?= csrf_field() ?>
                                <button class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Entregado</button>
                            </form>
                            <?php else: ?>
                                <span class="oui-sub" title="La oficina confirmará la entrega">Entrega en oficina</span>
                            <?php endif; ?>
                        </div>
                        <?php if ($tel !== '' || $urlWaze): ?>
                        <div class="cli-acciones">
                            <?php if ($tel !== ''): ?>
                                <a class="cli-act cli-wa" href="https://wa.me/<?= esc($tel) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 14) ?> WhatsApp</a>
                            <?php endif; ?>
                            <?php if ($urlWaze): ?>
                                <a class="cli-act cli-waze" href="<?= esc($urlWaze) ?>" target="_blank" rel="noopener"><?= icon('waze', 14) ?> Waze</a>
                                <a class="cli-act cli-maps" href="<?= esc($urlMaps) ?>" target="_blank" rel="noopener"><?= icon('gmaps', 14) ?> Maps</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($seccion === 'cartera'): ?>
        <?php if (empty($clientes)): ?>
            <p class="card-subtitle">No hay clientes en tu cartera.</p>
        <?php else: ?>
            <div class="cli-buscar">
                <?= icon('search', 16) ?>
                <input type="text" id="cli-buscar" autocomplete="off"
                       placeholder="Buscar por nombre, código o cédula…"
                       data-buscar="#cli-list .cli-item" data-empty="cli-vacio">
            </div>
            <p class="cli-buscar-vacio" id="cli-vacio" hidden>Sin resultados para esa búsqueda.</p>
            <div class="cli-list" id="cli-list">
                <?php foreach ($clientes as $c): ?>
                    <?php
                    $ini    = mb_strtoupper(mb_substr(trim($c['nombres']), 0, 1) . mb_substr(trim($c['apellidos']), 0, 1));
                    $tel    = preg_replace('/\D/', '', (string) ($c['telefono'] ?? ''));
                    if (strlen($tel) === 8) $tel = '505' . $tel;   // Nicaragua por defecto

                    // Mapas: prioridad a la dirección con GPS guardado en la ficha
                    $dirGeo  = $c['dir_geo'] ?? null;
                    $conGps  = $dirGeo && is_numeric($dirGeo['latitud'] ?? null) && is_numeric($dirGeo['longitud'] ?? null);
                    $dirTxt  = '';
                    if (!$conGps) {
                        $dirTxt = trim(implode(', ', array_filter([
                            $dirGeo['detalle'] ?? '', $dirGeo['barrio'] ?? '',
                            $dirGeo['ciudad'] ?? '', $dirGeo['departamento'] ?? '',
                        ])));
                        if ($dirTxt === '') {
                            $dirTxt = trim((string) ($c['direccion'] ?? ''));
                        }
                    }

                    if ($conGps) {
                        $urlWaze = 'https://waze.com/ul?ll=' . $dirGeo['latitud'] . '%2C' . $dirGeo['longitud'] . '&navigate=yes';
                        $urlMaps = 'https://www.google.com/maps/dir/?api=1&destination=' . $dirGeo['latitud'] . ',' . $dirGeo['longitud'];
                    } elseif ($dirTxt !== '') {
                        $urlWaze = 'https://waze.com/ul?q=' . urlencode($dirTxt) . '&navigate=yes';
                        $urlMaps = 'https://www.google.com/maps/dir/?api=1&destination=' . urlencode($dirTxt);
                    } else {
                        $urlWaze = $urlMaps = null;
                    }
                    ?>
                    <div class="cli-item">
                        <a class="cli-main" href="<?= base_url($slug . '/portal/cliente/' . $c['id']) ?>">
                            <span class="cli-avatar"><?= esc($ini !== '' ? $ini : '?') ?></span>
                            <span class="cli-info">
                                <span class="cli-nombre"><?= esc(trim($c['nombres'] . ' ' . $c['apellidos'])) ?></span>
                                <span class="cli-sub">
                                    <?= esc($c['codigo'] ?? '-') ?> · <?= esc($c['cedula'] ?: 'Sin cédula') ?>
                                    <span class="badge <?= $c['estado'] === 'ACTIVO' ? 'badge-soft' : '' ?>"><?= esc($c['estado']) ?></span>
                                </span>
                            </span>
                            <span class="cli-flecha"><?= icon('chevron-right', 18) ?></span>
                        </a>
                        <?php if ($tel !== '' || $urlWaze): ?>
                        <div class="cli-acciones">
                            <?php if ($tel !== ''): ?>
                                <a class="cli-act cli-wa" href="https://wa.me/<?= esc($tel) ?>" target="_blank" rel="noopener"><?= icon('whatsapp', 14) ?> WhatsApp</a>
                            <?php endif; ?>
                            <?php if ($urlWaze): ?>
                                <a class="cli-act cli-waze" href="<?= esc($urlWaze) ?>" target="_blank" rel="noopener"><?= icon('waze', 14) ?> Waze</a>
                                <a class="cli-act cli-maps" href="<?= esc($urlMaps) ?>" target="_blank" rel="noopener"><?= icon('gmaps', 14) ?> Maps</a>
                            <?php endif; ?>
                        </div>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    <?php elseif ($seccion === 'actividad'): ?>
        <?php if (empty($solicitudes)): ?>
            <p class="card-subtitle">Sin actividad reciente.</p>
        <?php else: ?>
            <?php
            $stCls = [
                'CONTACTO'   => 'st-info',
                'CREADA'     => 'st-info',
                'REVISION'   => 'st-warn',
                'APROBADA'   => 'st-ok',
                'DESEMBOLSO' => 'st-warn',
                'ACTIVO'     => 'st-ok',
                'RECHAZADA'  => 'st-bad',
            ];
            ?>
            <div class="cli-list">
                <?php foreach ($solicitudes as $s): ?>
                    <?php
                    $iniS = mb_strtoupper(mb_substr(trim($s['nombres']), 0, 1) . mb_substr(trim($s['apellidos']), 0, 1));
                    $fSol = !empty($s['created_at']) ? date('d/m/Y', strtotime($s['created_at'])) : '';
                    $editableSol = in_array($s['estado'], ['CREADA', 'REVISION'], true);
                    ?>
                    <div class="cli-item">
                        <a class="cli-main" href="<?= base_url($slug . '/portal/cliente/' . $s['cliente_id']) ?>">
                            <span class="cli-avatar"><?= esc($iniS !== '' ? $iniS : '?') ?></span>
                            <span class="cli-info">
                                <span class="cli-nombre">
                                    <?= esc(trim($s['nombres'] . ' ' . $s['apellidos'])) ?>
                                    <span class="sol-monto"><?= esc($m2) ?> <?= number_format((float) ($s['monto_aprobado'] ?: $s['monto']), 0) ?></span>
                                </span>
                                <span class="cli-sub"><?= esc($s['destino'] ?? 'Solicitud de crédito') ?> · <?= esc($fSol) ?></span>
                                <?php if (!empty($s['nota_revision']) && $s['estado'] === 'REVISION'): ?>
                                    <span class="cli-sub sol-nota-rev"><?= icon('message-square', 12) ?> <?= esc($s['nota_revision']) ?></span>
                                <?php endif; ?>
                            </span>
                            <span class="badge <?= $stCls[$s['estado']] ?? '' ?>"><?= esc($s['estado']) ?></span>
                        </a>
                        <?php if ($editableSol): ?>
                            <a class="cli-act" href="<?= base_url($slug . '/portal/solicitud/' . $s['id'] . '/editar') ?>">
                                <?= icon('edit', 14) ?> Editar
                            </a>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <!-- Feed de eventos: quién creó/movió/aprobó cada solicitud de la cartera -->
        <?php if (!empty($eventos)): ?>
            <h4 class="card-title" style="margin-top:20px;"><?= icon('activity', 16) ?> Historial de movimientos</h4>
            <ul class="tl">
                <?php foreach ($eventos as $ev): ?>
                    <?php
                    $acc = $ev['accion'] ?? 'ESTADO';
                    $txt = $acc === 'CREADO'  ? 'Solicitud creada'
                         : ($acc === 'EDITADO' ? 'Datos actualizados'
                         : ('Pasó a ' . mb_strtolower($ev['estado'] ?? '')));
                    $cliEv = trim(($ev['nombres'] ?? '') . ' ' . ($ev['apellidos'] ?? ''));
                    ?>
                    <li class="tl-item">
                        <div class="tl-dot tl-<?= strtolower($acc) ?>"></div>
                        <div class="tl-body">
                            <div class="tl-top">
                                <strong><?= esc($cliEv !== '' ? $cliEv : 'Solicitud #' . $ev['solicitud_id']) ?></strong>
                                <?php if (!empty($ev['estado'])): ?>
                                    <span class="badge st-<?= in_array($ev['estado'], ['APROBADA','ACTIVO'], true) ? 'ok' : (in_array($ev['estado'], ['REVISION','DESEMBOLSO'], true) ? 'warn' : 'info') ?>"><?= esc($ev['estado']) ?></span>
                                <?php endif; ?>
                            </div>
                            <p class="tl-txt"><?= esc($txt) ?> · <?= esc($ev['actor'] ?? 'Sistema') ?><?= !empty($ev['nota']) ? ' — ' . esc($ev['nota']) : '' ?></p>
                            <small class="tl-fecha"><?= esc($ev['created_at'] ?? '') ?></small>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    <?php elseif ($seccion === 'cobros'): ?>
        <?php
        $hoy  = $hoy ?? date('Y-m-d');
        $fSel = $fecha_cobro ?? $hoy;   // "por cobrar" hasta esta fecha
        // ?mora=1 → solo cuotas vencidas (recuperación); fecha → cuotas con vence <= fecha
        $conCuotas = [];
        foreach ($creditos ?? [] as $cr) {
            $pend = array_values(array_filter(
                $cr['cuotas_pend'] ?? [],
                fn($c) => (!$mora || !empty($c['vencida'])) && $c['fecha_vence'] <= $fSel
            ));
            if ($pend !== []) {
                $cr['_pend']    = $pend;
                // acumulado: suma de lo pendiente de todas las cuotas hasta la fecha
                $cr['_cobrar']  = array_sum(array_map(fn($c) => $c['pendiente'], $pend));
                $conCuotas[]    = $cr;
            }
        }
        ?>
        <!-- Stats del día -->
        <div class="cob-stats">
            <div class="cob-stat cob-stat-pend">
                <span class="cob-stat-ico"><?= icon('clock', 20) ?></span>
                <div>
                    <b><?= esc($m2) ?> <?= number_format($pendiente_hoy, 0) ?></b>
                    <small>Pendiente de cobrar hoy</small>
                </div>
            </div>
            <div class="cob-stat cob-stat-ok">
                <span class="cob-stat-ico"><?= icon('check-circle', 20) ?></span>
                <div>
                    <b><?= esc($m2) ?> <?= number_format($cobrado_hoy, 0) ?></b>
                    <small>Cobrado hoy (<?= count($cobrados_hoy) ?>)</small>
                </div>
            </div>
        </div>

        <!-- Registrar abono -->
        <button type="button" class="btn btn-primary" style="width:100%; justify-content:center;" data-modal="modal-abono">
            <?= icon('plus', 16) ?> Registrar abono
        </button>

        <!-- Pagos recepcionados hoy -->
        <div class="card portal-card">
            <h4 class="card-title"><?= icon('credit-card', 16) ?> Pagos recepcionados hoy</h4>
            <?php if (empty($cobrados_hoy)): ?>
                <p class="card-subtitle">Aún no has recepcionado cobros hoy.</p>
            <?php else: ?>
                <div class="oui-list mt-2">
                    <?php foreach ($cobrados_hoy as $p): ?>
                        <div class="oui-list-item">
                            <span class="oui-icon arq-icon-in"><?= icon('dollar-sign', 18) ?></span>
                            <span class="oui-body">
                                <span class="oui-title"><?= esc(trim($p['nombres'] . ' ' . $p['apellidos'])) ?></span>
                                <span class="oui-sub">
                                    <?= esc($p['codigo_credito'] ?? '') ?> · <?= esc(date('h:i a', strtotime($p['fecha_hora']))) ?>
                                    · <?= esc($metodos[$p['metodo']] ?? $p['metodo']) ?>
                                </span>
                            </span>
                            <span class="badge sol-badge-<?= strtolower($p['estado']) ?>"><?= esc($lblEstado[$p['estado']] ?? $p['estado']) ?></span>
                            <span class="oui-meta" style="display:flex; flex-direction:column; gap:4px; align-items:flex-end;">
                                <b class="arq-monto-in"><?= esc($m2) ?> <?= number_format((float) $p['monto'], 2) ?></b>
                                <?php if (in_array($p['estado'], ['REVISION', 'APLICADO'], true)): ?>
                                <span style="display:flex; gap:6px;">
                                    <button type="button" class="btn btn-outline btn-sm" title="Compartir recibo"
                                            data-modal="modal-recibo" data-recibo="<?= esc(json_encode([
                                                'num'     => \App\Models\PagoModel::reciboCode($p, $tenant['nombre']),
                                                'cliente' => trim($p['nombres'] . ' ' . $p['apellidos']),
                                                'credito' => $p['codigo_credito'] ?? '#' . $p['solicitud_id'],
                                                'monto'   => number_format((float) $p['monto'], 2),
                                                'fecha'   => date('d/m/Y h:i a', strtotime($p['fecha_hora'])),
                                                'metodo'  => $metodos[$p['metodo']] ?? $p['metodo'],
                                                'estado'  => $lblEstado[$p['estado']] ?? $p['estado'],
                                                'revision' => $p['estado'] === 'REVISION',
                                                'url'     => base_url($slug . '/portal/cobros/' . $p['id'] . '/recibo'),
                                            ]), 'attr') ?>">
                                        <?= icon('send', 14) ?>
                                    </button>
                                    <a class="btn btn-outline btn-sm" title="Imprimir recibo"
                                       href="<?= base_url($slug . '/portal/cobros/' . $p['id'] . '/recibo?print=1') ?>" target="_blank">
                                        <?= icon('printer', 14) ?>
                                    </a>
                                </span>
                                <?php endif; ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Por cobrar -->
        <div class="card portal-card">
            <div class="card-head" style="display:flex; justify-content:space-between; align-items:center; flex-wrap:wrap; gap:8px;">
                <h4 class="card-title">Por cobrar</h4>
                <form method="get" action="<?= base_url($slug . '/portal/cobros') ?>" style="display:flex; align-items:center; gap:6px;">
                    <?php if ($mora): ?><input type="hidden" name="mora" value="1"><?php endif; ?>
                    <input type="date" name="fecha" value="<?= esc($fSel) ?>"
                           onchange="this.form.submit()"
                           style="padding:6px 8px; border:1px solid var(--border,#e5e7eb); border-radius:8px; font:inherit; font-size:13px;">
                    <?php if ($fSel !== $hoy): ?>
                        <a class="badge badge-soft" href="<?= base_url($slug . '/portal/cobros' . ($mora ? '?mora=1' : '')) ?>">Hoy</a>
                    <?php endif; ?>
                </form>
                <span>
                    <a class="badge <?= !$mora ? 'sol-badge-activo' : 'badge-soft' ?>" href="<?= base_url($slug . '/portal/cobros' . ($fSel !== $hoy ? '?fecha=' . $fSel : '')) ?>">Por cobrar</a>
                    <a class="badge <?= $mora ? 'sol-badge-rechazada' : 'badge-soft' ?>" href="<?= base_url($slug . '/portal/cobros?mora=1' . ($fSel !== $hoy ? '&fecha=' . $fSel : '')) ?>">En mora</a>
                    <a class="badge badge-soft" href="<?= base_url($slug . '/portal/mapa') ?>">Mapa</a>
                </span>
            </div>
            <p class="card-subtitle" style="margin-top:2px;">
                <?= $fSel === $hoy ? 'Cuotas que vencen hoy o antes'
                    : 'Cuotas que vencen hasta el ' . esc(date('d/m/Y', strtotime($fSel))) ?>
                — incluye lo acumulado pendiente.
            </p>
            <?php if (empty($creditos)): ?>
                <p class="card-subtitle">No tenés créditos activos en tu cartera.</p>
            <?php elseif (empty($conCuotas)): ?>
                <p class="card-subtitle"><?= $mora ? 'Sin cuotas vencidas - nadie está en mora.' : 'Nada por cobrar hasta el ' . esc(date('d/m/Y', strtotime($fSel))) . ' — la cartera está al día.' ?></p>
            <?php else: ?>
                <?php foreach ($conCuotas as $cr): ?>
                    <div class="cli-item" style="flex-direction:column; align-items:stretch;">
                        <div class="cli-main" style="cursor:default;">
                            <span class="cli-avatar"><?= esc(mb_strtoupper(mb_substr(trim($cr['nombres']), 0, 1) . mb_substr(trim($cr['apellidos']), 0, 1))) ?></span>
                            <span class="cli-info">
                                <span class="cli-nombre"><?= esc(trim($cr['nombres'] . ' ' . $cr['apellidos'])) ?></span>
                                <span class="cli-sub">
                                    <?= esc($cr['codigo_credito'] ?: $cr['codigo'] ?? '-') ?> · Saldo <?= esc($m2) ?> <?= number_format($cr['saldo'], 0) ?>
                                    · <strong style="color:var(--primary,#30CB9A);">A cobrar <?= esc($m2) ?> <?= number_format($cr['_cobrar'], 0) ?></strong>
                                    <?php if ($cr['en_revision'] > 0): ?>
                                        <span class="badge st-warn"><?= $cr['en_revision'] ?> en revisión</span>
                                    <?php endif; ?>
                                </span>
                            </span>
                        </div>
                        <?php foreach ($cr['_pend'] as $cuo): ?>
                            <form method="post" action="<?= base_url($slug . '/portal/cobros/' . $cr['id'] . '/abonar') ?>"
                                  class="oui-list-item" style="border-top:1px solid var(--border,#e5e7eb);"
                                  onsubmit="return confirm('¿Registrar cobro de <?= esc($m2,'attr') ?>' + (this.monto.value || 0) + '? Queda en revisión hasta que oficina lo confirme.');">
                                <?= csrf_field() ?>
                                <input type="hidden" name="cuota_id" value="<?= (int) $cuo['id'] ?>">
                                <input type="hidden" name="volver" value="<?= '/' . $slug . '/portal/cobros' ?>">
                                <span class="oui-icon<?= $cuo['vencida'] ? ' arq-icon-out' : '' ?>"><?= icon($cuo['vencida'] ? 'alert-circle' : 'clock', 18) ?></span>
                                <span class="oui-body">
                                    <span class="oui-title">Cuota #<?= (int) $cuo['n'] ?> - vence <?= esc($cuo['fecha_vence']) ?></span>
                                    <span class="oui-sub">Pendiente <?= esc($m2) ?> <?= number_format($cuo['pendiente'], 0) ?><?= $cuo['vencida'] ? ' · Vencida' : '' ?></span>
                                </span>
                                <input type="number" name="monto" step="0.01" min="0.01" required
                                       value="<?= esc($cuo['pendiente']) ?>" style="width:88px; padding:6px 8px;">
                                <button class="btn btn-primary btn-sm"><?= icon('check', 14) ?> Cobrar</button>
                            </form>
                        <?php endforeach; ?>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    <?php elseif ($seccion === 'arqueo'): ?>
        <?php
        $fAnt = date('Y-m-d', strtotime($fecha . ' -1 day'));
        $fSig = $fecha < date('Y-m-d') ? date('Y-m-d', strtotime($fecha . ' +1 day')) : null;
        $arqHoy = $resumen['arqueo'] ?? null;
        ?>
        <div class="arq-stack">
        <!-- Navegador de día -->
        <div class="arq-day">
            <a class="arq-day-btn" href="<?= base_url($slug . '/portal/arqueo?fecha=' . $fAnt) ?>" aria-label="Día anterior"><?= icon('chevron-left', 16) ?></a>
            <span class="arq-day-lbl">
                <?= esc(date('d/m/Y', strtotime($fecha))) ?>
                <small><?= $fecha === date('Y-m-d') ? 'Hoy' : esc(date('l', strtotime($fecha))) ?></small>
            </span>
            <?php if ($fSig): ?>
                <a class="arq-day-btn" href="<?= base_url($slug . '/portal/arqueo?fecha=' . $fSig) ?>" aria-label="Día siguiente"><?= icon('chevron-right', 16) ?></a>
            <?php else: ?>
                <span class="arq-day-btn" style="opacity:.35; pointer-events:none;"><?= icon('chevron-right', 16) ?></span>
            <?php endif; ?>
        </div>

        <!-- Hero - efectivo esperado -->
        <div class="card portal-card arq-hero">
            <p class="arq-hero-lbl">Efectivo esperado</p>
            <p class="arq-hero-num"><?= esc($m2) ?> <?= number_format($resumen['esperado'], 2) ?></p>
            <div class="arq-mini">
                <div><small>Inicial</small><b><?= esc($m2) ?> <?= number_format($resumen['inicial'], 0) ?></b></div>
                <div class="arq-in"><small>Cobros</small><b>+ <?= number_format($resumen['cobros'], 0) ?></b></div>
                <div class="arq-out"><small>Desembolsos</small><b>? <?= number_format($resumen['desembolsos'], 0) ?></b></div>
            </div>
            <?php if ($arqHoy): ?>
                <span class="badge <?= $arqHoy['estado'] === 'CUADRADO' ? 'st-ok' : 'st-bad' ?> arq-hero-badge">
                    <?= icon($arqHoy['estado'] === 'CUADRADO' ? 'check-circle' : 'alert-circle', 13) ?>
                    Arqueo <?= esc(strtolower($lblEstado[$arqHoy['estado']] ?? $arqHoy['estado'])) ?>
                    - contado <?= esc($m2) ?> <?= number_format((float) $arqHoy['contado'], 2) ?>
                </span>
            <?php else: ?>
                <span class="badge st-warn arq-hero-badge"><?= icon('clock', 13) ?> Sin cerrar - oficina lo cierra al final del día</span>
            <?php endif; ?>
        </div>

        <!-- Totales del día (detalle en Cobros / Desembolso) -->
        <div class="card portal-card">
            <h4 class="card-title"><?= icon('trending-up', 16) ?> Movimientos del día</h4>
            <div class="oui-list mt-2">
                <a class="oui-list-item" href="<?= base_url($slug . '/portal/cobros') ?>">
                    <span class="oui-icon arq-icon-in"><?= icon('dollar-sign', 18) ?></span>
                    <span class="oui-body">
                        <span class="oui-title">Cobros recepcionados</span>
                        <span class="oui-sub"><?= count($resumen['mov']['cobros'] ?? []) ?> pago(s) - ver detalle en Cobros</span>
                    </span>
                    <span class="oui-meta arq-monto-in">+ <?= esc($m2) ?> <?= number_format((float) ($resumen['mov']['total_cobros'] ?? 0), 2) ?></span>
                </a>
                <a class="oui-list-item" href="<?= base_url($slug . '/portal/desembolso') ?>">
                    <span class="oui-icon arq-icon-out"><?= icon('dollar-sign', 18) ?></span>
                    <span class="oui-body">
                        <span class="oui-title">Desembolsos entregados</span>
                        <span class="oui-sub"><?= count($resumen['mov']['desembolsos'] ?? []) ?> entrega(s) - ver detalle en Desembolso</span>
                    </span>
                    <span class="oui-meta arq-monto-out">? <?= esc($m2) ?> <?= number_format((float) ($resumen['mov']['total_desembolsos'] ?? 0), 2) ?></span>
                </a>
                <a class="oui-list-item" href="<?= base_url($slug . '/portal/desembolso') ?>">
                    <span class="oui-icon"><?= icon('briefcase', 18) ?></span>
                    <span class="oui-body">
                        <span class="oui-title">Por desembolsar</span>
                        <span class="oui-sub">Dinero asignado pendiente de entregar</span>
                    </span>
                    <span class="oui-meta" style="font-weight:800; color:#B45309;"><?= esc($m2) ?> <?= number_format($por_desembolsar ?? 0, 2) ?></span>
                </a>
            </div>
        </div>

        <!-- Arqueos anteriores -->
        <?php if (!empty($arqueos)): ?>
            <div class="card portal-card">
                <h4 class="card-title"><?= icon('clipboard', 16) ?> Arqueos anteriores</h4>
                <div class="oui-list mt-2">
                    <?php foreach ($arqueos as $a): ?>
                        <div class="oui-list-item">
                            <span class="oui-icon"><?= icon('clipboard', 18) ?></span>
                            <span class="oui-body">
                                <span class="oui-title"><?= esc(date('d/m/Y', strtotime($a['fecha']))) ?></span>
                                <span class="oui-sub">Esperado <?= esc($m2) ?> <?= number_format((float) $a['esperado'], 2) ?> · Contado <?= esc($m2) ?> <?= number_format((float) $a['contado'], 2) ?></span>
                            </span>
                            <span class="badge <?= $a['estado'] === 'CUADRADO' ? 'st-ok' : 'st-bad' ?>">
                                <?= $a['estado'] === 'CUADRADO' ? 'Cuadrado' : 'dif. ' . number_format((float) $a['diferencia'], 2) ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
        </div><!-- /arq-stack -->
    <?php else: // recuperacion ?>
        <p class="card-subtitle">Sin clientes en seguimiento. Pronto se cargará la cartera a cobrar.</p>
    <?php endif; ?>
<?php if (!in_array($seccion, ['arqueo', 'cobros'], true)): ?></div><?php endif; ?>

<?= isset($pager) ? $pager->links('default', 'cfsi') : '' ?>

<?php if ($seccion === 'cobros'): ?>
<!-- Modal: registrar abono -->
<div class="modal-overlay" id="modal-abono" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('dollar-sign', 17) ?> Registrar abono</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <form method="post" action="<?= base_url($slug . '/portal/cobros/0/abonar') ?>"
              data-action-tpl="<?= base_url($slug . '/portal/cobros/__ID__/abonar') ?>">
            <?= csrf_field() ?>
            <input type="hidden" name="volver" value="<?= '/' . $slug . '/portal/cobros' ?>">
            <div class="modal-body">
                <div class="form-group form-full">
                    <label>Crédito</label>
                    <select name="solicitud_id" required>
                        <option value="">- Seleccioné el crédito -</option>
                        <?php foreach ($creditos ?? [] as $cr): ?>
                            <option value="<?= (int) $cr['id'] ?>" data-pendiente="<?= esc($cr['cuota_sugerida'] ?? '') ?>">
                                <?= esc(trim($cr['nombres'] . ' ' . $cr['apellidos'])) ?> · <?= esc($cr['codigo_credito'] ?: '#' . $cr['id']) ?> · Saldo <?= esc($m2) ?> <?= number_format((float) $cr['saldo'], 0) ?>
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
                    El cobro queda <strong>en revisión</strong> hasta que oficina confirme que recibió el dinero.
                </p>
            </div>
            <div class="modal-foot">
                <button type="button" class="btn" data-close>Cancelar</button>
                <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Registrar</button>
            </div>
        </form>
    </div>
</div>

<!-- Modal: voucher del recibo -->
<div class="modal-overlay" id="modal-recibo" hidden>
    <div class="modal-box">
        <div class="modal-head">
            <h4><?= icon('file-text', 17) ?> Recibo</h4>
            <button type="button" class="modal-close" data-close><?= icon('x', 18) ?></button>
        </div>
        <div class="modal-body" style="display:block;">
            <div class="rec-preview" id="rec-preview">
                <div class="rec-head">
                    <b id="rec-emp"><?= esc($tenant['nombre']) ?></b>
                    <small>RECIBO DE PAGO</small>
                    <b class="rec-num" id="rec-num"></b>
                </div>
                <div class="rec-rows">
                    <div><span>Cliente</span><b id="rec-cliente"></b></div>
                    <div><span>Crédito</span><b id="rec-credito"></b></div>
                    <div><span>Fecha</span><b id="rec-fecha"></b></div>
                    <div><span>Método</span><b id="rec-metodo"></b></div>
                    <div><span>Estado</span><b id="rec-estado"></b></div>
                </div>
                <div class="rec-monto"><?= esc($m2) ?> <span id="rec-monto"></span></div>
                <div class="rec-nota" id="rec-nota" hidden>
                    PAGO EN REVISIÓN - este comprobante no confirma el abono. Se aplica al
                    plan de cuotas cuando oficina lo valide en cuentas bancarias o caja.
                </div>
            </div>
        </div>
        <div class="modal-foot">
            <button type="button" class="btn" data-close>Cerrar</button>
            <button type="button" class="btn btn-outline" id="rec-imprimir"><?= icon('printer', 14) ?> Imprimir</button>
            <button type="button" class="btn btn-primary" id="rec-compartir"><?= icon('send', 14) ?> Compartir</button>
        </div>
    </div>
</div>
<?php endif; ?>

<?= $this->endSection() ?>
