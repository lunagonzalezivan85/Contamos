# PRD — Product Requirements Document
## CFSI — Sistema de Gestión de Préstamos y Créditos

**Versión:** 1.0 | **Fecha:** 24 Sep 2026 | **Estado:** Borrador

---

## 1. Visión del Producto

CFSI es un sistema web de gestión crediticia para microfinanciera en Nicaragua. Gestiona el ciclo completo del crédito: registro de clientes, solicitud, aprobación, desembolso, cobro (abonos/pagos), recuperación de cartera y reportes operativos/gerenciales.

**Objetivo del rediseño:** migrar la experiencia a un estilo **One UI** (limpio, tarjetas redondeadas, jerarquía visual clara, orientado a una mano en móvil) y agregar la funcionalidad **"Generar Documentación"** en solicitudes de crédito.

---

## 2. Usuarios y Roles

| Rol | idRol | Descripción |
|---|---|---|
| SUPERADMIN | 1 | Acceso total, configuración, menús, usuarios |
| ADMIN | 2 | Gestión completa de créditos y solicitudes |
| SUPERVISOR | 3 | Monitoreo de cartera, gestores, mora |
| CAJA/FINANZAS | 4 | Pagos, arqueo, desembolsos, reversiones |
| PROMOTOR/GESTOR | 5+ | Registro de solicitudes, cobros en ruta, recuperación |
| RRHH | — | Empleados, asistencia |

---

## 3. Alcance Funcional (módulos existentes)

- **Clientes/Personas:** wizard de registro (datos, contactos, direcciones, referencias, negocios, ingresos, activos, garantías, documentos adjuntos)
- **Solicitudes de crédito:** registro, modificación, observación, aprobación, rechazo, desembolso
- **Créditos:** detalle, plan de pago, estado de cuenta, regeneración de abonos, revolvencia, anulación
- **Pagos:** registro, recibo, reporte, reversión (flujo solicitud→aprobación)
- **Recuperación:** cartera en mora por gestor/sucursal
- **Traslados:** reasignación de clientes entre gestores
- **Caja:** arqueo, fondos, gastos, ingresos
- **Reportes:** desembolsos, abonos pendientes, cobros pendientes, mora, estado real de crédito, generador
- **Administración:** usuarios, roles, permisos, menú dinámico, dashboards configurables, backup

---

## 4. Nueva Funcionalidad: Generar Documentación en Solicitudes de Crédito

### 4.1 Problema
Hoy los documentos (contrato, pagaré, plan de pago, recibo de desembolso) se generan dispersos en `Reporte/*` y algunos están incompletos (`credito/contrato.php` casi vacío). El usuario debe navegar a varias pantallas para obtener el paquete documental de una solicitud.

### 4.2 Objetivo
Desde la vista de detalle de la solicitud (`Solicitud/solicitud/{id}`) y/o del listado, el usuario podrá **generar el paquete completo de documentación** de la solicitud/crédito con un solo clic, con opción de seleccionar documentos individuales.

### 4.3 Documentos incluidos
| Documento | Disponibilidad | Fuente de datos |
|---|---|---|
| Solicitud de crédito (ficha) | Siempre | `solicitudcredito` + `vwcliente2` |
| Contrato de crédito | Estado APROBADO+ | `credito` + `configuracion` (plantilla) |
| Pagaré | Estado APROBADO+ | `credito` + `configuracion.pagare` |
| Plan de pago | Desembolsado | `credito` + `abono` |
| Recibo de desembolso | Desembolsado | `credito` + `pagos` |
| Referencias/garantías del cliente | Siempre | `referencias`, `vwgarantiassolicitud2` |

### 4.4 Requerimientos funcionales
- **RF-DOC-1:** Botón "Generar Documentación" en `solicitud/view` (botones flotantes) y en `solicitud/index` (acción por fila).
- **RF-DOC-2:** Modal/panel con checklist de documentos disponibles según estado de la solicitud.
- **RF-DOC-3:** Generación individual (vista imprimible/PDF) o paquete completo.
- **RF-DOC-4:** Salida en PDF (html2pdf o FPDF/TCPDF ya disponibles) e impresión directa.
- **RF-DOC-5:** Registrar en `historialsolicitud` cada generación (auditoría: quién, cuándo, qué documentos).
- **RF-DOC-6:** Plantillas editables desde `configuracion` (ya existe patrón con `pagare`).

### 4.5 Requerimientos no funcionales
- Tiempo de generación < 5s por documento.
- Compatible con impresión carta (Letter) y descarga PDF.
- Permisos: roles 1,2,3,4 pueden generar; gestor solo la ficha de solicitud.

### 4.6 Criterios de aceptación
- Desde el detalle de una solicitud PENDIENTE se puede imprimir la ficha.
- Desde una solicitud APROBADA se generan contrato + pagaré + ficha.
- Desde una solicitud desembolsada se genera el paquete completo (5 documentos).
- Cada generación queda auditada en `historialsolicitud`.

---

## 5. Rediseño One UI (resumen — detalle en UI_UX.md)

- Migrar de AdminLTE visual a un sistema de diseño propio tipo **Samsung One UI**: tarjetas grandes con radio 16-24px, encabezados con título grande + subtítulo, acciones principales en la mitad inferior (alcanzables), espaciado generoso, modo claro con acento `#30CB9A`.
- Mantener `variables.css` como fuente de verdad de tokens.
- Prioridad de rediseño: Dashboard → Solicitudes → Crédito detalle → Pagos → resto de módulos.

---

## 6. Métricas de éxito

- Reducción de clics para obtener documentación: de ~6 pantallas a 1 modal.
- 100% de solicitudes desembolsadas con paquete documental generado.
- Tiempo de capacitación de gestores reducido (UI más simple).
