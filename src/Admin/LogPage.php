<?php
/**
 * Log admin page.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

use PostVoyage\Log\Logger;
use PostVoyage\Plugin;

defined( 'ABSPATH' ) || exit;

/**
 * Shows the share log.
 */
final class LogPage {

	const SLUG = 'postvoyage-log';

	/**
	 * Registers hooks.
	 */
	public static function register() {
		add_action( 'admin_post_postvoyage_clear_log', array( self::class, 'handle_clear' ) );
	}

	/**
	 * Renders the page.
	 */
	public static function render() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		$table = new LogListTable();
		$table->prepare_items();
		?>
		<div class="wrap postvoyage">
			<h1 class="wp-heading-inline"><?php esc_html_e( 'Log', 'postvoyage-socialmedia-poster' ); ?></h1>
			<a class="page-title-action postvoyage-delete" href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin-post.php?action=postvoyage_clear_log' ), 'postvoyage_clear_log' ) ); ?>"><?php esc_html_e( 'Clear log', 'postvoyage-socialmedia-poster' ); ?></a>
			<hr class="wp-header-end">
			<?php Notices::render(); ?>
			<?php $table->views(); ?>
			<form method="get">
				<input type="hidden" name="page" value="<?php echo esc_attr( self::SLUG ); ?>">
				<?php $table->display(); ?>
			</form>
		</div>
		<?php
	}

	/**
	 * Clears the log.
	 */
	public static function handle_clear() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'postvoyage-socialmedia-poster' ), 403 );
		}
		check_admin_referer( 'postvoyage_clear_log' );
		Logger::clear();
		Notices::add( 'success', __( 'Log cleared.', 'postvoyage-socialmedia-poster' ) );
		wp_safe_redirect( admin_url( 'admin.php?page=' . self::SLUG ) );
		exit;
	}
}
