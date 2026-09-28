<?php

/**
 * Helper de menú — resuelve el menú del usuario actual en cada request.
 * Uso en layouts: <?php $menus = menu_items(); ?>
 */
if (!function_exists('menu_items')) {

    function menu_items(): array
    {
        return (new \App\Services\Shared\MenuService())->paraSesion();
    }
}
