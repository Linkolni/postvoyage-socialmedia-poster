<?php
/**
 * Tests for TextFitter.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PostVoyage\Content\TextFitter;

final class TextFitterTest extends TestCase {

	public function test_short_text_is_unchanged() {
		$this->assertSame( 'Hallo Welt', TextFitter::fit( '  Hallo Welt ', 280 ) );
	}

	public function test_zero_limit_means_unlimited() {
		$text = str_repeat( 'a ', 5000 );
		$this->assertSame( trim( $text ), TextFitter::fit( $text, 0 ) );
	}

	public function test_long_text_is_cut_at_word_boundary_with_ellipsis() {
		$text   = 'Ein ziemlich langer Satz mit vielen Wörtern, der nicht passt.';
		$result = TextFitter::fit( $text, 30 );
		$this->assertLessThanOrEqual( 30, TextFitter::strlen( $result ) );
		$this->assertStringEndsWith( '…', $result );
		$this->assertStringStartsWith( 'Ein ziemlich', $result );
	}

	public function test_links_are_never_cut() {
		$url    = 'https://example.com/ein/sehr/langer/pfad?utm_source=x&utm_medium=social';
		$text   = "Titel des Beitrags\n\n" . str_repeat( 'Lorem ipsum dolor sit amet. ', 40 ) . "\n\n" . $url . "\n\n#Tag";
		$result = TextFitter::fit( $text, 280 );
		$this->assertLessThanOrEqual( 280, TextFitter::strlen( $result ) );
		$this->assertStringContainsString( $url, $result );
		$this->assertStringContainsString( '#Tag', $result );
	}

	public function test_fixed_url_length_is_respected() {
		$url    = 'https://example.com/' . str_repeat( 'x', 200 );
		$text   = str_repeat( 'Wort ', 100 ) . $url;
		$result = TextFitter::fit( $text, 280, 23 );
		$this->assertLessThanOrEqual( 280, TextFitter::length( $result, 23 ) );
		$this->assertStringContainsString( $url, $result );
		// With 23 characters per link there is room for much more text than with the real length.
		$this->assertGreaterThan( 200, TextFitter::strlen( str_replace( $url, '', $result ) ) );
	}

	public function test_multibyte_and_emoji_count_as_one_character() {
		$this->assertSame( 5, TextFitter::strlen( 'Ümläu' ) );
		$this->assertSame( 3, TextFitter::strlen( '📚👍🏽!' ) );
	}

	public function test_result_never_exceeds_limit_for_various_limits() {
		$text = "📚 Überschrift mit „Zitat“\n\n" . str_repeat( 'Ein Satz mit Umlauten äöü. ', 50 ) . "\n\nhttps://example.org/beitrag?p=1\n\n#Eins #Zwei #Drei";
		foreach ( array( 120, 280, 300, 500, 1024 ) as $limit ) {
			$this->assertLessThanOrEqual( $limit, TextFitter::strlen( TextFitter::fit( $text, $limit ) ), "Limit $limit" );
		}
	}
}
