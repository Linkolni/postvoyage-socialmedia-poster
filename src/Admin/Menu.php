<?php
/**
 * Admin menu and assets.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

use PostVoyage\Plugin;
use PostVoyage\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Registers the "PostVoyage" admin menu and loads CSS/JS on its pages.
 */
final class Menu {

	/**
	 * Registers hooks.
	 */
	public static function register() {
		add_action( 'admin_menu', array( self::class, 'add_pages' ) );
		add_action( 'admin_enqueue_scripts', array( self::class, 'enqueue' ) );
	}

	/**
	 * Adds the menu pages.
	 */
	public static function add_pages() {
		$cap = Plugin::capability();

		add_menu_page( 'PostVoyage', 'PostVoyage', $cap, 'postvoyage', array( AccountsPage::class, 'render' ), 'dashicons-share', 26 );
		add_submenu_page( 'postvoyage', __( 'Accounts', 'postvoyage-socialmedia-poster' ), __( 'Accounts', 'postvoyage-socialmedia-poster' ), $cap, 'postvoyage', array( AccountsPage::class, 'render' ) );
		add_submenu_page( 'postvoyage', __( 'Rules', 'postvoyage-socialmedia-poster' ), __( 'Rules', 'postvoyage-socialmedia-poster' ), $cap, 'postvoyage-rules', array( RulesPage::class, 'render' ) );
		add_submenu_page( 'postvoyage', __( 'Log', 'postvoyage-socialmedia-poster' ), __( 'Log', 'postvoyage-socialmedia-poster' ), $cap, 'postvoyage-log', array( LogPage::class, 'render' ) );
		add_submenu_page( 'postvoyage', __( 'Settings', 'postvoyage-socialmedia-poster' ), __( 'Settings', 'postvoyage-socialmedia-poster' ), $cap, 'postvoyage-settings', array( SettingsPage::class, 'render' ) );
	}

	/**
	 * Enqueues assets on PostVoyage pages and on the post editor.
	 *
	 * @param string $hook_suffix Current admin page.
	 */
	public static function enqueue( $hook_suffix ) {
		$screen    = get_current_screen();
		$is_ours   = false !== strpos( (string) $hook_suffix, 'postvoyage' );
		$is_editor = $screen && 'post' === $screen->base && in_array( $screen->post_type, Settings::post_types(), true );

		if ( ! $is_ours && ! $is_editor ) {
			return;
		}

		wp_enqueue_style( 'postvoyage-admin', POSTVOYAGE_URL . 'assets/css/admin.css', array(), POSTVOYAGE_VERSION );
		wp_enqueue_script( 'postvoyage-admin', POSTVOYAGE_URL . 'assets/js/admin.js', array(), POSTVOYAGE_VERSION, array( 'in_footer' => true ) );
		wp_localize_script(
			'postvoyage-admin',
			'postvoyageAdmin',
			array(
				'ajaxUrl' => admin_url( 'admin-ajax.php' ),
				'i18n'    => array(
					'sharing'       => __( 'Sharing…', 'postvoyage-socialmedia-poster' ),
					'done'          => __( 'Done.', 'postvoyage-socialmedia-poster' ),
					'nothing'       => __( 'Nothing was shared. Check the rules and accounts.', 'postvoyage-socialmedia-poster' ),
					'error'         => __( 'Request failed.', 'postvoyage-socialmedia-poster' ),
					'view'          => __( 'View', 'postvoyage-socialmedia-poster' ),
					'confirmDelete' => __( 'Really delete?', 'postvoyage-socialmedia-poster' ),
				),
			)
		);
	}
}
