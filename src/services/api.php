<?php

/**
 * Cliente de la Seller Center API de Falabella/Sodimac (la que usa Homecenter).
 *
 * Autenticación: UserID + API Key, con firma HMAC-SHA256 de los parámetros
 * ordenados y codificados (RFC 3986). Las lecturas van por GET y las escrituras
 * por POST con el XML en el cuerpo; los parámetros y la firma van en la URL.
 *
 * Docs: https://developers.falabella.com/docs/getting-started
 */
class HCS_API
{
    const VERSION = '1.0';

    // Acciones que generan un feed y entran en el límite de llamadas.
    const FEED_ACTIONS = ['ProductCreate', 'ProductUpdate', 'ProductRemove', 'Image', 'UpdateStock'];

    public static function config()
    {
        return (new HCS_USE_DATA_CONFIG())->get();
    }

    public static function configured()
    {
        return HCS_USE_DATA_CONFIG::configured();
    }

    public static function isFeedAction($action)
    {
        return in_array($action, self::FEED_ACTIONS, true);
    }

    /**
     * ¿El código de error justifica reintentar? (timestamp expirado, error
     * interno o demasiadas peticiones).
     */
    public static function transientCode($code)
    {
        $code = strtoupper(trim((string) $code));
        if ('' !== $code && 'E' === $code[0]) {
            $code = substr($code, 1);
        }

        return in_array((int) $code, [3, 6, 429], true);
    }

    /**
     * ¿Se puede crear otro feed ahora? Seller Center permite 50 seguidos y
     * después exige 2 minutos entre envíos. Siempre respetamos el intervalo.
     */
    public static function canSendFeed()
    {
        $config = self::config();
        $last = (int) get_option(HCS_LAST_FEED, 0);
        $interval = (int) $config['min_feed_interval'];

        return !$last || (time() - $last) >= $interval;
    }

    public static function waitSeconds()
    {
        $config = self::config();
        $last = (int) get_option(HCS_LAST_FEED, 0);
        if (!$last) {
            return 0;
        }

        return max(0, (int) $config['min_feed_interval'] - (time() - $last));
    }

    private static function markFeedSent()
    {
        update_option(HCS_LAST_FEED, time(), false);
    }

    /**
     * Firma de los parámetros (sin Signature).
     */
    public static function signature($parameters, $api_key)
    {
        ksort($parameters);
        $encoded = [];
        foreach ($parameters as $name => $value) {
            $encoded[] = rawurlencode($name) . '=' . rawurlencode((string) $value);
        }

        return hash_hmac('sha256', implode('&', $encoded), (string) $api_key, false);
    }

    /**
     * Cabecera User-Agent exigida por la API.
     */
    public static function userAgent($config)
    {
        $seller = '' !== (string) $config['seller_id'] ? $config['seller_id'] : 'SELLER';
        $integration = '' !== (string) $config['integration_type'] ? $config['integration_type'] : 'PROPIA';

        return $seller . '/PHP/' . PHP_VERSION . '/' . $integration . '/' . strtoupper((string) $config['operator_code']);
    }

    /**
     * Ejecuta una acción de la API.
     *
     * @param string $action Acción (GetBrands, ProductCreate…).
     * @param string $body   XML del cuerpo para POST.
     * @param array  $extra  Parámetros adicionales de la query.
     * @param string $method GET|POST. Por defecto POST si hay cuerpo.
     * @return array{ok:bool,action:string,request_id:string,body:mixed,error:string,code:string,raw:string}
     */
    public static function request($action, $body = '', $extra = [], $method = '')
    {
        $config = self::config();
        $result = [
            'ok' => false,
            'action' => (string) $action,
            'request_id' => '',
            'response_type' => '',
            'body' => [],
            'error' => '',
            'code' => '',
            'raw' => '',
            'transient' => false,
        ];

        if (!self::configured()) {
            $result['error'] = 'Faltan credenciales: revisa la pestaña Conexión.';

            return $result;
        }
        if (self::isFeedAction($action) && !self::canSendFeed()) {
            $result['error'] = 'Espera ' . self::waitSeconds() . ' s antes de enviar otro feed.';

            return $result;
        }

        $method = '' !== $method ? strtoupper($method) : ('' !== $body ? 'POST' : 'GET');
        $parameters = array_merge([
            'Action' => $action,
            'Format' => 'XML',
            'Timestamp' => gmdate('c'),
            'UserID' => (string) $config['user_id'],
            'Version' => self::VERSION,
        ], (array) $extra);
        $parameters['Signature'] = self::signature($parameters, $config['api_key']);

        $url = rtrim((string) $config['endpoint'], '/') . '/?' . http_build_query($parameters, '', '&', PHP_QUERY_RFC3986);
        $args = [
            'timeout' => 60,
            'redirection' => 2,
            'headers' => [
                'User-Agent' => self::userAgent($config),
                'Accept' => 'application/xml, text/xml, application/json',
            ],
        ];
        if ('POST' === $method) {
            $args['method'] = 'POST';
            $args['body'] = (string) $body;
            $args['headers']['Content-Type'] = 'application/xml; charset=UTF-8';
        }

        if (self::isFeedAction($action)) {
            // Cuenta el intento aunque la red falle: evita ráfagas si el host no responde.
            self::markFeedSent();
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            $result['transient'] = true;
            $result['error'] = $response->get_error_message();
            HCS_LOG_SERVICE::add('api_error', [
                'action' => $action,
                'error' => $result['error'],
            ]);

            return $result;
        }

        $http_code = (int) wp_remote_retrieve_response_code($response);
        $result['raw'] = (string) wp_remote_retrieve_body($response);
        $parsed = self::parse($result['raw']);
        $result = array_merge($result, $parsed);
        $result['action'] = (string) $action;

        // Errores temporales: se reintentan en la siguiente ejecución de la cola.
        if (!$result['ok'] && (429 === $http_code || $http_code >= 500)) {
            $result['transient'] = true;
        }
        if (!$result['ok'] && self::transientCode($result['code'])) {
            $result['transient'] = true;
        }

        if (!$result['ok']) {
            HCS_LOG_SERVICE::add('api_error', [
                'action' => $action,
                'code' => $result['code'],
                'error' => $result['error'],
            ]);
        }

        return $result;
    }

    /**
     * Convierte la respuesta XML (o JSON) de la API en un array normalizado.
     */
    public static function parse($raw)
    {
        $out = ['ok' => false, 'error' => 'Respuesta vacía.', 'code' => '', 'request_id' => '', 'response_type' => '', 'body' => []];
        $raw = trim((string) $raw);
        if ('' === $raw) {
            return $out;
        }

        $xml = null;
        if (0 === strpos($raw, '<')) {
            $previous = libxml_use_internal_errors(true);
            $xml = simplexml_load_string($raw, 'SimpleXMLElement', LIBXML_NOCDATA | LIBXML_NONET);
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }

        if ($xml instanceof SimpleXMLElement) {
            $root = $xml->getName();
            if ('ErrorResponse' === $root) {
                $head = isset($xml->Head) ? self::node($xml->Head) : [];
                $out['error'] = (string) ($head['ErrorMessage'] ?? 'Error de la API.');
                $out['code'] = (string) ($head['ErrorCode'] ?? '');
                $out['body'] = isset($xml->Body) ? self::node($xml->Body) : [];

                return $out;
            }
            $head = isset($xml->Head) ? self::node($xml->Head) : [];

            return [
                'ok' => true,
                'error' => '',
                'code' => '',
                'request_id' => (string) ($head['RequestId'] ?? ''),
                'response_type' => (string) ($head['ResponseType'] ?? ''),
                'body' => isset($xml->Body) ? self::node($xml->Body) : [],
            ];
        }

        $json = json_decode($raw, true);
        if (is_array($json)) {
            if (isset($json['ErrorResponse'])) {
                $head = $json['ErrorResponse']['Head'] ?? [];
                $out['error'] = (string) ($head['ErrorMessage'] ?? 'Error de la API.');
                $out['code'] = (string) ($head['ErrorCode'] ?? '');

                return $out;
            }
            $response = $json['SuccessResponse'] ?? $json;
            $head = $response['Head'] ?? [];

            return [
                'ok' => true,
                'error' => '',
                'code' => '',
                'request_id' => (string) ($head['RequestId'] ?? ''),
                'response_type' => (string) ($head['ResponseType'] ?? ''),
                'body' => $response['Body'] ?? [],
            ];
        }

        $out['error'] = 'No se pudo interpretar la respuesta de la API.';

        return $out;
    }

    /**
     * SimpleXML → array. Los nodos repetidos quedan como lista y el texto en _text.
     */
    public static function node($element)
    {
        if (!$element instanceof SimpleXMLElement) {
            return (string) $element;
        }
        $out = [];
        foreach ($element->children() as $name => $child) {
            $value = self::node($child);
            if (array_key_exists($name, $out)) {
                if (!is_array($out[$name]) || !isset($out[$name][0])) {
                    $out[$name] = [$out[$name]];
                }
                $out[$name][] = $value;
            } else {
                $out[$name] = $value;
            }
        }
        $text = trim((string) $element);
        if (!$out) {
            return $text;
        }
        if ('' !== $text) {
            $out['_text'] = $text;
        }

        return $out;
    }

    /**
     * Lista de elementos cuando el XML trae uno solo o varios.
     */
    public static function listOf($value)
    {
        if (!is_array($value)) {
            return [] === $value ? [] : [$value];
        }
        if (isset($value[0]) && is_array($value[0])) {
            return $value;
        }

        return [$value];
    }

    /**
     * Prueba de conexión: GetBrands es de solo lectura y requiere la misma firma.
     */
    public static function test()
    {
        $result = self::request('GetBrands');
        if (!$result['ok']) {
            return $result;
        }
        $brands = $result['body']['Brands']['Brand'] ?? [];
        $result['count'] = count(self::listOf($brands));

        return $result;
    }

    /**
     * Productos remotos por SKU (para conciliar estado). Devuelve por SellerSku.
     *
     * @param string[] $skus
     */
    public static function getProducts($skus)
    {
        $skus = array_values(array_unique(array_filter(array_map('strval', (array) $skus))));
        if (!$skus) {
            return ['ok' => false, 'error' => 'No hay SKU para consultar.', 'products' => []];
        }

        $products = [];
        // La API recibe como máximo 100 SKU por consulta: se pagina para no
        // dejar fuera (ni marcar como inexistentes) los productos restantes.
        foreach (array_chunk($skus, 100) as $chunk) {
            $result = self::request('GetProducts', '', [
                'Filter' => 'all',
                'SkuSellerList' => implode(',', $chunk),
                'Limit' => 100,
            ]);
            if (!$result['ok']) {
                return ['ok' => false, 'error' => $result['error'], 'products' => []];
            }
            $nodes = $result['body']['Products']['Product'] ?? [];
            foreach (self::listOf($nodes) as $node) {
                if (!is_array($node)) {
                    continue;
                }
                $sku = (string) ($node['SellerSku'] ?? '');
                if ('' !== $sku) {
                    $products[$sku] = $node;
                }
            }
        }

        return ['ok' => true, 'error' => '', 'products' => $products];
    }
}
