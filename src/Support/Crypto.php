<?php
/**
 * Encryption of stored credentials.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Support;

defined( 'ABSPATH' ) || exit;

/**
 * Encrypts secrets (tokens, passwords) with libsodium before they are stored in wp_options.
 *
 * The key is derived from the WordPress salts in wp-config.php. If the salts change,
 * stored secrets can no longer be decrypted and must be entered again. A fixed key can
 * be set with the constant POSTVOYAGE_ENCRYPTION_KEY.
 */
final class Crypto {

	const PREFIX = 'pv1:';

	/**
	 * Encrypts a string. Empty strings stay empty.
	 *
	 * @param string $plain Plain text.
	 * @return string
	 */
	public static function encrypt( $plain ) {
		$plain = (string) $plain;
		if ( '' === $plain ) {
			return '';
		}
		$nonce  = random_bytes( SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = sodium_crypto_secretbox( $plain, $nonce, self::key() );
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Binary ciphertext storage.
		return self::PREFIX . base64_encode( $nonce . $cipher );
	}

	/**
	 * Decrypts a string produced by encrypt(). Returns '' if decryption fails.
	 *
	 * @param string $stored Stored value.
	 * @return string
	 */
	public static function decrypt( $stored ) {
		$stored = (string) $stored;
		if ( 0 !== strpos( $stored, self::PREFIX ) ) {
			return $stored;
		}
		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_decode -- Binary ciphertext storage.
		$raw = base64_decode( substr( $stored, strlen( self::PREFIX ) ), true );
		if ( false === $raw || strlen( $raw ) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES ) {
			return '';
		}
		$nonce  = substr( $raw, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$cipher = substr( $raw, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES );
		$plain  = sodium_crypto_secretbox_open( $cipher, $nonce, self::key() );
		return false === $plain ? '' : $plain;
	}

	/**
	 * 32-byte key.
	 *
	 * @return string
	 */
	private static function key() {
		$material = defined( 'POSTVOYAGE_ENCRYPTION_KEY' ) ? (string) POSTVOYAGE_ENCRYPTION_KEY : wp_salt( 'auth' );
		return hash( 'sha256', 'postvoyage|' . $material, true );
	}
}
