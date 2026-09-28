/* ==========================================================================
   Calculadora de préstamo — portal del gestor (/portal/calculadora)
   Sistema francés (cuota fija) con frecuencia de pago.
   La moneda llega por data-mon en #calc-app.
   ========================================================================== */
(function () {
    var app = document.getElementById('calc-app');
    if (!app) return;

    var mon      = app.dataset.mon || 'C$';
    var TASA_MAX = parseFloat(app.dataset.tasa) || 0;   // tope del tenant — se puede bajar

    var monto    = document.getElementById('calc-monto');
    var tasa     = document.getElementById('calc-tasa');
    var plazo    = document.getElementById('calc-plazo');
    var diasIn   = document.getElementById('calc-dias');
    var outCuota   = document.getElementById('calc-cuota');
    var outTotal   = document.getElementById('calc-total');
    var outInteres = document.getElementById('calc-interes');
    var outCuotas  = document.getElementById('calc-cuotas');
    var lblCuota   = document.getElementById('calc-cuota-label');
    var lblMonto   = document.getElementById('calc-monto-lbl');
    var lblTasa    = document.getElementById('calc-tasa-lbl');
    var lblPlazo   = document.getElementById('calc-plazo-lbl');
    var lblDias    = document.getElementById('calc-dias-lbl');
    var freqWrap   = document.getElementById('calc-freq');
    var diasWrap   = document.getElementById('calc-dias-wrap');

    // Frecuencia seleccionada (D, DI, S, Q, M)
    var freq = 'M';

    // Pagos por mes y etiqueta del período por frecuencia
    var freqInfo = {
        D:  { pagosPorMes: 30,  lbl: 'Cuota diaria' },
        DI: { pagosPorMes: 0,   lbl: 'Cuota por día de pago' }, // depende de días/sem
        S:  { pagosPorMes: 4,   lbl: 'Cuota semanal' },
        Q:  { pagosPorMes: 2,   lbl: 'Cuota quincenal' },
        M:  { pagosPorMes: 1,   lbl: 'Cuota mensual' }
    };

    function fmt(n) {
        return mon + ' ' + n.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function calcular() {
        var P     = parseFloat(monto.value) || 0;
        var i     = Math.min(parseFloat(tasa.value) || 0, TASA_MAX) / 100;   // tope tenant
        var meses = parseInt(plazo.value, 10) || 0;
        var dias  = parseInt(diasIn.value, 10) || 3;          // solo diario intermitente

        var pagosPorMes = freq === 'DI' ? (4 * dias) : freqInfo[freq].pagosPorMes;
        var iPeriodo    = i / pagosPorMes;                    // tasa proporcional por período
        var n           = Math.max(1, Math.round(meses * pagosPorMes));

        // Etiquetas
        lblMonto.textContent = mon + ' ' + P.toLocaleString('es-NI');
        lblTasa.textContent  = (i * 100).toFixed(2) + '%';
        lblPlazo.textContent = meses + (meses === 1 ? ' mes' : ' meses');
        lblDias.textContent  = dias + (dias === 1 ? ' día' : ' días') + ' / semana';
        lblCuota.textContent = freqInfo[freq].lbl + ' estimada';
        diasWrap.hidden = freq !== 'DI';

        if (P <= 0 || meses <= 0) {
            outCuota.textContent = mon + ' 0.00';
            outTotal.textContent = outInteres.textContent = outCuotas.textContent = '—';
            return;
        }
        var cuota = iPeriodo > 0
            ? P * iPeriodo * Math.pow(1 + iPeriodo, n) / (Math.pow(1 + iPeriodo, n) - 1)
            : P / n;
        var total = cuota * n;
        outCuota.textContent   = fmt(cuota);
        outTotal.textContent   = fmt(total);
        outInteres.textContent = fmt(total - P);
        outCuotas.textContent  = n;
    }

    // Clic en un botón de frecuencia
    freqWrap.querySelectorAll('.freq-btn').forEach(function (b) {
        b.addEventListener('click', function () {
            freqWrap.querySelectorAll('.freq-btn').forEach(function (x) { x.classList.remove('activo'); });
            b.classList.add('activo');
            freq = b.dataset.f;
            calcular();
        });
    });

    [monto, tasa, plazo, diasIn].forEach(function (el) {
        el.addEventListener('input', calcular);
    });

    /* ---------- Plan de pago simulado (fecha propuesta por el cliente) ---------- */
    var inicioIn  = document.getElementById('calc-inicio');
    var planBtn   = document.getElementById('calc-plan-btn');
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
            if (dia < 15)      f.setDate(15);
            else if (dia < ult) f.setDate(ult);
            else               f = new Date(f.getFullYear(), f.getMonth() + 1, 15);
            return f;
        }
        do { f.setDate(f.getDate() + diasPaso()); }
        while (freq === 'DI' && f.getDay() === 0);  // sin domingo
        return f;
    }

    // Fecha sugerida del primer pago: hoy + el paso de la frecuencia
    function sugerirInicio() {
        if (!inicioIn.value) {
            inicioIn.value = sumarPaso(new Date()).toISOString().slice(0, 10);
        }
    }

    function verPlan() {
        var P     = parseFloat(monto.value) || 0;
        var i     = Math.min(parseFloat(tasa.value) || 0, TASA_MAX) / 100;
        var meses = parseInt(plazo.value, 10) || 0;
        var dias  = parseInt(diasIn.value, 10) || 3;
        var ppm   = freq === 'DI' ? 4 * dias : freqInfo[freq].pagosPorMes;
        var iP    = i / ppm;
        var n     = Math.max(1, Math.round(meses * ppm));
        var cuota = iP > 0 ? P * iP * Math.pow(1 + iP, n) / (Math.pow(1 + iP, n) - 1) : P / n;

        if (P <= 0 || meses <= 0) { alert('Completa monto y plazo.'); return; }
        sugerirInicio();

        var f = new Date(inicioIn.value + 'T12:00:00');
        var saldo = P;
        var html  = '';
        var totInt = 0;
        for (var k = 1; k <= n; k++) {
            var interes = saldo * iP;
            var capital = cuota - interes;
            saldo = Math.max(0, saldo - capital);
            totInt += interes;
            html += '<tr><td>' + k + '</td><td style="white-space:nowrap;">' +
                f.toLocaleDateString('es-NI', {day:'2-digit', month:'2-digit', year:'numeric'}) +
                '</td><td style="text-align:right;">' + fmt(cuota) +
                '</td><td style="text-align:right;">' + fmt(interes) +
                '</td><td style="text-align:right;">' + fmt(saldo) + '</td></tr>';
            f = sumarPaso(f);
        }
        planModal.querySelector('#plan-tbl tbody').innerHTML = html;
        document.getElementById('plan-title').textContent =
            'Plan ' + freqInfo[freq].lbl.toLowerCase() + ' — ' + n + ' pagos';
        document.getElementById('plan-foot').innerHTML =
            '<span>Total intereses: <strong>' + fmt(totInt) + '</strong></span>' +
            '<span>Total a pagar: <strong>' + fmt(P + totInt) + '</strong></span>';
        planModal.hidden = false;
    }

    if (planBtn && planModal) {
        planBtn.addEventListener('click', verPlan);
        planModal.addEventListener('click', function (e) {
            if (e.target === planModal || e.target.closest('[data-close]')) planModal.hidden = true;
        });
    }

    calcular();
})();
