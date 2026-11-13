<?php

/**
 * Consulta de feeds (FeedStatus) y actualización del estado de los productos.
 *
 * La creación/actualización de productos es asíncrona: que la API acepte el feed
 * no significa que el producto se haya publicado. Cuando el feed termina se
 * marca cada producto como publicado o con el error que devolvió Homecenter.
 */
class HCS_FEEDS
{
    /**
     * @return array{ok:bool,checked:int,finished:int,message:string}
     */
    public static function poll($limit = 20)
    {
        $feeds = new HCS_USE_DATA_FEEDS();
        $pending = array_slice($feeds->pending(), 0, absint($limit), true);
        $out = ['ok' => true, 'checked' => 0, 'finished' => 0, 'message' => ''];

        foreach ($pending as $feed_id => $feed) {
            $result = HCS_API::request('FeedStatus', '', ['FeedID' => $feed_id]);
            $out['checked']++;
            if (!$result['ok']) {
                $out['message'] = $result['error'];
                continue;
            }
            $detail = $result['body']['FeedDetail'] ?? [];
            if (!is_array($detail) || empty($detail['Status'])) {
                continue;
            }
            $status = (string) $detail['Status'];
            $failed = (int) ($detail['FailedRecords'] ?? 0);
            $errors = [];
            foreach (HCS_API::listOf($detail['FeedErrors']['Error'] ?? []) as $error) {
                if (is_array($error)) {
                    $errors[] = trim((string) ($error['Code'] ?? '') . ' ' . (string) ($error['Message'] ?? ''));
                } else {
                    $errors[] = (string) $error;
                }
            }
            $errors = array_values(array_filter($errors));

            $feeds->update($feed_id, [
                'status' => $status,
                'total' => (int) ($detail['TotalRecords'] ?? $feed['total']),
                'failed' => $failed,
                'errors' => $errors,
            ]);

            if (!in_array($status, ['Finished', 'Error', 'Canceled'], true)) {
                continue;
            }
            $out['finished']++;

            $ids = isset($feed['ids']) ? (array) $feed['ids'] : [];
            $mode = (string) ($feed['mode'] ?? '');
            // FailedRecords > 0 también es un fallo, aunque FeedErrors venga vacío.
            $has_failure = $failed > 0 || 'Error' === $status || 'Canceled' === $status;
            $message = $errors ? implode(' · ', array_slice($errors, 0, 3)) : '';
            if ('' === $message && $failed > 0) {
                $message = 'Homecenter no pudo procesar ' . $failed . ' registro(s).';
            }

            foreach ($ids as $id) {
                if ('image' === $mode) {
                    break;
                }
                if ('remove' === $mode) {
                    continue;
                }
                if ($has_failure) {
                    HCS_PRODUCT::patchHcs($id, [
                        'remote_status' => 'error',
                        'last_error' => $message ? $message : 'El feed terminó con error.',
                        'last_sync' => current_time('mysql'),
                    ]);
                    continue;
                }
                if ('deactivate' === $mode) {
                    HCS_PRODUCT::patchHcs($id, [
                        'remote_status' => 'inactive',
                        'remote_published' => 1,
                        'last_error' => '',
                        'last_sync' => current_time('mysql'),
                    ]);
                    continue;
                }
                HCS_PRODUCT::patchHcs($id, [
                    'remote_status' => 'published',
                    'remote_published' => 1,
                    'last_error' => '',
                    'last_sync' => current_time('mysql'),
                ]);
            }
        }

        if ('' === $out['message']) {
            $out['message'] = $out['finished']
                ? $out['finished'] . ' feed(s) terminaron.'
                : ($out['checked'] ? 'Los feeds siguen en proceso.' : 'No hay feeds pendientes.');
        }

        return $out;
    }
}
