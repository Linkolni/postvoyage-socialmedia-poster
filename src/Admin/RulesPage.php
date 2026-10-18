<?php
/**
 * Rules admin page.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Admin;

use PostVoyage\Content\Language;
use PostVoyage\Content\TemplateEngine;
use PostVoyage\Networks\Registry;
use PostVoyage\Plugin;
use PostVoyage\Storage\AccountRepository;
use PostVoyage\Storage\RuleRepository;
use PostVoyage\Storage\Settings;

defined( 'ABSPATH' ) || exit;

/**
 * Lists and edits distribution rules.
 */
final class RulesPage {

	const SLUG = 'postvoyage-rules';

	/**
	 * Registers form handlers.
	 */
	public static function register() {
		add_action( 'admin_post_postvoyage_save_rule', array( self::class, 'handle_save' ) );
		add_action( 'admin_post_postvoyage_delete_rule', array( self::class, 'handle_delete' ) );
		add_action( 'admin_post_postvoyage_move_rule', array( self::class, 'handle_move' ) );
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
		$id     = isset( $_GET['rule'] ) ? sanitize_key( wp_unslash( $_GET['rule'] ) ) : '';
		// phpcs:enable

		echo '<div class="wrap postvoyage">';
		Notices::render();

		if ( 'new' === $action ) {
			self::render_form( RuleRepository::blank() );
		} elseif ( 'edit' === $action && RuleRepository::find( $id ) ) {
			self::render_form( RuleRepository::find( $id ) );
		} else {
			self::render_list();
		}

		echo '</div>';
	}

	/**
	 * Rule list.
	 */
	private static function render_list() {
		$rules    = RuleRepository::all();
		$accounts = AccountRepository::all();
		$count    = count( $rules );
		$position = 0;
		?>
		<h1 class="wp-heading-inline"><?php esc_html_e( 'Rules', 'postvoyage-socialmedia-poster' ); ?></h1>
		<a href="<?php echo esc_url( self::url( array( 'action' => 'new' ) ) ); ?>" class="page-title-action"><?php esc_html_e( 'Add rule', 'postvoyage-socialmedia-poster' ); ?></a>
		<hr class="wp-header-end">

		<p class="description"><?php esc_html_e( 'A rule defines which posts (post type, category, language) are shared to which accounts and with which text. If several rules reach the same account, the topmost one wins.', 'postvoyage-socialmedia-poster' ); ?></p>

		<?php if ( ! $accounts ) : ?>
			<div class="notice notice-warning inline"><p>
				<?php esc_html_e( 'No accounts connected yet.', 'postvoyage-socialmedia-poster' ); ?>
				<a href="<?php echo esc_url( admin_url( 'admin.php?page=postvoyage&action=new' ) ); ?>"><?php esc_html_e( 'Add account', 'postvoyage-socialmedia-poster' ); ?></a>
			</p></div>
		<?php endif; ?>

		<table class="widefat striped postvoyage-table">
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'postvoyage-socialmedia-poster' ); ?></th>
					<th><?php esc_html_e( 'Categories', 'postvoyage-socialmedia-poster' ); ?></th>
					<th><?php esc_html_e( 'Accounts', 'postvoyage-socialmedia-poster' ); ?></th>
					<th><?php esc_html_e( 'Status', 'postvoyage-socialmedia-poster' ); ?></th>
					<th><?php esc_html_e( 'Actions', 'postvoyage-socialmedia-poster' ); ?></th>
				</tr>
			</thead>
			<tbody>
			<?php if ( ! $rules ) : ?>
				<tr><td colspan="5"><?php esc_html_e( 'No rules yet. Without a rule nothing is shared automatically.', 'postvoyage-socialmedia-poster' ); ?></td></tr>
			<?php endif; ?>
			<?php foreach ( $rules as $rule ) : ?>
				<?php ++$position; ?>
				<tr>
					<td><strong><a href="
					<?php
					echo esc_url(
						self::url(
							array(
								'action' => 'edit',
								'rule'   => $rule['id'],
							)
						)
					);
					?>
											"><?php echo esc_html( $rule['name'] ); ?></a></strong></td>
					<td><?php echo esc_html( self::category_summary( $rule ) ); ?></td>
					<td><?php echo esc_html( self::account_summary( $rule, $accounts ) ); ?></td>
					<td>
						<?php if ( $rule['enabled'] ) : ?>
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
									'action' => 'edit',
									'rule'   => $rule['id'],
								)
							)
						);
						?>
									"><?php esc_html_e( 'Edit', 'postvoyage-socialmedia-poster' ); ?></a>
						<?php if ( $position > 1 ) : ?>
							| <a href="<?php echo esc_url( self::action_url( 'postvoyage_move_rule', $rule['id'], array( 'direction' => 'up' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Move up', 'postvoyage-socialmedia-poster' ); ?>">&uarr;</a>
						<?php endif; ?>
						<?php if ( $position < $count ) : ?>
							| <a href="<?php echo esc_url( self::action_url( 'postvoyage_move_rule', $rule['id'], array( 'direction' => 'down' ) ) ); ?>" aria-label="<?php esc_attr_e( 'Move down', 'postvoyage-socialmedia-poster' ); ?>">&darr;</a>
						<?php endif; ?>
						| <a class="postvoyage-delete" href="<?php echo esc_url( self::action_url( 'postvoyage_delete_rule', $rule['id'] ) ); ?>"><?php esc_html_e( 'Delete', 'postvoyage-socialmedia-poster' ); ?></a>
					</td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
		<?php
	}

	/**
	 * Rule form.
	 *
	 * @param array $rule Rule.
	 */
	private static function render_form( array $rule ) {
		$accounts   = AccountRepository::all();
		$languages  = Language::available();
		$post_types = array();
		foreach ( Settings::post_types() as $slug ) {
			$object = get_post_type_object( $slug );
			if ( $object ) {
				$post_types[ $slug ] = $object->labels->name;
			}
		}
		$networks_in_use = array();
		foreach ( $accounts as $account ) {
			$network = Registry::get( $account->network );
			if ( $network ) {
				$networks_in_use[ $account->network ] = $network;
			}
		}
		?>
		<h1><?php echo '' === $rule['id'] ? esc_html__( 'Add rule', 'postvoyage-socialmedia-poster' ) : esc_html__( 'Edit rule', 'postvoyage-socialmedia-poster' ); ?></h1>
		<p><a href="<?php echo esc_url( self::url() ); ?>">&larr; <?php esc_html_e( 'Back to rules', 'postvoyage-socialmedia-poster' ); ?></a></p>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="postvoyage-form">
			<input type="hidden" name="action" value="postvoyage_save_rule">
			<input type="hidden" name="rule[id]" value="<?php echo esc_attr( $rule['id'] ); ?>">
			<?php wp_nonce_field( 'postvoyage_save_rule' ); ?>

			<table class="form-table" role="presentation">
				<tr>
					<th scope="row"><label for="postvoyage-rule-name"><?php esc_html_e( 'Name', 'postvoyage-socialmedia-poster' ); ?></label></th>
					<td><input type="text" id="postvoyage-rule-name" class="regular-text" name="rule[name]" value="<?php echo esc_attr( $rule['name'] ); ?>" required></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Status', 'postvoyage-socialmedia-poster' ); ?></th>
					<td><label><input type="checkbox" name="rule[enabled]" value="1" <?php checked( $rule['enabled'] ); ?>> <?php esc_html_e( 'Active', 'postvoyage-socialmedia-poster' ); ?></label></td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Post types', 'postvoyage-socialmedia-poster' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $post_types as $slug => $name ) : ?>
								<label class="postvoyage-check"><input type="checkbox" name="rule[post_types][]" value="<?php echo esc_attr( $slug ); ?>" <?php checked( in_array( $slug, $rule['post_types'], true ) ); ?>> <?php echo esc_html( $name ); ?></label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'None selected = all post types enabled in the settings.', 'postvoyage-socialmedia-poster' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><?php esc_html_e( 'Categories', 'postvoyage-socialmedia-poster' ); ?></th>
					<td>
						<div class="postvoyage-checklist">
							<?php self::render_category_tree( 0, array_map( 'intval', $rule['categories'] ) ); ?>
						</div>
						<label class="postvoyage-check"><input type="checkbox" name="rule[include_children]" value="1" <?php checked( $rule['include_children'] ); ?>> <?php esc_html_e( 'Include subcategories', 'postvoyage-socialmedia-poster' ); ?></label>
						<p class="description"><?php esc_html_e( 'None selected = all categories.', 'postvoyage-socialmedia-poster' ); ?></p>
					</td>
				</tr>
				<?php if ( $languages ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Languages', 'postvoyage-socialmedia-poster' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $languages as $code => $name ) : ?>
								<label class="postvoyage-check"><input type="checkbox" name="rule[languages][]" value="<?php echo esc_attr( $code ); ?>" <?php checked( in_array( $code, $rule['languages'], true ) ); ?>> <?php echo esc_html( $name ); ?></label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'None selected = all languages. Tip: one rule per language lets you use a separate template and separate accounts per language.', 'postvoyage-socialmedia-poster' ); ?></p>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Accounts', 'postvoyage-socialmedia-poster' ); ?></th>
					<td>
						<fieldset>
							<?php foreach ( $accounts as $account ) : ?>
								<label class="postvoyage-check"><input type="checkbox" name="rule[accounts][]" value="<?php echo esc_attr( $account->id ); ?>" <?php checked( in_array( $account->id, $rule['accounts'], true ) ); ?>> <?php echo esc_html( $account->label ); ?> <span class="postvoyage-muted">(<?php echo esc_html( $account->network ); ?>)</span></label>
							<?php endforeach; ?>
						</fieldset>
						<p class="description"><?php esc_html_e( 'None selected = all active accounts.', 'postvoyage-socialmedia-poster' ); ?></p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="postvoyage-template"><?php esc_html_e( 'Text template', 'postvoyage-socialmedia-poster' ); ?></label></th>
					<td>
						<textarea id="postvoyage-template" class="large-text code postvoyage-template" rows="7" name="rule[template]" data-postvoyage-template><?php echo esc_textarea( $rule['template'] ); ?></textarea>
						<?php self::render_variables(); ?>
						<p class="description"><?php esc_html_e( 'Texts are shortened automatically to the limit of each network; links are never cut.', 'postvoyage-socialmedia-poster' ); ?></p>
					</td>
				</tr>
				<?php if ( $networks_in_use ) : ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Per network', 'postvoyage-socialmedia-poster' ); ?></th>
					<td>
						<?php foreach ( $networks_in_use as $id => $network ) : ?>
							<details class="postvoyage-override" <?php echo isset( $rule['templates'][ $id ] ) ? 'open' : ''; ?>>
								<?php /* translators: %s: network name */ ?>
								<summary><?php echo esc_html( sprintf( __( 'Own template for %s', 'postvoyage-socialmedia-poster' ), $network->label() ) ); ?></summary>
								<textarea class="large-text code postvoyage-template" rows="4" name="rule[templates][<?php echo esc_attr( $id ); ?>]" data-postvoyage-template placeholder="<?php esc_attr_e( 'Empty = use the template above', 'postvoyage-socialmedia-poster' ); ?>"><?php echo esc_textarea( $rule['templates'][ $id ] ?? '' ); ?></textarea>
							</details>
						<?php endforeach; ?>
					</td>
				</tr>
				<?php endif; ?>
				<tr>
					<th scope="row"><?php esc_html_e( 'Image', 'postvoyage-socialmedia-poster' ); ?></th>
					<td><label><input type="checkbox" name="rule[attach_image]" value="1" <?php checked( $rule['attach_image'] ); ?>> <?php esc_html_e( 'Share the featured image (where the network supports it)', 'postvoyage-socialmedia-poster' ); ?></label></td>
				</tr>
			</table>

			<?php submit_button( __( 'Save rule', 'postvoyage-socialmedia-poster' ) ); ?>
		</form>
		<?php
	}

	/**
	 * Clickable list of template variables.
	 */
	private static function render_variables() {
		echo '<div class="postvoyage-variables"><span>' . esc_html__( 'Insert:', 'postvoyage-socialmedia-poster' ) . '</span> ';
		foreach ( TemplateEngine::variables_help() as $variable => $description ) {
			printf(
				'<button type="button" class="button button-small" data-postvoyage-variable="%1$s" title="%2$s">%1$s</button> ',
				esc_attr( $variable ),
				esc_attr( $description )
			);
		}
		echo '</div>';
	}

	/**
	 * Hierarchical category checkboxes.
	 *
	 * @param int   $parent_id Parent term ID.
	 * @param int[] $selected  Selected IDs.
	 */
	private static function render_category_tree( $parent_id, array $selected ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'category',
				'hide_empty' => false,
				'parent'     => $parent_id,
				'lang'       => '', // Polylang: all languages.
			)
		);
		if ( ! is_array( $terms ) || ! $terms ) {
			return;
		}
		echo '<ul>';
		foreach ( $terms as $term ) {
			printf(
				'<li><label class="postvoyage-check"><input type="checkbox" name="rule[categories][]" value="%1$d" %2$s> %3$s</label>',
				(int) $term->term_id,
				checked( in_array( (int) $term->term_id, $selected, true ), true, false ),
				esc_html( $term->name )
			);
			self::render_category_tree( (int) $term->term_id, $selected );
			echo '</li>';
		}
		echo '</ul>';
	}

	/**
	 * Saves a rule.
	 */
	public static function handle_save() {
		check_admin_referer( 'postvoyage_save_rule' );
		self::guard();
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Sanitized in RuleRepository::sanitize().
		$input = isset( $_POST['rule'] ) && is_array( $_POST['rule'] ) ? wp_unslash( $_POST['rule'] ) : array();
		RuleRepository::save( RuleRepository::sanitize( $input ) );
		Notices::add( 'success', __( 'Rule saved.', 'postvoyage-socialmedia-poster' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Deletes a rule.
	 */
	public static function handle_delete() {
		check_admin_referer( 'postvoyage_delete_rule' );
		self::guard();
		RuleRepository::delete( isset( $_GET['rule'] ) ? sanitize_key( wp_unslash( $_GET['rule'] ) ) : '' );
		Notices::add( 'success', __( 'Rule deleted.', 'postvoyage-socialmedia-poster' ) );
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Moves a rule up or down.
	 */
	public static function handle_move() {
		check_admin_referer( 'postvoyage_move_rule' );
		self::guard();
		$id        = isset( $_GET['rule'] ) ? sanitize_key( wp_unslash( $_GET['rule'] ) ) : '';
		$direction = isset( $_GET['direction'] ) && 'up' === $_GET['direction'] ? -1 : 1;

		$rules = array_values( RuleRepository::all() );
		$index = array_search( $id, array_column( $rules, 'id' ), true );
		$swap  = false === $index ? -1 : $index + $direction;
		if ( false !== $index && isset( $rules[ $swap ] ) ) {
			$moved           = $rules[ $index ];
			$rules[ $index ] = $rules[ $swap ];
			$rules[ $swap ]  = $moved;
			update_option( RuleRepository::OPTION, array_column( $rules, null, 'id' ), false );
		}
		wp_safe_redirect( self::url() );
		exit;
	}

	/**
	 * Category column text.
	 *
	 * @param array $rule Rule.
	 * @return string
	 */
	private static function category_summary( array $rule ) {
		if ( ! $rule['categories'] ) {
			return __( 'All', 'postvoyage-socialmedia-poster' );
		}
		$names = array();
		foreach ( $rule['categories'] as $term_id ) {
			$term = get_term( (int) $term_id, 'category' );
			if ( $term && ! is_wp_error( $term ) ) {
				$names[] = $term->name;
			}
		}
		return implode( ', ', $names );
	}

	/**
	 * Accounts column text.
	 *
	 * @param array $rule     Rule.
	 * @param array $accounts Accounts.
	 * @return string
	 */
	private static function account_summary( array $rule, array $accounts ) {
		if ( ! $rule['accounts'] ) {
			return __( 'All active', 'postvoyage-socialmedia-poster' );
		}
		$names = array();
		foreach ( $rule['accounts'] as $id ) {
			if ( isset( $accounts[ $id ] ) ) {
				$names[] = $accounts[ $id ]->label;
			}
		}
		return implode( ', ', $names );
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
	 * @param string $action  Action.
	 * @param string $rule_id Rule ID.
	 * @param array  $extra   Extra args.
	 * @return string
	 */
	private static function action_url( $action, $rule_id, array $extra = array() ) {
		return wp_nonce_url(
			add_query_arg(
				array_merge(
					array(
						'action' => $action,
						'rule'   => $rule_id,
					),
					$extra
				),
				admin_url( 'admin-post.php' )
			),
			$action
		);
	}
}
