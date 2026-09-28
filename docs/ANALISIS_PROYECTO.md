# Análisis del Proyecto CFSI (Sistema de Gestión de Préstamos y Créditos)

**Fecha:** 24 Sep 2026
**Framework:** CodeIgniter 3 (PHP) sobre XAMPP / MariaDB
**Base de datos:** `creditmanagment`
**URL producción:** `https://sistema2.jvcreators.com`

---

## 1. Arquitectura General

El proyecto sigue el patrón **MVC clásico de CodeIgniter 3**:

```
app/
├── Controllers/   (49 controladores)
├── Models/        (5 modelos — se usa principalmente Global_model genérico)
├── Views/         (~188 vistas organizadas por módulo)
├── Helpers/       (auth_helper, utilities_helper)
├── Libraries/     (FPDF, TCPDF, Report)
├── Config/        (configuración CI3)
└── hooks/         (hooks de timezone, etc.)
resources/
├── css/           (46 hojas de estilo por módulo + variables.css)
├── libraries/     (JS por módulo: request.js, credito.js, prestamo.js...)
├── plugins/       (AdminLTE, DataTables, Select2, SweetAlert2, Leaflet...)
└── dist/          (AdminLTE theme)
```

### Patrón de acceso a datos

No hay un modelo por entidad. Se usa **`Global_model`** como DAO genérico:

- `set_table($table)` — cambia la tabla activa
- `get($id)`, `get_all($params, $filter, $order_by, $asc)` — lectura
- `add($params)`, `update($id, $params)`, `delete($id)` — escritura
- `query($sql, $return)` — SQL crudo
- `queyBuilder($sp, $params)` — llamada a stored procedures con limpieza de resultados múltiples (mysqli)

Los filtros se construyen como **strings SQL concatenados** en los controladores (ej. `"estado = '".$estado."'"`), lo cual es un **riesgo de inyección SQL** a documentar como deuda técnica.

### Patrón de vistas

- Layout principal: `app/Views/layouts/main.php` (AdminLTE 3 + Bootstrap 4)
- Cada controlador pasa `$data['_view']` y `$this->load->view('layouts/main', $data)`
- Reportes imprimibles usan `app/Views/reporte.php` con `encabezado` + `contenido`
- Sidebars por rol en `app/Views/sidebars/` + sidebar dinámico desde BD (`render_sidebar_menu()` en `utilities_helper`)

### Autenticación y autorización

- `auth_helper::isLogin()` verifica `$_SESSION['auth']`
- Roles por `idRol`: 1=SUPERADMIN, 2=ADMIN, 3=SUPERVISOR, 4=CAJA/FINANZAS, otros=empleados/gestores
- Menú dinámico desde tablas `menu` + `permiso` (rol 1 ve todo)
- Cada controlador carga notificaciones POPUP en el constructor

---

## 2. Modelos

| Modelo | Propósito |
|---|---|
| `Global_model` | DAO genérico usado por ~todo el sistema |
| `Cem_model` | Catálogo específico (pequeño) |
| `Cps_model` | Catálogo específico (pequeño) |
| `Mhs_model` | Catálogo específico (pequeño) |
| `Rms_model` | Catálogo específico (sin extensión .php — posible bug) |

**Observación:** `Rms_model` no tiene extensión `.php`, probablemente no carga.

---

## 3. Controladores (mapa funcional)

### Núcleo de crédito
- **`Solicitud`** (1078 líneas) — ciclo de solicitud: `index`, `registrar`, `Modificar`, `aprobar`, `Desembolsar`, `solicitud` (vista detalle), `Rechazar`, `Observacion`, `save`, `GenerarAbonos`, `redistribucionPagos`, `simularPlanPago`
- **`Credito`** (1042 líneas) — gestión de créditos activos, `RegenerarAbonos`, `_generarTablaAbonos`
- **`Credito2` / `Credito10`** — versiones alternas/legacy del flujo de crédito (contrato, desembolso, plan de pago, estado de cuenta, revolvencia)
- **`Pagos`** — registro de pagos, recibos, reversiones, reporte de pagos
- **`Reversion`** — flujo completo de reversión de pagos (solicitar → aprobar/rechazar/cancelar) con transacciones
- **`Abono`** — gestión de abonos/cuotas
- **`Prestamos`** — módulo alterno de préstamos

### Personas y cartera
- **`Persona` / `Persona2`** — wizard de registro de persona (datos, contactos, direcciones, referencias, negocios, ingresos, activos, garantías, **documentos**)
- **`Cliente`**, **`Empleado`**, **`MasterCliente`** — fichas y recuperación de cartera
- **`Gestor`** — panel del gestor de cobro (clientes, registrar pago, no-pago, recuperaciones)
- **`Supervisor`** — cartera por gestor, clientes en mora, cobros del día
- **`Recuperacion`** — recuperación de cartera (admin y empleado)
- **`Traslados`** — traslado de clientes entre empleados/rutas

### Administración
- **`Seguridad`** — login/logout
- **`Usuario`**, **`Rol`**, **`Menu`**, **`MenuManager`** (+ variantes Temp/simple/Test) — seguridad y menú dinámico
- **`Dashboard` / `Dashboards`** — dashboards y constructor de dashboards por rol
- **`Reporte`** — centro de reportes: planPago, Contrato, Recibo, Pagare, noPago, Generador, desembolsos, abonosPendientes, moracliente, cobrosPendientes, EstadoRealCredito, generate_pdf
- **`Arqueo`**, **`Fondos`**, **`Gastos`**, **`Ingreso`** — caja y finanzas
- **`Catalogo`**, **`localidad`**, **`Configuracion`**, **`Backup`**, **`Asistencia`**, **`Tracking`**, **`Mensaje`**, **`Api`**, **`Crr`**, **`Cem`/`Cps`/`Mhs`/`Rms`**

**Deuda técnica:** existen controladores duplicados/legacy (`Credito2`, `Credito10`, `MenuManagerTemp`, `MenuManager_simple`, `MenuTest`, `Persona` vs `Persona2`) que conviene consolidar.

---

## 4. Vistas

Organizadas por módulo. Las más relevantes:

- `solicitud/` — index, registro, view (detalle con botones flotantes), aprobar, modificar, rechazar, observacion
- `credito/` — index, detalle (perfil con panel lateral), estadoCuenta, planPago, contrato, recibo, desembolsar, revolvencia, regenerar_abonos
- `pagos/` — reporte, recibos, reversiones (solicitar/aprobar/ver/reporte)
- `persona2/` — wizard de registro completo
- `Reporte/` — plantillas imprimibles (header, header_recibo, formato)
- `sidebars/` — un sidebar por rol + `dynamic_sidebar.php`
- `layouts/main.php` — layout AdminLTE con panel de notificaciones deslizante

---

## 5. Helpers

### `auth_helper.php`
- `isLogin()` — verificación de sesión
- `numbertToText($numero)` — número a letras (implementación incompleta: miles/millones mal calculados, usa división entera incorrecta)
- `formatar_valor_abreviado($valor)` — 1.5K, 2M, etc.

### `utilities_helper.php`
- `registrarPago($ci)` — renderiza vista parcial de pago
- `date2CustomFormat($fecha, $formato)` — fechas en español (locale es_NI)
- `toManagua($datetime)` — conversión de timezone a America/Managua
- `render_sidebar_menu($roleId)` — menú dinámico desde BD con conexión aislada (evita "commands out of sync")
- `build_menu_tree()` / `build_sidebar_html()` — árbol de menú recursivo

---

## 6. Base de datos

> **Estructura real descargada de producción** el 24/09/2026 → `database/estructura_online.sql` + `database/sp_online.sql`
> Servidor: `mysql8010.site4now.net` | BD: `db_aa03a4_easycas`
> **57 tablas + 43 vistas + 25 stored procedures**. El archivo local `creditmanagment.sql` (2023) está **desactualizado**.
> BD local `creditmanagment` replicada con datos semilla (`database/seed_local.sql`).

### Stored Procedures (25)

**Créditos/pagos:** `sp_cred_GetCreditList`, `SP_CREDITO_LISTAR_TODO`, `sp_ConsultarEstadoCreditos`, `sp_ObtenerEstadoCreditos`, `ObtenerInformacionCredito`, `sp_pay_GetCreditInstallmentsReport`, `sp_pay_GetPaymentList`, `sp_registros_pagos`, `kardex_pagos`, `sp_pagos_retrasados`, `sp_obtener_pagos_pendientes`, `sp_ConsultarCobrosPendientes`, `sp_obtenerDesembolsos`

**Cartera/mora:** `sp_obtener_clientes_mora`, `SP_OBTENER_MONTOS_A_RECUPERAR_POR_EMPLEADO`, `SP_OBTENER_MONTOS_CARTERA_POR_EMPLEADO`, `SP_OBTENER_MONTOS_PENDIENTES`

**CRM/rutas:** `sp_crm_GetCustomerList`, `sp_crm_GetAvailableGuarantees`, `sp_crm_GetMasterCustomerRoute`, `sp_crm_SyncMasterRouteOne`, `sp_clientes_por_empleados`

**Otros:** `Dashboard2`, `registrarAsistencia`, `SP_GetAllAsistencia`

### Tablas (57)

**Núcleo crediticio:** `solicitudcredito`, `credito`, `abono`, `pagos`, `pago_reversion`, `historialsolicitud`, `nopago`, `garantias`, `detallegarantia`, `creditos_revisados_para_su_migracion`

**Personas:** `persona`, `cliente`, `empleado`, `usuario`, `contacto`, `direccion`, `direccionpersona`, `direccionsucursal`, `referencias`, `negocios`, `activos`, `ingresosegresos`, `documentacion` (docs subidos por persona: ruta, titulo, fechaVencimiento), `masterclientes`

**Caja/finanzas:** `arqueo`, `detallearqueo`, `fondos`, `gastos`, `ingresos`, `moneda`, `periodopago`, `metas`, `payroll`, `productos`

**Organización:** `sucursal`, `localidad`, `rutas` (implícita), `detalleruta`, `traslado`, `asistencia`, `feriados`, `catalogo`, `configuracion`, `campos`, `formulario`, `rutinas`

**Sistema:** `menu`, `permiso`, `rol`, `dashboards`, `dashboard_components`, `dashboard_role`, `notificacion`, `logs`, `tracking`, `cem`, `cps` (implícita), `flm`, `pci`

### Vistas (43)

**Operativas:** `vwcliente2`, `vwcredito2`, `vwsolicitud2`, `vwempleado2`, `vwusuario`, `vwmenu2`, `vwdireccionpersona2`, `vwgarantiassolicitud2`, `vwgarantias2`, `vwnegocios`, `vwabonos`, `vwpagos2`, `vwprestamo`, `vwarqueo2`, `vwmastercliente2`, `vwmastercreditoempleado`

**Reportes (rpt*):** `rptcredito`, `rptdesembolso`, `rptdesembolso_por_gestor`, `rptmatrizcrediticia`, `rptsolicitudes`

**Análisis:** `vw_saldo_cartera`, `vw_kardex_pago`, `vwclientesenmora2`, `vwcobrosdeldia2`, `vwcreditosvencidos`, `vwabonosretrasados`, `vwmatrizpago2`, `vwrecaudo`, `vwrecuperacionporfecha2`, `vwprestamospagosactuales`, `vwtasacambio`, `vwtrackingusuario`, `vw_empleados_sin_usuarios`, `vw_usuario_activos2`

### Tablas principales (detalle)

| Tabla | Rol |
|---|---|
| `solicitudcredito` | Solicitudes: noSolicitud, estado, montos, plazo, terminoPago, garantía, sucursal, empleado, campos de aprobación (montoAprobado, idFondo, deduccion, gastosFormalizacion, montoSeguro...) |
| `credito` | Créditos desembolsados: noCredito, estado, montos, plazo, fechaVencimiento |
| `abono` | Cuotas planificadas: fechaAbono, montoAbono, montoRecibido, montoPendiente, estado |
| `pagos` | Pagos recibidos con referencia, método, saldoAnterior |
| `pago_reversion` | Solicitudes de reversión con auditoría completa |
| `historialsolicitud` | Auditoría de cambios de estado de solicitud |
| `persona` / `cliente` / `empleado` / `usuario` | Actores del sistema |
| `catalogo` | Catálogo jerárquico multiuso (tipos de préstamo SC, periodos PP, garantías GT, sexo, rutas, denominaciones...) |
| `menu` / `permiso` / `rol` | Menú dinámico y permisos por rol |
| `sucursal`, `fondos`, `configuracion` | Configuración organizacional |
| `negocios`, `activos`, `ingresosegresos`, `referencias`, `direccionpersona`, `contacto` | Expediente del cliente |
| `notificacion`, `logs`, `tracking` | Soporte |

**Vistas SQL:** `vwcliente2`, `vwcredito2`, `vwsolicitud2`, `vwempleado2`, `vwdireccionpersona2`, `vwgarantiassolicitud2`, `vwclientesrutas` — los controladores consultan vistas, no tablas base, para listados.

---

## 7. Frontend / UI actual

- **Base:** AdminLTE 3 + Bootstrap 4 + jQuery + FontAwesome
- **Plugins:** DataTables, Select2, SweetAlert2, Toastr, Leaflet (mapas), html2pdf, printThis, Summernote, daterangepicker
- **CSS propio:** 46 archivos por módulo en `resources/css/`, todos consumen `variables.css`
- **Paleta oficial (logo CFSI):**
  - Primario `#30CB9A` (verde), claro `#5DD9B3`, oscuro `#28A880`
  - Secundario `#2E3542` (gris oscuro), oscuro `#1A202C`
- **Patrones UI ya establecidos:** stats cards, filtros inline, botones flotantes de acción, exportar Excel, imprimir, tabs estilizadas, wizard con pasos clickeables

---

## 8. Generación de documentos (estado actual)

| Documento | Método | Mecanismo |
|---|---|---|
| Plan de pago | `Reporte/planPago` | Vista imprimible `credito/planPago` |
| Contrato | `Reporte/Contrato`, `Credito10/Contrato` | Vista `credito/contrato` (649 bytes — casi vacía) |
| Recibo | `Reporte/Recibo` | Vista `credito/recibo` |
| Pagaré | `Reporte/Pagare` | html2pdf + plantilla desde `configuracion.pagare` |
| Desembolso | `Solicitud/Desembolsar` | Vista `credito/desembolsar` |
| PDF genérico | `Reporte/generate_pdf` | Librería `Report` (FPDF) — esqueleto de ejemplo |

**Librerías disponibles:** FPDF (`app/Libraries/fpdf.php`), TCPDF (`app/Libraries/tcpdf/`), html2pdf (JS), printThis (JS).

**Brecha:** no existe una funcionalidad unificada de "Generar Documentación" en solicitudes de crédito que emita el paquete completo (solicitud, contrato, pagaré, plan de pago, recibo de desembolso) desde un solo lugar.

---

## 9. Riesgos y deuda técnica

### Críticos (seguridad)
- **Login con inyección SQL:** `Seguridad::login()` concatena `ci_mail`/`ci_pass` directo en el WHERE (`"userName='".$email."' and userPass='".$pass."'"`) — bypass de autenticación trivial
- **Contraseñas en texto plano:** `userPass` se compara sin hash; hay flag hardcodeado `userPass=="12345"`
- **CSRF deshabilitado:** `$config['csrf_protection'] = FALSE`
- **`encryption_key` vacío** en `config.php`
- **Cookies inseguras:** `cookie_secure=FALSE`, `cookie_httponly=FALSE`, `sess_match_ip=FALSE`, `sess_regenerate_destroy=FALSE`
- **Sin control de acceso por método:** solo `isLogin()` en constructor; la vista oculta botones pero `Solicitud/aprobar`, `Desembolsar`, etc. no verifican rol en el POST — acceso directo por URL
- **Archivos debug/test en raíz pública:** `debug_*.php`, `test_*.php`, `check_paths.php`, `info*.txt` expuestos en producción

### Altos
- **Inyección SQL generalizada:** filtros concatenados como strings en casi todos los controladores
- **`numbertToText` con bugs** en miles/millones (división entera incorrecta)
- **`Desembolsar` sin transacción:** crea crédito + abonos + cancela crédito anterior sin atomicidad (solo `Reversion` usa transacciones)
- **`base_url` hardcodeado a producción** (`sistema2.jvcreators.com`) — rompe dev local

### Medios
- **Controladores duplicados** (Credito/Credito2/Credito10, MenuManager x4, Persona/Persona2)
- **`Rms_model` sin extensión .php** — no carga
- **Vista `credito/contrato.php` casi vacía** (649 bytes)
- **Cache-busting `?v=".date("s")`** — invalida cache del navegador cada segundo
- **Sesiones en archivos** con `sess_save_path=NULL`

### Bajos
- **Mezcla de idiomas** en nombres (español/inglés)
- **Notificaciones en cada constructor** — candidato a `MY_Controller` o hook
- **XSS potencial:** salidas sin `htmlspecialchars` en varias vistas
