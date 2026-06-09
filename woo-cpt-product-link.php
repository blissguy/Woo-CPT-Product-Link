<?php
/**
 * Plugin Name: Woo CPT Product Link
 * Description: Link custom post type entries to real WooCommerce products and render product price, details, add-to-cart, or buy-now controls from CPT templates.
 * Version: 1.0.1
 * Author: MixbusMarketing
 * Author URI: https://mixbusmarketing.com/
 * Plugin URI: https://github.com/blissguy/Woo-CPT-Product-Link
 * License: GPL-2.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * WC requires at least: 8.0
 * Text Domain: woo-cpt-product-link
 *
 * @package WooCptProductLink
 */

defined( 'ABSPATH' ) || exit;

if ( file_exists( __DIR__ . '/plugin-update-checker/plugin-update-checker.php' ) ) {
	require_once __DIR__ . '/plugin-update-checker/plugin-update-checker.php';
}

final class MBM_Woo_CPT_Product_Link {
	const VERSION          = '1.0.1';
	const OPTION_NAME      = 'wcpl_settings';
	const META_PRODUCT_ID  = '_wcpl_product_id';
	const NONCE_ACTION     = 'wcpl_save_product';
	const NONCE_NAME       = 'wcpl_nonce';
	const SETTINGS_PAGE_ID = 'wcpl-settings';

	/**
	 * Redirect target while rendering a WooCommerce form.
	 *
	 * @var string
	 */
	private static $form_redirect_target = 'cart';

	/**
	 * Custom button label while rendering a WooCommerce form.
	 *
	 * @var string
	 */
	private static $form_button_label = '';

	/**
	 * Whether the selected variation price script has already been printed.
	 *
	 * @var bool
	 */
	private static $selected_variation_price_script_printed = false;

	/**
	 * Boot plugin hooks.
	 */
	public static function init() {
		add_action( 'admin_init', array( __CLASS__, 'register_settings' ) );
		add_action( 'admin_menu', array( __CLASS__, 'register_settings_page' ) );
		add_action( 'add_meta_boxes', array( __CLASS__, 'register_meta_boxes' ) );
		add_action( 'save_post', array( __CLASS__, 'save_related_product' ), 10, 2 );
		add_action( 'admin_enqueue_scripts', array( __CLASS__, 'enqueue_admin_assets' ) );
		add_action( 'init', array( __CLASS__, 'register_shortcodes' ) );
		self::setup_update_checker();

		add_filter( 'woocommerce_add_to_cart_redirect', array( __CLASS__, 'maybe_redirect_after_form_add_to_cart' ), 20 );
		add_filter( 'bricks/dynamic_tags_list', array( __CLASS__, 'register_bricks_tags' ) );
		add_filter( 'bricks/dynamic_data/render_tag', array( __CLASS__, 'render_bricks_tag' ), 20, 3 );
		add_filter( 'bricks/dynamic_data/render_content', array( __CLASS__, 'render_bricks_content' ), 20, 3 );
		add_filter( 'bricks/frontend/render_data', array( __CLASS__, 'render_bricks_content' ), 20, 2 );
	}

	/**
	 * Configure GitHub release updates.
	 */
	public static function setup_update_checker() {
		if ( ! class_exists( '\\YahnisElsts\\PluginUpdateChecker\\v5\\PucFactory' ) ) {
			return;
		}

		$update_checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			'https://github.com/blissguy/Woo-CPT-Product-Link/',
			__FILE__,
			'woo-cpt-product-link'
		);

		$update_checker->setBranch( 'main' );
		$update_checker->getVcsApi()->enableReleaseAssets( '/woo-cpt-product-link-[0-9A-Za-z.-]+\.zip($|[?&#])/i' );
	}

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
			__( 'Woo CPT Product Link', 'woo-cpt-product-link' ),
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
			'post_types'     => array(),
			'default_target' => 'cart',
			'form_outputs'   => array(
				'variation_description' => true,
				'variation_price'       => true,
				'variation_availability' => true,
				'quantity'              => true,
				'reset_link'            => true,
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

		$target       = isset( $input['default_target'] ) ? sanitize_key( $input['default_target'] ) : 'cart';
		$form_outputs = array();

		foreach ( self::form_output_options() as $key => $label ) {
			$form_outputs[ $key ] = ! empty( $input['form_outputs'][ $key ] );
		}

			return array(
				'post_types'     => array_values( array_unique( $post_types ) ),
				'default_target' => in_array( $target, array( 'cart', 'checkout' ), true ) ? $target : 'cart',
				'form_outputs'   => $form_outputs,
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
						<th scope="row"><?php esc_html_e( 'Default button target', 'woo-cpt-product-link' ); ?></th>
						<td>
							<select name="<?php echo esc_attr( self::OPTION_NAME ); ?>[default_target]">
								<option value="cart" <?php selected( 'cart', $settings['default_target'] ); ?>><?php esc_html_e( 'Cart page', 'woo-cpt-product-link' ); ?></option>
								<option value="checkout" <?php selected( 'checkout', $settings['default_target'] ); ?>><?php esc_html_e( 'Checkout page', 'woo-cpt-product-link' ); ?></option>
							</select>
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
			<p><code>[wcpl_add_to_cart]</code> <?php esc_html_e( 'renders a button using the default target.', 'woo-cpt-product-link' ); ?></p>
			<p><code>[wcpl_buy_now]</code> <?php esc_html_e( 'renders a checkout button.', 'woo-cpt-product-link' ); ?></p>
			<p><code>[wcpl_product field="title"]</code> <?php esc_html_e( 'renders fields such as title, type, sku, price, short_description, description, stock_status, availability, id, image, and permalink.', 'woo-cpt-product-link' ); ?></p>
			<p><?php esc_html_e( 'Bricks dynamic tags: {wcpl_has_product}, {wcpl_product_type}, {wcpl_product_price}, {wcpl_product_title}, {wcpl_product_url}, {wcpl_product_id}, {wcpl_product_sku}, {wcpl_product_stock}.', 'woo-cpt-product-link' ); ?></p>
		</div>
		<?php
	}

	/**
	 * Register the product relationship meta box.
	 */
	public static function register_meta_boxes() {
		if ( ! function_exists( 'wc_get_product' ) ) {
			return;
		}

		foreach ( self::enabled_post_types() as $post_type ) {
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

		$product_id = self::get_related_product_id( $post->ID );
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

		if ( ! $post instanceof WP_Post || ! in_array( $post->post_type, self::enabled_post_types(), true ) ) {
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
			update_post_meta( $post_id, self::META_PRODUCT_ID, $product_id );
		} else {
			delete_post_meta( $post_id, self::META_PRODUCT_ID );
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

		if ( ! $screen || ! in_array( $screen->post_type, self::enabled_post_types(), true ) ) {
			return;
		}

		wp_enqueue_style( 'woocommerce_admin_styles' );
		wp_enqueue_script( 'wc-enhanced-select' );
	}

	/**
	 * Register frontend shortcodes.
	 */
	public static function register_shortcodes() {
		add_shortcode( 'wcpl_price', array( __CLASS__, 'price_shortcode' ) );
		add_shortcode( 'wcpl_selected_variation_price', array( __CLASS__, 'selected_variation_price_shortcode' ) );
		add_shortcode( 'wcpl_add_to_cart', array( __CLASS__, 'add_to_cart_shortcode' ) );
		add_shortcode( 'wcpl_buy_now', array( __CLASS__, 'buy_now_shortcode' ) );
		add_shortcode( 'wcpl_product', array( __CLASS__, 'product_shortcode' ) );
	}

	/**
	 * Render price shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function price_shortcode( $atts ) {
		$product = self::get_product_from_atts( $atts );

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
	public static function selected_variation_price_shortcode( $atts ) {
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

		$product = self::get_product_from_atts( $atts );

		if ( ! $product ) {
			return '';
		}

		$fallback = 'empty' === sanitize_key( $atts['fallback'] ) ? '' : $product->get_price_html();
		$classes  = array_unique( array_filter( preg_split( '/\s+/', 'wcpl-selected-variation-price ' . self::sanitize_class_list( $atts['class'] ) ) ) );
		$class    = implode( ' ', $classes );

		$output = sprintf(
			'<span class="%1$s" data-product_id="%2$d" data-fallback-html="%3$s">%4$s</span>',
			esc_attr( $class ),
			absint( $product->get_id() ),
			esc_attr( $fallback ),
			wp_kses_post( $fallback )
		);

		return $output . self::render_selected_variation_price_script();
	}

	/**
	 * Render default add-to-cart shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function add_to_cart_shortcode( $atts ) {
		$settings = self::get_settings();
		$atts     = shortcode_atts(
			array(
				'label'      => '',
				'target'     => $settings['default_target'],
				'quantity'   => 1,
				'class'      => 'button wcpl-add-to-cart',
				'show_price' => 'false',
				'post_id'    => 0,
				'product'    => 0,
				'product_id' => 0,
			),
			$atts,
			'wcpl_add_to_cart'
		);

		return self::render_button( $atts );
	}

	/**
	 * Render checkout shortcut shortcode.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function buy_now_shortcode( $atts ) {
		$atts = shortcode_atts(
			array(
				'label'      => __( 'Buy now', 'woo-cpt-product-link' ),
				'target'     => 'checkout',
				'quantity'   => 1,
				'class'      => 'button wcpl-buy-now',
				'show_price' => 'false',
				'post_id'    => 0,
				'product'    => 0,
				'product_id' => 0,
			),
			$atts,
			'wcpl_buy_now'
		);

		return self::render_button( $atts );
	}

	/**
	 * Render arbitrary product field.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return string
	 */
	public static function product_shortcode( $atts ) {
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

		$product = self::get_product_from_atts( $atts );

		if ( ! $product ) {
			return '';
		}

		return self::render_product_field( $product, sanitize_key( $atts['field'] ), sanitize_key( $atts['size'] ) );
	}

	/**
	 * Render an add-to-cart or checkout button.
	 *
	 * @param array $atts Parsed attributes.
	 * @return string
	 */
	private static function render_button( $atts ) {
		$product = self::get_product_from_atts( $atts );

		if ( ! $product ) {
			return '';
		}

		$quantity = max( 1, absint( $atts['quantity'] ) );
		$target   = in_array( $atts['target'], array( 'cart', 'checkout' ), true ) ? $atts['target'] : 'cart';
		$label    = $atts['label'] ? sanitize_text_field( $atts['label'] ) : $product->add_to_cart_text();
		$class    = self::sanitize_class_list( $atts['class'] );
		$url      = self::get_purchase_url( $product, $target, $quantity );

		if ( ! $url && $product->is_type( array( 'variable', 'grouped' ) ) ) {
			return self::render_add_to_cart_form( $product, $target, $label );
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
	 * @param WC_Product $product  WooCommerce product.
	 * @param string     $target   cart or checkout.
	 * @param int        $quantity Quantity.
	 * @return string
	 */
	private static function get_purchase_url( $product, $target, $quantity ) {
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
	 * @param WC_Product $product Product.
	 * @param string     $target  cart or checkout.
	 * @param string     $label   Button label.
	 * @return string
	 */
	private static function render_add_to_cart_form( $wc_product, $target, $label ) {
		if ( ! function_exists( 'woocommerce_template_single_add_to_cart' ) ) {
			return '';
		}

		global $post, $product;

		$previous_post    = $post;
		$previous_product = $product;
		$product_post     = get_post( $wc_product->get_id() );

		$post    = $product_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited
		$product = $wc_product; // phpcs:ignore WordPress.WP.GlobalVariablesOverride.Prohibited

		self::$form_redirect_target = $target;
		self::$form_button_label    = $label;

		add_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'render_form_redirect_field' ) );
		add_filter( 'woocommerce_add_to_cart_form_action', array( __CLASS__, 'get_current_form_action' ) );
		add_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'get_form_button_label' ) );
		add_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_available_variation_output' ) );

		ob_start();
		printf(
			'<div class="wcpl-add-to-cart-form %s">',
			esc_attr( self::get_form_output_classes() )
		);
		self::render_form_output_styles();
		woocommerce_template_single_add_to_cart();
		echo '</div>';
		$output = ob_get_clean();

		remove_filter( 'woocommerce_available_variation', array( __CLASS__, 'filter_available_variation_output' ) );
		remove_filter( 'woocommerce_product_single_add_to_cart_text', array( __CLASS__, 'get_form_button_label' ) );
		remove_filter( 'woocommerce_add_to_cart_form_action', array( __CLASS__, 'get_current_form_action' ) );
		remove_action( 'woocommerce_after_add_to_cart_button', array( __CLASS__, 'render_form_redirect_field' ) );

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
	public static function filter_available_variation_output( $variation ) {
		$outputs = self::get_settings()['form_outputs'];

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
	private static function get_form_output_classes() {
		$outputs = self::get_settings()['form_outputs'];
		$classes = array();

		foreach ( self::form_output_options() as $key => $label ) {
			if ( empty( $outputs[ $key ] ) ) {
				$classes[] = 'wcpl-hide-' . str_replace( '_', '-', $key );
			}
		}

		return implode( ' ', $classes );
	}

	/**
	 * Render scoped CSS for static WooCommerce form pieces.
	 */
	private static function render_form_output_styles() {
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
	private static function render_selected_variation_price_script() {
		if ( self::$selected_variation_price_script_printed ) {
			return '';
		}

		self::$selected_variation_price_script_printed = true;

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
	public static function render_form_redirect_field() {
		printf(
			'<input type="hidden" name="wcpl_redirect" value="%s" />',
			esc_attr( self::$form_redirect_target )
		);
	}

	/**
	 * Keep variable/grouped add-to-cart forms on the current CPT URL.
	 *
	 * @return string
	 */
	public static function get_current_form_action() {
		global $wp;

		return home_url( add_query_arg( array(), $wp->request ) );
	}

	/**
	 * Override the form button text when a shortcode label is supplied.
	 *
	 * @param string $label Default label.
	 * @return string
	 */
	public static function get_form_button_label( $label ) {
		return self::$form_button_label ? self::$form_button_label : $label;
	}

	/**
	 * Redirect CPT-hosted WooCommerce forms after add to cart.
	 *
	 * @param string $url Existing redirect URL.
	 * @return string
	 */
	public static function maybe_redirect_after_form_add_to_cart( $url ) {
		$target = isset( $_REQUEST['wcpl_redirect'] ) ? sanitize_key( wp_unslash( $_REQUEST['wcpl_redirect'] ) ) : ''; // phpcs:ignore WordPress.Security.NonceVerification.Recommended

		if ( 'checkout' === $target ) {
			return wc_get_checkout_url();
		}

		if ( 'cart' === $target ) {
			return wc_get_cart_url();
		}

		return $url;
	}

	/**
	 * Get product from shortcode attributes or current post.
	 *
	 * @param array $atts Shortcode attributes.
	 * @return WC_Product|false
	 */
	private static function get_product_from_atts( $atts ) {
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

	/**
	 * Render one product field.
	 *
	 * @param WC_Product $product Product.
	 * @param string     $field   Field key.
	 * @param string     $size    Image size.
	 * @return string
	 */
	private static function render_product_field( $product, $field, $size = 'woocommerce_thumbnail' ) {
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
	 * @param string       $tag     Dynamic tag.
	 * @param int|WP_Post  $post    Post ID or object.
	 * @param string       $context Render context.
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
		$product = self::get_product_from_atts( array( 'post_id' => $post_id ) );

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

		return self::render_product_field( $product, $field_map[ $clean_tag ] );
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

	/**
	 * Enabled post type names.
	 *
	 * @return array
	 */
	private static function enabled_post_types() {
		$settings = self::get_settings();

		return array_values( array_filter( array_map( 'sanitize_key', (array) $settings['post_types'] ) ) );
	}

	/**
	 * Sanitize a space-separated CSS class list.
	 *
	 * @param string $classes Raw class list.
	 * @return string
	 */
	private static function sanitize_class_list( $classes ) {
		$classes = preg_split( '/\s+/', (string) $classes );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', $classes );
	}

	/**
	 * Available full-form output toggles.
	 *
	 * @return array
	 */
	private static function form_output_options() {
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
	 * @return WP_Post_Type[]
	 */
	private static function available_post_types() {
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
	private static function available_post_type_names() {
		return array_keys( self::available_post_types() );
	}
}

add_action( 'plugins_loaded', array( 'MBM_Woo_CPT_Product_Link', 'init' ) );

/**
 * Public helper for theme/plugin usage.
 *
 * @param int $post_id CPT post ID.
 * @return WC_Product|false
 */
function wcpl_get_product( $post_id = 0 ) {
	if ( ! class_exists( 'WooCommerce' ) || ! function_exists( 'wc_get_product' ) ) {
		return false;
	}

	$product_id = MBM_Woo_CPT_Product_Link::get_related_product_id( $post_id );

	return $product_id ? wc_get_product( $product_id ) : false;
}
