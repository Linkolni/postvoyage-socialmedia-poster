<?php
/**
 * Tests for OAuth1.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PostVoyage\Http\OAuth1;

final class OAuth1Test extends TestCase {

	/**
	 * Example from the X/Twitter developer documentation "Creating a signature".
	 */
	public function test_signature_matches_documented_example() {
		$params = array(
			'status'                 => 'Hello Ladies + Gentlemen, a signed OAuth request!',
			'include_entities'       => 'true',
			'oauth_consumer_key'     => 'xvz1evFS4wEEPTGEFPHBog',
			'oauth_nonce'            => 'kYjzVBB8Y0ZFabxSWbWovY3uYSQ2pTgmZeNu2VS4cg',
			'oauth_signature_method' => 'HMAC-SHA1',
			'oauth_timestamp'        => '1318622958',
			'oauth_token'            => '370773112-GmHxMAgYyLbNEtIKZeRNFsMKPR9EyMZeS9weJAEb',
			'oauth_version'          => '1.0',
		);

		$signature = OAuth1::signature(
			'POST',
			'https://api.twitter.com/1.1/statuses/update.json',
			$params,
			'kAcSOqF21Fu85e7zjz7ZN2U4ZRhfV3WpwPAoE3Z7kBw',
			'LswwdoUaIvS8ltyTt5jkRh4J50vUPVVHtR2YPi5kE'
		);

		$this->assertSame( 'hCtSmYh+iHYCEqBWrE7C7hYmtUk=', $signature );
	}

	public function test_header_contains_all_oauth_fields() {
		$header = OAuth1::header( 'POST', 'https://api.x.com/2/tweets', 'key', 'secret', 'token', 'tsecret', array(), 'nonce', 1700000000 );
		$this->assertStringStartsWith( 'OAuth ', $header );
		foreach ( array( 'oauth_consumer_key="key"', 'oauth_nonce="nonce"', 'oauth_timestamp="1700000000"', 'oauth_token="token"', 'oauth_signature=' ) as $part ) {
			$this->assertStringContainsString( $part, $header );
		}
	}
}
