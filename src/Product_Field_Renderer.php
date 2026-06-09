<?php
/**
 * Renders individual WooCommerce product fields as escaped HTML.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

defined( 'ABSPATH' ) || exit;

/**
 * Pure formatter: maps a field key to its escaped product value.
 */
final class Product_Field_Renderer {

	/**
	 * Render one product field.
	 *
	 * @param \WC_Product $product Product.
	 * @param string      $field   Field key.
	 * @param string      $size    Image size.
	 * @return string
	 */
	public static function render( $product, $field, $size = 'woocommerce_thumbnail' ) {
		switch ( $field ) {
			case 'id':
				return (string) $product->get_id();
			case 'title':
			case 'name':
				return esc_html( $product->get_name() );
			case 'sku':
				return esc_html( $product->get_sku() );
			case 'type':
				return esc_html( $product->get_type() );
			case 'price':
				return wp_kses_post( $product->get_price_html() );
			case 'regular_price':
				return '' !== $product->get_regular_price() ? wp_kses_post( wc_price( $product->get_regular_price() ) ) : '';
			case 'sale_price':
				return $product->get_sale_price() ? wp_kses_post( wc_price( $product->get_sale_price() ) ) : '';
			case 'short_description':
				return wp_kses_post( apply_filters( 'woocommerce_short_description', $product->get_short_description() ) );
			case 'description':
				return wp_kses_post( apply_filters( 'the_content', $product->get_description() ) );
			case 'stock_status':
				return esc_html( $product->get_stock_status() );
			case 'availability':
				$availability = $product->get_availability();
				return isset( $availability['availability'] ) ? esc_html( $availability['availability'] ) : '';
			case 'image':
				return $product->get_image( $size );
			case 'permalink':
			case 'url':
				return esc_url( $product->get_permalink() );
			default:
				return '';
		}
	}
}
