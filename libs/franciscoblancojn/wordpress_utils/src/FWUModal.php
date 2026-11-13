<?php

namespace franciscoblancojn\wordpress_utils;

if (!class_exists("FWUModal") && function_exists("add_action")) {
    class FWUModal extends FWUComponent
    {
        public static function html(...$args): string
        {
            $modalId = $args[0] ?? '';
            $title = $args[1] ?? '';
            $content = $args[2] ?? '';
            ob_start();
?>
            <div id="<?= $modalId ?>" class="fwue-modal">
                <div class="fwue-modal-content">
                    <span class="fwue-modal-close" onclick="fwueCloseModal('<?= $modalId ?>')">&times;</span>
                    <h3><?= $title ?></h3>
                    <?= $content ?>
                </div>
            </div>
<?php
            return ob_get_clean();
        }

        public static function css(): string
        {
            ob_start();
?>
            <style>
                .fwue-modal {
                    display: none;
                    position: fixed;
                    z-index: 9999;
                    inset: 0;
                    background: rgba(0, 0, 0, 0.5);
                    align-items: center;
                    justify-content: center;
                }
                .fwue-modal.open {
                    display: flex;
                }
                .fwue-modal-content {
                    background: #fff;
                    border-radius: 8px;
                    padding: 24px;
                    max-width: 600px;
                    width: 90%;
                    max-height: 80vh;
                    overflow-y: auto;
                    position: relative;
                    box-shadow: 0 4px 24px rgba(0, 0, 0, 0.2);
                }
                .fwue-modal-content h3 {
                    margin-top: 0;
                }
                .fwue-modal-close {
                    position: absolute;
                    top: 12px;
                    right: 16px;
                    font-size: 24px;
                    cursor: pointer;
                    color: #666;
                    line-height: 1;
                }
                .fwue-modal-close:hover {
                    color: #000;
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
                function fwueOpenModal(modalId) {
                    document.getElementById(modalId).classList.add('open');
                }
                function fwueCloseModal(modalId) {
                    document.getElementById(modalId).classList.remove('open');
                }
                document.addEventListener('click', function(e) {
                    var modal = e.target.closest('.fwue-modal');
                    if (modal && e.target === modal) fwueCloseModal(modal.id);
                });
            </script>
<?php
            return ob_get_clean();
        }
    }
}
