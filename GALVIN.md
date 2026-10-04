# Instrucciones para Galvin — colaborador Contamos

## Antes de tocar nada (orden obligatorio)

1. **`NEURONA.md`** — fuente de verdad del proyecto: dónde va cada proyecto, cómo se
   conectan (Partner / Admin / Portal gestor / app) y qué reglas aplican.
2. **`docs/CONVENCIONES.md`** — capas, BD, vistas, seguridad.
3. **`AGENTS.md`** — reglas no negociables y rutina de deploy/sync.

## Cómo reportar un error

Todo reporte de bug debe incluir estos 4 puntos:

1. **URL del error** — la ruta exacta donde ocurre
   (ej. `/{slug}/portal/solicitudes`, `/admin/leads/5`, `/{slug}/solicitudes/nueva`).
2. **Portal afectado** — indicar en cuál ocurre. Si pasa en varios, listar TODOS:
   - **Portal de Tenant** — panel partner `/{slug}/...`
   - **Portal Administrativo** — `/admin/...`
   - **Portal de Gestores** — `/{slug}/portal/*` y app móvil (`/connect`)
   - **Portal de Supervisores** — *(próximamente)*
3. **Explicación correcta del error** — qué hace el sistema, qué debería hacer y
   pasos para reproducirlo.
4. **Cómo corregir** — propuesta de solución. Si es un **cálculo matemático no
   contemplado** (amortización, frecuencias, plazos decimales, mora, pronto pago,
   refinanciamiento, etc.), indicar:
   - la **fórmula esperada** con un ejemplo numérico,
   - el **caso concreto** donde falla (montos, plazo, frecuencia, tipo de cálculo),
   - el resultado actual vs. el esperado.

## Pruebas — validar MCPs instalados

Antes de trabajar, verificar que el entorno tenga los **MCP de pruebas**
instalados:

- **Playwright** — pruebas E2E de los portales.
- **Vercel** — deploys/previews cuando aplique.

Si no están instalados → instalarlos primero, para que un **agente de test**
pueda ejecutar las pruebas sobre cada cambio.

## Documentación obligatoria

- **Toda corrección o feature se documenta en `CHANGELOG.md`** — entrada de la
  fase, la más reciente va arriba.
- Si hubo cambio de esquema → `.sql` en `writable/migraciones_sql/` y avisar para
  correr `.\sync-bd.ps1`.

## Credenciales del server — ⚠️ BORRAR ESTA SECCIÓN TRAS COPIARLAS

> Copiá estos datos a tu máquina (config local / notas seguras) y **eliminá esta
> sección completa del archivo antes de commitear**. Es solo para que tengás los
> accesos la primera vez — no deben quedar escritos en el repo.

**FTP — subida de archivos (`deploy.ps1`)**

| Dato | Valor |
|---|---|
| Host | `win8166.site4now.net` |
| Usuario | `ftpcontamos` |
| Clave | `$Easy2023` |
| Carpeta remota | `/contamos` |

**MySQL remoto — sync de esquema (`sync-bd.ps1`)**

| Dato | Valor |
|---|---|
| Host | `mysql8001.site4now.net` |
| Usuario | `aa03a4_actas` |
| Clave | `easy2023` |
| Base de datos | `db_aa03a4_actas` |
| Prefijo de tablas | `CT_` |

Ojo: el server corre **MySQL 5.7** (local es MariaDB 10.4) — `bigint(20)` ≡
`bigint`, solo cambia el display width. Credenciales **locales** (XAMPP, DB
`cfsi` sin prefijo): `admin` / `galvin` / `admin-ce` → `admin123`; panel admin
`admin@cfsi.dev / admin123`.
