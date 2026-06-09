<?php
/**
 * Shared helpers.
 *
 * @package WooCptProductLink
 */

namespace MixbusMarketing\WooCptProductLink;

defined( 'ABSPATH' ) || exit;

/**
 * Small stateless utilities shared across renderers.
 */
final class Util {

	/**
	 * Sanitize a space-separated CSS class list.
	 *
	 * @param string $classes Raw class list.
	 * @return string
	 */
	public static function sanitize_class_list( $classes ) {
		$classes = preg_split( '/\s+/', (string) $classes );
		$classes = array_filter( array_map( 'sanitize_html_class', $classes ) );

		return implode( ' ', array_unique( $classes ) );
	}
}
