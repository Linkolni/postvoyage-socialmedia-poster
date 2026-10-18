<?php
/**
 * Contract for social networks.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

use PostVoyage\Content\Message;
use PostVoyage\Storage\Account;

defined( 'ABSPATH' ) || exit;

/**
 * Every network (built-in or added via the "postvoyage_networks" filter) implements this.
 */
interface NetworkInterface {

	/**
	 * Unique slug, e.g. "mastodon".
	 *
	 * @return string
	 */
	public function id();

	/**
	 * Display name.
	 *
	 * @return string
	 */
	public function label();

	/**
	 * Short help text on how to get the credentials (may contain links).
	 *
	 * @return string
	 */
	public function help();

	/**
	 * Account fields: key => array( label, type, required, secret, default, description, options ).
	 * Types: text, password, url, number, select, checkbox.
	 *
	 * @return array<string,array<string,mixed>>
	 */
	public function fields();

	/**
	 * Character limit of a post, 0 = unlimited.
	 *
	 * @param Account $account   Account.
	 * @param bool    $has_image Whether an image is attached.
	 * @return int
	 */
	public function char_limit( Account $account, $has_image );

	/**
	 * Fixed length a link counts as (e.g. 23 on Mastodon and X), 0 = real length.
	 *
	 * @return int
	 */
	public function url_length();

	/**
	 * Publishes a message.
	 *
	 * @param Message $message Message.
	 * @param Account $account Account.
	 * @return Result
	 */
	public function publish( Message $message, Account $account );

	/**
	 * Checks the credentials without posting anything.
	 *
	 * @param Account $account Account.
	 * @return Result
	 */
	public function test( Account $account );

	/**
	 * Refreshes expiring credentials (called weekly).
	 *
	 * @param Account $account Account.
	 * @return array<string,mixed>|null Changed values, or null if nothing changed.
	 */
	public function refresh_credentials( Account $account );
}
