/* ==========================================================================
   Plan de pago SUGERIDO — modal solo-visual en nueva/editar solicitud.
   Replica SolicitudService::planPagos() (sin gracia ni frecuencia P) para
   FRANCES / FLAT / ALEMAN / ANTICIPADO. El plan real se genera al aprobar.
   Uso: PlanSugerido.mostrar({mon, monto, tasa, meses, freq, dias, tipo, fecha})
   → llena #plan-tabla (dentro de #modal-plan).
   ========================================================================== */
window.PlanSugerido = (function () {

    function fmt(mon, n) {
        return mon + ' ' + n.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    function fFecha(d) {
        return d.toLocaleDateString('es-NI', { day: '2-digit', month: 'short', year: 'numeric' });
    }
    function r2(n) { return Math.round(n * 100) / 100; }

    // Avanza la fecha según frecuencia — mismo criterio que planPagos()
    function pasoFecha(f, freq, dias) {
        if (freq === 'Q') {
            var dia    = f.getDate();
            var ultimo = new Date(f.getFullYear(), f.getMonth() + 1, 0).getDate();
            if (dia < 15) f.setDate(15);
            else if (dia < ultimo) f.setDate(ultimo);
            else { f.setDate(15); f.setMonth(f.getMonth() + 1); }
            return;
        }
        if (freq === 'M') { f.setMonth(f.getMonth() + 1); return; }
        var paso = freq === 'D' ? 1 : freq === 'S' ? 7 : Math.max(1, Math.round(7 / dias));
        do { f.setDate(f.getDate() + paso); }
        while (freq === 'DI' && f.getDay() === 0);           // sin domingo
    }

    function mostrar(cfg) {
        var box = document.getElementById('plan-tabla');
        if (!box) return;

        var mon  = cfg.mon || 'C$';
        var P    = parseFloat(cfg.monto) || 0;
        var t    = parseFloat(cfg.tasa)  || 0;
        var mes  = parseInt(cfg.meses, 10) || 1;
        var freq = cfg.freq || 'M';
        var dias = Math.max(1, parseInt(cfg.dias, 10) || 3);
        var tc   = cfg.tipo || 'FLAT';
        var ini  = cfg.fecha ? new Date(cfg.fecha + 'T00:00:00') : new Date();

        var ppm = freq === 'D' ? 30 : freq === 'DI' ? 4 * dias : freq === 'S' ? 4 : freq === 'Q' ? 2 : 1;
        var iP  = (t / 100) / ppm;
        var n   = Math.max(1, Math.round(mes * ppm));
        var cFr = iP > 0 ? P * iP * Math.pow(1 + iP, n) / (Math.pow(1 + iP, n) - 1) : P / n;

        var saldo = P, fecha = new Date(ini), rows = '';
        var totC = 0, totCap = 0, totInt = 0;
        for (var k = 1; k <= n; k++) {
            var cap, intx, cuota, last = k === n;
            if (tc === 'FLAT') {
                cap = r2(P / n); intx = r2(P * iP);
                cuota = last ? r2(saldo + intx) : r2(cap + intx);
            } else if (tc === 'ALEMAN') {
                cap = last ? saldo : r2(P / n); intx = r2(saldo * iP);
                cuota = r2(cap + intx);
            } else if (tc === 'ANTICIPADO') {
                cap = last ? saldo : r2(P / n); intx = 0;
                cuota = r2(cap);
            } else { // FRANCES
                intx = r2(saldo * iP);
                cap  = last ? saldo : r2(cFr - intx);
                cuota = last ? r2(cap + intx) : r2(cFr);
            }
            saldo = Math.max(0, r2(saldo - cap));
            totC += cuota; totCap += cap; totInt += intx;
            rows += '<tr><td>' + k + '</td><td>' + fFecha(fecha) + '</td>'
                  + '<td><strong>' + fmt(mon, cuota) + '</strong></td>'
                  + '<td class="plan-cap">' + fmt(mon, cap) + '</td>'
                  + '<td class="plan-int">' + fmt(mon, intx) + '</td>'
                  + '<td>' + fmt(mon, saldo) + '</td></tr>';
            pasoFecha(fecha, freq, dias);
        }

        var nota = tc === 'ANTICIPADO'
            ? '<p class="plan-resumen">Interés anticipado: ' + fmt(mon, r2(P * (t / 100) * mes))
              + ' se descuenta del desembolso — las cuotas son solo capital.</p>' : '';

        box.innerHTML =
            '<p class="plan-resumen"><strong>' + n + ' cuotas</strong> · primer pago '
            + fFecha(ini) + ' · método ' + tc + '</p>' + nota
            + '<div class="plan-scroll"><table class="plan-table"><thead><tr>'
            + '<th>#</th><th>Fecha</th><th>Cuota</th><th>Capital</th><th>Interés</th><th>Saldo</th>'
            + '</tr></thead><tbody>' + rows
            + '<tr class="plan-total"><td colspan="2">Totales</td><td>' + fmt(mon, r2(totC)) + '</td>'
            + '<td>' + fmt(mon, r2(totCap)) + '</td><td>' + fmt(mon, r2(totInt)) + '</td><td></td></tr>'
            + '</tbody></table></div>';
    }

    return { mostrar: mostrar };
})();
