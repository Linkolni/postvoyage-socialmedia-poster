<?php
/**
 * LinkedIn profiles and company pages.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Shares posts as article posts via the LinkedIn Posts API.
 */
final class LinkedIn extends AbstractNetwork {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'linkedin';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'LinkedIn';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'Create an app at linkedin.com/developers with the products "Share on LinkedIn" and "Sign In with LinkedIn using OpenID Connect" and generate an access token (scopes: openid, profile, w_member_social) with the OAuth token generator. Tokens are valid for 60 days. Leave the author empty to post as yourself; company pages need urn:li:organization:ID and the w_organization_social scope.', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'access_token' => $this->field(
				__( 'Access token', 'postvoyage-socialmedia-poster' ),
				'password',
				array(
					'required' => true,
					'secret'   => true,
				)
			),
			'author_urn'   => $this->field(
				__( 'Author', 'postvoyage-socialmedia-poster' ),
				'text',
				array( 'description' => __( 'Optional: urn:li:person:… or urn:li:organization:…', 'postvoyage-socialmedia-poster' ) )
			),
			'api_version'  => $this->field(
				__( 'API version', 'postvoyage-socialmedia-poster' ),
				'text',
				array( 'description' => __( 'Optional, format YYYYMM. Empty = automatically a recent version.', 'postvoyage-socialmedia-poster' ) )
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
		return 3000;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 */
	protected function send( Message $message, Account $account ) {
		$author = (string) $account->get( 'author_urn', '' );
		if ( '' === $author ) {
			$author = $this->person_urn( $account );
		}

		$post = array(
			'author'                    => $author,
			'commentary'                => self::escape( $message->text ),
			'visibility'                => 'PUBLIC',
			'distribution'              => array(
				'feedDistribution'               => 'MAIN_FEED',
				'targetEntities'                 => array(),
				'thirdPartyDistributionChannels' => array(),
			),
			'lifecycleState'            => 'PUBLISHED',
			'isReshareDisabledByAuthor' => false,
		);

		if ( '' !== $message->url ) {
			$post['content'] = array(
				'article' => array(
					'source'      => $message->url,
					'title'       => mb_substr( $message->title, 0, 400 ),
					'description' => mb_substr( $message->description, 0, 4086 ),
				),
			);
		}

		$response = $this->http(
			'POST',
			'https://api.linkedin.com/rest/posts',
			array(
				'headers' => $this->headers( $account ),
				'json'    => $post,
			)
		);

		$urn = isset( $response['headers']['x-restli-id'] ) ? (string) $response['headers']['x-restli-id'] : '';
		$url = preg_match( '~^urn:li:[a-zA-Z]+:[\w-]+$~', $urn ) ? 'https://www.linkedin.com/feed/update/' . $urn . '/' : '';

		return Result::ok( '', $url );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$response = $this->http( 'GET', 'https://api.linkedin.com/v2/userinfo', array( 'headers' => array( 'Authorization' => 'Bearer ' . $account->get( 'access_token' ) ) ) );
		return Result::ok(
			sprintf(
				/* translators: 1: name, 2: URN */
				__( 'Connected as %1$s (%2$s)', 'postvoyage-socialmedia-poster' ),
				$response['body']['name'] ?? '?',
				'urn:li:person:' . ( $response['body']['sub'] ?? '?' )
			)
		);
	}

	/**
	 * Escapes reserved characters of LinkedIn's "little text format".
	 *
	 * @param string $text Text.
	 * @return string
	 */
	public static function escape( $text ) {
		return (string) preg_replace( '/([\\\\|{}@\[\]()<>#*_~])/', '\\\\$1', $text );
	}

	/**
	 * URN of the token owner.
	 *
	 * @param Account $account Account.
	 * @return string
	 * @throws NetworkException If the profile cannot be read.
	 */
	private function person_urn( Account $account ) {
		$response = $this->http( 'GET', 'https://api.linkedin.com/v2/userinfo', array( 'headers' => array( 'Authorization' => 'Bearer ' . $account->get( 'access_token' ) ) ) );
		if ( empty( $response['body']['sub'] ) ) {
			throw new NetworkException( __( 'Could not determine the LinkedIn author. Please enter it in the account settings.', 'postvoyage-socialmedia-poster' ) );
		}
		return 'urn:li:person:' . $response['body']['sub'];
	}

	/**
	 * Headers for the versioned REST API.
	 *
	 * @param Account $account Account.
	 * @return array<string,string>
	 */
	private function headers( Account $account ) {
		$version = (string) $account->get( 'api_version', '' );
		if ( ! preg_match( '~^\d{6}$~', $version ) ) {
			// LinkedIn supports each monthly version for at least a year; two months back is always available.
			$version = gmdate( 'Ym', strtotime( 'first day of -2 months' ) );
		}
		/**
		 * Filters the LinkedIn API version.
		 *
		 * @param string $version Version in the format YYYYMM.
		 */
		$version = (string) apply_filters( 'postvoyage_linkedin_api_version', $version );

		return array(
			'Authorization'             => 'Bearer ' . $account->get( 'access_token' ),
			'LinkedIn-Version'          => $version,
			'X-Restli-Protocol-Version' => '2.0.0',
		);
	}
}
