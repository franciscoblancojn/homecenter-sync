<?php

/**
 * Caché del catálogo de Seller Center: marcas y categorías.
 *
 * La API no permite publicar en categorías intermedias: solo se listan las hojas
 * (las que no tienen hijas), con su ruta completa para poder elegirlas.
 */
class HCS_CATALOG
{
    /**
     * Descarga marcas y categorías. Devuelve un resumen para el admin.
     */
    public static function refresh()
    {
        $brands = HCS_API::request('GetBrands');
        if (!$brands['ok']) {
            return $brands;
        }
        $brand_nodes = $brands['body']['Brands']['Brand'] ?? [];
        $names = [];
        foreach (HCS_API::listOf($brand_nodes) as $brand) {
            if (is_array($brand) && !empty($brand['Name'])) {
                $names[] = (string) $brand['Name'];
            }
        }
        sort($names);
        update_option(HCS_BRANDS, [
            'time' => time(),
            'items' => array_values(array_unique($names)),
        ], false);

        $categories = HCS_API::request('GetCategoryTree');
        if (!$categories['ok']) {
            return $categories;
        }
        $items = [];
        $roots = $categories['body']['Categories']['Category'] ?? [];
        foreach (HCS_API::listOf($roots) as $category) {
            self::walk($category, [], $items);
        }
        update_option(HCS_CATEGORIES, [
            'time' => time(),
            'items' => $items,
        ], false);

        return [
            'ok' => true,
            'brands' => count($names),
            'categories' => count($items),
        ];
    }

    private static function walk($category, $parents, &$items)
    {
        if (!is_array($category)) {
            return;
        }
        $name = (string) ($category['Name'] ?? '');
        $id = (string) ($category['CategoryId'] ?? '');
        $path = array_merge($parents, $name ? [$name] : []);
        $children = $category['Children']['Category'] ?? null;

        if (empty($children) && '' !== $id) {
            $items[] = [
                'id' => $id,
                'name' => $name,
                'path' => implode(' > ', $path),
            ];

            return;
        }
        foreach (HCS_API::listOf($children) as $child) {
            self::walk($child, $path, $items);
        }
    }

    /**
     * @return string[]
     */
    public static function brands()
    {
        $data = get_option(HCS_BRANDS, []);
        $items = is_array($data) && isset($data['items']) ? $data['items'] : [];

        return is_array($items) ? $items : [];
    }

    /**
     * @return array<int, array{id:string,name:string,path:string}>
     */
    public static function categories()
    {
        $data = get_option(HCS_CATEGORIES, []);
        $items = is_array($data) && isset($data['items']) ? $data['items'] : [];

        return is_array($items) ? $items : [];
    }

    public static function updatedAt()
    {
        $data = get_option(HCS_CATEGORIES, []);

        return is_array($data) && !empty($data['time']) ? (int) $data['time'] : 0;
    }

    public static function categoryLabel($id)
    {
        foreach (self::categories() as $category) {
            if ((string) $category['id'] === (string) $id) {
                return (string) $category['path'];
            }
        }

        return (string) $id;
    }
}
