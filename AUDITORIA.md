# AUDITORÍA DEL SISTEMA — CFSI
**Fecha:** 27 Sep 2026 · **Alcance:** núcleo de crédito (solicitud → desembolso → cuotas → pagos → reversión)

```
Casos evaluados:        22
Correctos:              17
Parcialmente correctos:  3
No soportados:           2

Cobertura funcional:    83.7%   (⚠ cuenta como 0.5)

Actualización 27 Sep — Fase 2 implementada y verificada E2E (35/35 PASS en
writable/_test_e2e.php + motor de planes en writable/_test_plan.php):
- Motor de planes multi-tipo: FRANCES / FLAT / ALEMAN / ANTICIPADO
- Períodos de gracia: solo-interés (INTERES) y desplazamiento total (TOTAL)
- Frecuencias: D, DI (sin domingo), S, Q quincena real (día 15 y fin de mes),
  M, P paso personalizado (paso_dias)
- Comisión y seguro opcionales al aprobar (defaults por tenant, descontados
  de la entrega neta)
- Refinanciamiento: solicitud ligada (refinancia_id) → al entregarse liquida
  el crédito anterior con pago INTERNO
- Reestructuración: anula cuotas pendientes (conservan pagado histórico) y
  regenera plan sobre el saldo con numeración continua
- Reversión trazable: pago_aplicaciones desaplica exacto por cuota (fallback
  LIFO para pagos legacy)
- Moneda por tenant en todas las vistas operativas
- Config tenant: tipo de cálculo, comisión %, seguro %, mora %, pronto pago %,
  plazo máx, moneda — todo editable en /configuracion
```

## Matriz de casos

| # | Caso | Veredicto | Detalle técnico |
|---|------|-----------|-----------------|
| 1 | Crédito con cuota fija | ✅ | Plan francés (`SolicitudService::planPagos`), cuota constante, última ajusta saldo |
| 2 | Crédito con cuota variable | ✅ | `tipo_calculo=ALEMAN`: amortización de capital constante, cuota decreciente |
| 3 | Pago semanal/quincenal/mensual | ✅ | `frecuencia` D, DI, S, Q, M + P personalizado; Q usa días 15/fin real |
| 4 | Interés sobre saldo | ✅ | Francés y alemán = interés sobre saldo insoluto por cuota |
| 5 | Interés fijo (flat) | ✅ | `tipo_calculo=FLAT`: interés sobre monto original, Σcapital conservada |
| 6 | Interés anticipado | ✅ | `tipo_calculo=ANTICIPADO`: interés descontado en desembolso (neto = monto − int.) |
| 7 | Mora | ✅ | `tenants.mora_diaria_pct` + devengo post-vencimiento sobre el pendiente (`mora_dev` congela al pagar, `mora` acumula cobrado) |
| 8 | Pagos parciales | ✅ | `cuotas.pagado` + estado PARCIAL; saldo remanente sigue pendiente |
| 9 | Pagos adelantados | ✅ | FIFO cubre cuotas futuras + **pronto pago opcional**: `tenants.pronto_pago_pct` descuenta el interés pendiente al liquidar (`cuotas.descuento`) |
| 10 | Refinanciamiento | ✅ | `POST /creditos/{id}/refinanciar` → solicitud CREADA con `refinancia_id` → al entregarse, `liquidarPorRefinanciamiento` aplica pago INTERNO y marca LIQUIDADO |
| 11 | Reestructuración | ✅ | `POST /creditos/{id}/reestructurar` → anula pendientes (pagado histórico conservado) + plan nuevo sobre saldo (numeración continua) |
| 12 | Créditos grupales | ❌ | **Fuera de alcance** (decisión del producto) — modelo 1:1 cliente↔solicitud |
| 13 | Crédito con garantía | ⚠ | Solo texto en contrato (`documentos.php` menciona avales); sin tabla de garantías ni aval ligado |
| 14 | Crédito sin garantía | ✅ | Flujo por defecto |
| 15 | Varios créditos por cliente | ✅ | Sin restricción; un cliente puede tener N solicitudes/créditos |
| 16 | Diferentes sucursales | ⚠ | `solicitudes.ruta` (varchar libre); no hay entidad sucursal ni cortes por sucursal |
| 17 | Diferentes monedas | ✅ | `tenants.moneda` configurable (C$/US$) — todas las vistas operativas usan `$mon`/`$m2` |
| 18 | Comisiones | ✅ | `tenants.comision_pct` default + `solicitudes.comision` por crédito (editable al aprobar, se descuenta de la entrega) |
| 19 | Seguros | ✅ | `tenants.seguro_pct` default + `solicitudes.seguro` por crédito (igual que comisión) |
| 20 | Períodos de gracia | ✅ | `gracia_meses` + `gracia_tipo`: INTERES = cuotas de solo interés; TOTAL = desplaza todo el plan |
| 21 | Calendarios de pago diferentes | ✅ | `frecuencia=P` con `paso_dias` libre + quincena real (15/fin de mes, no 15 días fijos) |
| 22 | Reversión de pagos | ✅ | Contra-pago negativo → `pago_aplicaciones` desaplica EXACTO por cuota + marca REVERTIDO |

## Áreas pendientes

- ❌ **Crédito grupal** — excluido del alcance del producto.
- ⚠ **Garantías** — solo texto en contratos; sin entidad de garantía ni aval ligado a personas.
- ⚠ **Sucursales** — `ruta` libre sin entidad, cortes ni reportes por sucursal.
- ⚠ **Gestiones de cobro** — motivos de no-pago aún sin tabla `gestiones_cobro`.
- ✅ **Reporte CONAMI** — `/credito/reporte-conami` clasifica cartera A1–D2 por días de atraso con % provisión (tabla `ReporteService::CONAMI`, ajustable), CSV + imprimir. Pendiente: intereses contingentes (C2+ dejan de devengar) y castigos de cartera E.

## Casos críticos (destructivos)

1. ~~**Sobre-pago se pierde silenciosamente**~~ **RESUELTO 27 Sep** — el excedente abona la siguiente cuota (FIFO) y si sobra tras cubrir todo el plan se acumula en `solicitudes.saldo_favor`.
2. ~~**Reversión desaplica por cuota, no por pago**~~ **RESUELTO 27 Sep** — `pago_aplicaciones` registra cuota×monto×tipo (CUOTA/MORA) por pago; la reversión desaplica exactamente lo aplicado. Fallback LIFO solo para pagos legacy sin trazabilidad.
3. ~~**Mora fantasma**~~ **RESUELTO 27 Sep** — `mora_diaria_pct` por tenant; se devenga solo post-vencimiento sobre el pendiente, se congela al pagar la cuota y se cobra con el siguiente pago.
4. ~~**Adelanto sin beneficio**~~ **RESUELTO 27 Sep** — `pronto_pago_pct` opcional por tenant: al liquidar se condona el % configurado del interés aún no cobrado. La reversión restaura la condonación (`descuento` → 0).
5. **`dias_semana` es aproximación** — frecuencia DI usa `4.33 × dias_semana` pagos/mes (plan de cobrador lunes-viernes no calza semanas reales). Mitigado parcialmente: las fechas saltan domingos.
6. ~~**`fecha_primer_pago` sin validación**~~ YA VALIDADA — `SolicitudService` rechaza fechas pasadas al aprobar.
7. ~~**Q = 15 días ≠ quincena real**~~ **RESUELTO 27 Sep** — frecuencia Q usa día 15 y fin de mes reales por calendario.
8. ~~**Pagos aplicables a cuotas ANULADAS**~~ **RESUELTO 27 Sep** — `aprobarPago` solo aplica a cuotas vivas; las anuladas por reestructuración conservan su historial intacto.
9. ~~**Reestructurar no regeneraba plan**~~ **RESUELTO 27 Sep** — `generarCuotas` ignoraba el crédito porque contaba cuotas ANULADAS; ahora solo un plan vigente por crédito.

## Guardas verificadas (lo que sí aguanta)

- Doble aprobación de pago: bloqueada por `estado !== REVISION`.
- Edición de solicitud: solo CONTACTO/CREADA/REVISION → el plan persistido no puede quedar inconsistente.
- Aislamiento multi-tenant: todas las queries filtran `tenant_id` (verificado cruzando tenant 2/3).
- `cuota_id` de pago validada contra el crédito antes de guardar.
- Tasa 0%: degrada a `monto/n` sin división por cero.
- Idempotencia de `generarCuotas` (un solo plan vigente por crédito; ANULADAS no cuentan).
- Rechazo de reversión duplicada (`revierte_id` ya en REVISION/APLICADO).
- Créditos activados antes del 26/9 sin cuotas: `saldoCredito` tiene fallback al plan calculado.
- Reversión multi-pago sobre una cuota: desaplica solo el monto del pago revertido (verificado: cuota queda PARCIAL con el remanente del otro pago).
- Arqueo del gestor: excluye contra-pagos negativos/internos — la reversión no genera saldo negativo en caja.
- Vouchers: pagos REVERTIDO/RECHAZADO no imprimibles desde el portal del gestor (acceso directo bloqueado, redirige a cobros).

## Verificación E2E (writable/_test_e2e.php — 35 asserts, 0 fallos)

- Aprobación hereda tipo de cálculo/comisión/seguro del tenant
- Plan FLAT persistido con Σcapital conservada
- Pago REVISION → APLICADO con trazabilidad `pago_aplicaciones`
- Reversión exacta (cuota desaplicada a su valor previo, original REVERTIDO)
- Reversión parcial multi-pago sobre la misma cuota
- Reestructuración: anula pendientes, plan nuevo sobre saldo, historial intacto
- Refinanciamiento: crea solicitud ligada, aprobación, liquidación del anterior
- Aislamiento tenant (solicitudes y pagos no cruzan)
- Mora devengada post-vencimiento y pronto pago con descuento
