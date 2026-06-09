<?php
/**
 * Frontend shortcodes.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the [wcpl_*] shortcodes and delegates rendering to the renderers.
 */
final class Shortcodes {

	/**
	 * Button/cart renderer.
	 *
	 * @var Cart_Button_Renderer
	 */
	private $button_renderer;

	/**
	 * Constructor.
	 *
	 * @param Cart_Button_Renderer $button_renderer Button/cart renderer.
	 */
	public function __construct( Cart_Button_Renderer $button_renderer ) {
		$this->button_renderer = $button_renderer;
	}

	/**
	 * Register frontend shortcodes.
	 */
	public function register() {
		add_shortcode( 'wcpl_price', array( $this, 'price_shortcode' ) );
		add_shortcode( 'wcpl_selected_variation_price', array( $this, 'selected_variation_price_shortcode' ) );
		add_shortcode( 'wcpl_add_to_cart', array( $this, 'add_to_cart_shortcode' ) );
		add_shortcode( 'wcpl_buy_now', array( $this, 'buy_now_shortcode' ) );
		add_shortcode( 'wcpl_product', array( $this, 'product_shortcode' ) );
	}

	/**
	 * Render price shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function price_shortcode( $atts ) {
		$product = Product_Resolver::get_product_from_atts( $atts );

		if ( ! $product ) {
			return '';
		}

		return '<span class="wcpl-product-price">' . wp_kses_post( $product->get_price_html() ) . '</span>';
	}

	/**
	 * Render a price placeholder that updates when a variable product variation is selected.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function selected_variation_price_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'post_id'  => 0,
				'product'  => 0,
				'fallback' => 'price',
				'class'    => 'wcpl-selected-variation-price',
			),
			$atts,
			'wcpl_selected_variation_price'
		);

		$product = Product_Resolver::get_product_from_atts( $atts );

		if ( ! $product ) {
			return '';
		}

		$fallback = 'empty' === sanitize_key( $atts['fallback'] ) ? '' : $product->get_price_html();
		$classes  = array_unique( array_filter( preg_split( '/\s+/', 'wcpl-selected-variation-price ' . Util::sanitize_class_list( $atts['class'] ) ) ) );
		$class    = implode( ' ', $classes );

		$output = sprintf(
			'<span class="%1$s" data-product_id="%2$d" data-fallback-html="%3$s">%4$s</span>',
			esc_attr( $class ),
			absint( $product->get_id() ),
			esc_attr( $fallback ),
			wp_kses_post( $fallback )
		);

		return $output . $this->button_renderer->render_selected_variation_price_script();
	}

	/**
	 * Render default add-to-cart shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function add_to_cart_shortcode( $atts ) {
		$settings = Settings::get_settings();
		$atts     = shortcode_atts(
			array(
				'label'       => '',
				'target'      => $settings['default_target'],
				'quantity'    => 1,
				'class'       => 'button wcpl-add-to-cart',
				'extra_class' => '',
				'show_price'  => 'false',
				'post_id'     => 0,
				'product'     => 0,
				'product_id'  => 0,
			),
			$atts,
			'wcpl_add_to_cart'
		);

		return $this->button_renderer->render_button( $atts );
	}

	/**
	 * Render checkout shortcut shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function buy_now_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'label'       => __( 'Buy now', 'woo-cpt-product-link' ),
				'target'      => 'checkout',
				'quantity'    => 1,
				'class'       => 'button wcpl-buy-now',
				'extra_class' => '',
				'show_price'  => 'false',
				'post_id'     => 0,
				'product'     => 0,
				'product_id'  => 0,
			),
			$atts,
			'wcpl_buy_now'
		);

		return $this->button_renderer->render_button( $atts );
	}

	/**
	 * Render arbitrary product field.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public function product_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'field'   => 'title',
				'post_id' => 0,
				'product' => 0,
				'size'    => 'woocommerce_thumbnail',
			),
			$atts,
			'wcpl_product'
		);

		$product = Product_Resolver::get_product_from_atts( $atts );

		if ( ! $product ) {
			return '';
		}

		return Product_Field_Renderer::render( $product, sanitize_key( $atts['field'] ), sanitize_key( $atts['size'] ) );
	}
}
