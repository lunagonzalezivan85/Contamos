<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>
<?php $m2 = $mon ?? ($tenant['moneda'] ?? 'C$'); ?>


<div class="page-head">
    <h3 class="page-title">Configuración</h3>
    <p class="page-subtitle">Datos generales de su empresa.</p>
</div>

<?php if (!empty($cobro['plan'])): ?>
<div class="card mb-4">
    <h4 class="card-title">Mi plan y consumo</h4>
    <p class="card-subtitle">
        Plan <?= esc($cobro['plan']) ?> · $<?= number_format((float) $cobro['precio'], 2) ?> <?= esc($cobro['moneda']) ?>/mes
        · próximo corte: <b><?= !empty($planEstado['proximo']) ? esc(date('d/m/Y', strtotime($planEstado['proximo']))) : '—' ?></b>
    </p>

    <div class="consumo-rows">
        <div class="consumo-row consumo-head">
            <span>Concepto</span><span>Uso actual</span><span>Incluido</span><span>Extra</span><span>Cargo</span>
        </div>
        <?php foreach ($cobro['recursos'] as $r): ?>
        <div class="consumo-row">
            <span><?= esc($r['label']) ?></span>
            <span><?= (int) $r['uso'] ?></span>
            <span><?= $r['incluido'] < 0 ? '∞' : (int) $r['incluido'] ?></span>
            <span class="<?= $r['extra'] > 0 ? 'consumo-over' : 'text-muted' ?>"><?= $r['extra'] > 0 ? '+' . (int) $r['extra'] : '—' ?></span>
            <span><?= $r['monto'] > 0 ? '$' . number_format($r['monto'], 2) : '—' ?></span>
        </div>
        <?php endforeach; ?>
        <div class="consumo-row consumo-total">
            <span>Estimado del ciclo (plan + sobreconsumo)</span><span></span><span></span><span></span>
            <span>$<?= number_format((float) $cobro['total'], 2) ?> <?= esc($cobro['moneda']) ?></span>
        </div>
    </div>
    <p class="card-subtitle mt-4">
        Extras por unidad: usuario $3.00 · empleado $1.00 · crédito activo $0.15 · cliente $0.20.
        El cobro se confirma sobre el consumo al día de corte de la factura.
    </p>
</div>
<?php endif; ?>

<div class="card">
    <h4 class="card-title">Datos de la empresa</h4>
    <p class="card-subtitle">Esta información aparece en reportes y documentos.</p>

    <form action="<?= base_url('configuracion') ?>" method="post" class="form mt-4" enctype="multipart/form-data">
        <?= csrf_field() ?>

        <!-- Logo - zona de arrastrar y soltar -->
        <div class="logo-dropzone" id="logo-dropzone">
            <input type="file" id="logo" name="logo" accept="image/png,image/jpeg,image/webp,image/gif" hidden>
            <div class="logo-dz-inner" id="logo-dz-inner">
                <?php if (!empty($tenant['logo'])): ?>
                    <img class="logo-dz-img" src="<?= base_url('public/uploads/logos/' . $tenant['logo']) ?>" alt="Logo">
                <?php else: ?>
                    <div class="logo-dz-icon"><?= icon('image', 34) ?></div>
                <?php endif; ?>
                <div class="logo-dz-text">
                    <strong>Arrastra tu logo aquí</strong> o haz clic para seleccionar
                    <span>PNG, JPG, WEBP o GIF · máx 2 MB · se usa en el sidebar y vouchers</span>
                </div>
            </div>
        </div>
        <div class="logo-dz-error" id="logo-dz-error" hidden></div>

        <div class="form-grid mt-4">
            <div class="form-group">
                <label for="nombre">Nombre comercial *</label>
                <input type="text" id="nombre" name="nombre"
                       value="<?= esc(old('nombre', $tenant['nombre'] ?? '')) ?>" required>
            </div>
            <div class="form-group">
                <label for="razon_social">Razón social</label>
                <input type="text" id="razon_social" name="razon_social"
                       value="<?= esc(old('razon_social', $tenant['razon_social'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="ruc">RUC</label>
                <input type="text" id="ruc" name="ruc" maxlength="30"
                       value="<?= esc(old('ruc', $tenant['ruc'] ?? '')) ?>"
                       placeholder="J-0000000000000">
            </div>
            <div class="form-group">
                <label for="conami_registro">Registro CONAMI</label>
                <input type="text" id="conami_registro" name="conami_registro" maxlength="40"
                       value="<?= esc(old('conami_registro', $tenant['conami_registro'] ?? '')) ?>"
                       placeholder="Nº de registro ante CONAMI">
            </div>
            <div class="form-group">
                <label for="email">Correo electrónico</label>
                <input type="email" id="email" name="email"
                       value="<?= esc(old('email', $tenant['email'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono"
                       value="<?= esc(old('telefono', $tenant['telefono'] ?? '')) ?>">
            </div>
            <div class="form-group form-full">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion"
                       value="<?= esc(old('direccion', $tenant['direccion'] ?? '')) ?>">
            </div>
            <div class="form-group form-full">
                <label for="lema">Lema de la empresa</label>
                <input type="text" id="lema" name="lema"
                       placeholder="Ej: Impulsando tu futuro"
                       value="<?= esc(old('lema', $tenant['lema'] ?? '')) ?>">
            </div>
            <div class="form-group form-full">
                <label for="voucher_footer">Pie de página de vouchers/recibos</label>
                <input type="text" id="voucher_footer" name="voucher_footer"
                       placeholder="Ej: Gracias por su pago. Conserve este recibo."
                       value="<?= esc(old('voucher_footer', $tenant['voucher_footer'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="horario">Horario de trabajo</label>
                <input type="text" id="horario" name="horario"
                       placeholder="Ej: Lun-Vie 8:00-17:00, Sáb 8:00-12:00"
                       value="<?= esc(old('horario', $tenant['horario'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="hora_inicio">Uso del sistema - desde</label>
                <input type="time" id="hora_inicio" name="hora_inicio"
                       value="<?= esc(old('hora_inicio', substr((string)($tenant['hora_inicio'] ?? ''), 0, 5))) ?>">
            </div>
            <div class="form-group">
                <label for="hora_fin">Uso del sistema - hasta</label>
                <input type="time" id="hora_fin" name="hora_fin"
                       value="<?= esc(old('hora_fin', substr((string)($tenant['hora_fin'] ?? ''), 0, 5))) ?>">
            </div>
            <div class="form-group">
                <label for="moneda">Moneda</label>
                <select id="moneda" name="moneda">
                    <?php $moneda = old('moneda', $tenant['moneda'] ?? 'C$'); ?>
                    <option value="C$"   <?= $moneda === 'C$'  ? 'selected' : '' ?>>C$ - Córdoba nicaragüense</option>
                    <option value="US$"  <?= $moneda === 'US$' ? 'selected' : '' ?>>USD - Dólar estadounidense</option>
                </select>
            </div>
        </div>
        <p class="card-subtitle">Los gestores solo podrán usar el sistema dentro del rango "desde-hasta". Déjelos vacíos para acceso sin restricción.</p>

        <h4 class="card-title mt-4">Información institucional</h4>
        <p class="card-subtitle">Se muestra en la landing del portal (/{slug}/portal).</p>
        <div class="form-grid">
            <div class="form-group form-full">
                <label for="quienes_somos">Quiénes somos</label>
                <textarea id="quienes_somos" name="quienes_somos" rows="3"
                          placeholder="Breve descripción de la empresa"><?= esc(old('quienes_somos', $tenant['quienes_somos'] ?? '')) ?></textarea>
            </div>
            <div class="form-group form-full">
                <label for="mision">Misión</label>
                <textarea id="mision" name="mision" rows="2"
                          placeholder="Nuestra misión"><?= esc(old('mision', $tenant['mision'] ?? '')) ?></textarea>
            </div>
            <div class="form-group form-full">
                <label for="vision">Visión</label>
                <textarea id="vision" name="vision" rows="2"
                          placeholder="Nuestra visión"><?= esc(old('vision', $tenant['vision'] ?? '')) ?></textarea>
            </div>
            <div class="form-group form-full">
                <label for="valores">Valores</label>
                <textarea id="valores" name="valores" rows="2"
                          placeholder="Ej: Transparencia, Compromiso, Responsabilidad"><?= esc(old('valores', $tenant['valores'] ?? '')) ?></textarea>
            </div>
        </div>

        <h4 class="card-title mt-4">Contacto principal</h4>
        <div class="form-grid">
            <div class="form-group">
                <label for="contacto_nombre">Nombre del contacto</label>
                <input type="text" id="contacto_nombre" name="contacto_nombre"
                       value="<?= esc(old('contacto_nombre', $tenant['contacto_nombre'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="contacto_cargo">Cargo</label>
                <input type="text" id="contacto_cargo" name="contacto_cargo"
                       value="<?= esc(old('contacto_cargo', $tenant['contacto_cargo'] ?? '')) ?>">
            </div>
        </div>

        <h4 class="card-title mt-4">Parámetros de crédito</h4>
        <p class="card-subtitle">Valores por defecto y límites que aplican a las solicitudes de crédito.</p>
        <div class="form-grid">
            <div class="form-group">
                <label for="tasa_interes">Tasa de interés mensual (%)</label>
                <input type="number" id="tasa_interes" name="tasa_interes" min="0" max="100" step="0.25"
                       value="<?= esc(old('tasa_interes', $tenant['tasa_interes'] ?? 3)) ?>">
            </div>
            <div class="form-group">
                <label for="tipo_calculo">Tipo de cálculo por defecto</label>
                <select id="tipo_calculo" name="tipo_calculo">
                    <?php $tc = old('tipo_calculo', $tenant['tipo_calculo'] ?? 'FLAT'); ?>
                    <?php foreach ($tiposCalc ?? [] as $k => $lbl): ?>
                        <option value="<?= esc($k) ?>" <?= $tc === $k ? 'selected' : '' ?>><?= esc($lbl) ?></option>
                    <?php endforeach; ?>
                </select>
                <small class="form-hint">Se preselecciona al aprobar un crédito; puede cambiarse en cada aprobación.</small>
            </div>
            <div class="form-group">
                <label for="plazo_meses_max">Plazo máximo (meses)</label>
                <input type="number" id="plazo_meses_max" name="plazo_meses_max" min="1" max="360" step="1"
                       value="<?= esc(old('plazo_meses_max', $tenant['plazo_meses_max'] ?? 24)) ?>">
            </div>
            <div class="form-group">
                <label for="mora_diaria_pct">Mora diaria sobre el pendiente (%)</label>
                <input type="number" id="mora_diaria_pct" name="mora_diaria_pct" min="0" max="5" step="0.01"
                       value="<?= esc(old('mora_diaria_pct', $tenant['mora_diaria_pct'] ?? 0)) ?>">
                <small class="form-hint">Se cobra por día sobre lo pendiente de la cuota, solo después de vencida. 0 = sin mora.</small>
            </div>
            <div class="form-group">
                <label for="pronto_pago_pct">Descuento por pronto pago (%)</label>
                <input type="number" id="pronto_pago_pct" name="pronto_pago_pct" min="0" max="100" step="0.5"
                       value="<?= esc(old('pronto_pago_pct', $tenant['pronto_pago_pct'] ?? 0)) ?>">
                <small class="form-hint">Descuento sobre el interés pendiente si el cliente liquida el crédito de una vez. 0 = desactivado.</small>
            </div>
            <div class="form-group">
                <label for="comision_pct">Comisión sobre el monto (%)</label>
                <input type="number" id="comision_pct" name="comision_pct" min="0" max="50" step="0.25"
                       value="<?= esc(old('comision_pct', $tenant['comision_pct'] ?? 0)) ?>">
                <small class="form-hint">Se sugiere como comisión al aprobar/desembolsar (opcional por crédito, se descuenta de la entrega). 0 = sin comisión.</small>
            </div>
            <div class="form-group">
                <label for="seguro_pct">Seguro sobre el monto (%)</label>
                <input type="number" id="seguro_pct" name="seguro_pct" min="0" max="50" step="0.25"
                       value="<?= esc(old('seguro_pct', $tenant['seguro_pct'] ?? 0)) ?>">
                <small class="form-hint">Se sugiere como seguro al aprobar/desembolsar (opcional por crédito, se descuenta de la entrega). 0 = sin seguro.</small>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar cambios</button>
        </div>
    </form>
</div>

<!-- Reportes - categorías del índice /reportes -->
<div class="card mt-4">
    <h4 class="card-title">Reportes</h4>
    <p class="card-subtitle">Organice el índice de reportes en categorías propias de su empresa.</p>
    <div class="mt-4">
        <?= view('partials/list', ['items' => [[
            'icono'     => 'bar-chart',
            'titulo'    => 'Categorías de reportes',
            'subtitulo' => 'Crear categorías y asignar cada reporte del índice /reportes',
            'url'       => '/configuracion/reportes',
        ]]]) ?>
    </div>
</div>

<!-- Plantillas - lista One UI clickeable ? editor -->
<div class="card mt-4">
    <h4 class="card-title">Plantillas</h4>
    <p class="card-subtitle">Documentos que se generan en el sistema (vouchers, contratos, pagarés).</p>

    <div class="mt-4">
        <?= view('partials/list', ['items' => array_map(fn($p) => [
            'icono'     => 'file-text',
            'titulo'    => $p['nombre'],
            'subtitulo' => $p['descripcion'] ?? '',
            'url'       => '/configuracion/plantilla/' . $p['id'],
        ], $plantillas ?? [])]) ?>
    </div>
</div>

<?= $this->endSection() ?>
