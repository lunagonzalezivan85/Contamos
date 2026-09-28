<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <meta name="theme-color" content="#F4F6F9">
    <title><?= esc($title) ?></title>
    <style>
        :root {
            --primary:      #30CB9A;
            --primary-dark: #1FA87C;
            --primary-soft: #E6F9F2;
            --secondary-d:  #1A202C;
            --bg:           #F4F6F9;
            --surface:      #FFFFFF;
            --text:         #2E3542;
            --muted:        #8A94A6;
            --line:         #E3E8EF;
            --danger:       #E5484D;
            --danger-soft:  #FDECEC;
            --warn:         #B25E09;
            --warn-soft:    #FDF3E3;
            --radius:       16px;
            --shadow:       0 8px 30px rgba(46,53,66,.08);
        }
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body { height: 100%; }
        body {
            background: var(--bg); color: var(--text);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            line-height: 1.6;
        }
        ::selection { background: var(--primary-soft); }

        /* ===== Split: empresa | acción ===== */
        .k-split { display: flex; min-height: 100vh; }

        /* Empresa — solo logo + nombre */
        .k-empresa {
            flex: 1.1; display: flex; flex-direction: column;
            align-items: center; justify-content: center; text-align: center;
            padding: 48px 56px; background: var(--secondary-d); color: #fff;
        }
        .k-empresa .k-titulo {
            font-size: 1rem; font-weight: 700; text-transform: uppercase;
            letter-spacing: 3px; color: var(--primary); margin-bottom: 34px;
        }
        .k-empresa img { max-height: 170px; max-width: 320px; object-fit: contain; margin-bottom: 36px; }
        .k-empresa h1 { font-size: clamp(2.1rem, 3.6vw, 3.3rem); font-weight: 800; line-height: 1.15; }

        /* Acción — reloj arriba + tarjeta clara */
        .k-panel {
            width: min(560px, 45%); display: flex; flex-direction: column;
            align-items: center; justify-content: center; padding: 40px 52px;
        }
        .k-reloj {
            font-size: clamp(3.4rem, 5vw, 4.8rem); font-weight: 800;
            font-variant-numeric: tabular-nums; line-height: 1; color: var(--text);
        }
        .k-fecha {
            margin: 8px 0 34px; font-size: 1.05rem; color: var(--muted);
            text-transform: capitalize;
        }
        .k-card {
            width: 100%; background: var(--surface); border: 1px solid var(--line);
            border-radius: var(--radius); box-shadow: var(--shadow);
            padding: 36px 32px 32px;
        }
        .k-card h2 { font-size: 1.4rem; font-weight: 700; text-align: center; margin-bottom: 28px; }

        .k-card label { display: block; font-size: .95rem; font-weight: 600; color: var(--muted); margin-bottom: 8px; }
        .k-card input[type="text"] {
            width: 100%; min-height: 56px; padding: 12px 16px;
            font-size: 1.15rem; font-weight: 600; font-family: inherit;
            border: 1.5px solid var(--line); border-radius: 12px;
            background: var(--surface); color: var(--text);
            text-align: center; letter-spacing: 1.5px;
            margin-bottom: 24px; outline: none;
            transition: border-color .15s, box-shadow .15s;
        }
        .k-card input::placeholder { color: #B9C2CF; font-weight: 400; letter-spacing: .5px; }
        .k-card input:focus { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }

        /* PIN por casillas */
        .k-pin { display: flex; justify-content: center; gap: 12px; margin-bottom: 28px; cursor: text; }
        .k-pin-cell {
            width: 56px; height: 64px;
            border: 1.5px solid var(--line); border-radius: 12px;
            background: var(--surface);
            display: flex; align-items: center; justify-content: center;
            font-size: 1.9rem; font-weight: 700; color: var(--text);
            transition: border-color .12s, box-shadow .12s, background .12s;
        }
        .k-pin-cell.k-activa { border-color: var(--primary); box-shadow: 0 0 0 3px var(--primary-soft); }
        .k-pin-cell.k-llena { border-color: var(--primary); background: var(--primary-soft); }
        #pin-hidden {
            position: absolute; opacity: 0; pointer-events: none;
            width: 1px; height: 1px; left: -999px;
        }

        .k-btn {
            width: 100%; min-height: 58px; border: 0; border-radius: 12px; cursor: pointer;
            font-size: 1.15rem; font-weight: 700; font-family: inherit;
            color: #fff; background: var(--primary);
            transition: background .15s, transform .08s, opacity .15s;
        }
        .k-btn:hover { background: var(--primary-dark); }
        .k-btn:active { transform: scale(.98); }
        .k-btn:disabled { opacity: .6; cursor: wait; transform: none; }
        .k-btn:focus-visible { outline: 2px solid var(--primary-dark); outline-offset: 3px; }

        /* Resultado */
        .k-result { text-align: center; animation: rise .3s ease-out; }
        @keyframes rise { from { opacity: 0; transform: translateY(8px); } }
        .k-ico {
            width: 92px; height: 92px; border-radius: 50%; margin: 0 auto 20px;
            display: flex; align-items: center; justify-content: center;
        }
        .k-ico svg { width: 44px; height: 44px; }
        .k-entrada .k-ico { background: var(--primary-soft); color: var(--primary-dark); }
        .k-salida  .k-ico { background: var(--warn-soft);   color: var(--warn); }
        .k-error   .k-ico { background: var(--danger-soft); color: var(--danger); }
        .k-result h2 { font-size: 1.7rem; font-weight: 800; margin-bottom: 4px; }
        .k-result .k-nombre { font-size: 1.2rem; font-weight: 700; color: var(--muted); }
        .k-result .k-detalle { font-size: 1.05rem; color: var(--muted); margin-top: 12px; }
        .k-volver {
            display: inline-flex; align-items: center; gap: 8px; margin-top: 24px;
            color: var(--primary-dark); font-size: 1.05rem; font-weight: 700; text-decoration: none;
            border: 1.5px solid var(--primary); border-radius: 12px; padding: 11px 22px;
            transition: background .15s;
        }
        .k-volver:hover { background: var(--primary-soft); }

        /* Countdown solo en éxito */
        .k-count { margin-top: 20px; }
        .k-count-bar { height: 4px; border-radius: 2px; background: var(--line); overflow: hidden; }
        .k-count-fill { height: 100%; width: 100%; background: var(--primary); animation: drain 6s linear forwards; }
        @keyframes drain { to { width: 0; } }
        .k-count-txt { font-size: .88rem; color: var(--muted); margin-top: 8px; }

        /* <900px: apilado */
        @media (max-width: 900px) {
            .k-split { flex-direction: column; }
            .k-empresa { padding: 30px 22px 24px; }
            .k-empresa img { max-height: 90px; margin-bottom: 14px; }
            .k-empresa h1 { font-size: 1.6rem; }
            .k-panel { width: 100%; padding: 24px 20px 34px; }
            .k-reloj { font-size: 2.8rem; }
        }
    </style>
</head>
<body>
<div class="k-split">

    <!-- Empresa: solo logo + nombre -->
    <section class="k-empresa">
        <div class="k-titulo">Control de Asistencia</div>
        <?php if (!empty($tenant['logo'])): ?>
            <img src="<?= base_url('public/uploads/logos/' . $tenant['logo']) ?>" alt="<?= esc($tenant['nombre']) ?>">
        <?php endif; ?>
        <h1><?= esc($tenant['nombre']) ?></h1>
    </section>

    <!-- Acción: reloj + marcación -->
    <section class="k-panel">
        <div class="k-reloj" id="k-reloj">--:--</div>
        <div class="k-fecha" id="k-fecha"></div>

        <div class="k-card">
            <?php if ($result): ?>
                <?php $esSalida = ($result['tipo'] ?? '') === 'SALIDA'; ?>
                <div class="k-result <?= $esSalida ? 'k-salida' : 'k-entrada' ?>">
                    <div class="k-ico">
                        <?php if ($esSalida): ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
                        <?php else: ?>
                            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.8" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                        <?php endif; ?>
                    </div>
                    <h2><?= $esSalida ? 'Hasta pronto' : 'Bienvenido' ?></h2>
                    <div class="k-nombre"><?= esc($result['empleado']) ?></div>
                    <div class="k-detalle">
                        <?= $esSalida ? 'Salida' : 'Entrada' ?> registrada · <?= esc($result['hora']) ?>
                        <?php if ($esSalida && isset($result['horas'])): ?>
                            · <?= rtrim(rtrim(number_format((float) $result['horas'], 2), '0'), '.') ?> h trabajadas
                        <?php endif; ?>
                    </div>
                    <a class="k-volver" href="<?= base_url($slug . '/asistencia') ?>">
                        Siguiente marcación
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round"><line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/></svg>
                    </a>
                    <div class="k-count">
                        <div class="k-count-bar"><div class="k-count-fill"></div></div>
                        <div class="k-count-txt">Continuará solo en <span id="k-segs">6</span> s</div>
                    </div>
                </div>
            <?php elseif ($error): ?>
                <div class="k-result k-error">
                    <div class="k-ico">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"><circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/></svg>
                    </div>
                    <h2>No se pudo registrar</h2>
                    <div class="k-detalle"><?= esc($error) ?></div>
                    <a class="k-volver" href="<?= base_url($slug . '/asistencia') ?>">Volver a intentar</a>
                </div>
            <?php else: ?>
                <h2>Marcá tu asistencia</h2>
                <form method="post" action="<?= base_url($slug . '/asistencia') ?>" autocomplete="off" id="k-form">
                    <?= csrf_field() ?>
                    <label for="carnet">Carnet</label>
                    <input type="text" id="carnet" name="carnet" placeholder="TI-0000"
                           maxlength="20" required autofocus
                           oninput="this.value=this.value.toUpperCase()">

                    <label>PIN</label>
                    <!-- Input real oculto: tocar las casillas lo enfoca -->
                    <input type="password" id="pin-hidden" name="pin" maxlength="10"
                           inputmode="numeric" required>
                    <div class="k-pin" id="k-pin"></div>

                    <button type="submit" class="k-btn" id="k-btn">Marcar</button>
                </form>
            <?php endif; ?>
        </div>
    </section>
</div>

<script>
(function () {
    /* Reloj */
    var reloj = document.getElementById('k-reloj');
    var fecha = document.getElementById('k-fecha');
    function tick() {
        var d = new Date();
        var h = d.getHours() % 12 || 12;
        reloj.textContent = h + ':' + String(d.getMinutes()).padStart(2, '0')
            + ' ' + (d.getHours() >= 12 ? 'PM' : 'AM');
        fecha.textContent = d.toLocaleDateString('es-NI', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' });
    }
    tick(); setInterval(tick, 1000);

    /* PIN en casillas — input oculto + celdas visuales.
       Las casillas coinciden con la longitud real del PIN del carnet. */
    var pin    = document.getElementById('pin-hidden');
    var wrap   = document.getElementById('k-pin');
    var carnet = document.getElementById('carnet');
    if (pin && wrap) {
        var pinLen = 4; // default hasta resolver el carnet

        function pintar() {
            if (wrap.children.length !== pinLen) {
                wrap.innerHTML = '';
                for (var i = 0; i < pinLen; i++) {
                    var c = document.createElement('div');
                    c.className = 'k-pin-cell';
                    wrap.appendChild(c);
                }
            }
            var cells = wrap.children;
            for (var i = 0; i < cells.length; i++) {
                cells[i].textContent = i < pin.value.length ? '\u25CF' : '';
                cells[i].className = 'k-pin-cell'
                    + (i < pin.value.length ? ' k-llena' : '')
                    + (i === pin.value.length ? ' k-activa' : '');
            }
        }

        /* Al escribir el carnet, consulta cuántos dígitos tiene su PIN. */
        var lookupTimer = null;
        function lookupLen() {
            var c = carnet.value.trim();
            if (c.length < 3) return;
            fetch('<?= base_url($slug . '/asistencia/pin-longitud') ?>?carnet=' + encodeURIComponent(c))
                .then(function (r) { return r.ok ? r.json() : null; })
                .then(function (d) {
                    if (d && d.ok && d.len >= 4 && d.len <= 10) {
                        pinLen = d.len;
                        pin.setAttribute('maxlength', pinLen);
                        if (pin.value.length > pinLen) pin.value = pin.value.slice(0, pinLen);
                        pintar();
                    }
                })
                .catch(function () {});
        }
        carnet.addEventListener('input', function () {
            clearTimeout(lookupTimer);
            lookupTimer = setTimeout(lookupLen, 400);
        });
        carnet.addEventListener('blur', lookupLen);

        wrap.addEventListener('click', function () { pin.focus(); });
        pin.addEventListener('input', pintar);
        pin.addEventListener('focus', pintar);
        pin.addEventListener('blur', pintar);
        pintar();
    }

    /* Submit → estado de carga (anti doble-marcación) */
    var form = document.getElementById('k-form');
    var btn  = document.getElementById('k-btn');
    if (form && btn) {
        form.addEventListener('submit', function () {
            btn.disabled = true;
            btn.textContent = 'Registrando\u2026';
        });
    }

    /* Countdown visible solo tras éxito */
    var fill = document.querySelector('.k-count-fill');
    var segs = document.getElementById('k-segs');
    if (fill && segs) {
        var t = 6;
        var iv = setInterval(function () {
            t -= 1;
            if (segs) segs.textContent = t;
            if (t <= 0) { clearInterval(iv); location.href = '<?= base_url($slug . '/asistencia') ?>'; }
        }, 1000);
    }
})();
</script>
</body>
</html>
