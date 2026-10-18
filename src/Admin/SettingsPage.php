<?php
/**
 * Settings admin page.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

use PostVoyage\Content\Language;
use PostVoyage\Plugin;
use PostVoyage\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * General settings via the Settings API.
 */
final class SettingsPage {

	const GROUP = 'postvoyage_settings_group';

	/**
	 * Registers hooks.
	 */
	public static function register() {
		add_action( 'admin_init', array( self::class, 'register_setting' ) );
		add_filter( 'option_page_capability_' . self::GROUP, array( Plugin::class, 'capability' ) );
	}

	/**
	 * Registers the option.
	 */
	public static function register_setting() {
		register_setting(
			self::GROUP,
			Settings::OPTION,
			array(
				'type'              => 'array',
				'sanitize_callback' => array( Settings::class, 'sanitize' ),
				'default'           => Settings::defaults(),
				'show_in_rest'      => false,
			)
		);
	}

	/**
	 * Renders the page.
	 */
	public static function render() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		$settings   = Settings::all();
		$name       = Settings::OPTION;
		$post_types = get_post_types( array( 'public' => true ), 'objects' );
		unset( $post_types['attachment'] );
		?>
		<div class="wrap postvoyage">
			<h1><?php esc_html_e( 'PostVoyage settings', 'postvoyage-socialmedia-poster' ); ?></h1>
			<?php settings_errors(); ?>

			<form method="post" action="options.php" class="postvoyage-form">
				<?php settings_fields( self::GROUP ); ?>

				<h2><?php esc_html_e( 'Publishing', 'postvoyage-socialmedia-poster' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'Post types', 'postvoyage-socialmedia-poster' ); ?></th>
						<td>
							<fieldset>
								<?php foreach ( $post_types as $slug => $object ) : ?>
									<label class="postvoyage-check"><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, (array) $settings['post_types'], true ) ); ?>> <?php echo esc_html( $object->labels->name ); ?></label>
								<?php endforeach; ?>
							</fieldset>
							<p class="description"><?php esc_html_e( 'PostVoyage only reacts to these post types.', 'postvoyage-socialmedia-poster' ); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Default for new posts', 'postvoyage-socialmedia-poster' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[default_enabled]" value="1" <?php checked( $settings['default_enabled'] ); ?>> <?php esc_html_e( 'Share automatically on publish (can be changed per post)', 'postvoyage-socialmedia-poster' ); ?></label></td>
					</tr>
					<tr>
						<th scope="row"><label for="postvoyage-delay"><?php esc_html_e( 'Delay', 'postvoyage-socialmedia-poster' ); ?></label></th>
						<td>
							<input type="number" min="0" max="86400" id="postvoyage-delay" class="small-text" name="<?php echo esc_attr( $name ); ?>[delay]" value="<?php echo esc_attr( (string) $settings['delay'] ); ?>"> <?php esc_html_e( 'seconds', 'postvoyage-socialmedia-poster' ); ?>
							<p class="description"><?php esc_html_e( 'Sharing runs in the background after this delay. At least 30 seconds are recommended so that settings in the editor are saved and caches are warmed up.', 'postvoyage-socialmedia-poster' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Tracking', 'postvoyage-socialmedia-poster' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><?php esc_html_e( 'UTM parameters', 'postvoyage-socialmedia-poster' ); ?></th>
						<td>
							<label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[utm_enabled]" value="1" <?php checked( $settings['utm_enabled'] ); ?>> <?php esc_html_e( 'Add UTM parameters to {url}', 'postvoyage-socialmedia-poster' ); ?></label>
							<p>
								<label>utm_source <input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[utm_source]" value="<?php echo esc_attr( $settings['utm_source'] ); ?>"></label><br>
								<label>utm_medium <input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[utm_medium]" value="<?php echo esc_attr( $settings['utm_medium'] ); ?>"></label><br>
								<label>utm_campaign <input type="text" class="regular-text" name="<?php echo esc_attr( $name ); ?>[utm_campaign]" value="<?php echo esc_attr( $settings['utm_campaign'] ); ?>"></label>
							</p>
							<p class="description"><?php esc_html_e( '{network} is replaced with the network, e.g. "mastodon".', 'postvoyage-socialmedia-poster' ); ?></p>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e( 'Maintenance', 'postvoyage-socialmedia-poster' ); ?></h2>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="postvoyage-retention"><?php esc_html_e( 'Keep log for', 'postvoyage-socialmedia-poster' ); ?></label></th>
						<td><input type="number" min="1" id="postvoyage-retention" class="small-text" name="<?php echo esc_attr( $name ); ?>[log_retention_days]" value="<?php echo esc_attr( (string) $settings['log_retention_days'] ); ?>"> <?php esc_html_e( 'days', 'postvoyage-socialmedia-poster' ); ?></td>
					</tr>
					<tr>
						<th scope="row"><?php esc_html_e( 'Uninstall', 'postvoyage-socialmedia-poster' ); ?></th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $name ); ?>[delete_on_uninstall]" value="1" <?php checked( $settings['delete_on_uninstall'] ); ?>> <?php esc_html_e( 'Delete all PostVoyage data (accounts, rules, log) when the plugin is deleted', 'postvoyage-socialmedia-poster' ); ?></label></td>
					</tr>
				</table>

				<?php submit_button(); ?>
			</form>

			<h2><?php esc_html_e( 'System', 'postvoyage-socialmedia-poster' ); ?></h2>
			<table class="widefat striped postvoyage-system">
				<tbody>
					<tr>
						<td><?php esc_html_e( 'Background processing', 'postvoyage-socialmedia-poster' ); ?></td>
						<td><?php echo function_exists( 'as_schedule_single_action' ) ? esc_html__( 'Action Scheduler', 'postvoyage-socialmedia-poster' ) : 'WP-Cron'; ?></td>
					</tr>
					<tr>
						<td>DISABLE_WP_CRON</td>
						<td>
							<?php
							if ( defined( 'DISABLE_WP_CRON' ) && DISABLE_WP_CRON ) {
								esc_html_e( 'Yes – make sure a server cron job calls wp-cron.php regularly.', 'postvoyage-socialmedia-poster' );
							} else {
								esc_html_e( 'No', 'postvoyage-socialmedia-poster' );
							}
							?>
						</td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Multilingual plugin', 'postvoyage-socialmedia-poster' ); ?></td>
						<td><?php echo Language::available() ? esc_html( implode( ', ', Language::available() ) ) : esc_html__( 'None detected (Polylang and WPML are supported)', 'postvoyage-socialmedia-poster' ); ?></td>
					</tr>
					<tr>
						<td><?php esc_html_e( 'Version', 'postvoyage-socialmedia-poster' ); ?></td>
						<td><?php echo esc_html( POSTVOYAGE_VERSION ); ?></td>
					</tr>
				</tbody>
			</table>
		</div>
		<?php
	}
}
