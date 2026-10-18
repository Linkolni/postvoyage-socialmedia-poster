<?php
/**
 * Plugin bootstrap: wires all components to WordPress hooks.
 *
 * @package PostVoyage
 */

namespace PostVoyage;

use PostVoyage\Admin\AccountsPage;
use PostVoyage\Admin\LogPage;
use PostVoyage\Admin\Menu;
use PostVoyage\Admin\MetaBox;
use PostVoyage\Admin\RulesPage;
use PostVoyage\Admin\SettingsPage;
use PostVoyage\Distribution\Distributor;
use PostVoyage\Distribution\Scheduler;
use PostVoyage\Log\Logger;
use PostVoyage\Storage\AccountRepository;
use PostVoyage\Storage\PostMeta;

defined( 'ABSPATH' ) || exit;

/**
 * Central entry point. Holds shared services and registers hooks.
 */
final class Plugin {

	/**
	 * Singleton instance.
	 *
	 * @var Plugin|null
	 */
	private static $instance = null;

	/**
	 * Boots the plugin once (hooked to plugins_loaded).
	 */
	public static function boot() {
		if ( null !== self::$instance ) {
			return;
		}
		self::$instance = new self();
		self::$instance->register_hooks();
	}

	/**
	 * Capability required to manage PostVoyage.
	 *
	 * @return string
	 */
	public static function capability() {
		/**
		 * Filters the capability needed to manage accounts, rules and settings.
		 *
		 * @param string $capability Default 'manage_options'.
		 */
		return (string) apply_filters( 'postvoyage_capability', 'manage_options' );
	}

	/**
	 * Registers all hooks.
	 */
	private function register_hooks() {
		add_action( 'init', array( $this, 'load_textdomain' ) );
		add_action( 'init', array( PostMeta::class, 'register' ) );
		add_action( 'admin_init', array( Installer::class, 'maybe_upgrade' ) );

		Scheduler::register();
		Distributor::register();
		Logger::register();
		AccountRepository::register();

		if ( is_admin() ) {
			Menu::register();
			AccountsPage::register();
			RulesPage::register();
			SettingsPage::register();
			LogPage::register();
			MetaBox::register();

			add_filter( 'plugin_action_links_' . plugin_basename( POSTVOYAGE_FILE ), array( $this, 'action_links' ) );
			add_action( 'admin_init', array( $this, 'privacy_policy_content' ) );
		}
	}

	/**
	 * Loads bundled translations from /languages (wp.org language packs take precedence).
	 */
	public function load_textdomain() {
		load_plugin_textdomain( 'postvoyage-socialmedia-poster', false, dirname( plugin_basename( POSTVOYAGE_FILE ) ) . '/languages' );
	}

	/**
	 * Adds a "Settings" link on the plugins screen.
	 *
	 * @param string[] $links Existing links.
	 * @return string[]
	 */
	public function action_links( $links ) {
		$url = admin_url( 'admin.php?page=postvoyage' );
		array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Settings', 'postvoyage-socialmedia-poster' ) . '</a>' );
		return $links;
	}

	/**
	 * Suggests text for the site's privacy policy.
	 */
	public function privacy_policy_content() {
		if ( ! function_exists( 'wp_add_privacy_policy_content' ) ) {
			return;
		}
		$content = '<p>' . esc_html__( 'When a post is published, this site sends the post title, excerpt, link and featured image to the social networks configured by the site owner (for example Mastodon, Bluesky, Telegram, Discord, Facebook, Threads, LinkedIn or X). No data about visitors is transmitted.', 'postvoyage-socialmedia-poster' ) . '</p>';
		wp_add_privacy_policy_content( 'PostVoyage', wp_kses_post( $content ) );
	}
}
