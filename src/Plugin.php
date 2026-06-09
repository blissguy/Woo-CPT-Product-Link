<?php
/**
 * Plugin bootstrap: wires hooks to the collaborating classes.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

defined( 'ABSPATH' ) || exit;

/**
 * Instantiates collaborators and registers their WordPress hooks.
 */
final class Plugin {

	/**
	 * Cart/button renderer instance (holds per-request form state).
	 *
	 * @var Cart_Button_Renderer|null
	 */
	private static $button_renderer = null;

	/**
	 * Shortcodes instance.
	 *
	 * @var Shortcodes|null
	 */
	private static $shortcodes = null;

	/**
	 * Boot plugin hooks.
	 */
	public static function boot() {
		// Admin: settings + product meta box.
		add_action( 'admin_init', array( Settings::class, 'register_settings' ) );
		add_action( 'admin_menu', array( Settings::class, 'register_settings_page' ) );
		add_action( 'add_meta_boxes', array( Product_Meta_Box::class, 'register_meta_boxes' ) );
		add_action( 'save_post', array( Product_Meta_Box::class, 'save_related_product' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( Product_Meta_Box::class, 'enqueue_admin_assets' ) );

		// Frontend rendering: shortcodes + cart redirect.
		self::$button_renderer = new Cart_Button_Renderer();
		self::$shortcodes      = new Shortcodes( self::$button_renderer );

		add_action( 'init', array( self::$shortcodes, 'register' ) );
		add_filter( 'woocommerce_add_to_cart_redirect', array( self::$button_renderer, 'maybe_redirect_after_form_add_to_cart' ), 20 );

		// Bricks integration.
		add_filter( 'bricks/dynamic_tags_list', array( Bricks_Integration::class, 'register_bricks_tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( Bricks_Integration::class, 'render_bricks_tag' ), 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( Bricks_Integration::class, 'render_bricks_content' ), 20, 3 );
		add_filter( 'bricks/frontend/render_data', array( Bricks_Integration::class, 'render_bricks_content' ), 20, 2 );

		self::setup_update_checker();
	}

	/**
	 * Configure GitHub release updates.
	 */
	public static function setup_update_checker() {
		if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/blissguy/Woo-CPT-Product-Link/',
			WCPL_PLUGIN_FILE,
			'woo-cpt-product-link'
		);

		$update_checker->setBranch( 'main' );
		$update_checker->getVcsApi()->enableReleaseAssets( '/woo-cpt-product-link-[0-9A-Za-z.-]+\.zip($|[?&#])/i' );
	}
}
