<?php
/**
 * Resolves the WooCommerce product linked to a CPT entry.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

defined( 'ABSPATH' ) || exit;

/**
 * Translates shortcode attributes or a post ID into a WC_Product.
 */
final class Product_Resolver {

	const META_PRODUCT_ID = '_wcpl_product_id';

	/**
	 * Get product from shortcode attributes or current post.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return \WC_Product|false
	 */
	public static function get_product_from_atts( $atts ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return false;
		}

		$product_id = 0;

		if ( ! empty( $atts['product'] ) ) {
			$product_id = absint( $atts['product'] );
		} elseif ( ! empty( $atts['product_id'] ) ) {
			$product_id = absint( $atts['product_id'] );
		}

		if ( ! $product_id ) {
			$post_id    = ! empty( $atts['post_id'] ) ? absint( $atts['post_id'] ) : get_the_ID();
			$product_id = self::get_related_product_id( $post_id );
		}

		return $product_id ? wc_get_product( $product_id ) : false;
	}

	/**
	 * Get stored product ID.
	 *
	 * @param int $post_id CPT post ID.
	 * @return int
	 */
	public static function get_related_product_id( $post_id = 0 ) {
		$post_id = $post_id ? absint( $post_id ) : get_the_ID();

		if ( ! $post_id ) {
			return 0;
		}

		return absint( get_post_meta( $post_id, self::META_PRODUCT_ID, true ) );
	}
}
