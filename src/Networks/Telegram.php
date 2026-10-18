<?php
/**
 * Telegram channels and groups.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Http\Multipart;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Sends messages through a Telegram bot that is admin of the target channel or member of the group.
 */
final class Telegram extends AbstractNetwork {

	/**
	 * Bot token of the current request, removed from error messages.
	 *
	 * @var string
	 */
	private $token = '';

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'telegram';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Telegram';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'Create a bot with @BotFather and copy the token. Add the bot as administrator to your channel. Chat ID: @channelname for public channels, otherwise the numeric ID (e.g. -100…).', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'bot_token'       => $this->field(
				__( 'Bot token', 'postvoyage-socialmedia-poster' ),
				'password',
				array(
					'required' => true,
					'secret'   => true,
				)
			),
			'chat_id'         => $this->field(
				__( 'Chat ID', 'postvoyage-socialmedia-poster' ),
				'text',
				array(
					'required'    => true,
					'description' => __( 'e.g. @mychannel or -1001234567890', 'postvoyage-socialmedia-poster' ),
				)
			),
			'send_image'      => $this->field(
				__( 'Send featured image', 'postvoyage-socialmedia-poster' ),
				'checkbox',
				array(
					'default'     => true,
					'description' => __( 'With an image the text is limited to 1024 characters.', 'postvoyage-socialmedia-poster' ),
				)
			),
			'disable_preview' => $this->field( __( 'Disable link preview', 'postvoyage-socialmedia-poster' ), 'checkbox' ),
			'silent'          => $this->field( __( 'Send silently (no notification sound)', 'postvoyage-socialmedia-poster' ), 'checkbox' ),
		);
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account   Account.
	 * @param bool    $has_image Has image.
	 */
	public function char_limit( Account $account, $has_image ) {
		return ( $has_image && $account->get( 'send_image', false ) ) ? 1024 : 4096;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 */
	protected function send( Message $message, Account $account ) {
		$this->token = (string) $account->get( 'bot_token' );
		$chat_id     = (string) $account->get( 'chat_id' );
		$silent      = $account->get( 'silent', false ) ? 'true' : 'false';
		$file        = $account->get( 'send_image', false ) ? $message->image_file( 10 * MB_IN_BYTES ) : null;

		if ( $file ) {
			// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
			$data      = (string) file_get_contents( $file['path'] );
			$multipart = Multipart::build(
				array(
					'chat_id'              => $chat_id,
					'caption'              => $message->text,
					'disable_notification' => $silent,
				),
				array(
					'photo' => array(
						'filename' => basename( $file['path'] ),
						'mime'     => $file['mime'],
						'data'     => $data,
					),
				)
			);
			$response  = $this->http(
				'POST',
				$this->endpoint( 'sendPhoto' ),
				array(
					'headers' => array( 'Content-Type' => $multipart['content_type'] ),
					'body'    => $multipart['body'],
					'timeout' => 60,
				)
			);
		} else {
			$response = $this->http(
				'POST',
				$this->endpoint( 'sendMessage' ),
				array(
					'json' => array(
						'chat_id'              => $chat_id,
						'text'                 => $message->text,
						'disable_notification' => 'true' === $silent,
						'link_preview_options' => array( 'is_disabled' => (bool) $account->get( 'disable_preview', false ) ),
					),
				)
			);
		}

		$message_id = $response['body']['result']['message_id'] ?? 0;
		$url        = ( $message_id && 0 === strpos( $chat_id, '@' ) ) ? 'https://t.me/' . rawurlencode( substr( $chat_id, 1 ) ) . '/' . (int) $message_id : '';

		return Result::ok( '', $url );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$this->token = (string) $account->get( 'bot_token' );

		$bot  = $this->http( 'GET', $this->endpoint( 'getMe' ) );
		$chat = $this->http( 'GET', add_query_arg( 'chat_id', rawurlencode( (string) $account->get( 'chat_id' ) ), $this->endpoint( 'getChat' ) ) );

		return Result::ok(
			sprintf(
				/* translators: 1: bot name, 2: chat title */
				__( 'Bot @%1$s can reach "%2$s"', 'postvoyage-socialmedia-poster' ),
				$bot['body']['result']['username'] ?? '?',
				$chat['body']['result']['title'] ?? $chat['body']['result']['username'] ?? '?'
			)
		);
	}

	/**
	 * Bot API endpoint.
	 *
	 * @param string $method API method.
	 * @return string
	 */
	private function endpoint( $method ) {
		return 'https://api.telegram.org/bot' . $this->token . '/' . $method;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param string $text Text.
	 */
	protected function redact( $text ) {
		return '' !== $this->token ? str_replace( $this->token, '***', $text ) : $text;
	}
}
