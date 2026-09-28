---
description: Agente 1 — Auditor/Validador del motor de crédito (re-ejecutar tras cada feature)
---

# Agente 1 — Auditoría "¿puede operar como microfinanciera?"

Re-ejecutable tras cada cambio. Produce/actualiza `AUDITORIA.md` y `catalogo-capacidades.json`.

## Pasos

1. **Inventario técnico** (leer, no modificar):
   - Schema real: `mysql -u root cfsi -e "DESCRIBE solicitudes; DESCRIBE cuotas; DESCRIBE pagos; DESCRIBE tenants;"`
   - Motor de plan: `app/Services/Partner/SolicitudService.php::planPagos` (frecuencias, amortización, iP, pasoDias)
   - Pagos: `app/Services/Partner/PagoService.php` (registrarPago, aprobarPago, revertirPago, cumplio)
   - Modelos: `SolicitudModel` (estados, frecuencias), `CuotaModel`, `PagoModel`, `TenantModel`
   - Validaciones: `SolicitudController::{editar,actualizar,aprobar,desembolsar}`, `PagoController`, `AuthFilter` (permisos por tenant)
   - Defaults del tenant: `/configuracion` (tasa_interes, plazo_meses_max, moneda)

2. **Evaluar la checklist estándar** (mantener sincronizada con AUDITORIA.md):
   cuota fija/variable · frecuencias D/DI/S/Q/M + irregulares · interés saldo/flat/anticipado ·
   mora · parciales · adelantados · refinanciamiento · reestructuración · grupal ·
   garantía/sin garantía · multi-crédito · sucursales · monedas · comisiones · seguros ·
   gracia · calendarios personalizados

3. **Casos destructivos** — buscar combinaciones que rompan, ej.:
   - Sobre-pago > saldo del plan (¿excedente a favor?)
   - Reversión cuando una cuota fue cubierta por 2+ pagos
   - Doble aprobación / doble reversión / revertir un contra-pago
   - fecha_primer_pago en el pasado · tasa 0% · plazo > plazo_meses_max
   - Editar solicitud tras generar cuotas · pagos.tipo=PROMESA mal usado
   - Cuotas de crédito pre-existente sin plan persistido (saldoCredito fallback)

4. **Salida**:
   - Actualizar tabla de casos y contadores en `AUDITORIA.md` (✅ / ⚠ / ❌, cobertura = (✅ + 0.5⚠)/total)
   - Actualizar `catalogo-capacidades.json` — es el contrato que consume el Agente 2
   - Listar casos críticos con archivo/línea de evidencia

5. **Restricción**: el auditor NUNCA modifica código de la app ni la BD de producción; solo lee.
