<?php

namespace franciscoblancojn\wordpress_utils;

if (!class_exists("FWUPage") && function_exists("add_action")) {
    class FWUPage extends FWUComponent
    {
        public static function render(...$args): void
        {
            $pageKey = $args[0] ?? '';
            $class = static::class;
            if (empty(self::$rendered[$class])) {
                echo static::css();
                echo static::js($pageKey);
                self::$rendered[$class] = true;
            }
            echo static::html(...$args);
        }

        public static function tabs($tags, $defaultTag): void
        {
?>
            <div class="nav-tab-wrapper woo-nav-tab-wrapper">
                <?php foreach ($tags as $tag): ?>
                    <a
                        class="nav-tab <?= $tag['key'] === $defaultTag ? 'nav-tab-active' : '' ?>"
                        data-tab="<?= $tag['key'] ?>"
                        href="#tag-<?= $tag['key'] ?>">
                        <?= $tag['title'] ?>
                    </a>
                <?php endforeach; ?>
            </div>
<?php
        }

        public static function html(...$args): string
        {
            $pageKey = $args[0] ?? '';
            $title = $args[1] ?? '';
            $tags = $args[2] ?? [];
            $sectionsDir = $args[3] ?? '';
            $data = $args[4] ?? [];
            $defaultTag = $tags[0]['key'] ?? '';
            if (!empty($data)) extract($data);

            ob_start();
?>
            <div id="page-<?= $pageKey ?>" class="wrap">
                <h1><?= $title ?></h1>
                <div class="nav-tab-wrapper woo-nav-tab-wrapper">
                    <?php foreach ($tags as $tag): ?>
                        <a
                            class="nav-tab <?= $tag['key'] === $defaultTag ? 'nav-tab-active' : '' ?>"
                            data-tab="<?= $tag['key'] ?>"
                            href="#tag-<?= $tag['key'] ?>">
                            <?= $tag['title'] ?>
                        </a>
                    <?php endforeach; ?>
                </div>
                <?php foreach ($tags as $tag):
                    $sectionFile = rtrim($sectionsDir, '/') . '/' . $tag['key'] . '.php';
                ?>
                    <div class="tab-content <?= $tag['key'] === $defaultTag ? 'nav-tab-active' : '' ?>" id="<?= $tag['key'] ?>">
                        <?php if (file_exists($sectionFile)) require $sectionFile; ?>
                    </div>
                <?php endforeach; ?>
            </div>
<?php
            return ob_get_clean();
        }

        public static function css(): string
        {
            ob_start();
?>
            <style>
                .tab-content:not(.nav-tab-active) {
                    display: none;
                }
                .tab-content {
                    padding-top: 1rem;
                }
                .nav-tab {
                    cursor: pointer;
                }
                [type="submit"].fwue-loader {
                    position: relative;
                    color: transparent !important;
                }
                [type="submit"].fwue-loader::after {
                    content: '';
                    display: block;
                    position: absolute;
                    inset: 0;
                    margin: auto;
                    width: 1rem;
                    height: 1rem;
                    aspect-ratio: 1/1;
                    border-radius: 100%;
                    border: 2px solid #1d2327;
                    border-top-color: transparent;
                    animation: fwue-rotate 1s infinite;
                }
                [type="submit"].button-primary.fwue-loader::after {
                    border-color: #fff;
                    border-top-color: transparent;
                }
                @keyframes fwue-rotate {
                    to {
                        transform: rotate(360deg);
                    }
                }
            </style>
<?php
            return ob_get_clean();
        }

        public static function js(...$args): string
        {
            $pageKey = $args[0] ?? '';
            ob_start();
?>
            <script>
                document.addEventListener('DOMContentLoaded', function() {
                    try {
                        document.querySelectorAll('.nav-tab').forEach(function(btn) {
                            btn.addEventListener('click', function() {
                                document.querySelectorAll('.nav-tab, .tab-content')
                                    .forEach(function(el) { el.classList.remove('nav-tab-active'); });
                                this.classList.add('nav-tab-active');
                                var tabContent = document.getElementById(this.dataset.tab);
                                if (tabContent) tabContent.classList.add('nav-tab-active');
                            });
                        });
                        var hash = window.location.hash;
                        if (hash) {
                            var btn = document.querySelector(".nav-tab[href='" + hash + "']");
                            if (btn) btn.click();
                        }
<?php if ($pageKey): ?>
                        var page = document.getElementById("page-<?= $pageKey ?>");
                        if (page) {
                            page.querySelectorAll('[type="submit"]').forEach(function(btn) {
                                btn.addEventListener('click', function() {
                                    this.classList.add('fwue-loader');
                                });
                            });
                        }
<?php endif; ?>
                    } catch (e) {
                        console.error('FWUPage init error:', e);
                    }
                });
            </script>
<?php
            return ob_get_clean();
        }
    }
}
