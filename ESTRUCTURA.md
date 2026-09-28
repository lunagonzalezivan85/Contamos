# Estructura del proyecto CFSI

> **Índice de consulta rápida** — buscar archivos aquí primero en vez de escanear el proyecto.
> Actualizar este archivo cada vez que se cree o elimine un archivo.

Stack: CodeIgniter 4 (PHP 8) + MariaDB/XAMPP + CSS propio en `public/css`.
URL base: `http://localhost/cfsi/`. Despliegue partner con tenants multi-empresa.

## Convenciones

- **Controladores** `app/Controllers/{Admin,Partner,Shared}/` — `Partner` = panel del tenant.
- **Vistas** `app/Views/{admin,partner,shared,landing}/` + `layouts/` (admin|partner|portal).
- **Assets** `public/css|js` — incluir con `v_asset('css/x.css')` (helper `asset`, ?v=filemtime).
- **Helpers** `app/Helpers/` — cargados en `BaseController::$helpers` (icon, menu, plantillas, asset).
- **Migraciones** `app/Database/Migrations/` — `php spark migrate`. Seeds: `SecuritySeeder`.

## Módulos y archivos

### Núcleo / seguridad
- `Controllers/Shared/AuthController.php` — login/logout, `?debugbar`
- `Controllers/Shared/LandingController.php` — `GET /` landing pública + `POST /solicitar-acceso` (form "solicita tu usuario" → tabla `acceso_solicitudes`)
- `Controllers/Shared/PerfilController.php` — perfil usuario, cambiar password
- `Controllers/Shared/NotificacionController.php` — campanita de notificaciones
- `Services/Shared/AuthService.php`, `Services/Shared/MenuService.php`, `NotificacionService.php`, `LandingService.php`
- `Filters/AuthFilter.php`, `Filters/AdminFilter.php`, `Filters/HorarioFilter.php`
- `Models/UserModel`, `RoleModel`, `TenantModel`, `NotificationModel`, `PlantillaModel`

### Services Partner (`app/Services/Partner/`)
- `PortalService.php` — portal gestor: auth carnet+PIN, ctxGestor, manifest PWA, cartera/actividad/desembolso, leads web, ficha cliente, direcciones
- `SolicitudService.php` — solicitudes: listar/filtros, detalle, validarTransicion, aprobación, desembolso, plan de pago francés, métricas/análisis, checklist expediente
- `PersonaService.php` — persona + expediente por pestañas (8 tablas persona_*) + upload de documentos (compartido por cliente/empleado)
- `EmpleadoService.php`, `ClienteService.php` — listar/registrar/actualizar/ficha; delegan expediente en PersonaService
- `DashboardService.php`, `ConfiguracionService.php`
- `PagoService.php` — cuotas del plan persistidas + pagos REVISION→APLICADO/RECHAZADO (aplica a cuotas FIFO)
- `ArqueoService.php` — cierre de caja del gestor: cobros del día + desembolsos entregados → esperado vs contado
- `GastoService.php` — control de gastos: egresos por categoría (sin detalle), métricas, seed de categorías por tenant, anular no borra
- `IngresoService.php` — otros ingresos (VENTA|DONACION|OTRO): VENTA lleva `iva_pct` y genera recibo imprimible con desglose subtotal/IVA/total (`Views/partner/finanzas/recibo_ingreso.php`), anular no borra
- `MetaService.php` — plan de metas por gestor: catálogo `meta_metricas` (AUTO: recuperación, captación, solicitudes, colocación, tasa de mora; MANUAL: métricas custom con avance a mano), metas mensuales `metas` (empleado+métrica+periodo YYYY-MM), modo MINIMO (al menos) | MAXIMO (no superar), cumplida/cerrada/% calculados al vuelo
- `AsistenciaService.php` — marcación única carnet+PIN (kiosco público `/{slug}/asistencia`): si hay jornada ABIERTA la marca cierra como salida (cubre turnos que cruzan medianoche), si no abre entrada; cooldown 90s anti doble-tap; alta manual/edición/anulación por admin (`editada`+`editado_por`)
- `ReporteService.php` — reportes agregados del tenant: `creditos()` (pipeline por estado, desembolsos del rango, gestión por gestor, salud de cartera) y `finanzas()` (cobros aplicados por método/gestor + ingresos − gastos = neto)
- `Services/Admin/TenantService.php` — panel admin: lista tenants, crear() con provision de seguridad (menús, role_permissions y role_menus copiados del tenant 1) + usuario admin opcional, toggle ACTIVO/INACTIVO
- `Models/CuotaModel.php`, `Models/PagoModel.php`, `Models/ArqueoModel.php`, `Models/GastoModel.php`, `Models/GastoCategoriaModel.php`, `Models/IngresoModel.php`, `Models/AsistenciaModel.php`, `Models/MetaModel.php`, `Models/MetaMetricaModel.php`
- `Controllers/Partner/CreditoController.php` (`/creditos`), `PagoController.php` (`/pagos`), `ArqueoController.php` (`/finanzas/arqueo`), `GastoController.php` (`/finanzas/gastos`, permiso `caja.gastos`), `IngresoController.php` (`/finanzas/ingresos`, permiso `caja.ingresos`; `GET /finanzas/ingresos/{id}/recibo` solo VENTA), `AsistenciaController.php` (kiosco `/{slug}/asistencia` público + panel `/asistencia`, permiso `empleados.asistencia`), `MetaController.php` (`/finanzas/metas`, permiso `metas.plan`; `POST metas/metricas` crea métricas custom MANUAL), `ReporteController.php` (`/credito/reporte` + `/finanzas/reporte`, permiso `reportes.ver`), `HerramientasController.php` (`/herramientas/calculadora` sin permiso + `/herramientas/documentacion` permiso `solicitudes.documentacion` → enlaza a `solicitudes/{id}/documentos`). También `CreditoController::cartera` (`/credito/cartera`, salud de cartera) y `PagoController::recuperacion` (`/finanzas/recuperacion`, cuotas vencidas por gestor)
- `PagoService.php` — cobros: cuotas del plan (generadas al entregar desembolso), pagos que nacen en REVISION y se aplican a cuotas FIFO solo al aprobarse (transacción), bandeja de aprobación, cobros del gestor

### Socios (partner): personas/clientes/empleados
- `Controllers/Partner/ClienteController.php` — CRUD clientes, expediente
- `Controllers/Partner/EmpleadoController.php` — CRUD empleados, carnet+PIN portal
- `Controllers/Partner/ConfiguracionController.php` — datos del tenant, plantillas
- `Models/ClienteModel`, `EmpleadoModel`, `PersonaModel`, `PersonaDetalleModel`
- `Views/partner/clientes/{index,form,ver}.php`
- `Views/partner/empleados/{index,form,ver}.php` (ver.php usa maplibre CDN)
- `Views/partner/configuracion/{index,plantilla}.php`

### Créditos y pagos (`/creditos`, `/pagos`)
- `Controllers/Partner/CreditoController.php` — `/creditos`, `/creditos/{id}` (plan + abono), `POST abonar` → REVISION
- `Controllers/Partner/PagoController.php` — `/pagos` con tabs Revisión/Aplicados, `POST registrar|aprobar|rechazar|revertir|cumplio`, `GET recibo`
- `Models/CuotaModel`, `Models/PagoModel` — `pagos.estado`: REVISION → APLICADO | RECHAZADO (| REVERTIDO); `pagos.tipo`: PAGO | PROMESA; `pagos.revierte_id` liga contra-pago → original
- `Views/partner/creditos/{index,ver}.php`, `Views/partner/pagos/{index,_modal_abono}.php`
- Reversión = contra-pago en negativo (`PagoService::revertirPago` → REVISION → al aprobar desaplica cuotas LIFO y marca original REVERTIDO). Permiso `pagos.revertir`.
- Promesas de pago = pago tipo PROMESA en REVISION con fecha futura; `POST cumplio` lo convierte en cobro.
- Portal gestor: `{slug}/portal/cobros` — cuotas por cobrar + abono en campo (REVISION); modal en ficha cliente
- Permiso `pagos.aprobar` — admin/superadmin/gerente/supervisor (ojo: los grants son por tenant en `role_permissions.tenant_id`)

### Crédito
- `Controllers/Partner/SolicitudController.php` — flujo completo: index, ver, cambiarEstado, analisis, aprobar, desembolsar, editar, documentos, planPagos
- `Models/SolicitudModel.php` — estados CONTACTO|CREADA|REVISION|APROBADA|DESEMBOLSO|ACTIVO|RECHAZADA, permisos por estado, `siguienteCodigoCredito()`
- `Models/SolicitudAnalisisModel.php` — análisis crediticio persistido
- `Views/partner/solicitudes/documentos.php` — contrato con cláusulas legales Nicaragua: Cuarto costo total + CAT (Ley 842), Sexto incumplimiento + interés moratorio (`tenants.mora_diaria_pct`), Séptimo datos personales (Ley 787), Octavo origen de fondos (Ley 977); RUC del tenant en encabezados
- `Views/partner/solicitudes/` — `index`,ver,editar,aprobar,desembolsar,analisis,documentos}.php`
- `public/js/solicitud-editar.js` — wizard del formulario

### Portal del gestor `/{slug}/portal`
- `Controllers/Partner/PortalController.php` — landing, login carnet+PIN, panel, solicitar (público), solicitud (gestor), desembolso/entregar, cartera, actividad, perfil, calculadora, recuperacion
- `Models/AccesoSolicitudModel.php` + migración `2026-09-26-000001_AccesoSolicitudes` — solicitudes de acceso de la landing
- Migración `2026-09-27-000011_TenantRuc` — `tenants.ruc` (identidad fiscal; se imprime en contrato, relación de garantías y recibo de pago; editable en `/configuracion`)
- Migración `2026-09-27-000012_ConamiRegistro` — `tenants.conami_registro`; si está cargado muestra sello "Registrada ante CONAMI" en footers de `landing.php`/`solicitar.php` (`.lp-conami` en landing.css) y "Reg. CONAMI Nº" en encabezados de `documentos.php`
- `public/img/landing/` — capturas del producto para el hero: `dashboard.png` y `gestor.png` (las coloca el usuario)
- `Views/partner/portal/{landing,login,panel,perfil,solicitar,solicitud,seccion,calculadora}.php` (seccion.php = vista genérica de secciones)
- `public/js/portal/{calculadora,solicitud}.js` + `css/portal.css`
- Sesión propia `portal_empleado_id` — separada de la sesión de usuario

### Dashboard / admin
- `Controllers/Partner/DashboardController.php` + `Services/Partner/DashboardService.php` — chips-KPI reales clicables (clientes, sin docs, por aprobar, desembolsar, por cobrar hoy, mora, cobrado hoy, pagos en revisión)
- `Controllers/Partner/AsistenteController.php` + `Services/Partner/AsistenteService.php` — `GET /asistente/datos/{tipo}` listas JSON para el Chat-AI: `mora`, `pagos_hoy`, `por_cobrar`, `solicitudes`, `sin_docs`, `pagos_revision`; el parser (`public/js/chat-ai.js`) las pinta como `.chat-list` (css/chat.css)
- `Controllers/Partner/ReporteController.php` + `Services/Partner/ReporteService.php` — `/reportes` índice de cards por categoría con buscador (catálogo `Config/Reportes.php`, categorías personalizables en `reporte_categorias`/`reporte_asignaciones` vía `/configuracion/reportes`; `Config/Reportes.php` incluye `kw` keywords que alimentan `window.CHAT_REPORTES` del asistente), `/credito/reporte` (pipeline, desembolsos, por gestor, salud de cartera), `/credito/reporte-conami` (clasificación A1–D2 por días de atraso + provisión, CSV/imprimir; tabla en `ReporteService::CONAMI`) y `/finanzas/reporte` (cobros+ingresos−gastos)
- `Controllers/Admin/EstadisticasController.php` + `Views/admin/estadisticas/index.php`
- `Controllers/Admin/TenantController.php` — `/admin/tenants` lista clicable (`.tn-row`), `GET tenants/{id}` ficha con stats + usuarios del tenant (agregar via `usuarios/nuevo?tenant=&back=`, editar/toggle/quitar con retorno a la ficha), `POST toggle`, `POST tenants/{tid}/usuarios/{uid}/quitar` (soft delete, no permite quitarse a sí mismo)
- `Controllers/Admin/UsuarioController.php` — `/admin/usuarios` usuarios de todos los tenants: listado con filtro tenant+búsqueda, alta, edición, toggle, reset manual y `reset-rapido` (clave temporal aleatoria → banner copiable/compartible); `backTo()` propaga `?back=admin/tenants/{id}` para regresar a la ficha
- `Controllers/Admin/AuditoriaController.php` — `/admin/auditoria` bitácora `audit_logs` global (filtros tenant/módulo/fechas, máx 300)
- `Controllers/Admin/ConfiguracionController.php` — `/admin/configuracion` catálogo `planes` editable (precio, límites; -1 = ilimitado)
- `Views/admin/{tenants/{index,nuevo},usuarios/index,auditoria/index,configuracion/index}.php`
- `Views/partner/dashboard/index.php` — chips de contadores
- `Views/partials/{detail_hero,list,pager}.php` — componentes compartidos

### Layouts / vistas base
- `Views/layouts/{partner,admin,portal}.php` — <link> de css y js de cada panel
- `Views/landing/index.php` — landing pública de CFSI (topbar, hero con marcos de captura, beneficios, dirigido, funciones, proceso, app, FAQ, contacto+form, footer)
- `Views/shared/auth/login.php`, `Views/shared/perfil/cambiar_password.php`

## Assets públicos (`public/`)

| Archivo | Usado por |
|---|---|
| `css/app.css` | layouts partner + portal landing/solicitar/editar |
| `css/contamos.css` | landing pública CFSI (`/`) — mundo "ledger": topbar/hero/.shots/.rule-list/.index-list/.proc/.route/.faq/.contacto |
| `css/landing.css` | landing del portal (lp-*) + solicitar |
| `css/auth.css` | login, cambiar password |
| `css/admin.css` | layout admin |
| `css/portal.css` | layout portal, solicitudes/editar |
| `js/app.js` | layout partner |
| `js/portal/calculadora.js`, `js/portal/solicitud.js`, `js/solicitud-editar.js` | vistas puntuales |
| `uploads/{documentos,logos}/` | archivos subidos |

## Tooling de agente (no es código de la app)

`.impeccable/` — source local de skills Impeccable (bundle `skill-v4.3.1`, el CDN `impeccable.style` 404; `impeccable update` no funciona hasta que lo arreglen). `.agent/` `.cursor/` `.gemini/` `.opencode/` — symlinks creados por `npx impeccable link`. Todo ignorado en git.

## Auditoría del motor de crédito (agentes)

- `AUDITORIA.md` — reporte del Agente 1 (casos ✅/⚠/❌ + críticos). Cobertura actual: 45.5%.
- `catalogo-capacidades.json` — contrato de capacidades del motor; lo consume el Agente 2 (empresas simuladas → FEATURE GAPs).
- `.devin/workflows/auditoria.md` — cómo re-ejecutar el auditor tras cada feature.

## Datos (tablas clave)

`tenants`, `users`, `roles`, `permisos`, `audit_logs`, `notifications`, `plantillas`,
`personas`, `persona_detalles`, `clientes`, `empleados`, `solicitudes`, `solicitud_analisis`,
`gasto_categorias`, `gastos`, `ingresos`, `meta_metricas`, `metas`, `cuotas`, `pagos`, `arqueos`, `asistencias`, `pago_aplicaciones`, `valoraciones`, `planes`, `plan_solicitudes`, `plan_pagos`.

**Pendientes** (módulo de pagos): `gestiones_cobro` (no-pago con motivo). Reversiones resueltas con contra-pago negativo (`pagos.revierte_id`), sin tabla extra.
Mora/pronto pago/saldo a favor: `tenants.{mora_diaria_pct,pronto_pago_pct}` (0 = off), `cuotas.{mora_dev,mora,descuento}`, `solicitudes.saldo_favor` — lógica en `PagoService::{moraDeCuota,cuotaPendiente,interesPendiente}` y `aprobarPago`.
Valoración del sistema: tabla `valoraciones` (user×tenant, estrellas+reseña), `Services/Partner/ValoracionService.php` (pendiente cada 5 días desde última calificación), `Controllers/Partner/ValoracionController.php` (`POST /valoracion`), modal auto-open en `layouts/partner.php` + JS/CSS en `app.js`/`app.css` (snooze 'Ahora no' por sesión).
SaaS por planes: catálogo `planes` (Básico $35: 50 créditos/5 empleados/3 usuarios; Profesional $79; Empresarial $149; -1=ilimitado), `tenants.{plan_id,dia_pago,suscripcion_estado}`, `plan_solicitudes` (cambio de plan tenant→admin PENDIENTE|APROBADA|RECHAZADA), `plan_pagos` (1 fila por período YYYY-MM, estado PENDIENTE|PAGADO|VENCIDO). `Helpers/plan_helper.php`: `plan_actual/limite/uso/puede/al_dia/proximo_pago` — cargado en `BaseController::$helpers`. Pendiente: portal admin (crear tenants, aprobar cambios de plan, registrar pagos) y enforcement en altas (crédito/empleado/usuario).
Bloqueo por suscripción: filtro `suscripcion` (`Filters/SuscripcionFilter.php`) en el grupo partner → si `plan_al_dia()` falla redirige a `GET /cuenta-suspendida` (`Controllers/Partner/SuscripcionController.php` + `Views/partner/suspendida.php` standalone, con form "reportar pago" → `plan_pagos` PENDIENTE del período). Portal gestores: `PortalController::{login,entrar,ctx}` chequean el plan → `GET /{slug}/portal/suspendida` (`Views/partner/portal/suspendida.php`). Superadmin bypass.

## Estado del pipeline de crédito (verificado 26 Sep)

`CONTACTO → CREADA → REVISION → APROBADA → DESEMBOLSO → ACTIVO` (`RECHAZADA` en cualquier punto).
`codigo_credito` C-{iniciales}-{año}-{0000} al Entregar. `documentos()` genera plan/contrato/garantías.
Cuotas persistidas al entregar (`PagoService::generarCuotas`); `fecha_entrega` real en solicitudes.
Gaps para pagos: sin auditoría de estados; créditos activados antes del 26/9 no tienen cuotas (solicitud #1).
