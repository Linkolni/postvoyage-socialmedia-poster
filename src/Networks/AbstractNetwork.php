<?php
/**
 * Base class for networks.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Shared HTTP handling and error conversion. Subclasses implement send() and check()
 * and may throw NetworkException anywhere.
 */
abstract class AbstractNetwork implements NetworkInterface {

	/**
	 * Sends the message. May throw NetworkException.
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 * @return Result
	 */
	abstract protected function send( Message $message, Account $account );

	/**
	 * Verifies credentials. May throw NetworkException.
	 *
	 * @param Account $account Account.
	 * @return Result
	 */
	abstract protected function check( Account $account );

	/**
	 * {@inheritDoc}
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 * @return Result
	 */
	final public function publish( Message $message, Account $account ) {
		try {
			return $this->send( $message, $account );
		} catch ( NetworkException $e ) {
			return Result::error( $e->getMessage(), $e->is_retryable() );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 * @return Result
	 */
	final public function test( Account $account ) {
		try {
			return $this->check( $account );
		} catch ( NetworkException $e ) {
			return Result::error( $e->getMessage() );
		}
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return string
	 */
	public function help() {
		return '';
	}

	/**
	 * {@inheritDoc}
	 *
	 * @return int
	 */
	public function url_length() {
		return 0;
	}

	/**
	 * {@inheritDoc}
	 *
	 * @param Account $account Account.
	 * @return array<string,mixed>|null
	 */
	public function refresh_credentials( Account $account ) {
		return null;
	}

	/**
	 * Performs an HTTP request.
	 *
	 * Args: 'headers' (array), 'json' (array, sent as JSON body), 'body' (string|array), 'timeout' (int).
	 *
	 * @param string $method HTTP method.
	 * @param string $url    URL.
	 * @param array  $args   Arguments.
	 * @return array{code:int,body:mixed,headers:mixed}
	 * @throws NetworkException On transport errors and HTTP status >= 400.
	 */
	protected function http( $method, $url, array $args = array() ) {
		$headers = $args['headers'] ?? array();
		$body    = $args['body'] ?? null;

		if ( isset( $args['json'] ) ) {
			$headers['Content-Type'] = 'application/json';
			$body                    = wp_json_encode( $args['json'] );
		}

		$response = wp_remote_request(
			$url,
			array(
				'method'     => $method,
				'headers'    => $headers,
				'body'       => $body,
				'timeout'    => $args['timeout'] ?? 20,
				'user-agent' => 'PostVoyage/' . POSTVOYAGE_VERSION . '; ' . home_url( '/' ),
			)
		);

		if ( is_wp_error( $response ) ) {
			throw new NetworkException( $this->redact( $response->get_error_message() ), true );
		}

		$code    = (int) wp_remote_retrieve_response_code( $response );
		$raw     = (string) wp_remote_retrieve_body( $response );
		$decoded = json_decode( $raw, true );
		$data    = null === $decoded ? $raw : $decoded;

		if ( $code >= 400 ) {
			$detail = $this->error_text( $data );
			throw new NetworkException(
				sprintf( 'HTTP %d: %s', $code, '' !== $detail ? $detail : wp_remote_retrieve_response_message( $response ) ),
				$code >= 500 || 429 === $code
			);
		}

		return array(
			'code'    => $code,
			'body'    => $data,
			'headers' => wp_remote_retrieve_headers( $response ),
		);
	}

	/**
	 * Extracts a readable error from typical API error responses.
	 *
	 * @param mixed $data Decoded body.
	 * @return string
	 */
	protected function error_text( $data ) {
		if ( is_string( $data ) ) {
			return $this->redact( mb_substr( wp_strip_all_tags( $data ), 0, 300 ) );
		}
		if ( ! is_array( $data ) ) {
			return '';
		}
		$candidates = array(
			$data['error']['message'] ?? null,
			$data['error_description'] ?? null,
			$data['detail'] ?? null,
			$data['message'] ?? null,
			$data['description'] ?? null,
			$data['errors'][0]['message'] ?? null,
			$data['errors'][0]['detail'] ?? null,
			$data['title'] ?? null,
			is_string( $data['error'] ?? null ) ? $data['error'] : null,
		);
		foreach ( $candidates as $candidate ) {
			if ( is_string( $candidate ) && '' !== $candidate ) {
				return $this->redact( mb_substr( $candidate, 0, 300 ) );
			}
		}
		return '';
	}

	/**
	 * Removes secrets from texts before they are logged. Override where tokens appear in URLs.
	 *
	 * @param string $text Text.
	 * @return string
	 */
	protected function redact( $text ) {
		return $text;
	}

	/**
	 * Standard field definition.
	 *
	 * @param string $label       Label.
	 * @param string $type        Type.
	 * @param array  $extra       Further keys (required, secret, default, description, options).
	 * @return array<string,mixed>
	 */
	protected function field( $label, $type = 'text', array $extra = array() ) {
		return array_merge(
			array(
				'label'       => $label,
				'type'        => $type,
				'required'    => false,
				'secret'      => false,
				'default'     => '',
				'description' => '',
				'options'     => array(),
			),
			$extra
		);
	}
}
