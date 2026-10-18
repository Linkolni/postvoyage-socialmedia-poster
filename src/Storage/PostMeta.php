<?php
/**
 * Per-post settings and share history.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * Post meta used by PostVoyage. Keys start with an underscore so they are hidden
 * from the custom fields box.
 */
final class PostMeta {

	/** Whether the post should be shared ('1' / '0'; missing = default setting). */
	const ENABLED = '_postvoyage_enabled';

	/** Optional custom text replacing the rule template. */
	const MESSAGE = '_postvoyage_message';

	/** Share history: account ID => array( status, time, url ). */
	const SHARES = '_postvoyage_shares';

	/** Prefix for short-lived per-account locks against double posting. */
	const LOCK_PREFIX = '_postvoyage_lock_';

	/**
	 * Registers the editable meta for the REST API / block editor.
	 */
	public static function register() {
		foreach ( Settings::post_types() as $post_type ) {
			register_post_meta(
				$post_type,
				self::ENABLED,
				array(
					'type'          => 'string',
					'single'        => true,
					'show_in_rest'  => true,
					'auth_callback' => array( self::class, 'can_edit' ),
				)
			);
			register_post_meta(
				$post_type,
				self::MESSAGE,
				array(
					'type'              => 'string',
					'single'            => true,
					'show_in_rest'      => true,
					'sanitize_callback' => 'sanitize_textarea_field',
					'auth_callback'     => array( self::class, 'can_edit' ),
				)
			);
		}
	}

	/**
	 * Auth callback for protected meta.
	 *
	 * @param bool   $allowed  Whether allowed.
	 * @param string $meta_key Meta key.
	 * @param int    $post_id  Post ID.
	 * @return bool
	 */
	public static function can_edit( $allowed, $meta_key, $post_id ) {
		return current_user_can( 'edit_post', $post_id );
	}

	/**
	 * Whether the post is marked for automatic sharing.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_enabled( $post_id ) {
		$value = get_post_meta( $post_id, self::ENABLED, true );
		if ( '' === $value ) {
			return (bool) Settings::get( 'default_enabled' );
		}
		return '1' === $value;
	}

	/**
	 * Custom message for the post.
	 *
	 * @param int $post_id Post ID.
	 * @return string
	 */
	public static function message( $post_id ) {
		return (string) get_post_meta( $post_id, self::MESSAGE, true );
	}

	/**
	 * Share history.
	 *
	 * @param int $post_id Post ID.
	 * @return array<string,array<string,mixed>>
	 */
	public static function shares( $post_id ) {
		$shares = get_post_meta( $post_id, self::SHARES, true );
		return is_array( $shares ) ? $shares : array();
	}

	/**
	 * Whether the post was already shared successfully to the account.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $account_id Account ID.
	 * @return bool
	 */
	public static function was_shared( $post_id, $account_id ) {
		$shares = self::shares( $post_id );
		return isset( $shares[ $account_id ]['status'] ) && 'success' === $shares[ $account_id ]['status'];
	}

	/**
	 * Records a share attempt.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $account_id Account ID.
	 * @param string $status     'success' or 'error'.
	 * @param string $url        Link to the remote post.
	 */
	public static function record_share( $post_id, $account_id, $status, $url = '' ) {
		$shares                = self::shares( $post_id );
		$shares[ $account_id ] = array(
			'status' => $status,
			'time'   => time(),
			'url'    => $url,
		);
		update_post_meta( $post_id, self::SHARES, $shares );
	}

	/**
	 * Acquires a lock so that parallel cron runs do not post twice.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $account_id Account ID.
	 * @return bool True if the lock was acquired.
	 */
	public static function lock( $post_id, $account_id ) {
		$key   = self::LOCK_PREFIX . $account_id;
		$since = (int) get_post_meta( $post_id, $key, true );
		if ( $since && $since > time() - 5 * MINUTE_IN_SECONDS ) {
			return false;
		}
		delete_post_meta( $post_id, $key );
		return (bool) add_post_meta( $post_id, $key, time(), true );
	}

	/**
	 * Releases the lock.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $account_id Account ID.
	 */
	public static function unlock( $post_id, $account_id ) {
		delete_post_meta( $post_id, self::LOCK_PREFIX . $account_id );
	}
}
