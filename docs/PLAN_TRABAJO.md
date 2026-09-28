# Plan de Trabajo — CFSI (CodeIgniter 4)

**Fecha:** 2026-10 | **Sustituye a:** `PLAN_MEJORA.md` (escrito para el codebase CI3 legacy)
**Basado en:** análisis del estado real del repo CI4 (Controllers → Services → Models)

---

## Estado cubierto por la migración a CI4

Del plan viejo, ya resuelto por la reescritura — no repetir:

| Ítem viejo | Estado en CI4 |
|---|---|
| 0.1 Login con bindings + `password_verify` | ✅ `AuthService` usa `password_verify` sobre `password_hash` |
| 0.2 Passwords hasheadas | ✅ `UserModel`/`SecuritySeeder` con `password_hash()` |
| 0.3 Bypass `12345` | ✅ No existe; hay `debe_cambiar_password` |
| 0.4 CSRF | ✅ Filter `csrf` global (`Filters.php`), `csrf_field()` en formularios |
| 0.5 encryption_key + cookies | ✅ `encryption.key` en `.env`, `secureheaders` global |
| 0.6 Autorización por método/rol | ✅ Filters `auth`, `admin`, `horario` + permisos granulares (`auth:empleados.ver`) |
| 0.7 Archivos debug públicos | ✅ Sin `debug_*`/`test_*`/`info*` en raíz ni `public/` |
| 1.3 Query bindings en filtros | ✅ Query builder en todos los modelos |
| 1.4 Validación de entrada | ✅ `$this->validate()` en controllers |
| 1.5 Escapar salidas | ✅ `esc()` en vistas |
| 3.x One UI / rediseño | ✅ Sistema propio (`app.css`, `portal.css`, oui-*), portal gestor PWA |
| 4.x Limpieza estructural | ✅ Services layer, sin `MY_Controller`, sin modelos duplicados |

---

## Fase A — Integridad transaccional (prioridad alta)

> Ninguna operación multi-escritura usa transacciones hoy. Si falla a la
> mitad quedan filas huérfanas (persona sin cliente, solicitud sin ficha).

| # | Acción | Archivo |
|---|---|---|
| A.1 | `crearLeadWeb`: transacción persona→cliente→solicitud | `PortalService` |
| A.2 | `crearSolicitudGestor`: transacción persona→cliente→solicitud | `PortalService` |
| A.3 | `entregarDesembolso`: transacción (estado + `codigo_credito`); `siguienteCodigoCredito` lee-escribe MAX — riesgo de colisión concurrente | `PortalService`, `SolicitudModel` |
| A.4 | `calcularAnalisis`: update-or-insert sin protección → unique key `(tenant_id, solicitud_id)` en `solicitud_analisis` o transacción | `SolicitudService`, migración |
| A.5 | `actualizarDato`/`agregarDato` + upload de archivo: si falla el insert queda archivo huérfano → borrar archivo si falla | `PortalService`, `PersonaService` |

## Fase B — Paquete documental (feature pendiente del plan viejo)

> `planPagos()` ya existe en `SolicitudService`. Falta la capa de documentos imprimibles.

| # | Acción |
|---|---|
| B.1 | `GET solicitudes/{id}/documentacion` — selector de documentos |
| B.2 | Vistas imprimibles: `ficha`, `contrato` (con plantilla del tenant), `pagare`, `plan_pago`, `recibo_desembolso` |
| B.3 | Monto en letras con `NumberFormatter('es', SPELLOUT)` (ext-intl ya requerida) |
| B.4 | Botón "Documentación" en solicitud/ver + permisos por rol |
| B.5 | Registro de generación (si se decide tabla de auditoría `solicitud_historial`) |

## Fase C — Portal del gestor (continuación)

| # | Acción |
|---|---|
| C.1 | Pestaña Recuperación: clientes en cobro con `asignado_a` (hoy placeholder) |
| C.2 | Notificaciones del gestor en el portal (badge en tabbar) |
| C.3 | Editar perfil del gestor (teléfono, dirección) |

## Fase D — Calidad y operación

| # | Acción |
|---|---|
| D.1 | Tests feature para rutas del portal (login, scope de cartera, CRUD dato/{tipo}) |
| D.2 | `cookie.secure = true` + `forcehttps` en producción |
| D.3 | `.htaccess`/`web.config` denegando `docs/` si se sirve desde raíz |
| D.4 | Backups automáticos verificados + rotación de logs |

---

## Convenciones

- Controllers solo HTTP (sesión, redirect, validate, flash); lógica en `Services/`; queries en `Models/`.
- `esc()` en salidas, `csrf_field()` en POST, `base_url()` en links, `v_asset()` en assets.
- Seguir `docs/CONVENCIONES.md` y `ESTRUCTURA.md`.
