<?php

/**
 * Cola de sincronización.
 *
 * Se envía como máximo un feed por ejecución (crear/actualizar, imágenes o
 * eliminar). Seller Center limita los feeds: 50 seguidos y luego uno cada 2
 * minutos, así que el cron va espaciado y la cola conserva lo pendiente.
 */
class HCS_QUEUE
{
    public static function get()
    {
        $data = get_option(HCS_QUEUE, []);
        $data = is_array($data) ? $data : [];

        return array_merge(['products' => [], 'images' => [], 'remove' => []], $data);
    }

    private static function save($data)
    {
        update_option(HCS_QUEUE, $data, false);
    }

    /**
     * Encola productos (los padres variables se expanden a sus variaciones).
     *
     * @return int Productos encolados.
     */
    public static function add($ids, $reason = 'manual')
    {
        $ids = HCS_PRODUCT::expand($ids);
        // Seller Center exige que el ParentSku vaya en el mismo feed: al guardar
        // una variación suelta se incluye el padre del grupo si aún no existe.
        $extra = [];
        foreach ($ids as $id) {
            $parent_id = HCS_PRODUCT::parentId($id);
            if (!$parent_id) {
                continue;
            }
            $parent_variation = HCS_PRODUCT::parentVariationId($parent_id);
            if ($parent_variation && !in_array($parent_variation, $ids, true) && !HCS_PRODUCT::isPublished($parent_variation)) {
                $extra[] = $parent_variation;
            }
        }
        $ids = array_values(array_unique(array_merge($ids, $extra)));
        $queue = self::get();
        foreach ($ids as $id) {
            $queue['products'][$id] = [
                'at' => time(),
                'reason' => sanitize_key($reason),
            ];
        }
        self::save($queue);

        return count($ids);
    }

    /**
     * Encola la eliminación de SKUs (se usa antes de borrar el producto).
     */
    public static function addRemove($skus)
    {
        $queue = self::get();
        foreach ((array) $skus as $sku) {
            $sku = sanitize_text_field((string) $sku);
            if ('' !== $sku) {
                $queue['remove'][$sku] = ['at' => time()];
            }
        }
        self::save($queue);
    }

    public static function count()
    {
        $queue = self::get();

        return count($queue['products']) + count($queue['images']) + count($queue['remove']);
    }

    public static function clear()
    {
        self::save(['products' => [], 'images' => [], 'remove' => []]);
    }

    /**
     * IDs del próximo lote de productos.
     *
     * Las variaciones de un mismo padre viajan juntas en el mismo feed para que
     * Seller Center encuentre su ParentSku, aunque el lote se corte en otro
     * producto. Seller Center admite hasta 1000 productos por feed.
     *
     * @param array $pending Productos encolados.
     * @param int   $limit   Tamaño máximo del lote.
     * @return int[]
     */
    private static function batchIds($pending, $limit)
    {
        $ids = array_map('intval', array_keys((array) $pending));
        if (!$ids) {
            return [];
        }
        $limit = $limit > 0 ? min(1000, (int) $limit) : 1000;

        $groups = [];
        foreach ($ids as $id) {
            $product = wc_get_product($id);
            $key = $product && $product->is_type('variation')
                ? 'p' . (int) $product->get_parent_id()
                : 'i' . (int) $id;
            $groups[$key][] = $id;
        }

        $out = [];
        foreach ($groups as $group) {
            if ($out && count($out) + count($group) > $limit) {
                // El grupo no cabe en lo que queda del lote: pasa completo al
                // siguiente feed para no separar las variaciones de su padre.
                break;
            }
            foreach ($group as $id) {
                $out[] = $id;
                if (count($out) >= 1000) {
                    break 2;
                }
            }
            if (count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    /**
     * Procesa un lote. Devuelve un resumen para el admin.
     *
     * @return array{ok:bool,message:string,sent:string,count:int,feed:string,wait:int}
     */
    public static function process($limit = 0)
    {
        $out = ['ok' => false, 'message' => '', 'sent' => '', 'count' => 0, 'feed' => '', 'wait' => 0];
        if (!HCS_API::configured()) {
            $out['message'] = 'Faltan credenciales: revisa la pestaña Conexión.';

            return $out;
        }
        if (!HCS_API::canSendFeed()) {
            $out['wait'] = HCS_API::waitSeconds();
            $out['message'] = 'Seller Center pide esperar ' . $out['wait'] . ' s entre feeds. Queda en cola.';

            return $out;
        }

        $config = HCS_PRODUCT::config();
        $limit = $limit ? absint($limit) : (int) $config['batch_size'];
        $queue = self::get();

        // 1. Productos: se envía un solo tipo de acción por ejecución. Las
        // variaciones de un mismo padre viajan juntas para que exista su ParentSku.
        $ids = self::batchIds($queue['products'], $limit);
        if ($ids) {
            $actions = HCS_PRODUCT::actions($ids);
            $groups = ['create' => [], 'update' => [], 'deactivate' => []];
            foreach ($actions as $id => $action) {
                if (isset($groups[$action])) {
                    $groups[$action][$id] = $action;
                }
            }
            $mode = '';
            foreach (['create', 'update', 'deactivate'] as $candidate) {
                if ($groups[$candidate]) {
                    $mode = $candidate;
                    break;
                }
            }
            // Sin acción (producto desactivado y sin relación remota): no hay nada que enviar.
            foreach ($ids as $id) {
                if (!isset($actions[$id])) {
                    unset($queue['products'][$id]);
                }
            }

            if ('' !== $mode) {
                $group = $groups[$mode];
                $batch = HCS_PRODUCT::batchXml($group);
                foreach ($batch['errors'] as $id => $error) {
                    HCS_PRODUCT::patchHcs($id, [
                        'remote_status' => 'error',
                        'last_error' => $error,
                        'last_sync' => current_time('mysql'),
                    ]);
                    unset($queue['products'][$id]);
                }

                if ($batch['ok']) {
                    $action = 'deactivate' === $mode ? 'ProductUpdate' : ('create' === $mode ? 'ProductCreate' : 'ProductUpdate');
                    $result = HCS_API::request($action, $batch['xml']);
                    if (!$result['ok']) {
                        if (!empty($result['transient'])) {
                            // Red, 429 o 5xx: se reintenta en la próxima ejecución.
                            foreach ($batch['ids'] as $id) {
                                HCS_PRODUCT::patchHcs($id, [
                                    'remote_status' => 'queued',
                                    'last_error' => $result['error'],
                                    'last_sync' => current_time('mysql'),
                                ]);
                            }
                            self::save($queue);
                            $out['message'] = 'Error temporal de Seller Center; queda en cola: ' . $result['error'];

                            return $out;
                        }
                        foreach ($batch['ids'] as $id) {
                            HCS_PRODUCT::patchHcs($id, [
                                'remote_status' => 'error',
                                'last_error' => $result['error'],
                                'last_sync' => current_time('mysql'),
                            ]);
                            unset($queue['products'][$id]);
                        }
                        self::save($queue);
                        $out['message'] = 'Seller Center rechazó el lote: ' . $result['error'];

                        return $out;
                    }

                    $feed_id = (string) $result['request_id'];
                    if ('' === $feed_id) {
                        foreach ($batch['ids'] as $id) {
                            HCS_PRODUCT::patchHcs($id, [
                                'remote_status' => 'error',
                                'last_error' => 'La API aceptó el envío pero no devolvió RequestId.',
                                'last_sync' => current_time('mysql'),
                            ]);
                            unset($queue['products'][$id]);
                        }
                        self::save($queue);
                        $out['message'] = 'La API no devolvió el identificador del feed; no se puede consultar su estado.';

                        return $out;
                    }

                    $feeds = new HCS_USE_DATA_FEEDS();
                    $feeds->add([
                        'id' => $feed_id,
                        'action' => $action,
                        'mode' => $mode,
                        'status' => 'Queued',
                        'ids' => $batch['ids'],
                        'skus' => $batch['skus'],
                        'total' => count($batch['ids']),
                    ]);

                    foreach ($batch['ids'] as $id) {
                        HCS_PRODUCT::patchHcs($id, [
                            'remote_status' => 'feed_pending',
                            'remote_published' => 'deactivate' === $mode ? 1 : 1,
                            'feed_id' => $feed_id,
                            'last_sync' => current_time('mysql'),
                            'last_error' => '',
                        ]);
                        unset($queue['products'][$id]);
                        // Solo se reenvían imágenes nuevas o modificadas: cada
                        // feed de imágenes cuenta para el límite de Seller Center.
                        if ('deactivate' !== $mode && $config['sync_images'] && HCS_PRODUCT::needsImages($id)) {
                            $queue['images'][$id] = true;
                        }
                    }
                    self::save($queue);

                    $out = [
                        'ok' => true,
                        'message' => 'Feed enviado: ' . count($batch['ids']) . ' producto(s).',
                        'sent' => $mode,
                        'count' => count($batch['ids']),
                        'feed' => $feed_id,
                        'wait' => HCS_API::waitSeconds(),
                    ];

                    return $out;
                }
            }

            // Guarda la cola aunque no haya acción: así se descartan los
            // productos que ya no se van a enviar.
            self::save($queue);
        }

        // 2. Imágenes.
        if ($queue['images']) {
            $ids = array_slice(array_keys($queue['images']), 0, $limit);
            $batch = HCS_PRODUCT::imagesXml($ids);
            foreach ($ids as $id) {
                if (empty($batch['ids']) || !in_array((int) $id, $batch['ids'], true)) {
                    unset($queue['images'][$id]);
                }
            }
            if ($batch['ok']) {
                // Huella de lo que se envía ahora: solo se repite el feed si las
                // imágenes vuelven a cambiar.
                $hashes = [];
                foreach ($batch['ids'] as $id) {
                    $hashes[$id] = HCS_PRODUCT::imagesHash($id);
                }
                $result = HCS_API::request('Image', $batch['xml']);
                if ($result['ok']) {
                    $feed_id = (string) $result['request_id'];
                    if ('' === $feed_id) {
                        $out['message'] = 'La API aceptó las imágenes pero no devolvió RequestId.';

                        return $out;
                    }
                    (new HCS_USE_DATA_FEEDS())->add([
                        'id' => $feed_id,
                        'action' => 'Image',
                        'mode' => 'image',
                        'status' => 'Queued',
                        'ids' => $batch['ids'],
                        'skus' => $batch['skus'],
                        'total' => count($batch['ids']),
                    ]);
                    foreach ($batch['ids'] as $id) {
                        HCS_PRODUCT::patchHcs($id, ['images_hash' => $hashes[$id] ?? '']);
                        unset($queue['images'][$id]);
                    }
                    self::save($queue);

                    return [
                        'ok' => true,
                        'message' => 'Imágenes enviadas: ' . count($batch['ids']) . ' producto(s).',
                        'sent' => 'image',
                        'count' => count($batch['ids']),
                        'feed' => $feed_id,
                        'wait' => HCS_API::waitSeconds(),
                    ];
                }
                $out['message'] = 'No se pudieron enviar las imágenes: ' . $result['error'];
                self::save($queue);

                return $out;
            }
            self::save($queue);
        }

        // 3. Eliminaciones solicitadas antes de borrar el producto.
        if ($queue['remove']) {
            $skus = array_slice(array_keys($queue['remove']), 0, $limit);
            $parts = [];
            foreach ($skus as $sku) {
                $parts[] = '<Product>' . HCS_PRODUCT::xmlTagPublic('SellerSku', $sku) . '</Product>';
            }
            $xml = '<?xml version="1.0" encoding="UTF-8" ?>' . "\n<Request>\n" . implode("\n", $parts) . "\n</Request>";
            $result = HCS_API::request('ProductRemove', $xml);
            if ($result['ok']) {
                $feed_id = (string) $result['request_id'];
                if ('' === $feed_id) {
                    $out['message'] = 'La API aceptó la eliminación pero no devolvió RequestId.';

                    return $out;
                }
                (new HCS_USE_DATA_FEEDS())->add([
                    'id' => $feed_id,
                    'action' => 'ProductRemove',
                    'mode' => 'remove',
                    'status' => 'Queued',
                    'skus' => $skus,
                    'total' => count($skus),
                ]);
                foreach ($skus as $sku) {
                    unset($queue['remove'][$sku]);
                }
                self::save($queue);

                return [
                    'ok' => true,
                    'message' => 'Eliminación enviada: ' . count($skus) . ' SKU.',
                    'sent' => 'remove',
                    'count' => count($skus),
                    'feed' => $feed_id,
                    'wait' => HCS_API::waitSeconds(),
                ];
            }
            $out['message'] = 'No se pudo eliminar: ' . $result['error'];

            return $out;
        }

        $out['message'] = $out['message'] ?: 'No hay nada pendiente en la cola.';

        return $out;
    }

    /**
     * Procesa varios lotes seguidos respetando la espera entre feeds.
     *
     * @return array Resumen acumulado.
     */
    public static function processAll($runs = 3)
    {
        $summary = ['sent' => 0, 'products' => 0, 'messages' => []];
        for ($i = 0; $i < $runs; $i++) {
            $result = self::process();
            if ('' !== $result['message']) {
                $summary['messages'][] = $result['message'];
            }
            if (!$result['ok']) {
                break;
            }
            $summary['sent']++;
            $summary['products'] += (int) $result['count'];
            if ($result['wait'] > 0) {
                break;
            }
        }

        return $summary;
    }
}
