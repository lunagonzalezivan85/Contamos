<?php

namespace App\Services\Shared;

/**
 * Resuelve el menú del usuario en cada request desde Config/Menu.php.
 * No se cachea en sesión — los cambios en Menu.php son inmediatos.
 */
class MenuService
{
    /**
     * Menú según la sesión actual: superadmin → sistema, resto → tenant
     * filtrado por los permisos guardados en sesión.
     */
    public function paraSesion(): array
    {
        $esSistema = (bool) session('es_admin_sistema');
        $permisos  = session('permisos') ?? [];
        $menu      = config('Menu')->para($esSistema ? 'sistema' : 'tenant');

        $menu = $esSistema ? $menu : $this->filtrarPorPermisos($menu, $permisos);

        return $this->resolverUrls($menu);
    }

    /**
     * Reemplaza placeholders en las urls ({slug} → slug del tenant en sesión).
     */
    protected function resolverUrls(array $items): array
    {
        $slug = (string) session('tenant_slug');

        foreach ($items as &$item) {
            if (isset($item['url'])) {
                $item['url'] = str_replace('{slug}', $slug, (string) $item['url']);
            }
            if (isset($item['children'])) {
                $item['children'] = $this->resolverUrls($item['children']);
            }
        }

        return $items;
    }

    /**
     * Quita items sin permiso; agrupadores sin hijos visibles se eliminan.
     */
    protected function filtrarPorPermisos(array $items, array $permisos): array
    {
        $resultado = [];

        foreach ($items as $item) {
            if (isset($item['children'])) {
                $item['children'] = $this->filtrarPorPermisos($item['children'], $permisos);
                if (empty($item['children'])) {
                    continue;
                }
            } elseif (isset($item['permiso']) && !in_array($item['permiso'], $permisos, true)) {
                continue;
            }
            $resultado[] = $item;
        }

        return $resultado;
    }
}
