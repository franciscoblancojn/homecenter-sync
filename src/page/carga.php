<?php

/**
 * Página «Carga Homecenter»: sube productos de WooCommerce al marketplace.
 *
 * Es la dirección WooCommerce → Homecenter. Las acciones se encolan y el cron
 * las envía respetando el límite de feeds de Seller Center; aquí también se
 * puede procesar la cola al momento.
 */

$configured = HCS_USE_DATA_CONFIG::configured();
$f = HCS_PRODUCT::filters($_GET); // phpcs:ignore WordPress.Security.NonceVerification.Recommended
$result = HCS_PRODUCT::page($f);
$base = ['page' => HCS_KEY . '-carga'];
$build_url = function ($extra) use ($base, $f) {
    return add_query_arg(array_filter(array_merge($base, $f, $extra), function ($v) {
        return '' !== $v && null !== $v;
    }), admin_url('admin.php'));
};
$bulk = [
    ['action' => 'publish', 'label' => 'Cargar en Homecenter', 'class' => 'button-primary'],
    ['action' => 'remote', 'label' => 'Consultar estado remoto', 'class' => ''],
];
$message_id = 'hcs-carga-message';
$scope_label = 'Cargar todos los resultados del filtro (no solo los de esta página)';

?>
<div class="wrap hcs-page">
    <h1>Carga Homecenter <span class="hcs-version">v<?= esc_html(HCS_get_version()) ?></span></h1>
    <p class="hcs-muted">Sube productos de <strong>WooCommerce → Homecenter</strong>. Selecciona los productos, se encolan y el cron los envía respetando el límite de feeds de Seller Center. Los productos variables cargan todas sus variaciones.</p>

    <?php if (!$configured) : ?>
        <div class="notice notice-warning inline">
            <p>Faltan las credenciales de Seller Center. <a href="<?= esc_url(admin_url('admin.php?page=' . HCS_KEY . '&tab=conexion')) ?>">Configurarlas en Conexión</a>.</p>
        </div>
    <?php endif; ?>

    <p class="hcs-carga-queue">
        Cola pendiente: <strong class="hcs-queue-count"><?= (int) HCS_QUEUE::count() ?></strong> ítem(s).
        <button type="button" class="button button-small" id="hcs-process-carga">Procesar cola ahora</button>
    </p>

    <?php require HCS_DIR . 'src/page/partials/products-table.php'; ?>
</div>
