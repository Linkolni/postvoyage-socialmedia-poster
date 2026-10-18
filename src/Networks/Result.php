<?php
/**
 * Result of a network request.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Outcome of publish() or test().
 */
final class Result {

	/**
	 * Whether the request succeeded.
	 *
	 * @var bool
	 */
	public $success;

	/**
	 * Human readable message (error text or info).
	 *
	 * @var string
	 */
	public $message;

	/**
	 * Link to the created post.
	 *
	 * @var string
	 */
	public $remote_url;

	/**
	 * Whether a later retry may succeed (timeouts, 5xx, rate limits).
	 *
	 * @var bool
	 */
	public $retryable;

	/**
	 * Constructor.
	 *
	 * @param bool   $success    Success.
	 * @param string $message    Message.
	 * @param string $remote_url Remote URL.
	 * @param bool   $retryable  Retryable.
	 */
	private function __construct( $success, $message, $remote_url = '', $retryable = false ) {
		$this->success    = (bool) $success;
		$this->message    = (string) $message;
		$this->remote_url = (string) $remote_url;
		$this->retryable  = (bool) $retryable;
	}

	/**
	 * Success.
	 *
	 * @param string $message    Info text.
	 * @param string $remote_url Link to the created post.
	 * @return self
	 */
	public static function ok( $message = '', $remote_url = '' ) {
		return new self( true, $message, $remote_url );
	}

	/**
	 * Failure.
	 *
	 * @param string $message   Error text.
	 * @param bool   $retryable Whether a retry may help.
	 * @return self
	 */
	public static function error( $message, $retryable = false ) {
		return new self( false, $message, '', $retryable );
	}
}
