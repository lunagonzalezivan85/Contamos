# 🧠 NEURONA — Cerebro del proyecto Contamos

> **Este es el punto de entrada para cualquier trabajo.** Lee este archivo primero,
> luego `docs/CONVENCIONES.md` (cómo se trabaja) y solo después tocá código.
> Todo lo que un agente o humano necesita saber vive aquí o sale de aquí.

---

## 1. Qué es esto

- **Contamos**: SaaS multi-tenant de microcréditos para financieras de ruta (Nicaragua).
- **Stack**: PHP 8 + CodeIgniter 4 + MySQL/MariaDB + Vanilla JS/CSS (sin frameworks).
- **Modelo**: una BD, cada fila de negocio lleva `tenant_id` (FK `tenants.id`).
- **Dos mundos**: `App\Controllers\Partner\*` (portal del tenant, URLs limpias) y
  `App\Controllers\Admin\*` (panel del sistema, prefijo `/admin`). **Nunca se mezclan.**
- **Portal del gestor**: `/{slug}/portal/*` — PWA instalable, sesión propia
  (`portal_empleado_id`), móvil-first.

## 2. Mapa de decisión rápida

| Quiero… | Voy a… |
|---|---|
| Entender **cómo se trabaja** | `docs/CONVENCIONES.md` ← **obligatorio siempre** |
| Entender la estructura de carpetas y módulos | `ESTRUCTURA.md` |
| Ver qué falta / qué ya cumple el sistema | `AUDITORIA.md` |
| El plan de trabajo vigente | `PLAN_TRABAJO.md` |
| El diseño visual y patrones UI | `UI_UX.md` + `public/css/app.css` |
| Qué se hizo y cuándo | `CHANGELOG.md` |
| Flujos de navegación | `APPFLOW.md` |
| Deploy al server | `deploy.ps1` / `deploy-watch.ps1` |
| Sync de base de datos | `sync-bd.ps1` + `writable/migraciones_sql/` |

## 3. Reglas que SIEMPRE aplican (resumen de CONVENCIONES)

1. **Capas**: Controller → Service → Model → View. Sin lógica en vistas ni SQL en controllers.
2. **Tenant**: toda query filtra `tenant_id` — nunca del cliente, sale de sesión.
3. **Separación de tecnologías**: CSS en `public/css/`, JS en `public/js/` — nada inline ni `<style>` en vistas.
4. **Montos `DECIMAL(15,2)`**, estados `VARCHAR` mayúsculas, fechas `DATE/DATETIME`.
5. **Seguridad**: `esc()` en vistas, Query Builder siempre, CSRF on, `password_hash`, permiso por ruta (`auth:{modulo}.{accion}`).
6. **Tablas nuevas**: `tenant_id` + índice `idx_{tabla}_tenant` + `created_at`/`updated_at`/`deleted_at` + `created_by`/`updated_by`. Únicos incluyen `tenant_id`.
7. **Views**: index = lista clickeable (`partials/list.php`); detalle = hero con Volver + acciones. No reinventar.
8. **Máx ~30 líneas/método**, nombres descriptivos, DRY — si se repite, va a helper/service/clase CSS.

## 4. Reglas de negocio (a no olvidar)

- **Tasa**: `tenants.tasa_interes` es el **tope** — el gestor puede bajarla al negociar; el server clampa `min(post, tenant)`. Nunca libre ni mayor.
- **Monto**: mínimo **1,000**; tope por cliente `clientes.limite_credito` (default **10,000** — oficina lo sube en la ficha).
- **Frecuencias**: `D`=30/mes · `DI`=4×días (sin domingo) · `S`=4/mes · `Q`=2 quincena real (15/fin mes) · `M`=1 · `P`=cada `paso_dias`.
- **Cálculo**: `FRANCES` | `FLAT` | `ALEMAN` | `ANTICIPADO` — el default sale de `tenants.tipo_calculo`; el gestor lo hereda, no elige.
- **Créditos grupales**: NO se implementan. Sí: **refinanciamiento** (nueva solicitud liquida la anterior), **reestructuración** (regenerar cuotas pendientes), **reversión exacta** (`pago_aplicaciones`), **mora diaria** (`mora_diaria_pct`), **pronto pago** (`pronto_pago_pct`), **comisión + seguro** opcionales por tenant (`comision_pct`, `seguro_pct` cobrados al desembolsar).
- **Arqueo**: cierre diario por gestor (efectivo = inicial + cobros − desembolsos).
- **Estados solicitud**: `CONTACTO → CREADA → REVISION → APROBADA → DESEMBOLSO → ACTIVO` (+`LIQUIDADO`, `RECHAZADA`).
- **Pagos**: `registrar → REVISION → aprobar` (aplica cuotas FIFO) `o rechazar`. Nada se aplica en revisión.
- **Portal gestor**: asistencia (kiosk carnet+PIN), cartera propia, cobros del día, calculadora, plan sugerido (solo visual).

## 5. Infraestructura

### Local
- XAMPP: `http://localhost/cfsi/` · DB `cfsi` (MariaDB, sin prefijo)
- Login local: `admin` / `galvin` / `admin-ce` → `admin123` | admin panel: `admin@cfsi.dev / admin123`
- `php spark` desde la raíz del proyecto

### Server (producción)
- Host: `contamos.softlutionic.com` · site4now (IIS compartido)
- DB remota: `mysql8001.site4now.net` · `db_aa03a4_actas` · prefijo **`CT_`** (DBPrefix)
- FTP: `ftp://win8166.site4now.net` (host del panel site4now — NO el dominio,
  Cloudflare no proxya :21). Raíz FTP = raíz del proyecto.
- Credenciales: `deploy.config.ps1` (local, gitignored — ver `deploy.config.ejemplo.ps1`)
- MySQL 5.7 en el server vs MariaDB 10.4 local — `bigint(20)` ≡ `bigint` (solo display width)

## 6. Ritual de deploy

### Archivos (FTP)
```powershell
.\deploy.ps1            # sube lo cambiado desde el último deploy (.deploy-stamp)
.\deploy.ps1 -Todo      # todo el proyecto
.\deploy-watch.ps1      # auto: cada save sube el archivo
```

### Base de datos — **SIEMPRE validar que el server esté al día**
```powershell
.\sync-bd.ps1 -Ver      # estado: qué se aplicó, qué falta
.\sync-bd.ps1           # aplica los .sql pendientes de writable/migraciones_sql/
```
**Rutina al cerrar una fase:**
1. El cambio incluye migración PHP → escribir su `.sql` equivalente en `writable/migraciones_sql/AAAA-MM-DD_desc.sql`
2. `.\sync-bd.ps1` (o pedírselo a Devin — lo corro directo desde aquí con el cliente mysql)
3. Verificar en `sync-bd.ps1 -Ver` que quedó aplicado
4. Si dudás del estado del esquema: comparar `information_schema.COLUMNS` local vs `ct_*` remoto (ver §5)

**Nunca** subir ni tocar: `writable/`, `public/uploads/`, `.env`, `vendor/`, `node_modules/`, `tests/`.

## 7. Checklist por tarea (todo agente)

Antes de marcar una tarea como lista:
- [ ] Leí `docs/CONVENCIONES.md` y seguí las capas + naming
- [ ] `php -l` en cada `.php` tocado · `node --check` en cada `.js`
- [ ] Filtrado por `tenant_id` en queries nuevas
- [ ] Si hubo migración → `.sql` en `writable/migraciones_sql/` + `.\sync-bd.ps1` ejecutado
- [ ] Vista sigue el patrón (`partials/list.php` / hero detalle) y clases del sistema
- [ ] `esc()` en toda salida · CSRF en toda forma · permiso `auth:` en toda ruta nueva
- [ ] `CHANGELOG.md` actualizado con la entrada de la fase
- [ ] `AUDITORIA.md` marcada si el ítem ya cumple

## 8. Referencias rápidas

| Tema | Archivo |
|---|---|
| Convenciones completas (BD, capas, vistas, seguridad) | `docs/CONVENCIONES.md` |
| Estructura del código y módulos | `ESTRUCTURA.md` |
| Auditoría de funcionalidades (qué falta) | `AUDITORIA.md` |
| Deploy archivos | `deploy.ps1` |
| Sync BD | `sync-bd.ps1` |
| Migraciones PHP | `app/Database/Migrations/` |
| Menús y permisos | `app/Config/Menu.php` + `app/Database/Seeds/SecuritySeeder.php` |

> Si algo de esto cambia (nueva regla, nuevo proceso, nuevo host) → actualizá este archivo.
> La neurona es la fuente de verdad: no dupliques reglas en otros docs, linkeá acá.
