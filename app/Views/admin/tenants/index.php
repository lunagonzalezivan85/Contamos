<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="toolbar">
    <div>
        <h3 class="page-title">Tenants</h3>
        <p class="page-sub">Empresas de la plataforma — <?= count($tenants) ?> registradas. Toca una para ver su ficha.</p>
    </div>
    <a class="btn btn-primary" href="<?= base_url('admin/tenants/nuevo') ?>">+ Nuevo tenant</a>
</div>

<div class="card" style="padding:0; overflow:hidden;">
    <?php if (empty($tenants)): ?>
        <p class="text-muted" style="padding:20px;">Sin tenants registrados.</p>
    <?php endif; ?>
    <?php foreach ($tenants as $t): ?>
        <a class="tn-row" href="<?= base_url('admin/tenants/' . $t['id']) ?>">
            <div class="tn-id">
                <b><?= esc($t['nombre']) ?></b>
                <small class="text-muted">/<?= esc($t['slug']) ?> · <?= esc($t['plan'] ?? 'Sin plan') ?></small>
            </div>
            <div class="tn-mid text-muted">
                <span><?= esc($t['contacto_nombre'] ?: '—') ?></span>
                <span><?= (int) $t['usuarios'] ?> usuario<?= (int) $t['usuarios'] === 1 ? '' : 's' ?></span>
                <span>Alta <?= $t['created_at'] ? esc(date('d/m/Y', strtotime($t['created_at']))) : '—' ?></span>
            </div>
            <div class="tn-tags">
                <span class="tag <?= ($t['suscripcion_estado'] ?? 'ACTIVA') === 'ACTIVA' ? 'tag-activo' : 'tag-warn' ?>">
                    <?= esc($t['suscripcion_estado'] ?? '—') ?>
                </span>
                <span class="tag <?= $t['estado'] === 'ACTIVO' ? 'tag-activo' : 'tag-inactivo' ?>">
                    <?= esc($t['estado']) ?>
                </span>
                <span class="tn-arrow">→</span>
            </div>
        </a>
    <?php endforeach; ?>
</div>

<?= $this->endSection() ?>
