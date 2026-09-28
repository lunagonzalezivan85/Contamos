# Plan de Mejora — CFSI

**Versión:** 1.0 | **Fecha:** 24 Sep 2026
**Basado en:** `ANALISIS_PROYECTO.md` sección 9 (riesgos y deuda técnica)

---

## Fase 0 — Seguridad crítica (1–2 días)

> Sin esto, todo lo demás es irrelevante: el sistema es comprometible hoy.

| # | Acción | Archivo(s) | Esfuerzo |
|---|---|---|---|
| 0.1 | **Login seguro:** usar query bindings y `password_verify()` | `Seguridad.php` | 2h |
| 0.2 | **Migrar passwords a hash:** `password_hash()` en `Usuario::save`/`resetPassword`/`CambiarContrasena` + script de migración de las existentes | `Usuario.php`, BD | 3h |
| 0.3 | **Eliminar bypass** `userPass=="12345"` → reemplazar por flag `debeCambiarPassword` en tabla usuario | `Seguridad.php` | 1h |
| 0.4 | **Activar CSRF:** `csrf_protection=TRUE` + token en formularios (CI3 lo agrega con `form_open()`) | `config.php`, vistas | 4h |
| 0.5 | **Configurar seguridad:** `encryption_key` aleatorio, `cookie_httponly=TRUE`, `cookie_secure=TRUE` (prod HTTPS), `sess_regenerate_destroy=TRUE` | `config.php` | 30min |
| 0.6 | **Autorización por método:** helper `requireRole([1,2,4])` llamado al inicio de `aprobar`, `Desembolsar`, `Rechazar`, `Reversion/aprobar`, etc. | `auth_helper.php`, controladores | 4h |
| 0.7 | **Mover/eliminar archivos debug** de raíz pública (`debug_*.php`, `test_*.php`, `check_paths.php`, `info*.txt`) | raíz | 30min |

**Entregable:** login no inyectable, passwords hasheadas, CSRF activo, acciones sensibles protegidas por rol.

---

## Fase 1 — Integridad de datos (2–3 días)

| # | Acción | Detalle |
|---|---|---|
| 1.1 | **Transacción en `Solicitud::Desembolsar`** | `trans_start()/trans_complete()` envolviendo: insert credito + GenerarAbonos + cancelación de crédito anterior + update solicitud + historial |
| 1.2 | **Corregir `numbertToText`** | Reescribir con algoritmo correcto de miles/millones o usar `NumberFormatter::SPELLOUT` (ya usado en `Desembolsar` línea 450 — estandarizar) |
| 1.3 | **Query bindings en filtros** | Reemplazar `"campo='".$valor."'"` por `$this->db->where()` con escaping. Prioridad: `Solicitud`, `Credito`, `Pagos`, `Gestor`, `Api` |
| 1.4 | **Validación de entrada** | `form_validation` en todos los POST que hoy solo hacen `set_value()` |
| 1.5 | **Escapar salidas** | `htmlspecialchars()` en vistas que imprimen datos de usuario |

**Entregable:** operaciones financieras atómicas, inputs sanitizados.

---

## Fase 2 — Nueva funcionalidad: Generar Documentación (2–3 días)

> Spec completa en `PRD.md` §4 y `TRN.md` §4.

| # | Acción |
|---|---|
| 2.1 | Completar `credito/contrato.php` (hoy casi vacía) con plantilla desde `configuracion` |
| 2.2 | Crear vistas `solicitud/docs/`: `ficha.php`, `contrato.php`, `pagare.php`, `plan_pago.php`, `recibo_desembolso.php`, `paquete.php` |
| 2.3 | Endpoints `Solicitud/documentacion/{id}` (selector) + `doc{Tipo}/{id}` |
| 2.4 | Botón "Generar Documentación" en `solicitud/view` (botones flotantes) e `index` (acción por fila) |
| 2.5 | Auditoría: insert en `historialsolicitud` por cada generación |
| 2.6 | Permisos: roles 1,2,3,4 completo; gestor solo ficha |

**Entregable:** paquete documental completo desde un clic.

---

## Fase 3 — Rediseño One UI (5–8 días, incremental)

> Spec completa en `UI_UX.md`.

| # | Acción |
|---|---|
| 3.1 | Crear `resources/css/oneui.css` con tokens + componentes (`.oui-card`, `.oui-btn`, `.oui-header`, `.oui-sheet`, chips de estado) |
| 3.2 | Migrar Dashboard → Solicitudes → Credito detalle → Pagos → resto (orden del plan de migración) |
| 3.3 | Bottom action bar en móvil; FABs en desktop |
| 3.4 | Reemplazar `?v=".date("s")` por `?v=".filemtime()` o versión fija por release |

**Entregable:** UI moderna consistente sin romper AdminLTE.

---

## Fase 4 — Limpieza estructural (3–5 días)

| # | Acción |
|---|---|
| 4.1 | Consolidar `Credito`/`Credito2`/`Credito10` → uno solo; eliminar `MenuManagerTemp`, `MenuManager_simple`, `MenuTest` |
| 4.2 | Deprecar `Persona` en favor de `Persona2` (wizard) |
| 4.3 | Renombrar `Rms_model` → `Rms_model.php` o eliminar |
| 4.4 | Crear `MY_Controller` con: `isLogin()`, carga de notificaciones, `requireRole()` — elimina código repetido en 49 constructores |
| 4.5 | `base_url` por entorno (variable de entorno o detección de host) |
| 4.6 | Modelos específicos para entidades críticas (`Credito_model`, `Solicitud_model`) en vez de `Global_model` genérico — gradual |

---

## Fase 5 — Calidad y operación (continuo)

- **Pruebas del módulo Reversion** (pendiente de sesión anterior): solicitud, aprobación transaccional, rechazo, cancelación, reporte, exportación
- **Botón "Ver Plan de Pago"** en solicitud (pendiente)
- **Rediseño aprobación de solicitud** (pendiente)
- Logs de errores a archivo + rotación; backups automáticos verificados
- `.htaccess` denegando acceso a `app/`, `sys/`, `docs/`, `*.sql`

---

## Resumen ejecutivo

| Fase | Tema | Esfuerzo | Riesgo si no se hace |
|---|---|---|---|
| 0 | Seguridad crítica | 1–2 días | **Compromiso total del sistema** |
| 1 | Integridad de datos | 2–3 días | Corrupción financiera, inyección SQL |
| 2 | Generar Documentación | 2–3 días | Feature solicitada |
| 3 | One UI | 5–8 días | UX obsoleta |
| 4 | Limpieza | 3–5 días | Mantenibilidad |
| 5 | Calidad | continuo | Regresiones |

**Recomendación:** ejecutar Fase 0 inmediatamente — son ~15h de trabajo que cierran las vulnerabilidades más graves. Fases 2 y 3 pueden ir en paralelo después.
