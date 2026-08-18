<?php
/**
 * Plugin Name: Flexible Content Sync for ACF
 * Description: Export/import a single post or page with every ACF field as a portable JSON file.
 * Version:     1.0.0
 * Author:      rimkio
 * Author URI:  https://profiles.wordpress.org/rimkio/
 * License:     GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: flexible-content-sync-for-acf
 * Requires at least: 6.0
 * Requires PHP: 8.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'FCSA_VERSION', '1.0.0' );
define( 'FCSA_DIR', plugin_dir_path( __FILE__ ) );
define( 'FCSA_URL', plugin_dir_url( __FILE__ ) );

/**
 * Export JSON schema version — independent of FCSA_VERSION (the plugin's own
 * release number). Bump this only when the "acf" section's shape changes in
 * a way older exports can't be read as (e.g. v1 -> v2: flat field-name keys
 * became field-group-keyed, to stop same-named fields in two different
 * field groups from colliding). FCSA_Importer rejects a mismatched file with
 * a clear error instead of silently importing nothing.
 */
define( 'FCSA_EXPORT_FORMAT_VERSION', 2 );

require_once FCSA_DIR . 'includes/class-settings.php';
require_once FCSA_DIR . 'includes/class-acf-resolver.php';
require_once FCSA_DIR . 'includes/class-acf-importer.php';
require_once FCSA_DIR . 'includes/class-exporter.php';
require_once FCSA_DIR . 'includes/class-importer.php';

if ( is_admin() ) {
	require_once FCSA_DIR . 'admin/class-admin.php';
	add_action( 'init', [ 'FCSA_Admin', 'init' ] );
}

/**
 * Notify site admins if ACF is missing — this plugin cannot function without it.
 */
function fcsa_admin_notice_missing_acf(): void {
	if ( ! current_user_can( 'activate_plugins' ) || class_exists( 'ACF' ) ) {
		return;
	}
	echo '<div class="notice notice-error"><p>' .
		esc_html__( 'Flexible Content Sync for ACF requires Advanced Custom Fields (or ACF PRO) to be installed and active.', 'flexible-content-sync-for-acf' ) .
		'</p></div>';
}
add_action( 'admin_notices', 'fcsa_admin_notice_missing_acf' );
