<?php
/**
 * Plugin Name: Woo CPT Product Link
 * Plugin URI:  https://mixbusmarketing.com/
 * Description: Link any custom post type to a WooCommerce product. Display price, add-to-cart, and buy-now buttons via shortcodes and Bricks dynamic tags.
 * Version:     1.0.0
 * Author:      MixbusMarketing
 * Author URI:  https://mixbusmarketing.com/
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Requires Plugins: woocommerce
 * Text Domain: woo-cpt-product-link
 * Domain Path: /languages
 * License:     GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'WOO_CPL_VERSION', '1.0.0' );
define( 'WOO_CPL_FILE', __FILE__ );
define( 'WOO_CPL_DIR', plugin_dir_path( __FILE__ ) );
define( 'WOO_CPL_URL', plugin_dir_url( __FILE__ ) );

require_once WOO_CPL_DIR . 'includes/class-woo-cpl-plugin.php';

register_activation_hook( __FILE__, array( 'WOO_CPL_Plugin', 'activate' ) );

/**
 * Register GitHub updates through Plugin Update Checker.
 */
function woo_cpl_init_update_checker(): void {
	$loader = WOO_CPL_DIR . 'lib/plugin-update-checker/load-v5p6.php';
	if ( ! file_exists( $loader ) ) {
		return;
	}

	require_once $loader;

	if ( ! class_exists( '\YahnisElsts\PluginUpdateChecker\v5\PucFactory' ) ) {
		return;
	}

	$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
		'https://github.com/blissguy/Woo-CPT-Product-Link',
		WOO_CPL_FILE,
		'woo-cpt-product-link'
	);

	$update_checker->setBranch( 'main' );
	$update_checker->getVcsApi()->enableReleaseAssets( '/^woo-cpt-product-link-[0-9A-Za-z_.-]+\.zip($|[?&#])/i' );
}

woo_cpl_init_update_checker();

add_action( 'plugins_loaded', function () {
	if ( ! class_exists( 'WooCommerce' ) ) {
		add_action( 'admin_notices', function () {
			echo '<div class="notice notice-error"><p><strong>Woo CPT Product Link</strong> requires WooCommerce to be installed and active.</p></div>';
		} );
		return;
	}
	WOO_CPL_Plugin::instance()->init();
} );
