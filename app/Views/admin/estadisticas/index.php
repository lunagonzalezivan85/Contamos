<?= $this->extend('layouts/admin') ?>

<?= $this->section('content') ?>

<div class="stats-grid">
    <div class="stat-card">
        <div class="num"><?= esc($stats['tenants_activos']) ?></div>
        <div class="lbl">Empresas activas</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= esc($stats['tenants_total']) ?></div>
        <div class="lbl">Empresas totales</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= esc($stats['usuarios_total']) ?></div>
        <div class="lbl">Usuarios registrados</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= esc($stats['logins_hoy']) ?></div>
        <div class="lbl">Logins exitosos hoy</div>
    </div>
    <div class="stat-card">
        <div class="num"><?= esc($stats['intentos_fallidos']) ?></div>
        <div class="lbl">Intentos fallidos hoy</div>
    </div>
</div>

<div class="card">
    <h3 class="card-title">Empresas registradas</h3>
    <p class="card-subtitle">Tenants de la plataforma y sus usuarios.</p>

    <table class="table mt-4">
        <thead>
            <tr>
                <th>Empresa</th>
                <th>Razón social</th>
                <th>Contacto</th>
                <th>Usuarios</th>
                <th>Estado</th>
                <th>Registro</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tenants as $t): ?>
                <tr>
                    <td class="fw-bold"><?= esc($t['nombre']) ?></td>
                    <td><?= esc($t['razon_social'] ?? '—') ?></td>
                    <td><?= esc($t['contacto_nombre'] ?? '—') ?></td>
                    <td><?= esc($t['usuarios']) ?></td>
                    <td>
                        <span class="tag <?= $t['estado'] === 'ACTIVO' ? 'tag-activo' : 'tag-inactivo' ?>">
                            <?= esc($t['estado']) ?>
                        </span>
                    </td>
                    <td class="text-muted"><?= esc(date('d/m/Y', strtotime($t['created_at']))) ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?= $this->endSection() ?>
