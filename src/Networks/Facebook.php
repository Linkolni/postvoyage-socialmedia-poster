<?php
/**
 * Facebook pages.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Posts links to a Facebook page via the Graph API with a page access token.
 */
final class Facebook extends AbstractNetwork {

	/**
	 * Graph API version. Can be changed per account or with the filter "postvoyage_facebook_api_version".
	 */
	const API_VERSION = 'v23.0';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'facebook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Facebook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'Create an app at developers.facebook.com, generate a long-lived page access token with the permissions pages_manage_posts and pages_read_engagement (e.g. with the Graph API Explorer) and enter the numeric page ID.', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'page_id'      => $this->field( __( 'Page ID', 'postvoyage-socialmedia-poster' ), 'text', array( 'required' => true ) ),
			'access_token' => $this->field(
				__( 'Page access token', 'postvoyage-socialmedia-poster' ),
				'password',
				array(
					'required' => true,
					'secret'   => true,
				)
			),
			'api_version'  => $this->field(
				__( 'Graph API version', 'postvoyage-socialmedia-poster' ),
				'text',
				array(
					/* translators: %s: version */
					'description' => sprintf( __( 'Leave empty for the default (%s).', 'postvoyage-socialmedia-poster' ), self::API_VERSION ),
				)
			),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account   Account.
	 * @param bool    $has_image Has image.
	 */
	public function char_limit( Account $account, $has_image ) {
		return 63206;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 */
	protected function send( Message $message, Account $account ) {
		$body = array(
			'message'      => $message->text,
			'access_token' => (string) $account->get( 'access_token' ),
		);
		if ( '' !== $message->url ) {
			$body['link'] = $message->url;
		}

		$response = $this->http( 'POST', $this->graph( $account ) . '/' . rawurlencode( (string) $account->get( 'page_id' ) ) . '/feed', array( 'body' => $body ) );
		$id       = (string) ( $response['body']['id'] ?? '' );

		return Result::ok( '', '' !== $id ? 'https://www.facebook.com/' . rawurlencode( $id ) : '' );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$url      = add_query_arg(
			array(
				'fields'       => 'name',
				'access_token' => rawurlencode( (string) $account->get( 'access_token' ) ),
			),
			$this->graph( $account ) . '/' . rawurlencode( (string) $account->get( 'page_id' ) )
		);
		$response = $this->http( 'GET', $url );
		/* translators: %s: page name */
		return Result::ok( sprintf( __( 'Connected to page "%s"', 'postvoyage-socialmedia-poster' ), $response['body']['name'] ?? '?' ) );
	}

	/**
	 * Graph API base URL.
	 *
	 * @param Account $account Account.
	 * @return string
	 */
	private function graph( Account $account ) {
		$version = (string) $account->get( 'api_version', '' );
		if ( ! preg_match( '~^v\d+\.\d+$~', $version ) ) {
			/**
			 * Filters the default Graph API version.
			 *
			 * @param string $version e.g. "v23.0".
			 */
			$version = (string) apply_filters( 'postvoyage_facebook_api_version', self::API_VERSION );
		}
		return 'https://graph.facebook.com/' . $version;
	}
}
