<?php

/**
 * Feeds enviados a Seller Center (opción HCS_FEEDS).
 *
 * La API de productos es asíncrona: cada envío devuelve un RequestId (UUID) que
 * luego se consulta con FeedStatus. Se guardan los últimos feeds para la pestaña
 * «Feeds» y para actualizar el estado de los productos.
 */
class HCS_USE_DATA_FEEDS extends HCS_USE_DATA_BASE
{
    protected $KEY = HCS_FEEDS;

    const LIMIT = 100;

    /**
     * @return array<string, array>
     */
    public function all()
    {
        $data = is_array($this->DATA) ? $this->DATA : [];

        return $data;
    }

    public function find($feed_id)
    {
        $all = $this->all();
        $feed_id = sanitize_text_field((string) $feed_id);

        return isset($all[$feed_id]) ? $all[$feed_id] : null;
    }

    /**
     * Agrega un feed recién aceptado por la API.
     */
    public function add($feed)
    {
        $feed = array_merge([
            'id' => '',
            'action' => '',
            'status' => 'Queued',
            'created' => current_time('mysql'),
            'updated' => current_time('mysql'),
            'ids' => [],
            'skus' => [],
            'total' => 0,
            'failed' => 0,
            'errors' => [],
        ], (array) $feed);

        if ('' === (string) $feed['id']) {
            return false;
        }
        $all = $this->all();
        $all[$feed['id']] = $feed;
        // Los más nuevos al final; se recortan los más antiguos.
        if (count($all) > self::LIMIT) {
            $all = array_slice($all, -self::LIMIT, null, true);
        }
        $this->DATA = $all;
        $this->save();

        return $feed;
    }

    public function update($feed_id, $patch)
    {
        $all = $this->all();
        if (!isset($all[$feed_id])) {
            return null;
        }
        $all[$feed_id] = array_merge($all[$feed_id], (array) $patch, ['updated' => current_time('mysql')]);
        $this->DATA = $all;
        $this->save();

        return $all[$feed_id];
    }

    /**
     * Feeds que aún pueden cambiar de estado (para consultar FeedStatus).
     *
     * @return array<string, array>
     */
    public function pending()
    {
        return array_filter($this->all(), function ($feed) {
            return in_array($feed['status'] ?? '', ['Queued', 'Processing'], true);
        });
    }
}
