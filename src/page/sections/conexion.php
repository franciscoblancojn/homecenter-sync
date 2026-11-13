<?php

$catalog_time = HCS_CATALOG::updatedAt();
$brands_count = count(HCS_CATALOG::brands());
$categories_count = count(HCS_CATALOG::categories());
$queue_count = HCS_QUEUE::count();

?>
<form method="post" action="<?= esc_url(admin_url('admin-post.php')) ?>" class="hcs-form">
    <input type="hidden" name="action" value="<?= esc_attr(HCS_API_SETTINGS::ACTION) ?>">
    <input type="hidden" name="hcs_tab" value="conexion" id="hcs-tab">
    <?php wp_nonce_field(HCS_API_SETTINGS::ACTION); ?>

<p class="description">Credenciales de <strong>Seller Center (Falabella/Sodimac)</strong> usadas por Homecenter. La API Key se guarda pero no se vuelve a mostrar.</p>

<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Activar</th>
        <td>
            <label>
                <input type="checkbox" name="hcs[enabled]" value="1" <?= checked($config['enabled'], 1, false) ?>>
                Activar sincronización con Homecenter
            </label>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-endpoint">URL de la API</label></th>
        <td>
            <input type="url" id="hcs-endpoint" name="hcs[endpoint]" value="<?= esc_attr($config['endpoint']) ?>" class="regular-text" placeholder="https://sellercenter-api.falabella.com/">
            <p class="description">Configurable por si Seller Center cambia el endpoint. Debe ser la URL base (sin parámetros).</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-user-id">User ID</label></th>
        <td>
            <input type="text" id="hcs-user-id" name="hcs[user_id]" value="<?= esc_attr($config['user_id']) ?>" class="regular-text" autocomplete="off">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-api-key">API Key</label></th>
        <td>
            <input type="password" id="hcs-api-key" name="hcs[api_key]" value="" class="regular-text" autocomplete="new-password" placeholder="<?= $config['api_key'] ? 'Guardada (deja en blanco para conservarla)' : 'API Key de Seller Center' ?>">
            <p class="description">Solo se usa para firmar las peticiones. Deja el campo vacío para conservar la clave guardada.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-seller-id">Seller ID</label></th>
        <td>
            <input type="text" id="hcs-seller-id" name="hcs[seller_id]" value="<?= esc_attr($config['seller_id']) ?>" class="regular-text">
            <p class="description">Se usa en la cabecera User-Agent requerida por la API.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-operator">Operador / país</label></th>
        <td>
            <select id="hcs-operator" name="hcs[operator_code]">
                <?php foreach (HCS_USE_DATA_CONFIG::OPERATORS as $code => $label) : ?>
                    <option value="<?= esc_attr($code) ?>" <?= selected($config['operator_code'], $code, false) ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-integration">Tipo de integración</label></th>
        <td>
            <input type="text" id="hcs-integration" name="hcs[integration_type]" value="<?= esc_attr($config['integration_type']) ?>" class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row">Prueba y catálogo</th>
        <td>
            <p>
                <button type="button" class="button" id="hcs-test">Probar conexión</button>
                <button type="button" class="button" id="hcs-catalog">Actualizar marcas y categorías</button>
            </p>
            <p class="description">
                Catálogo local: <strong><?= (int) $brands_count ?></strong> marcas, <strong><?= (int) $categories_count ?></strong> categorías hoja.
                <?php if ($catalog_time) : ?>
                    Actualizado: <?= esc_html(date_i18n('Y-m-d H:i', $catalog_time)) ?>.
                <?php else : ?>
                    Aún no se ha descargado.
                <?php endif; ?>
            </p>
            <p class="description">Cola pendiente: <strong id="hcs-queue-count"><?= (int) $queue_count ?></strong> ítem(s). <button type="button" class="button button-small" id="hcs-process">Procesar cola ahora</button></p>
            <p id="hcs-connection-message" class="hcs-inline-message"></p>
        </td>
    </tr>
</table>

<hr>
<h2>Valores por defecto</h2>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row"><label for="hcs-price-mode">Precios</label></th>
        <td>
            <select id="hcs-price-mode" name="hcs[price_mode]">
                <?php foreach (HCS_USE_DATA_CONFIG::PRICE_MODES as $mode => $label) : ?>
                    <option value="<?= esc_attr($mode) ?>" <?= selected($config['price_mode'], $mode, false) ?>><?= esc_html($label) ?></option>
                <?php endforeach; ?>
            </select>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-default-category">Categoría Homecenter</label></th>
        <td>
            <input type="text" id="hcs-default-category" name="hcs[default_category]" value="<?= esc_attr($config['default_category']) ?>" class="regular-text" list="hcs-default-categories">
            <datalist id="hcs-default-categories">
                <?php foreach (HCS_CATALOG::categories() as $category) : ?>
                    <option value="<?= esc_attr($category['id']) ?>"><?= esc_html($category['path']) ?></option>
                <?php endforeach; ?>
            </datalist>
            <p class="description">ID de categoría hoja por defecto (los productos pueden definir la suya).</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-default-brand">Marca</label></th>
        <td>
            <input type="text" id="hcs-default-brand" name="hcs[default_brand]" value="<?= esc_attr($config['default_brand']) ?>" class="regular-text" list="hcs-default-brands">
            <datalist id="hcs-default-brands">
                <?php foreach (HCS_CATALOG::brands() as $brand) : ?>
                    <option value="<?= esc_attr($brand) ?>"></option>
                <?php endforeach; ?>
            </datalist>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-default-condition">Condición</label></th>
        <td>
            <input type="text" id="hcs-default-condition" name="hcs[default_condition]" value="<?= esc_attr($config['default_condition']) ?>" class="regular-text">
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-default-tax">IVA %</label></th>
        <td>
            <input type="text" id="hcs-default-tax" name="hcs[default_tax]" value="<?= esc_attr($config['default_tax']) ?>" class="small-text">
        </td>
    </tr>
    <tr>
        <th scope="row">Paquete (cm / kg)</th>
        <td>
            <label>Alto <input type="text" name="hcs[package_height]" value="<?= esc_attr($config['package_height']) ?>" class="small-text"></label>
            <label>Ancho <input type="text" name="hcs[package_width]" value="<?= esc_attr($config['package_width']) ?>" class="small-text"></label>
            <label>Largo <input type="text" name="hcs[package_length]" value="<?= esc_attr($config['package_length']) ?>" class="small-text"></label>
            <label>Peso <input type="text" name="hcs[package_weight]" value="<?= esc_attr($config['package_weight']) ?>" class="small-text"></label>
            <p class="description">Se usan cuando el producto no tiene dimensiones/peso en WooCommerce.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-fallback-stock">Stock por defecto</label></th>
        <td>
            <input type="number" min="0" step="1" id="hcs-fallback-stock" name="hcs[fallback_stock]" value="<?= esc_attr($config['fallback_stock']) ?>" class="small-text">
            <p class="description">Se usa si el producto no gestiona stock en WooCommerce.</p>
        </td>
    </tr>
    <tr>
        <th scope="row"><label for="hcs-sku-prefix">Prefijo SellerSku</label></th>
        <td>
            <input type="text" id="hcs-sku-prefix" name="hcs[sku_prefix]" value="<?= esc_attr($config['sku_prefix']) ?>" class="regular-text">
            <p class="description">Se usa para generar SellerSku cuando el producto no tiene SKU en WooCommerce.</p>
        </td>
    </tr>
</table>

<hr>
<h2>Sincronización</h2>
<table class="form-table" role="presentation">
    <tr>
        <th scope="row">Automática</th>
        <td>
            <label><input type="checkbox" name="hcs[sync_on_save]" value="1" <?= checked($config['sync_on_save'], 1, false) ?>> Encolar al guardar productos habilitados</label><br>
            <label><input type="checkbox" name="hcs[sync_images]" value="1" <?= checked($config['sync_images'], 1, false) ?>> Enviar imágenes (JPG/PNG, máx. 8)</label>
        </td>
    </tr>
    <tr>
        <th scope="row">Lotes y límite</th>
        <td>
            <label>Tamaño de lote <input type="number" min="1" max="1000" name="hcs[batch_size]" value="<?= esc_attr($config['batch_size']) ?>" class="small-text"></label>
            <label>Intervalo mínimo entre feeds (s) <input type="number" min="0" max="3600" name="hcs[min_feed_interval]" value="<?= esc_attr($config['min_feed_interval']) ?>" class="small-text"></label>
            <p class="description">Seller Center exige esperar entre feeds; el valor recomendado es 120 segundos.</p>
        </td>
    </tr>
</table>

<p class="submit">
    <?php submit_button('Guardar cambios', 'primary', 'submit', false); ?>
</p>
</form>
