/**
 * Contamos — app.js
 * Comportamiento global del layout partner:
 *  - Sidebar desplegable (grupos colapsables)
 *  - Paleta de comandos (Ctrl+K)
 */
(function () {
    'use strict';

    // Base URL de la app — desde <meta name="app-base"> (sin JS inline en PHP)
    var APP_BASE = (document.querySelector('meta[name="app-base"]') || {}).content || '/';

    /* ---------- Sidebar desplegable ---------- */
    document.querySelectorAll('.nav-toggle').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var target = document.getElementById(btn.dataset.target);
            if (!target) return;
            btn.classList.toggle('collapsed');
            target.classList.toggle('collapsed');
        });
    });

    /* ---------- Sidebar móvil: hamburguesa + scrim ---------- */
    var sidebar = document.getElementById('sidebar');
    var sbToggle = document.getElementById('sidebar-toggle');
    var sbScrim = document.getElementById('sidebar-scrim');
    function cerrarSidebar() {
        sidebar.classList.remove('open');
        sbScrim.hidden = true;
    }
    if (sidebar && sbToggle && sbScrim) {
        sbToggle.addEventListener('click', function () {
            var abrir = !sidebar.classList.contains('open');
            sidebar.classList.toggle('open', abrir);
            sbScrim.hidden = !abrir;
        });
        sbScrim.addEventListener('click', cerrarSidebar);
        // Al tocar un enlace del menú en móvil, el sidebar se cierra
        sidebar.querySelectorAll('.nav-link[href]').forEach(function (a) {
            a.addEventListener('click', cerrarSidebar);
        });
    }

    /* ---------- Paleta de comandos ---------- */
    var overlay = document.getElementById('palette');
    var input   = document.getElementById('palette-input');
    var list    = document.getElementById('palette-list');
    var empty   = document.getElementById('palette-empty');
    var trigger = document.getElementById('palette-trigger');

    if (!overlay || !input || !list) return;

    var items = Array.prototype.slice.call(list.querySelectorAll('.palette-item'));
    var activeIndex = -1;

    function openPalette() {
        overlay.hidden = false;
        input.value = '';
        filterItems('');
        input.focus();
    }

    function closePalette() {
        overlay.hidden = true;
        activeIndex = -1;
    }

    function filterItems(query) {
        var q = query.trim().toLowerCase();
        var visible = 0;

        items.forEach(function (item) {
            var text = item.textContent.toLowerCase();
            var show = q === '' || text.indexOf(q) !== -1;
            item.style.display = show ? '' : 'none';
            if (show) visible++;
        });

        empty.hidden = visible > 0;
        activeIndex = -1;
        clearActive();
    }

    function visibleItems() {
        return items.filter(function (i) { return i.style.display !== 'none'; });
    }

    function clearActive() {
        items.forEach(function (i) { i.classList.remove('active'); });
    }

    function moveActive(dir) {
        var vis = visibleItems();
        if (!vis.length) return;
        activeIndex = (activeIndex + dir + vis.length) % vis.length;
        clearActive();
        vis[activeIndex].classList.add('active');
        vis[activeIndex].scrollIntoView({ block: 'nearest' });
    }

    function goActive() {
        var vis = visibleItems();
        var target = activeIndex >= 0 ? vis[activeIndex] : vis[0];
        if (target && target.dataset.url) {
            window.location.href = target.dataset.url;
        }
    }

    // Abrir con Ctrl+K o clic en el trigger
    document.addEventListener('keydown', function (e) {
        if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
            e.preventDefault();
            overlay.hidden ? openPalette() : closePalette();
        }
        if (e.key === 'Escape' && !overlay.hidden) {
            closePalette();
        }
    });

    if (trigger) {
        trigger.addEventListener('click', openPalette);
    }

    // Navegación dentro de la paleta
    input.addEventListener('input', function () { filterItems(input.value); });
    input.addEventListener('keydown', function (e) {
        if (e.key === 'ArrowDown') { e.preventDefault(); moveActive(1); }
        if (e.key === 'ArrowUp')   { e.preventDefault(); moveActive(-1); }
        if (e.key === 'Enter')     { e.preventDefault(); goActive(); }
    });

    // Clic en item o fuera del box
    items.forEach(function (item) {
        item.addEventListener('click', function () {
            if (item.dataset.url) window.location.href = item.dataset.url;
        });
    });

    overlay.addEventListener('click', function (e) {
        if (e.target === overlay) closePalette();
    });

    /* ---------- Panel de notificaciones ---------- */
    var bell      = document.getElementById('notif-bell');
    var badge     = document.getElementById('notif-badge');
    var nOverlay  = document.getElementById('notif-overlay');
    var nPanel    = document.getElementById('notif-panel');
    var nBody     = document.getElementById('notif-body');
    var nClose    = document.getElementById('notif-close');
    var nReadAll  = document.getElementById('notif-read-all');

    function setBadge(count) {
        if (!badge) return;
        badge.hidden = count <= 0;
        badge.textContent = count > 99 ? '99+' : count;
    }

    function openNotif() {
        nOverlay.hidden = false;
        nPanel.hidden = false;
        cargarNotificaciones();
    }

    function closeNotif() {
        nOverlay.hidden = true;
        nPanel.hidden = true;
    }

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function renderNotifs(items) {
        if (!items.length) {
            nBody.innerHTML = '<div class="notif-empty">Sin notificaciones</div>';
            return;
        }
        nBody.innerHTML = items.map(function (n) {
            return '<div class="notif-item' + (n.leida ? '' : ' unread') + '" data-id="' + n.id + '" data-url="' + esc(n.url || '') + '">'
                + '<span class="notif-dot ' + esc(n.tipo) + '"></span>'
                + '<div class="notif-item-content">'
                + '<div class="notif-item-title">' + esc(n.titulo) + '</div>'
                + '<div class="notif-item-msg">' + esc(n.mensaje) + '</div>'
                + '<div class="notif-item-time">' + esc(n.fecha) + '</div>'
                + '</div></div>';
        }).join('');

        nBody.querySelectorAll('.notif-item').forEach(function (el) {
            el.addEventListener('click', function () {
                marcarLeida(el.dataset.id);
                if (el.dataset.url) window.location.href = el.dataset.url;
            });
        });
    }

    function cargarNotificaciones() {
        nBody.innerHTML = '<div class="notif-loading">Cargando…</div>';
        fetch(window.APP_BASE + 'notificaciones', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                setBadge(data.noLeidas || 0);
                renderNotifs(data.items || []);
            })
            .catch(function () {
                nBody.innerHTML = '<div class="notif-empty">Error al cargar</div>';
            });
    }

    function marcarLeida(id) {
        fetch(window.APP_BASE + 'notificaciones/' + id + '/leida', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) { setBadge(data.noLeidas || 0); });
    }

    function leerTodas() {
        fetch(window.APP_BASE + 'notificaciones/leer-todas', {
            method: 'POST',
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function (r) { return r.json(); })
        .then(function (data) {
            setBadge(0);
            nBody.querySelectorAll('.notif-item.unread').forEach(function (el) {
                el.classList.remove('unread');
            });
        });
    }

    if (bell) {
        bell.addEventListener('click', function () {
            nPanel.hidden ? openNotif() : closeNotif();
        });
    }
    if (nClose)   nClose.addEventListener('click', closeNotif);
    if (nOverlay) nOverlay.addEventListener('click', closeNotif);
    if (nReadAll) nReadAll.addEventListener('click', leerTodas);

    // Badge inicial al cargar la página
    if (bell) {
        fetch(APP_BASE + 'notificaciones', { headers: { 'X-Requested-With': 'XMLHttpRequest' } })
            .then(function (r) { return r.json(); })
            .then(function (data) { setBadge(data.noLeidas || 0); })
            .catch(function () {});
    }

    /* ---------- Input estilo IA (dashboard) ---------- */
    var aiInput    = document.getElementById('ai-input');
    var aiBtn      = document.getElementById('ai-btn');
    var aiResponse = document.getElementById('ai-response');
    var aiDropdown = document.getElementById('ai-dropdown');
    var aiIndex    = -1;

    // Acciones = mismos items de la paleta (renderizados server-side)
    var aiActions = items.map(function (el) {
        return {
            name:  (el.querySelector('.palette-item-name') || {}).textContent || '',
            group: (el.querySelector('.palette-item-group') || {}).textContent || '',
            url:   el.dataset.url || ''
        };
    });

    function procesarIA() {
        var q = (aiInput.value || '').trim();
        if (!q) return;
        cerrarDropdown();
        // Asistente Chat-AI (dashboard): analiza el texto y abre el panel
        if (window.ChatAI && window.ChatAI.enviar) {
            aiInput.value = '';
            window.ChatAI.enviar(q);
            return;
        }
        aiResponse.hidden = false;
        aiResponse.textContent = 'Asistente en desarrollo — pronto podrá consultar la información de su negocio.';
    }

    function cerrarDropdown() {
        if (aiDropdown) aiDropdown.hidden = true;
        aiIndex = -1;
    }

    function renderDropdown(filtro) {
        var q = filtro.toLowerCase();
        var lista = aiActions.filter(function (a) {
            return q === '' || a.name.toLowerCase().indexOf(q) !== -1;
        });

        if (!lista.length) {
            aiDropdown.innerHTML = '<div class="ai-dropdown-empty">Sin acciones</div>';
            aiDropdown.hidden = false;
            return;
        }

        aiDropdown.innerHTML = lista.map(function (a, i) {
            return '<div class="ai-option" data-url="' + esc(a.url) + '" data-i="' + i + '">'
                + '<span class="ai-option-name">' + esc(a.name) + '</span>'
                + '<span class="ai-option-group">' + esc(a.group) + '</span>'
                + '</div>';
        }).join('');
        aiDropdown.hidden = false;
        aiIndex = -1;

        aiDropdown.querySelectorAll('.ai-option').forEach(function (el) {
            el.addEventListener('click', function () { aiIrEl(el); });
        });
    }

    // Sugerencias del asistente (datos / acción / reporte / módulo / pregunta)
    var aiTags = { datos: 'Datos', accion: 'Acción', reporte: 'Reporte', modulo: 'Ir a', chat: 'Preguntar' };

    function renderSugerencias(lista) {
        if (!lista.length) { cerrarDropdown(); return; }
        aiDropdown.innerHTML = lista.map(function (s, i) {
            return '<div class="ai-option" data-url="' + esc(s.url) + '" data-q="' + esc(s.q || '') + '" data-i="' + i + '">'
                + '<span class="ai-option-tag ai-tag-' + s.tipo + '">' + esc(aiTags[s.tipo] || s.tipo) + '</span>'
                + '<span class="ai-option-name">' + esc(s.nombre) + '</span>'
                + '<span class="ai-option-group">' + esc(s.desc || '') + '</span>'
                + '</div>';
        }).join('');
        aiDropdown.hidden = false;
        aiIndex = -1;

        aiDropdown.querySelectorAll('.ai-option').forEach(function (el) {
            el.addEventListener('click', function () { aiIrEl(el); });
        });
    }

    function aiIrEl(el) {
        if (!el || !el.dataset.url) return;
        var url = el.dataset.url;
        if (url === '#chat') {
            // Suggestion tipo consulta: enviar su texto canónico al chat
            if (el.dataset.q && aiInput) aiInput.value = el.dataset.q;
            procesarIA();
            return;
        }
        if (url.charAt(0) !== '/' && url.indexOf('http') !== 0) url = APP_BASE + url;
        window.location.href = url;
    }

    function aiVisible() {
        return Array.prototype.slice.call(aiDropdown.querySelectorAll('.ai-option'));
    }

    function aiMover(dir) {
        var vis = aiVisible();
        if (!vis.length) return;
        aiIndex = (aiIndex + dir + vis.length) % vis.length;
        vis.forEach(function (el) { el.classList.remove('active'); });
        vis[aiIndex].classList.add('active');
        vis[aiIndex].scrollIntoView({ block: 'nearest' });
    }

    function aiIr() {
        var vis = aiVisible();
        var target = aiIndex >= 0 ? vis[aiIndex] : vis[0];
        aiIrEl(target);
    }

    if (aiInput) {
        aiInput.addEventListener('input', function () {
            var v = aiInput.value;
            if (v.charAt(0) === '\\') {
                renderDropdown(v.slice(1));
            } else if (window.ChatAI && window.ChatAI.sugerir && v.trim().length >= 2) {
                // Sugerencias del asistente mientras escribe
                renderSugerencias(window.ChatAI.sugerir(v));
            } else {
                cerrarDropdown();
            }
        });

        aiInput.addEventListener('keydown', function (e) {
            var abierto = aiDropdown && !aiDropdown.hidden;
            if (e.key === 'Enter') {
                e.preventDefault();
                abierto ? aiIr() : procesarIA();
            }
            if (abierto && e.key === 'ArrowDown') { e.preventDefault(); aiMover(1); }
            if (abierto && e.key === 'ArrowUp')   { e.preventDefault(); aiMover(-1); }
            if (e.key === 'Escape') cerrarDropdown();
        });
    }

    if (aiBtn) aiBtn.addEventListener('click', procesarIA);

    // Cerrar el desplegable al hacer clic fuera
    document.addEventListener('click', function (e) {
        if (aiDropdown && !aiDropdown.hidden && !e.target.closest('.ai-input-wrap')) {
            cerrarDropdown();
        }
    });

    /* ---------- Editor tipo Word (plantillas) ---------- */
    var tplEditor  = document.getElementById('tpl-editor');
    var tplContent = document.getElementById('contenido');
    var tplForm    = document.getElementById('tpl-form');
    var tplSource  = document.getElementById('tpl-source');
    var tplToolbar = document.getElementById('tpl-toolbar');

    // Convierte texto plano (\n) a HTML para el editor; si ya es HTML lo usa tal cual
    function tplAhtml(txt) {
        if (txt.indexOf('<') !== -1 && txt.indexOf('>') !== -1) return txt;
        var div = document.createElement('div');
        div.textContent = txt;
        return div.innerHTML.replace(/\n/g, '<br>');
    }

    // Carga inicial: pasar el contenido guardado al editor
    if (tplEditor && tplContent) {
        tplEditor.innerHTML = tplAhtml(tplContent.value || '');
    }

    // Toolbar — execCommand
    if (tplToolbar && tplEditor) {
        tplToolbar.querySelectorAll('button[data-cmd]').forEach(function (btn) {
            btn.addEventListener('click', function () {
                document.execCommand(btn.dataset.cmd, false, btn.dataset.arg || null);
                tplEditor.focus();
            });
        });
    }

    // Cargar plantilla del sistema → al editor
    if (tplSource && tplEditor) {
        tplSource.addEventListener('change', function () {
            var src = document.querySelector('.sys-tpl-src[data-key="' + tplSource.value + '"]');
            if (src) {
                tplEditor.innerHTML = tplAhtml(src.value);
                tplEditor.focus();
            }
            tplSource.value = '';
        });
    }

    // Al enviar: volcar el HTML del editor al textarea oculto
    if (tplForm && tplEditor && tplContent) {
        tplForm.addEventListener('submit', function () {
            tplContent.value = tplEditor.innerHTML;
        });
    }

    /* ---------- Pestañas (detalle de persona) ---------- */
    var personaTabs = document.getElementById('persona-tabs');
    if (personaTabs) {
        var btns    = personaTabs.querySelectorAll('.tab-btn');
        var panels  = document.querySelectorAll('.tab-panel');
        btns.forEach(function (btn) {
            btn.addEventListener('click', function () {
                var tab = btn.dataset.tab;
                btns.forEach(function (b) { b.classList.toggle('on', b === btn); });
                panels.forEach(function (p) { p.classList.toggle('on', p.dataset.panel === tab); });
            });
        });
    }

    /* ---------- Modales (data-modal abre, data-close cierra) — por delegación ---------- */
    function cerrarModales() {
        document.querySelectorAll('.modal-overlay:not([hidden])').forEach(function (m) { m.hidden = true; });
    }
    document.addEventListener('click', function (e) {
        var btn = e.target.closest('[data-modal]');
        if (btn) {
            var m = document.getElementById(btn.dataset.modal);
            if (m) {
                resetModalAgregar(m);          // vuelve a modo "agregar"
                m.hidden = false;
                if (m.id === 'modal-direccion') initGeoMap(); // iniciar mapa al abrir dirección
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
                    var nota = m.querySelector('#rec-nota');
                    if (nota) nota.hidden = !r.revision;
                }
            }
            return;
        }
        var ov = e.target.closest('.modal-overlay');
        if (ov && (e.target === ov || e.target.closest('[data-close]'))) ov.hidden = true;

        // Item clickeable → abre su modal en modo EDICIÓN (no desde eliminar ni enlace)
        var it = e.target.closest('[data-editar]');
        if (it && !e.target.closest('.oui-meta') && !e.target.closest('a')) {
            abrirEdicionItem(it);
        }
    });
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') cerrarModales();
    });

    /* ---------- Recibo de pago: imprimir popup / compartir imagen ---------- */
    document.addEventListener('click', function (e) {
        var m = document.getElementById('modal-recibo');
        if (!m || !m._recibo) return;

        // Imprimir → popup limpio con el voucher (auto-print)
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

    // Abre el modal del tipo en modo editar: prellena campos y cambia el action a /actualizar
    function abrirEdicionItem(el) {
        var data = {};
        try { data = JSON.parse(el.getAttribute('data-editar') || '{}'); } catch (err) {}
        var tipo = el.getAttribute('data-tipo');
        var md = document.getElementById('modal-' + tipo);
        if (!md) return;

        var form = md.querySelector('form');
        if (form && data.url) form.action = data.url;   // apunta a /actualizar

        var titulo = md.querySelector('[data-edit-title]');
        if (titulo && titulo.dataset.editTitle) titulo.textContent = titulo.dataset.editTitle;

        // Rellenar inputs/select por nombre (checkboxes "name[]" marcan los valores)
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

        // Documento: el archivo no es obligatorio al editar (conserva el actual)
        var archivo = md.querySelector('input[type="file"][name="archivo"]');
        if (archivo) archivo.required = false;

        md.hidden = false;
        if (tipo === 'direccion' && typeof initGeoMap === 'function') {
            initGeoMap();
            var lat = parseFloat(campos.latitud), lng = parseFloat(campos.longitud);
            if (!isNaN(lat) && !isNaN(lng)) geoMarcar({ lat: lat, lng: lng });
        }
    }

    // Restablece el modal a modo "agregar" al abrirlo desde el botón
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

            // Toggle Mapa / Satélite
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

    // Solo coloca/mueve el marcador y llena lat/lon (sin reverse geocode — para editar)
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
        document.getElementById('geo-latitud').value  = latlng.lat.toFixed(7);
        document.getElementById('geo-longitud').value = latlng.lng.toFixed(7);
    }

    function geoPoner(latlng) {
        if (!geoMarker) {
            geoMarker = new maplibregl.Marker({ draggable: true })
                .setLngLat([latlng.lng, latlng.lat]).addTo(geoMap);
            geoMarker.on('dragend', function () { geoPoner(geoMarker.getLngLat()); });
        } else {
            geoMarker.setLngLat([latlng.lng, latlng.lat]);
        }
        geoMap.easeTo({ center: [latlng.lng, latlng.lat] });
        document.getElementById('geo-latitud').value  = latlng.lat.toFixed(7);
        document.getElementById('geo-longitud').value = latlng.lng.toFixed(7);

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

    /* ---------- Selector de cargo (buscable) ---------- */
    var cgTrigger = document.getElementById('cargo-trigger');
    var cgPanel   = document.getElementById('cargo-panel');
    var cgSearch  = document.getElementById('cargo-search');
    var cgList    = document.getElementById('cargo-list');
    var cgInput   = document.getElementById('cargo');
    var cgText    = document.getElementById('cargo-text');

    if (cgTrigger && cgPanel) {
        // Marcar valor inicial como elegido
        if (cgInput && cgInput.value) cgText.textContent = cgInput.value;

        cgTrigger.addEventListener('click', function (e) {
            e.stopPropagation();
            cgPanel.hidden = !cgPanel.hidden;
            if (!cgPanel.hidden && cgSearch) { cgSearch.value = ''; cgFiltrar(''); cgSearch.focus(); }
        });

        function cgFiltrar(q) {
            q = q.trim().toLowerCase();
            cgList.querySelectorAll('.cargo-opt').forEach(function (o) {
                o.style.display = (!q || o.textContent.toLowerCase().indexOf(q) !== -1) ? '' : 'none';
            });
        }
        if (cgSearch) cgSearch.addEventListener('input', function () { cgFiltrar(cgSearch.value); });

        cgList.querySelectorAll('.cargo-opt').forEach(function (o) {
            o.addEventListener('click', function () {
                cgInput.value = o.dataset.v;
                cgText.textContent = o.dataset.v;
                cgText.dataset.sel = '1';
                cgPanel.hidden = true;
            });
        });

        document.addEventListener('click', function (e) {
            if (!e.target.closest('.cargo-picker')) cgPanel.hidden = true;
        });
    }

    /* ---------- Switch de género (Hombre/Mujer) ---------- */
    var genSwitch = document.getElementById('gen-switch');
    var genInput  = document.getElementById('genero');
    if (genSwitch && genInput) {
        genSwitch.querySelectorAll('.gen-opt').forEach(function (o) {
            o.addEventListener('click', function () {
                genInput.value = o.dataset.v;
                genSwitch.dataset.v = o.dataset.v;   // mueve el slider
            });
        });
    }

    /* ---------- PIN: generar 4 dígitos ---------- */
    var btnPin = document.getElementById('btn-gen-pin');
    var pinIn  = document.getElementById('pin');
    if (btnPin && pinIn) {
        btnPin.addEventListener('click', function () {
            pinIn.value = String(Math.floor(1000 + Math.random() * 9000));
        });
    }

    /* ---------- Logo: dropzone con validación de imagen ---------- */
    var logoDz    = document.getElementById('logo-dropzone');
    var logoInput = document.getElementById('logo');
    var logoInner = document.getElementById('logo-dz-inner');
    var logoError = document.getElementById('logo-dz-error');

    function logoErr(msg) {
        if (!logoError) return;
        logoError.textContent = msg;
        logoError.hidden = false;
    }
    function logoOk() { if (logoError) logoError.hidden = true; }

    function logoSetFile(file) {
        if (!file.type || file.type.indexOf('image/') !== 0) {
            logoErr('El archivo debe ser una imagen (PNG, JPG, WEBP o GIF).');
            return;
        }
        if (file.size > 2 * 1024 * 1024) {
            logoErr('El logo no debe superar 2 MB.');
            return;
        }
        logoOk();
        var reader = new FileReader();
        reader.onload = function (e) {
            logoInner.innerHTML = '<img class="logo-dz-img" src="' + e.target.result + '" alt="Logo">'
                + '<div class="logo-dz-text"><strong>' + file.name + '</strong>'
                + '<span>Cambiar: arrastra o haz clic</span></div>';
        };
        reader.readAsDataURL(file);
    }

    if (logoDz && logoInput) {
        logoDz.addEventListener('click', function () { logoInput.click(); });
        logoDz.addEventListener('dragover', function (e) { e.preventDefault(); logoDz.classList.add('drag-over'); });
        logoDz.addEventListener('dragleave', function () { logoDz.classList.remove('drag-over'); });
        logoDz.addEventListener('drop', function (e) {
            e.preventDefault();
            logoDz.classList.remove('drag-over');
            var file = e.dataTransfer.files && e.dataTransfer.files[0];
            if (file) {
                logoInput.files = e.dataTransfer.files; // para que se envíe con el form
                logoSetFile(file);
            }
        });
        logoInput.addEventListener('change', function () { logoSetFile(logoInput.files[0]); });
    }

    /* ---------- Dropdown de acciones (hero de detalle) ---------- */
    var dToggle = document.getElementById('detail-actions-toggle');
    var dMenu   = document.getElementById('detail-actions-menu');

    if (dToggle && dMenu) {
        dToggle.addEventListener('click', function (e) {
            e.stopPropagation();
            var open = dMenu.hidden;
            dMenu.hidden = !open;
            dToggle.closest('.dropdown').classList.toggle('open', open);
        });

        document.addEventListener('click', function (e) {
            if (!dMenu.hidden && !e.target.closest('.dropdown')) {
                dMenu.hidden = true;
                dToggle.closest('.dropdown').classList.remove('open');
            }
        });
    }

    /* ---------- Valoración del sistema — modal cada 5 días ---------- */
    var valModal = document.getElementById('modal-valoracion');
    if (valModal) {
        var valInput  = document.getElementById('val-estrellas');
        var valEnviar = document.getElementById('val-enviar');
        var valLabel  = document.getElementById('val-label');
        var valStars  = valModal.querySelectorAll('.val-star');
        var valTxt    = ['', 'Muy malo', 'Malo', 'Regular', 'Bueno', 'Excelente'];
        var valSel    = 0;

        var pintarVal = function (v) {
            valStars.forEach(function (x) {
                x.classList.toggle('on', parseInt(x.dataset.v, 10) <= v);
            });
        };
        var textoVal = function (v) {
            if (!valLabel) return;
            valLabel.textContent = v ? valTxt[v] : 'Toca una estrella';
            valLabel.classList.toggle('sel', v > 0);
        };

        valStars.forEach(function (s) {
            s.addEventListener('mouseenter', function () {
                pintarVal(parseInt(s.dataset.v, 10));
                textoVal(parseInt(s.dataset.v, 10));
            });
            s.addEventListener('mouseleave', function () {
                pintarVal(valSel);
                textoVal(valSel);
            });
            s.addEventListener('click', function () {
                valSel = parseInt(s.dataset.v, 10);
                valInput.value = valSel;
                valEnviar.disabled = false;
                pintarVal(valSel);
                textoVal(valSel);
            });
        });

        var valLuego = document.getElementById('val-luego');
        if (valLuego) {
            valLuego.addEventListener('click', function () {
                valModal.hidden = true; // solo cierra esta vista; en la próxima carga vuelve
            });
        }
    }
})();
