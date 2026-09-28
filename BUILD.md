# BUILD — Contamos (Control de Préstamos)

**Última actualización:** 24 Sep 2026
**Stack:** CodeIgniter 4.7.4 · PHP 8.2 · MariaDB 10.4 · Multi-tenant

---

## 1. Entorno

| Recurso | Valor |
|---|---|
| URL local | `http://localhost/cfsi/` |
| Login | `http://localhost/cfsi/login` |
| BD activa | `cfsi` (nueva, desde cero) |
| BD legacy | `creditmanagment` (referencia, intacta) |
| Usuario BD | `root` (sin password, XAMPP) |
| Credenciales admin sistema | tenant `cfsi` — `admin` / `Admin2026!` |
| Credenciales tenant prueba | tenant `tu-impulso` — `galvin` / `TuImpulso2026!` |
| Backup proyecto viejo | `c:\xampp\backups\cfsi_20260924\` (187 MB + `creditmanagment_backup.sql`) |

---

## 2. Arquitectura

```
Controller (HTTP) → Service (negocio) → Model (datos)
```

### Separación por área

```
app/Controllers/
├── Admin/     # panel del sistema (superadmin): estadísticas, tenants, usuarios
├── Partner/   # operación del tenant: dashboard, solicitudes, créditos, pagos
└── Shared/    # compartidos: auth, perfil, landing

app/Services/  → Admin/ · Partner/ · Shared/   (misma división)
app/Views/     → admin/ · partner/ · shared/ · landing/ · layouts/(admin|partner).php
```

- Rutas `/admin/*` → filtro `admin` (solo `es_admin_sistema`)
- Rutas tenant → filtro `auth` (+ permiso opcional `auth:modulo.accion`)
- Redirect post-login: superadmin → `/admin/estadisticas`, tenant → `/dashboard`
- Layouts distintos: `layouts/admin.php` (índigo, badge ADMIN) vs `layouts/partner.php` (verde)

- **Convenciones completas:** `docs/CONVENCIONES.md`
- **Reglas de desarrollo:** CSS solo en `public/css/`, JS solo en `public/js/` — prohibido `<style>`, `style=""`, `<script>`, `onclick=""` en vistas PHP
- **Multi-tenant:** `tenant_id` en toda tabla de negocio; el login identifica la empresa por el username (sin selector)
- **Seguridad:** `password_hash`, CSRF global, secure headers, `AuthFilter` por ruta/permiso, `esc()` en vistas
- **Docs del proyecto:** `docs/` (PRD, TRN, UI_UX, APPFLOW, ANALISIS_PROYECTO, PLAN_MEJORA, CONVENCIONES)

---

## 3. Base de datos `cfsi` — Módulo Seguridad

### Migraciones ejecutadas (3)

| Archivo | Tablas |
|---|---|
| `2026-09-24-000001_CreateSecurityCore` | `tenants`, `roles`, `users` |
| `2026-09-24-000002_CreateSecurityAccess` | `permissions`, `role_permissions`, `menus`, `role_menus` |
| `2026-09-24-000003_CreateSecurityAudit` | `password_reset_tokens`, `login_attempts`, `audit_logs` |
| `2026-09-24-000004_AddContactFieldsToTenants` | +`razon_social`, `contacto_nombre`, `contacto_cargo` en `tenants` |
| `2026-09-24-000005_FixUniqueKeysTenantScope` | unique keys de `role_permissions`/`role_menus` incluyen `tenant_id` |
| `2026-09-24-000006_CreateNotifications` | `notifications` (tenant, usuario, tipo, leida) |
| `2026-09-24-000007_AddDireccionToTenants` | +`direccion` en `tenants` |
| `2026-09-24-000008_AddConfigFieldsAndPlantillas` | +`lema`/`voucher_footer`/`horario` en `tenants`; tabla `plantillas` |
| `2026-09-24-000009_AddHorarioSistemaToTenants` | +`hora_inicio`/`hora_fin` en `tenants` |
| `2026-09-24-000010_AddMonedaToTenants` | +`moneda` en `tenants` (default `RD$`) |
| `2026-09-24-000011_SetMonedaDefaultCordobas` | default `moneda` → `C$`; opciones C$/USD |
| `2026-09-24-000012_CreatePersonas` | `personas` (tipo CLIENTE/EMPLEADO) |
| `2026-09-24-000013_CreatePersonaRolesAndDetalle` | `empleados`, `clientes` + `persona_{direcciones,contactos,referencias,negocios,activos,pasivos,ingresos,documentos}` |
| `2026-09-24-000014_AddCarnetPinToEmpleados` | +`carnet`, `pin` en `empleados` |

### Tenants registrados

| id | slug | nombre | razón social | contacto |
|---|---|---|---|---|
| 1 | `cfsi` | CFSI | — | — |
| 2 | `tu-impulso` | Tu Impulso | Microfinanciera Tu Impulso | Galvin Garcia (Propietario) |

> Al crear un tenant hay que sembrar sus `role_permissions` (copiar plantilla del tenant 1).

### Menús — `app/Config/Menu.php`

Los menús se definen en código (no en BD). Dos menús:

- **`$sistema`** — solo rol `superadmin`: Estadísticas, Tenants, Usuarios, Auditoría, Configuración
- **`$tenant`** — operativo, filtrado por permisos del rol: Dashboard, Configuración, Socios (Clientes, Empleados), Crédito (Solicitud, Créditos, Gestión de cartera, Reporte), Finanzas (Caja, Arqueo, Recuperación, Reporte), Herramientas (Calculadora, Documentación). **Sin gestión de usuarios** (solo el admin del sistema crea usuarios)

### UI del partner

- **Sidebar desplegable** — grupos colapsan/expanden al clic (`nav-toggle` + `app.js`), iconos SVG vía `icon_helper`
- **Paleta de comandos Ctrl+K** — buscador de páginas/acciones; items renderizados server-side, `public/js/app.js` filtra y navega (↑↓ Enter Esc)
- **Dashboard estilo IA** — hero (saludo + icono), input tipo prompt con botón Procesar (placeholder "en desarrollo"), chips-contadores (Clientes, Créditos activos, Cobrado hoy, En mora — `DashboardService`, placeholders hasta que existan tablas de negocio). Escribir `\` en el input abre un desplegable con las acciones rápidas (mismos items de la paleta Ctrl+K, ↑↓ Enter Esc)
- **Notificaciones** — campana con badge en topbar + panel derecho deslizante; endpoint JSON `/notificaciones` (GET lista, POST `/{id}/leida`, POST `/leer-todas`); tabla `notifications` (por tenant, `user_id` NULL = broadcast)

### Configuración del tenant

`/configuracion` (permiso `admin.configuracion`) — `ConfiguracionController` en Partner. Actualiza `session('tenant_name')` al guardar.

Secciones:
- **Datos de la empresa** — logo (upload a `public/uploads/logos/`, PNG/JPG/WEBP/GIF ≤2MB, preview en vivo), nombre*, razón social, email, teléfono, dirección, lema, voucher_footer (pie de recibos), horario, moneda (C$ córdoba / USD, default C$), contacto (nombre/cargo)
- **Horario de uso** — `hora_inicio`/`hora_fin` (HH:MM). `HorarioFilter` bloquea al rol `gestor` fuera del rango → redirect a `/dashboard` con aviso. Vacío = sin restricción. Solo afecta `gestor`; demás roles entran siempre
- **Plantillas** — tabla `plantillas` por tenant (vouchers, contratos, pagarés). Lista One UI clickeable → editor `/configuracion/plantilla/{id}` — **WYSIWYG tipo Word** (toolbar: negrita/cursiva/subrayado, alineación, listas, título/párrafo, limpiar) sobre `contenteditable`, HTML → textarea oculto al guardar. `{variables}` dinámicas. Selector "Cargar plantilla del sistema" → `plantillas_helper::plantillas_sistema()` carga propuestas predefinidas al editor

### Socios — Empleados

`/socios/empleados` (permiso `empleados.ver`) — `EmpleadoController`. Modelo de 3 capas:
- `personas` — identidad (nombres, apellidos, cédula, contacto, dirección); compartida con Clientes
- `empleados` — rol (cargo, fecha_ingreso, estado) ligado a `persona_id`
- `persona_*` — 8 tablas hijas por `persona_id`: direcciones, contactos, referencias, negocios, activos, pasivos, ingresos, documentos

- **Index** — lista One UI clickeable + buscador; clic en el nombre → detalle
- **Ver** — card de persona (avatar, nombre, badges, meta) + pestañas: Datos | Direcciones | Contactos | Referencias | Negocios | Activos | Pasivos | Ingresos | Documentos. Cada pestaña muestra su tabla y un form inline para agregar (`POST /dato/{tipo}` → vuelve a `?tab=`). Eliminar por fila.
- **Crear** — form de persona (+carnet auto `TI-0001`, PIN con botón Generar, cargo/ingreso/estado) → inserta en `personas` + `empleados`. Carnet = iniciales del tenant + consecutivo (`EmpleadoModel::siguienteCarnet`); PIN de 4 dígitos autogenerado si vacío.
- **Pestaña Documentos** — sube archivo a `public/uploads/documentos/` (≤5MB), columna Archivo enlaza al documento.
- `PersonaDetalleModel` maneja las 8 hijas por tipo (genérico)

### Portal del empleado

`/{slug}/portal` — acceso público por slug de empresa, login con **carnet + PIN** (`PortalController`). Sesión propia (`portal_empleado_id`, separada del usuario del sistema). Panel con datos de la persona y sus documentos descargables; botón Salir. Layout standalone `layouts/portal.php` con marca del tenant.

El menú NO se cachea en sesión — `menu_items()` (helper) lo resuelve por request vía `MenuService` desde `Config/Menu.php` + permisos de sesión. Cambios en `Menu.php` son inmediatos sin re-login. Las tablas `menus`/`role_menus` quedan sin uso (reservadas para personalización futura por tenant).

### Seeder (`SecuritySeeder`)

| Dato | Cantidad |
|---|---|
| Tenant `cfsi` | 1 |
| Roles de sistema | 6 (superadmin, admin, gerente, supervisor, gestor, cajero) |
| Permisos `modulo.accion` | 29 |
| Menús jerárquicos | 18 |
| Usuario admin | 1 (`debe_cambiar_password=1`) |

---

## 4. Archivos creados

### Models — `app/Models/`

| Archivo | Propósito |
|---|---|
| `UserModel.php` | users + `findByUsername()`, `findByEmail()` por tenant |
| `TenantModel.php` | tenants + `default()`, `findBySlug()` |
| `RoleModel.php` | roles + `permissionCodes()`, `menus()` por rol/tenant |

### Services — `app/Services/`

| Archivo | Propósito |
|---|---|
| `AuthService.php` | `attempt()` con `password_verify`, `buildSession()` (permisos+menús), auditoría `login_attempts`, helper `can()` |

### Controllers — `app/Controllers/`

| Archivo | Rutas |
|---|---|
| `AuthController.php` | `GET/POST /login`, `GET /logout` |
| `LandingController.php` | `GET /` (landing pública) |
| `DashboardController.php` | `GET /dashboard` (protegida) |

### Filters — `app/Filters/`

| Archivo | Propósito |
|---|---|
| `AuthFilter.php` | Sesión obligatoria + permisos por argumento (`auth:solicitudes.aprobar`) |

### Views — `app/Views/`

| Archivo | Propósito |
|---|---|
| `landing/index.php` | Landing pública One UI (hero, quiénes somos, features, pasos, APK, CTA) |
| `auth/login.php` | Login One UI con CSRF |
| `perfil/cambiar_password.php` | Cambio de contraseña (página independiente) |
| `layouts/main.php` | Layout con sidebar dinámico desde `session('menus')` |
| `dashboard/index.php` | Dashboard mínimo |

### Assets — `public/`

| Archivo | Propósito |
|---|---|
| `css/app.css` | Layout principal + componentes compartidos |
| `css/auth.css` | Login, cambiar contraseña, recuperación |
| `css/landing.css` | Landing pública |

### Config modificada

| Archivo | Cambio |
|---|---|
| `app/Config/Routes.php` | `/` → landing, `/login` → auth, grupo `auth` → dashboard |
| `app/Config/Filters.php` | alias `auth`, CSRF global (excepto `api/*`), secure headers |
| `.env` | `development`, `baseURL`, BD `cfsi`, encryption key |

---

## 5. Verificación end-to-end

| Prueba | Resultado |
|---|---|
| `GET /` | HTTP 200 — landing "Contamos" |
| `GET /login` | HTTP 200 — formulario con token CSRF |
| `POST /login` (admin/Admin2026!) | 303 → `/perfil/cambiar-password` |
| `POST /login` (password incorrecta) | 303 → `/login` (mensaje genérico) |
| Auditoría | `login_attempts` registra IP + user agent |
| `spark migrate` / `db:seed` | OK |

---

## 6. Pendientes

### Inmediato
- [x] `GET/POST /perfil/cambiar-password` — `PerfilController` + `AuthService::changePassword()` + vista. Flujo verificado: login → cambio → dashboard

### Módulo Seguridad (completar)
- [ ] CRUD usuarios (`/admin/usuarios`)
- [ ] CRUD roles + asignación de permisos (`/admin/roles`)
- [ ] CRUD menús + asignación por rol (`/admin/menus`)
- [ ] Recuperación de contraseña (`password_reset_tokens` ya existe)
- [ ] Vista de auditoría (`audit_logs`, `login_attempts`)
- [ ] Selector de tenant en login (hoy usa tenant default)

### Siguientes módulos (según PRD)
- [ ] Personas/Clientes
- [ ] Solicitudes de crédito (+ Generar Documentación)
- [ ] Créditos + planes de pago
- [ ] Pagos + reversiones
- [ ] Caja (arqueo, gastos)
- [ ] Reportes
- [ ] Dashboard con widgets por rol

---

## 6.5 Plan de trabajo (roadmap)

### Completado — Módulo Empleados
- Persona + Empleado (carnet auto, PIN, género switch, cargo buscable)
- Detalle por pestañas: Datos, Direcciones (mapa MapLibre + geolocalización), Contactos, Referencias, Negocios (sector, días venta, venta/día), Activos, Pasivos, Ingresos, Documentos (subida)
- Items clickeables → mismo modal en modo editar (`/actualizar`); `+ Agregar` en encabezado del card
- Portal gestor `/{slug}/portal` (solo cargo "gestor", login carnet+PIN)

### En curso — Módulo Clientes
- Replicar patrón de Empleados: persona tipo CLIENTE + `clientes`
- CRUD, listado, detalle con pestañas + modales + geo + docs
- Cliente NO usa carnet/PIN/gestor (solo datos de crédito y referencias)

### Siguiente — Solicitud de crédito (tenant)
- Form de solicitud por cliente, monto/plazo/destino
- Estados: pendiente → aprobada/rechazada → credito
- Adjuntar documentos, análisis

### Portal cliente
- Login del cliente para ver su crédito/cuotas/pagos

### Pendiente (largo plazo)
- Créditos + planes de pago, Pagos + reversiones, Caja, Reportes, Dashboard por rol

---

## 6.6 Plan de trabajo — 26 Sep 2026 (cobros y caja del gestor)

Todo depende de persistir el plan de pago (`planPagos()` ya existe en `SolicitudController`).

### 0) Base — tabla `cuotas` (pre-requisito)
- `cuotas`: tenant_id, solicitud_id, n, fecha_vence, cuota, interes, capital, saldo_proyectado, pagado (acumulado), estado (PENDIENTE/PARCIAL/PAGADA/VENCIDA), fecha_pago
- Se generan al **Entregar** (`entregarDesembolso`) con el plan aprobado; `codigo_credito` ya existe
- Documentos leen de la tabla si existe (el plan impreso = lo cobrado)

### 1) Registro de abono — `pagos`
- `pagos`: tenant_id, solicitud_id, cuota_id (nullable = abono libre), monto, metodo (EFECTIVO/TRANSFERENCIA), empleado_id (quien cobró), fecha_hora, observacion, estado (ACTIVO/REVERTIDO)
- **Partner**: `GET/POST /creditos/{id}/abonar` — card con saldo, próximas cuotas, form monto+fecha → aplica a cuotas en orden (más vieja primero), excedente → cuota parcial/abono a capital
- **Portal gestor**: sección "Cobros" con sus créditos ACTIVO → abono desde el campo
- Recibo imprimible (reutiliza estilo de `documentos.php`)

### 2) Cálculo de mora
- Tenant: `mora_diaria_pct` en configuración (tasa diaria sobre cuota vencida)
- Al leer un crédito: cuota PENDIENTE/PARCIAL con fecha_vence < hoy → `mora = cuota_pendiente × mora_diaria_pct × días_atraso` (recalculo on-read, sin cron)
- Badge "En mora" en solicitud + chip del dashboard + listado de morosos (para Recuperación)

### 3) Reversiones de pago — `pago_reversiones`
- Flujo con aprobación: gestor/operador solicita (motivo) → supervisor aprueba/rechaza → transacción: pago.estado=REVERTIDO + cuotas liberadas (pagado -= monto, recalcular estado)
- `pago_reversiones`: pago_id, tenant_id, estado, motivo, solicitado_por, aprobado_por, created_at, resuelto_at

### 4) Arqueo de gestores — `arqueos`
- Por gestor y día: `esperado` = Σ abonos del día cobrados por él (+ desembolsos entregados, que salieron de caja); `declarado` lo ingresa el gestor en el portal al cerrar el día; `diferencia` = declarado − esperado
- Partner `/finanzas/arqueo` — lista del día por gestor (esperado/declarado/diferencia/estado) + historial; portal gestor "Cerrar caja"
- Estado: ABIERTO → DECLARADO → CERRADO (partner valida)

### 5) No pago con motivo + promesa — `gestiones_cobro`
- `gestiones_cobro`: tenant_id, solicitud_id, cuota_id, empleado_id, motivo (select: no estaba, sin dinero, enfermedad, otro), promesa_fecha, comentario, created_at
- Portal gestor: botón "No pagó" junto a la cuota → motivo + fecha de promesa
- Se muestra en detalle del crédito y alimenta Recuperación (promesas vencidas → mora de gestión)

### Orden sugerido
0 → 1 → 5 → 2 → 3 → 4 (abonos primero: todo lo demás opera sobre ellos)

---

## 7. Comandos útiles

```bash
# Migraciones
php spark migrate
php spark migrate:rollback
php spark migrate:status

# Seeders
php spark db:seed SecuritySeeder

# Crear componentes
php spark make:controller NombreController
php spark make:model NombreModel
php spark make:migration CreateTabla

# Ver tabla
php spark db:table users
```

> Nota: en este equipo Composer se ejecuta como
> `c:\xampp\php\php.exe C:\xampp\php\composer.phar <comando>`

### Recuperación de MariaDB (crash por apagado brusco — ya pasó 2 veces)

Síntoma: "conexión rechazada" (2002) y el log `mysql\data\mysql_error.log` dice `Aria recovery failed` / `ibdata1 must be writable`.

1. Matar `mysqld` si quedó colgado.
2. En `mysql\data` renombrar a `.old`: `aria_log.00000001`, `aria_log_control`, `*.pid`, y archivos basura `master-*`/`relay-log-*` (residuos corruptos que disparan un slave fantasma).
3. Arrancar: `c:\xampp\mysql\bin\mysqld.exe --defaults-file=c:\xampp\mysql\bin\my.ini` (o botón Start de MySQL en XAMPP).

Prevención: parar MySQL desde XAMPP antes de apagar la PC, o instalarlo como servicio de Windows (XAMPP Control Panel → Service).
