<?php

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WOO_CPL_Settings {

	public function hooks(): void {
		add_action( 'admin_menu', array( $this, 'add_menu' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );
		add_action( 'update_option_' . WOO_CPL_Plugin::OPTION_KEY, array( $this, 'on_save' ) );
	}

	public function add_menu(): void {
		add_options_page(
			'Woo CPT Product Link',
			'Woo CPT Product Link',
			'manage_options',
			'woo-cpl-settings',
			array( $this, 'render_page' )
		);
	}

	public function register_settings(): void {
		register_setting(
			'woo_cpl_settings_group',
			WOO_CPL_Plugin::OPTION_KEY,
			array(
				'sanitize_callback' => array( $this, 'sanitize' ),
				'default'           => woo_cpl()->default_settings(),
			)
		);
	}

	/** Flush the in-memory cache after any save. */
	public function on_save(): void {
		woo_cpl()->flush_settings_cache();
	}

	public function sanitize( $input ): array {
		if ( ! is_array( $input ) ) {
			return woo_cpl()->default_settings();
		}

		$available_pts = $this->get_available_post_types();

		$post_types = isset( $input['enabled_post_types'] ) && is_array( $input['enabled_post_types'] )
			? array_values( array_filter( $input['enabled_post_types'], fn( $s ) => isset( $available_pts[ $s ] ) ) )
			: array();

		$after_atc_options = array( 'wc_default', 'cart', 'checkout' );
		$after_atc = isset( $input['after_add_to_cart'] ) && in_array( $input['after_add_to_cart'], $after_atc_options, true )
			? $input['after_add_to_cart']
			: 'cart';

		$after_bn_options = array( 'cart', 'checkout' );
		$after_bn = isset( $input['after_buy_now'] ) && in_array( $input['after_buy_now'], $after_bn_options, true )
			? $input['after_buy_now']
			: 'checkout';

		return array(
			'enabled_post_types'     => $post_types,
			'after_add_to_cart'      => $after_atc,
			'after_buy_now'          => $after_bn,
			'show_quantity'          => empty( $input['show_quantity'] ) ? '0' : '1',
			'show_short_description' => empty( $input['show_short_description'] ) ? '0' : '1',
		);
	}

	public function render_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings  = woo_cpl()->get_settings();
		$available = $this->get_available_post_types();
		$enabled   = $settings['enabled_post_types'];
		?>
		<div class="wrap">
			<h1>Woo CPT Product Link &mdash; Settings</h1>

			<form method="post" action="options.php">
				<?php settings_fields( 'woo_cpl_settings_group' ); ?>

				<!-- ── Enabled post types ────────────────────────────────── -->
				<h2>Enabled Post Types</h2>
				<p>Choose which post types get the WooCommerce product selector on their edit screen.</p>

				<table class="form-table" role="presentation">
					<tbody>
						<?php foreach ( $available as $slug => $label ) : ?>
							<tr>
								<th scope="row">
									<label for="woo-cpl-pt-<?php echo esc_attr( $slug ); ?>">
										<?php echo esc_html( $label ); ?>
									</label>
								</th>
								<td>
									<input
										type="checkbox"
										id="woo-cpl-pt-<?php echo esc_attr( $slug ); ?>"
										name="<?php echo esc_attr( WOO_CPL_Plugin::OPTION_KEY ); ?>[enabled_post_types][]"
										value="<?php echo esc_attr( $slug ); ?>"
										<?php checked( in_array( $slug, $enabled, true ) ); ?>
									>
									<code><?php echo esc_html( $slug ); ?></code>
								</td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>

				<!-- ── After add to cart ─────────────────────────────────── -->
				<h2>After Add to Cart</h2>
				<p>Where should the user be sent after clicking the <code>[woo_add_to_cart]</code> button?
				   This can also be overridden per-shortcode with <code>redirect="cart|checkout|wc_default"</code>.</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Redirect destination</th>
						<td>
							<?php
							$atc_options = array(
								'cart'       => 'Cart page',
								'checkout'   => 'Checkout page',
								'wc_default' => 'WooCommerce default (respects the "Redirect to cart after successful addition" WooCommerce setting)',
							);
							foreach ( $atc_options as $val => $lbl ) :
								?>
								<label style="display:block;margin-bottom:6px">
									<input
										type="radio"
										name="<?php echo esc_attr( WOO_CPL_Plugin::OPTION_KEY ); ?>[after_add_to_cart]"
										value="<?php echo esc_attr( $val ); ?>"
										<?php checked( $settings['after_add_to_cart'], $val ); ?>
									>
									<?php echo esc_html( $lbl ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<!-- ── After buy now ─────────────────────────────────────── -->
				<h2>After Buy Now</h2>
				<p>Where should the user be sent after clicking the <code>[woo_buy_now]</code> button?
				   Buy Now always replaces the current cart before redirecting.</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Redirect destination</th>
						<td>
							<?php
							$bn_options = array(
								'checkout' => 'Checkout page (recommended — skip cart)',
								'cart'     => 'Cart page',
							);
							foreach ( $bn_options as $val => $lbl ) :
								?>
								<label style="display:block;margin-bottom:6px">
									<input
										type="radio"
										name="<?php echo esc_attr( WOO_CPL_Plugin::OPTION_KEY ); ?>[after_buy_now]"
										value="<?php echo esc_attr( $val ); ?>"
										<?php checked( $settings['after_buy_now'], $val ); ?>
									>
									<?php echo esc_html( $lbl ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<!-- ── Add-to-cart form components ───────────────────────── -->
				<h2>Add-to-Cart Form Components</h2>
				<p>Control what gets rendered inside the <code>[woo_add_to_cart]</code> form.
				   Per-shortcode attributes (<code>show_qty</code>, <code>show_description</code>) override these defaults.</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Quantity input</th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( WOO_CPL_Plugin::OPTION_KEY ); ?>[show_quantity]"
									value="1"
									<?php checked( '1', $settings['show_quantity'] ); ?>
								>
								Show a quantity input field (defaults to 1 when hidden)
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Product short description</th>
						<td>
							<label>
								<input
									type="checkbox"
									name="<?php echo esc_attr( WOO_CPL_Plugin::OPTION_KEY ); ?>[show_short_description]"
									value="1"
									<?php checked( '1', $settings['show_short_description'] ); ?>
								>
								Show the linked product's short description above the button
							</label>
						</td>
					</tr>
				</table>

				<?php submit_button( 'Save Settings' ); ?>
			</form>

			<hr>
			<!-- ── Reference ─────────────────────────────────────────────── -->
			<h2>Shortcode Reference</h2>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th>Shortcode</th><th>Description</th></tr></thead>
				<tbody>
					<tr><td><code>[woo_product_price]</code></td><td>Formatted price (shows sale price if active)</td></tr>
					<tr><td><code>[woo_product_regular_price]</code></td><td>Regular price only</td></tr>
					<tr><td><code>[woo_product_sale_price]</code></td><td>Sale price (empty if not on sale)</td></tr>
					<tr>
						<td><code>[woo_add_to_cart<br>
							&nbsp;&nbsp;label="Add to Cart"<br>
							&nbsp;&nbsp;class=""<br>
							&nbsp;&nbsp;quantity="1"<br>
							&nbsp;&nbsp;redirect="cart|checkout|wc_default"<br>
							&nbsp;&nbsp;show_qty="yes|no"<br>
							&nbsp;&nbsp;show_description="yes|no"]</code>
						</td>
						<td>Add-to-cart button. Per-shortcode attributes override global settings.</td>
					</tr>
					<tr>
						<td><code>[woo_buy_now<br>
							&nbsp;&nbsp;label="Buy Now"<br>
							&nbsp;&nbsp;class=""<br>
							&nbsp;&nbsp;quantity="1"<br>
							&nbsp;&nbsp;redirect="cart|checkout"]</code>
						</td>
						<td>Buy-now button. Replaces cart, then redirects.</td>
					</tr>
				</tbody>
			</table>

			<h2 style="margin-top:24px">Bricks Dynamic Tags</h2>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th>Tag</th><th>Output</th></tr></thead>
				<tbody>
					<tr><td><code>{woo_product_is_linked}</code></td><td><code>true</code> when a valid product is linked, otherwise <code>false</code></td></tr>
					<tr><td><code>{woo_product_type}</code></td><td>WooCommerce product type, such as <code>simple</code> or <code>variable</code></td></tr>
					<tr><td><code>{woo_product_price}</code></td><td>Formatted price HTML</td></tr>
					<tr><td><code>{woo_product_regular_price}</code></td><td>Regular price</td></tr>
					<tr><td><code>{woo_product_sale_price}</code></td><td>Sale price, or empty string</td></tr>
					<tr><td><code>{woo_product_name}</code></td><td>Product title</td></tr>
					<tr><td><code>{woo_product_sku}</code></td><td>SKU</td></tr>
					<tr><td><code>{woo_product_id}</code></td><td>Product ID</td></tr>
					<tr><td><code>{woo_product_stock_status}</code></td><td>In stock / Out of stock / On backorder</td></tr>
					<tr><td><code>{woo_product_short_description}</code></td><td>Product short description</td></tr>
					<tr><td><code>{woo_add_to_cart}</code></td><td>Add-to-cart button (uses global redirect setting)</td></tr>
					<tr><td><code>{woo_buy_now}</code></td><td>Buy-now button (uses global redirect setting)</td></tr>
				</tbody>
			</table>

			<h2 style="margin-top:24px">Bricks Code Element Functions</h2>
			<p>These PHP functions are whitelisted for use inside Bricks Code elements (<code>&lt;?php echo woo_cpl_get_price(); ?&gt;</code>).</p>
			<table class="widefat striped" style="max-width:800px">
				<thead><tr><th>Function</th><th>Returns</th></tr></thead>
				<tbody>
					<tr><td><code>woo_cpl_get_price( $post_id = 0 )</code></td><td>Price HTML string</td></tr>
					<tr><td><code>woo_cpl_get_regular_price( $post_id = 0 )</code></td><td>Regular price HTML string</td></tr>
					<tr><td><code>woo_cpl_get_sale_price( $post_id = 0 )</code></td><td>Sale price HTML string, or empty</td></tr>
					<tr><td><code>woo_cpl_get_product_name( $post_id = 0 )</code></td><td>Escaped product title</td></tr>
					<tr><td><code>woo_cpl_get_product_sku( $post_id = 0 )</code></td><td>Escaped SKU</td></tr>
					<tr><td><code>woo_cpl_get_product_id( $post_id = 0 )</code></td><td>Product ID (int)</td></tr>
					<tr><td><code>woo_cpl_has_linked_product( $post_id = 0 )</code></td><td>Boolean linked-product status</td></tr>
					<tr><td><code>woo_cpl_get_product_type( $post_id = 0 )</code></td><td>Product type string</td></tr>
					<tr><td><code>woo_cpl_get_stock_status( $post_id = 0 )</code></td><td>Localised stock label</td></tr>
					<tr><td><code>woo_cpl_get_short_description( $post_id = 0 )</code></td><td>Product short description HTML</td></tr>
				</tbody>
			</table>
		</div>
		<?php
	}

	/**
	 * Returns all public, non-WooCommerce CPTs as slug => label.
	 */
	private function get_available_post_types(): array {
		$excluded   = array( 'product', 'product_variation', 'shop_order', 'shop_coupon', 'shop_order_refund', 'attachment' );
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		$result     = array();

		foreach ( $post_types as $slug => $obj ) {
			if ( in_array( $slug, $excluded, true ) ) {
				continue;
			}
			$result[ $slug ] = $obj->labels->singular_name . ' (' . $slug . ')';
		}

		return $result;
	}
}
