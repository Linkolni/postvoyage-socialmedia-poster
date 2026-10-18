<?php
/**
 * Fits text into a character limit.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Shortens text to a network's character limit without breaking links.
 *
 * Pure PHP without WordPress functions, so it can be unit tested in isolation.
 */
final class TextFitter {

	const URL_REGEX = 'https?://[^\s<>"]+';

	const URL_PATTERN = '~' . self::URL_REGEX . '~u';

	const ELLIPSIS = '…';

	/**
	 * Fits $text into $limit characters.
	 *
	 * Links are never cut. Instead the longest text block is shortened at a word
	 * boundary and gets an ellipsis, until the text fits.
	 *
	 * @param string $text       Text.
	 * @param int    $limit      Maximum length, 0 = unlimited.
	 * @param int    $url_length Fixed length a link counts as (Mastodon/X: 23), 0 = real length.
	 * @return string
	 */
	public static function fit( $text, $limit, $url_length = 0 ) {
		$text = trim( $text );
		if ( $limit <= 0 || self::length( $text, $url_length ) <= $limit ) {
			return $text;
		}

		$segments = self::segments( $text );

		$guard = 0;
		while ( self::length( self::join( $segments ), $url_length ) > $limit && $guard++ < 50 ) {
			$overflow = self::length( self::join( $segments ), $url_length ) - $limit;
			$index    = self::longest_text_segment( $segments );
			if ( null === $index ) {
				break;
			}
			$segments[ $index ]['text'] = self::shorten( $segments[ $index ]['text'], self::strlen( $segments[ $index ]['text'] ) - $overflow );
		}

		$result = self::clean( self::join( $segments ) );

		// Still too long (only links left): cut hard as last resort.
		if ( self::length( $result, $url_length ) > $limit ) {
			$result = self::substr( $result, 0, max( 0, $limit - 1 ) ) . self::ELLIPSIS;
		}

		return $result;
	}

	/**
	 * Length of a text as counted by a network.
	 *
	 * @param string $text       Text.
	 * @param int    $url_length Fixed link length, 0 = real length.
	 * @return int
	 */
	public static function length( $text, $url_length = 0 ) {
		if ( $url_length > 0 ) {
			$text = (string) preg_replace( self::URL_PATTERN, str_repeat( 'x', $url_length ), $text );
		}
		return self::strlen( $text );
	}

	/**
	 * Character count. Uses graphemes when ext-intl is available (emoji = 1).
	 *
	 * @param string $text Text.
	 * @return int
	 */
	public static function strlen( $text ) {
		if ( function_exists( 'grapheme_strlen' ) ) {
			$length = grapheme_strlen( $text );
			if ( is_int( $length ) ) {
				return $length;
			}
		}
		return mb_strlen( $text, 'UTF-8' );
	}

	/**
	 * Multibyte-safe substring.
	 *
	 * @param string $text   Text.
	 * @param int    $start  Start.
	 * @param int    $length Length.
	 * @return string
	 */
	public static function substr( $text, $start, $length ) {
		if ( function_exists( 'grapheme_substr' ) ) {
			$sub = grapheme_substr( $text, $start, $length );
			if ( is_string( $sub ) ) {
				return $sub;
			}
		}
		return mb_substr( $text, $start, $length, 'UTF-8' );
	}

	/**
	 * Splits text into link and non-link segments.
	 *
	 * @param string $text Text.
	 * @return array<int,array{text:string,url:bool}>
	 */
	private static function segments( $text ) {
		$segments = array();
		$pieces   = preg_split( '~(' . self::URL_REGEX . ')~u', $text, -1, PREG_SPLIT_DELIM_CAPTURE );
		foreach ( (array) $pieces as $i => $piece ) {
			$segments[] = array(
				'text' => $piece,
				'url'  => 1 === $i % 2,
			);
		}
		return $segments;
	}

	/**
	 * Joins segments.
	 *
	 * @param array<int,array{text:string,url:bool}> $segments Segments.
	 * @return string
	 */
	private static function join( array $segments ) {
		return implode( '', array_column( $segments, 'text' ) );
	}

	/**
	 * Index of the longest shortenable text segment.
	 *
	 * @param array<int,array{text:string,url:bool}> $segments Segments.
	 * @return int|null
	 */
	private static function longest_text_segment( array $segments ) {
		$best   = null;
		$length = 0;
		foreach ( $segments as $i => $segment ) {
			if ( $segment['url'] ) {
				continue;
			}
			// A segment that is only whitespace or an ellipsis cannot be shortened further.
			$visible = self::strlen( trim( str_replace( self::ELLIPSIS, '', $segment['text'] ) ) );
			if ( $visible > $length ) {
				$best   = $i;
				$length = $visible;
			}
		}
		return $best;
	}

	/**
	 * Shortens a text block to at most $max characters at a word boundary and adds an ellipsis.
	 * Leading and trailing line breaks are kept so the layout around links stays intact.
	 *
	 * @param string $text Text block.
	 * @param int    $max  Maximum length including ellipsis.
	 * @return string
	 */
	private static function shorten( $text, $max ) {
		preg_match( '~^(\s*)(.*?)(\s*)$~su', $text, $m );
		$lead  = $m[1] ?? '';
		$core  = $m[2] ?? $text;
		$trail = $m[3] ?? '';

		$room = $max - self::strlen( $lead ) - self::strlen( $trail ) - 1;
		if ( $room <= 0 ) {
			// Keep a single separator so neighbouring links do not merge.
			return '' === $lead . $trail ? '' : ( false !== strpos( $lead . $trail, "\n" ) ? "\n" : ' ' );
		}

		$cut = self::substr( $core, 0, $room );

		// Prefer to cut at the last space if that does not lose too much.
		$space = mb_strrpos( $cut, ' ', 0, 'UTF-8' );
		if ( false !== $space && $space >= $room * 0.6 ) {
			$cut = mb_substr( $cut, 0, $space, 'UTF-8' );
		}

		$cut = rtrim( $cut, " \t\n\r\0\x0B.,;:-–—" );
		return $lead . $cut . self::ELLIPSIS . $trail;
	}

	/**
	 * Removes empty lines left over after shortening.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	private static function clean( $text ) {
		$text = (string) preg_replace( "~\n{3,}~", "\n\n", $text );
		return trim( $text );
	}
}
