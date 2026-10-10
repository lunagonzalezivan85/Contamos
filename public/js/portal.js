/**
 * Portal del gestor — portal.js
 *  - Pestañas del detalle de cliente (#persona-tabs)
 *  - Modales agregar/editar (data-modal / data-close / data-editar)
 *  - Mapa de dirección: MapLibre + Nominatim + botón GPS
 */
(function () {
    'use strict';

    /* ---------- Pestañas ---------- */
    var personaTabs = document.getElementById('persona-tabs');
    if (personaTabs) {
        var btns   = personaTabs.querySelectorAll('.tab-btn');
        var panels = document.querySelectorAll('.tab-panel');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.dataset.tab;
                btns.forEach(function (b) { b.classList.toggle('on', b === btn); });
                panels.forEach(function (p) { p.classList.toggle('on', p.dataset.panel === tab); });
            });
        });

        // Flechas de scroll — aparecen solo si hay más pestañas que ancho
        var tabsPrev = document.getElementById('tabs-prev');
        var tabsNext = document.getElementById('tabs-next');
        function pintarFlechas() {
            var puede = personaTabs.scrollWidth > personaTabs.clientWidth + 2;
            tabsPrev.hidden = !puede || personaTabs.scrollLeft <= 2;
            tabsNext.hidden = !puede ||
                personaTabs.scrollLeft >= personaTabs.scrollWidth - personaTabs.clientWidth - 2;
        }
        if (tabsPrev && tabsNext) {
            tabsPrev.addEventListener('click', function () {
                personaTabs.scrollBy({ left: -180, behavior: 'smooth' });
            });
            tabsNext.addEventListener('click', function () {
                personaTabs.scrollBy({ left: 180, behavior: 'smooth' });
            });
            personaTabs.addEventListener('scroll', pintarFlechas);
            window.addEventListener('resize', pintarFlechas);
            pintarFlechas();
        }
    }

    /* ---------- Modales ---------- */
    function cerrarModales() {
        document.querySelectorAll('.modal-overlay:not([hidden])').forEach(function (m) { m.hidden = true; });
    }

    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-modal]');
        if (btn) {
            var m = document.getElementById(btn.dataset.modal);
            if (m) {
                resetModalAgregar(m);
                m.hidden = false;
                if (m.id === 'modal-direccion') initGeoMap();
                // Cobro puntual desde la ficha: preselecciona crédito y sugiere la cuota
                if (btn.dataset.sol) {
                    var sel = m.querySelector('select[name="solicitud_id"]');
                    if (sel) {
                        sel.value = btn.dataset.sol;
                        sel.dispatchEvent(new Event('change'));
                    }
                }
                if (btn.dataset.monto) {
                    var inp = m.querySelector('input[name="monto"]');
                    if (inp) inp.value = btn.dataset.monto;
                }
                // Voucher: llenar preview del recibo con los datos del pago
                if (m.id === 'modal-recibo' && btn.dataset.recibo) {
                    var r = {};
                    try { r = JSON.parse(btn.dataset.recibo); } catch (err) {}
                    m._recibo = r;
                    m.querySelector('#rec-num').textContent     = r.num || '';
                    m.querySelector('#rec-cliente').textContent = r.cliente || '';
                    m.querySelector('#rec-credito').textContent = r.credito || '';
                    m.querySelector('#rec-fecha').textContent   = r.fecha || '';
                    m.querySelector('#rec-metodo').textContent  = r.metodo || '';
                    m.querySelector('#rec-estado').textContent  = r.estado || '';
                    m.querySelector('#rec-monto').textContent   = r.monto || '';
                    m.querySelector('#rec-nota').hidden = !r.revision;
                }
            }
            return;
        }
        var ov = e.target.closest('.modal-overlay');
        if (ov && (e.target === ov || e.target.closest('[data-close]'))) ov.hidden = true;

        var it = e.target.closest('[data-editar]');
        if (it && !e.target.closest('.oui-meta') && !e.target.closest('a') && !e.target.closest('form')) {
            abrirEdicionItem(it);
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') cerrarModales();
    });

    // Modal de pago/abono: sugiere el pendiente de la cuota al elegir crédito
    document.addEventListener('change', function (e) {
        var sel = e.target.closest('form[data-action-tpl] select[name="solicitud_id"]');
        if (!sel) return;
        var opt = sel.options[sel.selectedIndex];
        var inp = sel.closest('form').querySelector('input[name="monto"]');
        if (opt && inp && opt.dataset.pendiente) inp.value = opt.dataset.pendiente;
    });

    /* ---------- Recibo del cobro: imprimir popup / compartir imagen ---------- */
    document.addEventListener('click', function (e) {
        var m = document.getElementById('modal-recibo');
        if (!m || !m._recibo) return;

        // Imprimir → popup limpio con el voucher (auto-print para térmica)
        if (e.target.closest('#rec-imprimir')) {
            window.open(m._recibo.url + '?print=1', '_blank', 'width=460,height=760');
            return;
        }

        // Compartir → PNG formato ticket 58mm (térmica PT-210) → Web Share
        if (e.target.closest('#rec-compartir')) {
            var r   = m._recibo;
            var emp = (m.querySelector('#rec-emp') || {}).textContent || 'Contamos';
            dibujarTicket(r, emp);
            return;
        }

        // PDF → ticket vectorial 58mm (Courier-Bold base14, sin raster)
        if (e.target.closest('#rec-pdf')) {
            var emp2 = (m.querySelector('#rec-emp') || {}).textContent || 'Contamos';
            compartirPdf(m._recibo, emp2);
            return;
        }
    });

    /* Ticket térmico 58mm — 384px exactos (1px = 1 punto, 203dpi).
       Todo negrita Courier New: ancho fijo + bold = legible en térmica. */
    function dibujarTicket(r, emp) {
        var W = 384, pad = 16;
        var mono = '"Segoe UI", Arial, sans-serif';
        var SEP1 = '='.repeat(34);                  // separador doble (encabezado)
        var SEP  = '-'.repeat(34);                  // separador simple
        var FH   = '700 22px ' + mono,              // empresa
            FS   = '700 15px ' + mono,              // texto normal
            FB   = '700 16px ' + mono,              // semi-título
            FX   = '700 32px ' + mono;              // monto

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
        cv.width = W; cv.height = H;                  // 384px = 1:1 con los puntos de la PT-210
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
            cx.fillText(i.t, x + 0.5, y);          // doble pasada → más tinta
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
            var file = new File([blob], (r.num || 'recibo') + '.png', { type: 'image/png' });
            if (navigator.canShare && navigator.canShare({ files: [file] })) {
                navigator.share({ files: [file], title: 'Recibo ' + (r.num || '') }).catch(function () {});
            } else {
                var a = document.createElement('a');
                a.href = URL.createObjectURL(blob);
                a.download = file.name;
                a.click();
                URL.revokeObjectURL(a.href);
            }
        }, 'image/png');
    }

    /* PDF del ticket — página 58mm con texto vectorial Courier-Bold
       (base14 PDF, sin raster): la impresora/app lo renderiza nítido. */
    function compartirPdf(r, emp) {
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

        var blob = new Blob([pdf], { type: 'application/pdf' });
        var file = new File([blob], (r.num || 'recibo') + '.pdf', { type: 'application/pdf' });
        if (navigator.canShare && navigator.canShare({ files: [file] })) {
            navigator.share({ files: [file], title: 'Recibo ' + (r.num || '') }).catch(function () {});
        } else {
            var a = document.createElement('a');
            a.href = URL.createObjectURL(blob);
            a.download = file.name;
            a.click();
            URL.revokeObjectURL(a.href);
        }
    }

    // El action depende del crédito elegido: /portal/cobros/{id}/abonar
    // + anti doble-submit: una vez enviado el form queda bloqueado (evita
    // solicitudes/abonos duplicados por doble-tap o Enter repetido).
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form');
        if (!form) return;
        if (form.dataset.actionTpl) {
            var sel = form.querySelector('select[name="solicitud_id"]');
            if (sel && sel.value) {
                form.action = form.dataset.actionTpl.replace('__ID__', sel.value);
            }
        }
        if (e.defaultPrevented || form.dataset.noLock !== undefined) return;
        if (form.dataset.enviado === '1') { e.preventDefault(); return; }
        form.dataset.enviado = '1';
        // Solo feedback visual: disabled aquí excluiría el name=value del
        // submitter del POST; el flag de arriba ya bloquea re-submits.
        form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach(function (b) {
            b.classList.add('enviando');
        });
    });

    // Item clickeable → modal en modo edición (prellena y cambia action a /actualizar)
    function abrirEdicionItem(el) {
        var data = {};
        try { data = JSON.parse(el.getAttribute('data-editar') || '{}'); } catch (err) {}
        var tipo = el.getAttribute('data-tipo');
        var md = document.getElementById('modal-' + tipo);
        if (!md) return;

        var form = md.querySelector('form');
        if (form && data.url) form.action = data.url;

        var titulo = md.querySelector('[data-edit-title]');
        if (titulo && titulo.dataset.editTitle) titulo.textContent = titulo.dataset.editTitle;

        var campos = data.campos || {};
        for (var name in campos) {
            var multi = md.querySelectorAll('[name="' + name + '[]"]');
            if (multi.length) {
                var marcados = String(campos[name] || '').split(',');
                multi.forEach(function (cb) { cb.checked = marcados.indexOf(cb.value) !== -1; });
                continue;
            }
            var inp = md.querySelector('[name="' + name + '"]');
            if (inp) inp.value = campos[name];
        }

        var archivo = md.querySelector('input[type="file"][name="archivo"]');
        if (archivo) archivo.required = false;

        md.hidden = false;
        if (tipo === 'direccion' && typeof initGeoMap === 'function') {
            initGeoMap();
            var lat = parseFloat(campos.latitud), lng = parseFloat(campos.longitud);
            if (!isNaN(lat) && !isNaN(lng)) geoMarcar({ lat: lat, lng: lng });
        }
    }

    function resetModalAgregar(md) {
        var form = md.querySelector('form');
        if (form) {
            if (form.dataset.addUrl) form.action = form.dataset.addUrl;
            form.reset();
            var archivo = form.querySelector('input[type="file"][name="archivo"]');
            if (archivo) archivo.required = true;
        }
        var titulo = md.querySelector('[data-add-title]');
        if (titulo && titulo.dataset.addTitle) titulo.textContent = titulo.dataset.addTitle;
    }

    /* ---------- Dirección — mapa MapLibre GL + geocodificación (Nominatim) ---------- */
    var geoMap = null, geoMarker = null;
    var GEO_STYLE_MAPA = 'https://tiles.openfreemap.org/styles/liberty';   // gratis, sin key
    var GEO_STYLE_SAT  = {
        version: 8,
        sources: { sat: { type: 'raster', tiles: ['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'], tileSize: 256 } },
        layers: [{ id: 'sat', type: 'raster', source: 'sat' }]
    };
    var geoSatOn = false;

    function initGeoMap() {
        var el = document.getElementById('geo-mapa');
        if (!el || typeof maplibregl === 'undefined') return;
        if (!geoMap) {
            geoMap = new maplibregl.Map({
                container: 'geo-mapa',
                style: GEO_STYLE_MAPA,
                center: [-86.2362, 12.1150],   // Managua, Nicaragua — [lng, lat]
                zoom: 12
            });
            geoMap.addControl(new maplibregl.NavigationControl());
            geoMap.on('click', function (e) { geoPoner(e.lngLat); });

            var tog = document.getElementById('geo-toggle-sat');
            if (tog) {
                tog.addEventListener('click', function () {
                    geoSatOn = !geoSatOn;
                    geoMap.setStyle(geoSatOn ? GEO_STYLE_SAT : GEO_STYLE_MAPA);
                    tog.textContent = geoSatOn ? 'Mapa' : 'Satélite';
                });
            }
        }
        setTimeout(function () { geoMap.resize(); }, 180);
    }

    // Coloca/mueve marcador y llena lat/lon (sin reverse geocode — para editar)
    function geoMarcar(latlng) {
        if (!geoMap) return;
        if (!geoMarker) {
            geoMarker = new maplibregl.Marker({ draggable: true })
                .setLngLat([latlng.lng, latlng.lat]).addTo(geoMap);
            geoMarker.on('dragend', function () { geoPoner(geoMarker.getLngLat()); });
        } else {
            geoMarker.setLngLat([latlng.lng, latlng.lat]);
        }
        geoMap.jumpTo({ center: [latlng.lng, latlng.lat], zoom: 16 });
        geoSet('geo-latitud', latlng.lat.toFixed(7));
        geoSet('geo-longitud', latlng.lng.toFixed(7));
    }

    function geoPoner(latlng) {
        geoMarcar(latlng);
        if (!geoMap) return;
        geoMap.easeTo({ center: [latlng.lng, latlng.lat] });

        // Reverse geocoding → rellena departamento/ciudad/barrio
        fetch('https://nominatim.openstreetmap.org/reverse?format=jsonv2'
            + '&lat=' + latlng.lat + '&lon=' + latlng.lng + '&accept-language=es')
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var a = d.address || {};
                geoSet('geo-departamento', a.state || a.region || a.province || '');
                geoSet('geo-ciudad',       a.city || a.town || a.municipality || a.village || '');
                geoSet('geo-barrio',       a.neighbourhood || a.suburb || a.quarter || a.district || '');
                geoSet('geo-detalle',      d.display_name || '');
            })
            .catch(function () {});
    }

    function geoSet(id, v) {
        var el = document.getElementById(id);
        if (el && v) el.value = v;
    }

    function geoBuscar() {
        var inp = document.getElementById('geo-buscar');
        var q = inp ? inp.value.trim() : '';
        if (!q) return;
        fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&q=' + encodeURIComponent(q)
            + '&countrycodes=ni&limit=1&addressdetails=1&accept-language=es')
            .then(function (r) { return r.json(); })
            .then(function (res) {
                if (res && res[0]) {
                    var ll = { lat: parseFloat(res[0].lat), lng: parseFloat(res[0].lon) };
                    geoPoner(ll);
                    geoMap.jumpTo({ center: [ll.lng, ll.lat], zoom: 16 });
                } else {
                    alert('No se encontró la ubicación. Intente con más detalle.');
                }
            })
            .catch(function () { alert('Error al buscar la ubicación.'); });
    }

    var geoBtn = document.getElementById('geo-buscar-btn');
    if (geoBtn) geoBtn.addEventListener('click', geoBuscar);
    var geoInp = document.getElementById('geo-buscar');
    if (geoInp) {
        geoInp.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); geoBuscar(); }
        });
    }

    /* ---------- Buscador de lista: <input data-buscar="#sel .item" data-empty="id"> ---------- */
    document.querySelectorAll('[data-buscar]').forEach(function (inp) {
        var items = document.querySelectorAll(inp.dataset.buscar);
        var vacio = inp.dataset.empty ? document.getElementById(inp.dataset.empty) : null;
        var norm = function (s) {
            return (s || '').toLowerCase()
                .normalize('NFD').replace(/[̀-ͯ]/g, '');   // sin tildes
        };
        inp.addEventListener('input', function () {
            var q = norm(inp.value.trim());
            var visibles = 0;
            items.forEach(function (it) {
                var ok = q === '' || norm(it.textContent).indexOf(q) !== -1;
                it.style.display = ok ? '' : 'none';
                if (ok) visibles++;
            });
            if (vacio) vacio.hidden = visibles > 0;
        });
    });

    // Botón GPS — captura la ubicación actual del gestor
    var gpsBtn = document.getElementById('geo-gps');
    if (gpsBtn && navigator.geolocation) {
        gpsBtn.addEventListener('click', function () {
            gpsBtn.disabled = true;
            navigator.geolocation.getCurrentPosition(function (pos) {
                if (geoMap) {
                    geoPoner({ lat: pos.coords.latitude, lng: pos.coords.longitude });
                    geoMap.jumpTo({ center: [pos.coords.longitude, pos.coords.latitude], zoom: 17 });
                } else {
                    geoSet('geo-latitud', pos.coords.latitude.toFixed(7));
                    geoSet('geo-longitud', pos.coords.longitude.toFixed(7));
                }
                gpsBtn.disabled = false;
            }, function () {
                alert('No se pudo obtener la ubicación. Revisá los permisos de GPS.');
                gpsBtn.disabled = false;
            }, { enableHighAccuracy: true, timeout: 10000 });
        });
    }
})();
