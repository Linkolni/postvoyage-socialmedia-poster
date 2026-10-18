<?php
/**
 * Generic webhook.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Sends the post as JSON to any URL, e.g. n8n, Make, Zapier or Home Assistant.
 * This makes networks without a free API reachable (Instagram, Pinterest, …).
 */
final class Webhook extends AbstractNetwork {

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'webhook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Webhook';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'Sends a JSON POST request with text, link, title, image URL, tags and language. With a secret, the header X-PostVoyage-Signature contains "sha256=" plus the HMAC of the body.', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'url'        => $this->field( __( 'Webhook URL', 'postvoyage-socialmedia-poster' ), 'url', array( 'required' => true ) ),
			'secret'     => $this->field(
				__( 'Signing secret', 'postvoyage-socialmedia-poster' ),
				'password',
				array(
					'secret'      => true,
					'description' => __( 'Optional.', 'postvoyage-socialmedia-poster' ),
				)
			),
			'char_limit' => $this->field(
				__( 'Character limit', 'postvoyage-socialmedia-poster' ),
				'number',
				array( 'description' => __( 'Optional, empty = unlimited.', 'postvoyage-socialmedia-poster' ) )
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
		return (int) $account->get( 'char_limit', 0 );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 */
	protected function send( Message $message, Account $account ) {
		$file    = $message->image_file();
		$payload = array(
			'event'    => 'postvoyage.publish',
			'site'     => home_url( '/' ),
			'post_id'  => $message->post_id,
			'text'     => $message->text,
			'url'      => $message->url,
			'title'    => $message->title,
			'excerpt'  => $message->description,
			'image'    => $file ? $file['url'] : '',
			'language' => $message->language,
			'hashtags' => $message->hashtags,
			'account'  => $account->label,
		);

		/**
		 * Filters the webhook payload.
		 *
		 * @param array   $payload Payload.
		 * @param Message $message Message.
		 * @param Account $account Account.
		 */
		$payload = (array) apply_filters( 'postvoyage_webhook_payload', $payload, $message, $account );
		$body    = (string) wp_json_encode( $payload );

		$this->http(
			'POST',
			(string) $account->get( 'url' ),
			array(
				'headers' => $this->headers( $account, $body ) + array( 'Content-Type' => 'application/json' ),
				'body'    => $body,
			)
		);

		return Result::ok();
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$body     = (string) wp_json_encode(
			array(
				'event' => 'postvoyage.test',
				'site'  => home_url( '/' ),
			)
		);
		$response = $this->http(
			'POST',
			(string) $account->get( 'url' ),
			array(
				'headers' => $this->headers( $account, $body ) + array( 'Content-Type' => 'application/json' ),
				'body'    => $body,
			)
		);
		/* translators: %d: HTTP status code */
		return Result::ok( sprintf( __( 'Webhook answered with HTTP %d', 'postvoyage-socialmedia-poster' ), $response['code'] ) );
	}

	/**
	 * Signature header.
	 *
	 * @param Account $account Account.
	 * @param string  $body    Request body.
	 * @return array<string,string>
	 */
	private function headers( Account $account, $body ) {
		$secret = (string) $account->get( 'secret', '' );
		return '' === $secret ? array() : array( 'X-PostVoyage-Signature' => 'sha256=' . hash_hmac( 'sha256', $body, $secret ) );
	}
}
