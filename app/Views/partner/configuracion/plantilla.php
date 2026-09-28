<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<?= view('partials/detail_hero', [
    'titulo'    => $plantilla['nombre'],
    'subtitulo' => 'Plantilla · ' . ($plantilla['descripcion'] ?? ''),
    'icono'     => 'file-text',
    'volver'    => '/configuracion',
]) ?>

<div class="card">
    <h4 class="card-title">Contenido de la plantilla</h4>
    <p class="card-subtitle">
        Use <code>{variable}</code> para datos dinámicos: <code>{empresa}</code>, <code>{lema}</code>,
        <code>{cliente}</code>, <code>{credito}</code>, <code>{monto}</code>, <code>{fecha}</code>, <code>{voucher_footer}</code>.
    </p>

    <!-- Cargar una plantilla propuesta del sistema -->
    <div class="tpl-load">
        <label for="tpl-source" class="tpl-load-label">Cargar plantilla del sistema:</label>
        <select id="tpl-source" class="tpl-load-select">
            <option value="">— Seleccione una propuesta —</option>
            <?php foreach (plantillas_sistema() as $slug => $tpl): ?>
                <option value="<?= esc($slug) ?>"><?= esc($tpl['nombre']) ?></option>
            <?php endforeach; ?>
        </select>
        <span class="tpl-load-hint">Reemplaza el contenido actual del editor.</span>
    </div>

    <?php foreach (plantillas_sistema() as $slug => $tpl): ?>
        <textarea class="sys-tpl-src" data-key="<?= esc($slug) ?>" hidden><?= esc($tpl['contenido']) ?></textarea>
    <?php endforeach; ?>

    <form action="<?= base_url('configuracion/plantilla/' . $plantilla['id']) ?>" method="post" class="mt-4" id="tpl-form">
        <?= csrf_field() ?>

        <!-- Barra de herramientas -->
        <div class="tpl-toolbar" id="tpl-toolbar">
            <button type="button" data-cmd="bold" title="Negrita"><b>B</b></button>
            <button type="button" data-cmd="italic" title="Cursiva"><i>I</i></button>
            <button type="button" data-cmd="underline" title="Subrayado"><u>S</u></button>
            <span class="tpl-sep"></span>
            <button type="button" data-cmd="justifyLeft" title="Izquierda"><?= icon('menu', 15) ?></button>
            <button type="button" data-cmd="justifyCenter" title="Centrar"><?= icon('menu', 15) ?></button>
            <button type="button" data-cmd="justifyRight" title="Derecha"><?= icon('menu', 15) ?></button>
            <button type="button" data-cmd="justifyFull" title="Justificar"><?= icon('menu', 15) ?></button>
            <span class="tpl-sep"></span>
            <button type="button" data-cmd="insertUnorderedList" title="Viñetas">•≡</button>
            <button type="button" data-cmd="insertOrderedList" title="Numerada">1≡</button>
            <span class="tpl-sep"></span>
            <button type="button" data-cmd="formatBlock" data-arg="h2" title="Título">T</button>
            <button type="button" data-cmd="formatBlock" data-arg="p" title="Párrafo">¶</button>
            <button type="button" data-cmd="removeFormat" title="Limpiar formato">⌫</button>
        </div>

        <!-- Área editable (tipo Word) -->
        <div class="tpl-doc" id="tpl-editor" contenteditable="true"></div>

        <!-- Contenido real que se envía -->
        <textarea id="contenido" name="contenido" hidden><?= esc(old('contenido', $plantilla['contenido'] ?? '')) ?></textarea>

        <div class="form-actions">
            <button type="submit" class="btn btn-primary">Guardar plantilla</button>
            <a href="<?= base_url('configuracion') ?>" class="btn">Cancelar</a>
        </div>
    </form>
</div>

<?= $this->endSection() ?>
