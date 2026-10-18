<?php
/**
 * Exception for failed network requests.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Thrown inside networks; AbstractNetwork turns it into a Result.
 */
class NetworkException extends \RuntimeException {

	/**
	 * Whether a retry may succeed.
	 *
	 * @var bool
	 */
	private $retryable;

	/**
	 * Constructor.
	 *
	 * @param string $message   Error text.
	 * @param bool   $retryable Retryable.
	 */
	public function __construct( $message, $retryable = false ) {
		parent::__construct( $message );
		$this->retryable = (bool) $retryable;
	}

	/**
	 * Whether a retry may succeed.
	 *
	 * @return bool
	 */
	public function is_retryable() {
		return $this->retryable;
	}
}
