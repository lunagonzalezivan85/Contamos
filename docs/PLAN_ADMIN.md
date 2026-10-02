# Plan Admin — mejoras por fase

> Objetivo: pipeline comercial completo — lead → contacto → contrato → cobro → suspensión → exporte de datos.
> Reglas vigentes: capas Controller→Service→Model→View, CSS/JS en archivos, Query Builder, CSRF.

## Estado inicial (ya existe)

- `acceso_solicitudes`: nombre, negocio, telefono, correo, codigo, plan_estimado, detalle(JSON sliders), estado (PENDIENTE/CONTACTADO).
- `planes` (SaaS) con precio_mensual + incluidos; `plan_cobro_mes()` calcula sobreconsumo.
- `plan_pagos`: tenant_id, plan_id, periodo (YYYY-MM), monto, metodo, referencia, fecha_pago, estado.
- `tenants.estado` + `POST /admin/tenants/{id}/toggle` (activar/suspender manual).

## Fase 1 — UI: tenants/nuevo + leads agrupados ✅ sin BD

- `admin/tenants/nuevo.php`: rediseño a cards/secciones (Empresa · Crédito · Contacto · Admin), grid ordenado, hints.
- `admin/leads`: chips por estado (Todos · Pendientes · Contactados + conteos), filtro `?estado=`, estilo `.chip` en `admin.css`.
- Archivos: `Views/admin/tenants/nuevo.php`, `Views/admin/leads/index.php`, `LeadsController::index`, `admin.css`, `admin-tenant.js` (si aplica).

## Fase 2 — Modal "Contactando" + selector de plan (sin BD)

- Botón **Contactar** en el lead → modal (`public/js/admin-leads.js` + `.modal-*` en css):
  sliders/inputs de usuarios, clientes, créditos, empleados (precargados del detalle) + **select de plan** (`planes` activos).
- `POST /admin/leads/{id}/contactar` → `LandingService::precioEstimado` con el plan elegido → guarda `plan_estimado`, `plan_id` (agregar campo) y pone estado CONTACTADO.
- Migración: `acceso_solicitudes.plan_id INT NULL`.
- Quita el "Recalcular" inline (el modal lo reemplaza).

## Fase 3 — Contrato de servicio (BD nueva)

- Estados nuevos del lead: `RECHAZADO`, `CONTRATADO`. Chip extra en la lista.
- Tabla `contratos`: tenant_id NULL (se llena al provisionar), lead_id, plan_id, monto, dia_pago (5|10), gracia_dias (4|7|…), cuenta_bancaria (texto), terminos (texto firmado), estado, fecha_inicio, fecha_corte.
- `POST /admin/leads/{id}/contrato` → genera contrato + estado CONTRATADO.
- `GET /admin/contratos/{id}` → vista imprimible con T&C: pago días 5 o 10, periodo de gracia, cuenta bancaria, **cláusula de suspensión por falta de pago** (cierra portal tenant + app gestor), y derecho de datos (exporte tras saldar).
- `POST /admin/leads/{id}/rechazar` → RECHAZADO.
- Vista contrato: HTML con CSS de impresión (`@media print`), botón "Imprimir / Guardar PDF" (sin dompdf).
- Migración `000019` + SQL con prefijo `CT_`.

## Fase 4 — Cobranza SaaS y suspensión automática

- `spark cobros:generar` (comando): crea `plan_pagos` del periodo por tenant con contrato activo (dia_pago/gracia del contrato).
- `spark cobros:morosos` (o el mismo comando): plan_pagos PENDIENTE vencido `dia_pago + gracia_dias` → `tenants.estado = SUSPENDIDO`.
- Suspensión ya cierra partner portal (middleware de tenant activo) + app gestor (login/refresh valida estado — verificar y endurecer si falta).
- Admin: botón "Suspender plan" / "Reactivar" en ficha del tenant (toggle ya existe; etiquetar y confirmar).
- `POST /admin/tenants/{id}/cobrar` ya marca pagado — extender con método `TRANSFERENCIA` + referencia bancaria.
- Cron en server (Task Scheduler/cPanel): `php spark cobros:generar` diario.
- Migración: índice `plan_pagos(tenant_id, periodo)` unique.

## Fase 5 — Exporte de datos del tenant suspendido

- En ficha del tenant SUSPENDIDO: card "Exporte de datos" — muestra `saldo pendiente` (plan_pagos PENDIENTE).
- Flujo: cliente pide sus datos → debe saldar (marcar pagos) → habilita `GET /admin/tenants/{id}/exporte` → **Excel SpreadsheetML** (XML, abre en Excel sin composer) con hojas: Clientes, Créditos (solicitudes+plan), Pagos/Abonos, Saldo.
- Registrar `exporte` en audit_logs con el saldo saldado.
- Sin dependencias nuevas (PhpSpreadsheet pesa para el hosting; SpreadsheetML alcanza).

## Orden de trabajo sugerido

1. Fase 1 (hoy) → deploy.
2. Fase 2 → deploy.
3. Fase 3 (contratos) → SQL + deploy.
4. Fase 4 (cron cobros) → SQL + deploy + programar tarea en server.
5. Fase 5 (exporte) → deploy.

Cada fase cierra con: `deploy.ps1`, `sync-bd.ps1` si hubo migración, entrada en CHANGELOG y marca en AUDITORIA.
