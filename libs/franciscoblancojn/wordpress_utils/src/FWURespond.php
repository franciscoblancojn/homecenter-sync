<?php

namespace franciscoblancojn\wordpress_utils;

if (
    !class_exists("FWURespond")
    &&
    function_exists("add_action")
    &&
    function_exists("esc_html")
) {
    class FWURespond extends FWUComponent
    {
        public static function html(...$args): string
        {
            $respond = $args[0] ?? [];
            if (empty($respond) || !isset($respond['status'])) {
                return '';
            }

            $status = $respond['status'];
            $message = $respond['message'] ?? '';
            $data = $respond['data'] ?? [];

            $titlePrefix = '';
            if (!empty($data['post_id']) && function_exists('get_the_title')) {
                $titlePrefix = esc_html(get_the_title($data['post_id'])) . ' &rArr; ';
            } elseif (!empty($data['title'])) {
                $titlePrefix = esc_html($data['title']) . ' &rArr; ';
            }

            $viewUrl = !empty($data['url']) ? $data['url'] : '';

            ob_start();
?>
            <p class="fwue-message <?= esc_attr($status) ?>">
                <?= $titlePrefix ?>
                <?= self::parseMessage($message) ?>
                <?php if ($status === 'ok' && $viewUrl): ?>
                    <a href="<?= esc_url($viewUrl) ?>" target="_blank" rel="noopener noreferrer" class="button button-primary fwue-btn-right">
                        Ver
                    </a>
                <?php endif; ?>
            </p>
<?php
            return ob_get_clean();
        }

        public static function css(): string
        {
            ob_start();
?>
            <style>
                .fwue-message {
                    font-weight: 900;
                    position: sticky;
                    left: 0;
                    top: 2.5rem;
                    padding: 1rem;
                    border-radius: .5rem;
                    display: flex;
                    align-items: center;
                    flex-wrap: wrap;
                    gap: .5rem;
                    z-index: 10;
                }
                .fwue-message.error {
                    color: #fff;
                    background: #d63638;
                }
                .fwue-message.ok {
                    color: #fff;
                    background: #25992f;
                }
                .fwue-message a {
                    color: inherit;
                }
                .fwue-message .button {
                    margin-left: auto;
                }
                .fwue-btn-right {
                    margin-left: auto;
                }
            </style>
<?php
            return ob_get_clean();
        }

        public static function js(): string
        {
            return '';
        }

        private static function parseMessage($text): string
        {
            return preg_replace(
                '/(https?:\/\/[^\s]+)/',
                '<a href="$1" target="_blank" rel="noopener noreferrer">$1</a>',
                $text
            );
        }
    }
}
