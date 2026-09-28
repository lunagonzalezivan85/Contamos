# APPFLOW — Flujo de la Aplicación CFSI

**Versión:** 1.0 | **Fecha:** 24 Sep 2026

---

## 1. Flujo general del sistema

```
Login (Seguridad)
   │
   ▼
Dashboard (por rol)
   │
   ├──► Clientes/Personas ──► Solicitud de Crédito ──► Aprobación ──► Desembolso
   │                                                              │
   │                                                              ▼
   │                                                    Crédito ACTIVO
   │                                                              │
   │                              ┌───────────────────────────────┤
   │                              ▼                               ▼
   │                         Pagos/Abonos                   Plan de Pago
   │                              │                               │
   │                              ▼                               ▼
   │                    Reversión (si aplica)              Estado de Cuenta
   │
   ├──► Recuperación de cartera (mora)
   ├──► Traslados entre gestores
   ├──► Caja: arqueo, fondos, gastos, ingresos
   └──► Reportes y administración
```

---

## 2. Flujo de Solicitud de Crédito (núcleo)

```
┌──────────────┐
│   Persona2   │  Registro del cliente (wizard: datos, contactos,
│  /registrar  │  direcciones, referencias, negocios, ingresos,
└──────┬───────┘  activos, garantías, documentos)
       │
       ▼
┌──────────────┐   Genera noSolicitud S-YYYY-NNNNN
│  Solicitud/  │   Selecciona cliente, tipo préstamo (catálogo SC),
│  registrar   │   monto, plazo, término (PP), garantía, sucursal
└──────┬───────┘
       │ estado = PENDIENTE
       ▼
┌──────────────┐   Filtros: estado, fechas, sucursal
│  Solicitud/  │   Roles 1,2,4 ven todo; gestor solo las suyas
│    index     │
└──────┬───────┘
       │
       ▼
┌──────────────┐   Vista detalle con botones flotantes:
│  Solicitud/  │   [Aprobar] [Rechazar] [Observación] [Editar]
│ solicitud/id │   ★ NUEVO: [Generar Documentación]
└──────┬───────┘
       │
   ┌───┴────────────┬────────────────┐
   ▼                ▼                ▼
┌────────┐    ┌───────────┐    ┌───────────┐
│Aprobar │    │ Rechazar  │    │Observación│
│(admin) │    │  (admin)  │    │  (admin)  │
└───┬────┘    └─────┬─────┘    └─────┬─────┘
    │               │                │
    │ Define:       │ estado=        │ estado=
    │ montoAprobado │ RECHAZADO      │ OBSERVACION
    │ plazo, fondo, │ + historial    │ + historial
    │ tipoDesembolso│                │ (vuelve a editar)
    │ fecha1erAbono │
    │ estado=APROBADO
    ▼
┌──────────────┐   Crea registro en `credito` (CC-YYYY-NNNNN)
│  Solicitud/  │   Genera tabla `abono` (plan de pagos)
│ Desembolsar  │   Si es représtamo: cancela crédito anterior
│              │   estado solicitud = ACTIVO
└──────┬───────┘
       ▼
   Crédito ACTIVO → ciclo de cobros
```

### Estados de solicitud
`PENDIENTE → APROBADO → ACTIVO (desembolsado)`
`PENDIENTE → OBSERVACION → MODIFICADO → PENDIENTE`
`PENDIENTE → RECHAZADO` (terminal)

---

## 3. Flujo de Pagos

```
Credito/detalle ──► Pagos (registrar pago)
                        │
                        ▼
                  Recibo imprimible (Reporte/Recibo)
                        │
              ┌─────────┴──────────┐
              ▼                    ▼
        Pagos/reporte       Reversion/solicitar
        (listado + filtros)       │
                                  ▼
                          Reversion/index → ver
                                  │
                        ┌─────────┴─────────┐
                        ▼                   ▼
                    aprobar             rechazar
                    (_aplicarReversion  (observación
                     transaccional)      obligatoria)
```

---

## 4. Flujo NUEVO: Generar Documentación

```
Solicitud/solicitud/{id}
        │
        ▼  [botón "Generar Documentación"]
┌─────────────────────┐
│  Modal / vista      │  Checklist según estado:
│  documentacion      │  ☐ Ficha de solicitud   (siempre)
│                     │  ☐ Contrato             (APROBADO+)
│                     │  ☐ Pagaré               (APROBADO+)
│                     │  ☐ Plan de pago         (ACTIVO)
│                     │  ☐ Recibo desembolso    (ACTIVO)
│                     │  ☐ Referencias/garantías(siempre)
│  [Generar PDF] [Imprimir] [Paquete completo]
└─────────┬───────────┘
          │
          ▼
  Solicitud/doc{Tipo}/{id}  →  vista imprimible (layout reporte.php)
          │
          ▼
  Auditoría: INSERT historialsolicitud
  (estado='DOCUMENTACION', observaciones='docs generados')
```

---

## 5. Flujos secundarios

### Recuperación de cartera
`Recuperacion/index (admin) | Recuperacion/empleado` → filtros por sucursal/gestor → clientes en mora → registrar gestión / no-pago

### Traslados
`Traslados/trasladar` → selector Origen → Destino → checkbox de clientes → confirmar

### Caja
`Arqueo` (apertura/cierre) → `Pagos` del día → `Gastos`/`Ingreso` → cierre y reporte

### Administración
`MenuManager` (menú dinámico BD) → `Rol` (permisos por árbol) → `Usuario` → `Dashboards` (constructor por rol)

---

## 6. Mapa de navegación por rol

| Módulo | SUPERADMIN | ADMIN | SUPERVISOR | CAJA | GESTOR |
|---|---|---|---|---|---|
| Dashboard | ✓ | ✓ | ✓ | ✓ | ✓ |
| Clientes | ✓ | ✓ | ✓ | — | ✓ |
| Solicitudes | ✓ | ✓ | ver | — | crear/ver propias |
| Aprobar/Rechazar | ✓ | ✓ | — | — | — |
| Desembolsar | ✓ | ✓ | — | ✓ | — |
| **Generar Documentación** | ✓ | ✓ | ✓ | ✓ | solo ficha |
| Pagos | ✓ | ✓ | ver | ✓ | registrar |
| Reversiones | ✓ | ✓ | ✓ | ✓ | solicitar |
| Recuperación | ✓ | ✓ | ✓ | — | ✓ |
| Caja/Arqueo | ✓ | — | — | ✓ | — |
| Reportes | ✓ | ✓ | ✓ | parcial | — |
| Admin (usuarios/menú) | ✓ | — | — | — | — |
