# Bitácora de cambios — Contamos (CFSI)

Registro de cambios por sesión. Más reciente arriba.

---

## 2026-10-04 — Ruta de desembolsos en panel tenant

- **`GET /credito/desembolsar`** — nueva opción «Desembolsar» en el menú Crédito (permiso `solicitudes.desembolsar`, icono map-pin). Lista todas las solicitudes en estado DESEMBOLSO del tenant (todos los gestores) con monto aprobado, fecha de entrega, gestor asignado y mejor dirección del cliente (GPS preferido — mismo criterio que `rutaCobrosHoy`/`desembolsosPendientes` del portal). `SolicitudService::desembolsosRuta` devuelve paradas + total + conteo sin GPS + gestores presentes.
- **Mapa con ruta reorganizable** — botón «Ver mapa» despliega MapLibre (mismo estilo OpenFreeMap que ruta de cobro y ficha de cliente); markers numerados por posición en la ruta; «Mi ubicación» (geolocation) y «Orden sugerido» (vecino más cercano desde el GPS, sin GPS al final); cada parada se mueve con flechas ▲▼ y la ruta se recalcula. Línea por calles vía OSRM con fallback a línea recta; distancias de lista = haversine desde la parada anterior. Links Waze / Google Maps / WhatsApp por parada; click en el nombre abre el detalle de la solicitud.
- **Filtros** — modal «Elegir entregas» (checkboxes por solicitud) + select por gestor cuando hay más de uno; ambos solo filtran la vista, no tocan datos.
- **Archivos**: `SolicitudService::desembolsosRuta`, `SolicitudController::desembolsos`, ruta en `Routes.php`, ítem en `Menu.php`, vista `partner/solicitudes/desembolsos.php`, `public/js/desembolsos.js`, bloque `.ruta-*`/`.map-*`/`.ruta-mv` en `app.css` (reuso del patrón `portal/mapa.js` con reorden manual agregado).

## 2026-10-01 (noche) — Fix paginador + entrada app en 2 pasos + /alta con calculadora

- **Fix crítico `partials/pager.php`** — usaba métodos inexistentes en `PagerRenderer` (`getCurrentPage()`, `getPreviousPage()`, `getNextPage()` como URL). Corregido a `getCurrent()` / `getPrevious()` / `getNext()`; `getTotal()` tras `method_exists`. Era el 500 de `/portal/actividad` (solo con >1 página) — afectaba toda lista paginada.
- **Entrada app (Setup.jsx)** — paso 1: menú con 2 tarjetas («Ingresar a tu empresa» / «Darte de alta»); paso 2: solo slug (URL del host oculta) + «Siguiente» → Login. Placeholder del carnet `#######`. CSS `.setup-choice` en `theme.css`. App bump a **1.1.1 (code 3)** — incluye también «Editar solicitud» desde ficha del cliente (`Cliente.jsx` → `onNav('solicitud', s)`).
- **`GET /alta`** — página pública de registro (`landing/alta.php` + `public/css/alta.css` + `public/js/alta.js`): calculadora de plan a medida con 4 sliders (usuarios, clientes, créditos, empleados) cuya **base es el plan Básico real** de `planes` (`precio_mensual`, `max_usuarios`, `max_creditos_activos`, `max_empleados`, inyectados vía `window.ALTA_BASE`); precio estimado en vivo + formulario nombre/WhatsApp.
- **`POST /alta`** (`LandingController::altaStore` → `LandingService::registrarAlta`) — valida sliders, **recalcula el precio server-side**, guarda el lead en `acceso_solicitudes` con `codigo` (PTR-######), `plan_estimado` y `detalle` JSON; muestra el código en pantalla.
- **`acceso_solicitudes`** + 3 columnas (`codigo`, `plan_estimado`, `detalle`) — migración `000018` + SQL `writable/migraciones_sql/2026-10-01_acceso_solicitudes_plan.sql` (prefijo `CT_`).
- **Leads en admin** — `/admin/leads` (`LeadsController` + `admin/leads/index.php` + ítem menú): lista con código partner, desglose del plan, botón **WhatsApp** (`wa.me` + mensaje prellenado con el código), **Recalcular** (re-calcula `plan_estimado` contra el Básico actual) y toggle PENDIENTE ⇄ CONTACTADO.
- **Esquema de sobreconsumo** — `plan_helper` ahora tiene las 4 tarifas (`PLAN_USD_EXTRA_USUARIO` 3.00 · `EMPLEADO` 1.00 · `CREDITO` 0.15 · `CLIENTE` 0.20 + `PLAN_BASE_CLIENTES` 20) y `plan_cobro_mes()` devuelve `recursos[]` con uso/incluido/extra/cargo por concepto (llaves `extra`/`monto_extra` siguen = usuarios, compat con `UsuarioController`/`TenantService`). `plan_uso` suma `clientes` y cuenta créditos ACTIVO+DESEMBOLSO; `LandingService::precioEstimado` delega en esas constantes.
- **"Mi plan y consumo"** — card en `/configuracion` del tenant: plan, próximo corte, tabla uso/incluido/extra/cargo por concepto y estimado del ciclo (CSS `.consumo-*` en `app.css`).
- **Plan Básico → $19/mes, 1 usuario incluido** — SQL `2026-10-01_plan_basico_19.sql` aplicado local y server (empleados 5 y créditos 50 sin cambio); la calculadora de `/alta` lo toma sola del plan.
- **App `Setup.jsx` paso empresa simplificado** — solo identificador + «Siguiente» + link «dirección personalizada»; se quitaron pills QR/código, el modal de vinculación y el texto de soporte (y los imports/estados muertos). Requiere rebuild de APK para llegar a teléfonos.
- **Generador de slug en alta de tenant** — `TenantService::sugerirSlug` (slug del nombre, `-2`/`-3` si está ocupado) + `GET /admin/tenants/slug-sugerir` (JSON: slug libre u `ocupado`); `nuevo.php` suma botón **Generar** y hint de disponibilidad en vivo vía `public/js/admin-tenant.js`. El `guardar` ya rechazaba duplicados — esto los evita antes de enviar.
- **Fix `/finanzas/gastos` 500** — `GastoModel::totalesPorCategoria` no seleccionaba `gastos.categoria_id` y la vista lo usa para el link del chip (`Undefined array key "categoria_id"`). Solo explotaba con gastos guardados (por_cat vacío = foreach no corre). Una línea en el select.
- **Fix topbar del admin** — `admin.css` no tenía estilos para `.topbar-menu` ni `.sidebar-scrim`: el hamburguesa salía siempre visible y sin formato en desktop, y el scrim no oscurecía al abrir el drawer en móvil. Se copiaron las reglas de `app.css` (oculto en desktop, botón redondo + overlay en ≤768px, `.user` oculto en móvil).
- **Candado de identidad del cliente** — `ClienteModel::tieneCreditoActivo` (solicitud en DESEMBOLSO o ACTIVO). Con crédito vigente, `ClienteService::actualizar` y `PortalService::actualizarDatosCliente` **ignoran** nombres/apellidos/cédula recibidos y conservan los valores actuales (el resto del form sí guarda); devuelven `{ok, bloqueado}` y los controllers muestran warning. `fichaCliente` expone `bloqueado` → readonly en `clientes/form.php`, `portal/cliente.php` y `disabled` + aviso en `sections/Cliente.jsx` (la API también devuelve el flag en `datosCliente`).
- **Chequeo de actualización en el arranque** — `checkUpdate()` se movió del `useEffect` de `home` al boot de `App.jsx` (durante el splash): el `IonAlert` "Actualización disponible" aparece al abrir el APK sobre cualquier pantalla (splash/setup/login/home). `version.json` = code 3 y APK = code 3 — al publicar el APK nuevo hay que subir `versionCode`/`versionName` en gradle + `version.json` para que el aviso salte en teléfonos con la 1.1.1.
- **APK 1.2.0 (code 4)** — bump en `build.gradle` (code 4 / name 1.2.0), `package.json` y `version.json` (code 4, mensaje "Entrada simplificada + candado de identidad…"). `npm run build` + `cap sync` + `assembleDebug` → `public/app/contamos-gestor-1.2.0.apk` (14.4 MB). Teléfonos con 1.1.1 ven el alert al abrir gracias al chequeo en el splash.
- **Plan admin por fases** (`docs/PLAN_ADMIN.md`) — pipeline comercial: lead → contacto → contrato (día pago 5/10 + gracia) → cobro bancario → suspensión automática → exporte de datos. Contrato como vista imprimible (sin dompdf) y exporte en SpreadsheetML (sin PhpSpreadsheet).
- **Fase 1 — UI admin** — `tenants/nuevo` rediseñado: form en columnas `.form-cols` (Empresa | Plan y crédito) + card de admin, `.input-btn` para el slug y `.form-actions`. `leads`: **chips por estado** con conteos (`Todos · Pendientes · Contactados · Contratados · Rechazados`) + filtro `?estado=` en `LeadsController::index`; `AccesoSolicitudModel::ESTADOS` (PENDIENTE/CONTACTADO/RECHAZADO/CONTRATADO) y tags de color por estado. Nuevo CSS: `.form-cols`, `.input-btn`, `.form-actions`, `.chip`/`.chip-bar`.
- **Fase 2 — modal Contactar** — botón Contactar/Ajustar en el lead abre modal (`admin-leads.js` + patrón `.moverlay` del admin): 4 sliders de consumo precargados del `detalle` + **select de plan** (`LandingService::planes`) con estimado en vivo (tarifas espejo de `plan_helper`). `POST /admin/leads/{id}/contactar` recalcula server-side con el plan elegido (`precioEstimado` ahora acepta `plan_id`, default Básico) → guarda `plan_estimado` + `plan_id` + `detalle` y marca **CONTACTADO**. Migración `000019` → `acceso_solicitudes.plan_id` (SQL `2026-10-01_lead_plan_id.sql`). Reemplaza al "Recalcular" inline; `estado()` ya no reabre CONTRATADO/RECHAZADO.
- **Fase 3 — contrato de servicio** — tabla `contratos` (migración `000020` + SQL `2026-10-01_contratos.sql`): lead_id, tenant_id NULL (se llena al provisionar), plan_id, monto_mensual, **dia_pago (5|10)**, **gracia_dias**, **cuenta_bancaria**, terminos, estado, fecha_inicio/corte. `ContratoService::crear` (solo lead CONTACTADO, un contrato por lead) → `POST /admin/leads/{id}/contrato` marca **CONTRATADO** y redirige a `GET /admin/contratos/{id}` — vista imprimible standalone (`admin/contratos/ver.php` + `admin-contrato.css`/`js`) con 8 cláusulas: partes, objeto+precio, vigencia, **pago día 5/10 a cuenta bancaria**, **gracia**, **suspensión automática** (cierra portal tenant + app gestor), reactivación + exporte de datos tras saldar, terminación, y firmas. `POST /admin/leads/{id}/rechazar` → RECHAZADO (botón ✕ con confirm). Fila CONTRATADO muestra «Ver contrato».
- **Fase 4 — cobranza y suspensión automática** — comando `spark cobros:generar` (`App\Commands\CobrosGenerar`, diario por cron): genera el cargo PENDIENTE del período por tenant con plan pago (idempotente por `plan_pagos(tenant_id,periodo)` UNIQUE — migración `000021` + SQL) y marca `suscripcion_estado=SUSPENDIDA` a quien tiene un cargo vencido (`dia_pago + gracia_dias`). `tenants.gracia_dias` (nueva col, default 4) se suma al corte en `plan_al_dia` — el bloqueo ya lo aplican `SuscripcionFilter` (panel partner) y `ConnectController` (portal/app gestor, "Servicio suspendido"). Ficha del tenant suma card **Suscripción SaaS**: día de pago (5|10) + gracia editables (`POST .../condiciones`) y botón Suspender/Reactivar plan (`POST .../suscripcion` → `TenantService::toggleSuscripcion`).
- **Fase 5 — exporte de datos del suspendido** — card «Exporte de datos» en la ficha solo si `suscripcion_estado=SUSPENDIDA`: muestra el saldo pendiente (`TenantService::saldoPendiente` = plan_pagos PENDIENTE) y habilita `GET /admin/tenants/{id}/exporte` solo cuando quedó saldado. El endpoint devuelve **Excel SpreadsheetML** (`App\Libraries\SpreadsheetMl` — XML nativo, sin composer) con 4 hojas: Clientes, Créditos, Pagos y abonos, Suscripción (`exporteDatos`). Registra `EXPORTE_DATOS` en `audit_logs` por el admin que lo descarga.
- **Plazo decimal (meses)** — `solicitudes.plazo_meses`/`plazo_aprobado` pasan de INT a `DECIMAL(5,1)` (migración `000022` + SQL): un plazo de **2.5 meses semanal genera 10 cuotas** (`n = round(2.5 × 4)`), antes se truncaba a 2 meses/8 cuotas. Casts `(int)`→`(float)` en `SolicitudService` (crear, actualizar, aprobar, planPagos, métricas), `PortalService` (crear/editar gestor), `ConnectController` (API app) y `PagoService` (reestructura/refinanciamiento, mín. 0.5). Validación `integer`→`numeric` en `SolicitudController` y `CreditoController`. Inputs `step="0.5" min="0.5"` en nueva/editar/aprobar, portal solicitud+editar+calculadora, reestructura/refinanciamiento (`creditos/ver`) y herramienta calculadora. `parseInt`→`parseFloat` en `solicitud-editar.js`, `portal/solicitud.js`, `portal/calculadora.js` e inline de `nueva`/`aprobar`/`calculadora`. Displays con `(float)` para no mostrar `12.0` (ver, desembolsar, documentos, análisis).
- **Fix cuota FLAT** — `SolicitudService::planPagos` devolvía la cuota **francesa** en el resumen `'cuota'` para tipo FLAT (aunque las filas del plan sí eran flat): documentos, contrato, simulador de la app y desembolso mostraban un monto distinto al real. Ahora usa la cuota de la primera fila real (flat = capital+interés fijo) — igual criterio que Alemán. El estimado de `solicitudes/ver.php` y la cuota del ratio en `metricasAnalisis` también respetan `tipo_calculo` (antes siempre francesa).
- **Fix crash valoración** — `POST /valoracion` daba 500: `ct_valoraciones` en el server tenía índice **UNIQUE(tenant_id,user_id)** creado a mano, y la segunda calificación del mismo usuario violaba la clave. `ALTER TABLE` lo reemplaza por índice normal `idx_val_usuario` (como la migración). `ValoracionService::guardar` ahora captura fallos de BD → error controlado en vez de pantalla de excepción.
- **Tablas faltantes en server** — `ct_reporte_categorias`/`ct_reporte_asignaciones` nunca se crearon (migración `000013` sin .sql) → `/reportes` tiraba 500. SQL generado y aplicado (`2026-10-02_valoraciones_idx_y_reporte_categorias.sql`).
- **Bloqueo fuera de horario — portal gestor + app** — `HorarioFilter` solo cubría el panel partner (rol `gestor` de `users`); el gestor del portal/app podía seguir operando con sesión/token abierto fuera de `tenants.hora_inicio–hora_fin`. Nuevo helper `en_horario($tenant)` en `plan_helper` (soporta rangos que cruzan medianoche). **Portal**: `PortalController::ctx()` niega todas las secciones fuera de horario → `login()`/`entrar()` reenvían vía `destinoGestor()` (suspendida → horario → panel) a la nueva página `GET /{slug}/portal/horario` (`portal/horario.php` + `.blk-*` en `portal.css`, con Reintentar y Cerrar sesión). **App**: `HorarioConnectFilter` (alias `horarioApp`, patterns `*/connect`, `*/connect/*` — OPTIONS pasa) devuelve `{ok:false, code:'FUERA_HORARIO', hora_inicio, hora_fin}` en cualquier endpoint incluido handshake/login; `api.js::fhCheck` lo intercepta en `api()`/`handshake()`, guarda `cf.fh` y recarga → `App.jsx` arranca en pantalla `bloqueado` → `pages/FueraHorario.jsx` (estilo Family Link: reloj, rango, Reintentar = recarga y entra si ya es horario, Cerrar sesión). APK republicado sobre `contamos-gestor-1.2.0.apk`.
- **Deploy pendiente**: correr el SQL en el server + subir `Routes.php`, `LandingController`, `LandingService`, `AccesoSolicitudModel`, `landing/alta.php`, `css/alta.css`, `js/alta.js`, `partials/pager.php`, migración `000018`, los archivos admin de la Fase 1, y **toda `public/app/`** (APK 1.2.0 + `version.json` actualizado).

## 2026-10-01 (PM) — App móvil descargable + versionado + aviso de actualización

- **`GET /descargar`** — página pública de descarga (`LandingController::descargar` + `landing/app.php`): hero con la versión vigente (de `version.json`), botón descarga de la más reciente y lista de **versiones anteriores** — escanea `public/app/contamos-gestor-X.Y.Z.apk` ordenadas por versión. La sección "App móvil" del landing enlaza a `/descargar` (nav agregado también). NOTA: la ruta no es `/app` porque la carpeta física `public/app/` le gana en el rewrite del server (`{request} → public/{request}`) y terminaba en `/public/app/` con 403.
- **APK servida por PHP** — `GET /descargar/apk[/{version}]` → `download()` response con `contamos-gestor-X.Y.Z.apk`; sin versión sirve la más nueva. Así el APK no depende del MIME `.apk` de IIS (que devolvía 404.3/500). `public/app/` lleva solo `web.config` mínimo (default document) + `index.html` de redirect a `/descargar`.
- **APK versionada** — `android/app/build.gradle` `versionCode 2` / `versionName "1.1.0"` (+ `package.json` 1.1.0). `npm run build` + `cap sync` + `gradlew assembleDebug` → renombrado a `public/app/contamos-gestor-1.1.0.apk` (14.4 MB, **debug** — firmado con debug.keystore; para distribución formal conviene release con keystore propio).
- **Chequeo de actualización** — la app consulta `public/app/version.json` del server al abrir con sesión (`api.js::checkUpdate`: compara `versionCode` contra `App.getInfo().build`); si hay versión nueva, `IonAlert` "Actualización disponible" con Descargar → abre `descargar/apk` (URL relativa resuelta contra el host) → el navegador baja e instala el APK. Tolera red caída o manifest ausente (silencioso).
- **Rutina de release**: bump `versionCode`/`versionName` en gradle → editar `public/app/version.json` (code, name, mensaje) → build+sync+assembleDebug → copiar APK como `contamos-gestor-{versionName}.apk` a `public/app/` → subir `public/app/` completa.

## 2026-10-01 — Historial de solicitud + observaciones de revisión + logs de errores

- **`solicitud_historial`** (nueva tabla; migración `000017` + SQL `writable/migraciones_sql/2026-10-01_historial_errorlog.sql`): timeline por solicitud — acción (`CREADO|ESTADO|EDITADO`), estado destino, nota y actor (`user_id`/`empleado_id` + nombre desnormalizado). `SolicitudHistorialModel::registrar`/`deSolicitud`; nunca rompe el flujo si la tabla falta.
- **Eventos registrados en**: `crearOficina`, `crearSolicitudGestor`, `crearLeadWeb` (actor "Web"), `moverEstado` (todas las transiciones), `guardarAprobacion`, `guardarDesembolso`, `entregarDesembolso`, `actualizar` (oficina), `actualizarSolicitudGestor` (portal/app) y `PagoService::refinanciar`. Firmas nuevas: `moverEstado(array $sol, string $nuevo, ?string $nota)` y `guardarDesembolso(array $sol, string $fecha)` — callers en `SolicitudController` ajustados.
- **`solicitudes.nota_revision`**: al enviar a REVISION la modal pide observaciones (`modal-revision` en `ver.php`) → queda en la columna para que el gestor la vea y en el historial para siempre; se limpia al salir de REVISION.
- **Tenant `solicitudes/ver.php`**: botón «Historial» abre `modal-historial` con el timeline (quién creó/movió/aprobó, cuándo). Banner con la observación cuando está en REVISION. CSS `.tl-*` en `app.css`.
- **Portal gestor**: `actividad` muestra la nota de revisión, botón «Editar» en CREADA/REVISION (→ `portal/solicitud/{id}/editar`, vista `solicitud_editar.php`, solo términos — cliente fijo) y feed «Historial de movimientos» (`PortalService::historialGestor`). `actualizarSolicitudGestor` valida cartera + estado editable + monto/límite/plazo/tasa (clamp al tope del tenant).
- **`error_log`** (nueva tabla): helper global `log_error($origen, $e)` en `Common.php`; `Config\Exceptions::handler` registra todo Throwable no-404 antes de delegar al handler default → el usuario nunca ve el error crudo. Admin: `/admin/auditoria/errores` (visor con filtros + traza plegable) y `POST /admin/auditoria/errores/purgar` (borra logs >X días); menú sistema ahora es grupo Auditoría → Bitácora / Logs de errores.
- **App IONIC**: `api.js` `verSolicitud`/`editarSolicitud`; `Actividad.jsx` muestra «Oficina: …» y botón Editar (items llevan `editable` del endpoint); `Solicitud.jsx` acepta `param` = solicitud → modo edición sin selector de cliente, banner con la observación y POST a `connect/solicitud/{id}`. CSS `.lc-wrap/.lc-edit/.lc-nota/.sol-nota-banner` en `theme.css`.
- **Iconos nuevos** en `icon_helper`: `message-square`, `alert-triangle`, `save`, `trash-2`.
- **Deploy**: BD remota sincronizada vía `sync-bd.ps1` (incluyó `persona_egresos`, que estaba pendiente) + `php spark migrate` local OK. Falta `.\deploy.ps1` (archivos) y `npm run build` + `cap sync` de la app IONIC.

## 2026-09-29 — App IONIC: expediente completo del cliente + análisis financiero + egresos

- **Nuevo tipo `egreso`** (`persona_egresos`: descripcion + monto/mes) en `PersonaDetalleModel::TIPOS` — SQL `writable/migraciones_sql/2026-09-29_persona_egresos.sql` (prefijo `CT_`, FK cascade a `personas`). Tab Egresos agregada al portal gestor `portal/cliente.php` y al expediente de `solicitudes/analisis.php`.
- **`SolicitudService::metricasAnalisis`** ahora resta egresos: `neto = ingresos - egresos` y el **ratio usa el ingreso neto** (cuota/mes ÷ neto). `analisis.php` y `aprobar.php` muestran Egresos + Ingreso neto.
- **API `/connect`** (acotada a la cartera del gestor vía `fichaApp`): `POST cliente/{id}/datos` (persona básica), `POST cliente/{id}/dato/{tipo}` y `.../{item}/eliminar` (reusan `PortalService::agregarDato`/`eliminarDato`), `GET/POST cliente/{id}/analisis` (métricas + checklist + nivel; POST persiste vía `calcularAnalisis` sobre la última solicitud).
- **`Cliente.jsx` reescrito**: pestañas deslizables (Datos | Direcciones | Contactos | Referencias | Negocios | Activos | Pasivos | Ingresos | Egresos | Documentos | Solicitudes), cada sección lista sus items con botón eliminar y «Agregar» abre un bottom-sheet con los campos del tipo (selects, números, días de venta en chips L-D). Direcciones con botón **«Usar mi GPS»** (navigator.geolocation, permisos `ACCESS_*_LOCATION` agregados al manifest). Datos básicos editables inline (nombres…dirección). Documentos conserva subir/borrar.
- **Botón «Análisis financiero»** en la ficha: bottom-sheet con nivel (badge de color según NIVELES), métricas (ingresos/egresos/neto, cuota/mes, % comprometido, activos/pasivos/patrimonio), checklist del expediente y «Calcular/Recalcular».
- **CSS `theme.css`**: `.exp-tabs` (chips scroll horizontal), `.exp-item/.exp-del`, `.ficha-acts`, `.ana-nivel` (ok/warn/bad/off), `.chk-item` y `.exp-full/.exp-gps`. Build Vite OK + `cap sync android`.
- **Home (stats slider)**: las 4 stats del día pasan a tarjetas grandes deslizables `.hstat` (scroll-snap, 74% de ancho para que asome la siguiente): icono en chip de color por acento (ok/warn/info/vio), número 26px/800, label y sub-cta con chevron. Las clases `.stats/.stat` se conservan para Cobros y Arqueo.
- **Ficha de cliente más profesional**: `.ficha-hero` pasa a card con degradado primary (avatar circular con anillo, código+cédula en una línea, badge `.ficha-est` del estado, botón de llamada translúcido) — también beneficia `Perfil.jsx`. Datos básicos dentro de `.exp-card` blanca.
- **Fix producción "Cliente no encontrado"**: si el server recibió el código nuevo sin `CT_persona_egresos`, `fichaCliente`/`seccionesCliente`/`seccionesDe` explotaban con 500 (JSON de error CI4 sin `ok`/`error` → la app mostraba el fallback genérico). Los 3 loops de secciones ahora son tolerantes: tabla hija faltante → sección vacía, no 500. La app muestra `d.message` (error real de CI4) + botón Reintentar. Requiere `sync-bd.ps1` para que Egresos funcione en serio.
- **Anti doble-submit global**: `app.js` (panel oficina) y `portal.js` (PWA gestor) bloquean todo `<form>` tras el primer submit — flag `dataset.enviado`, respeta validaciones (`defaultPrevented`), opt-out `data-no-lock`, clase `.enviando` en botones (sin `disabled` para no excluir `name=value` del submitter). En la app: `Cliente.jsx` agrega guard `borrando` en eliminar items/documentos (los crear/guardar ya tenían `guardando`/`subiendo`/`saving`/`busy`).
- **Dedup server-side** (defensa real contra doble-submit/retry): `SolicitudService::crearOficina` y `PortalService::crearSolicitudGestor` — mismo cliente+gestor+monto en <5 min devuelve la solicitud ya creada (`duplicada:true`); `PagoService::registrarPago` — mismo abono (solicitud+monto+tipo+quién cobró) en <2 min devuelve el `pago_id` existente; `PersonaService::agregarDato` y `PortalService::agregarDato` — fila idéntica (`PersonaDetalleModel::existeIgual`, ignora `archivo`/id/fecha) no inserta y borra el archivo huérfano; `ClienteService::registrar` — persona existente por cédula/teléfono reutiliza su ficha en vez de duplicarla.
- **Listado solicitudes (tenant)**: sin param `estado` arranca filtrado en **CREADA** (`?estado=` vacío = Todos); chips reordenados por el pipeline Creada → Revisión → Aprobada → Desembolso → Activo → Liquidado → Rechazada, con **Por contactar** y **Todos** al final. `chipUrl` siempre manda `estado` (si se filtraba fuera, el chip Todos quedaba inalcanzable).

## 2026-09-28 (PM1) — Entrega de desembolso en 3 mundos + fix plan semanal + buscador

- **Tenant**: `POST /credito/solicitudes/{id}/entregar` (`auth:solicitudes.desembolsar`) + botón «Marcar entregado» en `solicitudes/ver.php` cuando la solicitud está en `DESEMBOLSO`. Reutiliza `PortalService::entregarDesembolso($tenantId, null, $id)` — `empleadoId` ahora nullable: `null` = oficina (sin chequeo de cartera ni flag).
- **Flag por gestor** `empleados.puede_desembolsar` (migración 000016, default 1): checkbox «Puede entregar desembolsos» en `empleados/form.php`, badge en `empleados/ver.php`, persistido en `EmpleadoService::registrar/actualizar`. SQL remoto aplicado vía `sync-bd.ps1` (prefijo `CT_`).
- **Portal gestor**: `seccion.php` (desembolso) muestra «Entrega en oficina» si el flag está apagado; el service rechaza el POST con `empleadoId` sin permiso.
- **API `/connect`**: `login` y `desembolsos` devuelven `puede_entregar`; el POST sigue validando server-side.
- **App IONIC**: `Ruta.jsx` — fix crash (`cargar` no definida, ReferenceError al abrir); `Desembolsos.jsx` lee `puede_entregar` del API y sustituye el botón por «Entrega en oficina».
- **Fix `ver.php`**: cuota estimada usaba `S=4.33` y `DI=4.33×días` → semanal a 3 meses mostraba 13 pagos. Ahora `S=4`, `DI=4×días` — igual que `planPagos`/`metricasAnalisis`/ambos JS.
- **Buscador de cliente**: `buscador-select.js` convierte `<select>` en input + paleta filtrable (normaliza tildes, dispara `change` — el resto del JS intacto). Aplicado a `cliente_id`/`asignado_a` en `solicitudes/nueva.php` y `asignado_a` en `editar.php` (el cliente no se edita — va fijo). CSS `.busq-*` en `app.css`.
- **Ficha de cliente** (`socios/clientes/{id}`): direcciones con links **Waze + Google Maps** (por GPS `lat/lng` o búsqueda por texto — mismo patrón del portal) y contactos con **Llamar / WhatsApp** (`tel:`/`wa.me/505…`) o **Correo** (`mailto:`) según el tipo. Click en link no abre el modal de edición (handler ya excluye `<a>`). CSS `.oui-acts` en `app.css`.
- **App IONIC (margen superior)**: Android edge-to-edge dejaba el header debajo de la barra de estado → `ion-toolbar { padding-top: env(safe-area-inset-top) }`. Stats de Home pasan a 2×2 en pantallas ≤380px (montos `C$` largos no cabían en 4 columnas).
- **App IONIC (notificaciones)**: items del panel navegan a la ficha del cliente (`cliente_id` del aviso) con chevron + badge de estado.
- **App IONIC (expediente)**: `POST /connect/cliente/{id}/documento` (multipart, valida cartera vía `fichaCliente`, reusa `PersonaService::agregarDato` → `uploads/documentos/`, tope 5 MB). `fichaCliente` agrega `archivo_url` absoluta. `Cliente.jsx`: sección Documentos — lista con «Ver archivo» + form tipo/descripción/foto-PDF (`doc-up` en `theme.css`).
- **Listado solicitudes** (`/credito/solicitudes`): chips de estado deslizables (scroll horizontal en móvil, sin scrollbar) que filtran por URL conservando q/gestor/ruta/fechas — el `<select name="estado">` pasa a input hidden dentro del form. `filtrar()` suma `telefono`/`direccion` + última GPS de `persona_direcciones` (`geo_lat`/`geo_lng`). `partials/list.php` soporta `acciones` — la fila pasa a `<div>` con links internos (no `<a>` anidados): WhatsApp (`wa.me/505…`), Waze y Maps (por GPS o texto de dirección); en móvil (≤640px) solo iconos (`row-act-txt` oculto).
- **Nav + responsive global**: el menú marca el item activo según `uri_string()` (y el grupo padre con `.grupo-activo`); sidebar-drawer ahora hasta ≤1024px (antes 768). Móvil ≤768px: topbar compacto (título truncado, buscador solo icono, sin nombre de usuario), tabs del expediente deslizables, modales tipo bottom-sheet de una columna, mapa 220px, `persona-card` con meta abajo y `detail-grid` 2 col (1 col ≤640px). Acciones de fila pasan a su propia línea en ≤640px.
- **Topbar móvil One UI**: `h2` absoluto centrado (sin el sufijo «— Contamos»), hamburguesa/lupa/campana circulares de 40px, usuario solo en el drawer. La lupa abre la paleta Ctrl+K.
- **Solicitudes como tarjetas** (`.sol-card` en `index.php`, ya no `partials/list`): blancas, radius 18px, gap 12px sobre fondo gris. Jerarquía: `#id · Nombre Capitalizado` (700) + monto derecha 16.5px/800; fila media con código `C-TI-*` + badge píldora 11px + iconos WhatsApp/Waze/Maps; meta gris 12px (gestor · frecuencia · fecha · ruta). Link estirado `.sol-card-link` cubre la tarjeta; `.sol-card-acts` con `z-index:2` queda clickeable.
- **App IONIC (logo)**: la imagen del tenant no cargaba — `Home.jsx` y `Login.jsx` ahora muestran siempre la inicial de la empresa (`nombre.charAt(0)`).
- **Fix producción** `/credito/solicitudes`: subqueries de `filtrar()` (geo_lat/geo_lng/contacto_tel) eran SQL crudo sin prefijo → `Unknown table` en el server (`CT_*`). Ahora usan `$this->db->prefixTable()` (mismo patrón de `PagoService`). Geo = **primera** dirección del cliente; teléfono = `personas.telefono` o primer contacto teléfono/whatsapp/celular (`LOWER(tipo)`).
- **Filtros colapsables**: el toggle es ahora un **header de panel** full-width (título «Filtros» + badge del conteo activo + chevron); el panel arranca cerrado y se abre solo si hay filtros aplicados. Handler genérico `[data-collapse]` en `app.js` + CSS `.sol-filter-toggle`/`.sol-filter-count`.
- **Portal gestor — cartera**: buscador client-side arriba de la lista (nombre/código/cédula, sin tildes, con estado vacío). Handler genérico `[data-buscar]` + `[data-empty]` en `portal.js` — reutilizable en otras listas del portal. CSS `.cli-buscar`/`.cli-buscar-vacio` en `portal.css`.
- **App IONIC (margen real)**: el safe-area se aplicaba a TODAS las toolbars — las de los modales bottom-sheet quedaban con un hueco muerto arriba. Ahora `.sec-content { --padding-top: 12px }` da el respiro bajo el header y `.modal-sheet ion-toolbar` quita el padding extra.
- **App IONIC (cobro One UI)**: el modal de abonar pasa a bottom sheet `.78` (radius 28px, handle bar gris, ampliable a `.95`). Sin doble toolbar — header custom: «Registrar cobro» + cliente capitalizado · contrato, X circular. Monto como display financiero (`C$` gris + input 36px/800), chips rápidos (Cuota / ×2 cuotas / Liquidar saldo), método en píldora segmentada (activo blanco+verde sobre #F1F5F9), nota opcional y botón **sticky** 52px radius-50px «Registrar cobro C$ x» sobre la nav-bar del teléfono.

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
