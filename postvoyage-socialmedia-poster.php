<?php
/**
 * Plugin Name:       PostVoyage - Free Social Media Post Distributor
 * Description:       Shares your posts automatically on Mastodon, Bluesky, Telegram, Discord, Facebook, Threads, LinkedIn, X and any webhook. Free, with rules per category and templates with variables.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      7.4
 * Author:            Alexander Volland
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       postvoyage-socialmedia-poster
 * Domain Path:       /languages
 *
 * @package PostVoyage
 */

defined( 'ABSPATH' ) || exit;

define( 'POSTVOYAGE_VERSION', '0.1.0' );
define( 'POSTVOYAGE_FILE', __FILE__ );
define( 'POSTVOYAGE_DIR', plugin_dir_path( __FILE__ ) );
define( 'POSTVOYAGE_URL', plugin_dir_url( __FILE__ ) );

/*
 * PSR-4 autoloader for the PostVoyage namespace (src/). No Composer needed at runtime,
 * so the plugin works when copied or installed from a ZIP.
 */
spl_autoload_register(
	static function ( $class_name ) {
		$prefix = 'PostVoyage\\';
		if ( 0 !== strpos( $class_name, $prefix ) ) {
			return;
		}
		$relative = str_replace( '\\', '/', substr( $class_name, strlen( $prefix ) ) );
		$file     = POSTVOYAGE_DIR . 'src/' . $relative . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);

register_activation_hook( __FILE__, array( \PostVoyage\Installer::class, 'activate' ) );
register_deactivation_hook( __FILE__, array( \PostVoyage\Installer::class, 'deactivate' ) );

add_action( 'plugins_loaded', array( \PostVoyage\Plugin::class, 'boot' ) );
