<?php
/**
 * Renders add-to-cart / buy-now controls and the WooCommerce add-to-cart form.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

defined( 'ABSPATH' ) || exit;

/**
 * Owns cart/checkout button rendering plus the transient state needed while a
 * native WooCommerce variable/grouped form is being rendered.
 */
final class Cart_Button_Renderer {

	/**
	 * Redirect target while rendering a WooCommerce form.
	 *
	 * @var string
	 */
	private $form_redirect_target = 'cart';

	/**
	 * Custom button label while rendering a WooCommerce form.
	 *
	 * @var string
	 */
	private $form_button_label = '';

	/**
	 * Whether the selected variation price script has already been printed.
	 *
	 * @var bool
	 */
	private $selected_variation_price_script_printed = false;

	/**
	 * Render an add-to-cart or checkout button.
	 *
	 * @param array $atts Parsed attributes.
	 * @return string
	 */
	public function render_button( $atts ) {
		$product = Product_Resolver::get_product_from_atts( $atts );

		if ( ! $product ) {
			return '';
		}

		$quantity    = max( 1, absint( $atts['quantity'] ) );
		$target      = in_array( $atts['target'], array( 'cart', 'checkout' ), true ) ? $atts['target'] : 'cart';
		$label       = $atts['label'] ? sanitize_text_field( $atts['label'] ) : $product->add_to_cart_text();
		$extra_class = Util::sanitize_class_list( isset( $atts['extra_class'] ) ? $atts['extra_class'] : '' );
		$class       = Util::sanitize_class_list( $atts['class'] . ' ' . $extra_class );
		$url         = $this->get_purchase_url( $product, $target, $quantity );

		if ( ! $url && $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return $this->render_add_to_cart_form( $product, $target, $label, $extra_class );
		}

		if ( ! $url ) {
			return '';
		}

		$aria_label = sprintf(
			/* translators: %s is product name. */
			__( 'Add %s to cart', 'woo-cpt-product-link' ),
			$product->get_name()
		);

		$button = sprintf(
			'<a href="%1$s" class="%2$s" data-product_id="%3$d" data-quantity="%4$d" aria-label="%5$s" rel="nofollow">%6$s</a>',
			esc_url( $url ),
			esc_attr( $class ),
			absint( $product->get_id() ),
			$quantity,
			esc_attr( $aria_label ),
			esc_html( $label )
		);

		if ( 'true' === strtolower( (string) $atts['show_price'] ) ) {
			$button = '<span class="wcpl-button-price">' . wp_kses_post( $product->get_price_html() ) . '</span> ' . $button;
		}

		return '<span class="wcpl-button-wrap">' . $button . '</span>';
	}

	/**
	 * Get a purchase URL for the referenced product.
	 *
	 * @param \WC_Product $product  WooCommerce product.
	 * @param string      $target   cart or checkout.
	 * @param int         $quantity Quantity.
	 * @return string
	 */
	private function get_purchase_url( $product, $target, $quantity ) {
		if ( ! $product->is_purchasable() || ! $product->is_in_stock() ) {
			return $product->get_permalink();
		}

		if ( $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return '';
		}

		if ( $product->is_type( 'external' ) ) {
			return $product->get_permalink();
		}

		$base_url = 'checkout' === $target ? wc_get_checkout_url() : wc_get_cart_url();

		return add_query_arg(
			array(
				'add-to-cart' => $product->get_id(),
				'quantity'    => $quantity,
			),
			$base_url
		);
	}

	/**
	 * Render WooCommerce's native variable/grouped add-to-cart form.
	 *
	 * @param \WC_Product $wc_product  Product.
	 * @param string      $target      cart or checkout.
	 * @param string      $label       Button label.
	 * @param string      $extra_class Sanitized custom classes for the form wrapper.
	 * @return string
	 */
	private function render_add_to_cart_form( $wc_product, $target, $label, $extra_class = '' ) {
		if ( ! function_exists( 'woocommerce_template_single_add_to_cart' ) ) {
			return '';
		}

		global $post, $product;

		$previous_post    = $post;
		$previous_product = $product;
		$product_post     = get_post( $wc_product->get_id() );

		$post    = $product_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$product = $wc_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		$this->form_redirect_target = $target;
		$this->form_button_label    = $label;

		add_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_form_redirect_field' ) );
		add_filter( 'woocommerce_add_to_cart_form_action', array( $this, 'get_current_form_action' ) );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'get_form_button_label' ) );
		add_filter( 'woocommerce_available_variation', array( $this, 'filter_available_variation_output' ) );

		ob_start();
		printf(
			'<div class="wcpl-add-to-cart-form %s">',
			esc_attr( trim( $this->get_form_output_classes() . ' ' . $extra_class ) )
		);
		$this->render_form_output_styles();
		woocommerce_template_single_add_to_cart();
		echo '</div>';
		$output = ob_get_clean();

		remove_filter( 'woocommerce_available_variation', array( $this, 'filter_available_variation_output' ) );
		remove_filter( 'woocommerce_product_single_add_to_cart_text', array( $this, 'get_form_button_label' ) );
		remove_filter( 'woocommerce_add_to_cart_form_action', array( $this, 'get_current_form_action' ) );
		remove_action( 'woocommerce_after_add_to_cart_button', array( $this, 'render_form_redirect_field' ) );

		$post    = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$product = $previous_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		return $output;
	}

	/**
	 * Remove selected variation payload fields before WooCommerce prints them into the form.
	 *
	 * @param array $variation Variation data.
	 * @return array
	 */
	public function filter_available_variation_output( $variation ) {
		$outputs = Settings::get_settings()['form_outputs'];

		if ( empty( $outputs['variation_description'] ) ) {
			$variation['variation_description'] = '';
		}

		if ( empty( $outputs['variation_availability'] ) ) {
			$variation['availability_html'] = '';
		}

		return $variation;
	}

	/**
	 * Build wrapper classes for hidden form output settings.
	 *
	 * @return string
	 */
	private function get_form_output_classes() {
		$outputs = Settings::get_settings()['form_outputs'];
		$classes = array();

		foreach ( Settings::form_output_options() as $key => $label ) {
			if ( empty( $outputs[ $key ] ) ) {
				$classes[] = 'wcpl-hide-' . str_replace( '_', '-', $key );
			}
		}

		return implode( ' ', $classes );
	}

	/**
	 * Render scoped CSS for static WooCommerce form pieces.
	 */
	private function render_form_output_styles() {
		?>
		<style>
			.wcpl-hide-variation-description .woocommerce-variation-description,
			.wcpl-hide-variation-price .woocommerce-variation-price,
			.wcpl-hide-variation-availability .woocommerce-variation-availability,
			.wcpl-hide-quantity .quantity,
			.wcpl-hide-reset-link .reset_variations {
				display: none !important;
			}
		</style>
		<?php
	}

	/**
	 * Render the selected variation price updater once per page.
	 *
	 * @return string
	 */
	public function render_selected_variation_price_script() {
		if ( $this->selected_variation_price_script_printed ) {
			return '';
		}

		$this->selected_variation_price_script_printed = true;

		return '<script>
(function () {
	function ready(callback) {
		if (document.readyState !== "loading") {
			callback();
			return;
		}
		document.addEventListener("DOMContentLoaded", callback);
	}

	ready(function () {
		if (!window.jQuery) {
			return;
		}

		var $ = window.jQuery;

		function getFormProductId($form) {
			var productId = parseInt($form.find("input[name=\"product_id\"], input[name=\"add-to-cart\"]").first().val(), 10);
			return isNaN(productId) ? 0 : productId;
		}

		function getTargets(productId) {
			return $(".wcpl-selected-variation-price").filter(function () {
				var targetId = parseInt($(this).attr("data-product_id"), 10);
				return !productId || !targetId || targetId === productId;
			});
		}

		$(document).on("found_variation", ".variations_form", function (event, variation) {
			var productId = getFormProductId($(this));
			var html = variation && variation.price_html ? variation.price_html : "";

			getTargets(productId).each(function () {
				$(this).html(html || $(this).attr("data-fallback-html") || "");
			});
		});

		$(document).on("reset_data hide_variation", ".variations_form", function () {
			var productId = getFormProductId($(this));

			getTargets(productId).each(function () {
				$(this).html($(this).attr("data-fallback-html") || "");
			});
		});
	});
})();
</script>';
	}

	/**
	 * Hidden redirect target for rendered WooCommerce forms.
	 */
	public function render_form_redirect_field() {
		printf(
			'<input type="hidden" name="wcpl_redirect" value="%s" />',
			esc_attr( $this->form_redirect_target )
		);
	}

	/**
	 * Keep variable/grouped add-to-cart forms on the current CPT URL.
	 *
	 * @return string
	 */
	public function get_current_form_action() {
		global $wp;

		return home_url( add_query_arg( array(), $wp->request ) );
	}

	/**
	 * Override the form button text when a shortcode label is supplied.
	 *
	 * @param string $label Default label.
	 * @return string
	 */
	public function get_form_button_label( $label ) {
		return $this->form_button_label ? $this->form_button_label : $label;
	}

	/**
	 * Redirect CPT-hosted WooCommerce forms after add to cart.
	 *
	 * @param string $url Existing redirect URL.
	 * @return string
	 */
	public function maybe_redirect_after_form_add_to_cart( $url ) {
		$target = isset( $_REQUEST['wcpl_redirect'] ) ? sanitize_key( wp_unslash( $_REQUEST['wcpl_redirect'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'checkout' === $target ) {
			return wc_get_checkout_url();
		}

		if ( 'cart' === $target ) {
			return wc_get_cart_url();
		}

		return $url;
	}
}
