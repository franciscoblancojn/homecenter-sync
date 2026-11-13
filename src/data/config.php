<?php

/**
 * Configuración del plugin (opción HCS_CONFIG).
 *
 * DEFAULTS es la única lista de ajustes: get() la mezcla con lo guardado y
 * sanitize() normaliza lo que llega del formulario. Un ajuste nuevo se agrega
 * en los dos sitios.
 */
class HCS_USE_DATA_CONFIG extends HCS_USE_DATA_BASE
{
    protected $KEY = HCS_CONFIG;

    const OPERATORS = [
        'faco' => 'Colombia (faco)',
        'facl' => 'Chile (facl)',
        'fape' => 'Perú (fape)',
    ];

    const PRICE_MODES = [
        'as-is' => 'Enviar el precio tal como está en WooCommerce',
        'add-tax' => 'Sumar el impuesto al precio (WooCommerce guarda sin IVA)',
        'remove-tax' => 'Quitar el impuesto al precio (WooCommerce guarda con IVA)',
    ];

    const DEFAULTS = [
        'enabled' => 1,
        // Credenciales de Seller Center (Mi cuenta → Usuarios).
        'endpoint' => 'https://sellercenter-api.falabella.com/',
        'user_id' => '',
        'api_key' => '',
        'seller_id' => '',
        'operator_code' => 'faco',
        'integration_type' => 'PROPIA',
        // Cómo se envía el precio.
        'price_mode' => 'as-is',
        // Valores por defecto para los productos que no los definan.
        'default_category' => '',
        'default_brand' => 'Genérico',
        'default_condition' => 'Nuevo',
        'default_tax' => '19',
        'package_height' => '10',
        'package_width' => '10',
        'package_length' => '10',
        'package_weight' => '1',
        'fallback_stock' => 100,
        'sku_prefix' => 'HCS-',
        // Sincronización.
        'sync_on_save' => 1,
        'sync_images' => 1,
        'batch_size' => 200,
        'min_feed_interval' => 120,
    ];

    public function get()
    {
        return array_merge(self::DEFAULTS, $this->DATA);
    }

    public function set($data)
    {
        $this->DATA = $data;
        $this->save();
    }

    /**
     * Decimal positivo con punto (la API no acepta coma).
     */
    public static function number($value, $default = 0)
    {
        $value = str_replace(',', '.', trim((string) $value));
        if ('' === $value || !is_numeric($value)) {
            return $default;
        }

        return (float) $value;
    }

    /**
     * Normaliza lo que llega del formulario de ajustes (ya con wp_unslash).
     */
    public static function sanitize($input)
    {
        $input = (array) $input;
        $d = self::DEFAULTS;
        $text = function ($key) use ($input, $d) {
            $value = sanitize_text_field($input[$key] ?? '');
            return '' === $value ? $d[$key] : $value;
        };
        $check = function ($key) use ($input) {
            return empty($input[$key]) ? 0 : 1;
        };

        $endpoint = esc_url_raw(trim((string) ($input['endpoint'] ?? '')));
        if ('' === $endpoint) {
            $endpoint = $d['endpoint'];
        }
        // La firma se calcula con la URL base y los parámetros en la query.
        $endpoint = preg_replace('/\?.*$/', '', $endpoint);
        $endpoint = rtrim($endpoint, '/') . '/';

        $operator = sanitize_key($input['operator_code'] ?? '');
        if (!isset(self::OPERATORS[$operator])) {
            $operator = $d['operator_code'];
        }
        $price_mode = sanitize_key($input['price_mode'] ?? '');
        if (!isset(self::PRICE_MODES[$price_mode])) {
            $price_mode = $d['price_mode'];
        }

        // La API Key nunca se muestra: si el campo llega vacío se conserva la guardada.
        $api_key = trim((string) ($input['api_key'] ?? ''));
        if ('' === $api_key) {
            $api_key = (string) (new self())->get()['api_key'];
        }

        return [
            'enabled' => $check('enabled'),
            'endpoint' => $endpoint,
            'user_id' => sanitize_text_field(trim((string) ($input['user_id'] ?? ''))),
            'api_key' => $api_key,
            'seller_id' => sanitize_text_field(trim((string) ($input['seller_id'] ?? ''))),
            'operator_code' => $operator,
            'integration_type' => sanitize_text_field($text('integration_type')),
            'price_mode' => $price_mode,
            'default_category' => sanitize_text_field($input['default_category'] ?? ''),
            'default_brand' => sanitize_text_field($text('default_brand')),
            'default_condition' => sanitize_text_field($text('default_condition')),
            'default_tax' => sanitize_text_field($text('default_tax')),
            'package_height' => (string) self::number($input['package_height'] ?? $d['package_height'], (float) $d['package_height']),
            'package_width' => (string) self::number($input['package_width'] ?? $d['package_width'], (float) $d['package_width']),
            'package_length' => (string) self::number($input['package_length'] ?? $d['package_length'], (float) $d['package_length']),
            'package_weight' => (string) self::number($input['package_weight'] ?? $d['package_weight'], (float) $d['package_weight']),
            'fallback_stock' => max(0, absint($input['fallback_stock'] ?? $d['fallback_stock'])),
            'sku_prefix' => sanitize_text_field($text('sku_prefix')),
            'sync_on_save' => $check('sync_on_save'),
            'sync_images' => $check('sync_images'),
            'batch_size' => min(1000, max(1, absint($input['batch_size'] ?? $d['batch_size']))),
            'min_feed_interval' => min(3600, max(0, absint($input['min_feed_interval'] ?? $d['min_feed_interval']))),
        ];
    }

    /**
     * ¿Están completas las credenciales mínimas?
     */
    public static function configured($config = null)
    {
        $config = null === $config ? (new self())->get() : $config;

        return !empty($config['enabled'])
            && '' !== (string) $config['endpoint']
            && '' !== (string) $config['user_id']
            && '' !== (string) $config['api_key']
            && '' !== (string) $config['seller_id'];
    }

    /**
     * Impuesto del producto (porcentaje) para enviarlo a la API.
     */
    public function taxFor($value)
    {
        $value = trim((string) $value);

        return '' === $value ? (string) $this->get()['default_tax'] : $value;
    }
}
