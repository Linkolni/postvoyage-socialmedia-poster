<?php
/**
 * Tests for the Bluesky text preparation (facets).
 *
 * @package PostVoyage
 */

namespace PostVoyage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PostVoyage\Networks\Bluesky;

final class BlueskyTest extends TestCase {

	public function test_link_is_shortened_and_linked_with_byte_offsets() {
		$url      = 'https://www.example.com/2026/10/ein-sehr-langer-beitragstitel/?utm_source=bluesky';
		$prepared = Bluesky::prepare_text( "Neu: Ümlaute 📚\n" . $url );

		$this->assertStringNotContainsString( $url, $prepared['text'] );
		$this->assertStringContainsString( 'example.com/2026/10/ein-sehr-…', $prepared['text'] );

		$facet = $prepared['facets'][0];
		$this->assertSame( $url, $facet['features'][0]['uri'] );

		$linked = substr( $prepared['text'], $facet['index']['byteStart'], $facet['index']['byteEnd'] - $facet['index']['byteStart'] );
		$this->assertSame( Bluesky::display_url( $url ), $linked );
	}

	public function test_hashtags_become_tag_facets() {
		$prepared = Bluesky::prepare_text( 'Text mit #Bücher und #E_Book, aber nicht #2026 oder a#b' );
		$tags     = array();
		foreach ( $prepared['facets'] as $facet ) {
			$tags[] = $facet['features'][0]['tag'];
			$this->assertSame(
				'#' . $facet['features'][0]['tag'],
				substr( $prepared['text'], $facet['index']['byteStart'], $facet['index']['byteEnd'] - $facet['index']['byteStart'] )
			);
		}
		$this->assertSame( array( 'Bücher', 'E_Book' ), $tags );
	}

	public function test_trailing_punctuation_is_not_part_of_link() {
		$prepared = Bluesky::prepare_text( 'Siehe https://example.com/a.' );
		$this->assertSame( 'https://example.com/a', $prepared['facets'][0]['features'][0]['uri'] );
		$this->assertStringEndsWith( 'example.com/a.', $prepared['text'] );
	}
}
