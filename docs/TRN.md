# TRN — Technical Requirements / Requerimientos Técnicos
## CFSI — Sistema de Gestión de Préstamos y Créditos

**Versión:** 1.0 | **Fecha:** 24 Sep 2026

---

## 1. Stack Tecnológico Actual

| Capa | Tecnología |
|---|---|
| Backend | PHP 7.x/8.x — CodeIgniter 3 |
| BD | MariaDB 10.4 (`creditmanagment`) |
| Servidor | XAMPP / Apache |
| Frontend | AdminLTE 3, Bootstrap 4, jQuery, FontAwesome |
| Plugins JS | DataTables, Select2, SweetAlert2, Toastr, Leaflet, html2pdf, printThis, Summernote |
| PDF | FPDF (`app/Libraries/fpdf.php`), TCPDF (`app/Libraries/tcpdf/`), html2pdf (cliente) |
| Sesión | `$_SESSION['auth']` con `idRol`, `empleado`, `userName` |

---

## 2. Convenciones del Proyecto

### Controladores
- Heredan `CI_Controller`, cargan `Global_model` como `gm` y helper `Auth`
- Constructor verifica `isLogin()` → redirect `Seguridad`
- Constructor carga notificaciones POPUP a sesión (patrón repetido — candidato a `MY_Controller`)
- Respuestas HTML vía `$data['_view']` + `layouts/main`; reportes vía `reporte.php` con `encabezado`/`contenido`

### Acceso a datos
- `Global_model::set_table()` + `get/get_all/add/update/delete/query/queyBuilder`
- Listados usan **vistas SQL** (`vw*2`), no tablas base
- Filtros: strings WHERE concatenados → **migrar a query bindings** (`$this->db->where()` con escaping)

### Vistas
- CSS por módulo en `resources/css/{modulo}.css`, cargado vía `$data['styles']`
- JS por módulo en `resources/libraries/*.js`, cargado vía `$data['libraries']`
- Tokens de diseño centralizados en `resources/css/variables.css`

---

## 3. Requerimientos Técnicos — Rediseño One UI

- **TR-UI-1:** Crear `resources/css/oneui.css` (o `oneui/` con partials) que consuma `variables.css` y defina el sistema de diseño: cards, tipografía, espaciados, botones, inputs, tabs, modales, bottom-action-bar.
- **TR-UI-2:** No romper AdminLTE: el tema nuevo convive; se aplica por módulo vía `$data['styles']`.
- **TR-UI-3:** Componentes como clases CSS reutilizables (`.oui-card`, `.oui-btn`, `.oui-header`, `.oui-fab`, `.oui-sheet`) para no reescribir HTML masivamente.
- **TR-UI-4:** Responsive mobile-first; acciones principales en la mitad inferior de pantalla.
- **TR-UI-5:** Mantener paleta: primario `#30CB9A`, secundario `#2E3542`.

---

## 4. Requerimientos Técnicos — Generar Documentación

### 4.1 Arquitectura propuesta

```
Solicitud/documentacion/{id}     → vista selector (checklist de documentos)
Solicitud/docFicha/{id}          → ficha de solicitud (imprimible)
Solicitud/docContrato/{id}       → contrato
Solicitud/docPagare/{id}         → pagaré
Solicitud/docPlanPago/{id}       → plan de pago
Solicitud/docRecibo/{id}         → recibo de desembolso
Solicitud/docPaquete/{id}        → todos en una sola salida (secciones + page-break)
```

Alternativa: controlador dedicado `Documentacion.php` si se prefiere no inflar `Solicitud`.

### 4.2 Implementación
- Reutilizar layout `reporte.php` (`encabezado` + `contenido`) ya usado por `Reporte/planPago`, `Pagare`, `Recibo`.
- Nuevas vistas en `app/Views/solicitud/docs/` (o `credito/docs/`): `ficha.php`, `contrato.php` (completar la existente casi vacía), `pagare.php`, `plan_pago.php`, `recibo_desembolso.php`, `paquete.php`.
- PDF: html2pdf (cliente, ya usado en `Pagare`) para mantener consistencia; FPDF/TCPDF como fallback servidor.
- Auditoría: insert en `historialsolicitud` con `estado='DOCUMENTACION'` y `observaciones` = lista de documentos generados.
- Disponibilidad por estado:
  - `PENDIENTE/OBSERVACION/MODIFICADO` → ficha
  - `APROBADO` → ficha + contrato + pagaré
  - `ACTIVO` (desembolsado) → paquete completo
- Permisos: roles 1,2,3,4 todo; gestor solo ficha.

### 4.3 Datos
- `solicitudcredito` + `vwcliente2` + `vwgarantiassolicitud2` + `referencias` + `vwdireccionpersona2` + `contacto`
- `credito` + `abono` + `pagos` (post-desembolso)
- `configuracion` (empresa, dirección, teléfono, plantilla `pagare`, futura plantilla `contrato`)
- `numbertToText` para montos en letras — **corregir bugs de miles/millones antes de usarlo en contratos**

---

## 5. Deuda técnica a abordar (priorizada)

| Prioridad | Ítem |
|---|---|
| Alta | Inyección SQL en filtros concatenados → query bindings |
| Alta | `numbertToText` incorrecto en miles/millones |
| Media | Consolidar Credito/Credito2/Credito10; MenuManager x4; Persona/Persona2 |
| Media | `Rms_model` sin `.php` |
| Media | `credito/contrato.php` casi vacío → completar como parte de Generar Documentación |
| Baja | Archivos debug/test en raíz → mover o eliminar |
| Baja | Notificaciones en cada constructor → `MY_Controller` o hook |

---

## 6. Entorno y despliegue

- Desarrollo: `c:\xampp\htdocs\cfsi` (Windows + XAMPP)
- Producción: `https://sistema2.jvcreators.com` (`config['base_url']`)
- BD: `creditmanagment.sql` como respaldo de esquema; vistas `vw*.sql` en raíz
- Versionado de assets: `?v=".date("s")` (cache-busting por segundo — considerar hash de archivo)
