<?php
/**
 * X (formerly Twitter).
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Http\OAuth1;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Posts via the X API v2 with OAuth 1.0a user context.
 */
final class X extends AbstractNetwork {

	const API = 'https://api.x.com/2';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'x';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'X (Twitter)';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'Create a project and app at developer.x.com, set the app permissions to "Read and write" and then generate API key/secret and access token/secret. Note: how many posts the X API allows depends on your API plan.', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		$secret = array(
			'required' => true,
			'secret'   => true,
		);
		return array(
			'api_key'             => $this->field( __( 'API key', 'postvoyage-socialmedia-poster' ), 'password', $secret ),
			'api_secret'          => $this->field( __( 'API key secret', 'postvoyage-socialmedia-poster' ), 'password', $secret ),
			'access_token'        => $this->field( __( 'Access token', 'postvoyage-socialmedia-poster' ), 'password', $secret ),
			'access_token_secret' => $this->field( __( 'Access token secret', 'postvoyage-socialmedia-poster' ), 'password', $secret ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account   Account.
	 * @param bool    $has_image Has image.
	 */
	public function char_limit( Account $account, $has_image ) {
		return 280;
	}

	/**
	 * {@inheritDoc}
	 */
	public function url_length() {
		return 23;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 */
	protected function send( Message $message, Account $account ) {
		$url      = self::API . '/tweets';
		$response = $this->http(
			'POST',
			$url,
			array(
				'headers' => array( 'Authorization' => $this->authorization( 'POST', $url, $account ) ),
				'json'    => array( 'text' => $message->text ),
			)
		);
		$id       = (string) ( $response['body']['data']['id'] ?? '' );
		return Result::ok( '', '' !== $id ? 'https://x.com/i/status/' . rawurlencode( $id ) : '' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$url      = self::API . '/users/me';
		$response = $this->http( 'GET', $url, array( 'headers' => array( 'Authorization' => $this->authorization( 'GET', $url, $account ) ) ) );
		/* translators: %s: account name */
		return Result::ok( sprintf( __( 'Connected as @%s', 'postvoyage-socialmedia-poster' ), $response['body']['data']['username'] ?? '?' ) );
	}

	/**
	 * OAuth 1.0a header.
	 *
	 * @param string  $method  Method.
	 * @param string  $url     URL.
	 * @param Account $account Account.
	 * @return string
	 */
	private function authorization( $method, $url, Account $account ) {
		return OAuth1::header(
			$method,
			$url,
			(string) $account->get( 'api_key' ),
			(string) $account->get( 'api_secret' ),
			(string) $account->get( 'access_token' ),
			(string) $account->get( 'access_token_secret' )
		);
	}
}
