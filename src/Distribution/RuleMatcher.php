<?php
/**
 * Finds the rules that apply to a post.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Distribution;

use PostVoyage\Content\Language;
use PostVoyage\Storage\RuleRepository;
use PostVoyage\Storage\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Matches posts against rules by post type, category and language.
 */
final class RuleMatcher {

	/**
	 * Enabled rules matching the post, in configured order.
	 *
	 * @param WP_Post $post Post.
	 * @return array<string,array<string,mixed>>
	 */
	public static function matching( WP_Post $post ) {
		$matches = array();
		foreach ( RuleRepository::all() as $id => $rule ) {
			if ( $rule['enabled'] && self::matches( $rule, $post ) ) {
				$matches[ $id ] = $rule;
			}
		}

		/**
		 * Filters the rules that apply to a post.
		 *
		 * @param array   $matches Matching rules.
		 * @param WP_Post $post    Post.
		 */
		return (array) apply_filters( 'postvoyage_matching_rules', $matches, $post );
	}

	/**
	 * Whether one rule matches the post.
	 *
	 * @param array<string,mixed> $rule Rule.
	 * @param WP_Post             $post Post.
	 * @return bool
	 */
	public static function matches( array $rule, WP_Post $post ) {
		$post_types = $rule['post_types'] ? $rule['post_types'] : Settings::post_types();
		if ( ! in_array( $post->post_type, $post_types, true ) ) {
			return false;
		}

		if ( $rule['languages'] && ! in_array( Language::of_post( $post->ID ), $rule['languages'], true ) ) {
			return false;
		}

		if ( $rule['categories'] ) {
			if ( ! is_object_in_taxonomy( $post->post_type, 'category' ) ) {
				return false;
			}
			$wanted = array_map( 'intval', $rule['categories'] );
			if ( $rule['include_children'] ) {
				foreach ( $rule['categories'] as $term_id ) {
					$children = get_term_children( (int) $term_id, 'category' );
					if ( is_array( $children ) ) {
						$wanted = array_merge( $wanted, array_map( 'intval', $children ) );
					}
				}
			}
			$post_terms = wp_get_post_categories( $post->ID );

			// With Polylang/WPML compare the default-language versions of the categories.
			$wanted     = Language::canonical_term_ids( $wanted, 'category' );
			$post_terms = Language::canonical_term_ids( array_map( 'intval', (array) $post_terms ), 'category' );

			if ( ! array_intersect( $wanted, $post_terms ) ) {
				return false;
			}
		}

		return true;
	}
}
