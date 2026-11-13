<?php

/**
 * Tabla de productos con filtros y acciones masivas.
 *
 * La usan la pestaña «Productos» y la página «Carga Homecenter».
 *
 * Variables esperadas antes de incluir:
 * - array    $f           Filtros normalizados (HCS_PRODUCT::filters).
 * - array    $result      Resultado de HCS_PRODUCT::page().
 * - callable $build_url   Genera URLs conservando los filtros.
 * - array    $base        Parámetros fijos de la página (page, tab…).
 * - array    $bulk        Acciones: [['action','label','class'], …].
 * - string   $message_id  Id del párrafo de mensajes.
 * - string   $scope_label Texto del checkbox «aplicar al filtro».
 */
$f = isset($f) ? $f : HCS_PRODUCT::filters([]);
$result = isset($result) ? $result : HCS_PRODUCT::page($f);
$base = isset($base) ? (array) $base : [];
$bulk = isset($bulk) ? (array) $bulk : [];
$message_id = isset($message_id) ? $message_id : 'hcs-products-message';
$scope_label = isset($scope_label) ? $scope_label : 'Aplicar a todos los resultados del filtro (no solo a la página actual)';
$build_url = isset($build_url) && is_callable($build_url) ? $build_url : function ($extra) {
    return add_query_arg($extra, admin_url('admin.php'));
};

?>
<form method="get" action="<?= esc_url(admin_url('admin.php')) ?>" class="hcs-filters">
    <?php foreach ($base as $name => $value) : ?>
        <input type="hidden" name="<?= esc_attr($name) ?>" value="<?= esc_attr($value) ?>">
    <?php endforeach; ?>
    <input type="search" name="s" value="<?= esc_attr($f['s']) ?>" placeholder="Buscar por nombre o SKU" class="regular-text">
    <select name="type">
        <option value="">Todos los tipos</option>
        <option value="simple" <?= selected($f['type'], 'simple', false) ?>>Simples</option>
        <option value="variable" <?= selected($f['type'], 'variable', false) ?>>Variables</option>
    </select>
    <select name="hc">
        <option value="">Todos los estados Homecenter</option>
        <option value="enabled" <?= selected($f['hc'], 'enabled', false) ?>>Habilitados</option>
        <option value="disabled" <?= selected($f['hc'], 'disabled', false) ?>>No habilitados</option>
        <option value="published" <?= selected($f['hc'], 'published', false) ?>>Publicados/inactivos</option>
        <option value="queued" <?= selected($f['hc'], 'queued', false) ?>>En cola / revisión</option>
        <option value="not_published" <?= selected($f['hc'], 'not_published', false) ?>>Sin publicar</option>
        <option value="error" <?= selected($f['hc'], 'error', false) ?>>Con error</option>
    </select>
    <select name="per_page">
        <?php foreach (HCS_PRODUCT::PER_PAGE as $pp) : ?>
            <option value="<?= (int) $pp ?>" <?= selected($f['per_page'], $pp, false) ?>><?= (int) $pp ?> por página</option>
        <?php endforeach; ?>
    </select>
    <?php submit_button('Filtrar', 'secondary', '', false); ?>
    <a href="<?= esc_url($build_url(['s' => '', 'type' => '', 'hc' => '', 'paged' => 1])) ?>" class="button">Limpiar</a>
</form>

<div class="hcs-bulk-actions">
    <strong>Acciones sobre la selección:</strong>
    <?php foreach ($bulk as $item) : ?>
        <button type="button" class="button <?= esc_attr($item['class'] ?? '') ?> hcs-bulk" data-action="<?= esc_attr($item['action']) ?>" data-message="<?= esc_attr($message_id) ?>"><?= esc_html($item['label']) ?></button>
    <?php endforeach; ?>
    <label class="hcs-inline">
        <input type="checkbox" id="hcs-scope-filtered" value="1">
        <?= esc_html($scope_label) ?>
    </label>
</div>
<p id="<?= esc_attr($message_id) ?>" class="hcs-inline-message"></p>

<p class="hcs-muted">Resultados: <?= (int) $result['total'] ?>. Cola pendiente: <strong class="hcs-queue-count"><?= (int) HCS_QUEUE::count() ?></strong>.</p>

<table class="wp-list-table widefat fixed striped hcs-products-table">
    <thead>
        <tr>
            <td class="manage-column column-cb check-column"><input type="checkbox" id="hcs-check-all"></td>
            <th>Producto</th>
            <th>Tipo</th>
            <th>SKU / SellerSku</th>
            <th>Precio / stock</th>
            <th>Homecenter</th>
            <th>Última sync</th>
            <th></th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$result['items']) : ?>
            <tr><td colspan="8">No hay productos con esos filtros.</td></tr>
        <?php endif; ?>
        <?php foreach ($result['items'] as $item) : ?>
            <tr data-id="<?= (int) $item['id'] ?>">
                <th scope="row" class="check-column"><input type="checkbox" class="hcs-check" value="<?= (int) $item['id'] ?>"></th>
                <td>
                    <?php if ($item['image']) : ?>
                        <img src="<?= esc_url($item['image']) ?>" alt="" class="hcs-thumb">
                    <?php endif; ?>
                    <strong><?= esc_html($item['name']) ?></strong>
                    <div class="hcs-muted">Woo: <?= esc_html($item['woo_status']) ?></div>
                    <?php if ($item['variations']) : ?>
                        <div class="hcs-muted">Variaciones: <?= (int) $item['published'] ?>/<?= (int) $item['variations'] ?> publicadas</div>
                    <?php endif; ?>
                </td>
                <td><?= esc_html($item['type']) ?></td>
                <td>
                    <div><?= esc_html($item['sku'] ? $item['sku'] : '—') ?></div>
                    <div class="hcs-muted"><?= esc_html($item['seller_sku']) ?></div>
                </td>
                <td>
                    <div><?= wp_kses_post($item['price']) ?></div>
                    <div class="hcs-muted">Stock: <?= null === $item['stock'] ? esc_html($item['stock_status']) : (int) $item['stock'] ?></div>
                </td>
                <td>
                    <span class="hcs-badge hcs-badge--<?= esc_attr($item['status'] ? $item['status'] : 'none') ?>"><?= esc_html($item['status_label']) ?></span>
                    <?php if ($item['enabled']) : ?><div class="hcs-muted">Habilitado</div><?php endif; ?>
                    <?php if ($item['last_error']) : ?><div class="hcs-error"><?= esc_html($item['last_error']) ?></div><?php endif; ?>
                </td>
                <td class="hcs-muted"><?= esc_html($item['last_sync'] ? $item['last_sync'] : '—') ?></td>
                <td>
                    <?php if ($item['edit']) : ?><a href="<?= esc_url($item['edit']) ?>" class="button button-small">Editar</a><?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<?php if ($result['pages'] > 1) : ?>
    <div class="tablenav bottom"><div class="tablenav-pages">
        <?php for ($p = 1; $p <= $result['pages']; $p++) : ?>
            <?php if ($p === (int) $f['paged']) : ?>
                <span class="tablenav-pages-navspan button disabled" aria-hidden="true"><?= (int) $p ?></span>
            <?php else : ?>
                <a class="button" href="<?= esc_url($build_url(['paged' => $p])) ?>"><?= (int) $p ?></a>
            <?php endif; ?>
        <?php endfor; ?>
    </div></div>
<?php endif; ?>
