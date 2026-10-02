<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Leads</h3>
        <p class="page-sub">Solicitudes de alta desde la landing — últimos <?= count($leads) ?> (máx. 200). Los de la calculadora traen plan estimado y código partner.</p>
    </div>
</div>

<div class="chip-bar">
    <a class="chip <?= $estado === '' ? 'on' : '' ?>" href="<?= base_url('admin/leads') ?>">
        Todos <span class="n"><?= array_sum($conteos) ?></span>
    </a>
    <?php
    $chipTag = ['PENDIENTE' => 'Pendientes', 'CONTACTADO' => 'Contactados',
                'CONTRATADO' => 'Contratados', 'RECHAZADO' => 'Rechazados'];
    foreach ($chipTag as $e => $lbl): ?>
        <a class="chip <?= $estado === $e ? 'on' : '' ?>"
           href="<?= base_url('admin/leads?estado=' . $e) ?>">
            <?= $lbl ?> <span class="n"><?= $conteos[$e] ?? 0 ?></span>
        </a>
    <?php endforeach; ?>
</div>

<div class="card">
    <table class="table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Lead</th>
                <th>Contacto</th>
                <th>Plan estimado</th>
                <th>Estado</th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php if (empty($leads)): ?>
                <tr><td colspan="6" class="text-muted">Sin solicitudes todavía.</td></tr>
            <?php endif; ?>
            <?php foreach ($leads as $l): ?>
                <?php $wa = preg_replace('/\D/', '', (string) $l['telefono']); ?>
                <tr>
                    <td class="text-muted" style="white-space:nowrap;"><?= esc(date('d/m/Y H:i', strtotime($l['created_at']))) ?></td>
                    <td>
                        <span class="fw-bold"><?= esc($l['nombre']) ?></span>
                        <?php if ($l['negocio'] && $l['negocio'] !== $l['nombre']): ?>
                            <br><small class="text-muted"><?= esc($l['negocio']) ?></small>
                        <?php endif; ?>
                        <?php if (!empty($l['codigo'])): ?>
                            <br><span class="tag tag-info"><?= esc($l['codigo']) ?></span>
                        <?php endif; ?>
                    </td>
                    <td style="white-space:nowrap;">
                        <?= esc($l['telefono']) ?>
                        <?php if ($wa): ?>
                            <a class="btn btn-sm" href="https://wa.me/<?= $wa ?>?text=<?= rawurlencode('Hola ' . $l['nombre'] . ', te contacto de Contamos por tu solicitud ' . ($l['codigo'] ?: '') . ' —') ?>"
                               target="_blank" rel="noopener" title="Abrir WhatsApp">
                                <?= icon('whatsapp', 13) ?>
                            </a>
                        <?php endif; ?>
                        <?php if (!empty($l['correo'])): ?>
                            <br><small class="text-muted"><?= esc($l['correo']) ?></small>
                        <?php endif; ?>
                    </td>
                    <td>
                        <?php if ($l['det']): ?>
                            <span class="fw-bold">$<?= number_format((float) ($l['plan_estimado'] ?? 0), 2) ?></span> <small class="text-muted">/mes</small>
                            <br><small class="text-muted">
                                <?= (int) $l['det']['usuarios'] ?> usr · <?= (int) $l['det']['clientes'] ?> cli ·
                                <?= (int) $l['det']['creditos'] ?> cré · <?= (int) $l['det']['empleados'] ?> emp
                            </small>
                        <?php else: ?>
                            <span class="text-muted">Sin calculadora</span>
                        <?php endif; ?>
                    </td>
                    <td>
                        <span class="tag <?= match ($l['estado']) {
                            'CONTACTADO' => 'tag-info',
                            'CONTRATADO' => 'tag-activo',
                            'RECHAZADO'  => 'tag-inactivo',
                            default      => 'tag-warn',
                        } ?>"><?= esc($l['estado']) ?></span>
                    </td>
                    <td style="white-space:nowrap;">
                        <?php if (in_array($l['estado'], ['PENDIENTE', 'CONTACTADO'], true)): ?>
                            <button type="button" class="btn btn-sm btn-primary js-contactar"
                                    data-id="<?= $l['id'] ?>"
                                    data-nombre="<?= esc($l['nombre']) ?>"
                                    data-det="<?= esc($l['detalle'] ?: '') ?>"
                                    data-pid="<?= (int) ($l['plan_id'] ?? 0) ?>">
                                <?= icon('phone', 13) ?> <?= $l['estado'] === 'CONTACTADO' ? 'Ajustar' : 'Contactar' ?>
                            </button>
                            <?php if ($l['estado'] === 'CONTACTADO'): ?>
                                <button type="button" class="btn btn-sm js-contrato"
                                        data-id="<?= $l['id'] ?>"
                                        data-nombre="<?= esc($l['nombre']) ?>"
                                        data-monto="<?= (float) ($l['plan_estimado'] ?? 0) ?>">
                                    <?= icon('file-text', 13) ?> Contrato
                                </button>
                                <form method="post" action="<?= base_url('admin/leads/' . $l['id'] . '/estado') ?>" style="display:inline">
                                    <?= csrf_field() ?>
                                    <button class="btn btn-sm" type="submit" title="Devolver a pendiente">
                                        <?= icon('refresh-cw', 13) ?>
                                    </button>
                                </form>
                            <?php endif; ?>
                            <form method="post" action="<?= base_url('admin/leads/' . $l['id'] . '/rechazar') ?>" style="display:inline"
                                  onsubmit="return confirm('¿Rechazar a <?= esc($l['nombre'], 'js') ?>? El lead queda descartado.')">
                                <?= csrf_field() ?>
                                <button class="btn btn-sm btn-danger" type="submit" title="Rechazar lead">
                                    <?= icon('x', 13) ?>
                                </button>
                            </form>
                        <?php elseif ($l['estado'] === 'CONTRATADO' && isset($contratos[$l['id']])): ?>
                            <a class="btn btn-sm" href="<?= base_url('admin/contratos/' . $contratos[$l['id']]) ?>">
                                <?= icon('file-text', 13) ?> Ver contrato
                            </a>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<!-- Modal: contactar — ajustar consumo real + plan → propuesta con precio -->
<div class="moverlay" id="modal-contactar" hidden onclick="if (event.target === this) this.hidden = true">
    <div class="modal">
        <div class="modal-head">
            <b>Contactando a <span id="mc-nombre"></span></b>
            <button type="button" class="btn btn-sm" onclick="document.getElementById('modal-contactar').hidden = true">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin:0 0 14px;">Ajustá el consumo real del cliente y elegí el plan — el precio se recalcula en vivo.</p>
            <form method="post" id="mc-form" data-base="<?= base_url('admin/leads') ?>">
                <?= csrf_field() ?>
                <?php
                $mcCampos = [
                    'usuarios'  => ['Usuarios del sistema', 1, 50, 1],
                    'clientes'  => ['Clientes en cartera', 0, 2000, 10],
                    'creditos'  => ['Créditos activos / mes', 0, 1000, 10],
                    'empleados' => ['Gestores / empleados', 0, 200, 1],
                ];
                foreach ($mcCampos as $k => [$lbl, $min, $max, $step]): ?>
                    <div class="fgroup" style="margin-bottom:10px;">
                        <label><?= $lbl ?> — <b id="mc-v-<?= $k ?>">0</b></label>
                        <input type="range" class="mc-slider" name="<?= $k ?>" id="mc-<?= $k ?>"
                               min="<?= $min ?>" max="<?= $max ?>" step="<?= $step ?>" value="<?= $min ?>">
                    </div>
                <?php endforeach; ?>
                <div class="fgroup" style="margin-bottom:12px;">
                    <label>Plan base</label>
                    <select class="inp" name="plan_id" id="mc-plan">
                        <?php foreach ($planes as $p): ?>
                            <option value="<?= $p['id'] ?>"
                                    data-p="<?= $p['precio_mensual'] ?>" data-u="<?= $p['max_usuarios'] ?>"
                                    data-e="<?= $p['max_empleados'] ?>" data-c="<?= $p['max_creditos_activos'] ?>"
                                    <?= $p['slug'] === 'basico' ? 'selected' : '' ?>>
                                <?= esc($p['nombre']) ?> — USD <?= number_format((float) $p['precio_mensual'], 2) ?>/mes
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mc-est">Propuesta: <b id="mc-est">USD 0.00</b> <span class="text-muted">/mes</span></div>
                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:14px;">
                    <button type="button" class="btn" onclick="document.getElementById('modal-contactar').hidden = true">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><?= icon('check', 14) ?> Guardar propuesta</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal: generar contrato — condiciones pactadas con el cliente -->
<div class="moverlay" id="modal-contrato" hidden onclick="if (event.target === this) this.hidden = true">
    <div class="modal">
        <div class="modal-head">
            <b>Contrato de <span id="ct-nombre"></span></b>
            <button type="button" class="btn btn-sm" onclick="document.getElementById('modal-contrato').hidden = true">✕</button>
        </div>
        <div class="modal-body">
            <p class="text-muted" style="margin:0 0 14px;">Condiciones de pago pactadas — quedan impresas en los T&C del contrato.</p>
            <form method="post" id="ct-form" data-base="<?= base_url('admin/leads') ?>">
                <?= csrf_field() ?>
                <div class="frow">
                    <div class="fgroup">
                        <label>Mensualidad (USD)</label>
                        <input class="inp" type="number" name="monto" id="ct-monto" step="0.01" min="0" required>
                    </div>
                    <div class="fgroup">
                        <label>Inicio de vigencia</label>
                        <input class="inp" type="date" name="fecha_inicio" value="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
                <div class="frow">
                    <div class="fgroup">
                        <label>Día de pago (cada mes)</label>
                        <select class="inp" name="dia_pago">
                            <option value="5">Día 5</option>
                            <option value="10">Día 10</option>
                        </select>
                    </div>
                    <div class="fgroup">
                        <label>Días de gracia</label>
                        <input class="inp" type="number" name="gracia_dias" value="4" min="0" max="30">
                    </div>
                </div>
                <div class="fgroup">
                    <label>Cuenta bancaria para los pagos</label>
                    <input class="inp" type="text" name="cuenta_bancaria" maxlength="200"
                           placeholder="Banco · tipo de cuenta · número · titular">
                </div>
                <div style="display:flex; gap:8px; justify-content:flex-end; margin-top:14px;">
                    <button type="button" class="btn" onclick="document.getElementById('modal-contrato').hidden = true">Cancelar</button>
                    <button type="submit" class="btn btn-primary"><?= icon('file-text', 14) ?> Generar contrato</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script src="<?= v_asset('js/admin-leads.js') ?>"></script>

<?= $this->endSection() ?>
