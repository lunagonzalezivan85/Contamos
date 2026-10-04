<?php

use CodeIgniter\Router\RouteCollection;

/** @var RouteCollection $routes */

// ---------------------------------------------------------------------------
// Públicas — Shared
// ---------------------------------------------------------------------------
$routes->get('/', 'Shared\LandingController::index');
$routes->get('descargar', 'Shared\LandingController::descargar');                // página de descarga de la app
$routes->get('descargar/apk', 'Shared\LandingController::apk');                  // última versión del APK
$routes->get('descargar/apk/(:segment)', 'Shared\LandingController::apk/$1');    // versión puntual
$routes->post('solicitar-acceso', 'Shared\LandingController::solicitarAcceso');
$routes->get('alta',  'Shared\LandingController::alta');                          // registro + calculadora de plan
$routes->post('alta', 'Shared\LandingController::altaStore');                     // recibe el lead con su plan estimado

// Autenticación
$routes->get('login', 'Shared\AuthController::login');
$routes->post('login', 'Shared\AuthController::attempt');
$routes->get('logout', 'Shared\AuthController::logout');

// Portal del negocio — landing pública + login de gestor (/{slug}/portal[/login])
$routes->get('(:segment)/portal',        'Partner\PortalController::index/$1');   // landing
$routes->get('(:segment)/portal/manifest.webmanifest', 'Partner\PortalController::manifest/$1'); // PWA
$routes->get('(:segment)/portal/login',  'Partner\PortalController::login/$1');    // form login gestor
$routes->post('(:segment)/portal/login', 'Partner\PortalController::entrar/$1');   // valida carnet+PIN

// Kiosco de asistencia — público, marcación por carnet+PIN (/{slug}/asistencia)
$routes->get('(:segment)/asistencia',    'Partner\AsistenciaController::kiosco/$1');
$routes->get('(:segment)/asistencia/pin-longitud', 'Partner\AsistenciaController::pinLongitud/$1');
$routes->post('(:segment)/asistencia',   'Partner\AsistenciaController::marcar/$1');
$routes->get('(:segment)/portal/suspendida', 'Partner\PortalController::suspendida/$1'); // plan vencido
$routes->get('(:segment)/portal/horario', 'Partner\PortalController::horario/$1');     // fuera de horario laboral
$routes->get('(:segment)/portal/panel',  'Partner\PortalController::panel/$1');    // app del gestor
$routes->get('(:segment)/portal/perfil', 'Partner\PortalController::perfil/$1');   // perfil del gestor
$routes->get('(:segment)/portal/salir',  'Partner\PortalController::salir/$1');
$routes->get('resolve/(:segment)',            'Partner\ConnectController::resolve/$1');   // código empresa → tenant
$routes->options('resolve/(:segment)',        'Partner\ConnectController::opciones');
$routes->options('(:segment)/connect',        'Partner\ConnectController::opciones');     // app IONIC — CORS
$routes->options('(:segment)/connect/(:any)', 'Partner\ConnectController::opciones');
$routes->get('(:segment)/connect',            'Partner\ConnectController::index/$1');     // handshake (valida URL)
$routes->post('(:segment)/connect/login',     'Partner\ConnectController::login/$1');     // carnet + PIN → token
$routes->get('(:segment)/connect/notificaciones', 'Partner\ConnectController::notificaciones/$1'); // avisos del gestor

// App IONIC — servicios del gestor (Bearer token)
$routes->get('(:segment)/connect/home',        'Partner\ConnectController::home/$1');           // resumen del día
$routes->get('(:segment)/connect/cartera',     'Partner\ConnectController::cartera/$1');        // clientes del gestor
$routes->get('(:segment)/connect/cliente/(:num)', 'Partner\ConnectController::cliente/$1/$2');  // ficha del cliente
$routes->post('(:segment)/connect/cliente/(:num)/documento', 'Partner\ConnectController::subirDocumento/$1/$2'); // expediente
$routes->post('(:segment)/connect/cliente/(:num)/datos',     'Partner\ConnectController::datosCliente/$1/$2');   // persona básica
$routes->post('(:segment)/connect/cliente/(:num)/dato/(:segment)', 'Partner\ConnectController::agregarDatoCliente/$1/$2/$3'); // dirección, contacto, negocio...
$routes->post('(:segment)/connect/cliente/(:num)/dato/(:segment)/(:num)/eliminar', 'Partner\ConnectController::eliminarDatoCliente/$1/$2/$3/$4');
$routes->get('(:segment)/connect/cliente/(:num)/analisis',   'Partner\ConnectController::analisisCliente/$1/$2'); // métricas + nivel
$routes->post('(:segment)/connect/cliente/(:num)/analisis',  'Partner\ConnectController::calcularAnalisisCliente/$1/$2');
$routes->get('(:segment)/connect/cobros',      'Partner\ConnectController::cobros/$1');         // cuotas por cobrar
$routes->post('(:segment)/connect/cobros/(:num)/abonar', 'Partner\ConnectController::abonarCobro/$1/$2'); // cobro en campo
$routes->get('(:segment)/connect/cobros/(:num)/recibo',  'Partner\ConnectController::reciboCobro/$1/$2'); // voucher
$routes->get('(:segment)/connect/ruta',        'Partner\ConnectController::ruta/$1');           // paradas del día (GPS)
$routes->get('(:segment)/connect/desembolsos', 'Partner\ConnectController::desembolsos/$1');    // por entregar
$routes->post('(:segment)/connect/desembolsos/(:num)/entregar', 'Partner\ConnectController::entregarDesembolso/$1/$2');
$routes->post('(:segment)/connect/solicitud',  'Partner\ConnectController::crearSolicitud/$1'); // nueva solicitud
$routes->get('(:segment)/connect/solicitud/(:num)',  'Partner\ConnectController::verSolicitud/$1/$2');    // detalle para editar
$routes->post('(:segment)/connect/solicitud/(:num)', 'Partner\ConnectController::editarSolicitud/$1/$2'); // solo CREADA|REVISION
$routes->get('(:segment)/connect/actividad',   'Partner\ConnectController::actividad/$1');      // solicitudes del gestor
$routes->get('(:segment)/connect/arqueo',      'Partner\ConnectController::arqueo/$1');         // "Mi caja"
$routes->get('(:segment)/connect/simulador',   'Partner\ConnectController::simulador/$1');      // plan de cuotas
$routes->get('(:segment)/portal/solicitar',    'Partner\PortalController::solicitar/$1');           // público — solicitar crédito
$routes->post('(:segment)/portal/solicitar',   'Partner\PortalController::guardarSolicitar/$1');
$routes->get('(:segment)/portal/solicitud',    'Partner\PortalController::solicitud/$1');
$routes->post('(:segment)/portal/solicitud',   'Partner\PortalController::guardarSolicitud/$1');
$routes->get('(:segment)/portal/solicitud/(:num)/editar',  'Partner\PortalController::editarSolicitud/$1/$2');   // solo CREADA|REVISION
$routes->post('(:segment)/portal/solicitud/(:num)/editar', 'Partner\PortalController::guardarEdicionSolicitud/$1/$2');
$routes->get('(:segment)/portal/desembolso',   'Partner\PortalController::desembolso/$1');   // desembolsos de su cartera
$routes->post('(:segment)/portal/desembolso/(:num)/entregar', 'Partner\PortalController::entregarDesembolso/$1/$2');
$routes->get('(:segment)/portal/recuperacion', 'Partner\PortalController::recuperacion/$1');
$routes->get('(:segment)/portal/cobros',       'Partner\PortalController::cobros/$1');     // cobros de su cartera
$routes->get('(:segment)/portal/mapa',         'Partner\PortalController::mapa/$1');       // ruta de cobro en mapa
$routes->post('(:segment)/portal/cobros/(:num)/abonar', 'Partner\PortalController::abonarPortal/$1/$2');
$routes->get('(:segment)/portal/cobros/(:num)/recibo',  'Partner\PortalController::reciboCobro/$1/$2');
$routes->get('(:segment)/portal/arqueo',       'Partner\PortalController::arqueo/$1');     // "Mi caja" — arqueo del día
$routes->get('(:segment)/portal/cartera',      'Partner\PortalController::cartera/$1');
$routes->get('(:segment)/portal/cliente/(:num)',                    'Partner\PortalController::cliente/$1/$2');
$routes->post('(:segment)/portal/cliente/(:num)',                   'Partner\PortalController::guardarCliente/$1/$2');
$routes->post('(:segment)/portal/cliente/(:num)/dato/(:segment)',               'Partner\PortalController::agregarDatoCliente/$1/$2/$3');
$routes->post('(:segment)/portal/cliente/(:num)/dato/(:segment)/(:num)/actualizar', 'Partner\PortalController::actualizarDatoCliente/$1/$2/$3/$4');
$routes->post('(:segment)/portal/cliente/(:num)/dato/(:segment)/(:num)/eliminar',   'Partner\PortalController::eliminarDatoCliente/$1/$2/$3/$4');
$routes->get('(:segment)/portal/actividad',    'Partner\PortalController::actividad/$1');
$routes->get('(:segment)/portal/calculadora',  'Partner\PortalController::calculadora/$1');

// ---------------------------------------------------------------------------
// Admin del sistema — solo superadmin (Controllers\Admin)
// ---------------------------------------------------------------------------
$routes->group('admin', ['filter' => 'admin', 'namespace' => 'App\Controllers\Admin'], static function ($routes) {
    $routes->get('estadisticas', 'EstadisticasController::index');

    // Tenants — listado, ficha, alta con provision, activar/suspender, usuarios
    $routes->get('tenants',               'TenantController::index');
    $routes->get('tenants/nuevo',         'TenantController::nuevo');
    $routes->post('tenants',              'TenantController::guardar');
    $routes->get('tenants/slug-sugerir',  'TenantController::slugSugerir');       // antes de (:num)
    $routes->get('tenants/(:num)',        'TenantController::ver/$1');
    $routes->post('tenants/(:num)/toggle',   'TenantController::toggle/$1');
    $routes->post('tenants/(:num)/suscripcion', 'TenantController::suscripcion/$1');
    $routes->post('tenants/(:num)/condiciones', 'TenantController::condiciones/$1');
    $routes->get('tenants/(:num)/exporte',   'TenantController::exporte/$1');
    $routes->post('tenants/(:num)/cobrar','TenantController::cobrar/$1');
    $routes->post('tenants/(:num)/usuarios/(:num)/quitar', 'TenantController::quitarUsuario/$1/$2');

    // Usuarios de todos los tenants: listado, alta, edición, estado, clave
    $routes->get('usuarios',                 'UsuarioController::index');
    $routes->get('usuarios/nuevo',           'UsuarioController::nuevo');
    $routes->post('usuarios',                'UsuarioController::guardar');
    $routes->get('usuarios/(:num)/editar',   'UsuarioController::editar/$1');
    $routes->post('usuarios/(:num)',         'UsuarioController::actualizar/$1');
    $routes->post('usuarios/(:num)/toggle',  'UsuarioController::toggle/$1');
    $routes->post('usuarios/(:num)/clave',   'UsuarioController::resetClave/$1');
    $routes->post('usuarios/(:num)/reset-rapido', 'UsuarioController::resetRapido/$1');

    // Leads — solicitudes de alta de la landing (acceso_solicitudes)
    $routes->get('leads',                        'LeadsController::index');
    $routes->post('leads/(:num)/contactar',      'LeadsController::contactar/$1');
    $routes->post('leads/(:num)/estado',         'LeadsController::estado/$1');
    $routes->post('leads/(:num)/rechazar',       'LeadsController::rechazar/$1');

    // Contratos de servicio SaaS (desde un lead CONTACTADO)
    $routes->post('leads/(:num)/contrato',       'ContratosController::guardar/$1');
    $routes->get('contratos/(:num)',             'ContratosController::ver/$1');

    // Auditoría global (audit_logs)
    $routes->get('auditoria', 'AuditoriaController::index');
    $routes->get('auditoria/errores',        'AuditoriaController::errores');        // bitácora error_log
    $routes->post('auditoria/errores/purgar','AuditoriaController::purgarErrores');  // borra >X días

    // Configuración — catálogo de planes SaaS
    $routes->get('configuracion',                  'ConfiguracionController::index');
    $routes->post('configuracion/planes/(:num)',   'ConfiguracionController::guardarPlan/$1');
});

// Suscripción — pantalla de bloqueo cuando el plan está vencido (auth, sin chequeo de plan)
$routes->get('cuenta-suspendida', 'Partner\SuscripcionController::index', ['filter' => 'auth']);
$routes->post('cuenta-suspendida/reportar', 'Partner\SuscripcionController::reportarPago', ['filter' => 'auth']);

// ---------------------------------------------------------------------------
// Partner (tenant) — requieren sesión + suscripción al día (Controllers\Partner)
// ---------------------------------------------------------------------------
$routes->group('', ['filter' => ['auth', 'suscripcion', 'horario'], 'namespace' => 'App\Controllers\Partner'], static function ($routes) {
    $routes->get('dashboard', 'DashboardController::index');

    // Asistente Chat-AI del dashboard — listas reales en JSON
    $routes->get('asistente/datos/(:segment)', 'AsistenteController::datos/$1');

    // Valoración del sistema — cualquier usuario logueado (modal cada 5 días)
    $routes->post('valoracion', 'ValoracionController::guardar');

    // Configuración del tenant — solo quien tenga el permiso
    $routes->get('configuracion', 'ConfiguracionController::index', ['filter' => 'auth:admin.configuracion']);
    $routes->post('configuracion', 'ConfiguracionController::guardar', ['filter' => 'auth:admin.configuracion']);
    $routes->get('configuracion/plantilla/(:num)', 'ConfiguracionController::plantilla/$1', ['filter' => 'auth:admin.configuracion']);
    $routes->post('configuracion/plantilla/(:num)', 'ConfiguracionController::guardarPlantilla/$1', ['filter' => 'auth:admin.configuracion']);

    // Socios — Empleados
    $routes->get('socios/empleados', 'EmpleadoController::index', ['filter' => 'auth:empleados.ver']);
    $routes->get('socios/empleados/crear', 'EmpleadoController::crear', ['filter' => 'auth:empleados.crear']);
    $routes->post('socios/empleados', 'EmpleadoController::guardar', ['filter' => 'auth:empleados.crear']);
    $routes->get('socios/empleados/(:num)', 'EmpleadoController::ver/$1', ['filter' => 'auth:empleados.ver']);
    $routes->get('socios/empleados/(:num)/editar', 'EmpleadoController::editar/$1', ['filter' => 'auth:empleados.editar']);
    $routes->post('socios/empleados/(:num)/editar', 'EmpleadoController::actualizar/$1', ['filter' => 'auth:empleados.editar']);
    $routes->post('socios/empleados/(:num)/dato/(:segment)', 'EmpleadoController::agregarDato/$1/$2', ['filter' => 'auth:empleados.editar']);
    $routes->post('socios/empleados/(:num)/dato/(:segment)/(:num)/actualizar', 'EmpleadoController::actualizarDato/$1/$2/$3', ['filter' => 'auth:empleados.editar']);
    $routes->post('socios/empleados/(:num)/dato/(:segment)/(:num)/eliminar', 'EmpleadoController::eliminarDato/$1/$2/$3', ['filter' => 'auth:empleados.editar']);

    // Socios — Clientes
    $routes->get('socios/clientes', 'ClienteController::index', ['filter' => 'auth:clientes.ver']);
    $routes->get('socios/clientes/crear', 'ClienteController::crear', ['filter' => 'auth:clientes.crear']);
    $routes->post('socios/clientes', 'ClienteController::guardar', ['filter' => 'auth:clientes.crear']);
    $routes->get('socios/clientes/(:num)', 'ClienteController::ver/$1', ['filter' => 'auth:clientes.ver']);
    $routes->get('socios/clientes/(:num)/editar', 'ClienteController::editar/$1', ['filter' => 'auth:clientes.editar']);
    $routes->post('socios/clientes/(:num)/editar', 'ClienteController::actualizar/$1', ['filter' => 'auth:clientes.editar']);
    $routes->post('socios/clientes/(:num)/dato/(:segment)', 'ClienteController::agregarDato/$1/$2', ['filter' => 'auth:clientes.editar']);
    $routes->post('socios/clientes/(:num)/dato/(:segment)/(:num)/actualizar', 'ClienteController::actualizarDato/$1/$2/$3', ['filter' => 'auth:clientes.editar']);
    $routes->post('socios/clientes/(:num)/dato/(:segment)/(:num)/eliminar', 'ClienteController::eliminarDato/$1/$2/$3', ['filter' => 'auth:clientes.editar']);

    // Desembolsos pendientes — ruta de entrega con mapa y reorden
    $routes->get('credito/desembolsar', 'SolicitudController::desembolsos', ['filter' => 'auth:solicitudes.desembolsar']);

    // Crédito — Solicitudes
    $routes->get('credito/solicitudes', 'SolicitudController::index', ['filter' => 'auth:solicitudes.ver']);
$routes->get('credito/solicitudes/nueva', 'SolicitudController::nueva', ['filter' => 'auth:solicitudes.crear']);
$routes->post('credito/solicitudes', 'SolicitudController::guardar', ['filter' => 'auth:solicitudes.crear']);
    $routes->get('credito/solicitudes/(:num)', 'SolicitudController::ver/$1', ['filter' => 'auth:solicitudes.ver']);
    $routes->get('credito/solicitudes/(:num)/analisis', 'SolicitudController::analisis/$1', ['filter' => 'auth:solicitudes.ver']);
    $routes->get('credito/solicitudes/(:num)/documentos', 'SolicitudController::documentos/$1', ['filter' => 'auth:solicitudes.ver']);
    $routes->post('credito/solicitudes/(:num)/analisis/calcular', 'SolicitudController::calcularAnalisis/$1', ['filter' => 'auth:solicitudes.editar']);
    $routes->get('credito/solicitudes/(:num)/aprobar', 'SolicitudController::aprobar/$1', ['filter' => 'auth:solicitudes.aprobar']);
    $routes->post('credito/solicitudes/(:num)/aprobar', 'SolicitudController::guardarAprobacion/$1', ['filter' => 'auth:solicitudes.aprobar']);
    $routes->get('credito/solicitudes/(:num)/desembolsar', 'SolicitudController::desembolsar/$1', ['filter' => 'auth:solicitudes.desembolsar']);
    $routes->post('credito/solicitudes/(:num)/desembolsar', 'SolicitudController::guardarDesembolso/$1', ['filter' => 'auth:solicitudes.desembolsar']);
    $routes->post('credito/solicitudes/(:num)/entregar', 'SolicitudController::entregarDesembolso/$1', ['filter' => 'auth:solicitudes.desembolsar']);
    $routes->get('credito/solicitudes/(:num)/editar', 'SolicitudController::editar/$1', ['filter' => 'auth:solicitudes.editar']);
    $routes->post('credito/solicitudes/(:num)/editar', 'SolicitudController::actualizar/$1', ['filter' => 'auth:solicitudes.editar']);
    $routes->post('credito/solicitudes/(:num)/estado', 'SolicitudController::cambiarEstado/$1', ['filter' => 'auth:solicitudes.ver']);

    // Créditos activos (plan de cuotas + abonos)
    $routes->get('creditos', 'CreditoController::index', ['filter' => 'auth:creditos.ver']);
    $routes->get('creditos/(:num)', 'CreditoController::ver/$1', ['filter' => 'auth:creditos.ver']);
    $routes->post('creditos/(:num)/abonar', 'CreditoController::abonar/$1', ['filter' => 'auth:pagos.registrar']);
    $routes->post('creditos/(:num)/reestructurar', 'CreditoController::reestructurar/$1', ['filter' => 'auth:solicitudes.aprobar']);
    $routes->post('creditos/(:num)/refinanciar', 'CreditoController::refinanciar/$1', ['filter' => 'auth:solicitudes.aprobar']);

    // Bandeja de pagos — todo pago nace en REVISION hasta aprobarse
    $routes->get('pagos', 'PagoController::index', ['filter' => 'auth:pagos.ver']);
    $routes->post('pagos/registrar', 'PagoController::registrar', ['filter' => 'auth:pagos.registrar']);
    $routes->post('pagos/(:num)/aprobar', 'PagoController::aprobar/$1', ['filter' => 'auth:pagos.aprobar']);
    $routes->post('pagos/(:num)/revertir', 'PagoController::revertir/$1', ['filter' => 'auth:pagos.revertir']);
    $routes->post('pagos/(:num)/rechazar', 'PagoController::rechazar/$1', ['filter' => 'auth:pagos.aprobar']);
    $routes->post('pagos/(:num)/cumplio',  'PagoController::cumplio/$1',  ['filter' => 'auth:pagos.registrar']);
    $routes->get('pagos/(:num)/recibo',    'PagoController::recibo/$1',   ['filter' => 'auth:pagos.ver']);

    // Finanzas — arqueo de caja del gestor (cierre diario)
    $routes->get('finanzas/arqueo',  'ArqueoController::index',   ['filter' => 'auth:caja.arqueo']);
    $routes->post('finanzas/arqueo', 'ArqueoController::guardar', ['filter' => 'auth:caja.arqueo']);

    // Finanzas — control de gastos por categoría
    $routes->get('finanzas/gastos',  'GastoController::index',    ['filter' => 'auth:caja.gastos']);
    $routes->post('finanzas/gastos', 'GastoController::guardar',  ['filter' => 'auth:caja.gastos']);
    $routes->post('finanzas/gastos/categorias',                 'GastoController::guardarCategoria',      ['filter' => 'auth:caja.gastos']);
    $routes->post('finanzas/gastos/categorias/(:num)/toggle',   'GastoController::toggleCategoria/$1',    ['filter' => 'auth:caja.gastos']);
    $routes->post('finanzas/gastos/(:num)/editar', 'GastoController::actualizar/$1', ['filter' => 'auth:caja.gastos']);
    $routes->post('finanzas/gastos/(:num)/anular', 'GastoController::anular/$1',     ['filter' => 'auth:caja.gastos']);

    // Asistencia del personal — panel del admin (kiosco = ruta pública arriba)
    $routes->get('asistencia',                 'AsistenciaController::index',       ['filter' => 'auth:empleados.asistencia']);
    $routes->post('asistencia',                'AsistenciaController::guardar',     ['filter' => 'auth:empleados.asistencia']);
    $routes->post('asistencia/(:num)/editar',  'AsistenciaController::actualizar/$1', ['filter' => 'auth:empleados.asistencia']);
    $routes->post('asistencia/(:num)/anular',  'AsistenciaController::anular/$1',     ['filter' => 'auth:empleados.asistencia']);

    // Finanzas — otros ingresos (venta con recibo e IVA, donación, otro)
    $routes->get('finanzas/ingresos',  'IngresoController::index',   ['filter' => 'auth:caja.ingresos']);
    $routes->post('finanzas/ingresos', 'IngresoController::guardar', ['filter' => 'auth:caja.ingresos']);
    $routes->get('finanzas/ingresos/(:num)/recibo',  'IngresoController::recibo/$1',    ['filter' => 'auth:caja.ingresos']);
    $routes->post('finanzas/ingresos/(:num)/editar', 'IngresoController::actualizar/$1', ['filter' => 'auth:caja.ingresos']);
    $routes->post('finanzas/ingresos/(:num)/anular', 'IngresoController::anular/$1',     ['filter' => 'auth:caja.ingresos']);

    // Reportes — índice con cards por categoría (el catálogo filtra por permiso)
    $routes->get('reportes', 'ReporteController::index', ['filter' => 'auth']);

    // Configuración — categorías de reportes y asignación reporte→categoría
    $routes->get ('configuracion/reportes',                          'ConfiguracionController::reportes',                 ['filter' => 'auth:admin.configuracion']);
    $routes->post('configuracion/reportes/categoria',                'ConfiguracionController::crearCategoriaReporte',    ['filter' => 'auth:admin.configuracion']);
    $routes->post('configuracion/reportes/categoria/(:num)',         'ConfiguracionController::guardarCategoriaReporte/$1',['filter' => 'auth:admin.configuracion']);
    $routes->post('configuracion/reportes/categoria/(:num)/eliminar','ConfiguracionController::eliminarCategoriaReporte/$1',['filter' => 'auth:admin.configuracion']);
    $routes->post('configuracion/reportes/asignar',                  'ConfiguracionController::asignarReportes',          ['filter' => 'auth:admin.configuracion']);

    // Crédito — cartera vigente y reporte del pipeline
    $routes->get('credito/cartera', 'CreditoController::cartera',  ['filter' => 'auth:creditos.ver']);
    $routes->get('credito/reporte',        'ReporteController::creditos', ['filter' => 'auth:reportes.ver']);
    $routes->get('credito/reporte-conami', 'ReporteController::conami',   ['filter' => 'auth:reportes.ver']);

    // Finanzas — recuperación (cuotas vencidas) y reporte financiero
    $routes->get('finanzas/recuperacion', 'PagoController::recuperacion',   ['filter' => 'auth:pagos.ver']);
    $routes->get('finanzas/reporte',      'ReporteController::finanzas',    ['filter' => 'auth:reportes.ver']);

    // Herramientas — calculadora de cuotas y generador de documentación
    $routes->get('herramientas/calculadora',  'HerramientasController::calculadora');
    $routes->get('herramientas/documentacion','HerramientasController::documentacion', ['filter' => 'auth:solicitudes.documentacion']);

    // Finanzas — plan de metas por gestor
    $routes->get('finanzas/metas',  'MetaController::index',   ['filter' => 'auth:metas.plan']);
    $routes->post('finanzas/metas', 'MetaController::guardar', ['filter' => 'auth:metas.plan']);
    $routes->post('finanzas/metas/metricas',               'MetaController::guardarMetrica',   ['filter' => 'auth:metas.plan']);
    $routes->post('finanzas/metas/metricas/(:num)/toggle', 'MetaController::toggleMetrica/$1', ['filter' => 'auth:metas.plan']);
    $routes->post('finanzas/metas/(:num)/editar', 'MetaController::actualizar/$1', ['filter' => 'auth:metas.plan']);
    $routes->post('finanzas/metas/(:num)/anular', 'MetaController::anular/$1',     ['filter' => 'auth:metas.plan']);
});

// ---------------------------------------------------------------------------
// Shared — perfil (Controllers\Shared)
// ---------------------------------------------------------------------------
$routes->group('perfil', ['filter' => 'auth', 'namespace' => 'App\Controllers\Shared'], static function ($routes) {
    $routes->get('cambiar-password', 'PerfilController::cambiarPassword');
    $routes->post('cambiar-password', 'PerfilController::actualizarPassword');
});

// Notificaciones — JSON para el panel derecho
$routes->group('notificaciones', ['filter' => 'auth', 'namespace' => 'App\Controllers\Shared'], static function ($routes) {
    $routes->get('/', 'NotificacionController::index');
    $routes->post('(:num)/leida', 'NotificacionController::leida/$1');
    $routes->post('leer-todas', 'NotificacionController::leerTodas');
});
