/* ==========================================================================
   admin/leads — modal «Contactar»: sliders de consumo + select de plan,
   estimado en vivo (mismas tarifas que plan_helper) y POST a
   /admin/leads/{id}/contactar. El server recalcula — esto es solo preview.
   ========================================================================== */
(function () {
    'use strict';

    var modal = document.getElementById('modal-contactar');
    var form  = document.getElementById('mc-form');
    var plan  = document.getElementById('mc-plan');
    if (!modal || !form || !plan) return;

    // Tarifas de sobreconsumo — espejo de plan_helper (PLAN_USD_EXTRA_*)
    var TASA = { usuarios: 3.00, empleados: 1.00, creditos: 0.15, clientes: 0.20 };
    var BASE_CLIENTES = 20;
    var campos = ['usuarios', 'clientes', 'creditos', 'empleados'];

    function opt() { return plan.options[plan.selectedIndex]; }

    function estimado() {
        var o = opt();
        var total = parseFloat(o.dataset.p);
        total += Math.max(0, +val('usuarios')  - (+o.dataset.u < 0 ? 1e9 : +o.dataset.u)) * TASA.usuarios;
        total += Math.max(0, +val('clientes')  - BASE_CLIENTES)                           * TASA.clientes;
        total += Math.max(0, +val('creditos')  - (+o.dataset.c < 0 ? 1e9 : +o.dataset.c)) * TASA.creditos;
        total += Math.max(0, +val('empleados') - (+o.dataset.e < 0 ? 1e9 : +o.dataset.e)) * TASA.empleados;
        return total;
    }

    function val(k) { return document.getElementById('mc-' + k).value; }

    function pintar() {
        campos.forEach(function (k) {
            document.getElementById('mc-v-' + k).textContent = val(k);
        });
        document.getElementById('mc-est').textContent =
            'USD ' + estimado().toFixed(2);
    }

    campos.forEach(function (k) {
        document.getElementById('mc-' + k).addEventListener('input', pintar);
    });
    plan.addEventListener('change', pintar);

    // Abrir desde el botón de la fila — precarga sliders y plan del lead
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('.js-contactar');
        if (!btn) return;

        var det = {};
        try { det = JSON.parse(btn.dataset.det || '{}'); } catch (err) { /* sin detalle */ }

        document.getElementById('mc-nombre').textContent = btn.dataset.nombre;
        form.action = form.dataset.base + '/' + btn.dataset.id + '/contactar';

        campos.forEach(function (k) {
            var inp = document.getElementById('mc-' + k);
            var v = parseInt(det[k], 10);
            if (isNaN(v)) v = +inp.min;
            inp.value = Math.min(+inp.max, Math.max(+inp.min, v));
        });

        // Plan del lead si ya tenía propuesta; si no, queda el Básico (selected)
        if (+btn.dataset.pid > 0) plan.value = btn.dataset.pid;

        pintar();
        modal.hidden = false;
    });

    // Modal «Generar contrato» — condiciones pactadas (día pago, gracia, cuenta)
    var mCt   = document.getElementById('modal-contrato');
    var ctForm = document.getElementById('ct-form');
    if (mCt && ctForm) {
        document.addEventListener('click', function (e) {
            var btn = e.target.closest('.js-contrato');
            if (!btn) return;

            document.getElementById('ct-nombre').textContent = btn.dataset.nombre;
            document.getElementById('ct-monto').value =
                (+btn.dataset.monto > 0 ? (+btn.dataset.monto).toFixed(2) : '');
            ctForm.action = ctForm.dataset.base + '/' + btn.dataset.id + '/contrato';
            mCt.hidden = false;
        });
    }
})();
