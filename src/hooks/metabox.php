<?php

use franciscoblancojn\wordpress_utils\FWUPage;

/**
 * Caja «Homecenter» en la edición del producto.
 *
 * Aquí se marca si el producto se publica, se define el mapeo (SellerSku,
 * categoría, marca, atributos) y se ven el estado y los errores del feed.
 */
class HCS_HOOK_METABOX
{
    const NONCE = 'hcs_metabox_nonce';

    public static function init()
    {
        add_action('add_meta_boxes', [self::class, 'add']);
        add_action('save_post_product', [self::class, 'save'], 20, 1);
        add_action('admin_enqueue_scripts', [self::class, 'assets']);
    }

    public static function add()
    {
        add_meta_box(
            'hcs-homecenter',
            'Homecenter',
            [self::class, 'render'],
            'product',
            'side',
            'high'
        );
    }

    public static function assets($hook)
    {
        if (!in_array($hook, ['post.php', 'post-new.php'], true)) {
            return;
        }
        $screen = function_exists('get_current_screen') ? get_current_screen() : null;
        if (!$screen || 'product' !== $screen->post_type) {
            return;
        }
        $css = HCS_DIR . 'src/css/admin.css';
        $js = HCS_DIR . 'src/js/admin.js';
        wp_enqueue_style('hcs-admin', HCS_URL . 'src/css/admin.css', [], filemtime($css));
        wp_enqueue_script('hcs-admin', HCS_URL . 'src/js/admin.js', ['jquery'], filemtime($js), true);
        wp_localize_script('hcs-admin', 'HCS_ADMIN', self::localized());
    }

    public static function localized()
    {
        return [
            'ajax' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce(HCS_API_PRODUCTS::NONCE),
            'confirm_deactivate' => '¿Despublicar de Homecenter? El producto quedará inactivo en el marketplace.',
            'processing' => 'Procesando…',
            'error' => 'No se pudo completar la operación.',
        ];
    }

    /**
     * @param WP_Post $post
     */
    public static function render($post)
    {
        $product = wc_get_product($post->ID);
        if (!$product) {
            echo '<p>No se encontró el producto.</p>';

            return;
        }
        $meta = HCS_PRODUCT::meta($post->ID);
        $summary = HCS_PRODUCT::summary($post->ID);
        $config = HCS_PRODUCT::config();
        $configured = HCS_USE_DATA_CONFIG::configured($config);
        $categories = HCS_CATALOG::categories();
        $brands = HCS_CATALOG::brands();
        $seller_sku = HCS_PRODUCT::sellerSku($product);
        wp_nonce_field(self::NONCE, self::NONCE);
        ?>
        <div class="hcs-metabox">
            <?php if (!$configured) : ?>
                <p class="hcs-error">Faltan las credenciales. <a href="<?= esc_url(admin_url('admin.php?page=' . HCS_KEY . '&tab=conexion')) ?>">Configurar Homecenter</a>.</p>
            <?php endif; ?>

            <p>
                <label>
                    <input type="checkbox" name="hcs[enabled]" value="1" <?= checked($meta['enabled'], 1, false) ?>>
                    <strong>Publicar en Homecenter</strong>
                </label>
            </p>

            <p class="hcs-state">
                Estado:
                <span class="hcs-badge hcs-badge--<?= esc_attr($summary['status'] ? $summary['status'] : 'none') ?>"><?= esc_html($summary['label']) ?></span>
                <?php if ($product->is_type('variable')) : ?>
                    <span class="hcs-muted">(<?= (int) $summary['published'] ?>/<?= (int) $summary['total'] ?> variaciones)</span>
                <?php endif; ?>
            </p>
            <?php if (!empty($meta['last_sync'])) : ?>
                <p class="hcs-muted">Última sincronización: <?= esc_html($meta['last_sync']) ?></p>
            <?php endif; ?>
            <?php if (!empty($meta['feed_id'])) : ?>
                <p class="hcs-muted">Feed: <code><?= esc_html($meta['feed_id']) ?></code></p>
            <?php endif; ?>
            <?php if (!empty($meta['last_error'])) : ?>
                <p class="hcs-error"><?= esc_html($meta['last_error']) ?></p>
            <?php endif; ?>

            <p>
                <label for="hcs-seller-sku"><strong>SellerSku</strong></label>
                <input type="text" class="widefat" id="hcs-seller-sku" name="hcs[seller_sku]" value="<?= esc_attr($meta['seller_sku']) ?>" placeholder="<?= esc_attr($seller_sku) ?>">
                <span class="hcs-muted">Vacío = SKU de WooCommerce (<?= esc_html($seller_sku) ?>).</span>
            </p>

            <p>
                <label for="hcs-category"><strong>Categoría Homecenter</strong></label>
                <input type="text" class="widefat" id="hcs-category" name="hcs[category]" value="<?= esc_attr($meta['category']) ?>" list="hcs-categories" placeholder="<?= esc_attr($config['default_category']) ?>">
                <datalist id="hcs-categories">
                    <?php foreach ($categories as $category) : ?>
                        <option value="<?= esc_attr($category['id']) ?>"><?= esc_html($category['id'] . ' — ' . $category['path']) ?></option>
                    <?php endforeach; ?>
                </datalist>
                <span class="hcs-muted">Solo categorías hoja. Actualiza la lista en <a href="<?= esc_url(admin_url('admin.php?page=' . HCS_KEY . '&tab=conexion')) ?>">Conexión</a>.</span>
            </p>

            <p>
                <label for="hcs-brand"><strong>Marca</strong></label>
                <input type="text" class="widefat" id="hcs-brand" name="hcs[brand]" value="<?= esc_attr($meta['brand']) ?>" list="hcs-brands" placeholder="<?= esc_attr($config['default_brand']) ?>">
                <datalist id="hcs-brands">
                    <?php foreach ($brands as $brand) : ?>
                        <option value="<?= esc_attr($brand) ?>"></option>
                    <?php endforeach; ?>
                </datalist>
            </p>

            <p>
                <label for="hcs-gtin"><strong>EAN / UPC</strong></label>
                <input type="text" class="widefat" id="hcs-gtin" name="hcs[gtin]" value="<?= esc_attr($meta['gtin']) ?>" placeholder="Opcional; si está vacío se usa el SKU numérico">
            </p>

            <div class="hcs-grid">
                <p>
                    <label for="hcs-tax"><strong>IVA %</strong></label>
                    <input type="text" class="widefat" id="hcs-tax" name="hcs[tax]" value="<?= esc_attr($meta['tax']) ?>" placeholder="<?= esc_attr($config['default_tax']) ?>">
                </p>
                <p>
                    <label for="hcs-condition"><strong>Condición</strong></label>
                    <input type="text" class="widefat" id="hcs-condition" name="hcs[condition]" value="<?= esc_attr($meta['condition']) ?>" placeholder="<?= esc_attr($config['default_condition']) ?>">
                </p>
            </div>

            <p>
                <label for="hcs-status"><strong>Estado en Homecenter</strong></label>
                <select class="widefat" id="hcs-status" name="hcs[status]">
                    <option value="active" <?= selected($meta['status'], 'active', false) ?>>Activo</option>
                    <option value="inactive" <?= selected($meta['status'], 'inactive', false) ?>>Inactivo</option>
                </select>
            </p>

            <p>
                <label for="hcs-attributes"><strong>Atributos de la categoría</strong></label>
                <textarea class="widefat" id="hcs-attributes" name="hcs[attributes]" rows="3" placeholder="Material=Madera&#10;Color=Blanco"><?= esc_textarea($meta['attributes']) ?></textarea>
                <span class="hcs-muted">Una línea por atributo: <code>Nombre=Valor</code>. Consulta los obligatorios con GetCategoryAttributes.</span>
            </p>

            <?php if ($product->is_type('variable')) : ?>
                <p>
                    <label for="hcs-variation-map"><strong>Mapeo de variaciones</strong></label>
                    <textarea class="widefat" id="hcs-variation-map" name="hcs[variation_map]" rows="3" placeholder="pa_color=Color&#10;pa_talla=Talla"><?= esc_textarea($meta['variation_map']) ?></textarea>
                    <span class="hcs-muted">Atributo de WooCommerce = nombre del atributo en Homecenter.</span>
                </p>

                <p><strong>Variaciones</strong></p>
                <table class="widefat striped hcs-variations">
                    <thead>
                        <tr>
                            <th>SellerSku</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($product->get_children() as $child_id) : ?>
                            <?php
                            $child = wc_get_product($child_id);
                            if (!$child) {
                                continue;
                            }
                            $child_meta = HCS_PRODUCT::meta($child_id);
                            $child_summary = HCS_PRODUCT::summary($child_id);
                            ?>
                            <tr>
                                <td><?= esc_html(HCS_PRODUCT::sellerSku($child)) ?></td>
                                <td>
                                    <span class="hcs-badge hcs-badge--<?= esc_attr($child_summary['status'] ? $child_summary['status'] : 'none') ?>"><?= esc_html($child_summary['label']) ?></span>
                                    <?php if (!empty($child_meta['last_error'])) : ?>
                                        <span class="hcs-error"><?= esc_html($child_meta['last_error']) ?></span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <p class="hcs-muted">Guarda el producto para aplicar los cambios. La sincronización la procesa el cron cada 5 minutos.</p>

            <?php if ($configured) : ?>
                <p class="hcs-actions">
                    <button type="button" class="button button-primary hcs-product-action" data-id="<?= (int) $post->ID ?>" data-action="publish">
                        <?= $meta['enabled'] ? 'Encolar actualización' : 'Publicar en Homecenter' ?>
                    </button>
                    <?php if (!empty($summary['published'])) : ?>
                        <button type="button" class="button hcs-product-action" data-id="<?= (int) $post->ID ?>" data-action="deactivate">Despublicar</button>
                    <?php endif; ?>
                </p>
                <p class="hcs-message" data-for="<?= (int) $post->ID ?>"></p>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function save($post_id)
    {
        if (!isset($_POST[self::NONCE])) {
            return;
        }
        if (!wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE])), self::NONCE)) {
            return;
        }
        if ((defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) || !current_user_can('edit_post', $post_id)) {
            return;
        }
        $input = wp_unslash($_POST['hcs'] ?? []);
        if (!is_array($input)) {
            return;
        }
        $status = sanitize_key($input['status'] ?? 'active');
        $enabled = empty($input['enabled']) ? 0 : 1;
        // La ficha guarda después de WooCommerce, que ya disparó woocommerce_update_product
        // con el meta anterior: aquí detectamos los cambios que onSave no pudo ver.
        $before = HCS_PRODUCT::meta($post_id);
        $was_published = HCS_PRODUCT::isPublished($post_id);

        HCS_PRODUCT::patch($post_id, [
            'enabled' => $enabled,
            'seller_sku' => sanitize_text_field($input['seller_sku'] ?? ''),
            'category' => sanitize_text_field($input['category'] ?? ''),
            'brand' => sanitize_text_field($input['brand'] ?? ''),
            'gtin' => sanitize_text_field($input['gtin'] ?? ''),
            'condition' => sanitize_text_field($input['condition'] ?? ''),
            'tax' => sanitize_text_field($input['tax'] ?? ''),
            'attributes' => sanitize_textarea_field($input['attributes'] ?? ''),
            'variation_map' => sanitize_textarea_field($input['variation_map'] ?? ''),
            'status' => in_array($status, ['active', 'inactive'], true) ? $status : 'active',
        ]);

        // Primera publicación desde la ficha (0 → 1) o despublicar algo ya publicado.
        if (($enabled && empty($before['enabled'])) || (!$enabled && $was_published)) {
            HCS_QUEUE::add([$post_id], 'metabox');
        }
    }
}

HCS_HOOK_METABOX::init();
