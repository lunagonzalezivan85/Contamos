/**
 * Chat-AI — asistente del dashboard.
 *
 * Analizador sintáctico ligero (sin backend de IA):
 *  - Normaliza el texto (minúsculas, sin acentos, sin puntuación).
 *  - Puntúa cada conocimiento (reportes del catálogo, módulos del menú,
 *    respuestas FAQ) según coincidencias de palabras clave y frases.
 *  - Devuelve respuestas conversacionales con tarjetas de sugerencia.
 *
 * Conocimiento de reportes: window.CHAT_REPORTES (inyectado por la vista
 * del dashboard desde Config\Reportes, ya filtrado por permisos).
 * Módulos: se leen de la paleta de comandos del layout.
 */
window.ChatAI = (function () {
    'use strict';

    var BASE = (document.querySelector('meta[name="app-base"]') || {}).content || '/';
    var REPORTES = window.CHAT_REPORTES || [];

    var overlay, panel, body, input, creado = false;

    /* ═══════════ Analizador sintáctico ═══════════ */

    function norm(s) {
        return (s || '').toLowerCase().normalize('NFD')
            .replace(/[\u0300-\u036f]/g, '')
            .replace(/[^a-z0-9 ]+/g, ' ')
            .replace(/\s+/g, ' ').trim();
    }
    function tokens(s) { return norm(s).split(' ').filter(Boolean); }

    // Puntos por keyword: +3 token exacto, +2 si el token del query empieza con la kw (o viceversa), +1 substring
    function scoreKw(qtoks, kws) {
        var pts = 0;
        (kws || []).forEach(function (kw) {
            var kwn = norm(kw);
            qtoks.forEach(function (t) {
                if (t === kwn) pts += 3;
                else if (t.length >= 4 && (t.indexOf(kwn) === 0 || kwn.indexOf(t) === 0)) pts += 2;
                else if (kwn.length >= 4 && t.length >= 4 && t.indexOf(kwn) !== -1) pts += 1;
            });
        });
        return pts;
    }

    // Bonus por frase literal ("cartera vencida" en el texto normalizado)
    function scoreFrase(qn, frases) {
        var pts = 0;
        (frases || []).forEach(function (f) {
            if (qn.indexOf(norm(f)) !== -1) pts += 4;
        });
        return pts;
    }

    function scoreTexto(qtoks, texto) {
        var pts = 0, tn = ' ' + norm(texto) + ' ';
        qtoks.forEach(function (t) {
            if (t.length >= 3 && tn.indexOf(' ' + t) !== -1) pts += 2;
        });
        return pts;
    }

    // Módulos desde la paleta de comandos del layout
    function modulos() {
        return Array.prototype.slice.call(
            document.querySelectorAll('#palette-list .palette-item')
        ).map(function (el) {
            var n = el.querySelector('.palette-item-name');
            var g = el.querySelector('.palette-item-group');
            return {
                nombre: n ? n.textContent.trim() : '',
                grupo:  g ? g.textContent.trim() : '',
                url:    el.dataset.url || ''
            };
        });
    }

    /* ── Respuestas frecuentes (ayuda del sistema) ── */
    var FAQ = [
        {
            kw: ['contrasena', 'clave', 'password'],
            msg: 'Puede cambiar su contraseña desde su cuenta.',
            sugs: [{ nombre: 'Cambiar contraseña', desc: 'Cuenta', url: 'perfil/cambiar-password' }]
        },
        {
            kw: ['pago', 'pagos', 'abono', 'abonos', 'registrar pago', 'cobrar'],
            msg: 'Los pagos se registran en el módulo Pagos: busque el cliente y aplique el abono a sus cuotas.',
            sugs: [{ nombre: 'Pagos', desc: 'Registro de abonos', url: 'pagos' }]
        },
        {
            kw: ['solicitud', 'solicitudes', 'credito nuevo', 'nuevo credito', 'crear credito'],
            msg: 'Las solicitudes de crédito se crean y evalúan en Crédito → Solicitudes.',
            sugs: [{ nombre: 'Solicitudes', desc: 'Crédito', url: 'credito/solicitudes' }]
        },
        {
            kw: ['cliente', 'clientes', 'socio', 'socios'],
            msg: 'Los clientes se administran en Socios → Clientes (alta, edición, documentos).',
            sugs: [{ nombre: 'Clientes', desc: 'Socios', url: 'socios/clientes' }]
        },
        {
            kw: ['arqueo', 'caja', 'cuadre'],
            msg: 'El arqueo de caja diario está en Finanzas → Arqueo.',
            sugs: [{ nombre: 'Arqueo', desc: 'Finanzas', url: 'finanzas/arqueo' }]
        },
        {
            kw: ['calculadora', 'calcular', 'cuota', 'simular', 'simulador'],
            msg: 'La calculadora simula planes de pago según monto, tasa y plazo.',
            sugs: [{ nombre: 'Calculadora de cuotas', desc: 'Herramientas', url: 'herramientas/calculadora' }]
        },
        {
            kw: ['plan de pago', 'plan', 'cuotas', 'amortizacion'],
            msg: 'El plan de pago completo de cada crédito está en su ficha (Crédito → Créditos).',
            sugs: [{ nombre: 'Créditos', desc: 'Ver créditos activos', url: 'creditos' }]
        },
        {
            kw: ['documento', 'contrato', 'pagare', 'documentacion'],
            msg: 'Los contratos y pagarés se generan en Herramientas → Generar documentación.',
            sugs: [{ nombre: 'Generar documentación', desc: 'Herramientas', url: 'herramientas/documentacion' }]
        },
        {
            kw: ['mora', 'atraso', 'atrasado', 'vencida', 'vencidas', 'no pago', 'deuda'],
            msg: 'La cartera en mora se ve en Recuperación y en el reporte de cartera vigente (días de atraso por cuota).',
            sugs: [
                { nombre: 'Recuperación', desc: 'Cuotas vencidas por gestor', url: 'finanzas/recuperacion' },
                { nombre: 'Cartera vigente', desc: 'Días de atraso y saldo', url: 'credito/cartera' }
            ]
        },
        {
            kw: ['reestructurar', 'refinanciar', 'renegociar', 'condonar'],
            msg: 'La reestructura y refinanciamiento de un crédito se hacen desde la ficha del crédito (Crédito → Créditos → ver).',
            sugs: [{ nombre: 'Créditos', desc: 'Abra la ficha del crédito', url: 'creditos' }]
        },
        {
            kw: ['salir', 'cerrar sesion', 'logout', 'desconectar'],
            msg: 'Cierre sesión desde el menú de usuario (avatar arriba a la derecha).'
        },
        {
            kw: ['notificacion', 'notificaciones', 'alerta', 'alertas'],
            msg: 'Las notificaciones del sistema están en la campana 🔔 del menú superior.'
        },
        {
            kw: ['buscar', 'buscar algo', 'encontrar', 'ir a'],
            msg: 'Use <b>Ctrl+K</b> para abrir el buscador rápido de módulos y pantallas del sistema.',
            sugs: []
        },
        {
            kw: ['exportar', 'excel', 'csv', 'descargar', 'imprimir'],
            msg: 'Los reportes se exportan a CSV/Excel o se imprimen desde los botones de cada reporte.',
            sugs: [{ nombre: 'Índice de reportes', desc: 'Todos los reportes', url: 'reportes' }]
        },
        {
            kw: ['empleado', 'empleados', 'gestor', 'gestores', 'cobrador', 'equipo', 'personal'],
            msg: 'El equipo se gestiona en Socios → Empleados: altas, datos y marcación por carnet+PIN.',
            sugs: [{ nombre: 'Empleados', desc: 'Socios', url: 'socios/empleados' }]
        }
    ];

    /* ── Consultas de datos — traen listas reales vía GET /asistente/datos/{ep} ── */
    var DATOS = [
        {
            kw: ['mora', 'en mora', 'no han pagado', 'sin pagar', 'atrasados', 'atrasadas',
                 'vencidos', 'vencidas', 'clientes en mora', 'deben', 'deudores', 'que deben'],
            msg: 'Créditos con cuotas vencidas:', ep: 'mora', q: 'clientes en mora', sugs: 'Clientes en mora'
        },
        {
            kw: ['pagos hoy', 'pagos de hoy', 'cobros hoy', 'cobros de hoy', 'cobrado hoy',
                 'recibidos hoy', 'recibido hoy', 'cobraron hoy', 'cuanto se cobro', 'cuanto cobramos'],
            msg: 'Pagos cobrados hoy:', ep: 'pagos_hoy', q: 'pagos recibidos hoy', sugs: 'Pagos recibidos hoy'
        },
        {
            kw: ['por cobrar', 'por cobrar hoy', 'cobrar hoy', 'vencen hoy', 'pendientes hoy',
                 'a quien cobrar', 'quien debe pagar', 'pendientes de pago'],
            msg: 'Personas por cobrar (vencidas + hoy):', ep: 'por_cobrar', q: 'a quien debo cobrar hoy', sugs: 'Por cobrar hoy'
        },
        {
            kw: ['solicitudes pendientes', 'por aprobar', 'solicitudes activas', 'en proceso',
                 'solicitudes en revision', 'pendientes de aprobar'],
            msg: 'Solicitudes pendientes de decisión:', ep: 'solicitudes', q: 'solicitudes pendientes', sugs: 'Solicitudes pendientes'
        },
        {
            kw: ['sin documentos', 'sin documentacion', 'falta documentacion',
                 'documentos pendientes', 'faltan documentos', 'expediente incompleto'],
            msg: 'Clientes sin documentación cargada:', ep: 'sin_docs', q: 'clientes sin documentos', sugs: 'Clientes sin documentos'
        },
        {
            kw: ['pagos en revision', 'pagos pendientes', 'aprobar pagos', 'abonos pendientes',
                 'pagos por aprobar'],
            msg: 'Pagos esperando su aprobación:', ep: 'pagos_revision', q: 'pagos por aprobar', sugs: 'Pagos por aprobar'
        }
    ];

    var SALUDOS  = ['hola', 'buenas', 'buenos dias', 'buenas tardes', 'buenas noches', 'hey'];
    var AYUDA    = ['ayuda', 'que puedes', 'que puedes hacer', 'opciones', 'capacidades', 'para que sirves'];
    var GRACIAS  = ['gracias', 'excelente', 'perfecto', 'genial'];

    /* ── Acciones (verbos de hacer: crear, registrar, agregar…) ──
       Si el texto incluye un verbo de acción, el usuario pide HACER algo,
       no ver un reporte — se evalúan los flujos de alta antes que reportes. */
    var VERBOS = ['crear', 'crea', 'creame', 'nuevo', 'nueva', 'quiero', 'registrar', 'registre',
                  'agregar', 'agrega', 'hacer', 'haga', 'necesito', 'dame', 'dar', 'abrir', 'abre',
                  'llevar', 'lleva', 'sacar', 'mostrar', 'muestrame', 'ver', 'revisar', 'ir'];

    var ACCIONES = [
        {
            kw: ['credito', 'creditos', 'prestamo', 'prestamos', 'solicitud', 'solicitudes'],
            msg: 'Para crear un crédito se inicia una <b>solicitud</b>: capture cliente, monto y plazo; luego se evalúa y aprueba.',
            sugs: [
                { nombre: 'Nueva solicitud de crédito', desc: 'Crédito → Solicitudes', url: 'credito/solicitudes/nueva' },
                { nombre: 'Ver solicitudes', desc: 'Listado y estados', url: 'credito/solicitudes' }
            ]
        },
        {
            kw: ['cliente', 'clientes', 'socio', 'socios'],
            msg: 'El alta de cliente incluye datos personales, documentos y dirección.',
            sugs: [{ nombre: 'Nuevo cliente', desc: 'Socios → Clientes', url: 'socios/clientes/crear' }]
        },
        {
            kw: ['empleado', 'empleados', 'gestor', 'gestores', 'cobrador', 'usuario'],
            msg: 'Alta de empleado o gestor con sus datos de contacto.',
            sugs: [{ nombre: 'Nuevo empleado', desc: 'Socios → Empleados', url: 'socios/empleados/crear' }]
        },
        {
            kw: ['pago', 'pagos', 'abono', 'abonos', 'cobro', 'cobrar', 'cuota'],
            msg: 'Los pagos se registran buscando al cliente y aplicando el abono a sus cuotas.',
            sugs: [{ nombre: 'Registrar pago', desc: 'Finanzas → Pagos', url: 'pagos' }]
        },
        {
            kw: ['gasto', 'gastos', 'egreso', 'egresos'],
            msg: 'Registre el gasto de caja con su comprobante.',
            sugs: [{ nombre: 'Registrar gasto', desc: 'Finanzas → Gastos', url: 'finanzas/gastos' }]
        },
        {
            kw: ['ingreso', 'ingresos', 'venta', 'donacion'],
            msg: 'Registre un ingreso adicional con su recibo.',
            sugs: [{ nombre: 'Registrar ingreso', desc: 'Finanzas → Ingresos', url: 'finanzas/ingresos' }]
        },
        {
            kw: ['asistencia', 'marcacion', 'marcar', 'entrada', 'salida', 'horario', 'pin'],
            msg: 'La asistencia del personal se marca con carnet+PIN en el kiosco; el panel admin muestra el registro.',
            sugs: [
                { nombre: 'Panel de asistencia', desc: 'Registro del día', url: 'asistencia' },
                { nombre: 'Empleados', desc: 'Carnet y PIN de marcación', url: 'socios/empleados' }
            ]
        },
        {
            kw: ['arqueo', 'caja', 'cuadre', 'cierre', 'cerrar'],
            msg: 'El arqueo cuadra la caja del día: cuotas cobradas menos gastos.',
            sugs: [{ nombre: 'Arqueo de caja', desc: 'Finanzas → Arqueo', url: 'finanzas/arqueo' }]
        },
        {
            kw: ['cobrar', 'cobranza', 'recuperar', 'recuperacion', 'mora', 'vencidas', 'vencido', 'atrasado'],
            msg: 'La recuperación lista las cuotas vencidas por gestor para gestionar el cobro.',
            sugs: [{ nombre: 'Recuperación de cartera', desc: 'Finanzas → Recuperación', url: 'finanzas/recuperacion' }]
        },
        {
            kw: ['documento', 'documentacion', 'contrato', 'pagare', 'recibo'],
            msg: 'Genere contratos, pagarés y relaciones de garantías con los datos del cliente.',
            sugs: [{ nombre: 'Generar documentación', desc: 'Herramientas', url: 'herramientas/documentacion' }]
        },
        {
            kw: ['calcular', 'calculadora', 'cuota', 'cuotas', 'simular', 'simulacion', 'plan'],
            msg: 'Simule el plan de pagos según monto, tasa y plazo antes de crear el crédito.',
            sugs: [{ nombre: 'Calculadora de cuotas', desc: 'Herramientas', url: 'herramientas/calculadora' }]
        },
        {
            kw: ['configuracion', 'configurar', 'ajustes', 'negocio', 'logo', 'ruc', 'categorias'],
            msg: 'La configuración del negocio incluye datos fiscales, plantillas y categorías de reportes.',
            sugs: [{ nombre: 'Configuración', desc: 'Datos del negocio y plantillas', url: 'configuracion' }]
        },
        {
            kw: ['creditos', 'cartera', 'activos', 'desembolsar', 'desembolso', 'aprobados'],
            msg: 'La cartera activa y los desembolsos pendientes están en Crédito → Créditos.',
            sugs: [{ nombre: 'Créditos', desc: 'Cartera activa y desembolsos', url: 'creditos' }]
        }
    ];

    function esAccion(qt) {
        return qt.some(function (t) {
            return VERBOS.some(function (v) {
                return t === v || (v.length >= 3 && t.indexOf(v) === 0) || (t.length >= 3 && v.indexOf(t) === 0);
            });
        });
    }

    function detectarPeriodo(qn) {
        var p = null;
        if (/\b(hoy|dia|diario)\b/.test(qn)) p = 'hoy';
        else if (/\b(semana|semanal)\b/.test(qn)) p = 'la semana';
        else if (/\b(mes|mensual)\b/.test(qn)) p = 'el mes en curso';
        else if (/\b(ano|anual|year)\b/.test(qn)) p = 'el año';
        return p;
    }

    function analizar(q) {
        var qn = norm(q), qt = tokens(q);
        var r = { tipo: 'nada', items: [], texto: '', periodo: detectarPeriodo(qn) };

        if (SALUDOS.some(function (s) { return qn === norm(s) || qn.indexOf(norm(s)) === 0; }) && qt.length <= 3) {
            r.tipo = 'saludo'; return r;
        }
        if (GRACIAS.some(function (s) { return qn.indexOf(norm(s)) === 0; })) {
            r.tipo = 'gracias'; return r;
        }
        if (AYUDA.some(function (s) { return qn.indexOf(norm(s)) !== -1; })) {
            r.tipo = 'ayuda'; return r;
        }

        // Consultas de datos reales (mora, pagos de hoy, por cobrar…) —
        // antes que FAQ: «clientes en mora» debe mostrar la lista, no texto enlatado
        for (var d = 0; d < DATOS.length; d++) {
            if (scoreFrase(qn, DATOS[d].kw) >= 4 || scoreKw(qt, DATOS[d].kw) >= 3) {
                r.tipo = 'datos'; r.datos = DATOS[d]; return r;
            }
        }

        // FAQ con frase literal fuerte ("plan de pago", "cambiar contraseña")
        // gana sobre verbos de acción — "ver el plan de pago" es consulta, no registro
        for (var f0 = 0; f0 < FAQ.length; f0++) {
            if (scoreFrase(qn, FAQ[f0].kw) >= 4) { r.tipo = 'faq'; r.faq = FAQ[f0]; return r; }
        }

        // Verbo de acción → el usuario quiere HACER algo (crear, registrar…)
        var accion = esAccion(qt);
        if (accion) {
            for (var a = 0; a < ACCIONES.length; a++) {
                if (scoreKw(qt, ACCIONES[a].kw) >= 3) {
                    r.tipo = 'accion'; r.acc = ACCIONES[a]; return r;
                }
            }
        }

        // Reportes por keywords del catálogo
        var reps = REPORTES.map(function (rep) {
            return { rep: rep, s: scoreKw(qt, rep.kw) + scoreFrase(qn, [rep.nombre]) + scoreTexto(qt, rep.nombre + ' ' + rep.descripcion) };
        }).filter(function (x) { return x.s > 0; })
          .sort(function (a, b) { return b.s - a.s; });

        // Intención genérica "reporte(s)" sin tema → ofrecer índice
        var pideReporte = /\breporte(s)?\b|\binforme(s)?\b|\bestadistica(s)?\b/.test(qn);

        if (reps.length) { r.tipo = 'reportes'; r.items = reps.slice(0, 4).map(function (x) { return x.rep; }); return r; }
        if (pideReporte) { r.tipo = 'todos_reportes'; return r; }

        // FAQ del sistema (keywords sueltas — las frases ya se evaluaron arriba)
        for (var i = 0; i < FAQ.length; i++) {
            if (scoreKw(qt, FAQ[i].kw) >= 3) {
                r.tipo = 'faq'; r.faq = FAQ[i]; return r;
            }
        }

        // Módulos del menú por nombre
        var mods = modulos().map(function (m) {
            return { m: m, s: scoreTexto(qt, m.nombre) + scoreFrase(qn, [m.nombre]) };
        }).filter(function (x) { return x.s > 0; })
          .sort(function (a, b) { return b.s - a.s; });
        if (mods.length) { r.tipo = 'modulos'; r.items = mods.slice(0, 4).map(function (x) { return x.m; }); return r; }

        return r;
    }

    /* ── Sugerencias en vivo para el input del dashboard ──
       Devuelve opciones mezcladas (acción / reporte / módulo) ordenadas
       por score, mientras el usuario escribe. */
    function sugerir(q) {
        var qn = norm(q), qt = tokens(q);
        if (!qt.length) return [];
        var out = [], accion = esAccion(qt);

        // Consultas de datos reales → abren el chat con la lista
        DATOS.forEach(function (d) {
            if (scoreFrase(qn, d.kw) >= 4 || scoreKw(qt, d.kw) >= 3) {
                out.push({ tipo: 'datos', nombre: d.sugs, desc: 'Consulta en vivo', url: '#chat', q: d.q, s: 130 });
            }
        });

        if (accion) {
            ACCIONES.forEach(function (a) {
                if (scoreKw(qt, a.kw) >= 3) {
                    (a.sugs || []).forEach(function (s) {
                        out.push({ tipo: 'accion', nombre: s.nombre, desc: s.desc, url: s.url, s: 100 });
                    });
                }
            });
        }
        REPORTES.forEach(function (rep) {
            var sc = scoreKw(qt, rep.kw) + scoreFrase(qn, [rep.nombre]) + scoreTexto(qt, rep.nombre + ' ' + rep.descripcion);
            if (sc > 0) out.push({ tipo: 'reporte', nombre: rep.nombre, desc: rep.descripcion, url: rep.url, s: sc });
        });
        modulos().forEach(function (m) {
            var sc = scoreTexto(qt, m.nombre) + scoreFrase(qn, [m.nombre]);
            if (sc > 0) out.push({ tipo: 'modulo', nombre: m.nombre, desc: m.grupo, url: m.url, s: sc });
        });

        // Sin resultados: sugerir abrir el chat
        if (!out.length && qt.length >= 3) {
            out.push({ tipo: 'chat', nombre: 'Preguntar al asistente', desc: '«' + q + '»', url: '#chat', s: 1 });
        }
        out.sort(function (a, b) { return b.s - a.s; });
        return out.slice(0, 6);
    }

    /* ═══════════ UI del panel ═══════════ */

    function esc(s) {
        var d = document.createElement('div');
        d.textContent = s == null ? '' : s;
        return d.innerHTML;
    }

    function crear() {
        if (creado) return;
        creado = true;

        overlay = document.createElement('div');
        overlay.className = 'chat-overlay';
        overlay.hidden = true;
        overlay.addEventListener('click', cerrar);

        panel = document.createElement('aside');
        panel.className = 'chat-panel';
        panel.hidden = true;
        panel.innerHTML =
            '<div class="chat-head">' +
                '<div class="chat-avatar">C</div>' +
                '<div class="chat-head-t"><h4>Asistente</h4><small>Contamos — responde sobre su sistema</small></div>' +
                '<button type="button" class="chat-close" aria-label="Cerrar">✕</button>' +
            '</div>' +
            '<div class="chat-body" id="chat-body"></div>' +
            '<div class="chat-foot">' +
                '<input type="text" id="chat-in" placeholder="Pregunte algo…" autocomplete="off">' +
                '<button type="button" class="chat-send" aria-label="Enviar">' +
                    '<svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="19" x2="12" y2="5"/><polyline points="5 12 12 5 19 12"/></svg>' +
                '</button>' +
            '</div>';

        document.body.appendChild(overlay);
        document.body.appendChild(panel);

        body  = panel.querySelector('#chat-body');
        input = panel.querySelector('#chat-in');

        panel.querySelector('.chat-close').addEventListener('click', cerrar);
        panel.querySelector('.chat-send').addEventListener('click', function () { enviar(input.value); });
        input.addEventListener('keydown', function (e) {
            if (e.key === 'Enter') { e.preventDefault(); enviar(input.value); }
        });
        document.addEventListener('keydown', function (e) {
            if (e.key === 'Escape' && !panel.hidden) cerrar();
        });

        saludoInicial();
    }

    function saludoInicial() {
        var chips = ['Clientes en mora', 'Pagos recibidos hoy', '¿Quién debo cobrar?', '¿Qué reportes hay?'];
        msgBot(
            '¡Hola! Soy el asistente de Contamos. Puedo <b>consultar datos reales</b> de su negocio, ' +
            'encontrar <b>reportes</b>, llevarle a un <b>módulo</b> o responder dudas. Pruébeme con: ' +
            '«clientes que no han pagado», «pagos recibidos hoy» o «crear un crédito».',
            [], chips
        );
    }

    function abrir() {
        crear();
        overlay.hidden = false;
        panel.hidden = false;
        input.focus();
    }
    function cerrar() {
        if (overlay) overlay.hidden = true;
        if (panel) panel.hidden = true;
    }

    function msgUser(t) {
        var d = document.createElement('div');
        d.className = 'chat-msg user';
        d.textContent = t;
        body.appendChild(d);
        body.scrollTop = body.scrollHeight;
    }

    function msgBot(html, sugs, chips) {
        var d = document.createElement('div');
        d.className = 'chat-msg bot';
        d.innerHTML = html;

        if (sugs && sugs.length) {
            var wrap = document.createElement('div');
            wrap.className = 'chat-sugs';
            sugs.forEach(function (s) {
                var a = document.createElement('a');
                a.className = 'chat-sug';
                a.href = s.url.indexOf('http') === 0 ? s.url : BASE + s.url.replace(/^\//, '');
                a.innerHTML =
                    '<div class="chat-sug-ico">' + esc((s.nombre || '?').charAt(0).toUpperCase()) + '</div>' +
                    '<div><b>' + esc(s.nombre) + '</b><span>' + esc(s.desc || s.descripcion || s.grupo || '') + '</span></div>';
                wrap.appendChild(a);
            });
            d.appendChild(wrap);
        }

        if (chips && chips.length) {
            var cw = document.createElement('div');
            cw.className = 'chat-chips';
            chips.forEach(function (c) {
                var b = document.createElement('button');
                b.type = 'button';
                b.className = 'chat-chip';
                b.textContent = c;
                b.addEventListener('click', function () { enviar(c); });
                cw.appendChild(b);
            });
            d.appendChild(cw);
        }

        body.appendChild(d);
        body.scrollTop = body.scrollHeight;
    }

    // Lista de datos reales dentro de un mensaje del bot
    function msgLista(html, data) {
        var d = document.createElement('div');
        d.className = 'chat-msg bot';
        d.innerHTML = html;

        if (data.items && data.items.length) {
            var ul = document.createElement('div');
            ul.className = 'chat-list';
            data.items.forEach(function (it) {
                var a = document.createElement('a');
                a.className = 'chat-li';
                a.href = it.url || '#';
                a.innerHTML = '<div class="chat-li-t"><b>' + esc(it.titulo) + '</b><span>' + esc(it.sub || '') + '</span></div>' +
                              '<i>' + esc(it.valor || '') + '</i>';
                ul.appendChild(a);
            });
            d.appendChild(ul);
            if (data.ver_mas) {
                var vm = document.createElement('a');
                vm.className = 'chat-vermas';
                vm.href = data.ver_mas;
                vm.textContent = 'Ver todo →';
                d.appendChild(vm);
            }
        } else {
            d.innerHTML += '<br><small>' + esc(data.vacio || 'Sin resultados.') + '</small>';
        }
        body.appendChild(d);
        body.scrollTop = body.scrollHeight;
    }

    // Consulta /asistente/datos/{ep} y pinta la lista
    function responderDatos(d) {
        var t = typing();
        fetch(BASE + 'asistente/datos/' + d.ep)
            .then(function (x) { return x.ok ? x.json() : null; })
            .then(function (data) {
                t.remove();
                if (!data) { msgBot('No pude consultar ese dato ahora mismo. Pruebe desde el módulo correspondiente.'); return; }
                msgLista(esc(d.msg) + ' <b>' + esc(data.resumen || '') + '</b>', data);
            })
            .catch(function () {
                t.remove();
                msgBot('No pude consultar ese dato ahora mismo.');
            });
    }

    function typing() {
        var d = document.createElement('div');
        d.className = 'chat-typing';
        d.innerHTML = '<i></i><i></i><i></i>';
        body.appendChild(d);
        body.scrollTop = body.scrollHeight;
        return d;
    }

    function sugsReportes(items) {
        return items.map(function (r) {
            return { nombre: r.nombre, desc: r.descripcion, url: r.url };
        });
    }
    function sugsModulos(items) {
        return items.map(function (m) {
            return { nombre: m.nombre, desc: m.grupo, url: m.url };
        });
    }

    function responder(q) {
        var t = typing();
        // Pequeña espera para simular procesamiento
        setTimeout(function () {
            t.remove();
            var r = analizar(q);
            var notaPeriodo = r.periodo
                ? '<br><small>Periodo detectado: <b>' + esc(r.periodo) + '</b> — el reporte abre con su filtro de fecha habitual.</small>'
                : '';

            switch (r.tipo) {
                case 'saludo':
                    msgBot('¡Hola! ¿En qué le ayudo? Puedo buscar reportes, abrir módulos o explicar funciones del sistema.',
                        [], ['¿Qué reportes hay?', 'Cartera en mora', 'Ayuda']);
                    break;

                case 'gracias':
                    msgBot('Con gusto. Si necesita algo más, aquí estoy.',
                        [], ['Ver reportes', 'Ayuda']);
                    break;

                case 'ayuda':
                    msgBot(
                        'Puedo ayudarle con esto:<br>' +
                        '• <b>Datos reales</b> — «clientes en mora», «pagos de hoy», «por cobrar», «solicitudes pendientes»…<br>' +
                        '• <b>Acciones</b> — «crear crédito», «registrar pago», «nuevo cliente»…<br>' +
                        '• <b>Reportes</b> — «cartera», «conami», «finanzas»…<br>' +
                        '• <b>Dudas</b> — «¿cómo registro un pago?», «¿dónde veo el plan de pago?»',
                        [{ nombre: 'Índice de reportes', desc: 'Todos los reportes por categoría', url: 'reportes' }],
                        ['Clientes en mora', 'Pagos recibidos hoy', 'Crear un crédito']
                    );
                    break;

                case 'datos':
                    responderDatos(r.datos);
                    break;

                case 'accion':
                    msgBot(r.acc.msg, r.acc.sugs, []);
                    break;

                case 'reportes':
                    var n = r.items.length;
                    msgBot(
                        'Encontré ' + n + ' reporte' + (n > 1 ? 's' : '') + ' relacionado' + (n > 1 ? 's' : '') +
                        ' con «' + esc(q) + '»:' + notaPeriodo,
                        sugsReportes(r.items),
                        ['Ver todos los reportes']
                    );
                    break;

                case 'todos_reportes':
                    msgBot('Aquí tiene el índice completo de reportes, organizado por categorías:' + notaPeriodo,
                        sugsReportes(REPORTES.slice(0, 4)).concat(
                            REPORTES.length > 4 ? [{ nombre: 'Ver todos', desc: 'Índice de reportes', url: 'reportes' }] : []
                        ),
                        []);
                    break;

                case 'faq':
                    msgBot(esc(r.faq.msg), r.faq.sugs || [], []);
                    break;

                case 'modulos':
                    msgBot('Puede ir directamente a estas secciones:', sugsModulos(r.items), []);
                    break;

                default:
                    msgBot(
                        'No encontré coincidencias para «' + esc(q) + '». Pruebe con: ' +
                        '«cartera», «conami», «cobros», «pagos»… o escriba <b>ayuda</b> para ver lo que puedo hacer.',
                        [{ nombre: 'Índice de reportes', desc: 'Explorar reportes', url: 'reportes' }],
                        ['¿Qué reportes hay?', 'Ayuda']
                    );
            }
        }, 450 + Math.random() * 450);
    }

    function enviar(texto) {
        var q = (texto || '').trim();
        if (!q) { abrir(); return; }
        abrir();
        msgUser(q);
        input.value = '';
        responder(q);
    }

    return { abrir: abrir, cerrar: cerrar, enviar: enviar, sugerir: sugerir };
})();
