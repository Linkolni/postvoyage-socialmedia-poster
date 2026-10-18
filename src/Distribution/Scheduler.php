<?php
/**
 * Background scheduling.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Distribution;

use PostVoyage\Storage\AccountRepository;
use PostVoyage\Storage\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Queues the distribution when a post is published. Sharing runs in the background
 * (WP-Cron, or Action Scheduler if available) so publishing stays fast and the block
 * editor has time to save the meta box values first.
 */
final class Scheduler {

	const HOOK_DISTRIBUTE = 'postvoyage_distribute';
	const HOOK_RETRY      = 'postvoyage_retry';
	const HOOK_CLEANUP    = 'postvoyage_cleanup_log';
	const GROUP           = 'postvoyage';

	/**
	 * Registers hooks.
	 */
	public static function register() {
		add_action( 'transition_post_status', array( self::class, 'on_transition' ), 10, 3 );
	}

	/**
	 * Queues the post when it changes to "publish" for the first time.
	 * Scheduled posts (future → publish) are covered as well.
	 *
	 * @param string  $new_status New status.
	 * @param string  $old_status Old status.
	 * @param WP_Post $post       Post.
	 */
	public static function on_transition( $new_status, $old_status, $post ) {
		if ( 'publish' !== $new_status || 'publish' === $old_status ) {
			return;
		}
		if ( ! $post instanceof WP_Post || wp_is_post_revision( $post ) || wp_is_post_autosave( $post ) ) {
			return;
		}
		if ( defined( 'WP_IMPORTING' ) && WP_IMPORTING ) {
			return;
		}
		if ( ! in_array( $post->post_type, Settings::post_types(), true ) ) {
			return;
		}

		self::schedule( self::HOOK_DISTRIBUTE, array( (int) $post->ID ), time() + (int) Settings::get( 'delay' ) );
	}

	/**
	 * Schedules a single background job (no duplicates).
	 *
	 * @param string $hook      Hook.
	 * @param array  $args      Arguments.
	 * @param int    $timestamp Run time.
	 */
	public static function schedule( $hook, array $args, $timestamp ) {
		if ( function_exists( 'as_schedule_single_action' ) && function_exists( 'as_has_scheduled_action' ) ) {
			if ( ! as_has_scheduled_action( $hook, $args, self::GROUP ) ) {
				as_schedule_single_action( $timestamp, $hook, $args, self::GROUP );
			}
			return;
		}
		if ( ! wp_next_scheduled( $hook, $args ) ) {
			wp_schedule_single_event( $timestamp, $hook, $args );
		}
	}

	/**
	 * Whether a distribution is queued for the post.
	 *
	 * @param int $post_id Post ID.
	 * @return bool
	 */
	public static function is_queued( $post_id ) {
		$args = array( (int) $post_id );
		if ( function_exists( 'as_has_scheduled_action' ) ) {
			return (bool) as_has_scheduled_action( self::HOOK_DISTRIBUTE, $args, self::GROUP );
		}
		return (bool) wp_next_scheduled( self::HOOK_DISTRIBUTE, $args );
	}

	/**
	 * Schedules the recurring maintenance jobs.
	 */
	public static function ensure_recurring_events() {
		if ( ! wp_next_scheduled( self::HOOK_CLEANUP ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::HOOK_CLEANUP );
		}
		if ( ! wp_next_scheduled( AccountRepository::REFRESH_HOOK ) ) {
			wp_schedule_event( time() + DAY_IN_SECONDS, 'weekly', AccountRepository::REFRESH_HOOK );
		}
	}

	/**
	 * Removes all scheduled jobs (deactivation).
	 */
	public static function clear_all() {
		foreach ( array( self::HOOK_DISTRIBUTE, self::HOOK_RETRY, self::HOOK_CLEANUP, AccountRepository::REFRESH_HOOK ) as $hook ) {
			wp_unschedule_hook( $hook );
			if ( function_exists( 'as_unschedule_all_actions' ) ) {
				as_unschedule_all_actions( $hook, array(), self::GROUP );
			}
		}
	}
}
