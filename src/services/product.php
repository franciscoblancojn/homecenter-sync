<?php

/**
 * Productos: lectura del catálogo de WooCommerce, mapeo local ↔ Homecenter,
 * generación del XML de Seller Center y estado de sincronización.
 *
 * El producto padre guarda la configuración (categoría, marca, impuesto, estado…)
 * y cada variación guarda su SellerSku y su estado remoto. `meta()` hereda la
 * configuración del padre cuando se trata de una variación.
 */
class HCS_PRODUCT
{
    const TYPES = ['simple', 'variable'];
    const STATUSES = ['publish', 'private', 'draft', 'pending', 'future'];
    const PER_PAGE = [25, 50, 100];

    // El SellerSku es propio de cada producto/variación: no se hereda del padre.
    // El GTIN tampoco: cada variación puede tener su propio EAN.
    const CONFIG_KEYS = ['enabled', 'category', 'brand', 'condition', 'tax', 'attributes', 'variation_map', 'status'];
    const STATE_KEYS = ['remote_status', 'remote_published', 'feed_id', 'last_sync', 'last_error', 'remote_price', 'remote_stock', 'remote_qc'];

    const DEFAULTS = [
        'enabled' => 0,
        'seller_sku' => '',
        'category' => '',
        'brand' => '',
        'gtin' => '',
        'condition' => '',
        'tax' => '',
        'attributes' => '',
        'variation_map' => '',
        'status' => 'active',
        'remote_status' => '',
        'remote_published' => 0,
        'feed_id' => '',
        'last_sync' => '',
        'last_error' => '',
        'remote_price' => '',
        'remote_stock' => '',
        'remote_qc' => '',
        'images_hash' => '',
        'parent_sku' => '',
    ];

    const STATUS_LABELS = [
        '' => 'Sin publicar',
        'queued' => 'En cola',
        'feed_pending' => 'En revisión',
        'published' => 'Publicado',
        'inactive' => 'Inactivo',
        'error' => 'Error',
        'partial' => 'Parcial',
    ];

    public static function config()
    {
        return (new HCS_USE_DATA_CONFIG())->get();
    }

    private static function read($id)
    {
        $out = self::DEFAULTS;
        foreach (self::DEFAULTS as $key => $default) {
            $value = get_post_meta($id, HCS_META . $key, true);
            if ('' !== $value) {
                $out[$key] = $value;
            }
        }
        $out['enabled'] = 1 === (int) $out['enabled'] ? 1 : 0;
        $out['remote_published'] = 1 === (int) $out['remote_published'] ? 1 : 0;

        return $out;
    }

    /**
     * Configuración + estado del producto. En variaciones hereda del padre.
     */
    public static function meta($id)
    {
        $own = self::read($id);
        $parent = self::parentId($id);
        if (!$parent) {
            return $own;
        }
        $parent_meta = self::read($parent);
        foreach (self::CONFIG_KEYS as $key) {
            $own[$key] = $parent_meta[$key];
        }
        // El estado del padre decide si la variación se publica o se desactiva.
        $own['enabled'] = $parent_meta['enabled'];

        return $own;
    }

    public static function patch($id, $data)
    {
        foreach ((array) $data as $key => $value) {
            if (!array_key_exists($key, self::DEFAULTS)) {
                continue;
            }
            update_post_meta($id, HCS_META . $key, is_bool($value) ? (int) $value : $value);
        }
    }

    public static function parentId($id)
    {
        if ('product_variation' !== get_post_type($id)) {
            return 0;
        }

        return (int) wp_get_post_parent_id($id);
    }

    public static function isPublished($id)
    {
        $meta = self::read($id);

        return !empty($meta['remote_published'])
            || in_array($meta['remote_status'], ['published', 'feed_pending', 'inactive'], true);
    }

    /**
     * SellerSku del producto o variación (SKU de WooCommerce o uno generado).
     */
    public static function sellerSku($product)
    {
        $product = is_numeric($product) ? wc_get_product($product) : $product;
        if (!$product) {
            return '';
        }
        $own = get_post_meta($product->get_id(), HCS_META . 'seller_sku', true);
        if ('' !== $own) {
            return sanitize_text_field($own);
        }
        $sku = (string) $product->get_sku('edit');
        if ('' !== trim($sku)) {
            return trim($sku);
        }
        $config = self::config();

        return $config['sku_prefix'] . $product->get_id();
    }

    /**
     * ParentSku de un grupo de variaciones: el SellerSku de la primera variación.
     *
     * Se guarda en el padre la primera vez para que el grupo no cambie si luego
     * se reordenan o se borran variaciones.
     */
    public static function groupParentSku($parent_id)
    {
        $parent = wc_get_product($parent_id);
        if (!$parent || !$parent->is_type('variable')) {
            return $parent ? self::sellerSku($parent) : '';
        }
        $saved = (string) get_post_meta($parent_id, HCS_META . 'parent_sku', true);
        if ('' !== $saved) {
            foreach ($parent->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                if ($child && self::sellerSku($child) === $saved) {
                    return $saved;
                }
            }
        }
        foreach ($parent->get_children() as $child_id) {
            $child = wc_get_product($child_id);
            if (!$child) {
                continue;
            }
            $sku = self::sellerSku($child);
            if ('' !== $sku) {
                update_post_meta($parent_id, HCS_META . 'parent_sku', $sku);

                return $sku;
            }
        }

        return self::sellerSku($parent);
    }

    /**
     * Variación que hace de padre del grupo (la del ParentSku guardado).
     */
    public static function parentVariationId($parent_id)
    {
        $parent = wc_get_product($parent_id);
        if (!$parent || !$parent->is_type('variable')) {
            return 0;
        }
        $sku = self::groupParentSku($parent_id);
        if ('' === $sku) {
            return 0;
        }
        foreach ($parent->get_children() as $child_id) {
            $child = wc_get_product($child_id);
            if ($child && self::sellerSku($child) === $sku) {
                return (int) $child_id;
            }
        }

        return 0;
    }

    /**
     * Convierte IDs (padres variables, simples o variaciones) en IDs sincronizables.
     *
     * @return int[]
     */
    public static function expand($ids)
    {
        $out = [];
        foreach (array_unique(array_map('absint', (array) $ids)) as $id) {
            $product = $id ? wc_get_product($id) : null;
            if (!$product) {
                continue;
            }
            if ($product->is_type('variable')) {
                foreach ($product->get_children() as $child_id) {
                    $out[] = (int) $child_id;
                }
            } elseif (in_array($product->get_type(), ['simple', 'variation'], true)) {
                $out[] = $product->get_id();
            }
        }

        return array_values(array_unique($out));
    }

    /**
     * Productos que hay que enviar para un ID de la cola, con su acción.
     *
     * @return array<int, string> [product_id => create|update|deactivate]
     */
    public static function actions($ids)
    {
        $config = self::config();
        $out = [];
        foreach (self::expand($ids) as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }
            $meta = self::meta($id);
            $published = self::isPublished($id);
            if ($meta['enabled'] && 'active' === $meta['status']) {
                $out[$id] = $published ? 'update' : 'create';
            } elseif ($published) {
                // Deshabilitado o marcado inactivo: ProductUpdate con Status inactive.
                $out[$id] = 'deactivate';
            }
        }

        return $out;
    }

    public static function xmlTagPublic($name, $value, $cdata = false)
    {
        return self::xmlTag($name, $value, $cdata);
    }

    private static function xmlTag($name, $value, $cdata = false)
    {
        $name = preg_replace('/[^A-Za-z0-9_]/', '', (string) $name);
        if ('' === $name) {
            return '';
        }
        $value = (string) $value;
        if ($cdata) {
            return '<' . $name . '><![CDATA[' . str_replace(']]>', ']]]]><![CDATA[>', $value) . ']]></' . $name . '>';
        }

        return '<' . $name . '>' . htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</' . $name . '>';
    }

    /**
     * Convierte líneas "Clave=Valor" en un array ordenado.
     */
    public static function pairs($text)
    {
        $out = [];
        foreach (preg_split('/\r\n|\r|\n/', (string) $text) as $line) {
            $line = trim($line);
            if ('' === $line) {
                continue;
            }
            $pos = strpos($line, '=');
            if (false === $pos) {
                $pos = strpos($line, ':');
            }
            if (false === $pos) {
                continue;
            }
            $key = trim(substr($line, 0, $pos));
            $value = trim(substr($line, $pos + 1));
            if ('' !== $key && '' !== $value) {
                $out[$key] = $value;
            }
        }

        return $out;
    }

    private static function brandOf($product, $meta)
    {
        if ('' !== $meta['brand']) {
            return $meta['brand'];
        }
        foreach (['product_brand', 'pwb-brand', 'yith_product_brand'] as $taxonomy) {
            if (!taxonomy_exists($taxonomy)) {
                continue;
            }
            $terms = get_the_terms($product->get_id(), $taxonomy);
            if ($terms && !is_wp_error($terms)) {
                return $terms[0]->name;
            }
        }
        $config = self::config();

        return $config['default_brand'];
    }

    private static function price($value, $tax_percent, $mode)
    {
        $value = (float) $value;
        $tax = (float) $tax_percent;
        if ('add-tax' === $mode) {
            $value = $value * (1 + $tax / 100);
        } elseif ('remove-tax' === $mode && $tax > -100) {
            $value = $value / (1 + $tax / 100);
        }

        return number_format(max(0, $value), 2, '.', '');
    }

    /**
     * XML de un producto para ProductCreate/ProductUpdate o desactivación.
     *
     * @return array{ok:bool,xml:string,sku:string,error:string}
     */
    public static function payload($id, $action = 'create')
    {
        $product = wc_get_product($id);
        if (!$product) {
            return ['ok' => false, 'xml' => '', 'sku' => '', 'error' => 'El producto no existe.'];
        }
        $config = self::config();
        $meta = self::meta($id);
        $sku = self::sellerSku($product);
        if ('' === $sku) {
            return ['ok' => false, 'xml' => '', 'sku' => '', 'error' => 'Sin SKU.'];
        }

        if ('deactivate' === $action) {
            $xml = self::xmlTag('SellerSku', $sku);
            $xml .= '<BusinessUnits><BusinessUnit>';
            $xml .= self::xmlTag('OperatorCode', $config['operator_code']);
            $xml .= self::xmlTag('Status', 'inactive');
            $xml .= '</BusinessUnit></BusinessUnits>';

            return ['ok' => true, 'xml' => '<Product>' . $xml . '</Product>', 'sku' => $sku, 'error' => ''];
        }

        $parent_id = self::parentId($id);
        $parent = $parent_id ? wc_get_product($parent_id) : null;
        $parent_meta = $parent_id ? self::meta($parent_id) : $meta;

        $category = '' !== (string) $meta['category'] ? $meta['category'] : $config['default_category'];
        if ('' === (string) $category) {
            return ['ok' => false, 'xml' => '', 'sku' => $sku, 'error' => 'Falta la categoría de Homecenter (PrimaryCategory).'];
        }
        $brand = self::brandOf($product, $meta);
        if ('' === trim((string) $brand)) {
            return ['ok' => false, 'xml' => '', 'sku' => $sku, 'error' => 'Falta la marca (Brand).'];
        }

        $tax = '' !== (string) $meta['tax'] ? $meta['tax'] : $config['default_tax'];
        $condition = '' !== (string) $meta['condition'] ? $meta['condition'] : $config['default_condition'];
        $name = trim((string) $product->get_name());
        $description = trim((string) $product->get_description());
        if ('' === $description) {
            $description = trim((string) $product->get_short_description());
        }
        if ('' === $description) {
            $description = $name;
        }
        $description = wp_kses_post($description);
        if (function_exists('mb_substr')) {
            $description = mb_substr($description, 0, 25000);
        }

        $regular = (float) $product->get_regular_price('edit');
        if ($regular <= 0) {
            $regular = (float) $product->get_price('edit');
        }
        if ($regular <= 0) {
            return ['ok' => false, 'xml' => '', 'sku' => $sku, 'error' => 'El producto no tiene precio.'];
        }
        $sale = (float) $product->get_sale_price('edit');
        $price = self::price($regular, $tax, $config['price_mode']);
        $special = $sale > 0 && $sale < $regular ? self::price($sale, $tax, $config['price_mode']) : '';
        $from = '';
        $to = '';
        if ('' !== $special) {
            $from_date = $product->get_date_on_sale_from('edit');
            $to_date = $product->get_date_on_sale_to('edit');
            $from = $from_date ? $from_date->date_i18n('Y-m-d H:i:s') : current_time('Y-m-d H:i:s');
            $to = $to_date ? $to_date->date_i18n('Y-m-d H:i:s') : gmdate('Y-m-d H:i:s', time() + YEAR_IN_SECONDS);
        }

        $stock = $product->get_stock_quantity('edit');
        if (null === $stock) {
            $stock = $product->is_in_stock() ? (int) $config['fallback_stock'] : 0;
        }
        // En una variación manda el estado del producto padre.
        $woo_status = $parent ? get_post_status($parent_id) : get_post_status($id);
        $status = 'publish' === $woo_status && 'active' === $meta['status'] ? 'active' : 'inactive';

        $xml = self::xmlTag('SellerSku', $sku);
        if ($parent) {
            $xml .= self::xmlTag('ParentSku', self::groupParentSku($parent_id));
        }
        $xml .= self::xmlTag('Name', $name);
        $xml .= self::xmlTag('PrimaryCategory', $category);
        $xml .= self::xmlTag('Description', $description, true);
        $xml .= self::xmlTag('Brand', $brand);

        $gtin = trim((string) $meta['gtin']);
        if ('' === $gtin) {
            $gtin = (string) $product->get_sku('edit');
        }
        if (preg_match('/^\d{8,14}$/', $gtin)) {
            $xml .= self::xmlTag('ProductId', $gtin);
        }

        // Variación: atributos que generan la variante, con el nombre que espera Homecenter.
        if ($parent) {
            $map = self::pairs($parent_meta['variation_map']);
            foreach ($product->get_attributes() as $slug => $value) {
                $slug = (string) $slug;
                if (!isset($map[$slug])) {
                    continue;
                }
                $label = $value;
                $term = get_term_by('slug', $value, $slug);
                if ($term && !is_wp_error($term)) {
                    $label = $term->name;
                }
                $xml .= self::xmlTag($map[$slug], $label);
            }
            $variation_name = wp_strip_all_tags(wc_get_formatted_variation($product, true, true, false));
            if ('' !== $variation_name) {
                $xml .= self::xmlTag('Variation', $variation_name);
            }
        }

        $xml .= '<BusinessUnits><BusinessUnit>';
        $xml .= self::xmlTag('OperatorCode', $config['operator_code']);
        $xml .= self::xmlTag('Price', $price);
        if ('' !== $special) {
            $xml .= self::xmlTag('SpecialPrice', $special);
            $xml .= self::xmlTag('SpecialFromDate', $from);
            $xml .= self::xmlTag('SpecialToDate', $to);
        }
        $xml .= self::xmlTag('Stock', (int) max(0, $stock));
        $xml .= self::xmlTag('Status', $status);
        $xml .= '</BusinessUnit></BusinessUnits>';

        $xml .= '<ProductData>';
        $xml .= self::xmlTag('ConditionType', $condition);
        $xml .= self::xmlTag('PackageHeight', $product->get_height('edit') ? $product->get_height('edit') : $config['package_height']);
        $xml .= self::xmlTag('PackageWidth', $product->get_width('edit') ? $product->get_width('edit') : $config['package_width']);
        $xml .= self::xmlTag('PackageLength', $product->get_length('edit') ? $product->get_length('edit') : $config['package_length']);
        $xml .= self::xmlTag('PackageWeight', $product->get_weight('edit') ? $product->get_weight('edit') : $config['package_weight']);
        $xml .= self::xmlTag('TaxPercentage', $tax);

        $attributes = self::pairs($parent_meta['attributes']);
        foreach (self::pairs($meta['attributes']) as $key => $value) {
            $attributes[$key] = $value;
        }
        foreach ($attributes as $key => $value) {
            $xml .= self::xmlTag($key, $value);
        }
        $xml .= '</ProductData>';

        return ['ok' => true, 'xml' => '<Product>' . $xml . '</Product>', 'sku' => $sku, 'error' => ''];
    }

    /**
     * XML de lote para ProductCreate/ProductUpdate/ProductRemove.
     *
     * @return array{ok:bool,xml:string,ids:int[],skus:string[],errors:array}
     */
    public static function batchXml($actions)
    {
        $parts = [];
        $ids = [];
        $skus = [];
        $errors = [];
        foreach ((array) $actions as $id => $action) {
            $payload = self::payload($id, $action);
            if (!$payload['ok']) {
                $errors[$id] = $payload['error'];
                continue;
            }
            $parts[] = $payload['xml'];
            $ids[] = (int) $id;
            $skus[] = $payload['sku'];
        }

        return [
            'ok' => !empty($parts),
            'xml' => '<?xml version="1.0" encoding="UTF-8" ?>' . "\n<Request>\n" . implode("\n", $parts) . "\n</Request>",
            'ids' => $ids,
            'skus' => $skus,
            'errors' => $errors,
        ];
    }

    /**
     * Imágenes del producto (máx. 8, JPG/PNG). Para variaciones usa las del padre.
     *
     * @return string[]
     */
    public static function imageUrls($id)
    {
        $product = wc_get_product($id);
        if (!$product) {
            return [];
        }
        $ids = [];
        if ($product->get_image_id()) {
            $ids[] = (int) $product->get_image_id();
        }
        foreach ($product->get_gallery_image_ids() as $gallery_id) {
            $ids[] = (int) $gallery_id;
        }
        if ($product->is_type('variation')) {
            $parent = wc_get_product($product->get_parent_id());
            if ($parent) {
                if (!$ids && $parent->get_image_id()) {
                    $ids[] = (int) $parent->get_image_id();
                }
                foreach ($parent->get_gallery_image_ids() as $gallery_id) {
                    $ids[] = (int) $gallery_id;
                }
            }
        }

        $urls = [];
        foreach (array_unique($ids) as $attachment_id) {
            $mime = (string) get_post_mime_type($attachment_id);
            if (!in_array($mime, ['image/jpeg', 'image/png'], true)) {
                continue;
            }
            $url = wp_get_attachment_image_url($attachment_id, 'full');
            if ($url) {
                $urls[] = $url;
            }
            if (count($urls) >= 8) {
                break;
            }
        }

        return array_values(array_unique($urls));
    }

    /**
     * Huella de las imágenes actuales: evita reenviar un feed de imágenes si no
     * cambiaron. Seller Center limita los feeds y cada reenvío cuenta.
     */
    public static function imagesHash($id)
    {
        $urls = self::imageUrls($id);

        return $urls ? md5(implode('|', $urls)) : '';
    }

    /**
     * ¿Hay imágenes nuevas o modificadas desde el último envío aceptado?
     */
    public static function needsImages($id)
    {
        $hash = self::imagesHash($id);
        if ('' === $hash) {
            return false;
        }
        $own = self::read($id);

        return $hash !== (string) $own['images_hash'];
    }

    /**
     * XML de lote para la acción Image.
     *
     * @return array{ok:bool,xml:string,ids:int[],skus:string[],errors:array}
     */
    public static function imagesXml($ids)
    {
        $parts = [];
        $out_ids = [];
        $skus = [];
        foreach ((array) $ids as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }
            $urls = self::imageUrls($id);
            if (!$urls) {
                continue;
            }
            $sku = self::sellerSku($product);
            $xml = self::xmlTag('SellerSku', $sku) . '<Images>';
            foreach ($urls as $url) {
                $xml .= self::xmlTag('Image', $url);
            }
            $xml .= '</Images>';
            $parts[] = '<ProductImage>' . $xml . '</ProductImage>';
            $out_ids[] = (int) $id;
            $skus[] = $sku;
        }

        return [
            'ok' => !empty($parts),
            'xml' => '<?xml version="1.0" encoding="UTF-8" ?>' . "\n<Request>\n" . implode("\n", $parts) . "\n</Request>",
            'ids' => $out_ids,
            'skus' => $skus,
            'errors' => [],
        ];
    }

    /**
     * Guarda en el producto el estado que devuelve GetProducts.
     */
    public static function applyRemote($id, $node)
    {
        if (!is_array($node)) {
            return;
        }
        $unit = $node['BusinessUnits']['BusinessUnit'] ?? [];
        if (isset($unit[0])) {
            $unit = $unit[0];
        }
        if (!is_array($unit)) {
            $unit = [];
        }
        $status = (string) ($unit['Status'] ?? '');
        // La API documenta el campo como isPublished, pero el XML de ejemplo usa IsPublished.
        $is_published = $unit['IsPublished'] ?? ($unit['isPublished'] ?? '');
        $published = !empty($is_published) || 'active' === $status;
        $patch = [
            'remote_price' => (string) ($unit['Price'] ?? ''),
            'remote_stock' => (string) ($unit['Stock'] ?? ''),
            'remote_qc' => (string) ($node['QCStatus'] ?? ''),
            'remote_published' => $published ? 1 : 0,
            'remote_status' => 'active' === $status ? 'published' : 'inactive',
            'last_sync' => current_time('mysql'),
            'last_error' => '',
        ];
        self::patchHcs($id, $patch);
    }

    /**
     * Escribe solo claves de estado (o cualquier clave válida) sin tocar el resto.
     */
    public static function patchHcs($id, $data)
    {
        foreach ((array) $data as $key => $value) {
            if (array_key_exists($key, self::DEFAULTS)) {
                update_post_meta($id, HCS_META . $key, is_bool($value) ? (int) $value : $value);
            }
        }
        self::refreshParent($id);
    }

    /**
     * Recalcula en el padre variable el estado agregado de sus variaciones.
     *
     * La tabla y los filtros leen el meta del producto padre; sin esto un
     * producto variable nunca aparecería como publicado, en cola o con error.
     */
    public static function refreshParent($id)
    {
        $parent_id = self::parentId($id);
        if (!$parent_id) {
            return;
        }
        $parent = wc_get_product($parent_id);
        if (!$parent || !$parent->is_type('variable')) {
            return;
        }
        $summary = self::summary($parent_id);
        $last_sync = '';
        $last_error = '';
        foreach ($parent->get_children() as $child_id) {
            $child = self::read($child_id);
            if ('' !== $child['last_sync'] && $child['last_sync'] > $last_sync) {
                $last_sync = $child['last_sync'];
            }
            if ('' !== $child['last_error']) {
                $last_error = $child['last_error'];
            }
        }
        update_post_meta($parent_id, HCS_META . 'remote_status', $summary['status']);
        update_post_meta($parent_id, HCS_META . 'remote_published', $summary['published'] ? 1 : 0);
        update_post_meta($parent_id, HCS_META . 'last_sync', $last_sync);
        update_post_meta($parent_id, HCS_META . 'last_error', $last_error);
    }

    /**
     * Resumen de estado de un producto (para el padre variable, de sus variaciones).
     *
     * @return array{status:string,label:string,total:int,published:int}
     */
    public static function summary($id)
    {
        $product = wc_get_product($id);
        $ids = $product && $product->is_type('variable') ? $product->get_children() : [$id];
        $statuses = [];
        $published = 0;
        foreach ($ids as $child_id) {
            $meta = self::read($child_id);
            $statuses[] = $meta['remote_status'];
            if (!empty($meta['remote_published'])) {
                $published++;
            }
        }
        if (!$ids) {
            $status = '';
        } elseif (in_array('error', $statuses, true)) {
            $status = 'error';
        } elseif (in_array('feed_pending', $statuses, true)) {
            $status = 'feed_pending';
        } elseif (in_array('queued', $statuses, true)) {
            $status = 'queued';
        } elseif (count(array_unique($statuses)) > 1) {
            $status = 'partial';
        } elseif ('published' === $statuses[0]) {
            $status = 'published';
        } elseif ('inactive' === $statuses[0]) {
            $status = 'inactive';
        } else {
            $status = '';
        }

        return [
            'status' => $status,
            'label' => self::STATUS_LABELS[$status] ?? $status,
            'total' => count($ids),
            'published' => $published,
        ];
    }

    /**
     * Filtros normalizados de la tabla de productos.
     */
    public static function filters($source)
    {
        $source = (array) $source;
        $hc = sanitize_key($source['hc'] ?? '');
        if (!in_array($hc, ['enabled', 'disabled', 'queued', 'published', 'not_published', 'error'], true)) {
            $hc = '';
        }
        $type = sanitize_key($source['type'] ?? '');
        if (!in_array($type, self::TYPES, true)) {
            $type = '';
        }
        $per_page = absint($source['per_page'] ?? 50);

        return [
            's' => sanitize_text_field(wp_unslash($source['s'] ?? '')),
            'type' => $type,
            'hc' => $hc,
            'per_page' => in_array($per_page, self::PER_PAGE, true) ? $per_page : 50,
            'paged' => max(1, absint($source['paged'] ?? 1)),
        ];
    }

    private static function metaQuery($hc)
    {
        $key = HCS_META;
        switch ($hc) {
            case 'enabled':
                return [['key' => $key . 'enabled', 'value' => '1']];
            case 'disabled':
                return [
                    'relation' => 'OR',
                    ['key' => $key . 'enabled', 'value' => '0'],
                    ['key' => $key . 'enabled', 'compare' => 'NOT EXISTS'],
                ];
            case 'published':
                return [['key' => $key . 'remote_status', 'value' => ['published', 'inactive'], 'compare' => 'IN']];
            case 'queued':
                return [['key' => $key . 'remote_status', 'value' => ['queued', 'feed_pending'], 'compare' => 'IN']];
            case 'error':
                return [['key' => $key . 'remote_status', 'value' => 'error']];
            case 'not_published':
                return [
                    'relation' => 'OR',
                    ['key' => $key . 'remote_status', 'compare' => 'NOT EXISTS'],
                    ['key' => $key . 'remote_status', 'value' => ''],
                ];
        }

        return [];
    }

    private static function queryArgs($f)
    {
        $args = [
            'post_type' => 'product',
            'post_status' => self::STATUSES,
            'orderby' => 'title',
            'order' => 'ASC',
            'tax_query' => [
                'relation' => 'AND',
                [
                    'taxonomy' => 'product_type',
                    'field' => 'slug',
                    'terms' => $f['type'] ? [$f['type']] : self::TYPES,
                ],
            ],
        ];
        $meta_query = self::metaQuery($f['hc']);
        if ($meta_query) {
            $args['meta_query'] = $meta_query;
        }
        if ('' !== $f['s']) {
            $data_store = WC_Data_Store::load('product');
            $ids = $data_store->search_products($f['s'], '', true, true);
            $parents = [];
            foreach ($ids as $id) {
                $parent = wp_get_post_parent_id($id);
                $parents[] = $parent ? $parent : $id;
            }
            $args['post__in'] = $parents ? array_values(array_unique(array_map('absint', $parents))) : [0];
        }

        return $args;
    }

    /**
     * @return array{total:int,pages:int,items:array}
     */
    public static function page($f)
    {
        $query = new WP_Query(array_merge(self::queryArgs($f), [
            'posts_per_page' => $f['per_page'],
            'paged' => $f['paged'],
        ]));
        $items = [];
        foreach ($query->posts as $post) {
            $product = wc_get_product($post);
            if ($product) {
                $items[] = self::row($product);
            }
        }

        return [
            'total' => (int) $query->found_posts,
            'pages' => (int) $query->max_num_pages,
            'items' => $items,
        ];
    }

    public static function row($product)
    {
        $id = $product->get_id();
        $meta = self::meta($id);
        $summary = self::summary($id);

        return [
            'id' => $id,
            'type' => $product->get_type(),
            'name' => $product->get_name(),
            'sku' => $product->get_sku('edit'),
            'seller_sku' => self::sellerSku($product),
            'image' => wp_get_attachment_image_url($product->get_image_id(), 'thumbnail'),
            'price' => wp_strip_all_tags($product->get_price_html()),
            'stock' => $product->get_stock_quantity(),
            'stock_status' => $product->get_stock_status(),
            'woo_status' => get_post_status($id),
            'enabled' => $meta['enabled'],
            'status' => $summary['status'],
            'status_label' => $summary['label'],
            'variations' => $summary['total'],
            'published' => $summary['published'],
            'last_sync' => $meta['last_sync'],
            'last_error' => $meta['last_error'],
            'edit' => get_edit_post_link($id, 'raw'),
        ];
    }

    /**
     * Todos los IDs que cumplen los filtros (para acciones masivas).
     *
     * @return int[]
     */
    public static function idsByFilters($f)
    {
        return array_map('intval', get_posts(array_merge(self::queryArgs($f), [
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
        ])));
    }
}
