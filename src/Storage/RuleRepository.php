<?php
/**
 * Persistence of distribution rules.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * A rule decides which posts (post type, categories, language) go to which accounts
 * and with which text template. Rules are stored as arrays in "postvoyage_rules".
 */
final class RuleRepository {

	const OPTION = 'postvoyage_rules';

	/**
	 * Template used when nothing else is configured.
	 *
	 * @return string
	 */
	public static function default_template() {
		return "{title}\n\n{excerpt}\n\n{url}\n\n{hashtags}";
	}

	/**
	 * Shape of a rule with default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function blank() {
		return array(
			'id'               => '',
			'name'             => '',
			'enabled'          => true,
			'post_types'       => array(),
			'categories'       => array(),
			'include_children' => true,
			'languages'        => array(),
			'accounts'         => array(),
			'template'         => self::default_template(),
			'templates'        => array(),
			'attach_image'     => true,
		);
	}

	/**
	 * All rules in their configured order.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		$rules  = array();
		foreach ( is_array( $stored ) ? $stored : array() as $rule ) {
			if ( ! empty( $rule['id'] ) ) {
				$rules[ $rule['id'] ] = array_merge( self::blank(), $rule );
			}
		}
		return $rules;
	}

	/**
	 * Single rule.
	 *
	 * @param string $id Rule ID.
	 * @return array<string,mixed>|null
	 */
	public static function find( $id ) {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Inserts or updates a rule.
	 *
	 * @param array<string,mixed> $rule Sanitized rule.
	 * @return string Rule ID.
	 */
	public static function save( array $rule ) {
		$rules = self::all();
		if ( empty( $rule['id'] ) ) {
			$rule['id'] = 'rule_' . strtolower( wp_generate_password( 10, false, false ) );
		}
		$rules[ $rule['id'] ] = array_merge( self::blank(), $rule );
		update_option( self::OPTION, $rules, false );
		return $rule['id'];
	}

	/**
	 * Deletes a rule.
	 *
	 * @param string $id Rule ID.
	 */
	public static function delete( $id ) {
		$rules = self::all();
		unset( $rules[ $id ] );
		update_option( self::OPTION, $rules, false );
	}

	/**
	 * Creates the starter rule "All posts to all accounts" on first install.
	 */
	public static function seed_default_rule() {
		if ( false !== get_option( self::OPTION ) ) {
			return;
		}
		$rule         = self::blank();
		$rule['name'] = __( 'All posts', 'postvoyage-socialmedia-poster' );
		self::save( $rule );
	}

	/**
	 * Sanitizes submitted rule data.
	 *
	 * @param array $input Raw (unslashed) input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( array $input ) {
		$rule = self::blank();

		$rule['id']               = isset( $input['id'] ) ? sanitize_key( $input['id'] ) : '';
		$rule['name']             = sanitize_text_field( (string) ( $input['name'] ?? '' ) );
		$rule['enabled']          = ! empty( $input['enabled'] );
		$rule['include_children'] = ! empty( $input['include_children'] );
		$rule['attach_image']     = ! empty( $input['attach_image'] );
		$rule['post_types']       = array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['post_types'] ?? array() ) ), 'post_type_exists' ) );
		$rule['categories']       = array_values( array_filter( array_map( 'absint', (array) ( $input['categories'] ?? array() ) ) ) );
		$rule['languages']        = array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['languages'] ?? array() ) ) ) );
		$rule['accounts']         = array_values( array_filter( array_map( 'sanitize_key', (array) ( $input['accounts'] ?? array() ) ) ) );
		$rule['template']         = self::sanitize_template( (string) ( $input['template'] ?? '' ) );

		$rule['templates'] = array();
		foreach ( (array) ( $input['templates'] ?? array() ) as $network => $template ) {
			$template = self::sanitize_template( (string) $template );
			if ( '' !== $template ) {
				$rule['templates'][ sanitize_key( $network ) ] = $template;
			}
		}

		if ( '' === $rule['name'] ) {
			$rule['name'] = __( 'Unnamed rule', 'postvoyage-socialmedia-poster' );
		}
		if ( '' === $rule['template'] ) {
			$rule['template'] = self::default_template();
		}

		return $rule;
	}

	/**
	 * Templates are plain text with line breaks; tags are removed.
	 *
	 * @param string $template Raw template.
	 * @return string
	 */
	public static function sanitize_template( $template ) {
		return trim( sanitize_textarea_field( $template ) );
	}
}
