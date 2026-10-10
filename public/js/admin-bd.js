// /admin/base-datos — seleccionar todas/ninguna y confirmar el export
document.addEventListener('DOMContentLoaded', function () {
    var form  = document.getElementById('form-bd');
    if (!form) return;
    var todas = document.getElementById('ck-todas');
    var cks   = form.querySelectorAll('input[name="tablas[]"]');

    todas.addEventListener('change', function () {
        cks.forEach(function (c) { c.checked = todas.checked; });
    });
    cks.forEach(function (c) {
        c.addEventListener('change', function () {
            todas.checked = [...cks].every(function (x) { return x.checked; });
        });
    });

    form.addEventListener('submit', function (e) {
        var btn = e.submitter;
        if (btn && btn.value === 'completo'
            && !confirm('Backup completo de todas las tablas marcadas — puede tardar. ¿Continuar?')) {
            e.preventDefault();
        }
    });
});
