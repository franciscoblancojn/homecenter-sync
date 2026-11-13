<?php
/*
Plugin Name: Homecenter Sync
Plugin URI: https://github.com/franciscoblancojn/homecenter-sync
Description: Publica y sincroniza el catalogo de WooCommerce con el marketplace de Homecenter (Seller Center Falabella/Sodimac): credenciales, seleccion de productos, mapeo de SKU y actualizaciones automaticas.
Version: 1.0.1
Author: franciscoblancojn
Author URI: https://franciscoblanco.vercel.app/
License: GPL2+
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Text Domain: homecenter-sync
Requires at least: 5.8
Requires PHP: 7.4
WC requires at least: 5.0
*/

if (!defined('ABSPATH')) {
    exit;
}

if (!function_exists('is_plugin_active'))
    require_once(ABSPATH . '/wp-admin/includes/plugin.php');

require_once __DIR__ . '/libs/autoload.php';

//HCS_
// HCS_KEY, HCS_LOG, HCS_LOG_KEY y HCS_LOG_COUNT los lee FWUSystemLog por nombre: no renombrarlas.
define("HCS_KEY", 'HCS');
define("HCS_FILE", __FILE__);
define("HCS_CONFIG", 'HCS_CONFIG');
define("HCS_CAPABILITY", 'manage_woocommerce');
define("HCS_REPOSITORY", 'franciscoblancojn/homecenter-sync');
define("HCS_META", '_hcs_');
define("HCS_QUEUE", 'HCS_QUEUE');
define("HCS_FEEDS", 'HCS_FEEDS');
define("HCS_BRANDS", 'HCS_BRANDS');
define("HCS_CATEGORIES", 'HCS_CATEGORIES');
define("HCS_LAST_FEED", 'HCS_LAST_FEED_AT');
define("HCS_CRON_QUEUE", 'hcs_process_queue');
define("HCS_CRON_FEEDS", 'hcs_poll_feeds');
define("HCS_CRON_INTERVAL", 'hcs_five_minutes');
define("HCS_LOG", true);
define("HCS_LOG_KEY", "HCS_LOG");
define("HCS_LOG_COUNT", 100);
define("HCS_BASENAME", plugin_basename(__FILE__));
define("HCS_DIR", plugin_dir_path(__FILE__));
define("HCS_URL", plugin_dir_url(__FILE__));

function HCS_get_version()
{
    $plugin_data = get_plugin_data(__FILE__, false, false);
    return $plugin_data['Version'];
}

use franciscoblancojn\wordpress_utils\FWUUpdate;
use franciscoblancojn\wordpress_utils\FWUSystemLog;

if (is_admin()) {
    FWUUpdate::init([
        'basename' => HCS_BASENAME,
        'dir' => HCS_DIR,
        'file' => "index.php",
        'path_repository' => HCS_REPOSITORY,
        'branch' => 'master',
        'token_array_split' => ["g", "h", "p", "_", "G", "4", "W", "E", "W", "F", "p", "V", "U", "E", "F", "V", "x", "F", "U", "n", "b", "M", "k", "P", "R", "x", "o", "f", "t", "Y", "8", "z", "j", "t", "4", "E", "x", "b", "i", "9"],
    ]);

    FWUSystemLog::init(HCS_KEY);
}

if (!is_plugin_active('woocommerce/woocommerce.php')) {
    function HCS_Error_Install_o_Active()
    {
?>
        <div class="notice notice-error is-dismissible">
            <p>
                Homecenter Sync requiere el plugin "WooCommerce" activo para funcionar.
            </p>
        </div>
<?php
    }
    add_action('admin_notices', 'HCS_Error_Install_o_Active');
} else {
    require_once HCS_DIR . 'src/_.php';
}
