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
});

function HCS_PAGE_VIEW()
{
    require_once HCS_DIR . 'src/page/page.php';
}

// CSS y JS del admin solo en la página del plugin.
add_action('admin_enqueue_scripts', function ($hook) {
    if ('toplevel_page_' . HCS_KEY !== $hook) {
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

