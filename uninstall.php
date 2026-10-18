<?php
/**
 * Uninstall: removes all data if the user opted in under Settings → Uninstall.
 *
 * @package PostVoyage
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

/**
 * Removes PostVoyage data of the current site.
 */
function postvoyage_uninstall_site() {
	global $wpdb;

	$settings = get_option( 'postvoyage_settings', array() );
	if ( empty( $settings['delete_on_uninstall'] ) ) {
		return;
	}

	foreach ( array( 'postvoyage_settings', 'postvoyage_accounts', 'postvoyage_rules', 'postvoyage_db_version' ) as $option ) {
		delete_option( $option );
	}

	foreach ( array( '_postvoyage_enabled', '_postvoyage_message', '_postvoyage_shares' ) as $meta_key ) {
		delete_post_meta_by_key( $meta_key );
	}
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Lock keys have dynamic suffixes.
	$wpdb->query( $wpdb->prepare( "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s", $wpdb->esc_like( '_postvoyage_lock_' ) . '%' ) );

	$table = $wpdb->prefix . 'postvoyage_log';
	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery.SchemaChange -- Dropping own table.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" );
}

if ( is_multisite() ) {
	foreach ( get_sites( array( 'fields' => 'ids' ) ) as $postvoyage_site_id ) {
		switch_to_blog( $postvoyage_site_id );
		postvoyage_uninstall_site();
		restore_current_blog();
	}
} else {
	postvoyage_uninstall_site();
}
