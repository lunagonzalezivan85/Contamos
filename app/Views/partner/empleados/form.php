<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<?php $e = $empleado ?? []; $esEditar = !empty($e); ?>
<?php $cargos = ['Caja','Gestor','Gerente','Supervisor','Cobrador','Analista de crédito','Contador','Auxiliar administrativo','Recepcionista','Servicio al cliente']; ?>

<?= view('partials/detail_hero', [
    'titulo'    => $esEditar ? 'Editar empleado' : 'Nuevo empleado',
    'subtitulo' => $esEditar ? trim(($e['nombres'] ?? '') . ' ' . ($e['apellidos'] ?? '')) : 'Socios → Empleados',
    'icono'     => 'user-check',
    'volver'    => '/socios/empleados',
]) ?>

<div class="card">
    <form action="<?= base_url(ltrim($accion, '/')) ?>" method="post" class="form">
        <?= csrf_field() ?>

        <div class="form-grid">
            <div class="form-group">
                <label for="nombres">Nombres *</label>
                <input type="text" id="nombres" name="nombres" required
                       value="<?= esc(old('nombres', $e['nombres'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="apellidos">Apellidos *</label>
                <input type="text" id="apellidos" name="apellidos" required
                       value="<?= esc(old('apellidos', $e['apellidos'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>Género</label>
                <div class="gen-switch" id="gen-switch" data-v="<?= esc(old('genero', $e['genero'] ?? 'M')) ?>">
                    <input type="hidden" name="genero" id="genero" value="<?= esc(old('genero', $e['genero'] ?? 'M')) ?>">
                    <span class="gen-slider"></span>
                    <button type="button" class="gen-opt" data-v="M">Hombre</button>
                    <button type="button" class="gen-opt" data-v="F">Mujer</button>
                </div>
            </div>
            <div class="form-group">
                <label for="cedula">Cédula</label>
                <input type="text" id="cedula" name="cedula"
                       value="<?= esc(old('cedula', $e['cedula'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono"
                       value="<?= esc(old('telefono', $e['telefono'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email"
                       value="<?= esc(old('email', $e['email'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="cargo">Cargo</label>
                <div class="cargo-picker">
                    <input type="hidden" name="cargo" id="cargo" value="<?= esc(old('cargo', $e['cargo'] ?? '')) ?>">
                    <button type="button" class="cargo-trigger" id="cargo-trigger">
                        <span id="cargo-text" class="cargo-text"><?= esc(old('cargo', $e['cargo'] ?? 'Seleccionar cargo…')) ?></span>
                        <?= icon('chevron-down', 16) ?>
                    </button>
                    <div class="cargo-panel" id="cargo-panel" hidden>
                        <div class="cargo-search">
                            <?= icon('search', 15) ?>
                            <input type="text" id="cargo-search" placeholder="Buscar cargo…">
                        </div>
                        <div class="cargo-list" id="cargo-list">
                            <?php foreach ($cargos as $c): ?>
                                <button type="button" class="cargo-opt" data-v="<?= esc($c) ?>"><?= esc($c) ?></button>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <div class="form-group">
                <label for="carnet">Carnet de empleado</label>
                <input type="text" id="carnet" value="<?= esc($e['carnet'] ?? $carnet_sugerido ?? '—') ?>"
                       disabled title="Se genera automáticamente">
            </div>
            <div class="form-group">
                <label for="pin">PIN de acceso a la plataforma</label>
                <div class="pin-wrap">
                    <input type="text" id="pin" name="pin" maxlength="4" inputmode="numeric"
                           placeholder="4 dígitos" value="<?= esc(old('pin', $e['pin'] ?? '')) ?>">
                    <button type="button" class="btn btn-outline btn-sm" id="btn-gen-pin" title="Generar PIN">
                        <?= icon('refresh-cw', 14) ?> Generar
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label for="ruta">Ruta asignada</label>
                <input type="text" id="ruta" name="ruta" placeholder="Ej: Ruta Norte, Centro, R-05"
                       value="<?= esc(old('ruta', $e['ruta'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="fecha_nac">Fecha de nacimiento</label>
                <input type="date" id="fecha_nac" name="fecha_nac"
                       value="<?= esc(old('fecha_nac', $e['fecha_nac'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="fecha_ingreso">Fecha de ingreso</label>
                <input type="date" id="fecha_ingreso" name="fecha_ingreso"
                       value="<?= esc(old('fecha_ingreso', $e['fecha_ingreso'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="estado">Estado</label>
                <select id="estado" name="estado">
                    <?php $est = old('estado', $e['estado'] ?? 'ACTIVO'); ?>
                    <option value="ACTIVO"   <?= $est === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                    <option value="INACTIVO" <?= $est === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
                </select>
            </div>
            <div class="form-group">
                <label for="puede_desembolsar">Permiso de caja</label>
                <label class="chk">
                    <?php $puede = old('puede_desembolsar', $e['puede_desembolsar'] ?? 1); ?>
                    <input type="checkbox" id="puede_desembolsar" name="puede_desembolsar" value="1" <?= $puede ? 'checked' : '' ?>>
                    <span>Puede entregar desembolsos (portal y app)</span>
                </label>
            </div>
            <div class="form-group form-full">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion"
                       value="<?= esc(old('direccion', $e['direccion'] ?? '')) ?>">
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esEditar ? 'Guardar cambios' : 'Registrar empleado' ?></button>
            <a href="<?= base_url('socios/empleados') ?>" class="btn">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
