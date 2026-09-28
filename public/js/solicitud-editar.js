/* ==========================================================================
   Editar solicitud (/credito/solicitudes/{id}/editar)
   Mismo layout/UX que nueva.php: formulario a la izquierda, resumen sticky
   en vivo a la derecha. Moneda y nombre del cliente llegan por data-attrs
   en #sol-edit-form. La tasa se edita en campo (nueva.php la tiene fija).
   ========================================================================== */
(function () {
    var form = document.getElementById('sol-edit-form');
    if (!form) return;

    var mon = form.dataset.mon || 'C$';

    var monto  = document.getElementById('monto');
    var tasa   = document.getElementById('tasa_mensual');
    var plazo  = document.getElementById('plazo_meses');
    var dias   = document.getElementById('dias_semana');
    var gestor = document.getElementById('asignado_a');
    var tipo   = document.getElementById('tipo_calculo');
    var fpp    = document.getElementById('fecha_primer_pago');
    var dwrap  = document.getElementById('sol-dias-wrap');

    var LBL_F = { D: 'Diario', DI: 'Diario intermitente', S: 'Semanal', Q: 'Quincenal', M: 'Mensual' };
    var PPM   = { D: 30, S: 4, Q: 2, M: 1 };

    function freq() { return (form.querySelector('input[name=frecuencia]:checked') || {}).value || 'M'; }
    function fmt(n) { return mon + ' ' + n.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); }

    function resumen() {
        var f    = freq();
        var P    = parseFloat(monto.value) || 0;
        var t    = parseFloat(tasa.value)  || 0;
        var mes  = parseInt(plazo.value, 10) || 0;
        var dSem = parseInt(dias.value, 10)  || 3;

        // ocultar días si no es intermitente
        dwrap.hidden = f !== 'DI';
        dias.required = f === 'DI';

        var ppm = f === 'DI' ? 4 * dSem : PPM[f];
        var iP  = (t / 100) / ppm;
        var n   = Math.max(1, Math.round(mes * ppm));
        var tc  = tipo.value || 'FLAT';

        // Cuota estimada según el tipo de cálculo (igual que el plan real)
        var intFlat = P * (t / 100) * mes;                          // FLAT/ANTICIPADO: tasa × meses
        var cuota, total, lblCuota = 'Cuota ' + LBL_F[f].toLowerCase() + ' estimada';
        if (tc === 'FLAT') {
            cuota = P > 0 ? (P + intFlat) / n : 0;
            total = P + intFlat;
        } else if (tc === 'ALEMAN') {
            cuota = P > 0 ? P / n + iP * P : 0;                     // primera cuota (la mayor)
            total = P + iP * P * (n + 1) / 2;
            lblCuota = 'Primera cuota ' + LBL_F[f].toLowerCase() + ' (baja cada pago)';
        } else if (tc === 'ANTICIPADO') {
            cuota = P > 0 ? P / n : 0;                              // solo capital — interés descontado del desembolso
            total = P + intFlat;
            lblCuota = 'Cuota de capital ' + LBL_F[f].toLowerCase();
        } else { // FRANCES
            cuota = (iP > 0 && P > 0) ? P * iP * Math.pow(1 + iP, n) / (Math.pow(1 + iP, n) - 1) : (P / n || 0);
            total = cuota * n;
        }

        var opt = gestor && gestor.selectedOptions[0];
        document.getElementById('rs-gestor').textContent = opt && opt.value ? opt.text : 'Sin asignar';
        document.getElementById('rs-monto').textContent  = P > 0   ? fmt(P) : '—';
        document.getElementById('rs-tasa').textContent   = t.toFixed(2) + '%';
        document.getElementById('rs-plazo').textContent  = mes > 0 ? mes + (mes === 1 ? ' mes' : ' meses') : '—';
        document.getElementById('rs-freq').textContent   = LBL_F[f] + (f === 'DI' ? ' (' + dSem + ' días/sem)' : '');
        document.getElementById('rs-total').textContent  = P > 0 ? fmt(total) : '—';
        document.getElementById('rs-fecha').textContent  = fpp.value
            ? new Date(fpp.value + 'T00:00:00').toLocaleDateString('es-NI', { day: 'numeric', month: 'short', year: 'numeric' }) : '—';
        document.getElementById('rs-cuota').textContent  = fmt(cuota) + (n > 0 && P > 0 ? ' × ' + n : '');
        document.getElementById('rs-cuota-lbl').textContent = lblCuota;
    }

    document.getElementById('btn-plan').addEventListener('click', function () {
        PlanSugerido.mostrar({
            mon: mon, monto: monto.value, tasa: tasa.value, meses: plazo.value,
            freq: freq(), dias: dias.value, tipo: tipo.value, fecha: fpp.value
        });
    });

    document.getElementById('monto-chips').addEventListener('click', function (e) {
        var b = e.target.closest('.mini-chip');
        if (!b) return;
        monto.value = b.dataset.m;
        resumen();
    });

    [monto, tasa, plazo, dias, gestor, tipo, fpp].forEach(function (el) {
        el.addEventListener('change', resumen);
        el.addEventListener('input', resumen);
    });
    form.querySelectorAll('input[name=frecuencia]').forEach(function (r) {
        r.addEventListener('change', resumen);
    });

    form.addEventListener('submit', function (e) {
        if ((parseFloat(monto.value) || 0) < 1000) { e.preventDefault(); alert('El monto mínimo a prestar es ' + mon + ' 1,000.'); monto.focus(); return; }
        var t = parseFloat(tasa.value);
        if (!(t >= 0 && t <= 100)) { e.preventDefault(); alert('La tasa mensual debe ser entre 0 y 100%.'); tasa.focus(); return; }
        if ((parseInt(plazo.value, 10) || 0) <= 0) { e.preventDefault(); alert('Ingresa un plazo válido en meses.'); plazo.focus(); }
    });

    resumen();
})();
