<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Registers dynamic data tags for Bricks Builder and whitelists all woo_cpl_*
 * functions for use inside Bricks Code elements.
 */
class WOO_CPL_Bricks {

	/**
	 * Tag name (no braces) => human label.
	 */
	private const TAGS = array(
		'woo_product_is_linked'        => 'Linked Product: Is Linked',
		'woo_product_type'             => 'Linked Product: Type',
		'woo_product_price'             => 'Linked Product: Price',
		'woo_product_regular_price'     => 'Linked Product: Regular Price',
		'woo_product_sale_price'        => 'Linked Product: Sale Price',
		'woo_product_name'              => 'Linked Product: Name',
		'woo_product_sku'               => 'Linked Product: SKU',
		'woo_product_id'                => 'Linked Product: ID',
		'woo_product_stock_status'      => 'Linked Product: Stock Status',
		'woo_product_short_description' => 'Linked Product: Short Description',
		'woo_add_to_cart'               => 'Linked Product: Add to Cart Button',
		'woo_buy_now'                   => 'Linked Product: Buy Now Button',
	);

	/**
	 * Explicit function names exposed for Bricks Code elements.
	 * Any function matching @^woo_cpl_ is also allowed via the pattern below.
	 */
	private const ALLOWED_FUNCTIONS = array(
		'woo_cpl_get_price',
		'woo_cpl_get_regular_price',
		'woo_cpl_get_sale_price',
		'woo_cpl_get_product_name',
		'woo_cpl_get_product_sku',
		'woo_cpl_get_product_id',
		'woo_cpl_has_linked_product',
		'woo_cpl_get_product_type',
		'woo_cpl_get_stock_status',
		'woo_cpl_get_short_description',
	);

	/**
	 * Regex patterns Bricks uses to allow whole function namespaces.
	 * The leading @ signals to Bricks that this is a pattern, not a literal name.
	 */
	private const ALLOWED_PATTERNS = array( '@^woo_cpl_' );

	public function hooks(): void {
		if ( ! defined( 'BRICKS_VERSION' ) ) {
			return;
		}

		add_filter( 'bricks/dynamic_tags_list', array( $this, 'register_tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( $this, 'render_tag' ), 10, 3 );

		// 2nd arg = the specific function name being tested (Bricks passes it since ~1.9).
		add_filter( 'bricks/code/echo_function_names', array( $this, 'allow_echo_functions' ), 9999, 2 );
	}

	// -------------------------------------------------------------------------
	// Dynamic tags
	// -------------------------------------------------------------------------

	public function register_tags( array $tags ): array {
		foreach ( self::TAGS as $name => $label ) {
			$tags[] = array(
				'name'  => '{' . $name . '}',
				'label' => $label,
				'group' => 'Woo Product Link',
			);
		}
		return $tags;
	}

	public function render_tag( $tag, $post, string $context = 'text' ) {
		if ( ! is_string( $tag ) || ! ( $post instanceof WP_Post ) ) {
			return $tag;
		}

		$key = trim( $tag, '{}' );

		if ( ! array_key_exists( $key, self::TAGS ) ) {
			return $tag;
		}

		$product = woo_cpl()->get_linked_product( $post->ID );

		if ( 'woo_product_is_linked' === $key ) {
			return $product ? 'true' : 'false';
		}

		if ( ! $product ) {
			return '';
		}

		switch ( $key ) {
			case 'woo_product_type':
				return esc_html( $product->get_type() );

			case 'woo_product_price':
				return wp_kses_post( $product->get_price_html() );

			case 'woo_product_regular_price':
				return wp_kses_post( wc_price( (float) $product->get_regular_price() ) );

			case 'woo_product_sale_price':
				return $product->is_on_sale()
					? wp_kses_post( wc_price( (float) $product->get_sale_price() ) )
					: '';

			case 'woo_product_name':
				return esc_html( $product->get_name() );

			case 'woo_product_sku':
				return esc_html( $product->get_sku() );

			case 'woo_product_id':
				return (string) $product->get_id();

			case 'woo_product_stock_status':
				return esc_html( $this->format_stock_status( $product->get_stock_status() ) );

			case 'woo_product_short_description':
				return wp_kses_post( wpautop( $product->get_short_description() ) );

			case 'woo_add_to_cart':
				return $this->render_add_to_cart_button( $product );

			case 'woo_buy_now':
				return $this->render_buy_now_button( $product, $post->ID );
		}

		return '';
	}

	// -------------------------------------------------------------------------
	// Function whitelist for Bricks Code elements
	// -------------------------------------------------------------------------

	/**
	 * Properly whitelists the woo_cpl_* namespace in Bricks' code-execution sandbox.
	 *
	 * Bricks passes $allowed in three shapes:
	 *   true         → everything is already allowed; return true unchanged.
	 *   array        → merge our names + patterns into the list.
	 *   string       → a single function name is being tested; return true if ours.
	 *
	 * The second parameter $function_name (added in Bricks ~1.9) gives the name
	 * being tested regardless of the $allowed shape.
	 *
	 * @param true|string[]|string $allowed
	 * @param string|null          $function_name
	 * @return true|string[]|string
	 */
	public function allow_echo_functions( $allowed, ?string $function_name = null ) {
		// Everything is already permitted — nothing to add.
		if ( true === $allowed ) {
			return true;
		}

		// Bricks is checking a specific function name directly.
		if ( is_string( $function_name ) && $this->is_woo_cpl_function( $function_name ) ) {
			return true;
		}

		// Bricks is accumulating an allowlist array — add our names and pattern.
		if ( is_array( $allowed ) ) {
			return array_values(
				array_unique( array_merge( $allowed, self::ALLOWED_FUNCTIONS, self::ALLOWED_PATTERNS ) )
			);
		}

		// Bricks is testing a single name passed as the first arg (older Bricks).
		if ( is_string( $allowed ) && $this->is_woo_cpl_function( $allowed ) ) {
			return true;
		}

		return $allowed;
	}

	// -------------------------------------------------------------------------
	// Button renderers (used by dynamic tags — respect global settings)
	// -------------------------------------------------------------------------

	private function render_add_to_cart_button( WC_Product $product ): string {
		if ( ! $product->is_purchasable() ) {
			return '';
		}

		$settings  = woo_cpl()->get_settings();
		$redirect  = $settings['after_add_to_cart'];
		$show_qty  = $settings['show_quantity'] === '1';
		$show_desc = $settings['show_short_description'] === '1';

		if ( ! $product->is_type( 'simple' ) ) {
			return $this->render_native_add_to_cart_form(
				$product,
				$redirect,
				__( 'Add to Cart', 'woo-cpt-product-link' ),
				false,
				$show_desc
			);
		}

		$short_desc = '';
		if ( $show_desc && $product->get_short_description() ) {
			$short_desc = '<div class="woo-cpl-short-description">'
				. wp_kses_post( wpautop( $product->get_short_description() ) )
				. '</div>';
		}

		$qty_html = $show_qty
			? sprintf(
				'<div class="quantity"><input type="number" class="input-text qty text" name="quantity" value="1" min="1" step="1"></div>',
			)
			: '<input type="hidden" name="quantity" value="1">';

		return sprintf(
			'<div class="woo-cpl-add-to-cart">%s<form class="cart woo-cpl-cart-form" method="post" action="">
				<input type="hidden" name="woo_cpl_atc_redirect" value="%s">
				%s
				%s
				<button type="submit" name="add-to-cart" value="%d" class="button single_add_to_cart_button alt wp-element-button">%s</button>
			</form></div>',
			$short_desc,
			esc_attr( $redirect ),
			wp_nonce_field( 'woo_cpl_add_to_cart', 'woo_cpl_add_to_cart_nonce', true, false ),
			$qty_html,
			$product->get_id(),
			esc_html__( 'Add to Cart', 'woo-cpt-product-link' )
		);
	}

	private function render_buy_now_button( WC_Product $product, int $post_id ): string {
		if ( ! $product->is_purchasable() ) {
			return '';
		}

		$redirect = woo_cpl()->get_setting( 'after_buy_now', 'checkout' );

		if ( ! $product->is_type( 'simple' ) ) {
			return $this->render_native_add_to_cart_form(
				$product,
				$redirect,
				__( 'Buy Now', 'woo-cpt-product-link' ),
				true,
				false
			);
		}

		// Re-use the shortcodes class helper so the URL structure stays in one place.
		require_once WOO_CPL_DIR . 'includes/class-woo-cpl-shortcodes.php';
		$shortcodes = new WOO_CPL_Shortcodes();
		$url = $shortcodes->build_buy_now_url( $product->get_id(), 1, $redirect );

		return sprintf(
			'<a href="%s" class="button alt wp-element-button woo-cpl-buy-now">%s</a>',
			esc_url( $url ),
			esc_html__( 'Buy Now', 'woo-cpt-product-link' )
		);
	}

	private function render_native_add_to_cart_form( WC_Product $product, string $redirect, string $label, bool $buy_now, bool $show_description ): string {
		require_once WOO_CPL_DIR . 'includes/class-woo-cpl-shortcodes.php';

		$shortcodes = new WOO_CPL_Shortcodes();
		return $shortcodes->render_woocommerce_add_to_cart_form(
			$product,
			$redirect,
			$label,
			'',
			$buy_now,
			$show_description
		);
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function is_woo_cpl_function( string $name ): bool {
		return 0 === strpos( $name, 'woo_cpl_' );
	}

	private function format_stock_status( string $status ): string {
		$map = array(
			'instock'     => __( 'In stock', 'woo-cpt-product-link' ),
			'outofstock'  => __( 'Out of stock', 'woo-cpt-product-link' ),
			'onbackorder' => __( 'On backorder', 'woo-cpt-product-link' ),
		);
		return $map[ $status ] ?? ucfirst( str_replace( '_', ' ', $status ) );
	}
}
