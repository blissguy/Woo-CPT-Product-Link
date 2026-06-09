<?php
/**
 * Bricks Builder dynamic tag integration.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Exposes the related product as Bricks dynamic tags and renders them.
 */
final class Bricks_Integration {

	/**
	 * Register Bricks dynamic tags.
	 *
	 * @param array $tags Existing tags.
	 * @return array
	 */
	public static function register_bricks_tags( $tags ) {
		$group = __( 'Woo CPT Product Link', 'woo-cpt-product-link' );

		$custom_tags = array(
			array(
				'name'  => '{wcpl_product_price}',
				'label' => __( 'Related product price', 'woo-cpt-product-link' ),
				'group' => $group,
			),
			array(
				'name'  => '{wcpl_product_title}',
				'label' => __( 'Related product title', 'woo-cpt-product-link' ),
				'group' => $group,
			),
			array(
				'name'  => '{wcpl_product_url}',
				'label' => __( 'Related product URL', 'woo-cpt-product-link' ),
				'group' => $group,
			),
			array(
				'name'  => '{wcpl_product_id}',
				'label' => __( 'Related product ID', 'woo-cpt-product-link' ),
				'group' => $group,
			),
			array(
				'name'  => '{wcpl_product_sku}',
				'label' => __( 'Related product SKU', 'woo-cpt-product-link' ),
				'group' => $group,
			),
			array(
				'name'  => '{wcpl_product_stock}',
				'label' => __( 'Related product stock', 'woo-cpt-product-link' ),
				'group' => $group,
			),
			array(
				'name'  => '{wcpl_product_type}',
				'label' => __( 'Related product type', 'woo-cpt-product-link' ),
				'group' => $group,
			),
			array(
				'name'  => '{wcpl_has_product}',
				'label' => __( 'Has related product', 'woo-cpt-product-link' ),
				'group' => $group,
			),
		);

		return array_merge( $tags, $custom_tags );
	}

	/**
	 * Render one Bricks tag.
	 *
	 * @param string      $tag     Dynamic tag.
	 * @param int|WP_Post $post    Post ID or object.
	 * @param string      $context Render context.
	 * @return string
	 */
	public static function render_bricks_tag( $tag, $post, $context = 'text' ) {
		if ( ! is_string( $tag ) && ! is_numeric( $tag ) ) {
			return $tag;
		}

		$clean_tag = str_replace( array( '{', '}' ), '', (string) $tag );

		if ( 'wcpl_has_product' !== $clean_tag && 0 !== strpos( $clean_tag, 'wcpl_product_' ) ) {
			return $tag;
		}

		$post_id = $post instanceof WP_Post ? $post->ID : absint( $post );
		$product = Product_Resolver::get_product_from_atts( array( 'post_id' => $post_id ) );

		if ( 'wcpl_has_product' === $clean_tag ) {
			return $product ? 'true' : 'false';
		}

		if ( ! $product ) {
			return '';
		}

		$field_map = array(
			'wcpl_product_price' => 'price',
			'wcpl_product_title' => 'title',
			'wcpl_product_url'   => 'permalink',
			'wcpl_product_id'    => 'id',
			'wcpl_product_sku'   => 'sku',
			'wcpl_product_stock' => 'availability',
			'wcpl_product_type'  => 'type',
		);

		if ( ! isset( $field_map[ $clean_tag ] ) ) {
			return '';
		}

		return Product_Field_Renderer::render( $product, $field_map[ $clean_tag ] );
	}

	/**
	 * Replace Bricks tags in content.
	 *
	 * @param string      $content Content.
	 * @param int|WP_Post $post    Post ID or object.
	 * @param string      $context Render context.
	 * @return string
	 */
	public static function render_bricks_content( $content, $post, $context = 'text' ) {
		if ( ! is_string( $content ) || false === strpos( $content, '{wcpl_' ) ) {
			return $content;
		}

		return preg_replace_callback(
			'/{(wcpl_(?:has_product|product_[a-z_]+))}/',
			function ( $matches ) use ( $post, $context ) {
				return self::render_bricks_tag( $matches[0], $post, $context );
			},
			$content
		);
	}
}
