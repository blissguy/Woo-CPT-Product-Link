<?php
/**
 * Admin meta box for linking a CPT entry to a WooCommerce product.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Registers, renders, and persists the product relationship meta box.
 */
final class Product_Meta_Box {

	const NONCE_ACTION = 'wcpl_save_product';
	const NONCE_NAME   = 'wcpl_nonce';

	/**
	 * Register the product relationship meta box.
	 */
	public static function register_meta_boxes() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		foreach ( Settings::enabled_post_types() as $post_type ) {
			add_meta_box(
				'wcpl-product',
				__( 'Related WooCommerce Product', 'woo-cpt-product-link' ),
				array( __CLASS__, 'render_product_meta_box' ),
				$post_type,
				'side',
				'default'
			);
		}
	}

	/**
	 * Render product selector.
	 *
	 * @param WP_Post $post Current post.
	 */
	public static function render_product_meta_box( $post ) {
		wp_nonce_field( self::NONCE_ACTION, self::NONCE_NAME );

		$product_id = Product_Resolver::get_related_product_id( $post->ID );
		$product    = $product_id && function_exists( 'wc_get_product' ) ? wc_get_product( $product_id ) : false;
		?>
		<p><?php esc_html_e( 'Select the WooCommerce product this content should sell.', 'woo-cpt-product-link' ); ?></p>
		<select
			id="wcpl-product-id"
			name="wcpl_product_id"
			class="wc-product-search"
			style="width:100%;"
			data-placeholder="<?php esc_attr_e( 'Search for a product...', 'woo-cpt-product-link' ); ?>"
			data-action="woocommerce_json_search_products_and_variations"
			data-allow_clear="true"
		>
			<?php if ( $product ) : ?>
				<option value="<?php echo esc_attr( $product_id ); ?>" selected="selected"><?php echo esc_html( wp_strip_all_tags( $product->get_formatted_name() ) ); ?></option>
			<?php endif; ?>
		</select>
		<?php if ( $product ) : ?>
			<p>
				<a href="<?php echo esc_url( get_edit_post_link( $product_id ) ); ?>">
					<?php esc_html_e( 'Edit product', 'woo-cpt-product-link' ); ?>
				</a>
			</p>
		<?php endif; ?>
		<?php
	}

	/**
	 * Save product relationship.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post object.
	 */
	public static function save_related_product( $post_id, $post ) {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, Settings::enabled_post_types(), true ) ) {
			return;
		}

		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		$nonce = isset( $_POST[ self::NONCE_NAME ] ) ? sanitize_text_field( wp_unslash( $_POST[ self::NONCE_NAME ] ) ) : '';

		if ( ! wp_verify_nonce( $nonce, self::NONCE_ACTION ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		$product_id = isset( $_POST['wcpl_product_id'] ) ? absint( $_POST['wcpl_product_id'] ) : 0;
		$product    = $product_id ? wc_get_product( $product_id ) : false;

		if ( $product ) {
			update_post_meta( $post_id, Product_Resolver::META_PRODUCT_ID, $product_id );
		} else {
			delete_post_meta( $post_id, Product_Resolver::META_PRODUCT_ID );
		}
	}

	/**
	 * Enqueue WooCommerce product search assets on enabled CPT edit screens.
	 *
	 * @param string $hook Current admin hook.
	 */
	public static function enqueue_admin_assets( $hook ) {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) || ! function_exists( 'get_current_screen' ) ) {
			return;
		}

		$screen = get_current_screen();

		if ( ! $screen || ! in_array( $screen->post_type, Settings::enabled_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
	}
}
