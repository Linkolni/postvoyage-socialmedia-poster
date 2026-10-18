<?php
/**
 * Account value object.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Storage;

defined( 'ABSPATH' ) || exit;

/**
 * One connected social media account (e.g. a Mastodon profile or a Telegram channel).
 * Values are always held decrypted in memory.
 */
final class Account {

	/**
	 * Unique ID.
	 *
	 * @var string
	 */
	public $id;

	/**
	 * Network ID, e.g. "mastodon".
	 *
	 * @var string
	 */
	public $network;

	/**
	 * Name shown in the admin.
	 *
	 * @var string
	 */
	public $label;

	/**
	 * Whether the account receives posts.
	 *
	 * @var bool
	 */
	public $enabled;

	/**
	 * Field values as defined by the network (decrypted).
	 *
	 * @var array<string,mixed>
	 */
	public $values;

	/**
	 * Constructor.
	 *
	 * @param string              $id      ID.
	 * @param string              $network Network ID.
	 * @param string              $label   Label.
	 * @param bool                $enabled Enabled.
	 * @param array<string,mixed> $values  Field values.
	 */
	public function __construct( $id, $network, $label, $enabled, array $values ) {
		$this->id      = (string) $id;
		$this->network = (string) $network;
		$this->label   = (string) $label;
		$this->enabled = (bool) $enabled;
		$this->values  = $values;
	}

	/**
	 * Returns a field value.
	 *
	 * @param string $key      Field key.
	 * @param mixed  $fallback Value if not set.
	 * @return mixed
	 */
	public function get( $key, $fallback = '' ) {
		return isset( $this->values[ $key ] ) && '' !== $this->values[ $key ] ? $this->values[ $key ] : $fallback;
	}
}
