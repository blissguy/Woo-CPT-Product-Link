<?php
/**
 * Plugin settings: registration, sanitization, admin page, and post-type helpers.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

defined( 'ABSPATH' ) || exit;

/**
 * Owns the plugin option, its admin screen, and the enabled post-type list.
 */
final class Settings {

	const OPTION_NAME      = 'wcpl_settings';
	const SETTINGS_PAGE_ID = 'wcpl-settings';

	/**
	 * Register the plugin settings.
	 */
	public static function register_settings() {
		register_setting(
			'wcpl_settings',
			self::OPTION_NAME,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( __CLASS__, 'sanitize_settings' ),
				'default'           => self::default_settings(),
			)
		);
	}

	/**
	 * Add the settings page under WooCommerce when available.
	 */
	public static function register_settings_page() {
		$parent = class_exists( 'WooCommerce' ) ? 'woocommerce' : 'options-general.php';

		add_submenu_page(
			$parent,
			__( 'Woo CPT Product Link', 'woo-cpt-product-link' ),
			__( 'CPT Product Link', 'woo-cpt-product-link' ),
			'manage_options',
			self::SETTINGS_PAGE_ID,
			array( __CLASS__, 'render_settings_page' )
		);
	}

	/**
	 * Default settings.
	 *
	 * @return array
	 */
	public static function default_settings() {
		return array(
			'post_types'      => array(),
			'default_target'  => 'cart',
			'variable_target' => 'checkout',
			'form_outputs'    => array(
				'variation_description'  => true,
				'variation_price'        => true,
				'variation_availability' => true,
				'quantity'               => true,
				'reset_link'             => true,
			),
		);
	}

	/**
	 * Get sanitized settings.
	 *
	 * @return array
	 */
	public static function get_settings() {
		$settings = get_option( self::OPTION_NAME, array() );
		$settings = wp_parse_args( is_array( $settings ) ? $settings : array(), self::default_settings() );

		$settings['form_outputs'] = wp_parse_args(
			is_array( $settings['form_outputs'] ) ? $settings['form_outputs'] : array(),
			self::default_settings()['form_outputs']
		);

		return $settings;
	}

	/**
	 * Sanitize settings before saving.
	 *
	 * @param array $input Raw settings.
	 * @return array
	 */
	public static function sanitize_settings( $input ) {
		$input      = is_array( $input ) ? $input : array();
		$post_types = array();

		if ( ! empty( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			$available = self::available_post_type_names();

			foreach ( $input['post_types'] as $post_type ) {
				$post_type = sanitize_key( $post_type );

				if ( in_array( $post_type, $available, true ) ) {
					$post_types[] = $post_type;
				}
			}
		}

		$target          = isset( $input['default_target'] ) ? sanitize_key( $input['default_target'] ) : 'cart';
		$variable_target = isset( $input['variable_target'] ) ? sanitize_key( $input['variable_target'] ) : 'checkout';
		$form_outputs    = array();

		foreach ( self::form_output_options() as $key => $label ) {
			$form_outputs[ $key ] = ! empty( $input['form_outputs'][ $key ] );
		}

		return array(
			'post_types'      => array_values( array_unique( $post_types ) ),
			'default_target'  => in_array( $target, array( 'cart', 'checkout' ), true ) ? $target : 'cart',
			'variable_target' => in_array( $variable_target, array( 'cart', 'checkout' ), true ) ? $variable_target : 'checkout',
			'form_outputs'    => $form_outputs,
		);
	}

	/**
	 * Render the admin settings page.
	 */
	public static function render_settings_page() {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$settings       = self::get_settings();
		$selected_types = $settings['post_types'];
		$post_types     = self::available_post_types();
		?>
		<div class="wrap">
			<h1><?php esc_html_e( 'Woo CPT Product Link', 'woo-cpt-product-link' ); ?></h1>
			<p><?php esc_html_e( 'Choose which content types can reference a WooCommerce product. Editors will get a product selector on those post edit screens.', 'woo-cpt-product-link' ); ?></p>

			<form method="post" action="options.php">
				<?php settings_fields( 'wcpl_settings' ); ?>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Enabled post types', 'woo-cpt-product-link' ); ?></th>
						<td>
							<?php if ( empty( $post_types ) ) : ?>
								<p><?php esc_html_e( 'No public custom post types are currently available.', 'woo-cpt-product-link' ); ?></p>
							<?php endif; ?>

							<?php foreach ( $post_types as $post_type ) : ?>
								<label style="display:block;margin-bottom:8px;">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[post_types][]"
										value="<?php echo esc_attr( $post_type->name ); ?>"
										<?php checked( in_array( $post_type->name, $selected_types, true ) ); ?>
									/>
									<?php echo esc_html( $post_type->labels->singular_name ); ?>
									<code><?php echo esc_html( $post_type->name ); ?></code>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Simple product button target', 'woo-cpt-product-link' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_target]">
								<option value="cart" <?php selected( 'cart', $settings['default_target'] ); ?>><?php esc_html_e( 'Cart page', 'woo-cpt-product-link' ); ?></option>
								<option value="checkout" <?php selected( 'checkout', $settings['default_target'] ); ?>><?php esc_html_e( 'Checkout page', 'woo-cpt-product-link' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Where the button sends visitors for simple products.', 'woo-cpt-product-link' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Variable product button target', 'woo-cpt-product-link' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[variable_target]">
								<option value="cart" <?php selected( 'cart', $settings['variable_target'] ); ?>><?php esc_html_e( 'Cart page', 'woo-cpt-product-link' ); ?></option>
								<option value="checkout" <?php selected( 'checkout', $settings['variable_target'] ); ?>><?php esc_html_e( 'Checkout page', 'woo-cpt-product-link' ); ?></option>
							</select>
							<p class="description"><?php esc_html_e( 'Where visitors are sent after selecting variations and submitting a variable or grouped product form.', 'woo-cpt-product-link' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Full form output', 'woo-cpt-product-link' ); ?></th>
						<td>
							<p class="description"><?php esc_html_e( 'Controls parts of WooCommerce variable/grouped add-to-cart forms rendered by [wcpl_add_to_cart] or [wcpl_buy_now].', 'woo-cpt-product-link' ); ?></p>
							<?php foreach ( self::form_output_options() as $key => $label ) : ?>
								<label style="display:block;margin-top:8px;">
									<input
										type="checkbox"
										name="<?php echo esc_attr( self::OPTION_NAME ); ?>[form_outputs][<?php echo esc_attr( $key ); ?>]"
										value="1"
										<?php checked( ! empty( $settings['form_outputs'][ $key ] ) ); ?>
									/>
									<?php echo esc_html( $label ); ?>
								</label>
							<?php endforeach; ?>
						</td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'Template usage', 'woo-cpt-product-link' ); ?></h2>
			<p><code>[wcpl_price]</code> <?php esc_html_e( 'renders the referenced product price.', 'woo-cpt-product-link' ); ?></p>
			<p><code>[wcpl_selected_variation_price]</code> <?php esc_html_e( 'renders a price placeholder that updates when a variable product option is selected.', 'woo-cpt-product-link' ); ?></p>
			<p><code>[wcpl_add_to_cart]</code> <?php esc_html_e( 'renders a button using the simple or variable product target above, based on the product type. Add target="cart" or target="checkout" to override.', 'woo-cpt-product-link' ); ?></p>
			<p><code>[wcpl_buy_now]</code> <?php esc_html_e( 'renders a checkout button.', 'woo-cpt-product-link' ); ?></p>
			<p><?php esc_html_e( 'Both buttons accept label="Custom text" to rename them, class="..." to replace the default classes, and extra_class="..." to append your own classes while keeping the defaults.', 'woo-cpt-product-link' ); ?></p>
			<p><code>[wcpl_product field="title"]</code> <?php esc_html_e( 'renders fields such as title, type, sku, price, short_description, description, stock_status, availability, id, image, and permalink.', 'woo-cpt-product-link' ); ?></p>
			<p><?php esc_html_e( 'Bricks dynamic tags: {wcpl_has_product}, {wcpl_product_type}, {wcpl_product_price}, {wcpl_product_title}, {wcpl_product_url}, {wcpl_product_id}, {wcpl_product_sku}, {wcpl_product_stock}.', 'woo-cpt-product-link' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Enabled post type names.
	 *
	 * @return array
	 */
	public static function enabled_post_types() {
		$settings = self::get_settings();

		return array_values( array_filter( array_map( 'sanitize_key', (array) $settings['post_types'] ) ) );
	}

	/**
	 * Available full-form output toggles.
	 *
	 * @return array
	 */
	public static function form_output_options() {
		return array(
			'variation_description'  => __( 'Variation description', 'woo-cpt-product-link' ),
			'variation_price'        => __( 'Variation price', 'woo-cpt-product-link' ),
			'variation_availability' => __( 'Variation availability', 'woo-cpt-product-link' ),
			'quantity'               => __( 'Quantity control', 'woo-cpt-product-link' ),
			'reset_link'             => __( 'Reset variations link', 'woo-cpt-product-link' ),
		);
	}

	/**
	 * Available public post type objects.
	 *
	 * @return \WP_Post_Type[]
	 */
	public static function available_post_types() {
		$post_types = get_post_types(
			array(
				'public' => true,
			),
			'objects'
		);

		unset( $post_types['attachment'], $post_types['product'], $post_types['product_variation'] );

		return apply_filters( 'wcpl_available_post_types', $post_types );
	}

	/**
	 * Available post type names.
	 *
	 * @return array
	 */
	public static function available_post_type_names() {
		return array_keys( self::available_post_types() );
	}
}
