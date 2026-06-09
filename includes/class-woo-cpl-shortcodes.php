<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOO_CPL_Shortcodes {

	public function hooks(): void {
		add_shortcode( 'woo_product_price', array( $this, 'shortcode_price' ) );
		add_shortcode( 'woo_product_regular_price', array( $this, 'shortcode_regular_price' ) );
		add_shortcode( 'woo_product_sale_price', array( $this, 'shortcode_sale_price' ) );
		add_shortcode( 'woo_add_to_cart', array( $this, 'shortcode_add_to_cart' ) );
		add_shortcode( 'woo_buy_now', array( $this, 'shortcode_buy_now' ) );

		// Intercept WC's redirect after our add-to-cart form submits.
		add_filter( 'woocommerce_add_to_cart_redirect', array( $this, 'filter_add_to_cart_redirect' ), 10, 2 );
		add_filter( 'woocommerce_add_to_cart_validation', array( $this, 'maybe_empty_cart_for_buy_now' ), 10, 6 );

		// Handle buy-now before any output.
		add_action( 'template_redirect', array( $this, 'handle_buy_now_redirect' ) );
	}

	// -------------------------------------------------------------------------
	// Price shortcodes
	// -------------------------------------------------------------------------

	public function shortcode_price( array $atts ): string {
		$product = $this->get_product_for_current_post();
		if ( ! $product ) {
			return '';
		}
		return '<span class="woo-cpl-price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
	}

	public function shortcode_regular_price( array $atts ): string {
		$product = $this->get_product_for_current_post();
		if ( ! $product ) {
			return '';
		}
		return '<span class="woo-cpl-regular-price">' . wp_kses_post( wc_price( (float) $product->get_regular_price() ) ) . '</span>';
	}

	public function shortcode_sale_price( array $atts ): string {
		$product = $this->get_product_for_current_post();
		if ( ! $product || ! $product->is_on_sale() ) {
			return '';
		}
		return '<span class="woo-cpl-sale-price">' . wp_kses_post( wc_price( (float) $product->get_sale_price() ) ) . '</span>';
	}

	// -------------------------------------------------------------------------
	// Add-to-cart
	// -------------------------------------------------------------------------

	/**
	 * [woo_add_to_cart
	 *   label="Add to Cart"
	 *   class=""
	 *   quantity="1"
	 *   redirect="cart|checkout|wc_default"   ← overrides global setting
	 *   show_qty="yes|no"                     ← overrides global setting
	 *   show_description="yes|no"             ← overrides global setting
	 * ]
	 */
	public function shortcode_add_to_cart( array $atts ): string {
		$settings = woo_cpl()->get_settings();

		$atts = shortcode_atts(
			array(
				'label'            => __( 'Add to Cart', 'woo-cpt-product-link' ),
				'class'            => '',
				'quantity'         => '1',
				// Empty string = defer to global setting.
				'redirect'         => '',
				'show_qty'         => '',
				'show_description' => '',
			),
			$atts,
			'woo_add_to_cart'
		);

		$product = $this->get_product_for_current_post();
		if ( ! $product || ! $product->is_purchasable() ) {
			return '';
		}

		// Resolve redirect: shortcode attr takes precedence over global setting.
		$valid_redirects = array( 'cart', 'checkout', 'wc_default' );
		$redirect = in_array( $atts['redirect'], $valid_redirects, true )
			? $atts['redirect']
			: $settings['after_add_to_cart'];

		// Resolve show_qty.
		$show_qty = $this->resolve_bool_attr( $atts['show_qty'], $settings['show_quantity'] === '1' );

		// Resolve show_description.
		$show_desc = $this->resolve_bool_attr( $atts['show_description'], $settings['show_short_description'] === '1' );

		if ( ! $product->is_type( 'simple' ) ) {
			return $this->render_woocommerce_add_to_cart_form(
				$product,
				$redirect,
				(string) $atts['label'],
				(string) $atts['class'],
				false,
				$show_desc
			);
		}

		$qty       = max( 1, (int) $atts['quantity'] );
		$btn_class = trim( 'button single_add_to_cart_button alt wp-element-button ' . sanitize_html_class( $atts['class'] ) );

		ob_start();
		?>
		<div class="woo-cpl-add-to-cart">
			<?php if ( $show_desc && $product->get_short_description() ) : ?>
				<div class="woo-cpl-short-description">
					<?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?>
				</div>
			<?php endif; ?>

			<form class="cart woo-cpl-cart-form" method="post" action="">
				<?php
				// The hidden field carries the redirect target so the filter below
				// knows exactly where this particular form wants to send the user.
				?>
				<input type="hidden" name="woo_cpl_atc_redirect" value="<?php echo esc_attr( $redirect ); ?>">
				<?php wp_nonce_field( 'woo_cpl_add_to_cart', 'woo_cpl_add_to_cart_nonce' ); ?>

				<?php if ( $show_qty ) : ?>
					<div class="quantity">
						<input
							type="number"
							id="quantity_<?php echo esc_attr( $product->get_id() ); ?>"
							class="input-text qty text"
							name="quantity"
							value="<?php echo esc_attr( $qty ); ?>"
							min="1"
							step="1"
						>
					</div>
				<?php else : ?>
					<input type="hidden" name="quantity" value="<?php echo esc_attr( $qty ); ?>">
				<?php endif; ?>

				<button
					type="submit"
					name="add-to-cart"
					value="<?php echo esc_attr( $product->get_id() ); ?>"
					class="<?php echo esc_attr( $btn_class ); ?>"
				>
					<?php echo esc_html( $atts['label'] ); ?>
				</button>
			</form>
		</div>
		<?php
		return ob_get_clean();
	}

	/**
	 * Intercepts WC's redirect decision when our form was submitted.
	 * The hidden woo_cpl_atc_redirect field tells us exactly where to go.
	 */
	public function filter_add_to_cart_redirect( string $url ): string {
		if ( ! isset( $_POST['woo_cpl_atc_redirect'], $_POST['woo_cpl_add_to_cart_nonce'] ) ) {
			return $url;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['woo_cpl_add_to_cart_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'woo_cpl_add_to_cart' ) ) {
			return $url;
		}

		$target = sanitize_key( wp_unslash( $_POST['woo_cpl_atc_redirect'] ) );

		if ( 'checkout' === $target ) {
			return wc_get_checkout_url();
		}

		if ( 'cart' === $target ) {
			return wc_get_cart_url();
		}

		// 'wc_default' — let WooCommerce decide based on its own setting.
		return $url;
	}

	// -------------------------------------------------------------------------
	// Buy now
	// -------------------------------------------------------------------------

	/**
	 * [woo_buy_now
	 *   label="Buy Now"
	 *   class=""
	 *   quantity="1"
	 *   redirect="cart|checkout"   ← overrides global setting
	 * ]
	 */
	public function shortcode_buy_now( array $atts ): string {
		$settings = woo_cpl()->get_settings();

		$atts = shortcode_atts(
			array(
				'label'    => __( 'Buy Now', 'woo-cpt-product-link' ),
				'class'    => '',
				'quantity' => '1',
				'redirect' => '',
			),
			$atts,
			'woo_buy_now'
		);

		$product = $this->get_product_for_current_post();
		if ( ! $product || ! $product->is_purchasable() ) {
			return '';
		}

		$valid_redirects = array( 'cart', 'checkout' );
		$redirect = in_array( $atts['redirect'], $valid_redirects, true )
			? $atts['redirect']
			: $settings['after_buy_now'];

		if ( ! $product->is_type( 'simple' ) ) {
			return $this->render_woocommerce_add_to_cart_form(
				$product,
				$redirect,
				(string) $atts['label'],
				(string) $atts['class'],
				true,
				false
			);
		}

		$qty   = max( 1, (int) $atts['quantity'] );
		$url   = $this->build_buy_now_url( $product->get_id(), $qty, $redirect );
		$class = trim( 'button alt wp-element-button woo-cpl-buy-now ' . sanitize_html_class( $atts['class'] ) );

		return sprintf(
			'<a href="%s" class="%s">%s</a>',
			esc_url( $url ),
			esc_attr( $class ),
			esc_html( $atts['label'] )
		);
	}

	/**
	 * Renders WooCommerce's native add-to-cart template for product types that
	 * require more than a simple quantity + product ID form.
	 */
	public function render_woocommerce_add_to_cart_form(
		WC_Product $product,
		string $redirect,
		string $label = '',
		string $class = '',
		bool $buy_now = false,
		bool $show_description = false
	): string {
		$redirect = in_array( $redirect, array( 'cart', 'checkout', 'wc_default' ), true ) ? $redirect : 'cart';

		if ( $product->is_type( 'variable' ) ) {
			wp_enqueue_script( 'wc-add-to-cart-variation' );
		}

		$previous_product = $GLOBALS['product'] ?? null;
		$GLOBALS['product'] = $product;

		$form_action_filter = static function () {
			return '';
		};
		$hidden_fields = static function () use ( $redirect, $buy_now ) {
			echo '<input type="hidden" name="woo_cpl_atc_redirect" value="' . esc_attr( $redirect ) . '">';
			wp_nonce_field( 'woo_cpl_add_to_cart', 'woo_cpl_add_to_cart_nonce' );
			if ( $buy_now ) {
				echo '<input type="hidden" name="woo_cpl_buy_now" value="1">';
			}
		};
		$button_text_filter = static function ( $text ) use ( $label ) {
			return '' !== $label ? $label : $text;
		};

		add_filter( 'woocommerce_add_to_cart_form_action', $form_action_filter );
		add_action( 'woocommerce_before_add_to_cart_button', $hidden_fields );
		add_filter( 'woocommerce_product_single_add_to_cart_text', $button_text_filter );

		ob_start();
		?>
		<div class="<?php echo esc_attr( trim( 'woo-cpl-add-to-cart woo-cpl-native-add-to-cart ' . sanitize_html_class( $class ) ) ); ?>">
			<?php if ( $show_description && $product->get_short_description() ) : ?>
				<div class="woo-cpl-short-description">
					<?php echo wp_kses_post( wpautop( $product->get_short_description() ) ); ?>
				</div>
			<?php endif; ?>

			<?php
			if ( $product->is_type( 'variable' ) && function_exists( 'woocommerce_variable_add_to_cart' ) ) {
				woocommerce_variable_add_to_cart();
			} elseif ( $product->is_type( 'grouped' ) && function_exists( 'woocommerce_grouped_add_to_cart' ) ) {
				woocommerce_grouped_add_to_cart();
			} elseif ( $product->is_type( 'external' ) && function_exists( 'woocommerce_external_add_to_cart' ) ) {
				woocommerce_external_add_to_cart();
			}
			?>
		</div>
		<?php
		$output = ob_get_clean();

		remove_filter( 'woocommerce_add_to_cart_form_action', $form_action_filter );
		remove_action( 'woocommerce_before_add_to_cart_button', $hidden_fields );
		remove_filter( 'woocommerce_product_single_add_to_cart_text', $button_text_filter );

		if ( null === $previous_product ) {
			unset( $GLOBALS['product'] );
		} else {
			$GLOBALS['product'] = $previous_product;
		}

		return $output;
	}

	/**
	 * Fires on template_redirect. Processes the ?woo_buy_now= action,
	 * clears the cart, adds the product, then redirects.
	 */
	public function handle_buy_now_redirect(): void {
		if ( empty( $_GET['woo_buy_now'] ) ) {
			return;
		}

		$product_id = absint( wp_unslash( $_GET['woo_buy_now'] ) );
		$qty        = isset( $_GET['woo_buy_now_qty'] ) ? max( 1, absint( wp_unslash( $_GET['woo_buy_now_qty'] ) ) ) : 1;
		$nonce      = isset( $_GET['woo_buy_now_nonce'] ) ? sanitize_text_field( wp_unslash( $_GET['woo_buy_now_nonce'] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, 'woo_buy_now_' . $product_id ) ) {
			return;
		}

		$redirect = isset( $_GET['woo_buy_now_redirect'] ) ? sanitize_key( wp_unslash( $_GET['woo_buy_now_redirect'] ) ) : 'checkout';

		$product = wc_get_product( $product_id );
		if ( ! $product || ! $product->is_purchasable() ) {
			return;
		}

		if ( ! WC()->cart ) {
			return;
		}

		WC()->cart->empty_cart();
		WC()->cart->add_to_cart( $product_id, $qty );

		$destination = ( 'cart' === $redirect ) ? wc_get_cart_url() : wc_get_checkout_url();

		wp_safe_redirect( $destination );
		exit;
	}

	/**
	 * Variable/grouped buy-now buttons use WooCommerce's normal POST flow. Empty
	 * the cart before Woo adds the selected product or variation.
	 */
	public function maybe_empty_cart_for_buy_now( $passed, $product_id = 0, $quantity = 0, $variation_id = 0, $variations = array(), $cart_item_data = array() ) {
		if ( ! $passed || ! isset( $_POST['woo_cpl_buy_now'], $_POST['woo_cpl_add_to_cart_nonce'] ) ) {
			return $passed;
		}

		$nonce = sanitize_text_field( wp_unslash( $_POST['woo_cpl_add_to_cart_nonce'] ) );
		if ( ! wp_verify_nonce( $nonce, 'woo_cpl_add_to_cart' ) ) {
			return $passed;
		}

		if ( WC()->cart ) {
			WC()->cart->empty_cart();
		}

		return $passed;
	}

	// -------------------------------------------------------------------------
	// Helpers
	// -------------------------------------------------------------------------

	private function get_product_for_current_post(): ?WC_Product {
		return woo_cpl()->get_linked_product( (int) get_the_ID() );
	}

	/**
	 * Builds the buy-now URL, encoding the target redirect inside it so each
	 * button instance can carry its own destination independently.
	 */
	public function build_buy_now_url( int $product_id, int $qty, string $redirect ): string {
		return add_query_arg(
			array(
				'woo_buy_now'          => $product_id,
				'woo_buy_now_qty'      => $qty,
				'woo_buy_now_redirect' => $redirect,
				'woo_buy_now_nonce'    => wp_create_nonce( 'woo_buy_now_' . $product_id ),
			),
			get_permalink()
		);
	}

	/**
	 * Resolves a yes/no shortcode attribute into a boolean.
	 * Empty string means "not set — use the $default".
	 */
	private function resolve_bool_attr( string $attr, bool $default ): bool {
		if ( '' === $attr ) {
			return $default;
		}
		return in_array( strtolower( $attr ), array( 'yes', '1', 'true' ), true );
	}
}
