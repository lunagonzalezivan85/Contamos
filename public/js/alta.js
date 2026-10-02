/* ==========================================================================
   /alta — calculadora de plan a medida.
   Base y límites vienen del plan Básico real (window.ALTA_BASE, inyectado
   por landing/alta.php desde la tabla planes). El server recalcula el
   precio al guardar — esto es solo la referencia en pantalla.
   ========================================================================== */
(function () {
    'use strict';

    var B = window.ALTA_BASE || { precio: 35, usuarios: 3, clientes: 20, creditos: 50, empleados: 5 };

    // Costo por unidad extra sobre el plan Básico
    var EXTRA = { usuarios: 3.00, clientes: 0.20, creditos: 0.15, empleados: 1.00 };

    var campos = ['Usuarios', 'Clientes', 'Creditos', 'Empleados'];

    function val(id) { return parseInt(document.getElementById(id).value, 10) || 0; }

    function calcular() {
        var v = {
            Usuarios:  val('inpUsuarios'),
            Clientes:  val('inpClientes'),
            Creditos:  val('inpCreditos'),
            Empleados: val('inpEmpleados')
        };

        campos.forEach(function (c) {
            document.getElementById('lbl' + c).textContent = v[c];
            var hid = document.getElementById('hid' + c);
            if (hid) hid.value = v[c];
        });

        var total = B.precio
            + Math.max(0, v.Usuarios  - B.usuarios)  * EXTRA.usuarios
            + Math.max(0, v.Clientes  - B.clientes)  * EXTRA.clientes
            + Math.max(0, v.Creditos  - B.creditos)  * EXTRA.creditos
            + Math.max(0, v.Empleados - B.empleados) * EXTRA.empleados;

        document.getElementById('txtTotal').textContent = total.toFixed(2);
    }

    campos.forEach(function (c) {
        document.getElementById('inp' + c).addEventListener('input', calcular);
    });

    calcular(); // estado inicial (por si la base no cae en los defaults)
})();
