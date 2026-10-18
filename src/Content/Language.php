<?php
/**
 * Multilingual support.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Detects post languages with Polylang or WPML. Without such a plugin the site language is used.
 */
final class Language {

	/**
	 * Language code of a post, e.g. "de" or "en".
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function of_post( $post_id ) {
		$code = '';

		if ( function_exists( 'pll_get_post_language' ) ) {
			$code = (string) pll_get_post_language( $post_id, 'slug' );
		} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$details = apply_filters( 'wpml_post_language_details', null, $post_id ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API.
			if ( is_array( $details ) && ! empty( $details['language_code'] ) ) {
				$code = (string) $details['language_code'];
			}
		}

		if ( '' === $code ) {
			$code = self::site();
		}

		/**
		 * Filters the detected language of a post.
		 *
		 * @param string $code    Language code.
		 * @param int    $post_id Post ID.
		 */
		return (string) apply_filters( 'postvoyage_post_language', $code, $post_id );
	}

	/**
	 * Two-letter site language.
	 *
	 * @return string
	 */
	public static function site() {
		return strtolower( substr( get_locale(), 0, 2 ) );
	}

	/**
	 * Languages configured in Polylang/WPML as code => name. Empty without a multilingual plugin.
	 *
	 * @return array<string,string>
	 */
	public static function available() {
		$languages = array();

		if ( function_exists( 'pll_languages_list' ) ) {
			$slugs = (array) pll_languages_list( array( 'fields' => 'slug' ) );
			$names = (array) pll_languages_list( array( 'fields' => 'name' ) );
			foreach ( $slugs as $i => $slug ) {
				$languages[ $slug ] = $names[ $i ] ?? $slug;
			}
		} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
			$active = apply_filters( 'wpml_active_languages', null, array( 'skip_missing' => 0 ) ); // phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API.
			foreach ( (array) $active as $code => $language ) {
				$languages[ $code ] = $language['translated_name'] ?? $language['native_name'] ?? $code;
			}
		}

		/**
		 * Filters the languages offered in rules.
		 *
		 * @param array<string,string> $languages Code => name.
		 */
		return (array) apply_filters( 'postvoyage_languages', $languages );
	}

	/**
	 * Maps term IDs to their default-language translation (Polylang/WPML), so a rule
	 * for "News" also matches the English "News" category. Untranslated IDs stay as they are.
	 *
	 * @param int[]  $term_ids Term IDs.
	 * @param string $taxonomy Taxonomy.
	 * @return int[]
	 */
	public static function canonical_term_ids( array $term_ids, $taxonomy ) {
		$result = array();
		foreach ( $term_ids as $term_id ) {
			$canonical = (int) $term_id;
			if ( function_exists( 'pll_get_term' ) && function_exists( 'pll_default_language' ) ) {
				$translated = pll_get_term( $term_id, pll_default_language() );
				$canonical  = $translated ? (int) $translated : $canonical;
			} elseif ( defined( 'ICL_SITEPRESS_VERSION' ) ) {
				// phpcs:ignore WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound -- WPML API.
				$translated = apply_filters( 'wpml_object_id', $term_id, $taxonomy, true, apply_filters( 'wpml_default_language', null ) );
				$canonical  = $translated ? (int) $translated : $canonical;
			}
			$result[] = $canonical;
		}
		return array_values( array_unique( $result ) );
	}

	/**
	 * Whether a multilingual plugin is active.
	 *
	 * @return bool
	 */
	public static function is_multilingual() {
		return count( self::available() ) > 1;
	}
}
