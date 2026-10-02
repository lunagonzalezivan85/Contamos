<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<?php $c = $cliente ?? []; $esEditar = !empty($c); $bloq = $bloqueado ?? false; ?>

<?= view('partials/detail_hero', [
    'titulo'    => $esEditar ? 'Editar cliente' : 'Nuevo cliente',
    'subtitulo' => $esEditar ? trim(($c['nombres'] ?? '') . ' ' . ($c['apellidos'] ?? '')) : 'Socios → Clientes',
    'icono'     => 'user',
    'volver'    => '/socios/clientes',
]) ?>

<div class="card">
    <form action="<?= base_url(ltrim($accion, '/')) ?>" method="post" class="form">
        <?= csrf_field() ?>

        <?php if ($bloq): ?>
            <p class="card-subtitle" style="margin-bottom:16px;">
                Este cliente tiene un crédito vigente — el nombre y la cédula no se pueden editar.
            </p>
        <?php endif; ?>
        <div class="form-grid">
            <div class="form-group">
                <label for="codigo">Código de cliente</label>
                <input type="text" id="codigo" value="<?= esc($c['codigo'] ?? $codigo_sugerido ?? '—') ?>"
                       disabled title="Se genera automáticamente (C-INICIALES-0001)">
            </div>
            <div class="form-group">
                <label for="nombres">Nombres *</label>
                <input type="text" id="nombres" name="nombres" required <?= $bloq ? 'readonly' : '' ?>
                       value="<?= esc(old('nombres', $c['nombres'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="apellidos">Apellidos *</label>
                <input type="text" id="apellidos" name="apellidos" required <?= $bloq ? 'readonly' : '' ?>
                       value="<?= esc(old('apellidos', $c['apellidos'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label>Género</label>
                <div class="gen-switch" id="gen-switch" data-v="<?= esc(old('genero', $c['genero'] ?? 'M')) ?>">
                    <input type="hidden" name="genero" id="genero" value="<?= esc(old('genero', $c['genero'] ?? 'M')) ?>">
                    <span class="gen-slider"></span>
                    <button type="button" class="gen-opt" data-v="M">Hombre</button>
                    <button type="button" class="gen-opt" data-v="F">Mujer</button>
                </div>
            </div>
            <div class="form-group">
                <label for="cedula">Cédula</label>
                <input type="text" id="cedula" name="cedula" <?= $bloq ? 'readonly' : '' ?>
                       value="<?= esc(old('cedula', $c['cedula'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="telefono">Teléfono</label>
                <input type="text" id="telefono" name="telefono"
                       value="<?= esc(old('telefono', $c['telefono'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="email">Correo</label>
                <input type="email" id="email" name="email"
                       value="<?= esc(old('email', $c['email'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="fecha_nac">Fecha de nacimiento</label>
                <input type="date" id="fecha_nac" name="fecha_nac"
                       value="<?= esc(old('fecha_nac', $c['fecha_nac'] ?? '')) ?>">
            </div>
            <div class="form-group">
                <label for="estado">Estado del cliente</label>
                <select id="estado" name="estado">
                    <?php $est = old('estado', $c['estado'] ?? 'ACTIVO'); ?>
                    <option value="ACTIVO"    <?= $est === 'ACTIVO' ? 'selected' : '' ?>>Activo</option>
                    <option value="INACTIVO"  <?= $est === 'INACTIVO' ? 'selected' : '' ?>>Inactivo</option>
                    <option value="SUSPENDIDO" <?= $est === 'SUSPENDIDO' ? 'selected' : '' ?>>Suspendido</option>
                </select>
            </div>
            <div class="form-group">
                <label for="limite_credito">Límite de crédito</label>
                <input type="number" id="limite_credito" name="limite_credito" step="0.01" min="0"
                       value="<?= esc(old('limite_credito', $c['limite_credito'] ?? '')) ?>" placeholder="0.00">
            </div>
            <div class="form-group">
                <label for="monto_min">Monto mínimo de préstamo</label>
                <input type="number" id="monto_min" name="monto_min" step="0.01" min="0"
                       value="<?= esc(old('monto_min', $c['monto_min'] ?? '')) ?>" placeholder="0.00">
            </div>
            <div class="form-group">
                <label for="monto_max">Monto máximo de préstamo</label>
                <input type="number" id="monto_max" name="monto_max" step="0.01" min="0"
                       value="<?= esc(old('monto_max', $c['monto_max'] ?? '')) ?>" placeholder="0.00">
            </div>
            <div class="form-group form-full">
                <label for="direccion">Dirección</label>
                <input type="text" id="direccion" name="direccion"
                       value="<?= esc(old('direccion', $c['direccion'] ?? '')) ?>">
            </div>
            <div class="form-group form-full">
                <label for="observaciones">Observaciones generales</label>
                <textarea id="observaciones" name="observaciones" rows="3"
                          placeholder="Notas o comentarios sobre el cliente"><?= esc(old('observaciones', $c['observaciones'] ?? '')) ?></textarea>
            </div>
        </div>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary"><?= $esEditar ? 'Guardar cambios' : 'Registrar cliente' ?></button>
            <a href="<?= base_url('socios/clientes') ?>" class="btn">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
