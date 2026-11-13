<?php

namespace franciscoblancojn\wordpress_utils;

if (!class_exists("FWUCollapse") && function_exists("add_action")) {
    class FWUCollapse extends FWUComponent
    {
        public static function html(...$args): string
        {
            $title = $args[0] ?? '';
            $content = $args[1] ?? '';
            $open = $args[2] ?? false;
            ob_start();
?>
            <details class="fwue-collapse" <?= $open ? 'open' : '' ?>>
                <summary><?= $title ?></summary>
                <div class="fwue-collapse-content"><?= $content ?></div>
            </details>
<?php
            return ob_get_clean();
        }

        public static function css(): string
        {
            ob_start();
?>
            <style>
                .fwue-collapse {
                    margin-bottom: 1rem;
                    border: 1px solid #dcdcde;
                    border-radius: 8px;
                    background: #fff;
                    overflow: hidden;
                }
                .fwue-collapse summary {
                    cursor: pointer;
                    padding: 12px 16px;
                    font-weight: 600;
                    font-size: 14px;
                    background: #f6f7f7;
                    list-style: none;
                    display: flex;
                    align-items: center;
                    transition: background 0.2s ease;
                    user-select: none;
                }
                .fwue-collapse summary:hover {
                    background: #e5e5e5;
                }
                .fwue-collapse summary::-webkit-details-marker {
                    display: none;
                }
                .fwue-collapse summary::after {
                    content: "\25B8";
                    margin-left: auto;
                    font-size: 14px;
                    transition: transform 0.2s ease;
                }
                .fwue-collapse[open] summary::after {
                    transform: rotate(90deg);
                }
                .fwue-collapse-content {
                    padding: 16px;
                    background: #fff;
                    border-top: 1px solid #dcdcde;
                    max-height: 75dvh;
                    overflow: auto;
                }
            </style>
<?php
            return ob_get_clean();
        }

        public static function js(): string
        {
            return '';
        }
    }
}
