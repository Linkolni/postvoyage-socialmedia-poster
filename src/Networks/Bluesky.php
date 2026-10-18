<?php
/**
 * Bluesky (AT Protocol).
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Content\TextFitter;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Posts to Bluesky with an app password. Links and hashtags become clickable facets,
 * long links are shown shortened, and a link card with the featured image is attached.
 */
final class Bluesky extends AbstractNetwork {

	/** Display length of links in the post text. */
	const LINK_DISPLAY_LENGTH = 30;

	/** Bluesky's blob limit for images. */
	const MAX_IMAGE_BYTES = 976560;

	/**
	 * {@inheritDoc}
	 */
	public function id() {
		return 'bluesky';
	}

	/**
	 * {@inheritDoc}
	 */
	public function label() {
		return 'Bluesky';
	}

	/**
	 * {@inheritDoc}
	 */
	public function help() {
		return __( 'In Bluesky open Settings → Privacy and security → App passwords and create a new app password. Never use your main password.', 'postvoyage-socialmedia-poster' );
	}

	/**
	 * {@inheritDoc}
	 */
	public function fields() {
		return array(
			'identifier'   => $this->field(
				__( 'Handle', 'postvoyage-socialmedia-poster' ),
				'text',
				array(
					'required'    => true,
					'description' => __( 'e.g. name.bsky.social', 'postvoyage-socialmedia-poster' ),
				)
			),
			'app_password' => $this->field(
				__( 'App password', 'postvoyage-socialmedia-poster' ),
				'password',
				array(
					'required' => true,
					'secret'   => true,
				)
			),
			'service'      => $this->field(
				__( 'PDS server', 'postvoyage-socialmedia-poster' ),
				'url',
				array(
					'default'     => 'https://bsky.social',
					'description' => __( 'Only change this if you host your own PDS.', 'postvoyage-socialmedia-poster' ),
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
		return 300;
	}

	/**
	 * Links are displayed shortened, so they count with their display length.
	 */
	public function url_length() {
		return self::LINK_DISPLAY_LENGTH;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 */
	protected function send( Message $message, Account $account ) {
		$session  = $this->session( $account );
		$prepared = self::prepare_text( $message->text );

		$record = array(
			'$type'     => 'app.bsky.feed.post',
			'text'      => $prepared['text'],
			'createdAt' => gmdate( 'Y-m-d\TH:i:s.000\Z' ),
		);
		if ( $prepared['facets'] ) {
			$record['facets'] = $prepared['facets'];
		}
		if ( '' !== $message->language ) {
			$record['langs'] = array( $message->language );
		}

		if ( '' !== $message->url ) {
			$external = array(
				'uri'         => $message->url,
				'title'       => $message->title,
				'description' => $message->description,
			);
			$file     = $message->image_file( self::MAX_IMAGE_BYTES );
			if ( $file ) {
				$external['thumb'] = $this->upload_blob( $account, $session, $file );
			}
			$record['embed'] = array(
				'$type'    => 'app.bsky.embed.external',
				'external' => $external,
			);
		}

		$response = $this->http(
			'POST',
			$this->service( $account ) . '/xrpc/com.atproto.repo.createRecord',
			array(
				'headers' => array( 'Authorization' => 'Bearer ' . $session['accessJwt'] ),
				'json'    => array(
					'repo'       => $session['did'],
					'collection' => 'app.bsky.feed.post',
					'record'     => $record,
				),
			)
		);

		$uri  = (string) ( $response['body']['uri'] ?? '' );
		$rkey = substr( $uri, strrpos( $uri, '/' ) + 1 );
		$url  = '' !== $rkey ? 'https://bsky.app/profile/' . rawurlencode( $session['handle'] ) . '/post/' . rawurlencode( $rkey ) : '';

		return Result::ok( '', $url );
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 */
	protected function check( Account $account ) {
		$session = $this->session( $account );
		/* translators: %s: account name */
		return Result::ok( sprintf( __( 'Connected as @%s', 'postvoyage-socialmedia-poster' ), $session['handle'] ) );
	}

	/**
	 * Shortens displayed links and builds facets for links and hashtags.
	 * Facet offsets are UTF-8 byte offsets as required by the AT Protocol.
	 *
	 * @param string $text Post text.
	 * @return array{text:string,facets:array}
	 */
	public static function prepare_text( $text ) {
		$facets = array();
		$output = '';
		$offset = 0;

		// Links: replace with a short display text, link the full URL.
		if ( preg_match_all( TextFitter::URL_PATTERN, $text, $matches, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $matches[0] as $match ) {
				list( $url, $start ) = $match;
				$url                 = rtrim( $url, '.,;:!?)]' );
				$output             .= substr( $text, $offset, $start - $offset );
				$display             = self::display_url( $url );
				$facets[]            = array(
					'index'    => array(
						'byteStart' => strlen( $output ),
						'byteEnd'   => strlen( $output ) + strlen( $display ),
					),
					'features' => array(
						array(
							'$type' => 'app.bsky.richtext.facet#link',
							'uri'   => $url,
						),
					),
				);
				$output             .= $display;
				$offset              = $start + strlen( $url );
			}
		}
		$output .= substr( $text, $offset );

		// Hashtags (on the final text so offsets are correct).
		if ( preg_match_all( '~(?<=^|\s)#([\p{L}\p{N}_]*\p{L}[\p{L}\p{N}_]*)~u', $output, $tags, PREG_OFFSET_CAPTURE ) ) {
			foreach ( $tags[0] as $i => $tag ) {
				$facets[] = array(
					'index'    => array(
						'byteStart' => $tag[1],
						'byteEnd'   => $tag[1] + strlen( $tag[0] ),
					),
					'features' => array(
						array(
							'$type' => 'app.bsky.richtext.facet#tag',
							'tag'   => $tags[1][ $i ][0],
						),
					),
				);
			}
		}

		return array(
			'text'   => $output,
			'facets' => $facets,
		);
	}

	/**
	 * "https://example.com/very/long/path?utm=1" → "example.com/very/long/pa…".
	 *
	 * @param string $url URL.
	 * @return string
	 */
	public static function display_url( $url ) {
		$display = (string) preg_replace( '~^https?://(www\.)?~i', '', $url );
		if ( TextFitter::strlen( $display ) > self::LINK_DISPLAY_LENGTH ) {
			$display = TextFitter::substr( $display, 0, self::LINK_DISPLAY_LENGTH - 1 ) . '…';
		}
		return $display;
	}

	/**
	 * Logs in and returns the session.
	 *
	 * @param Account $account Account.
	 * @return array{accessJwt:string,did:string,handle:string}
	 * @throws NetworkException On failure.
	 */
	private function session( Account $account ) {
		$response = $this->http(
			'POST',
			$this->service( $account ) . '/xrpc/com.atproto.server.createSession',
			array(
				'json' => array(
					'identifier' => ltrim( (string) $account->get( 'identifier' ), '@' ),
					'password'   => (string) $account->get( 'app_password' ),
				),
			)
		);
		$body     = $response['body'];
		if ( empty( $body['accessJwt'] ) || empty( $body['did'] ) ) {
			throw new NetworkException( __( 'Login failed.', 'postvoyage-socialmedia-poster' ) );
		}
		return array(
			'accessJwt' => (string) $body['accessJwt'],
			'did'       => (string) $body['did'],
			'handle'    => (string) ( $body['handle'] ?? $body['did'] ),
		);
	}

	/**
	 * Uploads an image blob.
	 *
	 * @param Account $account Account.
	 * @param array   $session Session.
	 * @param array   $file    File from Message::image_file().
	 * @return array Blob reference.
	 * @throws NetworkException On failure.
	 */
	private function upload_blob( Account $account, array $session, array $file ) {
		// phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Local file.
		$data     = (string) file_get_contents( $file['path'] );
		$response = $this->http(
			'POST',
			$this->service( $account ) . '/xrpc/com.atproto.repo.uploadBlob',
			array(
				'headers' => array(
					'Authorization' => 'Bearer ' . $session['accessJwt'],
					'Content-Type'  => $file['mime'],
				),
				'body'    => $data,
				'timeout' => 60,
			)
		);
		if ( empty( $response['body']['blob'] ) ) {
			throw new NetworkException( __( 'Image upload failed.', 'postvoyage-socialmedia-poster' ), true );
		}
		return $response['body']['blob'];
	}

	/**
	 * PDS base URL.
	 *
	 * @param Account $account Account.
	 * @return string
	 */
	private function service( Account $account ) {
		return untrailingslashit( (string) $account->get( 'service', 'https://bsky.social' ) );
	}
}
