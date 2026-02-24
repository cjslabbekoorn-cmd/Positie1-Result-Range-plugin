<?php
/**
 * Plugin Name: Listing Results Range
 * Description: Elementor widget that outputs a results range (start–end of total) for JetEngine/Elementor listings. Includes WPML String Translation support and improved AJAX updates for JetSmartFilters.
 * Version: 1.0.8
 * Author: Positie1
 * Text Domain: results-range-wpml
 */

if ( ! defined( 'ABSPATH' ) ) exit;

define( 'RRW_VERSION', '1.0.8' );
define( 'RRW_PATH', plugin_dir_path( __FILE__ ) );
define( 'RRW_URL', plugin_dir_url( __FILE__ ) );

/**
 * GitHub updater settings
 *
 * Public repo: no token needed.
 * Private repo: define P1_GITHUB_TOKEN in wp-config.php.
 */
if ( ! defined( 'RRW_GH_REPO' ) ) {
    // Format: owner/repo
    define( 'RRW_GH_REPO', 'cjslabbekoorn-cmd/Positie1-Result-Range-plugin' );
}
if ( ! defined( 'RRW_GH_ASSET_PREFIX' ) ) {
    // Asset name example: listing-results-range-{version}.zip
    define( 'RRW_GH_ASSET_PREFIX', 'listing-results-range-' );
}

require_once RRW_PATH . 'includes/class-rrw-core.php';
require_once RRW_PATH . 'includes/class-rrw-github-updater.php';

add_action( 'plugins_loaded', function() {
	// Initialize updater early so WP can detect updates.
	if ( class_exists( 'RRW_GitHub_Updater' ) ) {
		RRW_GitHub_Updater::init();
	}

	\RRW_Core::instance();
} );
