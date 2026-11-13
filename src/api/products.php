<?php

/**
 * Acciones AJAX sobre productos: encolar publicar/actualizar/desactivar,
 * consultar estado remoto y procesar selección.
 */
class HCS_API_PRODUCTS
{
    const NONCE = 'hcs_products_nonce';

    public static function init()
    {
        add_action('wp_ajax_hcs_queue', [self::class, 'queue']);
        add_action('wp_ajax_hcs_remote', [self::class, 'remote']);
    }

    private static function guard()
    {
        if (!current_user_can(HCS_CAPABILITY)) {
            wp_send_json_error('No tienes permisos para esta acción.', 403);
        }
        check_ajax_referer(self::NONCE, 'nonce');
    }

    /**
     * Resuelve IDs desde la selección o desde los filtros actuales.
     *
     * @return int[]
     */
    private static function ids()
    {
        $ids = isset($_POST['ids']) ? (array) wp_unslash($_POST['ids']) : [];
        $ids = array_values(array_filter(array_map('absint', $ids)));
        if ($ids) {
            return $ids;
        }
        $scope = sanitize_key($_POST['scope'] ?? '');
        if ('filtered' !== $scope) {
            return [];
        }
        $filters = HCS_PRODUCT::filters($_POST);

        return HCS_PRODUCT::idsByFilters($filters);
    }

    public static function queue()
    {
        self::guard();
        $ids = self::ids();
        $action = sanitize_key($_POST['do'] ?? 'publish');
        if (!$ids) {
            wp_send_json_error('No hay productos seleccionados.');
        }
        if (!HCS_USE_DATA_CONFIG::configured()) {
            wp_send_json_error('Faltan credenciales: revisa la pestaña Conexión.');
        }

        $message = '';
        $count = 0;
        if ('deactivate' === $action) {
            foreach ($ids as $id) {
                $product = wc_get_product($id);
                if (!$product) {
                    continue;
                }
                HCS_PRODUCT::patch($id, ['status' => 'inactive', 'enabled' => 0]);
                $count++;
            }
            HCS_QUEUE::add($ids, 'deactivate');
            $message = $count . ' producto(s) marcados para despublicar y encolados.';
        } elseif ('publish' === $action) {
            foreach ($ids as $id) {
                $product = wc_get_product($id);
                if (!$product) {
                    continue;
                }
                $patch = ['enabled' => 1, 'status' => 'active'];
                HCS_PRODUCT::patch($id, $patch);
                $count++;
            }
            HCS_QUEUE::add($ids, 'publish');
            $message = $count . ' producto(s) habilitados y encolados para publicar/actualizar.';
        } else {
            HCS_QUEUE::add($ids, 'update');
            $count = count(HCS_PRODUCT::expand($ids));
            $message = $count . ' producto(s) encolados para actualización.';
        }

        HCS_LOG_SERVICE::add('encolar_productos', [
            'action' => $action,
            'ids' => $ids,
            'count' => $count,
        ]);

        wp_send_json_success([
            'message' => $message . ' La cola se procesa cada 5 minutos (o con «Procesar cola ahora»).',
            'pending' => HCS_QUEUE::count(),
        ]);
    }

    public static function remote()
    {
        self::guard();
        $ids = self::ids();
        if (!$ids) {
            wp_send_json_error('No hay productos seleccionados.');
        }
        $items = [];
        foreach (HCS_PRODUCT::expand($ids) as $id) {
            $product = wc_get_product($id);
            if (!$product) {
                continue;
            }
            $items[$id] = HCS_PRODUCT::sellerSku($product);
        }
        if (!$items) {
            wp_send_json_error('No hay productos sincronizables en la selección.');
        }

        $result = HCS_API::getProducts(array_values($items));
        if (!$result['ok']) {
            wp_send_json_error($result['error']);
        }

        $found = 0;
        foreach ($items as $id => $sku) {
            if (!isset($result['products'][$sku])) {
                HCS_PRODUCT::patchHcs($id, [
                    'remote_status' => '',
                    'remote_published' => 0,
                    'last_sync' => current_time('mysql'),
                ]);
                continue;
            }
            HCS_PRODUCT::applyRemote($id, $result['products'][$sku]);
            $found++;
        }

        HCS_LOG_SERVICE::add('consultar_remoto', ['ids' => array_keys($items), 'found' => $found]);
        wp_send_json_success([
            'message' => 'Estado remoto actualizado: ' . $found . ' de ' . count($items) . ' encontrado(s) en Homecenter.',
        ]);
    }
}

HCS_API_PRODUCTS::init();
