<?php

$feeds_store = new HCS_USE_DATA_FEEDS();
$feeds = array_reverse($feeds_store->all(), true);
$pending = count($feeds_store->pending());

?>
<p class="description">Cada envío a Homecenter crea un feed asíncrono. Aquí puedes consultar su estado (FeedStatus) y ver errores devueltos por Seller Center.</p>
<p>
    <button type="button" class="button" id="hcs-poll-feeds">Consultar feeds pendientes</button>
    <button type="button" class="button" id="hcs-process-feeds">Procesar cola ahora</button>
    <span class="hcs-muted">Feeds pendientes: <strong id="hcs-pending-feeds"><?= (int) $pending ?></strong>. Cola de productos: <strong class="hcs-queue-count"><?= (int) HCS_QUEUE::count() ?></strong>.</span>
</p>
<p id="hcs-feeds-message" class="hcs-inline-message"></p>

<table class="wp-list-table widefat fixed striped hcs-feeds-table">
    <thead>
        <tr>
            <th>Feed</th>
            <th>Acción</th>
            <th>Estado</th>
            <th>Registros</th>
            <th>Actualizado</th>
            <th>Errores</th>
        </tr>
    </thead>
    <tbody>
        <?php if (!$feeds) : ?>
            <tr><td colspan="6">Aún no se han enviado feeds.</td></tr>
        <?php endif; ?>
        <?php foreach ($feeds as $feed) : ?>
            <tr>
                <td><code><?= esc_html($feed['id'] ?? '') ?></code></td>
                <td>
                    <?= esc_html($feed['action'] ?? '') ?>
                    <?php if (!empty($feed['mode'])) : ?><div class="hcs-muted"><?= esc_html($feed['mode']) ?></div><?php endif; ?>
                </td>
                <td><span class="hcs-badge hcs-badge--<?= esc_attr(sanitize_key($feed['status'] ?? '')) ?>"><?= esc_html($feed['status'] ?? '') ?></span></td>
                <td>
                    <?= (int) ($feed['total'] ?? 0) ?>
                    <?php if (!empty($feed['failed'])) : ?><span class="hcs-error">(<?= (int) $feed['failed'] ?> con error)</span><?php endif; ?>
                </td>
                <td class="hcs-muted"><?= esc_html($feed['updated'] ?? ($feed['created'] ?? '')) ?></td>
                <td>
                    <?php if (!empty($feed['errors']) && is_array($feed['errors'])) : ?>
                        <?php foreach ($feed['errors'] as $error) : ?>
                            <div class="hcs-error"><?= esc_html($error) ?></div>
                        <?php endforeach; ?>
                    <?php else : ?>
                        <span class="hcs-muted">—</span>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>
