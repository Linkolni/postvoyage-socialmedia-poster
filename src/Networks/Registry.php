<?php
/**
 * Registry of available networks.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Networks;

defined( 'ABSPATH' ) || exit;

/**
 * Knows all networks. Third-party code can add networks with the "postvoyage_networks" filter.
 */
final class Registry {

	/**
	 * Cached instances.
	 *
	 * @var array<string,NetworkInterface>|null
	 */
	private static $networks = null;

	/**
	 * All networks keyed by ID.
	 *
	 * @return array<string,NetworkInterface>
	 */
	public static function all() {
		if ( null === self::$networks ) {
			$built_in = array(
				new Mastodon(),
				new Bluesky(),
				new Telegram(),
				new Discord(),
				new Facebook(),
				new Threads(),
				new LinkedIn(),
				new X(),
				new Webhook(),
			);

			/**
			 * Filters the available networks.
			 *
			 * @param NetworkInterface[] $networks Network instances.
			 */
			$networks = (array) apply_filters( 'postvoyage_networks', $built_in );

			self::$networks = array();
			foreach ( $networks as $network ) {
				if ( $network instanceof NetworkInterface ) {
					self::$networks[ $network->id() ] = $network;
				}
			}
		}
		return self::$networks;
	}

	/**
	 * Single network.
	 *
	 * @param string $id Network ID.
	 * @return NetworkInterface|null
	 */
	public static function get( $id ) {
		$all = self::all();
		return $all[ $id ] ?? null;
	}
}
