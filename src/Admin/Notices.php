<?php
/**
 * Flash messages after redirects.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

defined( 'ABSPATH' ) || exit;

/**
 * Stores admin notices per user until the next page view (Post/Redirect/Get).
 */
final class Notices {

	/**
	 * Adds a notice.
	 *
	 * @param string $type    success|error|warning|info.
	 * @param string $message Plain text.
	 */
	public static function add( $type, $message ) {
		$notices   = self::get();
		$notices[] = array(
			'type'    => $type,
			'message' => $message,
		);
		set_transient( self::key(), $notices, 5 * MINUTE_IN_SECONDS );
	}

	/**
	 * Prints and clears all notices.
	 */
	public static function render() {
		foreach ( self::get() as $notice ) {
			printf(
				'<div class="notice notice-%1$s is-dismissible"><p>%2$s</p></div>',
				esc_attr( $notice['type'] ),
				esc_html( $notice['message'] )
			);
		}
		delete_transient( self::key() );
	}

	/**
	 * Pending notices.
	 *
	 * @return array<int,array{type:string,message:string}>
	 */
	private static function get() {
		$notices = get_transient( self::key() );
		return is_array( $notices ) ? $notices : array();
	}

	/**
	 * Transient key of the current user.
	 *
	 * @return string
	 */
	private static function key() {
		return 'postvoyage_notices_' . get_current_user_id();
	}
}
