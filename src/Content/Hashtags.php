<?php
/**
 * Hashtag helpers.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Turns term names into hashtags. Pure PHP, unit tested.
 */
final class Hashtags {

	/**
	 * Converts names like "Social Media" or "E-Book" to "SocialMedia" / "EBook".
	 *
	 * @param string[] $names Term names.
	 * @return string[] Tags without "#", unique, in original order.
	 */
	public static function from_names( array $names ) {
		$tags = array();
		foreach ( $names as $name ) {
			$name  = html_entity_decode( (string) $name, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
			$words = preg_split( '~[^\p{L}\p{N}_]+~u', $name, -1, PREG_SPLIT_NO_EMPTY );
			if ( ! $words ) {
				continue;
			}
			$tag = '';
			foreach ( $words as $i => $word ) {
				$tag .= 0 === $i ? $word : mb_strtoupper( mb_substr( $word, 0, 1, 'UTF-8' ), 'UTF-8' ) . mb_substr( $word, 1, null, 'UTF-8' );
			}
			// Purely numeric hashtags are not recognised by most networks.
			if ( preg_match( '~^\d+$~', $tag ) ) {
				continue;
			}
			$tags[ mb_strtolower( $tag, 'UTF-8' ) ] = $tag;
		}
		return array_values( $tags );
	}

	/**
	 * Formats tags as "#One #Two".
	 *
	 * @param string[] $tags Tags without "#".
	 * @return string
	 */
	public static function format( array $tags ) {
		return implode(
			' ',
			array_map(
				static function ( $tag ) {
					return '#' . $tag;
				},
				$tags
			)
		);
	}
}
