# Convenciones — Nueva Estructura CFSI (CI4 + Multi-tenant)

**Versión:** 1.0 | **Fecha:** 24 Sep 2026
**Aplica a:** nueva base de datos `cfsi` y código CodeIgniter 4

---

## 1. Base de datos

### Naming

| Elemento | Convención | Ejemplo |
|---|---|---|
| Tablas | `snake_case`, plural, español | `solicitudes`, `creditos`, `pagos` |
| Columnas | `snake_case`, español | `created_at`, `monto_aprobado` |
| PK | `id` BIGINT UNSIGNED AUTO_INCREMENT | `id` |
| FK | `{tabla_singular}_id` | `tenant_id`, `user_id`, `solicitud_id` |
| Índices | `idx_{tabla}_{columna}` | `idx_users_email` |
| Únicos | `uq_{tabla}_{columna}` | `uq_users_tenant_email` |
| FKs constraints | `fk_{tabla}_{ref}` | `fk_users_tenants` |

### Columnas estándar (todas las tablas de negocio)

```sql
id          BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY
tenant_id   BIGINT UNSIGNED NOT NULL          -- multi-tenant
created_at  DATETIME NULL
updated_at  DATETIME NULL
deleted_at  DATETIME NULL                     -- soft delete
created_by  BIGINT UNSIGNED NULL              -- auditoría
updated_by  BIGINT UNSIGNED NULL
```

### Reglas multi-tenant

- **Toda tabla de negocio lleva `tenant_id`** con FK a `tenants.id`
- Tablas globales (sin tenant): `tenants`, `permissions` (catálogo de permisos del sistema)
- `tenant_id` siempre indexado: `idx_{tabla}_tenant`
- Constraints únicos de negocio incluyen `tenant_id`: `uq_users_tenant_email (tenant_id, email)`
- El `tenant_id` se resuelve en el login y se inyecta vía `TenantScope` en los models — **nunca viene del cliente**

### Tipos de datos

| Dato | Tipo |
|---|---|
| Montos | `DECIMAL(15,2)` — nunca FLOAT/DOUBLE |
| Estados | `VARCHAR(20)` o ENUM corto en MAYÚSCULAS: `ACTIVO`, `PENDIENTE` |
| Fechas | `DATE` / `DATETIME` (nunca VARCHAR) |
| Textos largos | `TEXT` |
| Booleanos | `TINYINT(1)` |
| Charset | `utf8mb4` / `utf8mb4_unicode_ci` |

---

## 2. Reglas de desarrollo (obligatorias)

### Separación de tecnologías — SIN excepciones

| Regla | Correcto | Prohibido |
|---|---|---|
| **CSS** | Archivos `.css` en `public/css/` | `<style>` en vistas, `style=""` inline |
| **JS** | Archivos `.js` en `public/js/` | `<script>` en vistas, `onclick=""` inline |
| **PHP** | Lógica y renderizado de vistas | CSS/JS/SQL embebidos |
| **SQL** | Models con Query Builder | SQL en controllers o concatenado |
| **JSON/config** | Archivos de config o respuestas API | Bloques JSON dentro de PHP/HTML |

### Calidad de código

- **Nada de código espagueti:** un método = una responsabilidad; máx ~30 líneas por método
- **Controllers delgados:** solo validan input, llaman services, retornan respuesta
- **Sin lógica en vistas:** las vistas solo renderizan datos ya preparados
- **Nombres descriptivos:** `$solicitudesPendientes`, no `$data2` ni `$x`
- **Sin código muerto:** no dejar funciones/comentarios `// por si acaso`
- **DRY:** si se repite 2+ veces, va a un helper/service/clase CSS

### Organización de assets

```
public/
├── css/
│   ├── app.css          # layout principal + componentes compartidos
│   ├── auth.css         # login, cambiar password, recuperación
│   ├── landing.css      # landing pública
│   └── {modulo}.css     # estilos por módulo
├── js/
│   ├── app.js           # comportamiento global
│   └── {modulo}.js      # JS por módulo
└── img/
```

Las vistas referencian con `base_url('css/archivo.css')` y `base_url('js/archivo.js')`.

---

## 3. Código PHP (CI4)

| Elemento | Convención | Ejemplo |
|---|---|---|
| Controllers | `PascalCase`, singular | `SolicitudController` |
| Models | `PascalCase` + `Model` | `SolicitudModel` |
| Services | `PascalCase` + `Service` | `SolicitudService` |
| Métodos | `camelCase` | `findByTenant()` |
| Rutas | `kebab-case` | `/solicitudes/{id}/documentacion` |

### Capas

```
Controller  → solo HTTP: valida input, llama service, retorna vista/JSON
Service     → lógica de negocio, transacciones, orquesta models
Model       → solo acceso a datos (CI4 Model con $allowedFields)
```

**Prohibido:** SQL en controllers, lógica de negocio en models, `$this->request` en models.

### Seguridad obligatoria

- Passwords: `password_hash()` / `password_verify()` — nunca texto plano
- Queries: siempre Query Builder o bindings `?` — nunca concatenación
- CSRF: habilitado globalmente (`security` filter)
- Autorización: `AuthFilter` + verificación de permiso por ruta
- Salidas: `esc()` en todas las vistas

---

## 3. Estructura de carpetas

```
app/
├── Controllers/
│   ├── Admin/         # panel del sistema (superadmin)
│   ├── Partner/       # operación del tenant
│   └── Shared/        # compartidos: auth, perfil, landing
├── Services/
│   ├── Admin/         # lógica del panel de sistema
│   ├── Partner/       # lógica de negocio del tenant
│   └── Shared/        # servicios compartidos (AuthService)
├── Models/            # acceso a datos
├── Filters/           # AuthFilter, AdminFilter
├── Views/
│   ├── admin/         # vistas del panel de sistema
│   ├── partner/       # vistas del tenant
│   ├── shared/        # auth, perfil (compartidas)
│   ├── landing/       # pública
│   └── layouts/       # admin.php | partner.php
├── Database/
│   ├── Migrations/
│   └── Seeds/
└── Config/            # Menu.php define menús sistema/tenant
```

**Regla:** un cambio en `Admin/` nunca afecta `Partner/` y viceversa. Lo compartido va en `Shared/`.

---

## 4. Módulo Seguridad — Tablas

| Tabla | Propósito | tenant_id |
|---|---|---|
| `tenants` | Empresas/organizaciones | — (es la raíz) |
| `roles` | Roles por tenant | sí (NULL = rol de sistema) |
| `users` | Usuarios | sí |
| `permissions` | Catálogo de permisos | no (global) |
| `role_permissions` | Permisos por rol | sí |
| `menus` | Menú dinámico | sí |
| `role_menus` | Menús visibles por rol | sí |
| `password_reset_tokens` | Recuperación de contraseña | sí |
| `login_attempts` | Auditoría de intentos de login | sí |
| `audit_logs` | Auditoría general de acciones | sí |

---

## 5. Patrón de vistas — Listado y Detalle

Todas las vistas **index** usan la lista One UI clickeable, y todas las de **detalle** el hero con Volver + acciones. Usar los parciales — no reescribir el HTML.

### Listado — `partials/list.php`

```php
<?= view('partials/list', ['items' => $items]) ?>
// $items = [['icono','titulo','subtitulo','meta','url'], ...]
```

- Fila = icono + título + subtítulo + meta + chevron → navega a `url`
- Clases: `.oui-list`, `.oui-list-item`, `.oui-list-icon`, `.oui-list-body/title/sub`, `.oui-list-meta`, `.oui-list-chevron`

### Detalle — `partials/detail_hero.php`

```php
<?= view('partials/detail_hero', [
    'titulo'   => 'Nombre',
    'subtitulo'=> 'Descripción',
    'icono'    => 'user',
    'volver'   => '/socios/clientes',
    'acciones' => [['nombre','url','icono'], ...],
]) ?>
```

- Botón **Volver** → `volver` (o `history.back()` si se omite)
- **Dropdown "Acciones"** → lista de `$acciones` (toggle en `app.js`)
- Clases: `.detail-hero`, `.btn-volver`, `.dropdown`, `.dropdown-toggle`, `.dropdown-menu`, `.dropdown-item`

### Iconos — `icon_helper.php`

`<?= icon('user', 18) ?>` — SVG Feather-style. Nombres disponibles: home, settings, users, user, user-check, credit-card, file-text, file-plus, briefcase, bar-chart(-2), dollar-sign, archive, clipboard, refresh-cw, percent, tool, plus-circle, list, activity, alert-circle, minus-circle, menu, lock, shield, chevron-down/right, arrow-up/left, bell, x, check, log-out.
