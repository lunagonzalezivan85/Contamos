/* ==========================================================================
   Ruta de cobro — portal del gestor (/{slug}/portal/mapa)
   - Paradas = clientes con cuota que vence hoy o vencida (server).
   - Orden sugerido: vecino más cercano desde la posición GPS del gestor.
   - Distancias: haversine (línea recta, sin tráfico).
   - Ruta dibujada: OSRM (calles); si falla, polyline recta.
   ========================================================================== */
(function () {
    var app = document.getElementById('ruta-app');
    var jsonEl = document.getElementById('ruta-paradas');
    if (!app || !jsonEl) return;

    var mon  = app.dataset.mon || 'C$';
    var slug = app.dataset.slug || '';

    var paradas = [];
    try { paradas = JSON.parse(jsonEl.textContent) || []; } catch (e) { paradas = []; }

    var elMapa   = document.getElementById('ruta-mapa');
    var elLista  = document.getElementById('ruta-lista');
    var elGps    = document.getElementById('ruta-gps');
    var elRes    = document.getElementById('ruta-resumen');
    var btnGps   = document.getElementById('ruta-gps-btn');
    var btnPick  = document.getElementById('ruta-pick-btn');
    var modal    = document.getElementById('modal-paradas');
    var btnAplicar = document.getElementById('ruta-aplicar');
    var btnTodos   = document.getElementById('ruta-todos');
    var btnNinguno = document.getElementById('ruta-ninguno');

    var STYLE = 'https://tiles.openfreemap.org/styles/liberty'; // mismo estilo que ficha de cliente

    var map = null, miMarker = null;
    var markers = [];           // markers de paradas activas
    var miPos = null;           // {lat, lng}
    var seleccion = {};         // sol_id -> bool (checkboxes del modal)

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
    function activas() {
        return paradas.filter(function (p) { return seleccion[p.sol_id]; });
    }
    function conGps(lista) {
        return lista.filter(function (p) { return p.lat != null && p.lng != null; });
    }

    // Orden vecino-más-cercano desde miPos (sin GPS: respeta orden del server)
    function ordenar(lista) {
        var geo = conGps(lista);
        var sinGps = lista.filter(function (p) { return p.lat == null || p.lng == null; });
        if (!miPos) return lista.slice();
        var pend = geo.slice();
        var orden = [];
        var cur = miPos;
        while (pend.length) {
            var best = 0, bestD = Infinity;
            pend.forEach(function (p, i) {
                var d = distKm(cur, p);
                if (d < bestD) { bestD = d; best = i; }
            });
            var sig = pend.splice(best, 1)[0];
            orden.push(sig);
            cur = sig;
        }
        return orden.concat(sinGps);   // los sin GPS van al final
    }

    /* ---------- mapa ---------- */
    function initMap() {
        if (!elMapa || typeof maplibregl === 'undefined' || map) return;
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
        var orden = ordenar(activas());
        var geo = conGps(orden);
        var bounds = null;

        geo.forEach(function (p, i) {
            var mk = new maplibregl.Marker({ element: markerNum(i + 1) })
                .setLngLat([p.lng, p.lat])
                .setPopup(new maplibregl.Popup({ offset: 22 }).setHTML(
                    '<strong>' + esc(p.nombre) + '</strong><br>' + esc(p.codigo) + ' · ' + fmt(p.monto)
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

        trazarRuta(geo);

        if (bounds) map.fitBounds(bounds, { padding: 60, maxZoom: 15 });
        renderLista(orden);
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
                        : 'Orden sugerido por cercanía — distancia lineal, sin tráfico.';
                }
            })
            .catch(function () { src.setData(recta); });
    }

    /* ---------- lista ---------- */
    function renderLista(orden) {
        var lista = orden || ordenar(activas());
        if (!lista.length) {
            elLista.innerHTML = '<p class="geo-hint">Sin paradas seleccionadas.</p>';
            return;
        }
        elLista.innerHTML = lista.map(function (p, i) {
            var dist = (miPos && p.lat != null) ? distKm(miPos, p) : null;
            return '<div class="ruta-item' + (p.vencida ? ' vencida' : '') + '">'
                + '<span class="ruta-item-num">' + (p.lat != null ? (i + 1) : '·') + '</span>'
                + '<div class="ruta-item-body">'
                + '<a class="ruta-item-nom" href="' + '/' + esc(slug) + '/portal/cliente/' + p.cliente_id + '">'
                + esc(p.nombre) + (p.vencida ? ' <span class="ruta-badge-mora">mora</span>' : '') + '</a>'
                + '<span class="ruta-item-sub">' + esc(p.codigo) + ' · ' + fmt(p.monto)
                + (p.dir ? ' · ' + esc(p.dir) : '') + '</span>'
                + '<span class="map-links">'
                + '<a class="map-btn map-waze" href="' + esc(urlWaze(p)) + '" target="_blank" rel="noopener">Waze</a>'
                + '<a class="map-btn map-gmaps" href="' + esc(urlMaps(p)) + '" target="_blank" rel="noopener">Google Maps</a>'
                + '</span>'
                + '</div>'
                + '<span class="ruta-dist">' + fmtKm(dist) + '</span>'
                + '</div>';
        }).join('');
    }

    /* ---------- GPS ---------- */
    function localizar() {
        if (!navigator.geolocation) {
            elGps.textContent = 'Tu dispositivo no tiene GPS — se muestra el orden por nombre.';
            return;
        }
        elGps.textContent = 'Obteniendo tu ubicación…';
        navigator.geolocation.getCurrentPosition(function (pos) {
            miPos = { lat: pos.coords.latitude, lng: pos.coords.longitude };
            elGps.textContent = 'Ubicación lista — paradas ordenadas de la más cercana a la más lejana.';
            dibujar();
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
    if (btnTodos)   btnTodos.addEventListener('click', function () { checkAll(true); });
    if (btnNinguno) btnNinguno.addEventListener('click', function () { checkAll(false); });
    function checkAll(v) {
        modal.querySelectorAll('input[type="checkbox"]').forEach(function (c) { c.checked = v; });
    }
    if (btnAplicar) btnAplicar.addEventListener('click', function () {
        modal.querySelectorAll('input[type="checkbox"]').forEach(function (c) {
            seleccion[c.value] = c.checked;
        });
        cerrarModal();
        dibujar();
    });

    if (btnGps) btnGps.addEventListener('click', localizar);

    /* ---------- arranque ---------- */
    initMap();
    renderLista(ordenar(activas()));
    localizar();   // pedir GPS de una vez
})();
