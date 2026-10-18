<?php
/**
 * OAuth 1.0a request signing.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Creates OAuth 1.0a (HMAC-SHA1) authorization headers, as required by the X API for
 * posting on behalf of a user. Pure PHP, unit tested against the published example.
 */
final class OAuth1 {

	/**
	 * Builds the Authorization header.
	 *
	 * @param string               $method          HTTP method.
	 * @param string               $url             URL without query string.
	 * @param string               $consumer_key    API key.
	 * @param string               $consumer_secret API key secret.
	 * @param string               $token           Access token.
	 * @param string               $token_secret    Access token secret.
	 * @param array<string,string> $params          Query and form parameters (not JSON bodies).
	 * @param string               $nonce           Nonce (generated if empty).
	 * @param int                  $timestamp       Timestamp (now if 0).
	 * @return string
	 */
	public static function header( $method, $url, $consumer_key, $consumer_secret, $token, $token_secret, array $params = array(), $nonce = '', $timestamp = 0 ) {
		$oauth = array(
			'oauth_consumer_key'     => $consumer_key,
			'oauth_nonce'            => '' !== $nonce ? $nonce : bin2hex( random_bytes( 16 ) ),
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => (string) ( $timestamp > 0 ? $timestamp : time() ),
			'oauth_token'            => $token,
			'oauth_version'          => '1.0',
		);

		$oauth['oauth_signature'] = self::signature( $method, $url, array_merge( $params, $oauth ), $consumer_secret, $token_secret );

		$parts = array();
		foreach ( $oauth as $key => $value ) {
			$parts[] = rawurlencode( $key ) . '="' . rawurlencode( $value ) . '"';
		}
		return 'OAuth ' . implode( ', ', $parts );
	}

	/**
	 * Computes the HMAC-SHA1 signature.
	 *
	 * @param string               $method          HTTP method.
	 * @param string               $url             URL.
	 * @param array<string,string> $params          All parameters incl. oauth_*.
	 * @param string               $consumer_secret Consumer secret.
	 * @param string               $token_secret    Token secret.
	 * @return string
	 */
	public static function signature( $method, $url, array $params, $consumer_secret, $token_secret ) {
		$encoded = array();
		foreach ( $params as $key => $value ) {
			$encoded[ rawurlencode( (string) $key ) ] = rawurlencode( (string) $value );
		}
		ksort( $encoded, SORT_STRING );

		$pairs = array();
		foreach ( $encoded as $key => $value ) {
			$pairs[] = $key . '=' . $value;
		}

		$base = strtoupper( $method ) . '&' . rawurlencode( $url ) . '&' . rawurlencode( implode( '&', $pairs ) );
		$key  = rawurlencode( $consumer_secret ) . '&' . rawurlencode( $token_secret );

		// phpcs:ignore WordPress.PHP.DiscouragedPHPFunctions.obfuscation_base64_encode -- Required by OAuth 1.0a.
		return base64_encode( hash_hmac( 'sha1', $base, $key, true ) );
	}
}
