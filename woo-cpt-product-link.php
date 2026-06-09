<?php
/**
 * Plugin Name: Woo CPT Product Link
 * Description: Link custom post type entries to real WooCommerce products and render product price, details, add-to-cart, or buy-now controls from CPT templates.
 * Version: 1.1.0
 * Author: MixbusMarketing
 * Author URI: https://mixbusmarketing.com/
 * Plugin URI: https://github.com/blissguy/Woo-CPT-Product-Link
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Text Domain: woo-cpt-product-link
 *
 * @package WooCptProductLink
 */

defined( 'ABSPATH' ) || exit;

define( 'WCPL_VERSION', '1.1.0' );
define( 'WCPL_PLUGIN_FILE', __FILE__ );
define( 'WCPL_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

if ( file_exists( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
}

/**
 * Autoload plugin classes from the src/ directory.
 *
 * @param string $class Fully-qualified class name.
 */
spl_autoload_register(
	function ( $class ) {
		$prefix = 'MixbusMarketing\\WooCptProductLink\\';

		if ( 0 !== strpos( $class, $prefix ) ) {
			return;
		}

		$relative = substr( $class, strlen( $prefix ) );
		$file     = WCPL_PLUGIN_DIR . 'src/' . str_replace( '\\', '/', $relative ) . '.php';

		if ( file_exists( $file ) ) {
			require_once $file;
		}
	}
);

add_action( 'plugins_loaded', array( \MixbusMarketing\WooCptProductLink\Plugin::class, 'boot' ) );

/**
 * Public helper for theme/plugin usage.
 *
 * @param int $post_id CPT post ID.
 * @return WC_Product|false
 */
function wcpl_get_product( $post_id = 0 ) {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
		return false;
	}

	$product_id = \MixbusMarketing\WooCptProductLink\Product_Resolver::get_related_product_id( $post_id );

	return $product_id ? wc_get_product( $product_id ) : false;
}
