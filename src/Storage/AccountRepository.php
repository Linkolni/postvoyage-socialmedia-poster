<?php
/**
 * Persistence of accounts.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Storage;

use PostVoyage\Networks\Registry;
use PostVoyage\Support\Crypto;

defined( 'ABSPATH' ) || exit;

/**
 * Stores accounts in the option "postvoyage_accounts". Secret fields are encrypted.
 */
final class AccountRepository {

	const OPTION = 'postvoyage_accounts';

	/**
	 * Hook for the weekly token refresh.
	 */
	const REFRESH_HOOK = 'postvoyage_refresh_tokens';

	/**
	 * Registers hooks.
	 */
	public static function register() {
		add_action( self::REFRESH_HOOK, array( self::class, 'refresh_tokens' ) );
	}

	/**
	 * All accounts, keyed by ID.
	 *
	 * @return array<string,Account>
	 */
	public static function all() {
		$stored   = get_option( self::OPTION, array() );
		$accounts = array();
		if ( ! is_array( $stored ) ) {
			return $accounts;
		}
		foreach ( $stored as $row ) {
			if ( empty( $row['id'] ) || empty( $row['network'] ) ) {
				continue;
			}
			$values = array();
			foreach ( (array) ( $row['values'] ?? array() ) as $key => $value ) {
				$values[ $key ] = is_string( $value ) ? Crypto::decrypt( $value ) : $value;
			}
			$accounts[ $row['id'] ] = new Account( $row['id'], $row['network'], $row['label'] ?? '', ! empty( $row['enabled'] ), $values );
		}
		return $accounts;
	}

	/**
	 * Enabled accounts whose network is registered.
	 *
	 * @return array<string,Account>
	 */
	public static function enabled() {
		return array_filter(
			self::all(),
			static function ( Account $account ) {
				return $account->enabled && null !== Registry::get( $account->network );
			}
		);
	}

	/**
	 * Single account.
	 *
	 * @param string $id Account ID.
	 * @return Account|null
	 */
	public static function find( $id ) {
		$all = self::all();
		return $all[ $id ] ?? null;
	}

	/**
	 * Inserts or updates an account.
	 *
	 * @param Account $account Account.
	 */
	public static function save( Account $account ) {
		$network = Registry::get( $account->network );
		$fields  = $network ? $network->fields() : array();

		$values = array();
		foreach ( $account->values as $key => $value ) {
			$secret         = ! empty( $fields[ $key ]['secret'] );
			$values[ $key ] = $secret ? Crypto::encrypt( (string) $value ) : $value;
		}

		$stored = get_option( self::OPTION, array() );
		$stored = is_array( $stored ) ? $stored : array();

		$stored[ $account->id ] = array(
			'id'      => $account->id,
			'network' => $account->network,
			'label'   => $account->label,
			'enabled' => $account->enabled,
			'values'  => $values,
		);

		update_option( self::OPTION, $stored, false );
	}

	/**
	 * Deletes an account.
	 *
	 * @param string $id Account ID.
	 */
	public static function delete( $id ) {
		$stored = get_option( self::OPTION, array() );
		if ( is_array( $stored ) && isset( $stored[ $id ] ) ) {
			unset( $stored[ $id ] );
			update_option( self::OPTION, $stored, false );
		}
	}

	/**
	 * Builds an account from submitted form data. Secret fields left empty keep their stored value.
	 *
	 * @param string       $network_id Network ID.
	 * @param array        $input      Raw (unslashed) input.
	 * @param Account|null $existing   Existing account when editing.
	 * @return Account|\WP_Error
	 */
	public static function from_input( $network_id, array $input, $existing = null ) {
		$network = Registry::get( $network_id );
		if ( ! $network ) {
			return new \WP_Error( 'postvoyage_network', __( 'Unknown network.', 'postvoyage-socialmedia-poster' ) );
		}

		$values = array();
		foreach ( $network->fields() as $key => $field ) {
			$raw = $input['values'][ $key ] ?? '';
			switch ( $field['type'] ) {
				case 'checkbox':
					$value = ! empty( $raw );
					break;
				case 'url':
					$value = esc_url_raw( trim( (string) $raw ) );
					break;
				case 'number':
					$value = '' === $raw ? '' : absint( $raw );
					break;
				case 'select':
					$value = array_key_exists( (string) $raw, $field['options'] ) ? (string) $raw : (string) ( $field['default'] ?? '' );
					break;
				case 'password':
					// Tokens may contain characters sanitize_text_field() would alter (e.g. "%2F"); only strip control characters.
					$value = trim( (string) preg_replace( '/[\x00-\x1F\x7F]/', '', wp_check_invalid_utf8( (string) $raw ) ) );
					break;
				default:
					$value = trim( sanitize_text_field( (string) $raw ) );
			}

			if ( ! empty( $field['secret'] ) && '' === $value && $existing ) {
				$value = $existing->get( $key );
			}

			if ( ! empty( $field['required'] ) && ( '' === $value || null === $value ) ) {
				/* translators: %s: field label */
				return new \WP_Error( 'postvoyage_required', sprintf( __( 'Please fill in "%s".', 'postvoyage-socialmedia-poster' ), $field['label'] ) );
			}

			$values[ $key ] = $value;
		}

		$label = trim( sanitize_text_field( (string) ( $input['label'] ?? '' ) ) );
		if ( '' === $label ) {
			$label = $network->label();
		}

		return new Account(
			$existing ? $existing->id : self::new_id(),
			$network_id,
			$label,
			! empty( $input['enabled'] ),
			$values
		);
	}

	/**
	 * Lets networks refresh expiring tokens (weekly cron).
	 */
	public static function refresh_tokens() {
		foreach ( self::all() as $account ) {
			$network = Registry::get( $account->network );
			if ( ! $network ) {
				continue;
			}
			$values = $network->refresh_credentials( $account );
			if ( is_array( $values ) ) {
				$account->values = array_merge( $account->values, $values );
				self::save( $account );
			}
		}
	}

	/**
	 * Generates a short random ID.
	 *
	 * @return string
	 */
	private static function new_id() {
		return 'acc_' . strtolower( wp_generate_password( 10, false, false ) );
	}
}
