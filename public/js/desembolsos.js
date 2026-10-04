/* ==========================================================================
   Ruta de desembolsos — panel del tenant (/credito/desembolsar)
   - Paradas = solicitudes en estado DESEMBOLSO (dinero por entregar).
   - Orden: manual (flechas) o "Orden sugerido" (vecino más cercano desde GPS).
   - Distancias: haversine (línea recta, sin tráfico), medida desde la parada
     anterior de la ruta.
   - Ruta dibujada: OSRM (calles); si falla, polyline recta.
   ========================================================================== */
(function () {
    var app = document.getElementById('ruta-app');
    var jsonEl = document.getElementById('ruta-paradas');
    if (!app || !jsonEl) return;

    var mon = app.dataset.mon || 'C$';
    var det = (app.dataset.det || '').replace(/\/+$/, '');   // base_url('credito/solicitudes')

    var paradas = [];
    try { paradas = JSON.parse(jsonEl.textContent) || []; } catch (e) { paradas = []; }
    if (!paradas.length) return;

    var elMapaCard = document.getElementById('ruta-mapa-card');
    var elLista    = document.getElementById('ruta-lista');
    var elGps      = document.getElementById('ruta-gps');
    var elRes      = document.getElementById('ruta-resumen');
    var btnMapa    = document.getElementById('ruta-mapa-btn');
    var btnGps     = document.getElementById('ruta-gps-btn');
    var btnOrden   = document.getElementById('ruta-orden-btn');
    var btnPick    = document.getElementById('ruta-pick-btn');
    var selGestor  = document.getElementById('ruta-gestor');
    var modal      = document.getElementById('modal-paradas');
    var btnAplicar = document.getElementById('ruta-aplicar');
    var btnTodos   = document.getElementById('ruta-todos');
    var btnNinguno = document.getElementById('ruta-ninguno');

    var STYLE = 'https://tiles.openfreemap.org/styles/liberty'; // mismo estilo que ruta de cobro

    var map = null, miMarker = null;
    var markers = [];
    var miPos = null;                       // {lat, lng}
    var orden = paradas.map(function (p) { return p.sol_id; });  // orden actual de la ruta
    var seleccion = {};                     // sol_id -> bool
    var gestorFiltro = '';                  // '' = todos

    paradas.forEach(function (p) { seleccion[p.sol_id] = true; });

    /* ---------- helpers ---------- */
    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : String(s);
        return d.innerHTML;
    }
    function fmt(n) {
        return mon + ' ' + n.toLocaleString('es-NI', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }
    // Distancia en km entre dos puntos (haversine)
    function distKm(a, b) {
        var R = 6371;
        var dLat = (b.lat - a.lat) * Math.PI / 180;
        var dLng = (b.lng - a.lng) * Math.PI / 180;
        var la1 = a.lat * Math.PI / 180, la2 = b.lat * Math.PI / 180;
        var h = Math.sin(dLat / 2) * Math.sin(dLat / 2)
            + Math.cos(la1) * Math.cos(la2) * Math.sin(dLng / 2) * Math.sin(dLng / 2);
        return 2 * R * Math.asin(Math.sqrt(h));
    }
    function fmtKm(km) {
        return km == null ? '—' : (km < 1 ? Math.round(km * 1000) + ' m' : km.toFixed(1) + ' km');
    }
    function urlMaps(p) {
        return p.lat != null
            ? 'https://www.google.com/maps/dir/?api=1&destination=' + p.lat + ',' + p.lng
            : 'https://www.google.com/maps/dir/?api=1&destination=' + encodeURIComponent(p.detalle || p.dir || p.nombre);
    }
    function urlWaze(p) {
        return p.lat != null
            ? 'https://waze.com/ul?ll=' + p.lat + '%2C' + p.lng + '&navigate=yes'
            : 'https://waze.com/ul?q=' + encodeURIComponent(p.detalle || p.dir || p.nombre);
    }
    function telWa(p) {
        var t = String(p.telefono || '').replace(/\D/g, '');
        if (t.length === 8) t = '505' + t;   // Nicaragua por defecto
        return t ? 'https://wa.me/' + t : '';
    }
    function conGps(lista) {
        return lista.filter(function (p) { return p.lat != null && p.lng != null; });
    }
    function porId(id) {
        for (var i = 0; i < paradas.length; i++) if (paradas[i].sol_id === id) return paradas[i];
        return null;
    }

    /* ---------- selección y orden ---------- */
    // Paradas visibles (checkbox + filtro gestor) en el orden actual de la ruta
    function visibles() {
        var vis = [];
        orden.forEach(function (id) {
            var p = porId(id);
            if (!p || !seleccion[id]) return;
            if (gestorFiltro !== '' && String(p.gestor_id) !== gestorFiltro) return;
            vis.push(p);
        });
        return vis;
    }
    // Posiciones dentro de `orden` que ocupan las paradas visibles
    function idxsVisibles() {
        var vis = visibles();
        var porSol = {};
        vis.forEach(function (p) { porSol[p.sol_id] = true; });
        var idxs = [];
        orden.forEach(function (id, i) { if (porSol[id]) idxs.push(i); });
        return idxs;
    }
    // Reubica la lista visible en `orden` conservando los huecos de las ocultas
    function aplicarOrdenVisibles(nuevaVis) {
        var idxs = idxsVisibles();
        nuevaVis.forEach(function (p, k) { orden[idxs[k]] = p.sol_id; });
    }
    // Vecino más cercano desde `origen` (o desde la 1ra con GPS); sin GPS al final
    function ordenSugerido(lista, origen) {
        var geo = conGps(lista);
        var sinGps = lista.filter(function (p) { return p.lat == null || p.lng == null; });
        if (!geo.length) return lista.slice();
        var cur = origen || geo[0];
        var pend = geo.slice(), out = [];
        while (pend.length) {
            var best = 0, bestD = Infinity;
            pend.forEach(function (p, i) {
                var d = distKm(cur, p);
                if (d < bestD) { bestD = d; best = i; }
            });
            var sig = pend.splice(best, 1)[0];
            out.push(sig);
            cur = sig;
        }
        return out.concat(sinGps);
    }
    // Mueve la parada visible i hacia arriba (-1) o abajo (+1)
    function mover(i, dir) {
        var idxs = idxsVisibles();
        var j = i + dir;
        if (j < 0 || j >= idxs.length) return;
        var a = orden[idxs[i]], b = orden[idxs[j]];
        orden[idxs[i]] = b; orden[idxs[j]] = a;
        refrescar();
    }

    /* ---------- mapa ---------- */
    function initMap() {
        if (!elMapaCard || typeof maplibregl === 'undefined' || map) return;
        map = new maplibregl.Map({
            container: 'ruta-mapa',
            style: STYLE,
            center: [-86.2362, 12.1150],   // Managua
            zoom: 12
        });
        map.addControl(new maplibregl.NavigationControl());
        map.on('load', function () {
            map.addSource('ruta-linea', {
                type: 'geojson',
                data: { type: 'Feature', geometry: { type: 'LineString', coordinates: [] } }
            });
            map.addLayer({
                id: 'ruta-linea', type: 'line', source: 'ruta-linea',
                paint: { 'line-color': '#30CB9A', 'line-width': 4, 'line-opacity': .85 }
            });
            dibujar();
        });
    }

    function markerNum(n) {
        var el = document.createElement('div');
        el.className = 'ruta-mk';
        el.textContent = n;
        return el;
    }

    function limpiarMarkers() {
        markers.forEach(function (m) { m.remove(); });
        markers = [];
    }

    function dibujar() {
        if (!map || !map.isStyleLoaded()) return;
        limpiarMarkers();
        var lista = visibles();
        var bounds = null;

        lista.forEach(function (p, i) {
            if (p.lat == null) return;
            var mk = new maplibregl.Marker({ element: markerNum(i + 1) })
                .setLngLat([p.lng, p.lat])
                .setPopup(new maplibregl.Popup({ offset: 22 }).setHTML(
                    '<strong>' + esc(p.nombre) + '</strong><br>' + esc(p.codigo) + ' · ' + fmt(p.monto)
                    + '<br>' + esc(p.gestor)
                ))
                .addTo(map);
            markers.push(mk);
            if (!bounds) bounds = new maplibregl.LngLatBounds([p.lng, p.lat], [p.lng, p.lat]);
            else bounds.extend([p.lng, p.lat]);
        });

        if (miPos) {
            if (!miMarker) {
                var el = document.createElement('div');
                el.className = 'ruta-mk ruta-mk-mio';
                el.title = 'Mi ubicación';
                miMarker = new maplibregl.Marker({ element: el });
            }
            miMarker.setLngLat([miPos.lng, miPos.lat]).addTo(map);
            if (!bounds) bounds = new maplibregl.LngLatBounds([miPos.lng, miPos.lat], [miPos.lng, miPos.lat]);
            else bounds.extend([miPos.lng, miPos.lat]);
        }

        trazarRuta(conGps(lista));
        if (bounds) map.fitBounds(bounds, { padding: 60, maxZoom: 15 });
    }

    // Ruta por calles con OSRM (gratis, sin key); fallback: línea recta
    function trazarRuta(geo) {
        var src = map.getSource('ruta-linea');
        if (!src) return;

        var pts = [];
        if (miPos) pts.push([miPos.lng, miPos.lat]);
        geo.forEach(function (p) { pts.push([p.lng, p.lat]); });

        var recta = {
            type: 'Feature',
            geometry: { type: 'LineString', coordinates: pts }
        };
        if (pts.length < 2) { src.setData(recta); return; }

        var url = 'https://router.project-osrm.org/route/v1/driving/'
            + pts.map(function (c) { return c.join(','); }).join(';')
            + '?overview=full&geometries=geojson';

        fetch(url)
            .then(function (r) { return r.json(); })
            .then(function (d) {
                var geom = d && d.routes && d.routes[0] && d.routes[0].geometry;
                var leg = d && d.routes && d.routes[0] ? d.routes[0].distance / 1000 : null;
                src.setData(geom ? { type: 'Feature', geometry: geom } : recta);
                if (elRes) {
                    elRes.textContent = leg != null
                        ? 'Ruta por calles ≈ ' + leg.toFixed(1) + ' km (OSRM, sin tráfico) · distancias de la lista son lineales'
                        : 'Distancia lineal entre paradas, sin tráfico.';
                }
            })
            .catch(function () { src.setData(recta); });
    }

    /* ---------- lista ---------- */
    function renderLista() {
        var lista = visibles();
        if (!lista.length) {
            elLista.innerHTML = '<p class="geo-hint">Sin entregas seleccionadas.</p>';
            return;
        }
        var prev = miPos;   // distancia medida desde la parada anterior (o mi GPS)
        elLista.innerHTML = lista.map(function (p, i) {
            var dist = null;
            if (p.lat != null) {
                if (prev) dist = distKm(prev, p);
                prev = p;
            }
            var wa = telWa(p);
            var hoy = p.fecha && p.fecha <= new Date().toISOString().slice(0, 10);
            return '<div class="ruta-item' + (hoy ? ' vencida' : '') + '">'
                + '<span class="ruta-item-num">' + (i + 1) + '</span>'
                + '<div class="ruta-item-body">'
                + '<a class="ruta-item-nom" href="' + esc(det) + '/' + p.sol_id + '">'
                + esc(p.nombre) + (hoy ? ' <span class="ruta-badge-mora">hoy</span>' : '') + '</a>'
                + '<span class="ruta-item-sub">' + esc(p.codigo) + ' · ' + fmt(p.monto)
                + ' · entrega ' + esc(p.fecha || 'sin fecha') + ' · ' + esc(p.gestor)
                + (p.dir ? ' · ' + esc(p.dir) : '')
                + (p.lat == null ? ' · <em>sin GPS</em>' : '') + '</span>'
                + '<span class="map-links">'
                + '<a class="map-btn map-waze" href="' + esc(urlWaze(p)) + '" target="_blank" rel="noopener">Waze</a>'
                + '<a class="map-btn map-gmaps" href="' + esc(urlMaps(p)) + '" target="_blank" rel="noopener">Google Maps</a>'
                + (wa ? '<a class="map-btn map-wa" href="' + esc(wa) + '" target="_blank" rel="noopener">WhatsApp</a>' : '')
                + '</span>'
                + '</div>'
                + '<span class="ruta-mover">'
                + '<button type="button" class="ruta-mv" data-i="' + i + '" data-d="-1" title="Subir"' + (i === 0 ? ' disabled' : '') + '>▲</button>'
                + '<button type="button" class="ruta-mv" data-i="' + i + '" data-d="1" title="Bajar"' + (i === lista.length - 1 ? ' disabled' : '') + '>▼</button>'
                + '</span>'
                + '<span class="ruta-dist">' + fmtKm(dist) + '</span>'
                + '</div>';
        }).join('');

        elLista.querySelectorAll('.ruta-mv').forEach(function (b) {
            b.addEventListener('click', function () {
                mover(parseInt(b.dataset.i, 10), parseInt(b.dataset.d, 10));
            });
        });
    }

    function refrescar() {
        renderLista();
        if (map && map.isStyleLoaded()) dibujar();
    }

    /* ---------- GPS ---------- */
    function localizar() {
        if (!navigator.geolocation) {
            elGps.textContent = 'Tu dispositivo no tiene GPS — se muestra el orden por fecha de entrega.';
            return;
        }
        elGps.textContent = 'Obteniendo tu ubicación…';
        navigator.geolocation.getCurrentPosition(function (pos) {
            miPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            elGps.textContent = 'Ubicación lista — usá «Orden sugerido» para ordenar desde donde estás.';
            refrescar();
        }, function () {
            elGps.textContent = 'No se pudo obtener tu ubicación — activa el GPS o el permiso de ubicación.';
        }, { enableHighAccuracy: true, timeout: 12000, maximumAge: 30000 });
    }

    /* ---------- modal ---------- */
    function abrirModal() { modal.hidden = false; }
    function cerrarModal() { modal.hidden = true; }

    if (btnPick) btnPick.addEventListener('click', abrirModal);
    if (modal) {
        modal.querySelectorAll('[data-close]').forEach(function (b) {
            b.addEventListener('click', cerrarModal);
        });
        modal.addEventListener('click', function (e) { if (e.target === modal) cerrarModal(); });
    }
    function checkAll(v) {
        modal.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = v; });
    }
    if (btnTodos)   btnTodos.addEventListener('click', function () { checkAll(true); });
    if (btnNinguno) btnNinguno.addEventListener('click', function () { checkAll(false); });
    if (btnAplicar) btnAplicar.addEventListener('click', function () {
        modal.querySelectorAll('input[type="checkbox"]').forEach(function (c) {
            seleccion[c.value] = c.checked;
        });
        cerrarModal();
        refrescar();
    });

    /* ---------- botones ---------- */
    if (btnMapa) btnMapa.addEventListener('click', function () {
        var oculto = elMapaCard.hidden;
        elMapaCard.hidden = !oculto;
        btnMapa.innerHTML = oculto ? 'Ocultar mapa' : 'Ver mapa';
        if (oculto) { initMap(); dibujar(); }
    });
    if (btnGps) btnGps.addEventListener('click', localizar);
    if (btnOrden) btnOrden.addEventListener('click', function () {
        aplicarOrdenVisibles(ordenSugerido(visibles(), miPos));
        elGps.textContent = miPos
            ? 'Orden sugerido aplicado — de la parada más cercana a la más lejana.'
            : 'Orden sugerido aplicado — sin tu GPS, parte de la primera parada con GPS.';
        refrescar();
    });
    if (selGestor) selGestor.addEventListener('change', function () {
        gestorFiltro = selGestor.value;
        refrescar();
    });

    /* ---------- arranque ---------- */
    renderLista();
    localizar();   // pedir GPS de una vez
})();
