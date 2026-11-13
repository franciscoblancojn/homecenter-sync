<?php

namespace franciscoblancojn\wordpress_utils;

if (
    !class_exists("FWUExportImport")
    &&
    function_exists("add_action")
) {
    class FWUExportImport extends FWUComponent
    {
        public static function html(...$args): string
        {
            $modalId = $args[0] ?? '';
            $title = $args[1] ?? '';
            $action = $args[2] ?? '';
            $payload = $args[3] ?? [];
            $reload = $args[4] ?? true;
            $payloadJson = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
            $reloadJs = $reload ? 'true' : 'false';

            $content = '
                <p><input type="file" class="fwue-import-file" accept=".json"></p>
                <textarea class="fwue-import-data" rows="12" placeholder="Pega el JSON aquí o selecciona un archivo..."></textarea>
                <div class="fwue-modal-actions">
                    <button type="button" class="button button-primary fwue-import-btn" onclick="fwueImport(\'' . $action . '\',\'' . $payloadJson . '\',\'' . $modalId . '\',' . $reloadJs . ')">Importar</button>
                    <button type="button" class="button" onclick="fwueCloseModal(\'' . $modalId . '\')">Cancelar</button>
                </div>';

            return FWUModal::html($modalId, $title, $content);
        }

        public static function css(): string
        {
            ob_start();
?>
            <style>
                .fwue-modal-content textarea {
                    width: 100%;
                    min-height: 200px;
                    font-family: monospace;
                }
                .fwue-modal-content .fwue-modal-actions {
                    margin-top: 16px;
                    display: flex;
                    gap: 8px;
                }
            </style>
<?php
            return ob_get_clean();
        }

        public static function js(): string
        {
            ob_start();
?>
            <script>
                function fwueExport(action, payloadJson, filename) {
                    var payload = JSON.parse(payloadJson || '{}');
                    var formData = new FormData();
                    formData.append('action', action);
                    Object.keys(payload).forEach(function(k) { formData.append(k, payload[k]); });
                    fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(data) {
                            var blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
                            var a = document.createElement('a');
                            a.href = URL.createObjectURL(blob);
                            a.download = filename;
                            a.click();
                            URL.revokeObjectURL(a.href);
                        });
                }
                function fwueImport(action, payloadJson, modalId, reload) {
                    var textarea = document.querySelector('#' + modalId + ' .fwue-import-data');
                    var btn = document.querySelector('#' + modalId + ' .fwue-import-btn');
                    var raw = (textarea.value || '').trim();
                    if (!raw) { alert('Pega o carga un JSON primero.'); return; }
                    try { JSON.parse(raw); } catch (e) { alert('JSON inv\u00e1lido.'); return; }
                    btn.disabled = true;
                    btn.textContent = 'Importando...';
                    var formData = new FormData();
                    formData.append('action', action);
                    var payload = JSON.parse(payloadJson || '{}');
                    Object.keys(payload).forEach(function(k) { formData.append(k, payload[k]); });
                    formData.append('data', raw);
                    fetch(ajaxurl, { method: 'POST', body: formData })
                        .then(function(r) { return r.json(); })
                        .then(function(res) {
                            btn.disabled = false;
                            btn.textContent = 'Importar';
                            if (res.success) {
                                alert(res.data.message || 'Importado correctamente.');
                                fwueCloseModal(modalId);
                                if (reload) location.reload();
                            } else {
                                alert(res.data.message || 'Error al importar.');
                            }
                        });
                }
                document.addEventListener('change', function(e) {
                    if (e.target.classList.contains('fwue-import-file')) {
                        var file = e.target.files[0];
                        if (!file) return;
                        var reader = new FileReader();
                        var textarea = e.target.closest('.fwue-modal-content').querySelector('.fwue-import-data');
                        reader.onload = function(ev) { textarea.value = ev.target.result; };
                        reader.readAsText(file);
                    }
                });
            </script>
<?php
            return ob_get_clean();
        }

        public static function exportButtonHtml($action, $payload, $filename, $label = 'Exportar JSON'): string
        {
            $payloadJson = htmlspecialchars(json_encode($payload), ENT_QUOTES, 'UTF-8');
            return '<button type="button" class="button" onclick="fwueExport(\'' . $action . '\',\'' . $payloadJson . '\',\'' . $filename . '\')">' . $label . '</button>';
        }

        public static function importButtonHtml($modalId, $label = 'Importar JSON'): string
        {
            return '<button type="button" class="button" onclick="fwueOpenModal(\'' . $modalId . '\')">' . $label . '</button>';
        }
    }
}
