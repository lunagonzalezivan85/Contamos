<?php

/**
 * Helper de assets con cache-busting por versión.
 * v_asset('css/app.css') → http://.../css/app.css?v=<filemtime>
 * La versión cambia sola cuando el archivo se modifica — fuerza al
 * navegador a descargar el archivo nuevo sin limpiar caché a mano.
 */

if (!function_exists('v_asset')) {
    /**
     * URL de un asset de /public con ?v= basado en su fecha de modificación.
     * La URL incluye 'public/' explícito — funciona igual si el docroot
     * apunta a public/ o a la raíz del proyecto (host compartido por FTP).
     *
     * @param string $path Ruta relativa a public/ (ej: 'css/app.css')
     */
    function v_asset(string $path): string
    {
        $ver = @filemtime(FCPATH . $path);
        return base_url('public/' . ltrim($path, '/')) . '?v=' . ($ver ?: time());
    }
}
