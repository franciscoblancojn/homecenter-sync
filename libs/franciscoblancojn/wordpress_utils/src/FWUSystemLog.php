<?php

namespace franciscoblancojn\wordpress_utils;

if (
    !class_exists("FWUSystemLog")
    &&
    function_exists("get_option")
    &&
    function_exists("update_option")
    &&
    function_exists("wp_json_encode")
    &&
    function_exists("esc_html")
    &&
    function_exists("add_action")
    &&
    function_exists("get_site_url")
    &&
    function_exists("add_options_page")
) {
    class FWUSystemLog
    {
        static function keys($key)
        {
            $key = strtoupper($key);
            $KEY = defined("{$key}_KEY") ? constant("{$key}_KEY") : $key;
            $LOG = defined("{$key}_LOG") ? constant("{$key}_LOG") : true;
            $LOG_KEY = defined("{$key}_LOG_KEY") ? constant("{$key}_LOG_KEY") : $key . "_LOG_KEY";
            $LOG_COUNT = defined("{$key}_LOG_COUNT") ? constant("{$key}_LOG_COUNT") : 100;
            return [
                "KEY" => $KEY,
                "LOG" => $LOG,
                "LOG_KEY" => $LOG_KEY,
                "LOG_COUNT" => $LOG_COUNT,
            ];
        }
        static function get($key)
        {
            $log = \get_option(FWUSystemLog::keys($key)['LOG_KEY'], "[]");

            if (!$log) $log = "[]";

            return json_decode($log, true);
        }
        static function add($key, $newLog)
        {
            $key = strtoupper($key);

            $type = str_replace(" ", "_", strtoupper($newLog['type'] ?? "NO_TYPE"));

            $log = FWUSystemLog::get($key);

            $log[$type] ??= [];
            $log[$type][] = $newLog;
            $CONFIG = FWUSystemLog::keys($key);
            $limit = $CONFIG['LOG_COUNT'];

            $log[$type] = array_slice($log[$type], -$limit);

            \update_option($CONFIG['LOG_KEY'], json_encode($log));
        }
        static function render_page($key)
        {
            try {
                $CONFIG = FWUSystemLog::keys($key);
                $key = strtoupper($key);
                if (isset($_POST['clear-log']) && $_POST['clear-log'] == "1") {
                    \update_option($CONFIG['LOG_KEY'], "[]");
                }
                $log = FWUSystemLog::get($key);
                $limit = $CONFIG['LOG_COUNT'];
?>
                <div
                    class="<?= $key ?>_LOG_PAGE_VIEW">
                    <div
                        class="<?= $key ?>_LOG_PAGE_VIEW_HEADER">
                        <h1 style="color:inherit">
                            Solo se guardan las <?= $limit ?> peticiones por tipo
                        </h1>
                        <form method="post" style="margin-left: auto;">
                            <button class="button button-primary">Recargar</button>
                        </form>
                        <form method="post">
                            <input type="hidden" name="clear-log" value="1">
                            <button class="button button-primary">Borrar Log</button>
                        </form>

                    </div>
                    <script>
                        function copyJson(id) {
                            const element = document.getElementById(id);
                            const text = element.innerText;

                            // Crear textarea temporal
                            const textarea = document.createElement("textarea");
                            textarea.value = text;
                            document.body.appendChild(textarea);

                            textarea.select();
                            textarea.setSelectionRange(0, 999999); // Para mobile

                            try {
                                document.execCommand("copy");
                                showCopiedMessage(id);
                            } catch (err) {
                                console.error("Error al copiar", err);
                            }

                            document.body.removeChild(textarea);
                        }

                        function showCopiedMessage(id) {
                            const btn = document.querySelector(`[onclick="copyJson('${id}')"]`);
                            if (!btn) return;

                            const original = btn.innerText;
                            btn.innerText = "Copiado ✅";

                            setTimeout(() => {
                                btn.innerText = original;
                            }, 1500);
                        }
                        const json_log = <?= \wp_json_encode($log) ?>;
                    </script>
                    <style>
                        *:has(.<?= $key ?>_LOG_PAGE_VIEW_HEADER) {
                            position: static;
                        }

                        #wpbody-content>*:not(.<?= $key ?>_LOG_PAGE_VIEW) {
                            display: none !important;
                        }

                        #wpcontent {
                            position: relative;
                        }

                        .<?= $key ?>_LOG_PAGE_VIEW_HEADER {
                            padding: .25rem 1.5rem;
                            margin-bottom: 1rem;
                            z-index: 1000;
                            display: flex;
                            gap: 1rem;
                            align-items: center;
                            background: #1d2327;
                            color: #f0f0f1;
                            box-shadow: -20px 0 #1d2327;
                        }

                        /* Contenedor general */
                        details {
                            margin-bottom: 1rem;
                            border: 1px solid #dcdcde;
                            border-radius: 8px;
                            background: #fff;
                            overflow: hidden;
                        }

                        /* Header tipo collapse */
                        details summary {
                            cursor: pointer;
                            padding: 12px 16px;
                            font-weight: 600;
                            font-size: 14px;
                            background: #f6f7f7;
                            list-style: none;
                            position: relative;
                            transition: background 0.2s ease;
                        }

                        /* Hover */
                        details summary:hover {
                            background: #e5e5e5;
                        }

                        /* Quitar flecha default */
                        details summary::-webkit-details-marker {
                            display: none;
                        }

                        /* Flecha custom */
                        details summary::after {
                            content: "▸";
                            position: absolute;
                            right: 16px;
                            font-size: 14px;
                            transition: transform 0.2s ease;
                        }

                        /* Rotar cuando está abierto */
                        details[open] summary::after {
                            transform: rotate(90deg);
                        }

                        /* Contenido interno */
                        details>div {
                            padding: 16px;
                            background: #ffffff;
                            border-top: 1px solid #dcdcde;
                            max-height: 75dvh;
                            overflow: auto;
                        }
                    </style>
                    <?php
                    foreach ($log as $key => $value) {
                    ?>
                        <details>
                            <summary style="display: flex;">
                                <span><?= $key ?> </span>
                                <span style="margin-left: auto; margin-right:1rem">(<?= count($value) ?>)</span>
                            </summary>
                            <div>
                                <?php
                                for ($i = 0; $i < count($value); $i++) {
                                    $print = wp_json_encode(
                                        array_reverse($value[$i]),
                                        JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
                                    );

                                    $id = 'json_' . $key . '_' . $i;
                                ?>
                                    <div style="position:relative;margin-bottom:1rem;">

                                        <button
                                            type="button"
                                            onclick="copyJson('<?= $id ?>')"
                                            style="
                                    position:absolute;
                                    top:.5rem;
                                    right:.5rem;
                                    cursor:pointer;
                                    background:#00ff88;
                                    border:0;
                                    padding:.25rem .75rem;
                                    border-radius:.35rem;
                                    font-weight:bold;
                                ">
                                            Copiar
                                        </button>

                                        <pre
                                            id="<?= $id ?>"
                                            style="background:#1d2327;color:#00ff88;padding:1rem;border-radius:.5rem;overflow:auto;"><?= \esc_html($print) ?></pre>

                                    </div>
                                <?php
                                }
                                ?>
                            </div>
                        </details>
                    <?php
                    }
                    ?>
                </div>
<?php
            } catch (\Throwable $th) {
                echo "Error: " . $th->getMessage();
            }
        }
        static function init($key)
        {
            $key = strtoupper($key);
            static $initialized = [];

            if (isset($initialized[$key])) return;
            $initialized[$key] = true;

            // Registrar hooks
            \add_action('admin_bar_menu', function ($admin_bar) use ($key) {
                $admin_bar->add_menu([
                    'id' => "{$key}_LOG",
                    'title' => "{$key}_LOG",
                    'href' => \get_site_url() . "/wp-admin/options-general.php?page={$key}_LOG"
                ]);
            }, 100);

            \add_action('admin_menu', function () use ($key) {
                \add_options_page(
                    "{$key}_LOG",
                    "{$key}_LOG",
                    'manage_options',
                    "{$key}_LOG",
                    function () use ($key) {
                        FWUSystemLog::render_page($key);
                    }
                );
            });
        }
    }
}
