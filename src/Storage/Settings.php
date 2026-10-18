<?php
/**
 * General plugin settings.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Reads and sanitizes the option "postvoyage_settings".
 */
final class Settings {

	const OPTION = 'postvoyage_settings';

	/**
	 * Default values.
	 *
	 * @return array<string,mixed>
	 */
	public static function defaults() {
		return array(
			'post_types'          => array( 'post' ),
			'default_enabled'     => true,
			'delay'               => 60,
			'utm_enabled'         => true,
			'utm_source'          => '{network}',
			'utm_medium'          => 'social',
			'utm_campaign'        => 'postvoyage',
			'log_retention_days'  => 90,
			'delete_on_uninstall' => false,
		);
	}

	/**
	 * All settings merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	public static function all() {
		$stored = get_option( self::OPTION, array() );
		return array_merge( self::defaults(), is_array( $stored ) ? $stored : array() );
	}

	/**
	 * Single setting.
	 *
	 * @param string $key Setting key.
	 * @return mixed
	 */
	public static function get( $key ) {
		$all = self::all();
		return isset( $all[ $key ] ) ? $all[ $key ] : null;
	}

	/**
	 * Post types that can be distributed.
	 *
	 * @return string[]
	 */
	public static function post_types() {
		/**
		 * Filters the post types PostVoyage handles.
		 *
		 * @param string[] $post_types Post type slugs from the settings.
		 */
		return (array) apply_filters( 'postvoyage_post_types', (array) self::get( 'post_types' ) );
	}

	/**
	 * Sanitize callback for register_setting().
	 *
	 * @param mixed $input Raw input.
	 * @return array<string,mixed>
	 */
	public static function sanitize( $input ) {
		$input    = is_array( $input ) ? $input : array();
		$defaults = self::defaults();

		$post_types = array();
		if ( isset( $input['post_types'] ) && is_array( $input['post_types'] ) ) {
			foreach ( $input['post_types'] as $post_type ) {
				$post_type = sanitize_key( $post_type );
				if ( post_type_exists( $post_type ) ) {
					$post_types[] = $post_type;
				}
			}
		}

		return array(
			'post_types'          => $post_types,
			'default_enabled'     => ! empty( $input['default_enabled'] ),
			'delay'               => isset( $input['delay'] ) ? max( 0, min( 86400, absint( $input['delay'] ) ) ) : $defaults['delay'],
			'utm_enabled'         => ! empty( $input['utm_enabled'] ),
			'utm_source'          => isset( $input['utm_source'] ) ? sanitize_text_field( $input['utm_source'] ) : $defaults['utm_source'],
			'utm_medium'          => isset( $input['utm_medium'] ) ? sanitize_text_field( $input['utm_medium'] ) : $defaults['utm_medium'],
			'utm_campaign'        => isset( $input['utm_campaign'] ) ? sanitize_text_field( $input['utm_campaign'] ) : $defaults['utm_campaign'],
			'log_retention_days'  => isset( $input['log_retention_days'] ) ? max( 1, absint( $input['log_retention_days'] ) ) : $defaults['log_retention_days'],
			'delete_on_uninstall' => ! empty( $input['delete_on_uninstall'] ),
		);
	}
}
