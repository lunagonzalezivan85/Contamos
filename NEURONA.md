# ðŸ§  NEURONA â€” Cerebro del proyecto Contamos

> **Este es el punto de entrada para cualquier trabajo.** Lee este archivo primero,
> luego `docs/CONVENCIONES.md` (cÃ³mo se trabaja) y solo despuÃ©s tocÃ¡ cÃ³digo.
> Todo lo que un agente o humano necesita saber vive aquÃ­ o sale de aquÃ­.

---

## 1. QuÃ© es esto

- **Contamos**: SaaS multi-tenant de microcrÃ©ditos para financieras de ruta (Nicaragua).
- **Stack**: PHP 8 + CodeIgniter 4 + MySQL/MariaDB + Vanilla JS/CSS (sin frameworks).
- **Modelo**: una BD, cada fila de negocio lleva `tenant_id` (FK `tenants.id`).
- **Dos mundos**: `App\Controllers\Partner\*` (portal del tenant, URLs limpias) y
  `App\Controllers\Admin\*` (panel del sistema, prefijo `/admin`). **Nunca se mezclan.**
- **Portal del gestor**: `/{slug}/portal/*` â€” PWA instalable, sesiÃ³n propia
  (`portal_empleado_id`), mÃ³vil-first.

## 2. Mapa de decisiÃ³n rÃ¡pida

| Quieroâ€¦ | Voy aâ€¦ |
|---|---|
| Entender **cÃ³mo se trabaja** | `docs/CONVENCIONES.md` â† **obligatorio siempre** |
| Entender la estructura de carpetas y mÃ³dulos | `ESTRUCTURA.md` |
| Ver quÃ© falta / quÃ© ya cumple el sistema | `AUDITORIA.md` |
| El plan de trabajo vigente | `PLAN_TRABAJO.md` |
| El diseÃ±o visual y patrones UI | `UI_UX.md` + `public/css/app.css` |
| QuÃ© se hizo y cuÃ¡ndo | `CHANGELOG.md` |
| Flujos de navegaciÃ³n | `APPFLOW.md` |
| Deploy al server | `deploy.ps1` / `deploy-watch.ps1` |
| Sync de base de datos | `sync-bd.ps1` + `writable/migraciones_sql/` |

## 3. Reglas que SIEMPRE aplican (resumen de CONVENCIONES)

1. **Capas**: Controller â†’ Service â†’ Model â†’ View. Sin lÃ³gica en vistas ni SQL en controllers.
2. **Tenant**: toda query filtra `tenant_id` â€” nunca del cliente, sale de sesiÃ³n.
3. **SeparaciÃ³n de tecnologÃ­as**: CSS en `public/css/`, JS en `public/js/` â€” nada inline ni `<style>` en vistas.
4. **Montos `DECIMAL(15,2)`**, estados `VARCHAR` mayÃºsculas, fechas `DATE/DATETIME`.
5. **Seguridad**: `esc()` en vistas, Query Builder siempre, CSRF on, `password_hash`, permiso por ruta (`auth:{modulo}.{accion}`).
6. **Tablas nuevas**: `tenant_id` + Ã­ndice `idx_{tabla}_tenant` + `created_at`/`updated_at`/`deleted_at` + `created_by`/`updated_by`. Ãšnicos incluyen `tenant_id`.
7. **Views**: index = lista clickeable (`partials/list.php`); detalle = hero con Volver + acciones. No reinventar.
8. **MÃ¡x ~30 lÃ­neas/mÃ©todo**, nombres descriptivos, DRY â€” si se repite, va a helper/service/clase CSS.

## 4. Reglas de negocio (a no olvidar)

- **Tasa**: `tenants.tasa_interes` es el **tope** â€” el gestor puede bajarla al negociar; el server clampa `min(post, tenant)`. Nunca libre ni mayor.
- **Monto**: mÃ­nimo **1,000**; tope por cliente `clientes.limite_credito` (default **10,000** â€” oficina lo sube en la ficha).
- **Frecuencias**: `D`=30/mes Â· `DI`=4Ã—dÃ­as (sin domingo) Â· `S`=4/mes Â· `Q`=2 quincena real (15/fin mes) Â· `M`=1 Â· `P`=cada `paso_dias`.
- **CÃ¡lculo**: `FRANCES` | `FLAT` | `ALEMAN` | `ANTICIPADO` â€” el default sale de `tenants.tipo_calculo`; el gestor lo hereda, no elige.
- **CrÃ©ditos grupales**: NO se implementan. SÃ­: **refinanciamiento** (nueva solicitud liquida la anterior), **reestructuraciÃ³n** (regenerar cuotas pendientes), **reversiÃ³n exacta** (`pago_aplicaciones`), **mora diaria** (`mora_diaria_pct`), **pronto pago** (`pronto_pago_pct`), **comisiÃ³n + seguro** opcionales por tenant (`comision_pct`, `seguro_pct` cobrados al desembolsar).
- **Arqueo**: cierre diario por gestor (efectivo = inicial + cobros âˆ’ desembolsos).
- **Estados solicitud**: `CONTACTO â†’ CREADA â†’ REVISION â†’ APROBADA â†’ DESEMBOLSO â†’ ACTIVO` (+`LIQUIDADO`, `RECHAZADA`).
- **Pagos**: `registrar â†’ REVISION â†’ aprobar` (aplica cuotas FIFO) `o rechazar`. Nada se aplica en revisiÃ³n.
- **Portal gestor**: asistencia (kiosk carnet+PIN), cartera propia, cobros del dÃ­a, calculadora, plan sugerido (solo visual).

## 5. Infraestructura

### Local
- XAMPP: `http://localhost/cfsi/` Â· DB `cfsi` (MariaDB, sin prefijo)
- Login local: `admin` / `galvin` / `admin-ce` â†’ `admin123` | admin panel: `admin@cfsi.dev / admin123`
- `php spark` desde la raÃ­z del proyecto

### Server (producciÃ³n)
- Host: `contamos.softlutionic.com` Â· site4now (IIS compartido)
- DB remota: `mysql8001.site4now.net` Â· `db_aa03a4_actas` Â· prefijo **`CT_`** (DBPrefix)
- FTP: `ftp://win8166.site4now.net` (host del panel site4now â€” NO el dominio,
  Cloudflare no proxya :21). RaÃ­z FTP = raÃ­z del proyecto.
- Credenciales: `deploy.config.ps1` (local, gitignored â€” ver `deploy.config.ejemplo.ps1`)
- MySQL 5.7 en el server vs MariaDB 10.4 local â€” `bigint(20)` â‰¡ `bigint` (solo display width)

## 6. Ritual de deploy

### Archivos (FTP)
```powershell
.\deploy.ps1            # sube lo cambiado desde el Ãºltimo deploy (.deploy-stamp)
.\deploy.ps1 -Todo      # todo el proyecto
.\deploy-watch.ps1      # auto: cada save sube el archivo
```

### Base de datos â€” **SIEMPRE validar que el server estÃ© al dÃ­a**
```powershell
.\sync-bd.ps1 -Ver      # estado: quÃ© se aplicÃ³, quÃ© falta
.\sync-bd.ps1           # aplica los .sql pendientes de writable/migraciones_sql/
```
**Rutina al cerrar una fase:**
1. El cambio incluye migraciÃ³n PHP â†’ escribir su `.sql` equivalente en `writable/migraciones_sql/AAAA-MM-DD_desc.sql`
2. `.\sync-bd.ps1` (o pedÃ­rselo a Devin â€” lo corro directo desde aquÃ­ con el cliente mysql)
3. Verificar en `sync-bd.ps1 -Ver` que quedÃ³ aplicado
4. Si dudÃ¡s del estado del esquema: comparar `information_schema.COLUMNS` local vs `ct_*` remoto (ver Â§5)

**Nunca** subir ni tocar: `writable/`, `public/uploads/`, `.env`, `vendor/`, `node_modules/`, `tests/`.

## 7. Checklist por tarea (todo agente)

Antes de marcar una tarea como lista:
- [ ] LeÃ­ `docs/CONVENCIONES.md` y seguÃ­ las capas + naming
- [ ] `php -l` en cada `.php` tocado Â· `node --check` en cada `.js`
- [ ] Filtrado por `tenant_id` en queries nuevas
- [ ] Si hubo migraciÃ³n â†’ `.sql` en `writable/migraciones_sql/` + `.\sync-bd.ps1` ejecutado
- [ ] Vista sigue el patrÃ³n (`partials/list.php` / hero detalle) y clases del sistema
- [ ] `esc()` en toda salida Â· CSRF en toda forma Â· permiso `auth:` en toda ruta nueva
- [ ] `CHANGELOG.md` actualizado con la entrada de la fase
- [ ] `AUDITORIA.md` marcada si el Ã­tem ya cumple

## 8. Referencias rÃ¡pidas

| Tema | Archivo |
|---|---|
| Convenciones completas (BD, capas, vistas, seguridad) | `docs/CONVENCIONES.md` |
| Estructura del cÃ³digo y mÃ³dulos | `ESTRUCTURA.md` |
| AuditorÃ­a de funcionalidades (quÃ© falta) | `AUDITORIA.md` |
| Deploy archivos | `deploy.ps1` |
| Sync BD | `sync-bd.ps1` |
| Migraciones PHP | `app/Database/Migrations/` |
| MenÃºs y permisos | `app/Config/Menu.php` + `app/Database/Seeds/SecuritySeeder.php` |

> Si algo de esto cambia (nueva regla, nuevo proceso, nuevo host) â†’ actualizÃ¡ este archivo.
> La neurona es la fuente de verdad: no dupliques reglas en otros docs, linkeÃ¡ acÃ¡.

## 9. Pendientes

### App gestor — ruta de cobro (pedido 2026-10-05)
- Agregar **mapa** en la pantalla de ruta de la app (hoy solo lista; endpoint `connect/ruta` ya devuelve paradas con `lat/lng` — reusar patrón MapLibre/OSRM del portal `public/js/portal/mapa.js`).
- **Rediseñar la lista** de paradas en la app (al usuario no le gusta la versión actual — definir con él: números, orden por cercanía, distancia, botones Waze/Maps).
- **Selector de día** en la app: `connect/ruta?fecha=YYYY-MM-DD` para adelantar cobros — el endpoint ya acepta `?fecha=` (`rutaCobrosHoy(\, \, \)`); falta el UI en la app.
