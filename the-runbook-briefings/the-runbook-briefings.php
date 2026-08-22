<?php
/**
 * Plugin Name:       The Runbook Briefings
 * Plugin URI:        https://github.com/rss-666/sit-n-spin
 * Description:       Turns approved hosting and WordPress security RSS stories into original, source-attributed editorial briefings.
 * Version:           1.0.0
 * Requires at least: 6.4
 * Requires PHP:      8.1
 * Author:            The Runbook
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       the-runbook-briefings
 */

defined( 'ABSPATH' ) || exit;

define( 'TRB_VERSION', '1.0.0' );
define( 'TRB_DB_VERSION', '1.0.0' );
define( 'TRB_PLUGIN_FILE', __FILE__ );
define( 'TRB_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'TRB_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once TRB_PLUGIN_DIR . 'includes/class-trb-url.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-filter.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-duplicate-detector.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-settings.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-credentials.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-database.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-logger.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-activator.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-source-repository.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-item-repository.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-importer.php';
require_once TRB_PLUGIN_DIR . 'includes/interface-trb-ai-provider.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-prompt-builder.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-openai-provider.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-briefing.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-draft.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-security.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-actions.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-admin.php';
require_once TRB_PLUGIN_DIR . 'includes/class-trb-plugin.php';

register_activation_hook( __FILE__, array( 'TRB_Activator', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'TRB_Activator', 'deactivate' ) );

add_action(
    'plugins_loaded',
    static function (): void {
        TRB_Plugin::instance()->run();
    }
);
