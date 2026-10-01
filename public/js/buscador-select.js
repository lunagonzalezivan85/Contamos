/* ==========================================================================
   Buscador sobre <select> — convierte un select largo en input + paleta
   filtrable (nombre, código o cualquier texto de la opción).
   El <select> queda oculto pero conserva name/value y dispara 'change',
   así el resto del JS del form (resúmenes, validaciones) no cambia.

   Uso:  BuscarSelect.enhance(document.getElementById('cliente_id'), {
             placeholder: 'Buscar cliente…', vacio: '— Seleccione —'
         });
   ========================================================================== */
window.BuscarSelect = (function () {

    function norm(s) {
        return (s || '').toLowerCase()
            .normalize('NFD').replace(/[̀-ͯ]/g, '');
    }

    function enhance(sel, opts) {
        opts = opts || {};
        if (!sel || sel.dataset.busq) return;
        sel.dataset.busq = '1';
        sel.style.display = 'none';

        var wrap = document.createElement('div');
        wrap.className = 'busq';
        sel.parentNode.insertBefore(wrap, sel);
        wrap.appendChild(sel);

        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'busq-input';
        input.placeholder = opts.placeholder || 'Buscar…';
        input.autocomplete = 'off';
        if (sel.required) input.setAttribute('aria-required', 'true');
        wrap.appendChild(input);
        sel._busqInput = input;   // para .focus() desde validaciones externas

        var list = document.createElement('div');
        list.className = 'busq-list';
        list.hidden = true;
        wrap.appendChild(list);

        // Opciones actuales del select (vacío incluido si existe)
        var items = Array.prototype.slice.call(sel.options).map(function (o) {
            return { v: o.value, t: o.textContent.trim(), n: norm(o.textContent) };
        });

        function syncInput() {
            var o = sel.selectedOptions[0];
            input.value = o && o.value ? o.textContent.trim() : '';
        }
        syncInput();

        function pick(v, t) {
            sel.value = v;
            input.value = v ? t : '';
            list.hidden = true;
            sel.dispatchEvent(new Event('change', { bubbles: true }));
        }

        function render(q) {
            var nq = norm(q);
            var hits = items.filter(function (it) {
                return it.v !== '' && (nq === '' || it.n.indexOf(nq) !== -1);
            }).slice(0, 60);
            var html = '';
            if (opts.vacio) {
                html += '<button type="button" class="busq-item' + (sel.value === '' ? ' on' : '')
                      + '" data-v="">' + opts.vacio + '</button>';
            }
            html += hits.map(function (it) {
                return '<button type="button" class="busq-item' + (it.v === sel.value ? ' on' : '')
                     + '" data-v="' + it.v + '">' + it.t + '</button>';
            }).join('');
            if (!hits.length && nq !== '') {
                html += '<div class="busq-empty">Sin coincidencias</div>';
            }
            list.innerHTML = html;
            list.hidden = false;
        }

        input.addEventListener('focus', function () { input.select(); render(input.value === '' ? '' : ''); });
        input.addEventListener('input', function () { render(input.value); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Escape') { list.hidden = true; syncInput(); }
            if (e.key === 'Enter' && !list.hidden) {
                var first = list.querySelector('.busq-item');
                if (first) { e.preventDefault(); first.click(); }
            }
        });
        list.addEventListener('click', function (e) {
            var b = e.target.closest('.busq-item');
            if (b) pick(b.dataset.v, b.textContent);
        });
        // Cierre al salir — timeout para que el click de la lista gane
        wrap.addEventListener('focusout', function () {
            setTimeout(function () {
                if (!wrap.contains(document.activeElement)) {
                    list.hidden = true;
                    syncInput();   // si tecleó sin elegir, restaura el valor real
                }
            }, 120);
        });
    }

    return { enhance: enhance };
})();
