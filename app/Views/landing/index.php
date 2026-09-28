<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= esc($title ?? 'Contamos — Solución para tu negocio') ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=IBM+Plex+Mono:wght@400;500&family=IBM+Plex+Sans:wght@400;500;600&family=IBM+Plex+Serif:ital,wght@0,300;0,400;0,500;0,600;1,300;1,400;1,500&display=swap" rel="stylesheet">
    <link rel="icon" type="image/png" href="<?= base_url('public/favicon.png') ?>">
    <link rel="stylesheet" href="<?= v_asset('css/contamos.css') ?>">
</head>
<body>

<!-- ===================== TOPBAR ===================== -->
<header class="topbar">
    <div class="wrap topbar-in">
        <a class="wordmark" href="<?= base_url() ?>"><i class="mark">C</i>Contamos<span>.</span></a>
        <nav class="topnav">
            <a href="#beneficios">Beneficios</a>
            <a href="#funciones">Funciones</a>
            <a href="#proceso">Proceso</a>
            <a href="#faq">Preguntas</a>
            <a href="#contacto">Contacto</a>
            <a href="<?= base_url('login') ?>" class="topnav-cta">Iniciar sesión</a>
        </nav>
    </div>
</header>

<!-- ===================== HERO ===================== -->
<section class="hero">
    <div class="wrap hero-grid">
        <div class="hero-copy">
            <h1>Los préstamos de tu negocio, <em>bajo&nbsp;control.</em></h1>
            <p class="lede">
                Contamos es la herramienta para personas y mipymes que prestan dinero:
                registra solicitudes, genera planes de pago, cobra cuotas y
                controla tu cartera — todo desde un solo lugar.
            </p>
            <div class="hero-actions">
                <a href="<?= base_url('login') ?>" class="btn">Acceder al sistema</a>
                <a href="#beneficios" class="link-more">Conocer más</a>
            </div>
        </div>

        <div class="shots">
            <figure class="shot shot-a">
                <span class="shot-bar"><i></i><i></i><i></i></span>
                <?php if (is_file(FCPATH . 'img/landing/dashboard.png')): ?>
                <img src="<?= v_asset('img/landing/dashboard.png') ?>" alt="Dashboard de Contamos">
                <?php else: ?>
                <div class="shot-empty"><b>dashboard.png</b><span>captura del panel principal</span></div>
                <?php endif; ?>
            </figure>
            <figure class="shot shot-b">
                <span class="shot-bar"><i></i><i></i><i></i></span>
                <?php if (is_file(FCPATH . 'img/landing/gestor.png')): ?>
                <img src="<?= v_asset('img/landing/gestor.png') ?>" alt="Portal del asesor de crédito">
                <?php else: ?>
                <div class="shot-empty"><b>gestor.png</b><span>captura del portal del asesor</span></div>
                <?php endif; ?>
            </figure>
        </div>
    </div>
</section>

<!-- ===================== DATOS ===================== -->
<div class="meta-strip">
    <div class="wrap meta-in">
        <span><b>Número de crédito</b> único y ordenado</span>
        <span><b>Plan de cuotas</b> automático al entregar el dinero</span>
        <span><b>Recibo numerado</b> en cada cobro</span>
    </div>
</div>

<!-- ===================== BENEFICIOS ===================== -->
<section class="block" id="beneficios">
    <div class="wrap block-grid">
        <aside class="block-label"><span>Beneficios</span></aside>
        <div class="block-body">
            <h2 class="h-sec">Por qué Contamos</h2>
            <p>
                <strong>Contamos</strong> ordena todo el ciclo del préstamo para quienes
                viven de financiar a otros — para que tú te enfoques en hacer crecer tu negocio.
            </p>
            <ul class="rule-list">
                <li><b>Cobranza con orden</b> — pagos y recibos registrados en el momento, sin papel.</li>
                <li><b>Cartera en tiempo real</b> — saldos, mora y cobros del día siempre actualizados.</li>
                <li><b>Menos errores</b> — el plan de pago y las cuotas se calculan solos.</li>
                <li><b>Cobros en la calle</b> — cada asesor de crédito cobra desde su celular con su propio portal.</li>
                <li><b>Documentos conforme a la ley</b> — contrato, pagaré y plan de pago con las cláusulas que exige la legislación nicaragüense.</li>
                <li><b>Crece sin perder control</b> — roles y permisos para cada miembro del equipo.</li>
            </ul>
        </div>
    </div>
</section>

<!-- ===================== A QUIÉN VA DIRIGIDO ===================== -->
<section class="block" id="dirigido">
    <div class="wrap block-grid">
        <aside class="block-label"><span>Dirigido a</span></aside>
        <div class="block-body">
            <h2 class="h-sec">A quién va dirigido</h2>
            <dl class="who">
                <div class="who-row">
                    <dt>Prestamistas independientes</dt>
                    <dd>Personas que prestan y cobran por su cuenta</dd>
                </div>
                <div class="who-row">
                    <dt>Mipymes y comercios</dt>
                    <dd>Negocios que dan crédito a sus clientes</dd>
                </div>
                <div class="who-row">
                    <dt>Microfinancieras y cooperativas</dt>
                    <dd>Organizaciones de crédito registradas ante CONAMI, con asesores en campo</dd>
                </div>
            </dl>
        </div>
    </div>
</section>

<!-- ===================== FUNCIONES ===================== -->
<section class="block" id="funciones">
    <div class="wrap block-grid">
        <aside class="block-label"><span>Funciones</span></aside>
        <div class="block-body">
            <h2 class="h-sec">Todo lo que tu negocio necesita</h2>
            <p class="block-intro">Desde la solicitud hasta el último pago, Contamos cubre cada etapa de tu operación.</p>
            <dl class="index-list">
                <div class="index-row">
                    <dt>Solicitudes de crédito</dt>
                    <dd>Registra y evalúa solicitudes con datos del cliente, garantías y documentos. Aprueba o rechaza con historial completo.</dd>
                </div>
                <div class="index-row">
                    <dt>Planes de pago</dt>
                    <dd>Cuotas generadas automáticamente según monto, plazo y periodicidad. Saldos y mora siempre actualizados.</dd>
                </div>
                <div class="index-row">
                    <dt>Cobranza y pagos</dt>
                    <dd>Registra pagos, imprime recibos y da seguimiento a clientes atrasados. Reversiones con aprobación.</dd>
                </div>
                <div class="index-row">
                    <dt>Reportes de cartera</dt>
                    <dd>Cobros del día, clientes en mora, saldo de cartera y recuperación. Exporta a Excel o imprime.</dd>
                </div>
                <div class="index-row">
                    <dt>Documentos automáticos</dt>
                    <dd>Contrato, pagaré, plan de pago y recibo de desembolso generados con un clic, listos para imprimir.</dd>
                </div>
                <div class="index-row">
                    <dt>Seguro y por roles</dt>
                    <dd>Cada usuario ve solo lo que necesita: asesores, cajeros y administradores con permisos propios.</dd>
                </div>
            </dl>
        </div>
    </div>
</section>

<!-- ===================== PROCESO ===================== -->
<section class="block" id="proceso">
    <div class="wrap block-grid">
        <aside class="block-label"><span>Proceso</span></aside>
        <div class="block-body">
            <h2 class="h-sec">Empieza en cuatro pasos</h2>
            <ol class="proc">
                <li>
                    <span class="proc-num">1</span>
                    <h3>Inicia sesión</h3>
                    <p>Entra a tu panel con tu usuario — cada negocio tiene el suyo.</p>
                </li>
                <li>
                    <span class="proc-num">2</span>
                    <h3>Crea tu equipo</h3>
                    <p>Registra a tus asesores de crédito — cada uno trabaja desde su celular.</p>
                </li>
                <li>
                    <span class="proc-num">3</span>
                    <h3>Registra el crédito</h3>
                    <p>Solicitud, aprobación y plan de cuotas generado al entregar el dinero.</p>
                </li>
                <li>
                    <span class="proc-num">4</span>
                    <h3>Haz seguimiento</h3>
                    <p>Cobros, mora y reportes de cartera actualizados a diario.</p>
                </li>
            </ol>
        </div>
    </div>
</section>

<!-- ===================== APP ===================== -->
<section class="block" id="app">
    <div class="wrap block-grid">
        <aside class="block-label"><span>App móvil</span></aside>
        <div class="block-body app-split">
            <div class="app-copy">
                <h2 class="h-sec">Contamos en tu bolsillo</h2>
                <p>
                    Lleva la cobranza a la calle: tus asesores podrán registrar pagos,
                    consultar clientes y ver su ruta del día directamente desde el celular.
                    La app Android está en desarrollo — muy pronto disponible para descargar.
                </p>
                <p class="app-note"><span class="line-chip">Android — próximamente</span></p>
            </div>
            <figure class="route" aria-label="Ejemplo de ruta de cobro del día">
                <figcaption class="route-head">Ruta del día — viernes 26</figcaption>
                <ul>
                    <li><b>Rosa M.</b><span>Cuota 4</span><em class="ok">Cobrada</em></li>
                    <li><b>José A.</b><span>Cuota 2</span><em class="ok">Cobrada</em></li>
                    <li><b>Marta L.</b><span>Cuota 7</span><em>Pendiente</em></li>
                    <li><b>Carlos R.</b><span>Cuota 1</span><em class="warn">En mora</em></li>
                </ul>
            </figure>
        </div>
    </div>
</section>

<!-- ===================== FAQ ===================== -->
<section class="block" id="faq">
    <div class="wrap block-grid">
        <aside class="block-label"><span>Preguntas</span></aside>
        <div class="block-body">
            <h2 class="h-sec">Preguntas frecuentes</h2>
            <div class="faq">
                <details>
                    <summary>¿Necesito conocimientos técnicos para usarlo?</summary>
                    <p>No. Contamos está pensado para la operación diaria de cobranza: formularios guiados, botones claros y nada de tecnicismos.</p>
                </details>
                <details>
                    <summary>¿Mis datos están seguros?</summary>
                    <p>Sí. Cada negocio tiene su propio espacio — ningún otro negocio ve tu información, y cada usuario solo ve lo que su rol permite.</p>
                </details>
                <details>
                    <summary>¿Funciona con asesores de crédito en ruta?</summary>
                    <p>Sí. Cada asesor entra a su portal desde el celular, ve su ruta del día y registra los pagos ahí mismo.</p>
                </details>
                <details>
                    <summary>¿Puedo imprimir contratos y recibos?</summary>
                    <p>Sí. El contrato, el pagaré, el plan de pago y los recibos se generan automáticamente, listos para imprimir.</p>
                </details>
                <details>
                    <summary>¿Cómo obtengo mi usuario?</summary>
                    <p>Déjanos tus datos en el formulario de contacto y te creamos tu acceso.</p>
                </details>
            </div>
        </div>
    </div>
</section>

<!-- ===================== CONTACTO ===================== -->
<section class="contacto" id="contacto">
    <div class="wrap contacto-grid">
        <div class="contacto-copy">
            <h2>Contáctanos</h2>
            <p>¿Querés ordenar la cobranza de tu negocio? Escribinos o visitanos.</p>
            <ul class="contacto-list">
                <li><span>Dirección</span><b>Santa Teresa, Carazo</b></li>
                <li><span>Teléfono</span><b>+505 7718 7005</b></li>
                <li><span>Correo</span><b>ventas@softlutionic</b></li>
            </ul>
        </div>
        <form class="solicita" method="post" action="<?= base_url('solicitar-acceso') ?>">
            <?= csrf_field() ?>
            <h3>Solicita tu usuario</h3>
            <?php if (session('acceso_ok')): ?>
            <p class="form-ok"><?= esc(session('acceso_ok')) ?></p>
            <?php endif; ?>
            <?php if (session('acceso_error')): ?>
            <p class="form-err"><?= esc(session('acceso_error')) ?></p>
            <?php endif; ?>
            <label>Nombre completo
                <input type="text" name="nombre" value="<?= esc(old('nombre') ?? '') ?>" required maxlength="120">
            </label>
            <label>Tu negocio
                <input type="text" name="negocio" value="<?= esc(old('negocio') ?? '') ?>" required maxlength="160">
            </label>
            <label>Teléfono
                <input type="tel" name="telefono" value="<?= esc(old('telefono') ?? '') ?>" required maxlength="30">
            </label>
            <label>Correo <small>(opcional)</small>
                <input type="email" name="correo" value="<?= esc(old('correo') ?? '') ?>" maxlength="160">
            </label>
            <button type="submit" class="btn btn-inv">Solicitar usuario</button>
        </form>
    </div>
</section>

<!-- ===================== FOOTER ===================== -->
<footer class="foot">
    <div class="wrap foot-in">
        <span class="wordmark sm"><i class="mark">C</i>Contamos<span>.</span></span>
        <span class="foot-dir">Santa Teresa, Carazo &nbsp;·&nbsp; +505 7718 7005 &nbsp;·&nbsp; ventas@softlutionic</span>
        <small>&copy; <?= date('Y') ?> Contamos — Desarrollado por Softlutionic</small>
    </div>
</footer>

</body>
</html>
