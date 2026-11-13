<?php

$f = HCS_PRODUCT::filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$result = HCS_PRODUCT::page($f);
$base = ['page' => HCS_KEY, 'tab' => 'productos'];
$build_url = function ($extra) use ($base, $f) {
    return add_query_arg(array_filter(array_merge($base, $f, $extra), function ($v) {
        return '' !== $v && null !== $v;
    }), admin_url('admin.php'));
};
$bulk = [
    ['action' => 'publish', 'label' => 'Publicar / actualizar', 'class' => 'button-primary'],
    ['action' => 'deactivate', 'label' => 'Despublicar', 'class' => ''],
    ['action' => 'remote', 'label' => 'Consultar estado remoto', 'class' => ''],
];
$message_id = 'hcs-products-message';

?>
<p class="description">Selecciona productos de WooCommerce para publicar o actualizar en Homecenter. Los productos variables publican sus variaciones. Las acciones se encolan y se procesan por cron o con «Procesar cola ahora».</p>

<?php require HCS_DIR . 'src/page/partials/products-table.php'; ?>
