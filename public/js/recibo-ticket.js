/* Ticket térmico 58mm compartido — portal gestor y portal tenant.
   Uso: TicketRecibo.png(r, emp) → PNG 384px · TicketRecibo.pdf(r, emp) → PDF vectorial.
   Botones: data-tkt="png"|"pdf" + data-recibo='{json}' (+ data-emp opcional). */
var TicketRecibo = (function () {
    'use strict';

    /* ---- PNG: 384px exactos (1px = 1 punto, 203dpi), B/N puro ---- */
    function png(r, emp) {
        var W = 384, pad = 16;
        var mono = '"Segoe UI", Arial, sans-serif';
        var SEP1 = '='.repeat(34), SEP = '-'.repeat(34);
        var FH = '700 22px ' + mono, FS = '700 15px ' + mono,
            FB = '700 16px ' + mono, FX = '700 32px ' + mono;

        var items = [];
        function add(t, f, a, lh, g) { items.push({ t: t, f: f, a: a, lh: lh, g: g || 0 }); }
        function wrap(t, f, a, lh, g, max) {
            var ln = '';
            String(t).split(' ').forEach(function (w) {
                var x = ln ? ln + ' ' + w : w;
                if (x.length > max) { add(ln, f, a, lh, 0); ln = w; } else { ln = x; }
            });
            if (ln) add(ln, f, a, lh, g);
        }

        wrap(String(emp).toUpperCase(), FH, 'center', 26, 0, 20);
        add(SEP1, FS, 'center', 16, 4);
        add('RECIBO DE PAGO', FB, 'center', 20, 2);
        add(r.num || '', FH, 'center', 26, 4);
        add(SEP, FS, 'center', 14, 4);
        [['Cliente', r.cliente], ['Credito', r.credito], ['Fecha', r.fecha],
         ['Metodo', r.metodo],  ['Estado', r.estado],  ['Gestor', r.gestor]]
            .forEach(function (f) { wrap(f[0] + ': ' + (f[1] || '-'), FS, 'left', 20, 0, 36); });
        if (r.saldo) wrap('Saldo pend.: C$ ' + r.saldo, FS, 'left', 20, 0, 36);
        add(SEP, FS, 'center', 14, 6);
        add('MONTO PAGADO', FS, 'center', 18, 2);
        add('C$ ' + (r.monto || ''), FX, 'center', 38, 6);
        add(SEP1, FS, 'center', 16, 4);
        if (r.revision) {
            wrap('** PAGO EN REVISION **', FB, 'center', 20, 0, 34);
            wrap('Se aplica al plan cuando oficina valide la transferencia.',
                 FS, 'center', 17, 4, 36);
            add(SEP, FS, 'center', 14, 4);
        }
        add('Gracias por su pago', FS, 'center', 20, 0);
        add('contamos.softlutionic.com', FS, 'center', 18, 0);

        var H = 28;
        items.forEach(function (i) { H += i.lh + i.g; });
        var cv = document.createElement('canvas');
        cv.width = W; cv.height = H;
        var cx = cv.getContext('2d');
        cx.fillStyle = '#fff'; cx.fillRect(0, 0, W, H);
        cx.fillStyle = '#000';
        var y = 12;
        items.forEach(function (i) {
            y += i.lh;
            cx.font = i.f;
            cx.textAlign = i.a;
            var x = i.a === 'center' ? W / 2 : pad;
            cx.fillText(i.t, x, y);
            cx.fillText(i.t, x + 0.5, y);
            y += i.g;
        });

        // Umbral a B/N puro: la térmica difumina los grises del antialias
        var img = cx.getImageData(0, 0, W, H), d = img.data;
        for (var px = 0; px < d.length; px += 4) {
            var v = d[px] < 190 ? 0 : 255;
            d[px] = d[px + 1] = d[px + 2] = v;
        }
        cx.putImageData(img, 0, 0);

        cv.toBlob(function (blob) {
            if (!blob) return;
            enviar(new File([blob], (r.num || 'recibo') + '.png', { type: 'image/png' }), r);
        }, 'image/png');
    }

    /* ---- PDF: página 58mm, Courier-Bold base14 vectorial ---- */
    function pdf(r, emp) {
        var MM = 72 / 25.4, pw = 58 * MM, margen = 3 * MM;
        var clean = function (s) {
            return String(s == null ? '' : s)
                .normalize('NFD').replace(/[̀-ͯ]/g, '')
                .replace(/[^\x20-\x7E]/g, ' ');
        };
        var lines = [];
        function addL(t, s, c, adv) { lines.push({ t: t, s: s, c: !!c, adv: adv }); }
        function wrapL(t, s, c, adv) {
            var max = Math.floor((pw - 2 * margen) / (0.6 * s));
            var ln = '';
            clean(t).split(' ').forEach(function (w) {
                var x = ln ? ln + ' ' + w : w;
                if (x.length > max) { addL(ln, s, c, adv); ln = w; } else { ln = x; }
            });
            if (ln) addL(ln, s, c, adv);
        }
        var SEP1 = '='.repeat(32), SEP = '-'.repeat(32);

        wrapL(String(emp).toUpperCase(), 11, true, 13);
        addL(SEP1, 8, true, 10);
        addL('RECIBO DE PAGO', 9, true, 11);
        addL(r.num || '', 10, true, 13);
        addL(SEP, 8, true, 11);
        [['Cliente', r.cliente], ['Credito', r.credito], ['Fecha', r.fecha],
         ['Metodo', r.metodo],  ['Estado', r.estado],  ['Gestor', r.gestor]]
            .forEach(function (f) { wrapL(f[0] + ': ' + (f[1] || '-'), 7.5, false, 10); });
        if (r.saldo) wrapL('Saldo pend.: C$ ' + r.saldo, 7.5, false, 10);
        addL(SEP, 8, true, 11);
        addL('MONTO PAGADO', 7.5, true, 10);
        addL('C$ ' + (r.monto || ''), 14, true, 18);
        addL(SEP1, 8, true, 12);
        if (r.revision) {
            wrapL('** PAGO EN REVISION **', 8, true, 11);
            wrapL('Se aplica al plan cuando oficina valide la transferencia.', 7, true, 9);
            addL(SEP, 8, true, 11);
        }
        addL('Gracias por su pago', 7.5, true, 10);
        addL('contamos.softlutionic.com', 7, true, 10);

        var ph = 8 + lines.reduce(function (a, l) { return a + l.adv; }, 0) + 8;
        var stream = '', y = ph - 8;
        lines.forEach(function (l) {
            y -= l.adv;
            var x = l.c ? (pw - l.t.length * 0.6 * l.s) / 2 : margen;
            var esc = l.t.replace(/\\/g, '\\\\').replace(/\(/g, '\\(').replace(/\)/g, '\\)');
            stream += 'BT /F1 ' + l.s + ' Tf ' + x.toFixed(2) + ' ' + y.toFixed(2)
                    + ' Td (' + esc + ') Tj ET\n';
        });

        var objs = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' + pw.toFixed(2) + ' ' + ph.toFixed(2)
                + '] /Contents 4 0 R /Resources << /Font << /F1 5 0 R >> >> >>',
            '<< /Length ' + stream.length + ' >>\nstream\n' + stream + 'endstream',
            '<< /Type /Font /Subtype /Type1 /BaseFont /Courier-Bold /Encoding /WinAnsiEncoding >>'
        ];
        var pdf = '%PDF-1.4\n', offs = [];
        objs.forEach(function (o, i) {
            offs.push(pdf.length);
            pdf += (i + 1) + ' 0 obj\n' + o + '\nendobj\n';
        });
        var xref = pdf.length;
        pdf += 'xref\n0 ' + (objs.length + 1) + '\n0000000000 65535 f \n';
        offs.forEach(function (o) { pdf += String(o).padStart(10, '0') + ' 00000 n \n'; });
        pdf += 'trailer\n<< /Size ' + (objs.length + 1) + ' /Root 1 0 R >>\nstartxref\n' + xref + '\n%%EOF';

        enviar(new File([new Blob([pdf], { type: 'application/pdf' })],
            (r.num || 'recibo') + '.pdf', { type: 'application/pdf' }), r);
    }

    function enviar(file, r) {
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            navigator.share({ files: [file], title: 'Recibo ' + (r.num || '') }).catch(function () {});
        } else {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(file);
            a.download = file.name;
            a.click();
            URL.revokeObjectURL(a.href);
        }
    }

    // Botones declarativos: <button data-tkt="png|pdf" data-recibo='{...}' data-emp="...">
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-tkt]');
        if (!btn) return;
        var r = {};
        try { r = JSON.parse(btn.dataset.recibo || '{}'); } catch (err) {}
        var emp = btn.dataset.emp || 'Contamos';
        (btn.dataset.tkt === 'pdf' ? pdf : png)(r, emp);
    });

    return { png: png, pdf: pdf };
})();
