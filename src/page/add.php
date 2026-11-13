<?php

add_action('admin_menu', function () {
    add_menu_page(
        'Homecenter Sync',            // Título de la página
        'Homecenter',            // Nombre en el menú
        HCS_CAPABILITY,    // Permisos
        HCS_KEY,           // Slug
        'HCS_PAGE_VIEW',
        'dashicons-store',
        57
    );
    // Sustituye el primer submenú automático del menú padre.
    add_submenu_page(
        HCS_KEY,
        'Homecenter Sync',
        'Sincronización',
        HCS_CAPABILITY,
        HCS_KEY,
        'HCS_PAGE_VIEW'
    );
    add_submenu_page(
        HCS_KEY,
        'Carga Homecenter',
        'Carga Homecenter',
        HCS_CAPABILITY,
        HCS_KEY . '-carga',
        'HCS_PAGE_CARGA_VIEW'
    );
});

function HCS_PAGE_VIEW()
{
    require_once HCS_DIR . 'src/page/page.php';
}

function HCS_PAGE_CARGA_VIEW()
{
    require_once HCS_DIR . 'src/page/carga.php';
}

// CSS y JS del admin solo en las páginas del plugin.
add_action('admin_enqueue_scripts', function ($hook) {
    $page = isset($_GET['page']) ? sanitize_text_field(wp_unslash($_GET['page'])) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended
    if (!in_array($page, [HCS_KEY, HCS_KEY . '-carga'], true)) {
        return;
    }
    $css = HCS_DIR . 'src/css/admin.css';
    $js = HCS_DIR . 'src/js/admin.js';
    wp_enqueue_style('hcs-admin', HCS_URL . 'src/css/admin.css', [], filemtime($css));
    wp_enqueue_script('hcs-admin', HCS_URL . 'src/js/admin.js', ['jquery'], filemtime($js), true);
    wp_localize_script('hcs-admin', 'HCS_ADMIN', [
        'ajax' => admin_url('admin-ajax.php'),
        'nonce_settings' => wp_create_nonce(HCS_API_SETTINGS::NONCE),
        'nonce_products' => wp_create_nonce(HCS_API_PRODUCTS::NONCE),
        'processing' => 'Procesando…',
        'error' => 'No se pudo completar la operación.',
        'confirm_deactivate' => '¿Despublicar de Homecenter los productos seleccionados? Quedarán inactivos en el marketplace.',
    ]);
});
