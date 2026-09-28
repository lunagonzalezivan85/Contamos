<?= $this->extend('layouts/partner') ?>

<?= $this->section('content') ?>

<style>
.rep-search { position:relative; max-width:360px; }
.rep-search svg { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:var(--text-muted,#8A94A6); }
.rep-search input { width:100%; padding-left:38px; }
.rep-grid { display:grid; grid-template-columns:repeat(auto-fill,minmax(250px,1fr)); gap:14px; }
.rep-card { display:flex; gap:14px; align-items:flex-start; padding:18px; background:var(--surface,#fff);
    border:1px solid var(--border,#E5E9F0); border-radius:14px; text-decoration:none; color:inherit;
    transition:box-shadow .15s, transform .15s; }
.rep-card:hover { box-shadow:0 6px 18px rgba(46,53,66,.10); transform:translateY(-2px); }
.rep-card-ico { flex:0 0 44px; width:44px; height:44px; border-radius:12px; display:flex; align-items:center;
    justify-content:center; background:var(--primary-soft,rgba(48,203,154,.12)); color:var(--primary-dark,#1E9E76); }
.rep-card h5 { margin:0 0 4px; font-size:.98rem; font-weight:700; color:var(--text,#2E3542); }
.rep-card p { margin:0; font-size:.82rem; color:var(--text-muted,#8A94A6); line-height:1.45; }
.rep-cat { display:flex; align-items:center; gap:10px; margin:26px 0 14px; }
.rep-cat h4 { margin:0; font-size:.8rem; font-weight:700; letter-spacing:.06em; text-transform:uppercase; color:var(--text-muted,#8A94A6); }
.rep-cat::after { content:''; flex:1; height:1px; background:var(--border,#E5E9F0); }
.rep-empty { padding:40px 20px; text-align:center; color:var(--text-muted,#8A94A6); }
.rep-search input:focus { border-color:var(--primary,#30CB9A); box-shadow:0 0 0 3px rgba(48,203,154,.18); outline:none; }
.rep-chips { display:flex; gap:8px; flex-wrap:wrap; margin-top:4px; }
.rep-chip { padding:5px 14px; border-radius:999px; border:1px solid var(--border,#E5E9F0); background:var(--surface,#fff);
    font-size:.8rem; font-weight:600; color:var(--text-muted,#8A94A6); cursor:pointer; transition:all .15s; }
.rep-chip:hover { border-color:var(--primary,#30CB9A); color:var(--primary-dark,#1E9E76); }
.rep-chip.on { background:var(--primary,#30CB9A); border-color:var(--primary,#30CB9A); color:#fff; }
.rep-card.kbd { border-color:var(--primary,#30CB9A); box-shadow:0 0 0 3px rgba(48,203,154,.22); }
.rep-count { font-size:.78rem; color:var(--text-muted,#8A94A6); margin-top:6px; }
.rep-search kbd { position:absolute; right:10px; top:50%; transform:translateY(-50%); font-size:.7rem;
    border:1px solid var(--border,#E5E9F0); border-radius:4px; padding:1px 6px; color:var(--text-muted,#8A94A6); }
</style>

<div class="list-head">
    <div>
        <h3 class="page-title">Reportes</h3>
        <p class="page-subtitle"><?= array_sum(array_map(fn ($g) => count($g['items']), $grupos ?? [])) ?> reportes disponibles</p>
    </div>
    <div class="detail-hero-actions">
        <div>
            <div class="rep-search">
                <?= icon('search', 15) ?>
                <input type="text" id="rep-q" class="form-control" placeholder="Buscar reporte…" autocomplete="off">
                <kbd>/</kbd>
            </div>
            <div class="rep-count" id="rep-count"></div>
        </div>
    </div>
</div>

<!-- Chips de categoría -->
<div class="rep-chips" id="rep-chips">
    <button type="button" class="rep-chip on" data-cat="">Todas</button>
    <?php foreach ($grupos as $g): ?>
        <button type="button" class="rep-chip" data-cat="<?= esc($g['nombre']) ?>"><?= esc($g['nombre']) ?></button>
    <?php endforeach; ?>
</div>

<div id="rep-index">
<?php if (empty($grupos)): ?>
    <div class="card rep-empty">No tiene permisos para ver reportes.</div>
<?php endif; ?>

<?php foreach ($grupos as $g): ?>
    <div class="rep-group" data-cat="<?= esc(mb_strtolower($g['nombre'])) ?>">
        <div class="rep-cat"><h4><?= esc($g['nombre']) ?></h4></div>
        <div class="rep-grid">
            <?php foreach ($g['items'] as $r): ?>
                <a class="rep-card" href="<?= base_url($r['url']) ?>"
                   data-q="<?= esc(mb_strtolower($r['nombre'] . ' ' . $r['descripcion'] . ' ' . $g['nombre'])) ?>">
                    <div class="rep-card-ico"><?= icon($r['icono'], 20) ?></div>
                    <div>
                        <h5><?= esc($r['nombre']) ?></h5>
                        <p><?= esc($r['descripcion']) ?></p>
                    </div>
                </a>
            <?php endforeach; ?>
        </div>
    </div>
<?php endforeach; ?>

    <div class="card rep-empty" id="rep-vacio" hidden>Sin resultados para su búsqueda.</div>
</div>

<script>
(function () {
    var q       = document.getElementById('rep-q');
    var count   = document.getElementById('rep-count');
    var vacio   = document.getElementById('rep-vacio');
    var chips   = document.querySelectorAll('.rep-chip');
    var catSel  = '';
    var kbdIdx  = -1;

    // Normaliza: minúsculas + sin acentos + sin puntuación
    function norm(s) {
        return (s || '').toLowerCase().normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9 ]+/g, ' ')
            .replace(/\s+/g, ' ').trim();
    }
    function matchAll(texto, tokens) {
        for (var i = 0; i < tokens.length; i++) {
            if (texto.indexOf(tokens[i]) === -1) return false;
        }
        return true;
    }

    function visibleCards() {
        return Array.prototype.slice.call(
            document.querySelectorAll('.rep-card:not([style*="display: none"])')
        );
    }

    function aplicar() {
        var tokens = norm(q.value).split(' ').filter(Boolean);
        var total = 0;
        document.querySelectorAll('.rep-group').forEach(function (g) {
            var visible = 0;
            var catOk = catSel === '' || norm(g.dataset.cat) === norm(catSel);
            g.querySelectorAll('.rep-card').forEach(function (c) {
                var ok = catOk && matchAll(norm(c.dataset.q), tokens);
                c.style.display = ok ? '' : 'none';
                if (ok) visible++;
            });
            g.style.display = visible ? '' : 'none';
            total += visible;
        });
        vacio.hidden = total > 0;
        count.textContent = total === 0 ? '' : total + ' reporte' + (total !== 1 ? 's' : '');
        kbdIdx = -1;
    }

    q.addEventListener('input', aplicar);

    // Chips de categoría
    chips.forEach(function (ch) {
        ch.addEventListener('click', function () {
            chips.forEach(function (c) { c.classList.remove('on'); });
            ch.classList.add('on');
            catSel = ch.dataset.cat || '';
            aplicar();
        });
    });

    // Teclado: '/' enfoca, flechas navegan, Enter abre, Esc limpia
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement !== q && !/input|textarea|select/i.test(document.activeElement.tagName)) {
            e.preventDefault(); q.focus();
        }
    });
    q.addEventListener('keydown', function (e) {
        var vis = visibleCards();
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            if (!vis.length) return;
            kbdIdx = e.key === 'ArrowDown'
                ? (kbdIdx + 1) % vis.length
                : (kbdIdx - 1 + vis.length) % vis.length;
            vis.forEach(function (c) { c.classList.remove('kbd'); });
            vis[kbdIdx].classList.add('kbd');
            vis[kbdIdx].scrollIntoView({ block: 'nearest' });
        }
        if (e.key === 'Enter' && vis.length) {
            e.preventDefault();
            window.location.href = (vis[kbdIdx >= 0 ? kbdIdx : 0]).href;
        }
        if (e.key === 'Escape') { q.value = ''; aplicar(); }
    });

    aplicar();
})();
</script>

<?= $this->endSection() ?>
