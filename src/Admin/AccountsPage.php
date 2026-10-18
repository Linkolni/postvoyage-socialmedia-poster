<?php
/**
 * Accounts admin page.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

use PostVoyage\Networks\Registry;
use PostVoyage\Plugin;
use PostVoyage\Storage\Account;
use PostVoyage\Storage\AccountRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Lists, adds, edits, tests and deletes accounts.
 */
final class AccountsPage {

	const SLUG = 'postvoyage';

	/**
	 * Registers form handlers.
	 */
	public static function register() {
		add_action( 'admin_post_postvoyage_save_account', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_postvoyage_delete_account', array( self::class, 'handle_delete' ) );
		add_action( 'admin_post_postvoyage_test_account', array( self::class, 'handle_test' ) );
	}

	/**
	 * Renders the page.
	 */
	public static function render() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			return;
		}
		// phpcs:disable WordPress.Security.NonceVerification.Recommended -- Read-only view switch.
		$action = isset( $_GET['action'] ) ? sanitize_key( wp_unslash( $_GET['action'] ) ) : '';
		$id     = isset( $_GET['account'] ) ? sanitize_key( wp_unslash( $_GET['account'] ) ) : '';
		// phpcs:enable

		echo '<div class="wrap postvoyage">';
		Notices::render();

		if ( 'new' === $action || 'edit' === $action ) {
			$account = 'edit' === $action ? AccountRepository::find( $id ) : null;
			self::render_form( $account );
		} else {
			self::render_list();
		}

		echo '</div>';
	}

	/**
	 * Account list.
	 */
	private static function render_list() {
		$accounts = AccountRepository::all();
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'PostVoyage accounts', 'postvoyage-socialmedia-poster' ); ?></h1>
		<a href="<?php echo esc_url( self::url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add account', 'postvoyage-socialmedia-poster' ); ?></a>
		<hr class="wp-header-end">

		<p class="description"><?php esc_html_e( 'Connect the profiles, pages and channels your posts should be shared to. Then decide in "Rules" which posts go where.', 'postvoyage-socialmedia-poster' ); ?></p>

		<?php if ( ! $accounts ) : ?>
			<div class="postvoyage-empty">
				<p><?php esc_html_e( 'No accounts connected yet.', 'postvoyage-socialmedia-poster' ); ?></p>
				<p><a class="button button-primary" href="<?php echo esc_url( self::url( array( 'action' => 'new' ) ) ); ?>"><?php esc_html_e( 'Connect your first account', 'postvoyage-socialmedia-poster' ); ?></a></p>
			</div>
			<?php
			return;
		endif;
		?>

		<table class="widefat striped postvoyage-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'postvoyage-socialmedia-poster' ); ?></th>
					<th><?php esc_html_e( 'Network', 'postvoyage-socialmedia-poster' ); ?></th>
					<th><?php esc_html_e( 'Status', 'postvoyage-socialmedia-poster' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'postvoyage-socialmedia-poster' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php foreach ( $accounts as $account ) : ?>
				<?php $network = Registry::get( $account->network ); ?>
				<tr>
					<td><strong><a href="
					<?php
					echo esc_url(
						self::url(
							array(
								'action'  => 'edit',
								'account' => $account->id,
							)
						)
					);
					?>
											"><?php echo esc_html( $account->label ); ?></a></strong></td>
					<td><span class="postvoyage-network postvoyage-network--<?php echo esc_attr( $account->network ); ?>"><?php echo esc_html( $network ? $network->label() : $account->network ); ?></span></td>
					<td>
						<?php if ( ! $network ) : ?>
							<span class="postvoyage-status postvoyage-status--error"><?php esc_html_e( 'Network missing', 'postvoyage-socialmedia-poster' ); ?></span>
						<?php elseif ( $account->enabled ) : ?>
							<span class="postvoyage-status postvoyage-status--success"><?php esc_html_e( 'Active', 'postvoyage-socialmedia-poster' ); ?></span>
						<?php else : ?>
							<span class="postvoyage-status"><?php esc_html_e( 'Paused', 'postvoyage-socialmedia-poster' ); ?></span>
						<?php endif; ?>
					</td>
					<td class="postvoyage-actions">
						<a href="
						<?php
						echo esc_url(
							self::url(
								array(
									'action'  => 'edit',
									'account' => $account->id,
								)
							)
						);
						?>
									"><?php esc_html_e( 'Edit', 'postvoyage-socialmedia-poster' ); ?></a>
						| <a href="<?php echo esc_url( self::action_url( 'postvoyage_test_account', $account->id ) ); ?>"><?php esc_html_e( 'Test connection', 'postvoyage-socialmedia-poster' ); ?></a>
						| <a class="postvoyage-delete" href="<?php echo esc_url( self::action_url( 'postvoyage_delete_account', $account->id ) ); ?>"><?php esc_html_e( 'Delete', 'postvoyage-socialmedia-poster' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Add/edit form. All networks' fields are rendered; JavaScript shows the selected one.
	 *
	 * @param Account|null $account Account when editing.
	 */
	private static function render_form( $account ) {
		$networks = Registry::all();
		$current  = $account ? $account->network : (string) key( $networks );
		?>
		<h1><?php echo $account ? esc_html__( 'Edit account', 'postvoyage-socialmedia-poster' ) : esc_html__( 'Add account', 'postvoyage-socialmedia-poster' ); ?></h1>
		<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php esc_html_e( 'Back to accounts', 'postvoyage-socialmedia-poster' ); ?></a></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="postvoyage-form" data-postvoyage-networks>
			<input type="hidden" name="action" value="postvoyage_save_account">
			<?php if ( $account ) : ?>
				<input type="hidden" name="account" value="<?php echo esc_attr( $account->id ); ?>">
			<?php endif; ?>
			<?php wp_nonce_field( 'postvoyage_save_account' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="postvoyage-network"><?php esc_html_e( 'Network', 'postvoyage-socialmedia-poster' ); ?></label></th>
					<td>
						<select id="postvoyage-network" name="postvoyage[network]" <?php disabled( (bool) $account ); ?>>
							<?php foreach ( $networks as $id => $network ) : ?>
								<option value="<?php echo esc_attr( $id ); ?>" <?php selected( $current, $id ); ?>><?php echo esc_html( $network->label() ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="postvoyage-label"><?php esc_html_e( 'Name', 'postvoyage-socialmedia-poster' ); ?></label></th>
					<td>
						<input type="text" id="postvoyage-label" class="regular-text" name="postvoyage[label]" value="<?php echo esc_attr( $account ? $account->label : '' ); ?>">
						<p class="description"><?php esc_html_e( 'Your own name for this account, e.g. "Blog channel".', 'postvoyage-socialmedia-poster' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'postvoyage-socialmedia-poster' ); ?></th>
					<td><label><input type="checkbox" name="postvoyage[enabled]" value="1" <?php checked( $account ? $account->enabled : true ); ?>> <?php esc_html_e( 'Active', 'postvoyage-socialmedia-poster' ); ?></label></td>
				</tr>
			</table>

			<?php foreach ( $networks as $id => $network ) : ?>
				<fieldset class="postvoyage-network-fields" data-network="<?php echo esc_attr( $id ); ?>" <?php disabled( $id !== $current ); ?> <?php echo $id !== $current ? 'hidden' : ''; ?>>
					<?php if ( '' !== $network->help() ) : ?>
						<div class="postvoyage-help"><span class="dashicons dashicons-info-outline" aria-hidden="true"></span> <?php echo esc_html( $network->help() ); ?></div>
					<?php endif; ?>
					<table class="form-table" role="presentation">
						<?php
						foreach ( $network->fields() as $key => $field ) {
							$value = ( $account && $account->network === $id ) ? $account->get( $key, null ) : null;
							self::render_field( $id, $key, $field, $value, (bool) $account );
						}
						?>
					</table>
				</fieldset>
			<?php endforeach; ?>

			<?php submit_button( __( 'Save and test connection', 'postvoyage-socialmedia-poster' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Renders one field.
	 *
	 * @param string $network_id Network ID.
	 * @param string $key        Field key.
	 * @param array  $field      Definition.
	 * @param mixed  $value      Current value (null = not set).
	 * @param bool   $editing    Whether an existing account is edited.
	 */
	private static function render_field( $network_id, $key, array $field, $value, $editing ) {
		$id    = 'postvoyage-' . $network_id . '-' . $key;
		$name  = 'postvoyage[values][' . $network_id . '][' . $key . ']';
		$value = null === $value ? $field['default'] : $value;
		?>
		<tr>
			<th scope="row">
				<label for="<?php echo esc_attr( $id ); ?>"><?php echo esc_html( $field['label'] ); ?></label>
				<?php if ( $field['required'] ) : ?>
					<span class="postvoyage-required" aria-hidden="true">*</span>
				<?php endif; ?>
			</th>
			<td>
				<?php
				switch ( $field['type'] ) {
					case 'checkbox':
						printf( '<input type="checkbox" id="%1$s" name="%2$s" value="1" %3$s>', esc_attr( $id ), esc_attr( $name ), checked( (bool) $value, true, false ) );
						break;
					case 'select':
						printf( '<select id="%1$s" name="%2$s">', esc_attr( $id ), esc_attr( $name ) );
						foreach ( $field['options'] as $option => $label ) {
							printf( '<option value="%1$s" %2$s>%3$s</option>', esc_attr( $option ), selected( (string) $value, (string) $option, false ), esc_html( $label ) );
						}
						echo '</select>';
						break;
					case 'password':
						$has_value   = $editing && '' !== (string) $value && $field['secret'];
						$placeholder = $has_value ? __( '•••••••• saved – leave empty to keep', 'postvoyage-socialmedia-poster' ) : '';
						printf(
							'<input type="password" id="%1$s" name="%2$s" value="" class="regular-text" autocomplete="new-password" placeholder="%3$s">',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( $placeholder )
						);
						break;
					case 'number':
						printf( '<input type="number" min="0" id="%1$s" name="%2$s" value="%3$s" class="small-text">', esc_attr( $id ), esc_attr( $name ), esc_attr( (string) $value ) );
						break;
					default:
						printf(
							'<input type="%1$s" id="%2$s" name="%3$s" value="%4$s" class="regular-text">',
							'url' === $field['type'] ? 'url' : 'text',
							esc_attr( $id ),
							esc_attr( $name ),
							esc_attr( (string) $value )
						);
				}
				if ( '' !== $field['description'] ) {
					echo '<p class="description">' . esc_html( $field['description'] ) . '</p>';
				}
				?>
			</td>
		</tr>
		<?php
	}

	/**
	 * Saves an account, then tests the connection.
	 */
	public static function handle_save() {
		check_admin_referer( 'postvoyage_save_account' );
		self::guard();

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized per field in AccountRepository::from_input().
		$input    = isset( $_POST['postvoyage'] ) && is_array( $_POST['postvoyage'] ) ? wp_unslash( $_POST['postvoyage'] ) : array();
		$existing = isset( $_POST['account'] ) ? AccountRepository::find( sanitize_key( wp_unslash( $_POST['account'] ) ) ) : null;
		$network  = $existing ? $existing->network : sanitize_key( (string) ( $input['network'] ?? '' ) );

		$account = AccountRepository::from_input(
			$network,
			array(
				'label'   => $input['label'] ?? '',
				'enabled' => $input['enabled'] ?? '',
				'values'  => $input['values'][ $network ] ?? array(),
			),
			$existing
		);

		if ( is_wp_error( $account ) ) {
			Notices::add( 'error', $account->get_error_message() );
			wp_safe_redirect(
				self::url(
					$existing ? array(
						'action'  => 'edit',
						'account' => $existing->id,
					) : array( 'action' => 'new' )
				)
			);
			exit;
		}

		AccountRepository::save( $account );
		Notices::add( 'success', __( 'Account saved.', 'postvoyage-socialmedia-poster' ) );
		self::add_test_notice( $account );

		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Tests the connection of an account.
	 */
	public static function handle_test() {
		check_admin_referer( 'postvoyage_test_account' );
		self::guard();
		$account = AccountRepository::find( isset( $_GET['account'] ) ? sanitize_key( wp_unslash( $_GET['account'] ) ) : '' );
		if ( $account ) {
			self::add_test_notice( $account );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Deletes an account.
	 */
	public static function handle_delete() {
		check_admin_referer( 'postvoyage_delete_account' );
		self::guard();
		$id = isset( $_GET['account'] ) ? sanitize_key( wp_unslash( $_GET['account'] ) ) : '';
		AccountRepository::delete( $id );
		Notices::add( 'success', __( 'Account deleted.', 'postvoyage-socialmedia-poster' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Runs the connection test and adds the result as notice.
	 *
	 * @param Account $account Account.
	 */
	private static function add_test_notice( Account $account ) {
		$network = Registry::get( $account->network );
		if ( ! $network ) {
			return;
		}
		$result = $network->test( $account );
		/* translators: 1: account name, 2: result */
		$text = sprintf( __( 'Connection test "%1$s": %2$s', 'postvoyage-socialmedia-poster' ), $account->label, $result->message );
		Notices::add( $result->success ? 'success' : 'error', $text );
	}

	/**
	 * Stops unless the user may manage PostVoyage.
	 */
	private static function guard() {
		if ( ! current_user_can( Plugin::capability() ) ) {
			wp_die( esc_html__( 'You are not allowed to do this.', 'postvoyage-socialmedia-poster' ), 403 );
		}
	}

	/**
	 * Page URL.
	 *
	 * @param array $args Query args.
	 * @return string
	 */
	private static function url( array $args = array() ) {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * Nonce-protected admin-post URL.
	 *
	 * @param string $action     Action.
	 * @param string $account_id Account ID.
	 * @return string
	 */
	private static function action_url( $action, $account_id ) {
		return wp_nonce_url(
			add_query_arg(
				array(
					'action'  => $action,
					'account' => $account_id,
				),
				admin_url( 'admin-post.php' )
			),
			$action
		);
	}
}
