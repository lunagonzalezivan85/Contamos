# UI/UX — Sistema de Diseño One UI para CFSI

**Versión:** 1.0 | **Fecha:** 24 Sep 2026

---

## 1. Filosofía One UI aplicada a CFSI

Inspirado en Samsung One UI: interfaz limpia, contenido en la parte superior para **ver**, acciones en la parte inferior para **tocar**. Ideal para gestores que usan el sistema en móvil/tablet en campo.

### Principios
- **Ver arriba, actuar abajo:** encabezados grandes y datos en la mitad superior; botones de acción en la mitad inferior (alcanzables con el pulgar).
- **Tarjetas grandes y redondeadas:** radio 16–24px, sombras suaves, fondo gris claro.
- **Jerarquía tipográfica fuerte:** título XL + subtítulo descriptivo, menos ruido visual.
- **Una acción primaria por pantalla:** botón principal destacado con el verde CFSI.
- **Espaciado generoso:** padding 20–28px en cards, gaps de 16px+.

---

## 2. Tokens de Diseño

Extender `resources/css/variables.css`:

```css
:root {
    /* Paleta CFSI (existente) */
    --color-primary: #30CB9A;
    --color-primary-light: #5DD9B3;
    --color-primary-dark: #28A880;
    --color-secondary: #2E3542;
    --color-secondary-dark: #1A202C;

    /* One UI — superficies */
    --oui-bg: #F5F6F8;              /* fondo de app */
    --oui-surface: #FFFFFF;         /* tarjetas */
    --oui-surface-2: #FAFBFC;       /* tarjetas anidadas */

    /* One UI — radios más grandes */
    --oui-radius-card: 20px;
    --oui-radius-btn: 14px;
    --oui-radius-pill: 999px;
    --oui-radius-input: 12px;

    /* One UI — tipografía */
    --oui-font: 'Segoe UI', 'Roboto', system-ui, sans-serif;
    --oui-title-xl: 28px / 700;     /* título de pantalla */
    --oui-title-lg: 20px / 600;     /* título de card */
    --oui-body: 14px / 400;
    --oui-caption: 12px / 500;      /* labels, uppercase opcional */

    /* One UI — espaciado */
    --oui-space-xs: 8px;
    --oui-space-sm: 12px;
    --oui-space-md: 20px;
    --oui-space-lg: 28px;

    /* One UI — sombras suaves */
    --oui-shadow-card: 0 2px 12px rgba(46,53,66,.06);
    --oui-shadow-fab: 0 6px 20px rgba(48,203,154,.35);
}
```

---

## 3. Componentes

### 3.1 Header de pantalla (`.oui-header`)
```
┌─────────────────────────────────┐
│  Solicitudes                    │  ← título XL (28px/700)
│  Gestiona las solicitudes...    │  ← subtítulo gris
│                    [buscar] [+] │  ← acciones a la derecha
└─────────────────────────────────┘
```
- Título grande, subtítulo descriptivo, sin breadcrumb visual pesado.

### 3.2 Card (`.oui-card`)
- Fondo blanco, radio 20px, sombra suave, padding 20–24px.
- Variantes: `.oui-card--stat` (stats), `.oui-card--list` (filas), `.oui-card--form`.

### 3.3 Stats cards (`.oui-stat`)
- Icono en círculo de color suave + valor grande + label.
- Ya existe el patrón en reportes rediseñados — estandarizar.

### 3.4 Botones
| Clase | Uso |
|---|---|
| `.oui-btn` | base: radio 14px, padding 12px 20px, font 600 |
| `.oui-btn--primary` | verde `#30CB9A`, acción principal |
| `.oui-btn--dark` | gris `#2E3542`, acción secundaria |
| `.oui-btn--ghost` | borde/fondo transparente |
| `.oui-btn--pill` | radio completo (chips, filtros) |

### 3.5 FAB / barra de acciones inferior
- Reemplazar los botones flotantes actuales (`btn-float` en `solicitud/view`) por una **bottom action bar** en móvil y FABs en desktop.
- Acción primaria a la derecha, destructiva con confirmación SweetAlert2.

### 3.6 Inputs (`.oui-input`)
- Radio 12px, borde `#E9ECEF`, fondo `#FAFBFC`, focus con borde/sombra verde.
- Labels encima del campo, caption 12px.

### 3.7 Tablas → listas
- Desktop: tabla limpia con filas hover suaves, header caption uppercase.
- Móvil: transformar a cards apiladas (patrón ya usado en `cliente-index` con 3 vistas).

### 3.8 Modal → Bottom sheet
- En móvil, modales como **bottom sheet** (`.oui-sheet`): sube desde abajo, radio superior 24px, handle de arrastre.
- Desktop: modal centrado con radio 20px.

### 3.9 Chips de estado
- Pills con color suave de fondo + texto del color fuerte:
  - PENDIENTE: fondo `#FEF3E2`, texto `#F39C12`
  - APROBADO/ACTIVO: fondo `#E5F9F2`, texto `#28A880`
  - RECHAZADO: fondo `#FDECEA`, texto `#E74C3C`
  - OBSERVACION: fondo `#E8F4FD`, texto `#3498DB`

---

## 4. Layout

### Desktop
- Sidebar colapsable oscuro `#2E3542` (existente) con items redondeados y activo en verde.
- Contenido: fondo `#F5F6F8`, max-width 1280px, padding 24px.

### Móvil
- Sidebar → drawer; acciones principales en bottom bar fija.
- Headers compactos con título grande que colapsa al hacer scroll (efecto One UI).

---

## 5. Plan de migración por módulos

| Fase | Módulo | Notas |
|---|---|---|
| 1 | `oneui.css` base + Dashboard | tokens + componentes core |
| 2 | Solicitudes (index, view, registro, aprobar) | incluye botón Generar Documentación |
| 3 | Crédito detalle + plan de pago | ya tiene layout perfil — adaptar |
| 4 | Pagos + Reversiones | ya rediseñados — migrar clases |
| 5 | Clientes/Personas wizard | pasos como progress One UI |
| 6 | Resto (caja, reportes, admin) | incremental |

**Estrategia:** coexistencia — cada módulo carga `oneui.css` además de su CSS actual; se migran clases gradualmente sin romper AdminLTE.

---

## 6. Accesibilidad

- Contraste mínimo AA (verde `#30CB9A` sobre blanco solo para elementos grandes/bold; texto en `#28A880`).
- Targets táctiles ≥ 44px.
- Focus visible en todos los interactivos.
- `data-tooltip` existente → mantener en FABs.
