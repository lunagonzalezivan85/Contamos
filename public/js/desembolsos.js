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

    // Iconos de marca (fill currentColor) — mismos paths que icon_helper (waze/gmaps)
    var ICO_WAZE  = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M13.218 0C9.915 0 6.835 1.49 4.723 4.148c-1.515 1.913-2.31 4.272-2.31 6.706v1.739c0 .894-.62 1.738-1.862 1.813-.298.025-.547.224-.547.522-.05.82.82 2.31 2.012 3.502.82.844 1.788 1.515 2.832 2.036a3 3 0 0 0 2.955 3.528 2.966 2.966 0 0 0 2.931-2.385h2.509c.323 1.689 2.086 2.856 3.974 2.21 1.64-.546 2.36-2.409 1.763-3.924a12.84 12.84 0 0 0 1.838-1.465 10.73 10.73 0 0 0 3.18-7.65c0-2.882-1.118-5.589-3.155-7.625A10.899 10.899 0 0 0 13.218 0zm0 1.217c2.558 0 4.967.994 6.78 2.807a9.525 9.525 0 0 1 2.807 6.78A9.526 9.526 0 0 1 20 17.585a9.647 9.647 0 0 1-6.78 2.807h-2.46a3.008 3.008 0 0 0-2.93-2.41 3.03 3.03 0 0 0-2.534 1.367v.024a8.945 8.945 0 0 1-2.41-1.788c-.844-.844-1.316-1.614-1.515-2.11a2.858 2.858 0 0 0 1.441-.846 2.959 2.959 0 0 0 .795-2.036v-1.789c0-2.11.696-4.197 2.012-5.861 1.863-2.385 4.62-3.726 7.6-3.726zm-2.41 5.986a1.192 1.192 0 0 0-1.191 1.192 1.192 1.192 0 0 0 1.192 1.193A1.192 1.192 0 0 0 12 8.395a1.192 1.192 0 0 0-1.192-1.192zm7.204 0a1.192 1.192 0 0 0-1.192 1.192 1.192 1.192 0 0 0 1.192 1.193 1.192 1.192 0 0 0 1.192-1.193 1.192 1.192 0 0 0-1.192-1.192zm-7.377 4.769a.596.596 0 0 0-.546.845 4.813 4.813 0 0 0 4.346 2.757 4.77 4.77 0 0 0 4.347-2.757.596.596 0 0 0-.547-.845h-.025a.561.561 0 0 0-.521.348 3.59 3.59 0 0 1-3.254 2.061 3.591 3.591 0 0 1-3.254-2.061.64.64 0 0 0-.546-.348z"/></svg>';
    var ICO_GMAPS = '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M19.527 4.799c1.212 2.608.937 5.678-.405 8.173-1.101 2.047-2.744 3.74-4.098 5.614-.619.858-1.244 1.75-1.669 2.727-.141.325-.263.658-.383.992-.121.333-.224.673-.34 1.008-.109.314-.236.684-.627.687h-.007c-.466-.001-.579-.53-.695-.887-.284-.874-.581-1.713-1.019-2.525-.51-.944-1.145-1.817-1.79-2.671L19.527 4.799zM8.545 7.705l-3.959 4.707c.724 1.54 1.821 2.863 2.871 4.18.247.31.494.622.737.936l4.984-5.925-.029.01c-1.741.601-3.691-.291-4.392-1.987a3.377 3.377 0 0 1-.209-.716c-.063-.437-.077-.761-.004-1.198l.001-.007zM5.492 3.149l-.003.004c-1.947 2.466-2.281 5.88-1.117 8.77l4.785-5.689-.058-.05-3.607-3.035zM14.661.436l-3.838 4.563a.295.295 0 0 1 .027-.01c1.6-.551 3.403.15 4.22 1.626.176.319.323.683.377 1.045.068.446.085.773.012 1.22l-.003.016 3.836-4.561A8.382 8.382 0 0 0 14.67.439l-.009-.003zM9.466 5.868L14.162.285l-.047-.012A8.31 8.31 0 0 0 11.986 0a8.439 8.439 0 0 0-6.169 2.766l-.016.018 3.665 3.084z"/></svg>';

    // Botones "ir con" (solo icono) para el popup del marker
    function navIcons(p) {
        return '<div class="map-nav">'
            + '<a class="map-ic map-ic-waze" href="' + esc(urlWaze(p)) + '" target="_blank" rel="noopener" title="Ir con Waze">' + ICO_WAZE + '</a>'
            + '<a class="map-ic map-ic-gmaps" href="' + esc(urlMaps(p)) + '" target="_blank" rel="noopener" title="Ir con Google Maps">' + ICO_GMAPS + '</a>'
            + '</div>';
    }

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
                    + '<br>' + esc(p.gestor) + navIcons(p)
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
