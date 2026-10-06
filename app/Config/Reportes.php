<?php

namespace Config;

use CodeIgniter\Config\BaseConfig;

/**
 * Catálogo de reportes del tenant.
 *
 * Cada reporte disponible en el índice /reportes se declara aquí.
 * 'cat' es la categoría por defecto cuando el tenant no la ha
 * personalizado en Configuración → Reportes.
 *
 * Para agregar un reporte nuevo: crear su controlador/ruta y luego
 * registrarlo aquí — aparece automáticamente en el índice y en
 * la pantalla de categorías.
 */
class Reportes extends BaseConfig
{
    public array $catalogo = [
        'credito.reporte' => [
            'nombre'      => 'Reporte de créditos',
            'descripcion' => 'Pipeline de solicitudes, desembolsos y salud de la cartera por periodo.',
            'icono'       => 'credit-card',
            'url'         => '/credito/reporte',
            'permiso'     => 'reportes.ver',
            'cat'         => 'Créditos',
            'kw'          => ['credito', 'creditos', 'solicitudes', 'desembolso', 'desembolsos', 'pipeline', 'gestor', 'gestores', 'prestamo', 'prestamos'],
        ],
        'credito.cartera' => [
            'nombre'      => 'Cartera vigente',
            'descripcion' => 'Créditos activos con cuotas vencidas, saldo y días de atraso.',
            'icono'       => 'briefcase',
            'url'         => '/credito/cartera',
            'permiso'     => 'creditos.ver',
            'cat'         => 'Créditos',
            'kw'          => ['cartera', 'vencida', 'vencidas', 'atraso', 'atrasos', 'mora', 'activos', 'vigente', 'vigentes', 'cuotas'],
        ],
        'credito.conami' => [
            'nombre'      => 'Cartera por riesgo (CONAMI)',
            'descripcion' => 'Clasificación A1–D2 por días de atraso con provisión estimada. Exportable.',
            'icono'       => 'shield',
            'url'         => '/credito/reporte-conami',
            'permiso'     => 'reportes.ver',
            'cat'         => 'Regulatorio',
            'kw'          => ['conami', 'riesgo', 'clasificacion', 'provision', 'provisiones', 'regulatorio', 'regulador', 'categoria', 'categorias'],
        ],
        'finanzas.reporte' => [
            'nombre'      => 'Reporte financiero',
            'descripcion' => 'Cobros, otros ingresos y gastos del periodo con flujo neto.',
            'icono'       => 'dollar-sign',
            'url'         => '/finanzas/reporte',
            'permiso'     => 'reportes.ver',
            'cat'         => 'Finanzas',
            'kw'          => ['financiero', 'finanzas', 'cobros', 'cobro', 'ingresos', 'gastos', 'flujo', 'neto', 'pagos', 'ingreso', 'gasto'],
        ],
        'finanzas.pagos-dia' => [
            'nombre'      => 'Pagos del día',
            'descripcion' => 'Cobros del día desglosados: cliente, crédito, capital, interés y mora.',
            'icono'       => 'calendar',
            'url'         => '/finanzas/pagos-dia',
            'permiso'     => 'pagos.ver',
            'cat'         => 'Finanzas',
            'kw'          => ['pagos', 'dia', 'cobros', 'capital', 'interes', 'mora', 'desglose', 'diario'],
        ],
        'finanzas.flujo' => [
            'nombre'      => 'Ingresos vs egresos',
            'descripcion' => 'Comparativo del periodo: pagos, otros ingresos, desembolsos y gastos con resultado positivo o negativo.',
            'icono'       => 'trending-up',
            'url'         => '/finanzas/flujo',
            'permiso'     => 'reportes.ver',
            'cat'         => 'Finanzas',
            'kw'          => ['flujo', 'caja', 'comparativo', 'ingresos', 'egresos', 'gastos', 'desembolsos', 'positivo', 'negativo', 'resultado', 'balance'],
        ],
        'finanzas.recuperacion' => [
            'nombre'      => 'Recuperación de cartera',
            'descripcion' => 'Cuotas vencidas pendientes por gestor para gestión de cobro.',
            'icono'       => 'refresh-cw',
            'url'         => '/finanzas/recuperacion',
            'permiso'     => 'pagos.ver',
            'cat'         => 'Finanzas',
            'kw'          => ['recuperacion', 'cobro', 'cobranza', 'vencidas', 'vencido', 'mora', 'pendientes', 'gestor'],
        ],
    ];
}
