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

        // Compartir → canvas PNG → Web Share (móvil) o descarga
        if (e.target.closest('#rec-compartir')) {
            var r = m._recibo;
            var cv = document.createElement('canvas');
            cv.width = 760;
            var cx = cv.getContext('2d');
            var W = cv.width, pad = 44;
            var filas = [
                ['Cliente', r.cliente], ['Crédito', r.credito],
                ['Fecha', r.fecha], ['Método', r.metodo], ['Estado', r.estado],
            ];

            // Alto estimado + wrap de nota
            var nota = r.revision
                ? 'PAGO EN REVISION — este comprobante no confirma el abono. Se aplica al plan de cuotas cuando oficina lo valide en cuentas bancarias o caja.'
                : null;
            cv.height = 430 + (nota ? 130 : 0);

            cx.fillStyle = '#ffffff'; cx.fillRect(0, 0, W, cv.height);
            var y = 52;
            cx.textAlign = 'center'; cx.fillStyle = '#172B4D';
            cx.font = '700 30px Segoe UI, Arial';
            cx.fillText((m.querySelector('#rec-emp') || {}).textContent || '', W / 2, y);
            y += 26; cx.fillStyle = '#64748B'; cx.font = '600 15px Segoe UI, Arial';
            cx.fillText('RECIBO DE PAGO', W / 2, y);
            y += 34; cx.fillStyle = '#0E8A6A'; cx.font = '800 26px Segoe UI, Arial';
            cx.fillText(r.num || '', W / 2, y);
            y += 20;
            cx.strokeStyle = '#CBD5E1'; cx.setLineDash([8, 6]);
            cx.beginPath(); cx.moveTo(pad, y); cx.lineTo(W - pad, y); cx.stroke();
            cx.setLineDash([]); cx.textAlign = 'left';
            y += 26;
            filas.forEach(function (f) {
                cx.fillStyle = '#64748B'; cx.font = '600 14px Segoe UI, Arial';
                cx.fillText(f[0].toUpperCase(), pad, y);
                cx.fillStyle = '#172B4D'; cx.font = '600 17px Segoe UI, Arial';
                cx.textAlign = 'right'; cx.fillText(String(f[1] || ''), W - pad, y);
                cx.textAlign = 'left';
                y += 30;
            });
            y += 12;
            cx.fillStyle = '#E6F9F1'; cx.fillRect(0, y, W, 78);
            cx.fillStyle = '#172B4D'; cx.font = '800 40px Segoe UI, Arial'; cx.textAlign = 'center';
            cx.fillText('C$ ' + (r.monto || ''), W / 2, y + 52);
            y += 78;
            if (nota) {
                cx.fillStyle = '#FFF4E5'; cx.fillRect(0, y, W, 110);
                cx.fillStyle = '#B45309'; cx.font = '600 13px Segoe UI, Arial';
                var palabras = nota.split(' '), linea = '', ly = y + 24;
                palabras.forEach(function (p) {
                    var t = linea + p + ' ';
                    if (cx.measureText(t).width > W - 2 * pad) {
                        cx.fillText(linea.trim(), W / 2, ly); linea = p + ' '; ly += 20;
                    } else { linea = t; }
                });
                cx.fillText(linea.trim(), W / 2, ly);
            }
            cx.textAlign = 'left';

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
            return;
        }
    });

    // El action depende del crédito elegido: /portal/cobros/{id}/abonar
    document.addEventListener('submit', function (e) {
        var form = e.target.closest('form[data-action-tpl]');
        if (!form) return;
        var sel = form.querySelector('select[name="solicitud_id"]');
        if (sel && sel.value) {
            form.action = form.dataset.actionTpl.replace('__ID__', sel.value);
        }
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
