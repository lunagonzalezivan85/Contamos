/* ==========================================================================
   Nueva solicitud — wizard de 3 pasos (portal del gestor)
   Paso 1: Cliente (existente o nuevo) · Paso 2: Préstamo dinámico (sliders
   + frecuencia de pago + cuota en vivo) · Paso 3: Resumen.
   La moneda llega por data-mon en #sol-form.
   ========================================================================== */
(function () {
    var form = document.getElementById('sol-form');
    if (!form) return;

    var mon  = form.dataset.mon || 'C$';
    var TASA_MAX = parseFloat(form.dataset.tasa) || 0;   // tope del tenant — el gestor puede bajarla
    var TIPO     = form.dataset.tipo || 'FLAT';          // método de cálculo del tenant (config)
    var TIPO_LBL = { FRANCES: 'Francés', FLAT: 'Flat', ALEMAN: 'Alemán', ANTICIPADO: 'Anticipado' };
    var LIM_DEFECTO = 10000;                          // tope de cliente nuevo

    var panes  = document.querySelectorAll('.wiz-pane');
    var steps  = document.querySelectorAll('.wiz-step');
    var bar    = document.getElementById('wiz-bar');
    var prev   = document.getElementById('wiz-prev');
    var next   = document.getElementById('wiz-next');
    var send   = document.getElementById('wiz-send');
    var paso   = 1;
    var total  = panes.length;

    // ---- Frecuencia de pago ----
    var freq      = 'M';
    var freqWrap  = document.getElementById('sol-freq');
    var freqInput = document.getElementById('frecuencia');
    var diasWrap  = document.getElementById('sol-dias-wrap');
    var diasIn    = document.getElementById('dias_semana');
    var diasLbl   = document.getElementById('sol-dias-lbl');

    var freqLbl   = { D: 'Diario', DI: 'Diario intermitente', S: 'Semanal', Q: 'Quincenal', M: 'Mensual' };
    var freqPagos = { D: 30, S: 4, Q: 2, M: 1 };

    freqWrap.querySelectorAll('.freq-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            freqWrap.querySelectorAll('.freq-btn').forEach(function (x) { x.classList.remove('activo'); });
            b.classList.add('activo');
            freq = b.dataset.f;
            freqInput.value = freq;
            diasWrap.hidden = freq !== 'DI';
            calcular();
        });
    });
    diasIn.addEventListener('input', calcular);

    // ---- Modo cliente (existente / nuevo) ----
    var modo      = 'existente';
    var modoWrap  = document.getElementById('cli-modo');
    var modoInput = document.getElementById('cli_modo');
    var cliExist  = document.getElementById('cli-existente');
    var cliNuevo  = document.getElementById('cli-nuevo');

    // ---- Paleta de búsqueda de cliente ----
    var pickRoot  = document.getElementById('cli-pick');
    var pickWrap  = document.getElementById('cli-pick-wrap');
    var pickQ     = document.getElementById('cli-pick-q');
    var pickList  = document.getElementById('cli-pick-list');
    var pickHid   = document.getElementById('cliente_id');
    var pickClear = document.getElementById('cli-pick-clear');
    var pickEmpty = document.getElementById('cli-pick-empty');
    var pickItems = pickList ? Array.prototype.slice.call(pickList.querySelectorAll('.cli-pick-item')) : [];

    function pickFiltrar(q) {
        q = q.trim().toLowerCase();
        var vis = 0;
        pickItems.forEach(function (it) {
            var show = q === '' || it.dataset.q.indexOf(q) !== -1;
            it.style.display = show ? '' : 'none';
            if (show) vis++;
        });
        pickEmpty.hidden = vis > 0;
    }
    function pickCerrar() { pickList.hidden = true; }
    function pickElegir(it) {
        pickHid.value = it.dataset.id;
        pickHid.dataset.nombre = it.dataset.nombre;
        pickQ.value = it.dataset.nombre;
        pickQ.readOnly = true;
        pickWrap.classList.add('sel');
        pickClear.hidden = false;
        pickItems.forEach(function (x) { x.classList.toggle('sel', x === it); });
        pickCerrar();
        aplicarLimite(parseFloat(it.dataset.limite) || LIM_DEFECTO);
    }
    function pickLimpiar() {
        pickHid.value = '';
        pickHid.dataset.nombre = '';
        pickQ.value = '';
        pickQ.readOnly = false;
        pickWrap.classList.remove('sel');
        pickClear.hidden = true;
        pickItems.forEach(function (x) { x.classList.remove('sel'); });
        aplicarLimite(LIM_DEFECTO);
        pickQ.focus();
    }

    if (pickQ && pickList) {
        // Estado inicial (old() tras error del server)
        if (pickHid.value && pickQ.value) {
            pickHid.dataset.nombre = pickQ.value;
            pickWrap.classList.add('sel');
        }
        pickQ.addEventListener('focus', function () {
            if (pickQ.readOnly) return;
            pickFiltrar(pickQ.value);
            pickList.hidden = false;
        });
        pickQ.addEventListener('input', function () { pickFiltrar(pickQ.value); });
        pickQ.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') pickCerrar();
        });
        pickItems.forEach(function (it) {
            it.addEventListener('click', function () { pickElegir(it); });
        });
        pickClear.addEventListener('click', pickLimpiar);
        document.addEventListener('click', function (e) {
            if (!e.target.closest('#cli-pick')) pickCerrar();
        });
    }

    modoWrap.querySelectorAll('.freq-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            modoWrap.querySelectorAll('.freq-btn').forEach(function (x) { x.classList.remove('activo'); });
            b.classList.add('activo');
            modo = b.dataset.m;
            modoInput.value = modo;
            cliExist.hidden = modo !== 'existente';
            cliNuevo.hidden = modo !== 'nuevo';
            if (modo === 'nuevo') aplicarLimite(LIM_DEFECTO);
            else {
                var sel = pickList.querySelector('.cli-pick-item.sel');
                aplicarLimite(sel ? (parseFloat(sel.dataset.limite) || LIM_DEFECTO) : LIM_DEFECTO);
            }
        });
    });

    // ---- Sliders dinámicos: etiquetas + cuota en vivo ----
    var montoIn   = document.getElementById('monto');
    var tasaIn    = document.getElementById('tasa_mensual');
    var plazoIn   = document.getElementById('plazo_meses');
    var lblMonto  = document.getElementById('sol-monto-lbl');
    var lblTasa   = document.getElementById('sol-tasa-lbl');
    var lblPlazo  = document.getElementById('sol-plazo-lbl');
    var prevCuota = document.getElementById('sol-cuota-prev');
    var lblCuota  = document.getElementById('sol-cuota-label');
    var lblLimite = document.getElementById('sol-limite-lbl');

    function fmt(n) {
        return mon + ' ' + n.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    /* Tope de crédito según el cliente elegido (o 10,000 si es nuevo). */
    function aplicarLimite(lim) {
        var max = Math.max(1000, Math.round(lim || LIM_DEFECTO));
        montoIn.max = max;
        if (parseFloat(montoIn.value) > max) montoIn.value = max;
        if (lblLimite) lblLimite.textContent =
            'Máximo para este cliente: ' + mon + ' ' + max.toLocaleString('es-NI');
        calcular();
    }

    function datos() {
        var P     = parseFloat(montoIn.value) || 0;
        var i     = Math.min(parseFloat(tasaIn.value) || 0, TASA_MAX) / 100;
        var meses = parseInt(plazoIn.value, 10) || 0;
        var dias  = parseInt(diasIn.value, 10) || 3;
        var pagosPorMes = freq === 'DI' ? 4 * dias : (freqPagos[freq] || 1);
        var iP = i / pagosPorMes;
        var n  = Math.max(1, Math.round(meses * pagosPorMes));
        var cuota;   // cuota representativa según el método del tenant
        if (TIPO === 'FLAT' || TIPO === 'ALEMAN') {    // alemán: muestra la 1ra (mayor)
            cuota = P / n + P * iP;
        } else if (TIPO === 'ANTICIPADO') {
            cuota = P / n;                             // solo capital — el interés se descuenta al entregar
        } else { // FRANCES
            cuota = iP > 0 ? P * iP * Math.pow(1 + iP, n) / (Math.pow(1 + iP, n) - 1) : (P / n || 0);
        }
        return { P: P, i: i, iP: iP, meses: meses, dias: dias, n: n, cuota: cuota };
    }

    function calcular() {
        var d = datos();
        lblMonto.textContent  = mon + ' ' + d.P.toLocaleString('es-NI');
        lblTasa.textContent   = (d.i * 100).toFixed(2) + '%';
        lblPlazo.textContent  = d.meses + (d.meses === 1 ? ' mes' : ' meses');
        diasLbl.textContent   = d.dias + (d.dias === 1 ? ' día' : ' días') + ' / semana';
        lblCuota.textContent  = (freqLbl[freq] ? 'Cuota ' + freqLbl[freq].toLowerCase() : 'Cuota')
            + ' estimada · ' + (TIPO_LBL[TIPO] || TIPO);
        prevCuota.textContent = fmt(d.cuota) + ' × ' + d.n;
    }
    [montoIn, tasaIn, plazoIn].forEach(function (el) { el.addEventListener('input', calcular); });

    // ---- Validación por paso ----
    function validar(s) {
        if (s === 1) {
            if (modo === 'existente') {
                if (!document.getElementById('cliente_id').value) { alert('Selecciona un cliente.'); return false; }
            } else {
                var n = document.getElementById('cli_nombres').value.trim();
                var a = document.getElementById('cli_apellidos').value.trim();
                if (!n || !a) { alert('Completa nombres y apellidos del cliente.'); return false; }
            }
        }
        if (s === 2) {
            var m = parseFloat(montoIn.value) || 0;
            if (m < 1000) { alert('El monto mínimo a prestar es ' + mon + ' 1,000.'); return false; }
        }
        return true;
    }

    // ---- Resumen (paso 3) ----
    function resumen() {
        var cliente;
        if (modo === 'existente') {
            cliente = pickHid.dataset.nombre || pickQ.value || '—';
        } else {
            cliente = (document.getElementById('cli_nombres').value + ' ' + document.getElementById('cli_apellidos').value).trim();
        }
        var d = datos();
        var f = freqLbl[freq] + (freq === 'DI' ? ' (' + d.dias + ' días/sem)' : '');

        document.getElementById('sol-resumen').innerHTML =
            '<div class="sol-row"><span>Cliente</span><strong>' + (cliente || '—') + '</strong></div>' +
            '<div class="sol-row"><span>Monto</span><strong>' + fmt(d.P) + '</strong></div>' +
            '<div class="sol-row"><span>Tasa mensual</span><strong>' + (d.i * 100).toFixed(2) + '%</strong></div>' +
            '<div class="sol-row"><span>Plazo</span><strong>' + d.meses + ' meses</strong></div>' +
            '<div class="sol-row"><span>Frecuencia</span><strong>' + f + '</strong></div>' +
            '<div class="sol-row"><span>Método</span><strong>' + (TIPO_LBL[TIPO] || TIPO) + '</strong></div>' +
            '<div class="sol-row sol-cuota"><span>Cuota estimada</span><strong>' + fmt(d.cuota) + ' × ' + d.n + ' pagos</strong></div>';
    }

    // ---- Navegación del wizard ----
    function ir(s) {
        if (s < 1 || s > total) return;
        paso = s;
        panes.forEach(function (p) { p.classList.toggle('on', +p.dataset.s === s); });
        steps.forEach(function (st) {
            st.classList.toggle('on', +st.dataset.s === s);
            st.classList.toggle('done', +st.dataset.s < s);
        });
        bar.style.transform = 'scaleX(' + ((s - 1) / (total - 1)) + ')';
        prev.disabled = s === 1;
        next.hidden   = s === total;
        send.hidden   = s !== total;
        if (s === total) resumen();
    }

    next.addEventListener('click', function () { if (validar(paso)) ir(paso + 1); });
    prev.addEventListener('click', function () { ir(paso - 1); });
    form.addEventListener('submit', function (e) {
        if (!validar(paso)) e.preventDefault();
    });

    /* ---- Plan de pago simulado (fecha propuesta por la clienta) ---- */
    var inicioIn  = document.getElementById('sol-inicio');
    var planBtn   = document.getElementById('sol-plan-btn');
    var planModal = document.getElementById('modal-plan');

    function diasPaso() {
        if (freq === 'D')  return 1;
        if (freq === 'S')  return 7;
        if (freq === 'DI') return Math.max(1, Math.round(7 / (parseInt(diasIn.value, 10) || 3)));
        return 30; // M
    }

    // Próxima fecha de cuota según la frecuencia (igual que el plan real del server)
    function sumarPaso(fecha) {
        var f = new Date(fecha.getTime());
        if (freq === 'Q') {
            var dia = f.getDate();
            var ult = new Date(f.getFullYear(), f.getMonth() + 1, 0).getDate();
            if (dia < 15)       f.setDate(15);
            else if (dia < ult) f.setDate(ult);
            else                f = new Date(f.getFullYear(), f.getMonth() + 1, 15);
            return f;
        }
        do { f.setDate(f.getDate() + diasPaso()); }
        while (freq === 'DI' && f.getDay() === 0);  // sin domingo
        return f;
    }

    function verPlan() {
        var d = datos();
        if (d.P <= 0 || d.meses <= 0) { alert('Completa monto y plazo.'); return; }
        if (!inicioIn.value) {
            inicioIn.value = sumarPaso(new Date()).toISOString().slice(0, 10);
        }

        var iP  = d.iP;
        var cFr = iP > 0 ? d.P * iP * Math.pow(1 + iP, d.n) / (Math.pow(1 + iP, d.n) - 1) : d.P / d.n;
        var f   = new Date(inicioIn.value + 'T12:00:00');
        var saldo = d.P, totInt = 0, html = '';
        for (var k = 1; k <= d.n; k++) {
            var capital, interes, cuotaI, last = k === d.n;
            if (TIPO === 'FLAT')            { capital = d.P / d.n; interes = d.P * iP; cuotaI = last ? saldo + interes : capital + interes; }
            else if (TIPO === 'ALEMAN')     { capital = last ? saldo : d.P / d.n; interes = saldo * iP; cuotaI = capital + interes; }
            else if (TIPO === 'ANTICIPADO') { capital = last ? saldo : d.P / d.n; interes = 0; cuotaI = capital; }
            else /* FRANCES */              { interes = saldo * iP; capital = last ? saldo : cFr - interes; cuotaI = last ? capital + interes : cFr; }
            saldo = Math.max(0, saldo - capital);
            totInt += interes;
            html += '<tr><td>' + k + '</td><td style="white-space:nowrap;">' +
                f.toLocaleDateString('es-NI', {day:'2-digit', month:'2-digit', year:'numeric'}) +
                '</td><td style="text-align:right;">' + fmt(cuotaI) +
                '</td><td style="text-align:right;">' + fmt(interes) +
                '</td><td style="text-align:right;">' + fmt(saldo) + '</td></tr>';
            f = sumarPaso(f);
        }
        planModal.querySelector('#plan-tbl tbody').innerHTML = html;
        document.getElementById('plan-title').textContent =
            'Plan ' + (freqLbl[freq] || '').toLowerCase() + ' — ' + d.n + ' pagos · ' + (TIPO_LBL[TIPO] || TIPO);
        // Anticipado: el interés no va en las cuotas, se descuenta del desembolso
        var intShow = TIPO === 'ANTICIPADO' ? d.P * iP * d.n : totInt;
        document.getElementById('plan-foot').innerHTML =
            '<span>' + (TIPO === 'ANTICIPADO' ? 'Interés anticipado' : 'Total intereses') +
            ': <strong>' + fmt(intShow) + '</strong></span>' +
            '<span>Total a pagar: <strong>' + fmt(d.P + intShow) + '</strong></span>';
        planModal.hidden = false;
    }

    if (planBtn && planModal) {
        planBtn.addEventListener('click', verPlan);
        planModal.addEventListener('click', function (e) {
            if (e.target === planModal || e.target.closest('[data-close]')) planModal.hidden = true;
        });
    }

    // Tope inicial: el del cliente preseleccionado (old()) o el por defecto
    var selIni = pickList ? pickList.querySelector('.cli-pick-item.sel') : null;
    aplicarLimite(selIni ? (parseFloat(selIni.dataset.limite) || LIM_DEFECTO) : LIM_DEFECTO);
    ir(1);
})();
