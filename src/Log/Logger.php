<?php
/**
 * Share log.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Log;

use PostVoyage\Distribution\Scheduler;
use PostVoyage\Networks\Result;
use PostVoyage\Storage\Account;
use PostVoyage\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Writes every share attempt into the table {prefix}postvoyage_log.
 */
final class Logger {

	/**
	 * Registers hooks.
	 */
	public static function register() {
		add_action( Scheduler::HOOK_CLEANUP, array( self::class, 'cleanup' ) );
	}

	/**
	 * Table name.
	 *
	 * @return string
	 */
	public static function table() {
		global $wpdb;
		return $wpdb->prefix . 'postvoyage_log';
	}

	/**
	 * Writes a log entry.
	 *
	 * @param int     $post_id Post ID.
	 * @param Account $account Account.
	 * @param Result  $result  Result.
	 */
	public static function log( $post_id, Account $account, Result $result ) {
		global $wpdb;
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery -- Custom table.
		$wpdb->insert(
			self::table(),
			array(
				'created_at'    => current_time( 'mysql', true ),
				'post_id'       => (int) $post_id,
				'account_id'    => $account->id,
				'account_label' => mb_substr( $account->label, 0, 191 ),
				'network'       => $account->network,
				'status'        => $result->success ? 'success' : 'error',
				'message'       => $result->message,
				'remote_url'    => mb_substr( $result->remote_url, 0, 255 ),
			),
			array( '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Paged log entries.
	 *
	 * @param array{status?:string,post_id?:int,per_page?:int,page?:int} $args Filters.
	 * @return array{rows:array<int,object>,total:int}
	 */
	public static function query( array $args = array() ) {
		global $wpdb;

		$table    = self::table();
		$per_page = max( 1, (int) ( $args['per_page'] ?? 20 ) );
		$offset   = ( max( 1, (int) ( $args['page'] ?? 1 ) ) - 1 ) * $per_page;

		$where  = array( '1=1' );
		$params = array();
		if ( ! empty( $args['status'] ) ) {
			$where[]  = 'status = %s';
			$params[] = (string) $args['status'];
		}
		if ( ! empty( $args['post_id'] ) ) {
			$where[]  = 'post_id = %d';
			$params[] = (int) $args['post_id'];
		}
		$where_sql = implode( ' AND ', $where );

		// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.PreparedSQLPlaceholders.UnfinishedPrepare, WordPress.DB.PreparedSQLPlaceholders.ReplacementsWrongNumber -- Custom table; $where_sql only contains placeholders.
		// "%d = %d" keeps prepare() valid when no filter is set (it requires at least one placeholder).
		$total = (int) $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$table} WHERE {$where_sql} AND %d = %d", array_merge( $params, array( 1, 1 ) ) ) );
		$rows  = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$table} WHERE {$where_sql} ORDER BY id DESC LIMIT %d OFFSET %d", array_merge( $params, array( $per_page, $offset ) ) ) );
		// phpcs:enable

		return array(
			'rows'  => is_array( $rows ) ? $rows : array(),
			'total' => $total,
		);
	}

	/**
	 * Deletes entries older than the retention period (daily cron).
	 */
	public static function cleanup() {
		global $wpdb;
		$days   = max( 1, (int) Settings::get( 'log_retention_days' ) );
		$cutoff = gmdate( 'Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS );
		$table  = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$wpdb->query( $wpdb->prepare( "DELETE FROM {$table} WHERE created_at < %s", $cutoff ) );
	}

	/**
	 * Deletes all entries.
	 */
	public static function clear() {
		global $wpdb;
		$table = self::table();
		// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Custom table.
		$wpdb->query( "DELETE FROM {$table}" );
	}
}
