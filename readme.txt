=== Woo CPT Product Link ===
Contributors: mixbusmarketing
Tags: woocommerce, custom post types, bricks, dynamic tags, products
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 7.4
Stable tag: 1.0.0
Requires Plugins: woocommerce
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Link custom post types to WooCommerce products, then render product data, add-to-cart forms, and buy-now buttons in templates.

== Description ==

Woo CPT Product Link adds a WooCommerce product selector to enabled custom post types. Once a post is linked to a product, templates can output product price, product data, add-to-cart forms, and buy-now links without duplicating commerce data on the custom post type.

It includes shortcode output, Bricks Builder dynamic tags, and Bricks Code element helper functions.

== Installation ==

1. Upload the `woo-cpt-product-link` folder to `/wp-content/plugins/`.
2. Activate Woo CPT Product Link from Plugins.
3. Go to Settings > Woo CPT Product Link and choose the post types that should show the product selector.
4. Edit one of those posts and select the related WooCommerce product.
5. Use the shortcodes, Bricks dynamic tags, or helper functions in your single template.

== Shortcodes ==

* `[woo_product_price]`
* `[woo_product_regular_price]`
* `[woo_product_sale_price]`
* `[woo_add_to_cart]`
* `[woo_buy_now]`

`[woo_add_to_cart]` supports `label`, `class`, `quantity`, `redirect`, `show_qty`, and `show_description` attributes.

`[woo_buy_now]` supports `label`, `class`, `quantity`, and `redirect` attributes.

== Bricks Dynamic Tags ==

* `{woo_product_is_linked}` returns `true` or `false`.
* `{woo_product_type}` returns the linked product type, such as `simple` or `variable`.
* `{woo_product_price}`
* `{woo_product_regular_price}`
* `{woo_product_sale_price}`
* `{woo_product_name}`
* `{woo_product_sku}`
* `{woo_product_id}`
* `{woo_product_stock_status}`
* `{woo_product_short_description}`
* `{woo_add_to_cart}`
* `{woo_buy_now}`

== Bricks Code Functions ==

* `woo_cpl_get_price( $post_id = 0 )`
* `woo_cpl_get_regular_price( $post_id = 0 )`
* `woo_cpl_get_sale_price( $post_id = 0 )`
* `woo_cpl_get_product_name( $post_id = 0 )`
* `woo_cpl_get_product_sku( $post_id = 0 )`
* `woo_cpl_get_product_id( $post_id = 0 )`
* `woo_cpl_has_linked_product( $post_id = 0 )`
* `woo_cpl_get_product_type( $post_id = 0 )`
* `woo_cpl_get_stock_status( $post_id = 0 )`
* `woo_cpl_get_short_description( $post_id = 0 )`

== Frequently Asked Questions ==

= Does this copy product pricing to the custom post type? =

No. The custom post type stores only the linked WooCommerce product ID. Price and product details are pulled live from WooCommerce.

= Can I keep existing MBM CPT Product Link data? =

Yes. The plugin reads the legacy MBM settings and post meta keys. When a post is saved through the new selector, it writes the new key.

== Changelog ==

= 1.0.0 =
* Renamed the plugin to Woo CPT Product Link by MixbusMarketing.
* Added Bricks dynamic tags for linked-product status and product type.
* Added native WooCommerce form rendering for variable, grouped, and external linked products.
* Added GitHub updater support through Plugin Update Checker.
* Added release packaging workflow, readme metadata, and text-domain cleanup.
* Added nonce validation for plugin add-to-cart redirect handling.
