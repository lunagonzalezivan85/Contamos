/* ==========================================================================
   admin/tenants/nuevo — botón «Generar» slug + chequeo de disponibilidad
   en vivo contra GET /admin/tenants/slug-sugerir (JSON).
   ========================================================================== */
(function () {
    'use strict';

    var slug   = document.getElementById('inp-slug');
    var nombre = document.querySelector('input[name="nombre"]');
    var btn    = document.getElementById('btn-gen-slug');
    var hint   = document.getElementById('slug-hint');
    if (!slug || !btn) return;

    var base = slug.form ? slug.form.getAttribute('action').replace(/admin\/tenants$/, '') : '/';
    var api  = base + 'admin/tenants/slug-sugerir';
    var timer = null;

    function aviso(txt, ocupado) {
        hint.textContent = txt;
        hint.style.color = ocupado ? '#B91C1C' : '';
    }

    function checkLibre(val) {
        if (!val) { aviso('Si se omite se genera del nombre.', false); return; }
        fetch(api + '?slug=' + encodeURIComponent(val), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                aviso(d.ocupado ? '«' + val + '» ya está en uso — el alta fallará.' : '«' + val + '» está libre.', d.ocupado);
            })
            .catch(function () { /* sin red → el server valida igual */ });
    }

    btn.addEventListener('click', function () {
        var nom = nombre ? nombre.value.trim() : '';
        if (!nom) { aviso('Escribí primero el nombre de la empresa.', true); if (nombre) nombre.focus(); return; }
        btn.disabled = true;
        fetch(api + '?nombre=' + encodeURIComponent(nom), { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (d) {
                if (d.slug) {
                    slug.value = d.slug;
                    aviso('«' + d.slug + '» está libre.', false);
                }
            })
            .finally(function () { btn.disabled = false; });
    });

    slug.addEventListener('input', function () {
        var val = slug.value.trim().toLowerCase().replace(/[^a-z0-9-]/g, '-').replace(/-+/g, '-');
        if (val !== slug.value) slug.value = val;
        clearTimeout(timer);
        timer = setTimeout(function () { checkLibre(slug.value.trim()); }, 350);
    });
})();
