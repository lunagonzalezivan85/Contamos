/* admin/contratos/ver — botón imprimir (window.print → guardar como PDF) */
(function () {
    'use strict';
    var btn = document.getElementById('btn-print');
    if (btn) btn.addEventListener('click', function () { window.print(); });
})();
