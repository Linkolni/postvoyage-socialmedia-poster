<?php
/**
 * Tests for Hashtags and the Multipart builder.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Tests\Unit;

use PHPUnit\Framework\TestCase;
use PostVoyage\Content\Hashtags;
use PostVoyage\Http\Multipart;
use PostVoyage\Networks\LinkedIn;

final class HashtagsTest extends TestCase {

	public function test_names_become_camel_case_tags() {
		$this->assertSame(
			array( 'SocialMedia', 'EBook', 'Bücher', 'ScienceFiction' ),
			Hashtags::from_names( array( 'Social Media', 'E-Book', 'Bücher', 'science fiction', 'Science Fiction', '2026', '' ) )
		);
	}

	public function test_format() {
		$this->assertSame( '#A #B', Hashtags::format( array( 'A', 'B' ) ) );
		$this->assertSame( '', Hashtags::format( array() ) );
	}

	public function test_multipart_body() {
		$result = Multipart::build(
			array( 'caption' => 'Hallo' ),
			array(
				'photo' => array(
					'filename' => 'a.jpg',
					'mime'     => 'image/jpeg',
					'data'     => 'BINARY',
				),
			),
			'b'
		);
		$this->assertSame( 'multipart/form-data; boundary=b', $result['content_type'] );
		$this->assertSame(
			"--b\r\nContent-Disposition: form-data; name=\"caption\"\r\n\r\nHallo\r\n" .
			"--b\r\nContent-Disposition: form-data; name=\"photo\"; filename=\"a.jpg\"\r\nContent-Type: image/jpeg\r\n\r\nBINARY\r\n--b--\r\n",
			$result['body']
		);
	}

	public function test_linkedin_escapes_reserved_characters() {
		$this->assertSame( 'Neu \\(Teil 1\\) \\#Tag \\@Name a\\_b', LinkedIn::escape( 'Neu (Teil 1) #Tag @Name a_b' ) );
	}
}
