<?php

namespace franciscoblancojn\wordpress_utils;

if (
    !class_exists("FWUUpdate")
    &&
    function_exists("is_admin")
    &&
    function_exists("add_filter")
    &&
    function_exists("get_transient")
    &&
    function_exists("wp_remote_get")
    &&
    function_exists("is_wp_error")
    &&
    function_exists("wp_remote_retrieve_body")
    &&
    function_exists("set_transient")
    &&
    function_exists("get_plugin_data")
    &&
    function_exists("wp_nonce_url")
    &&
    function_exists("admin_url")
    &&
    function_exists("set_transient")
    &&
    function_exists("set_transient")
) {
    class FWUUpdate
    {
        static function init($config)
        {
            if (is_admin()) {
                // Obtener la URL de la página actual en el admin
                $current_url = $_SERVER['REQUEST_URI'];

                if (
                    strpos($current_url, '/wp-admin/plugins.php') !== false ||
                    strpos($current_url, '/wp-admin/update.php?action=upgrade-plugin') !== false
                ) {
                    add_filter('site_transient_update_plugins', function ($transient) use ($config) {
                        if (empty($transient->checked)) {
                            return $transient;
                        }

                        // Definir constantes
                        $plugin_slug =  basename(rtrim($config['dir'], '/'));
                        $plugin_file_php = $config['file'];
                        $plugin_file = $plugin_slug . '/' . $plugin_file_php;
                        $github_api_url = 'https://api.github.com/repos/' . $config['path_repository'] . '/releases/latest';

                        // ⚠️ Asegúrate de almacenar el token de manera segura
                        $github_token = join('', $config['token_array_split']);

                        $cache_key = 'github_updater_' . md5($plugin_file);
                        $release = get_transient($cache_key);
                        if (!$release) {
                            // Llamada a la API de GitHub
                            $response = wp_remote_get($github_api_url, [
                                'headers' => [
                                    'User-Agent'    => 'WordPress-Updater',
                                    'Authorization' => 'token ' . $github_token,
                                ]
                            ]);

                            if (is_wp_error($response)) {
                                return $transient;
                            }

                            $release = json_decode(wp_remote_retrieve_body($response));
                            if (isset($release->message) && strpos($release->message, 'API rate limit exceeded') !== false) {
                                set_transient($cache_key, 'RATE_LIMIT', 1 * MINUTE_IN_SECONDS);
                                return $transient;
                            }
                            set_transient($cache_key, $release, MINUTE_IN_SECONDS);
                        }
                        if (!isset($release->tag_name)) {
                            return $transient;
                        }

                        $latest_version = ltrim($release->tag_name, 'v');

                        // Obtener la versión actual del plugin
                        if (!function_exists('get_plugin_data')) {
                            require_once ABSPATH . 'wp-admin/includes/plugin.php';
                        }

                        $plugin_path = $config['dir'] . $plugin_file_php;
                        $plugin_data = get_plugin_data($plugin_path);
                        $current_version = $plugin_data['Version'];

                        // Comparar versiones
                        if (version_compare($current_version, $latest_version, '<')) {
                            $transient->response[$plugin_file] = (object) [
                                'new_version' => $latest_version,
                                'package'     => "https://github.com/" . $config['path_repository'] . "/archive/refs/heads/" . $config['branch'] . ".zip",
                                'slug'        => $plugin_slug,
                                'url'         => 'https://github.com/' . $config['path_repository'],
                            ];
                        }

                        return $transient;
                    });

                    add_filter('plugin_action_links_' . $config['basename'], function ($links, $file) use ($config) {
                        if ($file == $config['basename']) {
                            $actualizar_url = wp_nonce_url(
                                admin_url('update.php?action=upgrade-plugin&plugin=' . $file),
                                'upgrade-plugin_' . $file
                            );
                            $plugin_slug =  basename(rtrim($config['dir'], '/'));

                            $links[] = '<a class="github_updater_plugin_wordpress_btn" href="' . esc_url($actualizar_url) . '" style="color: #0073aa; font-weight: bold;">Actualizar</a>
                            <style>
                                tr.plugin-update-tr[data-slug="' . $plugin_slug . '"] a,
                                tr.plugin-update-tr[data-slug="' . $plugin_slug . '"] a + *{
                                    display:none;
                                }
                            </style>
                        ';
                        }
                        return $links;
                    }, 10, 2);

                    // Forzar actualización de plugins
                    // delete_site_transient('update_plugins');
                }
            }
        }
    }
}
