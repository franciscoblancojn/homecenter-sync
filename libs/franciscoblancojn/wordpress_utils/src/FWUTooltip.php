<?php

namespace franciscoblancojn\wordpress_utils;

if (!class_exists("FWUTooltip") && function_exists("add_action")) {
    class FWUTooltip extends FWUComponent
    {
        public static function html(...$args): string
        {
            $title = $args[0] ?? '';
            $text = $args[1] ?? '';
            ob_start();
?>
            <span class="fwue-tooltip">
                <?= $title ?>
                <span class="fwue-tooltip-icon dashicons dashicons-info"></span>
                <span class="fwue-tooltip-text"><?= $text ?></span>
            </span>
<?php
            return ob_get_clean();
        }

        public static function css(): string
        {
            ob_start();
?>
            <style>
                .fwue-tooltip {
                    position: relative;
                    cursor: pointer;
                    display: inline-flex;
                    align-items: center;
                    gap: 4px;
                }
                .fwue-tooltip-icon {
                    font-size: 16px;
                    width: 16px;
                    height: 16px;
                    color: #787c82;
                }
                .fwue-tooltip-text {
                    visibility: hidden;
                    opacity: 0;
                    width: 360px;
                    background: #1d2327;
                    color: #fff;
                    text-align: left;
                    padding: 8px 12px;
                    border-radius: 6px;
                    position: absolute;
                    z-index: 9999;
                    bottom: 130%;
                    left: 0;
                    transition: opacity 0.2s ease;
                    font-size: 12px;
                    line-height: 1.4;
                    font-weight: 400;
                    pointer-events: none;
                }
                .fwue-tooltip-text::after {
                    content: "";
                    position: absolute;
                    top: 100%;
                    left: 12px;
                    border-width: 5px;
                    border-style: solid;
                    border-color: #1d2327 transparent transparent transparent;
                }
                .fwue-tooltip:hover .fwue-tooltip-text {
                    visibility: visible;
                    opacity: 1;
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
