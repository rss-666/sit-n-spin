<?php
/**
 * Plague Dr Song records are deliberately retained on uninstall.
 *
 * This prevents accidental loss of titles, descriptions, lyrics, and placement
 * choices. Delete the records under Plague Dr Music before uninstalling when
 * permanent removal is desired.
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

delete_option( 'pdrs_version' );
