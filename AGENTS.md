# Instrucciones para agentes — Contamos

> ⚠️ **Orden obligatorio de lectura antes de trabajar:**
> 1. `NEURONA.md` — el cerebro del proyecto (mapa, reglas, deploy, checklist)
> 2. `docs/CONVENCIONES.md` — cómo se trabaja (BD, capas, vistas, seguridad)
> 3. Solo entonces tocá código.

Contamos es SaaS multi-tenant (CodeIgniter 4 + MySQL). Dos mundos: `Partner/` (portal
del tenant) y `Admin/` (panel del sistema) — nunca se mezclan. Portal gestor en
`/{slug}/portal/*` (PWA, sesión propia).

## Reglas que no se negocian

- **Capas**: Controller → Service → Model → View. Sin lógica en vistas, sin SQL en controllers.
- **`tenant_id` en TODA query** — sale de sesión, nunca del cliente.
- **CSS/JS en archivos** (`public/css/`, `public/js/`) — nada inline ni `<style>` en vistas.
- `esc()` en salidas · Query Builder siempre · CSRF on · `password_hash()` · permiso `auth:{mod}.{accion}` por ruta.
- Patrón de vistas: index = lista clickeable, detalle = hero + acciones. No reinventar.
- Máx ~30 líneas/método · nombres descriptivos · DRY.

## Reglas de negocio clave (resumen)

- Tasa `tenants.tasa_interes` = **tope** — el gestor puede bajarla, el server clampa.
- Monto mínimo **1,000**; tope por cliente `limite_credito` (default 10,000).
- Frecuencias: D=30/mes · DI=4×días (sin domingo) · S=4/mes · Q=quincena real · M=1.
- Tipo de cálculo heredado del tenant (`tipo_calculo`): FRANCES/FLAT/ALEMAN/ANTICIPADO.
- Sin créditos grupales. Sí: refinanciamiento, reestructuración, reversión exacta, mora, pronto pago, comisión/seguro opcionales.

## Deploy + sync de BD — rutina obligatoria

Al cerrar una fase probada:

1. **Archivos**: `.\deploy.ps1` (o `deploy-watch.ps1` para auto-upload al guardar)
2. **Esquema BD — VALIDAR SIEMPRE que el server esté al día**:
   - Si hubo migración → escribir `.sql` en `writable/migraciones_sql/` y `.\sync-bd.ps1`
   - Si hay duda del estado → `.\sync-bd.ps1 -Ver` o comparar `information_schema.COLUMNS`
     local vs remoto (prefijo `CT_`, MySQL 5.7 vs MariaDB 10.4 — `bigint(20)` ≡ `bigint`)
3. **`CHANGELOG.md`**: entrada de la fase · **`AUDITORIA.md`**: marcar lo que ya cumple

Nunca subir: `writable/`, `public/uploads/`, `.env`, `vendor/`, `node_modules/`, `tests/`.

## Credenciales locales

`admin` / `galvin` / `admin-ce` → `admin123` (DB local `cfsi`, sin prefijo).
Panel admin: `admin@cfsi.dev / admin123`.

## Más detalle → `NEURONA.md` (fuente de verdad del proyecto)
