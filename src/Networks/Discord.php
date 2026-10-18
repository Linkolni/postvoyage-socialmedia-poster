<?php
/**
 * Discord webhooks.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Http\Multipart;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Posts into a Discord channel via a channel webhook.
 */
final class Discord extends AbstractNetwork {

	/**
	 * Webhook URL of the current request, removed from error messages.
	 *
	 * @var string
	 */
	private $webhook = '';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'discord';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Discord';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'In Discord open the channel settings → Integrations → Webhooks → New webhook, then copy the webhook URL.', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'webhook_url' => $this->field(
				__( 'Webhook URL', 'postvoyage-socialmedia-poster' ),
				'password',
				array(
					'required' => true,
					'secret'   => true,
				)
			),
			'username'    => $this->field(
				__( 'Display name', 'postvoyage-socialmedia-poster' ),
				'text',
				array( 'description' => __( 'Optional, overrides the webhook name.', 'postvoyage-socialmedia-poster' ) )
			),
			'use_embed'   => $this->field(
				__( 'Show as card (embed) with title and image', 'postvoyage-socialmedia-poster' ),
				'checkbox',
				array( 'default' => true )
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
		return 2000;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 */
	protected function send( Message $message, Account $account ) {
		$this->webhook = $this->webhook_url( $account );

		$payload = array(
			'content'          => $message->text,
			'allowed_mentions' => array( 'parse' => array() ),
		);
		if ( '' !== $account->get( 'username' ) ) {
			$payload['username'] = (string) $account->get( 'username' );
		}

		$files = array();
		if ( $account->get( 'use_embed', false ) && '' !== $message->url ) {
			$embed = array(
				'title'       => mb_substr( $message->title, 0, 256 ),
				'url'         => $message->url,
				'description' => mb_substr( $message->description, 0, 4096 ),
			);
			$file  = $message->image_file( 8 * MB_IN_BYTES );
			if ( $file ) {
				// Upload as attachment so it also works when the site is not public.
				$name              = 'image.' . pathinfo( $file['path'], PATHINFO_EXTENSION );
				$embed['image']    = array( 'url' => 'attachment://' . $name );
				$files['files[0]'] = array(
					'filename' => $name,
					'mime'     => $file['mime'],
					// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
					'data'     => (string) file_get_contents( $file['path'] ),
				);
			}
			$payload['embeds'] = array( $embed );
		}

		$url = add_query_arg( 'wait', 'true', $this->webhook );

		if ( $files ) {
			$multipart = Multipart::build( array( 'payload_json' => (string) wp_json_encode( $payload ) ), $files );
			$response  = $this->http(
				'POST',
				$url,
				array(
					'headers' => array( 'Content-Type' => $multipart['content_type'] ),
					'body'    => $multipart['body'],
					'timeout' => 60,
				)
			);
		} else {
			$response = $this->http( 'POST', $url, array( 'json' => $payload ) );
		}

		$body = $response['body'];
		$link = '';
		if ( ! empty( $body['guild_id'] ) && ! empty( $body['channel_id'] ) && ! empty( $body['id'] ) ) {
			$link = sprintf( 'https://discord.com/channels/%s/%s/%s', $body['guild_id'], $body['channel_id'], $body['id'] );
		}

		return Result::ok( '', $link );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$this->webhook = $this->webhook_url( $account );
		$response      = $this->http( 'GET', $this->webhook );
		/* translators: %s: webhook name */
		return Result::ok( sprintf( __( 'Webhook "%s" found', 'postvoyage-socialmedia-poster' ), $response['body']['name'] ?? '?' ) );
	}

	/**
	 * Validated webhook URL.
	 *
	 * @param Account $account Account.
	 * @return string
	 * @throws NetworkException If the URL is not a Discord webhook.
	 */
	private function webhook_url( Account $account ) {
		$url  = (string) $account->get( 'webhook_url' );
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( ! preg_match( '~(^|\.)(discord\.com|discordapp\.com)$~', $host ) ) {
			throw new NetworkException( __( 'This is not a Discord webhook URL.', 'postvoyage-socialmedia-poster' ) );
		}
		return $url;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $text Text.
	 */
	protected function redact( $text ) {
		return '' !== $this->webhook ? str_replace( $this->webhook, '***', $text ) : $text;
	}
}
