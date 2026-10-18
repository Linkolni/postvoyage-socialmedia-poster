<?php
/**
 * Threads (Meta).
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Publishes via the Threads API (create container, then publish). Long-lived tokens are
 * refreshed automatically once a week.
 */
final class Threads extends AbstractNetwork {

	const API = 'https://graph.threads.net/v1.0';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'threads';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Threads';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'Create a Meta app with the "Threads API" use case and the permissions threads_basic and threads_content_publish, then generate a long-lived access token. PostVoyage refreshes it weekly so it does not expire.', 'postvoyage-socialmedia-poster' );
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
			'user_id'      => $this->field(
				__( 'User ID', 'postvoyage-socialmedia-poster' ),
				'text',
				array( 'description' => __( 'Optional, "me" is used by default.', 'postvoyage-socialmedia-poster' ) )
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
		return 500;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 * @throws NetworkException On API errors.
	 */
	protected function send( Message $message, Account $account ) {
		$token = (string) $account->get( 'access_token' );
		$user  = rawurlencode( (string) $account->get( 'user_id', 'me' ) );

		$params    = array(
			'text'         => $message->text,
			'access_token' => $token,
		);
		$image_url = $message->public_image_url();
		if ( '' !== $image_url ) {
			$params['media_type'] = 'IMAGE';
			$params['image_url']  = $image_url;
		} else {
			$params['media_type'] = 'TEXT';
			if ( '' !== $message->url ) {
				$params['link_attachment'] = $message->url;
			}
		}

		$container = $this->http( 'POST', self::API . '/' . $user . '/threads', array( 'body' => $params ) );
		$creation  = (string) ( $container['body']['id'] ?? '' );
		if ( '' === $creation ) {
			throw new NetworkException( __( 'Threads did not return a container ID.', 'postvoyage-socialmedia-poster' ), true );
		}

		if ( 'IMAGE' === $params['media_type'] ) {
			$this->wait_until_ready( $creation, $token );
		}

		$published = $this->http(
			'POST',
			self::API . '/' . $user . '/threads_publish',
			array(
				'body' => array(
					'creation_id'  => $creation,
					'access_token' => $token,
				),
			)
		);
		$media_id  = (string) ( $published['body']['id'] ?? '' );

		$permalink = '';
		if ( '' !== $media_id ) {
			try {
				$info      = $this->http(
					'GET',
					add_query_arg(
						array(
							'fields'       => 'permalink',
							'access_token' => rawurlencode( $token ),
						),
						self::API . '/' . rawurlencode( $media_id )
					)
				);
				$permalink = (string) ( $info['body']['permalink'] ?? '' );
			} catch ( NetworkException $e ) {
				// The post is published; a missing permalink is not an error.
				$permalink = '';
			}
		}

		return Result::ok( '', $permalink );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$response = $this->http(
			'GET',
			add_query_arg(
				array(
					'fields'       => 'username',
					'access_token' => rawurlencode( (string) $account->get( 'access_token' ) ),
				),
				self::API . '/' . rawurlencode( (string) $account->get( 'user_id', 'me' ) )
			)
		);
		/* translators: %s: account name */
		return Result::ok( sprintf( __( 'Connected as @%s', 'postvoyage-socialmedia-poster' ), $response['body']['username'] ?? '?' ) );
	}

	/**
	 * Exchanges the long-lived token for a fresh one (valid 60 days).
	 *
	 * @param Account $account Account.
	 * @return array<string,mixed>|null
	 */
	public function refresh_credentials( Account $account ) {
		$token = (string) $account->get( 'access_token' );
		if ( '' === $token ) {
			return null;
		}
		try {
			$response = $this->http(
				'GET',
				add_query_arg(
					array(
						'grant_type'   => 'th_refresh_token',
						'access_token' => rawurlencode( $token ),
					),
					'https://graph.threads.net/refresh_access_token'
				)
			);
		} catch ( NetworkException $e ) {
			return null;
		}
		$new = (string) ( $response['body']['access_token'] ?? '' );
		return '' !== $new && $new !== $token ? array( 'access_token' => $new ) : null;
	}

	/**
	 * Image containers are processed asynchronously; wait up to ~20 seconds.
	 *
	 * @param string $container Container ID.
	 * @param string $token     Access token.
	 * @throws NetworkException If processing failed.
	 */
	private function wait_until_ready( $container, $token ) {
		for ( $i = 0; $i < 10; $i++ ) {
			$status = $this->http(
				'GET',
				add_query_arg(
					array(
						'fields'       => 'status,error_message',
						'access_token' => rawurlencode( $token ),
					),
					self::API . '/' . rawurlencode( $container )
				)
			);
			$state  = (string) ( $status['body']['status'] ?? '' );
			if ( 'FINISHED' === $state ) {
				return;
			}
			if ( 'ERROR' === $state || 'EXPIRED' === $state ) {
				throw new NetworkException( (string) ( $status['body']['error_message'] ?? $state ) );
			}
			sleep( 2 );
		}
	}
}
