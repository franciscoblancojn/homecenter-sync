<?php

use franciscoblancojn\wordpress_utils\FWUSystemLog;

$groups = class_exists(FWUSystemLog::class)
    ? FWUSystemLog::get(HCS_KEY)
    : json_decode((string) get_option(HCS_LOG_KEY, '[]'), true);
if (!is_array($groups)) {
    $groups = [];
}

$logs = [];
foreach ($groups as $group) {
    if (!is_array($group)) {
        continue;
    }
    foreach ($group as $entry) {
        $logs[] = is_array($entry) ? $entry : ['type' => '', 'data' => $entry];
    }
}
usort($logs, function ($a, $b) {
    return strcmp((string) ($b['time'] ?? ''), (string) ($a['time'] ?? ''));
});

?>
<p class="description">Registro técnico del plugin (últimos eventos). Los detalles de sincronización, feeds y errores de API quedan aquí para depuración.</p>
<?php if (!$logs) : ?>
    <p>No hay registros aún.</p>
<?php else : ?>
    <table class="wp-list-table widefat fixed striped hcs-log-table">
        <thead>
            <tr>
                <th>Fecha</th>
                <th>Tipo</th>
                <th>Detalle</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($logs as $entry) : ?>
                <?php
                $type = (string) ($entry['type'] ?? '');
                $time = (string) ($entry['time'] ?? '');
                $data = $entry['data'] ?? $entry;
                ?>
                <tr>
                    <td class="hcs-muted"><?= esc_html($time ? $time : '—') ?></td>
                    <td><code><?= esc_html($type) ?></code></td>
                    <td><pre class="hcs-pre"><?= esc_html(is_scalar($data) ? (string) $data : (string) wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) ?></pre></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>
