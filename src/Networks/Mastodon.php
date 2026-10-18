<?php
/**
 * Mastodon (and compatible Fediverse servers).
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Http\Multipart;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Posts statuses via the Mastodon REST API with a personal access token.
 */
final class Mastodon extends AbstractNetwork {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'mastodon';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Mastodon';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'On your Mastodon server open Preferences → Development → New application. Scopes "write:statuses" and "write:media" are enough. Copy "Your access token".', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'instance'     => $this->field(
				__( 'Server URL', 'postvoyage-socialmedia-poster' ),
				'url',
				array(
					'required'    => true,
					'default'     => 'https://mastodon.social',
					'description' => __( 'e.g. https://mastodon.social', 'postvoyage-socialmedia-poster' ),
				)
			),
			'access_token' => $this->field(
				__( 'Access token', 'postvoyage-socialmedia-poster' ),
				'password',
				array(
					'required' => true,
					'secret'   => true,
				)
			),
			'visibility'   => $this->field(
				__( 'Visibility', 'postvoyage-socialmedia-poster' ),
				'select',
				array(
					'default' => 'public',
					'options' => array(
						'public'   => __( 'Public', 'postvoyage-socialmedia-poster' ),
						'unlisted' => __( 'Unlisted', 'postvoyage-socialmedia-poster' ),
						'private'  => __( 'Followers only', 'postvoyage-socialmedia-poster' ),
					),
				)
			),
			'char_limit'   => $this->field(
				__( 'Character limit', 'postvoyage-socialmedia-poster' ),
				'number',
				array(
					'default'     => 500,
					'description' => __( 'Some servers allow more than 500.', 'postvoyage-socialmedia-poster' ),
				)
			),
			'attach_image' => $this->field(
				__( 'Upload featured image', 'postvoyage-socialmedia-poster' ),
				'checkbox',
				array( 'description' => __( 'Off: Mastodon shows a link preview card instead.', 'postvoyage-socialmedia-poster' ) )
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
		return (int) $account->get( 'char_limit', 500 );
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
		$base    = $this->base( $account );
		$headers = $this->auth( $account );

		$status = array(
			'status'     => $message->text,
			'visibility' => $account->get( 'visibility', 'public' ),
		);
		if ( '' !== $message->language ) {
			$status['language'] = $message->language;
		}

		if ( $account->get( 'attach_image', false ) ) {
			$file = $message->image_file( 8 * MB_IN_BYTES );
			if ( $file ) {
				$status['media_ids'] = array( $this->upload( $base, $headers, $file ) );
			}
		}

		$headers['Idempotency-Key'] = md5( 'postvoyage|' . $message->post_id . '|' . $account->id );

		$response = $this->http(
			'POST',
			$base . '/api/v1/statuses',
			array(
				'headers' => $headers,
				'json'    => $status,
			)
		);

		return Result::ok( '', (string) ( $response['body']['url'] ?? '' ) );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$response = $this->http( 'GET', $this->base( $account ) . '/api/v1/accounts/verify_credentials', array( 'headers' => $this->auth( $account ) ) );
		/* translators: %s: account name */
		return Result::ok( sprintf( __( 'Connected as @%s', 'postvoyage-socialmedia-poster' ), $response['body']['acct'] ?? '?' ) );
	}

	/**
	 * Uploads an image and returns its media ID.
	 *
	 * @param string $base    Server URL.
	 * @param array  $headers Auth headers.
	 * @param array  $file    File from Message::image_file().
	 * @return string
	 * @throws NetworkException On failure.
	 */
	private function upload( $base, array $headers, array $file ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$data      = (string) file_get_contents( $file['path'] );
		$multipart = Multipart::build(
			array( 'description' => $file['alt'] ),
			array(
				'file' => array(
					'filename' => basename( $file['path'] ),
					'mime'     => $file['mime'],
					'data'     => $data,
				),
			)
		);

		$headers['Content-Type'] = $multipart['content_type'];

		$response = $this->http(
			'POST',
			$base . '/api/v2/media',
			array(
				'headers' => $headers,
				'body'    => $multipart['body'],
				'timeout' => 60,
			)
		);

		if ( empty( $response['body']['id'] ) ) {
			throw new NetworkException( __( 'Image upload failed.', 'postvoyage-socialmedia-poster' ), true );
		}
		return (string) $response['body']['id'];
	}

	/**
	 * Server base URL without trailing slash.
	 *
	 * @param Account $account Account.
	 * @return string
	 */
	private function base( Account $account ) {
		return untrailingslashit( (string) $account->get( 'instance', 'https://mastodon.social' ) );
	}

	/**
	 * Authorization header.
	 *
	 * @param Account $account Account.
	 * @return array<string,string>
	 */
	private function auth( Account $account ) {
		return array( 'Authorization' => 'Bearer ' . $account->get( 'access_token' ) );
	}
}
