<?php

use franciscoblancojn\wordpress_utils\FWUPage;
use franciscoblancojn\wordpress_utils\FWURespond;

echo FWUPage::css();

$TAGS = [
    ['key' => 'conexion', 'title' => 'Conexión'],
    ['key' => 'productos', 'title' => 'Productos'],
    ['key' => 'feeds', 'title' => 'Feeds'],
    ['key' => 'registro', 'title' => 'Registro'],
];
$keys = wp_list_pluck($TAGS, 'key');
$defaultTag = in_array($_GET['tab'] ?? '', $keys, true) ? $_GET['tab'] : $TAGS[0]['key']; // phpcs:ignore WordPress.Security.NonceVerification
$config = (new HCS_USE_DATA_CONFIG())->get();
$respond = get_transient('hcs_respond');

?>
<div id="page-<?= esc_attr(HCS_KEY) ?>" class="wrap hcs-page">
    <h1>Homecenter Sync <span class="hcs-version">v<?= esc_html(HCS_get_version()) ?></span></h1>
    <p class="hcs-muted">Sincroniza productos de WooCommerce con el marketplace de Homecenter (Seller Center Falabella/Sodimac). La cola se procesa por cron cada 5 minutos.</p>
    <?php
    if ($respond) {
        delete_transient('hcs_respond');
        FWURespond::render($respond);
    }
    ?>
    <?php FWUPage::tabs($TAGS, $defaultTag); ?>
    <?php foreach ($TAGS as $value) : ?>
        <div class="tab-content <?= $value['key'] === $defaultTag ? 'nav-tab-active' : '' ?>" id="<?= esc_attr($value['key']) ?>">
            <?php require HCS_DIR . 'src/page/sections/' . $value['key'] . '.php'; ?>
        </div>
    <?php endforeach; ?>
</div>
<?php

echo FWUPage::js(HCS_KEY);

