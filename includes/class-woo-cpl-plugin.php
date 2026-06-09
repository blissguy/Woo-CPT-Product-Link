<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class WOO_CPL_Plugin {

	const META_KEY          = '_woo_linked_product_id';
	const LEGACY_META_KEY   = '_mbm_linked_product_id';
	const OPTION_KEY        = 'woo_cpl_settings';
	const LEGACY_OPTION_KEY = 'mbm_cpl_settings';

	private static $instance = null;

	/** @var array<string,mixed>|null */
	private ?array $settings_cache = null;

	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	private function __construct() {}

	public static function activate(): void {
		$settings = get_option( self::OPTION_KEY, null );
		$legacy   = get_option( self::LEGACY_OPTION_KEY, null );

		if ( null === $settings && is_array( $legacy ) ) {
			update_option( self::OPTION_KEY, $legacy );
		}
	}

	public function init(): void {
		require_once WOO_CPL_DIR . 'includes/class-woo-cpl-settings.php';
		require_once WOO_CPL_DIR . 'includes/class-woo-cpl-meta-box.php';
		require_once WOO_CPL_DIR . 'includes/class-woo-cpl-shortcodes.php';
		require_once WOO_CPL_DIR . 'includes/class-woo-cpl-bricks.php';

		( new WOO_CPL_Settings() )->hooks();
		( new WOO_CPL_Meta_Box() )->hooks();
		( new WOO_CPL_Shortcodes() )->hooks();
		( new WOO_CPL_Bricks() )->hooks();
	}

	// -------------------------------------------------------------------------
	// Settings
	// -------------------------------------------------------------------------

	/**
	 * Returns all plugin settings, merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public function get_settings(): array {
		if ( null === $this->settings_cache ) {
			$saved = get_option( self::OPTION_KEY, null );
			if ( ! is_array( $saved ) ) {
				$legacy = get_option( self::LEGACY_OPTION_KEY, array() );
				$saved  = is_array( $legacy ) ? $legacy : array();
			}

			$this->settings_cache  = array_merge( $this->default_settings(), $saved );
		}
		return $this->settings_cache;
	}

	/**
	 * Returns a single setting value, falling back to the default if not set.
	 *
	 * @param string $key     Setting key.
	 * @param mixed  $default Override default (uses built-in default when omitted).
	 * @return mixed
	 */
	public function get_setting( string $key, $default = null ) {
		$settings = $this->get_settings();
		if ( array_key_exists( $key, $settings ) ) {
			return $settings[ $key ];
		}
		$defaults = $this->default_settings();
		if ( null !== $default ) {
			return $default;
		}
		return $defaults[ $key ] ?? null;
	}

	/** Clears the in-memory cache after a save. */
	public function flush_settings_cache(): void {
		$this->settings_cache = null;
	}

	/** @return array<string,mixed> */
	public function default_settings(): array {
		return array(
			'enabled_post_types'     => array(),
			// Where to send the user after [woo_add_to_cart] submits.
			// 'wc_default' = respect WooCommerce's own "redirect to cart" setting
			// 'cart'       = always go to the cart page
			// 'checkout'   = always go to checkout
			'after_add_to_cart'      => 'cart',
			// Where to send the user after [woo_buy_now].
			// 'cart'     = go to cart (keep other items)
			// 'checkout' = go straight to checkout (cart is replaced)
			'after_buy_now'          => 'checkout',
			// Show a quantity <input> in the add-to-cart form. '0' = hidden (qty=1).
			'show_quantity'          => '0',
			// Show the linked product's short description above the add-to-cart button.
			'show_short_description' => '0',
		);
	}

	// -------------------------------------------------------------------------
	// Product helpers
	// -------------------------------------------------------------------------

	/**
	 * Returns the WooCommerce product linked to a post, or null if none.
	 */
	public function get_linked_product( int $post_id = 0 ): ?WC_Product {
		$id = $this->get_linked_product_id( $post_id );
		if ( ! $id ) {
			return null;
		}
		$product = wc_get_product( $id );
		return ( $product instanceof WC_Product ) ? $product : null;
	}

	/**
	 * Returns the linked product ID stored on a post, or 0 if none.
	 */
	public function get_linked_product_id( int $post_id = 0 ): int {
		if ( ! $post_id ) {
			$post_id = (int) get_the_ID();
		}

		$product_id = (int) get_post_meta( $post_id, self::META_KEY, true );
		if ( $product_id ) {
			return $product_id;
		}

		return (int) get_post_meta( $post_id, self::LEGACY_META_KEY, true );
	}

	/**
	 * Returns the list of post type slugs that have the product selector enabled.
	 */
	public function get_enabled_post_types(): array {
		return (array) $this->get_setting( 'enabled_post_types', array() );
	}

	public function is_enabled_for( string $post_type ): bool {
		return in_array( $post_type, $this->get_enabled_post_types(), true );
	}
}

/**
 * Global helper — returns the plugin instance.
 */
function woo_cpl(): WOO_CPL_Plugin {
	return WOO_CPL_Plugin::instance();
}

// -------------------------------------------------------------------------
// Global template functions — safe to call from Bricks Code elements.
// All are prefixed woo_cpl_ and whitelisted in WOO_CPL_Bricks.
// -------------------------------------------------------------------------

function woo_cpl_get_price( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	return $product ? wp_kses_post( $product->get_price_html() ) : '';
}

function woo_cpl_get_regular_price( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	return $product ? wp_kses_post( wc_price( (float) $product->get_regular_price() ) ) : '';
}

function woo_cpl_get_sale_price( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	return ( $product && $product->is_on_sale() )
		? wp_kses_post( wc_price( (float) $product->get_sale_price() ) )
		: '';
}

function woo_cpl_get_product_name( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	return $product ? esc_html( $product->get_name() ) : '';
}

function woo_cpl_get_product_sku( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	return $product ? esc_html( $product->get_sku() ) : '';
}

function woo_cpl_get_product_id( int $post_id = 0 ): int {
	return woo_cpl()->get_linked_product_id( $post_id );
}

function woo_cpl_has_linked_product( int $post_id = 0 ): bool {
	return (bool) woo_cpl()->get_linked_product( $post_id );
}

function woo_cpl_get_product_type( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	return $product ? esc_html( $product->get_type() ) : '';
}

function woo_cpl_get_stock_status( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	if ( ! $product ) {
		return '';
	}
	$map = array(
		'instock'     => __( 'In stock', 'woo-cpt-product-link' ),
		'outofstock'  => __( 'Out of stock', 'woo-cpt-product-link' ),
		'onbackorder' => __( 'On backorder', 'woo-cpt-product-link' ),
	);
	$status = $product->get_stock_status();
	return esc_html( $map[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) ) );
}

function woo_cpl_get_short_description( int $post_id = 0 ): string {
	$product = woo_cpl()->get_linked_product( $post_id );
	return $product ? wp_kses_post( wpautop( $product->get_short_description() ) ) : '';
}
