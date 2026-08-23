<?php
/**
 * Plugin Name:       Plague Dr Suno Publisher
 * Plugin URI:        https://plaguedr.online/
 * Description:       Publish Suno, local audio, YouTube, and Vimeo releases to native Plague Dr Universe music content.
 * Version:           1.3.2
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            Plague Dr
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       plague-dr-suno-publisher
 */

defined( 'ABSPATH' ) || exit;

define( 'PDRS_VERSION', '1.3.2' );
define( 'PDRS_PLUGIN_FILE', __FILE__ );
define( 'PDRS_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'PDRS_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once PDRS_PLUGIN_DIR . 'includes/class-pdrs-suno-url.php';
require_once PDRS_PLUGIN_DIR . 'includes/class-pdrs-metadata.php';
require_once PDRS_PLUGIN_DIR . 'includes/class-pdrs-renderer.php';
require_once PDRS_PLUGIN_DIR . 'includes/class-pdrs-pdu-integration.php';
require_once PDRS_PLUGIN_DIR . 'includes/class-pdrs-admin.php';
require_once PDRS_PLUGIN_DIR . 'includes/class-pdrs-plugin.php';

register_activation_hook( __FILE__, array( 'PDRS_Plugin', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'PDRS_Plugin', 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function (): void {
        PDRS_Plugin::instance()->run();
    }
);
