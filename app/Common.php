<?php

/**
 * The goal of this file is to allow developers a location
 * where they can overwrite core procedural functions and
 * replace them with their own. This file is loaded during
 * the bootstrap process and is called during the framework's
 * execution.
 *
 * This can be looked at as a `master helper` file that is
 * loaded early on, and may also contain additional functions
 * that you'd like to use throughout your entire application
 *
 * @see: https://codeigniter.com/user_guide/extending/common.html
 */

if (!function_exists('log_error')) {
    /**
     * Registra un error de runtime en la tabla error_log.
     * Uso: log_error('Clase::metodo', $e) dentro de catch, o desde el
     * exception handler. Nunca lanza — si la tabla no existe aún, traga.
     * El detalle solo lo ve el admin; al usuario se muestra mensaje genérico.
     */
    function log_error(string $origen, $e): void
    {
        try {
            $msg   = $e instanceof \Throwable ? $e->getMessage() : (string) $e;
            $traza = $e instanceof \Throwable
                ? mb_substr($e->getFile() . ':' . $e->getLine() . "\n" . $e->getTraceAsString(), 0, 65000)
                : null;
            (new \App\Models\ErrorLogModel())->insert([
                'tenant_id' => session('tenant_id') ?: null,
                'user_id'   => session('user_id') ?: null,
                'origen'    => mb_substr($origen, 0, 120),
                'mensaje'   => mb_substr($msg, 0, 255),
                'traza'     => $traza,
            ]);
        } catch (\Throwable $ignored) {
            // La bitácora nunca puede tumbar el flujo principal.
        }
    }
}
