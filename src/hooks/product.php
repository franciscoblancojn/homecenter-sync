<?php

/**
 * Sincronización al guardar o borrar productos de WooCommerce.
 *
 * Al guardar solo se encola: la llamada real a Homecenter la hace el cron (o el
 * botón «Procesar ahora») para no bloquear el admin y respetar el límite de feeds.
 */
class HCS_HOOK_PRODUCT
{
    public static function init()
    {
        add_action('woocommerce_update_product', [self::class, 'onSave'], 30, 1);
        add_action('woocommerce_new_product', [self::class, 'onSave'], 30, 1);
        add_action('before_delete_post', [self::class, 'onDelete'], 10, 1);
    }

    public static function onSave($product_id)
    {
        if (!HCS_USE_DATA_CONFIG::configured()) {
            return;
        }
        $config = HCS_PRODUCT::config();
        if (empty($config['sync_on_save'])) {
            return;
        }
        $product = wc_get_product($product_id);
        if (!$product || !in_array($product->get_type(), ['simple', 'variable', 'variation'], true)) {
            return;
        }
        $meta = HCS_PRODUCT::meta($product_id);
        $summary = HCS_PRODUCT::summary($product_id);
        // También se encola al deshabilitar: así el producto publicado se despublica.
        if (empty($meta['enabled']) && empty($summary['published'])) {
            return;
        }
        HCS_QUEUE::add([$product_id], 'save');
    }

    public static function onDelete($post_id)
    {
        if (!in_array(get_post_type($post_id), ['product', 'product_variation'], true)) {
            return;
        }
        $meta = HCS_PRODUCT::meta($post_id);
        $summary = HCS_PRODUCT::summary($post_id);
        if (empty($meta['enabled']) && empty($summary['published'])) {
            return;
        }
        $product = wc_get_product($post_id);
        if (!$product) {
            return;
        }
        // Un producto variable se borra con todas sus variaciones: cada SellerSku cuenta.
        $skus = [];
        if ($product->is_type('variable')) {
            foreach ($product->get_children() as $child_id) {
                $child = wc_get_product($child_id);
                if ($child) {
                    $skus[] = HCS_PRODUCT::sellerSku($child);
                }
            }
        } else {
            $skus[] = HCS_PRODUCT::sellerSku($product);
        }
        HCS_QUEUE::addRemove($skus);
    }
}

HCS_HOOK_PRODUCT::init();
