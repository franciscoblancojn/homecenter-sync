/* Homecenter Sync — admin (ES5 + jQuery). Datos de PHP en window.HCS_ADMIN. */
jQuery(function ($) {
    var $page = $('.hcs-page');
    var $metabox = $('.hcs-metabox');

    function postSettings(action, data, onDone, selector) {
        var $msg = $(selector || '#hcs-connection-message');
        if (!$msg.length || !$msg.is(':visible')) {
            $msg = $('#hcs-feeds-message');
        }
        $msg.removeClass('is-error is-ok').text(HCS_ADMIN.processing || 'Procesando…');
        $.post(HCS_ADMIN.ajax, $.extend({
            action: action,
            nonce: HCS_ADMIN.nonce_settings
        }, data || {})).done(function (res) {
            var ok = res && res.success;
            var msg = ok ? (res.data && res.data.message ? res.data.message : 'Listo.') : (res && res.data ? res.data : HCS_ADMIN.error);
            $msg.toggleClass('is-ok', !!ok).toggleClass('is-error', !ok).text(msg);
            if (ok && res.data && typeof res.data.pending !== 'undefined') {
                $('.hcs-queue-count, #hcs-queue-count').text(res.data.pending);
            }
            if (onDone) {
                onDone(res);
            }
        }).fail(function () {
            $msg.addClass('is-error').text(HCS_ADMIN.error || 'Error de conexión.');
        });
    }

    if ($page.length) {
        $page.on('click', '.nav-tab', function () {
            $('#hcs-tab').val($(this).data('tab'));
        });

        $('#hcs-test').on('click', function (e) {
            e.preventDefault();
            postSettings('hcs_test');
        });
        $('#hcs-catalog').on('click', function (e) {
            e.preventDefault();
            postSettings('hcs_catalog', {}, function (res) {
                if (res && res.success) {
                    window.location.reload();
                }
            });
        });
        $('#hcs-process').on('click', function (e) {
            e.preventDefault();
            postSettings('hcs_process', {}, function (res) {
                if (res && res.success) {
                    window.setTimeout(function () { window.location.reload(); }, 800);
                }
            }, '#hcs-connection-message');
        });
        $('#hcs-process-carga').on('click', function (e) {
            e.preventDefault();
            postSettings('hcs_process', {}, function (res) {
                if (res && res.success) {
                    window.setTimeout(function () { window.location.reload(); }, 800);
                }
            }, '#hcs-carga-message');
        });
        $('#hcs-process-feeds, #hcs-poll-feeds').on('click', function (e) {
            e.preventDefault();
            var action = $(this).attr('id') === 'hcs-poll-feeds' ? 'hcs_poll' : 'hcs_process';
            postSettings(action, {}, function (res) {
                if (res && res.success) {
                    window.setTimeout(function () { window.location.reload(); }, 800);
                }
            }, '#hcs-feeds-message');
        });

        $('#hcs-check-all').on('change', function () {
            $('.hcs-check').prop('checked', $(this).is(':checked'));
        });

        $('.hcs-bulk').on('click', function (e) {
            e.preventDefault();
            var $button = $(this);
            var action = $button.data('action');
            var ids = [];
            $('.hcs-check:checked').each(function () {
                ids.push($(this).val());
            });
            var scope = $('#hcs-scope-filtered').is(':checked') ? 'filtered' : 'selected';
            var $msg = $('#' + ($button.data('message') || 'hcs-products-message'));
            if (!ids.length && scope !== 'filtered') {
                $msg.addClass('is-error').removeClass('is-ok').text('Selecciona al menos un producto o marca "Aplicar a todos los resultados del filtro".');
                return;
            }
            if (action === 'deactivate' && !window.confirm(HCS_ADMIN.confirm_deactivate || '¿Despublicar en Homecenter?')) {
                return;
            }
            $msg.removeClass('is-error is-ok').text(HCS_ADMIN.processing || 'Procesando…');

            var params = window.location.search ? window.location.search.substring(1) : '';
            var data = {
                action: 'hcs_queue',
                nonce: HCS_ADMIN.nonce_products,
                do: action === 'remote' ? '' : action,
                scope: scope
            };
            if (action === 'remote') {
                data.action = 'hcs_remote';
            }
            $.each(ids, function (i, id) { data['ids[' + i + ']'] = id; });
            if (scope === 'filtered' && params) {
                params.split('&').forEach(function (pair) {
                    var parts = pair.split('=');
                    if (parts.length >= 1 && parts[0]) {
                        var key = decodeURIComponent(parts[0]);
                        var val = parts.length > 1 ? decodeURIComponent(parts.slice(1).join('=').replace(/\+/g, ' ')) : '';
                        if (key !== 'page' && key !== 'tab' && typeof data[key] === 'undefined') {
                            data[key] = val;
                        }
                    }
                });
            }
            $.post(HCS_ADMIN.ajax, data).done(function (res) {
                var ok = res && res.success;
                var msg = ok ? (res.data && res.data.message ? res.data.message : 'Listo.') : (res && res.data ? res.data : HCS_ADMIN.error);
                $msg.toggleClass('is-ok', !!ok).toggleClass('is-error', !ok).text(msg);
                if (ok && res.data && typeof res.data.pending !== 'undefined') {
                    $('.hcs-queue-count, #hcs-queue-count').text(res.data.pending);
                }
                if (ok) {
                    window.setTimeout(function () { window.location.reload(); }, 900);
                }
            }).fail(function () {
                $msg.addClass('is-error').text(HCS_ADMIN.error || 'Error de conexión.');
            });
        });
    }

    if ($metabox.length) {
        $metabox.on('click', '.hcs-product-action', function (e) {
            e.preventDefault();
            var $btn = $(this);
            var action = $btn.data('action');
            var id = $btn.data('id');
            if (action === 'deactivate' && !window.confirm(HCS_ADMIN.confirm_deactivate || '¿Despublicar en Homecenter?')) {
                return;
            }
            var $msg = $metabox.find('.hcs-message[data-for="' + id + '"]');
            $msg.removeClass('is-error is-ok').text(HCS_ADMIN.processing || 'Procesando…');
            $.post(HCS_ADMIN.ajax, {
                action: 'hcs_queue',
                nonce: HCS_ADMIN.nonce,
                do: action === 'publish' ? 'publish' : action,
                'ids[0]': id
            }).done(function (res) {
                var ok = res && res.success;
                var msg = ok ? (res.data && res.data.message ? res.data.message : 'Listo.') : (res && res.data ? res.data : HCS_ADMIN.error);
                $msg.toggleClass('is-ok', !!ok).toggleClass('is-error', !ok).text(msg);
                if (ok) {
                    window.setTimeout(function () { window.location.reload(); }, 900);
                }
            }).fail(function () {
                $msg.addClass('is-error').text(HCS_ADMIN.error || 'Error de conexión.');
            });
        });
    }
});
