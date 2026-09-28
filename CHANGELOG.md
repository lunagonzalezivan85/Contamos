# Bitácora de cambios — Contamos (CFSI)

Registro de cambios por sesión. Más reciente arriba.

---

## 2026-09-28 (AM2) — App IONIC: las 10 pantallas conectadas al API

- **Navegación real**: stack `{seg, param}` en `App.jsx` (`nav()`/`goBack()`); `Shell` + `AppTabs` compartidos en `components/`; tiles/menú/tabs navegan de verdad (el toast "en camino" quedó solo para mensajes).
- **Pantallas**: `Home` (resumen real `/home` con stats clickeables), `Cobros` (lista + modal abonar + voucher `reciboCode`), `Cartera` (búsqueda local) + `Cliente` (ficha: datos/direcciones-Maps/solicitudes/cobros), `Ruta` (paradas + tel/Waze/Maps), `Desembolsos` (entregar → activa crédito), `Solicitud` (cliente de cartera o nuevo + términos, valida mín 1,000 y `limite_credito`), `Actividad`, `Arqueo` ("Mi caja" + fecha), `Calculadora` (`/simulador` → tabla de cuotas), `Perfil`.
- **`api.js`**: 401 → borra sesión y recarga al login; helper `post()` FormData. **`fmt.js`**: `money`/`nombre`/`fHum`/`FREQ_LBL`/`wazeUrl`/`mapsUrl`.
- **Pull-to-refresh** (`IonRefresher`) en Home y todas las listas. ~180 líneas de CSS nuevas (`stats`, `lc`, `badge`, `seg`, `ficha-hero`, `plan-tbl`, `voucher`).
- `PLAN.md` en `IONIC/` con el checklist por fases. Build Vite OK + `cap sync` + `assembleDebug` 35s.

## 2026-09-28 (AM1) — API app: servicios del gestor (`/connect/*`)

- **`ConnectController`** — 12 endpoints nuevos, todos Bearer (`auth()` = `tokenAppValido`): `home` (resumen del día: cobrado/pendiente/paradas/desembolsos/avisos), `cartera`, `cliente/{id}` (ficha: persona + secciones + cobros), `cobros` (cuotas por cobrar + `resumenCobrosHoy` + `METODOS_LBL`), `POST cobros/{sol}/abonar` (pago en campo → REVISION), `GET cobros/{pago}/recibo` (voucher, solo cobros propios vigentes), `ruta` (paradas del día con GPS), `desembolsos` + `POST desembolsos/{id}/entregar` (activa crédito + cuotas + refinancia), `POST solicitud` (`crearSolicitudGestor` — clamp tasa tenant, estado CREADA), `actividad`, `arqueo?fecha=`, `simulador` (`planPagos` con tasa tope + `tipo_calculo` del tenant).
- **`PortalService::empleadoDeId`** — fila completa del empleado (necesita `ruta` para crear solicitudes desde la app).
- **`datos()`** helper — POST acepta JSON o form-urlencoded. `PagoModel::METODOS_LBL/LABEL_ESTADO/reciboCode` expuestos para la UI de la app.
- **IONIC `api.js`**: funciones `home/cartera/cliente/cobros/abonarCobro/reciboCobro/ruta/desembolsos/entregarDesembolso/crearSolicitud/actividad/arqueo/simulador` + helper `post()` FormData (sin preflight).
- `/IONIC/` movido de `.gitignore` a `.git/info/exclude` — sigue sin versionarse pero editable por agentes.
- Probado local: login `TI-0002` → 13 endpoints OK (`home` devuelve resumen real, `simulador` cuota=2226.29 para 5000 a 3 meses).

## 2026-09-27 (PM20) — App móvil IONIC (fase conexión) + API `/{slug}/connect`

- **`ConnectController`** (`Partner/`): `GET /{slug}/connect` handshake (tenant + logo + suspendida), `POST /{slug}/connect/login` (carnet + PIN, reusa `autenticarGestor`) → token firmado HMAC 30 días (`PortalService::tokenApp`/`tokenAppValido`, sin tabla nueva) + `personaDeEmpleado`, y `GET /{slug}/connect/notificaciones` (Bearer → `notificacionesGestor`). CORS abierto + OPTIONS en `connect/*`; CSRF excluido vía `*/connect*` en `Filters.php` (la app no maneja cookie CSRF). Fix: `getJSON()` solo si Content-Type es JSON — explota con form-urlencoded.
- **Carpeta `IONIC/`**: **Ionic React + Vite + Capacitor** (`@ionic/react`, `ionicons`, `react@18`). Flujo: splash "C" → setup de URL (valida `GET {base}connect` con spinner → "Conectado") → login carnet + PIN 4 dígitos (auto-avance/paste, cajas `.filled`) → home: toolbar con **campana de notificaciones** (`IonBadge` + `IonModal` bottom-sheet), hero del gestor, tiles, `IonTabBar` inferior y `IonMenu` lateral con las 9 secciones. `localStorage` (`cf.baseUrl`, `cf.tenant`, `cf.token`, `cf.gestor`) — sesión guardada entra directo. `webDir: dist` + `allowMixedContent` para HTTP en dev.
- Probado local: `connect` y `connect/login` responden OK (`TI-0002`/`3713` → gestor "Juan Perez").

## 2026-09-27 (PM19) — Portal gestor: ruta de cobro en mapa (`/portal/mapa`)

- **Nueva sección** "Ruta de cobro" (sidebar + badge "Mapa" en Cobros): mapa MapLibre con las paradas del gestor — créditos con cuota que vence hoy o vencida (`PortalService::rutaCobrosHoy`, mejor dirección del cliente con `latitud/longitud` preferida).
- **Geolocalización**: `navigator.geolocation` (botón "Mi ubicación"); orden sugerido vecino-más-cercano, markers numerados, distancia **lineal** por parada (haversine, sin tráfico). Clientes sin GPS quedan al final, marcados "sin GPS".
- **Ruta dibujada**: OSRM `router.project-osrm.org` (driving, gratis sin key) con fallback a polyline recta si falla.
- **Modal "Elegir clientes"**: checkboxes + Todos/Ninguno → redibuja mapa y lista.
- **Navegar**: links Waze (`waze.com/ul?ll=`) y Google Maps (`maps/dir?destination=`) por parada — con coordenadas o búsqueda por dirección si no hay GPS.
- Archivos: `Routes.php`, `PortalController::mapa`, `PortalService::rutaCobrosHoy`, `partner/portal/mapa.php`, `public/js/portal/mapa.js`, `portal.css` (`.ruta-*`), `layouts/portal.php` (`sideItems` + `tabMap`).

## 2026-09-27 (PM18) — Portal gestor: sliders → inputs numéricos

- `portal/solicitud.php` y `portal/calculadora.php`: los 4 `input[type=range]` (monto, tasa, plazo, días/semana) ahora son `type="number"` (`.calc-num`) — el usuario reportó que la solicitud dilataba por los sliders. El JS lee `.value` + evento `input`, funciona igual; `min`/`max`/`step`/`inputmode` conservados (teclado numérico en móvil). `aplicarLimite` sigue ajustando `montoIn.max` por cliente.
- `portal.css`: nueva clase `.calc-num` (estilo igual al resto de inputs del portal).

## 2026-09-27 (PM17) — Fix: panel de notificaciones vacío

- `app.js`: el panel hacía `fetch(window.APP_BASE + 'notificaciones…')` pero `APP_BASE` es una `var` **local del IIFE** (línea 11), no global → URL `'undefinednotificaciones'` → catch → "Error al cargar". El badge sí se pintaba porque la carga inicial usa `APP_BASE` local. Cambiados los 3 fetch (`notificaciones`, `/{id}/leida`, `/leer-todas`) a `APP_BASE`.

## 2026-09-27 (PM16) — `/herramientas/calculadora` con tipo de cobro

- Selector **Tipo de cobro** (Francés / Flat / Alemán / Anticipado), default = `tenants.tipo_calculo`. JS del propio view calcula cuota e interés total por método (misma matemática que la calculadora del portal); etiqueta del resultado indica el método ("1ra cuota" para Alemán, "(sin interés)" para Anticipado).
- **Responsive**: grids inline reemplazados por `.calc-split` (parámetros|resultado, 1 col ≤980px) y `.calc-inner` (campos 2 col → 1 col ≤560px) en `app.css` — el inline `minmax(320px,420px) 1fr` bloqueaba cualquier media query.

## 2026-09-27 (PM15) — Cobro de suscripción con usuarios extra (USD 3) + dropdowns admin

- **Usuario extra = USD 3**: nueva constante `PLAN_USD_EXTRA_USUARIO` y `plan_cobro_mes()` en `plan_helper` — desglose = precio del plan + (activos − `max_usuarios`) × $3. `UsuarioController::guardar` avisa con flash `warning` cuando el alta excede el plan; hint en `admin/usuarios/nuevo`.
- **Botón Cobrar** en la ficha del tenant (`admin/tenants/{id}`): modal con desglose (plan, usuarios incluidos/activos, extras × $3, total) + método/referencia/fecha/observación → `POST admin/tenants/{id}/cobrar` → `TenantService::cobrarSuscripcion` hace upsert en `plan_pagos` del período `YYYY-MM` (estado PAGADO, desglose en `observacion`, bloquea si ya está pagado).
- **Acciones en dropdown**: componente `.dd`/`.dd-menu`/`.dd-item` (CSS en `admin.css`, toggle delegado en `app.js`) aplicado en `admin/usuarios/index` y en la tabla de usuarios de la ficha del tenant (Editar / Activar-Desactivar / Resetear clave / Quitar).

## 2026-09-27 (PM14) — Gestor hereda tipo_calculo (portal)

- `data-tipo="<?= tenant.tipo_calculo ?>"` en `portal/solicitud.php` (`#sol-form`) y `portal/calculadora.php` (`#calc-app`) — el gestor ve y calcula con el método de la empresa, sin elegirlo.
- `portal/solicitud.js` y `portal/calculadora.js`: cuota estimada y plan simulado por método — FLAT `(P/n + P·iP)`, ALEMAN 1ra cuota + interés `iP·P·(n+1)/2`, ANTICIPADO solo capital + nota "Interés anticipado", FRANCES cuota fija. Etiqueta muestra el método ("Cuota semanal estimada · Flat", resumen con fila Método, título del plan con el tipo).
- `PortalService::crearSolicitudGestor` guarda `tipo_calculo` del tenant en la solicitud (default FLAT).

## 2026-09-27 (PM13) — Nueva/Editar solicitud responsive

- El inline `style="grid-template-columns: 1.4fr 1fr"` en `.sol-split` bloqueaba el `@media` de `app.css` → en móvil quedaban 2 columnas aplastadas. Reemplazado por `.sol-split-lg` (1.4fr solo ≥981px).
- Media ≤768px nuevo en `app.css`: `.form-grid` a 1 columna, `.seg` con wrap (opciones 30%), `.sol-cliente` wrap, `.page-title` 19px, modal plan a ancho completo con tabla compacta.

## 2026-09-27 (PM12) — Modal "Plan de pago sugerido" en nueva/editar

- Botón **Ver plan sugerido** junto al campo Primer pago en `nueva.php`/`editar.php` — abre `#modal-plan` (sistema `.modal-overlay`/`data-modal`/`data-close` existente) con la tabla de cuotas calculada en vivo.
- `public/js/plan-sugerido.js` (nuevo): replica `SolicitudService::planPagos` sin gracia — mismas fechas por frecuencia (DI sin domingo, Q día 15/fin de mes), cuotas por tipo FLAT/FRANCES/ALEMAN/ANTICIPADO, fila de totales y nota de interés anticipado. Estilos `.plan-*` en `app.css`.
- Es **solo visual** — el plan real se genera al aprobar (nota en el pie del modal).

## 2026-09-27 (PM11) — tipo_calculo por tenant, default FLAT

- El **Tipo de cálculo por defecto** ya existía en `/configuracion` pero la columna venía de `ProductoCredito` con default FRANCES. Nueva migración `2026-09-27-000014_TenantTipoCalculo`: `MODIFY` a `DEFAULT 'FLAT'` y normaliza tenants existentes.
- Defaults unificados a **FLAT** en `ConfiguracionService::guardar`, `configuracion/index.php`, `aprobar.php`, `nueva.php` y `crearOficina`/`actualizar`.
- Repo inicializado y subido a `github.com/lunagonzalezivan85/Contamos` (branch `main`); `.gitignore` excluye dumps/SQL/zips de `writable/` y `.env`.

## 2026-09-27 (PM10) — Tipo de cálculo + fecha de primer pago en nueva/editar

- **Nueva y Editar solicitud** ahora incluyen **Tipo de cálculo** (FRANCES / FLAT / ALEMAN / ANTICIPADO, por defecto FLAT — tasa × meses sobre el capital) y campo **Primer pago** (`fecha_primer_pago`, prefijada con `primerPagoSugerido`). Ambos se guardan en la solicitud y la vista de aprobación ya prefill la fecha elegida.
- **Resumen en vivo** respeta el tipo: FLAT `(P + P·t·meses)/n`, FRANCES cuota fija, ALEMAN primera cuota + total `P·iP·(n+1)/2`, ANTICIPADO cuota de capital; se muestra también la fecha de primer pago.
- `crearOficina`/`actualizar` persisten `tipo_calculo` y `fecha_primer_pago` (regex `YYYY-MM-DD`); `actualizar` los valida en el controlador.

## 2026-09-27 (PM9) — Editar solicitud adopta el layout de Nueva solicitud

- `editar.php` elimina el wizard de 3 pasos y pasa al layout de `nueva.php`: `page-head` con botón Volver + `.sol-split` 2 columnas — izquierda: cards Cliente (ficha fija + gestor + destino) y Préstamo (monto + mini-chips, tasa editable, plazo, frecuencia `.seg`, días/semana); derecha: card Resumen sticky con cuota estimada en vivo, badge de estado y botón Guardar.
- `solicitud-editar.js` reescrito como el script de `nueva.php` (resumen `rs-*` en vivo, radios de frecuencia, chips de monto, validación al submit) con la tasa editable como diferencia.

## 2026-09-27 (PM8) — Fix chat: cerrar + listas reales · editar igual a nueva

- **Cerrar del chat**: `.chat-panel {display:flex}` anulaba el atributo `hidden` — agregada regla `.chat-overlay[hidden], .chat-panel[hidden]{display:none}`; el botón ✕, Esc y el overlay ahora cierran.
- **Listas reales**: en `analizar()` el intent DATOS ahora evalúa **antes** que el FAQ literal — "clientes en mora" ya no cae en el FAQ de «mora» sino que trae la lista real del endpoint.
- **Editar solicitud = Nueva solicitud**: paso 2 ahora usa `.form-grid` + inputs numéricos planos, `mini-chips` de monto (1k/5k/10k/20k/50k) y frecuencia con radios `.seg` (sin input hidden). JS: frecuencia por radios, chips de monto, mínimo C$1,000. `.calc-num` eliminada de portal.css (sin uso).

## 2026-09-27 (PM7) — Fix: sugerencias de datos del asistente abren el chat

- `chat-ai.js` → `sugerir()` ahora incluye las consultas **DATOS** como opción "Consulta en vivo" (badge *Datos*, ámbar) con `url '#chat'` + `q` canónica — antes el dropdown solo ofrecía módulos/reportes y "clientes en mora" navegaba a una pantalla en vez de responder.
- `app.js` → las opciones del dropdown llevan `data-q`; al clicar una sugerencia `#chat` el input adopta la consulta canónica y se envía al asistente → abre el chat y pinta `.chat-list` con resumen + filas (nombre, gestor, monto/días) + "Ver todo →".

## 2026-09-27 (PM6) — Edición de solicitud: inputs en vez de sliders

- `Views/partner/solicitudes/editar.php`: monto, tasa, plazo y días/semana pasan de `input[type=range]` a `input[type=number]` con sufijo de unidad (`.calc-num` en portal.css). La tasa ya no queda topada al 10% del slider (ahora 0–100, paso 0.01).
- `solicitud-editar.js`: eliminadas las etiquetas `<output>` de los sliders (ya no existen); cuota en vivo intacta vía `input` events; validación del paso 2 reforzada (tasa 0–100 %, plazo > 0 con foco en el campo).

## 2026-09-27 (PM5) — Chips-KPI accionables + asistente consulta datos reales

- Chips del dashboard rediseñados como **KPIs en una línea** (scroll horizontal, `.chips` nowrap), cada uno clicable al módulo donde se resuelve: Clientes, Sin documentos, Por aprobar, Por desembolsar, Por cobrar hoy, En mora, Cobrado hoy ($), Pagos en revisión. Los pendientes se pintan en ámbar (`.chip-alert`).
- `DashboardService::contadores` ahora calcula datos reales: clientes sin documentos (`persona_documentos`), solicitudes REVISION (por aprobar) y DESEMBOLSO, créditos con cuota vencida/por cobrar hoy (PENDIENTE|PARCIAL con `fecha_vence <= hoy`), Σ pagos APLICADO hoy y pagos en REVISION.
- `GET /asistente/datos/{tipo}` (`AsistenteController` + `AsistenteService`): JSON `{resumen, items[{titulo,sub,valor,url}], vacio, ver_mas}` para `mora`, `pagos_hoy`, `por_cobrar`, `solicitudes`, `sin_docs`, `pagos_revision` — tenant-scoped, máx 12 filas.
- `chat-ai.js`: intent **DATOS** (frases/keywords de consulta) → fetch al endpoint y pinta `.chat-list` con filas clicable (nombre, detalle, monto/días) + "Ver todo →". Saludo y ayuda promocionan las consultas de datos.

## 2026-09-27 (PM4) — Buscador de reportes + asistente Chat-AI en dashboard

- `/reportes` buscador mejorado: **sin acentos** (normaliza NFD), **multi-token** (todas las palabras deben coincidir), **chips de categoría** (filtra por sección), teclado (`/` enfoca, flechas navegan cards, Enter abre, Esc limpia) y contador de resultados.
- **Chat-AI** (`public/js/chat-ai.js` + `public/css/chat.css`): al escribir en el input del dashboard y presionar Enter/▲ se abre un panel de chat lateral que analiza el texto con un **parser sintáctico** (normaliza, tokeniza, puntúa keywords + frases + nombres) y responde con mensajes conversacionales, tarjetas clicables a reportes/módulos y chips de sugerencias. Indicador "escribiendo…" simulado.
- Conocimiento del asistente: `window.CHAT_REPORTES` (catálogo `Config\Reportes` filtrado por permisos + `kw` keywords por reporte), módulos leídos de la paleta, FAQ enlatada (pagos, solicitudes, clientes, arqueo, calculadora, plan de pago, documentación, contraseña), saludos/ayuda, detección de periodo (hoy/semana/mes/año).
- `app.js` → `procesarIA` delega a `ChatAI.enviar(q)` cuando el módulo está cargado (dashboard); el dropdown `\` de acciones rápidas se conserva.
- Fix: capa **verbos de acción** (`VERBOS`/`ACCIONES`) — «créame un crédito» ahora sugiere *Nueva solicitud de crédito* en vez del reporte; frases literales de FAQ («plan de pago») evalúan antes que acciones.
- **Sugerencias en vivo**: `ChatAI.sugerir(q)` alimenta el dropdown del input IA mientras se escribe (≥2 chars) con opciones tipadas — badges *Acción* (alta/registro), *Reporte*, *Ir a* (módulo), *Preguntar* (abre el chat si no hay match). `\` sigue listando acciones de la paleta; Enter navega/abra la primera sugerencia.
- Conocimiento ampliado: ACCIONES +6 (asistencia, arqueo, cobranza/recuperación, documentación, calculadora, configuración, cartera/desembolsos) y FAQ +7 (mora, reestructurar/refinanciar, cerrar sesión, notificaciones, búsqueda Ctrl+K, exportar/imprimir, empleados).

## 2026-09-27 (PM3) — Índice de reportes con categorías administrables

- `GET /reportes` (menú top-level **Reportes**, filtro `auth`): índice de cards agrupadas por categoría con buscador JS instantáneo; cada reporte se filtra por su permiso del catálogo.
- `Config\Reportes` — catálogo estático de reportes (key → nombre, descripción, icono, url, permiso, categoría por defecto). Registrar un reporte nuevo ahí lo hace aparecer en el índice y en la pantalla de categorías.
- Migración `2026-09-27-000013_ReporteCategorias` — `reporte_categorias` (nombre/orden/activo por tenant) + `reporte_asignaciones` (reporte_key → categoria_id, unique por tenant).
- `GET /configuracion/reportes` (permiso `admin.configuracion`, link desde Configuración): CRUD inline de categorías (crear, renombrar, ordenar, activar/desactivar, eliminar — las asignaciones se limpian) + form de asignación reporte→categoría con opción "Por defecto".
- `ReporteService`: `categoriasReporte`, `asignacionesReporte`, `catalogoAgrupado` (agrupa por categoría personalizada o default, ordenadas), `crear/guardar/eliminarCategoriaReporte`, `guardarAsignaciones` (upsert validado).

## 2026-09-27 (PM2) — Reporte CONAMI: clasificación de cartera por riesgo

- `GET /credito/reporte-conami` (permiso `reportes.ver`, menú Crédito → CONAMI): clasifica la cartera activa por categorías de riesgo según días de atraso de la cuota más vieja sin cubrir — **A1** 0–30 (1%), **A2** 31–90 (5%), **B** 91–120 (25%), **C1** 121–150 (50%), **C2** 151–180 (75%), **D1** 181–365 (90%), **D2** +365 (100%).
- `ReporteService::carteraConami` + constante `CONAMI` editable (rangos/%): saldo capital insoluto por cuota (`LEAST(capital, cuota−pagado)` — el pago cubre interés primero), excluye cuotas ANULADAS por reestructuración.
- Vista `partner/reportes/conami.php`: KPIs (cartera, vencido, % mora, provisión requerida, cobertura), resumen por categoría y detalle por crédito (folio, cliente, cédula, gestor, ruta); filtro por fecha de corte, **exportar Excel** (CSV BOM+;) e **imprimir** (CSS print).

## 2026-09-27 (PM) — Ficha de tenant + gestión de usuarios por tenant

- `/admin/tenants` rediseñado: lista clicable (`.tn-row` en admin.css) — cada fila abre la ficha.
- `GET /admin/tenants/{id}` nuevo: ficha con stats (usuarios, clientes, solicitudes, créditos activos), datos de empresa (plan, suscripción, RUC, registro CONAMI, tasa, contacto) y tabla de usuarios del tenant.
- Acciones en la ficha: Ver portal ↗, Suspender/Reactivar (toggle ahora vuelve con `redirect()->back()`), + Usuario (preselecciona el tenant via `?tenant=` en `usuarios/nuevo`), Editar, Desactivar/Activar y **Quitar** (soft delete; `POST tenants/{tid}/usuarios/{uid}/quitar`, no permite quitarse a sí mismo).
- `UsuarioController::backTo()` — propagación de `?back=admin/tenants/{id}` (GET y POST) en nuevo, editar, actualizar, toggle y clave: todo regresa a la ficha del tenant.
- Márgenes: `.card` ahora tiene `margin-bottom: 20px` (separación real entre cards en la ficha y todo el panel admin); grid de datos de empresa con `gap:18px 28px`.
- **Reset rápido de clave** (`POST usuarios/{id}/reset-rapido` → `UsuarioController::resetRapido`): genera clave temporal de 8 caracteres sin ambiguos (0/O, 1/l/I), marca `debe_cambiar_password`. En la ficha abre **modal AJAX** (`.moverlay/.modal/.mr-pass`): confirma → fetch JSON → muestra la clave con **Copiar clave**, **Copiar credenciales** (clipboard) y **WhatsApp ↗** (wa.me). Sin JS sigue el flujo de redirect + banner `.pass-reset`. El superadmin no puede resetear su propia clave (usa el cambio de perfil).

---

## 2026-09-27 (AM2) — Sello CONAMI + lenguaje local en landings

- Migración `2026-09-27-000012_ConamiRegistro`: `tenants.conami_registro` (VARCHAR 40).
- Campo "Registro CONAMI" en `/configuracion` → Datos de la empresa (validación + `ConfiguracionService` + `TenantModel`).
- Si el registro está cargado: sello "Registrada ante CONAMI · Reg. Nº X" (icono `shield`, clase `.lp-conami`) en footers de la landing del portal y la solicitud pública; y "Reg. CONAMI Nº X" en los encabezados del contrato y la relación de garantías.
- Importante: CONAMI registra a la **empresa** (Ley 843), no certifica software — por eso es un campo editable, no una marca automática.

### Textos públicos adaptados a Nicaragua (`landing/index.php`, `portal/landing.php`)
- Meta-strip: fuera "folio único C-XX-AAAA-0000" / "Plan francés" / "portal para gestor de ruta" → "Número de crédito único", "Plan de cuotas automático al entregar el dinero", "Recibo numerado en cada cobro".
- "Gestor/gestores" → "asesor de crédito" (término que usa el sector microfinanciero local) en beneficios, proceso, app móvil, FAQ y CTAs del portal del tenant.
- "Dirigido a": "Financieras y cooperativas" → "Microfinancieras y cooperativas… registradas ante CONAMI".

---

## 2026-09-27 (AM) — Cumplimiento legal Nicaragua: RUC + cláusulas del contrato

### RUC del tenant (identidad fiscal)
- Migración `2026-09-27-000011_TenantRuc`: `tenants.ruc` (VARCHAR 30, nullable).
- `TenantModel::$allowedFields` + `ConfiguracionService::guardarDatos` + validación en `ConfiguracionController`.
- Nuevo campo "RUC" en `/configuracion` → Datos de la empresa.
- Impreso en: contrato (partes y encabezado), relación de garantías y recibo de pago (bajo la dirección).

### Contrato de crédito — cláusulas legales nuevas (`documentos.php`)
- **Cuarto — Costo total del crédito**: intereses + cargos administrativos/seguro (`comision_pct` + `seguro_pct` del tenant), monto total a pagar y **CAT** (costo anualizado sobre monto/plazo) — transparencia Ley 842.
- **Sexto — Incumplimiento e interés moratorio**: añade el `mora_diaria_pct`% diario sobre cuota vencida (la mora ya se devengaba en cuotas; ahora queda pactada en el documento).
- **Séptimo — Protección de datos personales**: consentimiento expreso de tratamiento (Ley 787) con finalidad limitada al crédito.
- **Octavo — Origen de fondos**: declaración PLD de fondos lícitos (Ley 977).
- El acreedor comparece ahora con `razon_social` + RUC (fallback nombre comercial).

### Solicitud pública `/portal/solicitar`
- Aviso actualizado: autorización de tratamiento de datos de contacto citando Ley 787.

---

## 2026-09-26/27 — Plan de metas + sidebar completo + fixes de permisos

### Fix: permisos multi-tenant (ingresos/gastos)
- `caja.ingresos` solo se había otorgado al tenant 1 → el menú "Ingresos" no aparecía en tenants 2 y 3.
- Migraciones `000008_Gastos` y `000009_Ingresos`: ahora otorgan el permiso a los roles en **todos los tenants existentes** (check de duplicado filtrado por `tenant_id`).
- Recordatorio: permisos se cargan en sesión al login — cambios requieren cerrar y volver a entrar.
- Fix menores vistas: títulos de modal en texto plano (`gastos.php`, `ingresos.php`), limpieza de `??` muerto.

### Módulo Plan de metas — NUEVO (`/finanzas/metas`, permiso `metas.plan`)
- Migración `2026-09-27-000010_Metas`: tablas `meta_metricas` (catálogo de métricas) + `metas` (gestor + métrica + periodo YYYY-MM) + permiso `metas.plan` en todos los tenants.
- `Models/{MetaModel,MetaMetricaModel}.php`, `Services/Partner/MetaService.php`, `Controllers/Partner/MetaController.php`, `Views/partner/finanzas/metas.php`.
- Métricas AUTO sembradas por tenant: **recuperación** (cobros APLICADO), **captación de clientes**, **solicitudes ingresadas**, **colocación** (monto desembolsado) y **tasa de mora**.
- Modos: MINIMO (alcanzar al menos X) / MAXIMO (no superar X); unidades Monto/Cantidad/%.
- Métricas custom = MANUAL (avance se ingresa a mano); campo `formula` describe cómo se calcula cada métrica (visible en listado y formulario).
- Listado: meta vs avance, barra de progreso, % cumplimiento y estado (Cumplida/En curso/Incumplida/Anulada) + tarjetas resumen.
- Unicidad gestor+métrica+periodo validada en service solo entre ACTIVAS (permite recrear tras anular).

### Sidebar sin enlaces rotos — 11 páginas construidas
Auditoría de todos los URLs del menú contra `Routes.php`. Construido:

**Partner (6):**
- `/credito/cartera` — `CreditoController::cartera` + `PagoService::cartera()`: salud de cartera (saldo, vencido, días atraso, badge Al día/En mora, filtro "solo en mora").
- `/credito/reporte` — `ReporteController::creditos` + `ReporteService`: pipeline por estado, desembolsos del periodo, gestión por gestor, cartera activa/en mora (permiso `reportes.ver`).
- `/finanzas/recuperacion` — `PagoController::recuperacion` + `PagoService::recuperacion()`: cuotas vencidas por crédito/gestor (permiso `pagos.ver`).
- `/finanzas/reporte` — `ReporteController::finanzas`: cobros por método/gestor + otros ingresos − gastos = resultado neto (permiso `reportes.ver`).
- `/herramientas/calculadora` — `HerramientasController::calculadora`: simulador de cuota francesa (monto/plazo/frecuencia/tasa).
- `/herramientas/documentacion` — `HerramientasController::documentacion`: selector de solicitud → documentos (permiso `solicitudes.documentacion`).

**Admin (5):**
- `/admin/tenants` — listado + `POST toggle` activar/suspender (`Admin\TenantController`).
- `/admin/tenants/nuevo` — alta con `Services/Admin/TenantService::crear()`: provisiona menús, `role_permissions` y `role_menus` copiando al tenant 1 + crea usuario admin.
- `/admin/usuarios` — usuarios de todos los tenants, filtro tenant + búsqueda (solo lectura).
- `/admin/auditoria` — bitácora `audit_logs` global con filtros tenant/módulo/fechas (máx 300).
- `/admin/configuracion` — catálogo `planes` editable (precio y límites; -1 = ilimitado).

**Otros:** `admin.css` + bloque `.btn`/`.inp`/`.filter-bar`/`.tag-info` (no existían estilos de form admin); `ESTRUCTURA.md` actualizado; rutas verificadas con `spark routes`.

---

## 2026-09-25 (PM) — Regla cero-espagueti + cartera/ruta + estados

### Separación de JS/CSS (regla cero espagueti)
- **`public/css/landing.css`** — estilos `lp-*` de la landing pública (separados de `app.css`).
- **`public/css/portal.css`** — estilos `app-*`, `calc-*`, `freq-*`, `wiz-*`, `sol-*`, `portal-*`, `.btn-block` del portal del gestor.
- **`public/js/portal/calculadora.js`** — calculadora (sistema francés + frecuencia). Lee moneda de `data-mon` en `#calc-app`.
- **`public/js/portal/solicitud.js`** — wizard de solicitud (pasos, sliders, frecuencia, resumen). Lee moneda de `data-mon` en `#sol-form`.
- `layouts/portal.php`: carga `portal.css` + nueva sección `renderSection('scripts')`.
- `landing.php`: carga `landing.css` (standalone, no usa layout).

### Cartera de clientes y rutas
- Migración `2026-09-25-000001_SolicitudCarteraRuta`:
  - `empleados` + `ruta`
  - `solicitudes` + `asignado_a` (FK→empleados) + `ruta`
- Empleado: campo **Ruta asignada** en formulario + modelo + validación + crear/editar.
- `guardarSolicitud`: guarda `empleado_id` (quién la creó) y `asignado_a` (a quién está asignada — cartera del gestor) + `ruta` del gestor.

### Estados de solicitud
- Nuevo flujo: `CREADA → REVISION → APROBADA → DESEMBOLSO` (+`RECHAZADA`).
- `SolicitudModel::CREADA/REVISION/APROBADA/DESEMBOLSO/RECHAZADA/ESTADOS`.
- Migración de `PENDIENTE`→`CREADA` en registros existentes.
- Bug corregido: `SolicitudModel::CREADA` no existía (fatal → POST abortaba).

### Prueba E2E (guardado verificado)
- Login gestor (TI-0001/4521) → GET solicitud → POST solicitud: **303 OK**.
- BD: solicitud `#1` estado `CREADA`, `cliente_id=1`, `empleado_id=1`, `asignado_a=1`, `frecuencia=M`, `monto=50000`.

### Solicitudes del tenant (`/credito/solicitudes`) — NUEVO
- **`SolicitudController`** (Partner): `index()` con filtros + paginación, `ver()` detalle, `cambiarEstado()` flujo de estados.
- **Filtros**: cliente/cédula/código, estado, gestor (asignado), ruta, rango de fechas.
- **Detalle**: badge de estado, cuota estimada, datos del préstamo/cliente/cartera, acciones (Enviar a revisión / Aprobar / Desembolsar / Rechazar) según permiso.
- Rutas: `credito/solicitudes`, `credito/solicitudes/{id}`, `POST .../{id}/estado` — permiso `solicitudes.ver`.
- `SolicitudModel`: `filtrar()` (query paginable), `detalle()` (cliente+creador+asignado), `PERMISO_ESTADO`, `LABEL_ESTADO`.

### Paginación en todos los listados (regla nueva)
- **`partials/pager.php`** — template reusable; registrado como `'cfsi'` en `Config/Pager.php`.
- `ClienteModel`/`EmpleadoModel`: `filtrar()` acumula en el builder → permite `->paginate(15)`.
- `clientes`, `empleados`, portal `cartera`, portal `actividad` → paginados (10-15/página); `pager->only([...])` preserva filtros.
- `partials/list.php` — `meta_class` opcional para badges.
- Bug: `select('DISTINCT ruta')` generaba SQL inválido → `->distinct()->select('ruta')`.
- Iconos nuevos: `send`, `external-link`. CSS: `.sol-badge-*`, `.sol-filters`, `.pager*`, `.sol-estado-head`, `.btn-danger`.

### E2E verificado
- Login galvin → `/credito/solicitudes` = **200** (items+badges), `?estado=CREADA` filtra, `/1` = **200**, POST estado→REVISION = **303**.

### Detalle de solicitud — checklist + edición + bloqueo de aprobación
- **Checklist "Expediente del cliente"**: requeridos (cédula, teléfono, dirección, contactos, referencias, ingresos, documentos) + opcionales (negocio, activos, pasivos); badge "Faltan N requeridos" / "Completo"; botón "Completar expediente" → ficha del cliente.
- **Editar solicitud**: `GET/POST /credito/solicitudes/{id}/editar` (permiso `solicitudes.editar`) — wizard de 3 pasos idéntico al portal (Cliente+gestor → Préstamo dinámico con sliders y cuota en vivo → Resumen); carga `portal.css` en head; JS `js/solicitud-editar.js`. Reasignar gestor sincroniza `ruta`. Solo editable en CREADA/REVISION.
- **Bloqueo de aprobación**: `cambiarEstado` rechaza pasar a APROBADA si faltan requeridos del expediente (server-side) y el botón se muestra deshabilitado en la vista.
- `checklistCliente()` privado compartido entre `ver()` y `cambiarEstado()`; `detalle()` ahora trae `persona_id`/`email`.
- JS externo `js/solicitud-editar.js` (toggle días DI — cero inline); `layouts/partner.php` ahora renderiza sección `scripts`.
- CSS: `.sol-detail` (gap entre cards), `.sol-check*`, `.sol-form-actions`, `.sol-ruta`, `.btn[disabled]`.
- E2E: GET editar=200, POST editar=303 (monto 60000 quincenal guardado), POST APROBADA **bloqueado** (estado sigue REVISION).

### Parámetros de crédito del tenant
- Migración `2026-09-25-000002`: `tenants.tasa_interes` (DECIMAL 5,2, default 3.00) + `tenants.plazo_meses_max` (INT, default 24).
- Config → nueva sección "Parámetros de crédito": tasa mensual (%) y plazo máximo (meses).
- Los wizards de solicitud (portal + edición) usan estos valores: tasa como default del slider, `plazo_meses_max` como `max` del slider de plazo.
- Validación server-side: portal `guardarSolicitud` y `SolicitudController::actualizar` rechazan plazo > `plazo_meses_max`.
- E2E: POST config guarda tasa=4.5/plazo=18 → wizard de edición muestra slider plazo con `max="18"`.

### Sidebar → Portal del gestor
- Nuevo item "Portal" (icono `external-link`) en el menú tenant → `/{slug}/portal`.
- `MenuService::resolverUrls()` reemplaza placeholders `{slug}` por `tenant_slug` de sesión (soporta hijos también).
- E2E: dashboard renderiza link `/tu-impulso/portal` en sidebar.

### Solicitud de crédito pública ("Por contactar")
- **Migración** `2026-09-25-000003`: `solicitudes.origen` (INTERNO/WEB).
- **Estado nuevo `CONTACTO`** ("Por contactar") en `SolicitudModel` — primer estado del embudo; se filtra en el listado.
- **`/{slug}/portal/solicitar`** — formulario público sin login (nombres, apellidos, teléfono, cédula, monto, destino) con honeypot antispam y pantalla de confirmación.
- **Dedupe**: busca persona por cédula (fallback teléfono) dentro del tenant → reutiliza su ficha, completa datos faltantes; si el cliente ya tiene una solicitud `CONTACTO` pendiente, la actualiza (monto/destino) en vez de duplicar el lead.
- Crea persona + cliente + solicitud `CONTACTO`/`WEB` sin gestor; el staff la ve en el listado con badge azul + etiqueta "Web".
- **Detalle**: panel "Estado actual" centrado — badges arriba, callout azul "llama al cliente al {tel}…" centrado encima de los botones (solo CONTACTO), botones centrados (`sol-estado-head` columna, `sol-acciones` centrado); botón **Iniciar gestión** (CONTACTO→CREADA, permiso `solicitudes.editar`); editable en CONTACTO.
- Guard: a CREADA solo se llega desde CONTACTO; rechazar disponible desde CONTACTO.
- Landing: botón "Solicitar crédito" en barra y hero.
- E2E: POST público crea solicitud CONTACTO/WEB → detalle muestra badge+hint → Iniciar gestión pasa a CREADA con botón "Enviar a revisión".

### Detalle de solicitud — expediente collapse + análisis financiero
- **Migración** `2026-09-25-000004`: tabla `solicitud_analisis` (ingresos, cuota, cuota_mes, activos, pasivos, patrimonio, ratio, nivel) + `SolicitudAnalisisModel` con niveles BUENO/AJUSTADO/RIESGO/SIN_DATOS.
- **Expediente del cliente** ahora es `<details>` colapsable (abierto solo si falta algo).
- **Sección split**: card izquierdo "Préstamo" + card derecho **"Análisis financiero"** — si expediente incompleto muestra aviso; si completo pero sin análisis → botón **Calcular** (`POST /credito/solicitudes/{id}/analisis/calcular`, permiso `solicitudes.editar`, bloqueado si falta expediente); si calculado → métricas + badge de nivel + Recalcular / Ver análisis completo.
- **`GET /credito/solicitudes/{id}/analisis`** — página completa: crédito solicitado, capacidad de pago (ratio cuota/ingreso con alerta <30/30–50/>50%), balance patrimonio, y expediente detallado (negocio, ingresos, activos, pasivos, referencias, direcciones, contactos, documentos).
- `metricasAnalisis()` + `seccionesCliente()` privados compartidos entre `ver()`, `analisis()` y `calcularAnalisis()`.
- Seed: expediente completo del cliente de solicitud #1 (Pedro Martinez) — dirección, 3 contactos, 2 referencias, 2 ingresos, 2 documentos, negocio, 2 activos, 2 pasivos.
- E2E: sin análisis → botón Calcular; POST calcular → métricas + nivel guardados; solicitud #4 (expediente incompleto) → análisis bloqueado sin botón.

### Pantalla de aprobación (solicitado vs aprobado)
- **Migración** `2026-09-25-000005`: `solicitudes` + `monto_aprobado`, `tasa_aprobada`, `plazo_aprobado`, `frecuencia_aprobada`, `fecha_primer_pago`.
- **`GET/POST /credito/solicitudes/{id}/aprobar`** (permiso `solicitudes.aprobar`, solo estado REVISION + expediente completo): card "Datos del cliente" (con nivel de capacidad si hay análisis) + split "Préstamo solicitado" (solo lectura) vs "Préstamo aprobado" (editable, pre-llenado con lo solicitado) con **cuota estimada en vivo** (JS francés) y **fecha del primer pago** (sugerida según frecuencia: D/DI +1d, S +7d, Q +15d, M +30d).
- Validación: plazo ≤ `plazo_meses_max` del tenant, fecha primer pago no pasada; guarda ambos datos (solicitado queda en monto/tasa/plazo/frecuencia; aprobado en *_aprobada) y pasa a APROBADA.
- `cambiarEstado` redirige intentos directos a APROBADA a la pantalla dedicada; detalle muestra bloque "Préstamo aprobado" (verde punteado) en la card Préstamo cuando existe.
- Orden del detalle: Estado (12) → Cliente (12) → Expediente collapse (12) → Préstamo+Análisis (6+6) → Cartera (12).
- E2E: REVISION→GET aprobar (cards+fecha) → POST guarda APROBADA con solicitado 60k/aprobado 25k + primer pago → detalle muestra bloque aprobado + Desembolsar.

### Desembolso programado + gestor al aprobar + portal del gestor
- **Migración** `2026-09-25-000006`: `solicitudes.fecha_desembolso` (día pactado de entrega).
- **`GET/POST /credito/solicitudes/{id}/desembolsar`** (permiso `solicitudes.desembolsar`, solo estado APROBADA): cliente (12) + préstamo aprobado (6) + card "Desembolso" (6) con fecha (min hoy) y monto a entregar → pasa a DESEMBOLSO.
- `cambiarEstado` redirige DESEMBOLSO a su pantalla dedicada (igual que APROBADA); en el detalle, "Desembolsar" ahora es link.
- **Gestor al aprobar**: select "Gestor asignado" en el card de préstamo aprobado (empleados activos del tenant, validado server-side) — reasigna `asignado_a`.
- **Portal del gestor**: nueva opción **Desembolso** (`/{slug}/portal/desembolso`) — lista solicitudes en DESEMBOLSO asignadas a él (`asignado_a`), ordenadas por fecha, badge "Hoy"/"Pendiente"; tile en el panel del gestor.
- E2E: aprobar con gestor → desembolsar con fecha → DESEMBOLSO guardado → portal de TI-0001 lista el desembolso con cliente y fecha.
- **"Entregado" → ACTIVO**: botón por item en el desembolso del gestor (`POST /{slug}/portal/desembolso/{id}/entregar`, solo DESEMBOLSO asignados a él) → estado **ACTIVO** ("Crédito activo", badge verde oscuro); label DESEMBOLSO pasa a "Por desembolsar". Guards: ACTIVO cierra cambio de estado y rechazo; el partner no puede saltar a ACTIVO (sin permiso en PERMISO_ESTADO — lo marca el gestor).
- E2E: portal muestra item + Entregado → POST → solicitud #1 en ACTIVO, sale de la lista y del detalle con badge "Crédito activo" sin botón Rechazar.
- **Código de crédito**: `solicitudes.codigo_credito` formato `C-{INI}-{AAAA}-{0000}` (iniciales empresa + año + correlativo anual por tenant, único) generado al Entregar; visible en bloque "Préstamo aprobado" del detalle y en los documentos.
- **"Generar documentos"** (`GET /credito/solicitudes/{id}/documentos`, acción del hero, cualquier estado): página standalone imprimible con **Plan de pago** (cuotas francesas con fechas desde `fecha_primer_pago`, interés/capital/saldo), **Contrato de crédito** (partes, monto, condiciones, garantías, incumplimiento, firmas) y **Relación de garantías** (bienes + referencias + declaración). Marca "Simulación" si aún no hay valores aprobados. Iconos `printer`/`check-circle` agregados.
- **Dashboard**: contadores reales — Clientes, En proceso (CONTACTO→APROBADA), Por desembolsar (DESEMBOLSO), Créditos activos (ACTIVO); Cobrado hoy/En mora en 0 hasta el módulo de pagos.
- E2E: entregar genera C-TI-2026-0001; documentos #1 muestran plan+contrato+garantías, #4 (sin aprobar) marca Simulación; dashboard muestra Clientes=3, En proceso=3, Activos=1.

---

## 2026-09-25 — Portal del gestor: landing, calculadora y solicitud

### Landing pública `/{slug}/portal`
- Landing rediseñada por secciones: barra, **hero con nombre de la empresa**, Quiénes somos, Misión/Visión/Valores, Contacto, CTA final, footer "CONTAMOS - SOFTLUTIONIC - 2026".
- Quitados del hero: subtítulo y lema fijo.
- Brand del nav sin azul de enlace (`text-decoration`/`color` en `.lp-brand`).
- Secciones con texto por defecto cuando el tenant aún no configuró la info institucional.
- Nueva vista `partner/portal/landing.php` + CSS landing (`lp-*`) en `public/css/app.css`.
- Iconos nuevos en `icon_helper.php`: `chevron-left`, `clock`, `target`, `eye`, `heart`.

### Configuración del tenant `/configuracion`
- Nueva sección **"Información institucional"**: `quienes_somos`, `mision`, `vision`, `valores`.
- Migración `2026-09-24-000021_TenantInstitucional` (columnas TEXT en `tenants`).
- `TenantModel` + `ConfiguracionController::guardar()` actualizados.

### Portal del gestor `/{slug}/portal/panel`
- Panel tipo app móvil: saludo, tiles de opciones, "Mi perfil" ahora es un **botón** que abre `/portal/perfil`.
- Nueva vista `perfil.php` (datos + documentos del gestor).
- `.portal-shell` con `gap:18px` — corrige cards pegadas al header en todas las páginas del portal.
- Corrección: tras guardar solicitud redirige al panel (no a la landing).

### Calculadora `/portal/calculadora` (solo gestor, NO en landing)
- Diseño tipo app: card de resultado destacado (cuota grande + total/intereses/N° pagos) + **sliders** monto/tasa/plazo con etiquetas en vivo.
- **Frecuencia de pago**: Diario, Diario intermitente (con slider días/semana), Semanal, Quincenal, Mensual.
- `PortalController::calculadora()` + `calculadora.php` + CSS `calc-*`.

### Nueva Solicitud `/portal/solicitud` → **wizard de 3 pasos**
- **Paso 1 Cliente**: chips "Cliente existente" / "Nuevo cliente" (form inline nombres, apellidos, cédula, teléfono, dirección).
- **Paso 2 Préstamo**: dinámico — sliders monto/tasa/plazo + **cuota en vivo** + frecuencia de pago (+ días/semana si diario intermitente).
- **Paso 3 Resumen**: destino + resumen calculado (cuota estimada × N pagos).
- `guardarSolicitud`: crea `persona`+`cliente` (código auto) si es nuevo; guarda `frecuencia`, `tasa_mensual`, `dias_semana`.
- Migración `2026-09-24-000022_SolicitudPagoFields`.
- CSS del wizard (`wiz-*`, `sol-*`). Bug corregido: `lblDias`→`diasLbl` (cuota se quedaba en 0.00).

### Archivos clave
- `app/Controllers/Partner/PortalController.php` — index (landing), login, panel, perfil, calculadora, solicitud, guardarSolicitud.
- `app/Config/Routes.php` — rutas `/portal`, `/portal/login`, `/portal/panel`, `/portal/perfil`, `/portal/calculadora`, `/portal/solicitud`.
- `app/Views/partner/portal/{landing,panel,perfil,calculadora,solicitud,seccion}.php`
- `public/css/app.css` — secciones `lp-*`, `app-*`, `calc-*`, `wiz-*`, `sol-*`.

---

## Pendiente próxima sesión (prioridad)
- [ ] **Probar el guardado** de la solicitud (wizard → BD, cliente existente y nuevo).
- [ ] Trabajar el **proceso de solicitud** (aprobación, estados, plan de pago).
