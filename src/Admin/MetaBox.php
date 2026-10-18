<?php
/**
 * Post editor box.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

use PostVoyage\Distribution\Distributor;
use PostVoyage\Distribution\Scheduler;
use PostVoyage\Plugin;
use PostVoyage\Storage\AccountRepository;
use PostVoyage\Storage\PostMeta;
use PostVoyage\Storage\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Side box in the block and classic editor: switch sharing on/off, custom text,
 * share history and "Share now".
 */
final class MetaBox {

	const NONCE = 'postvoyage_meta_box';

	/**
	 * Registers hooks.
	 */
	public static function register() {
		add_action( 'add_meta_boxes', array( self::class, 'add' ) );
		add_action( 'save_post', array( self::class, 'save' ), 10, 2 );
		add_action( 'wp_ajax_postvoyage_share_now', array( self::class, 'ajax_share_now' ) );
	}

	/**
	 * Adds the box to enabled post types.
	 *
	 * @param string $post_type Post type.
	 */
	public static function add( $post_type ) {
		if ( ! in_array( $post_type, Settings::post_types(), true ) ) {
			return;
		}
		add_meta_box( 'postvoyage', 'PostVoyage', array( self::class, 'render' ), $post_type, 'side', 'default' );
	}

	/**
	 * Renders the box.
	 *
	 * @param WP_Post $post Post.
	 */
	public static function render( $post ) {
		$accounts  = AccountRepository::enabled();
		$shares    = PostMeta::shares( $post->ID );
		$published = 'publish' === $post->post_status;

		wp_nonce_field( self::NONCE, self::NONCE );
		?>
		<div class="postvoyage-box">
			<?php if ( ! $accounts ) : ?>
				<p>
					<?php esc_html_e( 'No active accounts.', 'postvoyage-socialmedia-poster' ); ?>
					<?php if ( current_user_can( Plugin::capability() ) ) : ?>
						<a href="<?php echo esc_url( admin_url( 'admin.php?page=postvoyage&action=new' ) ); ?>"><?php esc_html_e( 'Add account', 'postvoyage-socialmedia-poster' ); ?></a>
					<?php endif; ?>
				</p>
			<?php endif; ?>

			<?php if ( ! $published ) : ?>
				<p>
					<input type="hidden" name="postvoyage_enabled" value="0">
					<label><input type="checkbox" name="postvoyage_enabled" value="1" <?php checked( PostMeta::is_enabled( $post->ID ) ); ?>> <?php esc_html_e( 'Share on publish', 'postvoyage-socialmedia-poster' ); ?></label>
				</p>
			<?php endif; ?>

			<p>
				<label for="postvoyage-message"><?php esc_html_e( 'Custom text (optional)', 'postvoyage-socialmedia-poster' ); ?></label>
				<textarea id="postvoyage-message" name="postvoyage_message" rows="4" class="widefat" placeholder="<?php esc_attr_e( 'Empty = rule template. Variables like {title} {url} {hashtags} work here too.', 'postvoyage-socialmedia-poster' ); ?>"><?php echo esc_textarea( PostMeta::message( $post->ID ) ); ?></textarea>
			</p>

			<?php if ( $published && Scheduler::is_queued( $post->ID ) ) : ?>
				<p class="postvoyage-muted"><?php esc_html_e( 'Sharing is queued and runs in the background shortly.', 'postvoyage-socialmedia-poster' ); ?></p>
			<?php endif; ?>

			<?php if ( $shares ) : ?>
				<ul class="postvoyage-shares">
					<?php foreach ( $shares as $account_id => $share ) : ?>
						<?php $label = isset( $accounts[ $account_id ] ) ? $accounts[ $account_id ]->label : $account_id; ?>
						<li>
							<span class="postvoyage-dot postvoyage-dot--<?php echo esc_attr( 'success' === $share['status'] ? 'success' : 'error' ); ?>" aria-hidden="true"></span>
							<?php if ( ! empty( $share['url'] ) ) : ?>
								<a href="<?php echo esc_url( $share['url'] ); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html( $label ); ?></a>
							<?php else : ?>
								<?php echo esc_html( $label ); ?>
							<?php endif; ?>
							<span class="postvoyage-muted"><?php echo esc_html( 'success' === $share['status'] ? __( 'shared', 'postvoyage-socialmedia-poster' ) : __( 'failed – see log', 'postvoyage-socialmedia-poster' ) ); ?></span>
						</li>
					<?php endforeach; ?>
				</ul>
			<?php endif; ?>

			<?php if ( $published && $accounts ) : ?>
				<details class="postvoyage-share-now">
					<summary><?php esc_html_e( 'Share now', 'postvoyage-socialmedia-poster' ); ?></summary>
					<fieldset>
						<?php foreach ( $accounts as $account ) : ?>
							<label class="postvoyage-check"><input type="checkbox" class="postvoyage-share-account" value="<?php echo esc_attr( $account->id ); ?>"> <?php echo esc_html( $account->label ); ?></label>
						<?php endforeach; ?>
					</fieldset>
					<p class="description"><?php esc_html_e( 'None selected = accounts from the matching rules. Saves nothing – save your changes first.', 'postvoyage-socialmedia-poster' ); ?></p>
					<button type="button" class="button" data-postvoyage-share-now data-post="<?php echo esc_attr( (string) $post->ID ); ?>" data-nonce="<?php echo esc_attr( wp_create_nonce( 'postvoyage_share_now_' . $post->ID ) ); ?>"><?php esc_html_e( 'Share now', 'postvoyage-socialmedia-poster' ); ?></button>
					<div class="postvoyage-share-result" aria-live="polite"></div>
				</details>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Saves the box values.
	 *
	 * @param int     $post_id Post ID.
	 * @param WP_Post $post    Post.
	 */
	public static function save( $post_id, $post ) {
		if ( ! isset( $_POST[ self::NONCE ] ) || ! wp_verify_nonce( sanitize_key( wp_unslash( $_POST[ self::NONCE ] ) ), self::NONCE ) ) {
			return;
		}
		if ( ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) || wp_is_post_revision( $post_id ) || ! current_user_can( 'edit_post', $post_id ) ) {
			return;
		}
		if ( ! in_array( $post->post_type, Settings::post_types(), true ) ) {
			return;
		}

		if ( isset( $_POST['postvoyage_enabled'] ) ) {
			update_post_meta( $post_id, PostMeta::ENABLED, '1' === $_POST['postvoyage_enabled'] ? '1' : '0' );
		}

		$message = isset( $_POST['postvoyage_message'] ) ? sanitize_textarea_field( wp_unslash( $_POST['postvoyage_message'] ) ) : '';
		if ( '' === trim( $message ) ) {
			delete_post_meta( $post_id, PostMeta::MESSAGE );
		} else {
			update_post_meta( $post_id, PostMeta::MESSAGE, $message );
		}
	}

	/**
	 * AJAX: share a published post immediately.
	 */
	public static function ajax_share_now() {
		$post_id = isset( $_POST['post_id'] ) ? absint( $_POST['post_id'] ) : 0;
		check_ajax_referer( 'postvoyage_share_now_' . $post_id, 'nonce' );

		if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) || ! current_user_can( 'publish_posts' ) ) {
			wp_send_json_error( array( 'message' => __( 'You are not allowed to do this.', 'postvoyage-socialmedia-poster' ) ), 403 );
		}

		$account_ids = isset( $_POST['accounts'] ) ? array_map( 'sanitize_key', (array) wp_unslash( $_POST['accounts'] ) ) : array();
		$accounts    = AccountRepository::all();
		$results     = Distributor::distribute( $post_id, $account_ids, true );

		$out = array();
		foreach ( $results as $account_id => $result ) {
			$out[] = array(
				'account' => isset( $accounts[ $account_id ] ) ? $accounts[ $account_id ]->label : $account_id,
				'success' => $result->success,
				'message' => $result->message,
				'url'     => $result->remote_url,
			);
		}

		wp_send_json_success( array( 'results' => $out ) );
	}
}
