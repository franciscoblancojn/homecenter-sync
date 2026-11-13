<?php

/**
 * Guardar ajustes (admin-post) y acciones AJAX de conexión/catálogo/feeds.
 */
class HCS_API_SETTINGS
{
    const ACTION = 'hcs_save_settings';
    const NONCE = 'hcs_settings_nonce';

    public static function init()
    {
        add_action('admin_post_' . self::ACTION, [self::class, 'save']);
        add_action('wp_ajax_hcs_test', [self::class, 'test']);
        add_action('wp_ajax_hcs_catalog', [self::class, 'catalog']);
        add_action('wp_ajax_hcs_poll', [self::class, 'poll']);
        add_action('wp_ajax_hcs_process', [self::class, 'process']);
    }

    private static function guard()
    {
        if (!current_user_can(HCS_CAPABILITY)) {
            wp_send_json_error('No tienes permisos para esta acción.', 403);
        }
        check_ajax_referer(self::NONCE, 'nonce');
    }

    public static function save()
    {
        if (!current_user_can(HCS_CAPABILITY)) {
            wp_die('No tienes permisos para cambiar esta configuración.', '', ['response' => 403]);
        }
        check_admin_referer(self::ACTION);

        $config = HCS_USE_DATA_CONFIG::sanitize(wp_unslash($_POST['hcs'] ?? []));
        (new HCS_USE_DATA_CONFIG())->set($config);

        // Nunca guardar la API Key en el log (la pestaña Registro es visible en el admin).
        $logged = $config;
        $logged['api_key'] = '' !== (string) $logged['api_key'] ? '***' : '';
        HCS_LOG_SERVICE::add('guardar_ajustes', $logged);
        set_transient('hcs_respond', [
            'status' => 'ok',
            'message' => 'Configuración guardada.',
        ], 60);

        $tab = sanitize_key($_POST['hcs_tab'] ?? '');
        wp_safe_redirect(add_query_arg(['page' => HCS_KEY, 'tab' => $tab], admin_url('admin.php')));
        exit;
    }

    public static function test()
    {
        self::guard();
        $result = HCS_API::test();
        if (!$result['ok']) {
            wp_send_json_error($result['error']);
        }
        HCS_LOG_SERVICE::add('test_conexion', ['ok' => 1, 'brands' => $result['count']]);
        wp_send_json_success([
            'message' => 'Conexión correcta. Marcas visibles: ' . (int) $result['count'] . '.',
        ]);
    }

    public static function catalog()
    {
        self::guard();
        $result = HCS_CATALOG::refresh();
        if (empty($result['ok'])) {
            wp_send_json_error(isset($result['error']) ? $result['error'] : 'No se pudo actualizar el catálogo.');
        }
        HCS_LOG_SERVICE::add('actualizar_catalogo', $result);
        wp_send_json_success([
            'message' => 'Catálogo actualizado: ' . (int) $result['brands'] . ' marcas y ' . (int) $result['categories'] . ' categorías hoja.',
        ]);
    }

    public static function poll()
    {
        self::guard();
        $result = HCS_FEEDS::poll(20);
        wp_send_json_success($result);
    }

    public static function process()
    {
        self::guard();
        $result = HCS_QUEUE::processAll(3);
        $message = $result['messages'] ? implode(' ', $result['messages']) : 'No hay nada pendiente en la cola.';
        if (!empty($result['sent'])) {
            $message = 'Feeds enviados: ' . (int) $result['sent'] . '. ' . $message;
        }
        wp_send_json_success([
            'message' => $message,
            'pending' => HCS_QUEUE::count(),
            'wait' => HCS_API::waitSeconds(),
        ]);
    }
}

HCS_API_SETTINGS::init();
