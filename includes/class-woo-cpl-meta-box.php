<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOO_CPL_Meta_Box {

	public function hooks(): void {
		add_action( 'add_meta_boxes', array( $this, 'register' ) );
		add_action( 'save_post', array( $this, 'save' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_assets' ) );

		// AJAX: search products.
		add_action( 'wp_ajax_woo_cpl_search_products', array( $this, 'ajax_search_products' ) );
	}

	public function register(): void {
		foreach ( woo_cpl()->get_enabled_post_types() as $post_type ) {
			add_meta_box(
				'woo-cpl-product-link',
				'Linked WooCommerce Product',
				array( $this, 'render' ),
				$post_type,
				'side',
				'high'
			);
		}
	}

	public function render( WP_Post $post ): void {
		$product_id = woo_cpl()->get_linked_product_id( $post->ID );
		$product    = $product_id ? wc_get_product( $product_id ) : null;

		wp_nonce_field( 'woo_cpl_save_' . $post->ID, 'woo_cpl_nonce' );
		?>
		<div class="woo-cpl-meta-box">
			<select
				id="woo-cpl-product-select"
				name="<?php echo esc_attr( WOO_CPL_Plugin::META_KEY ); ?>"
				style="width:100%"
				data-placeholder="Search for a product&hellip;"
			>
				<option value=""></option>
				<?php if ( $product ) : ?>
					<option value="<?php echo esc_attr( $product_id ); ?>" selected>
						<?php echo esc_html( $product->get_name() ); ?> (#<?php echo esc_html( $product_id ); ?>)
					</option>
				<?php endif; ?>
			</select>

			<?php if ( $product ) : ?>
				<p class="woo-cpl-current" style="margin-top:8px;font-size:12px;color:#555">
					Price: <?php echo wp_kses_post( $product->get_price_html() ); ?><br>
					Status: <?php echo esc_html( $product->get_stock_status() ); ?>
				</p>
			<?php endif; ?>
		</div>
		<?php
	}

	public function save( int $post_id, WP_Post $post ): void {
		if ( wp_is_post_autosave( $post_id ) || wp_is_post_revision( $post_id ) ) {
			return;
		}

		if ( ! isset( $_POST['woo_cpl_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['woo_cpl_nonce'] ) ), 'woo_cpl_save_' . $post_id ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}

		if ( ! woo_cpl()->is_enabled_for( $post->post_type ) ) {
			return;
		}

		$product_id = isset( $_POST[ WOO_CPL_Plugin::META_KEY ] ) ? absint( $_POST[ WOO_CPL_Plugin::META_KEY ] ) : 0;

		if ( $product_id && wc_get_product( $product_id ) ) {
			update_post_meta( $post_id, WOO_CPL_Plugin::META_KEY, $product_id );
			delete_post_meta( $post_id, WOO_CPL_Plugin::LEGACY_META_KEY );
		} else {
			delete_post_meta( $post_id, WOO_CPL_Plugin::META_KEY );
			delete_post_meta( $post_id, WOO_CPL_Plugin::LEGACY_META_KEY );
		}
	}

	public function enqueue_assets( string $hook ): void {
		if ( ! in_array( $hook, array( 'post.php', 'post-new.php' ), true ) ) {
			return;
		}

		$screen = get_current_screen();
		if ( ! $screen || ! woo_cpl()->is_enabled_for( $screen->post_type ) ) {
			return;
		}

		// Re-use select2 that ships with WooCommerce.
		wp_enqueue_script(
			'woo-cpl-select2',
			WC()->plugin_url() . '/assets/js/select2/select2.full.min.js',
			array( 'jquery' ),
			WC_VERSION,
			true
		);
		wp_enqueue_style(
			'woo-cpl-select2',
			WC()->plugin_url() . '/assets/css/select2.css',
			array(),
			WC_VERSION
		);
		wp_enqueue_script(
			'woo-cpl-admin',
			WOO_CPL_URL . 'assets/js/admin.js',
			array( 'jquery', 'woo-cpl-select2' ),
			WOO_CPL_VERSION,
			true
		);
		wp_enqueue_style(
			'woo-cpl-admin',
			WOO_CPL_URL . 'assets/css/admin.css',
			array(),
			WOO_CPL_VERSION
		);
		wp_localize_script( 'woo-cpl-admin', 'wooCplAdmin', array(
			'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
			'nonce'       => wp_create_nonce( 'woo_cpl_search_products' ),
			'placeholder' => __( 'Search for a product&hellip;', 'woo-cpt-product-link' ),
		) );
	}

	public function ajax_search_products(): void {
		check_ajax_referer( 'woo_cpl_search_products', 'nonce' );

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_send_json_error( 'Forbidden', 403 );
		}

		$term = isset( $_GET['term'] ) ? sanitize_text_field( wp_unslash( $_GET['term'] ) ) : '';
		$page = isset( $_GET['page'] ) ? absint( $_GET['page'] ) : 1;

		$query = new WP_Query( array(
			'post_type'      => 'product',
			'post_status'    => 'publish',
			'posts_per_page' => 20,
			'paged'          => $page,
			's'              => $term,
			'fields'         => 'ids',
		) );

		$results = array();
		foreach ( $query->posts as $id ) {
			$product = wc_get_product( $id );
			if ( ! $product ) {
				continue;
			}
			$results[] = array(
				'id'   => $id,
				'text' => $product->get_name() . ' (#' . $id . ')',
			);
		}

		wp_send_json( array(
			'results' => $results,
			'more'    => $page < $query->max_num_pages,
		) );
	}
}
