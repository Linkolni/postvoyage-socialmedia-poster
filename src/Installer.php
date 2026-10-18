<?php
/**
 * Activation, deactivation and database upgrades.
 *
 * @package PostVoyage
 */

namespace PostVoyage;

use PostVoyage\Distribution\Scheduler;
use PostVoyage\Log\Logger;
use PostVoyage\Storage\RuleRepository;
use PostVoyage\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Creates the log table, default options and a starter rule.
 */
final class Installer {

	/**
	 * Schema version. Increase when the log table changes.
	 */
	const DB_VERSION = '1';

	/**
	 * Option that stores the installed schema version.
	 */
	const DB_VERSION_OPTION = 'postvoyage_db_version';

	/**
	 * Activation hook. On multisite network activation every site is set up lazily
	 * via maybe_upgrade() on its first admin request.
	 */
	public static function activate() {
		self::install();
	}

	/**
	 * Deactivation hook: removes scheduled events, keeps all data.
	 */
	public static function deactivate() {
		Scheduler::clear_all();
	}

	/**
	 * Runs the installer when the stored schema version is outdated.
	 */
	public static function maybe_upgrade() {
		if ( get_option( self::DB_VERSION_OPTION ) !== self::DB_VERSION ) {
			self::install();
		}
	}

	/**
	 * Creates or updates the log table and seeds defaults.
	 */
	private static function install() {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$table   = Logger::table();
		$charset = $wpdb->get_charset_collate();

		// dbDelta needs two spaces after PRIMARY KEY and one field per line.
		$sql = "CREATE TABLE {$table} (
			id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
			created_at datetime NOT NULL,
			post_id bigint(20) unsigned NOT NULL DEFAULT 0,
			account_id varchar(64) NOT NULL DEFAULT '',
			account_label varchar(191) NOT NULL DEFAULT '',
			network varchar(32) NOT NULL DEFAULT '',
			status varchar(16) NOT NULL DEFAULT '',
			message text NOT NULL,
			remote_url varchar(255) NOT NULL DEFAULT '',
			PRIMARY KEY  (id),
			KEY post_id (post_id),
			KEY created_at (created_at)
		) {$charset};";

		dbDelta( $sql );

		add_option( Settings::OPTION, Settings::defaults(), '', false );
		RuleRepository::seed_default_rule();

		update_option( self::DB_VERSION_OPTION, self::DB_VERSION, false );

		Scheduler::ensure_recurring_events();
	}
}
