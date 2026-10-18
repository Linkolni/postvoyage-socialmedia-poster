<?php
/**
 * Message value object.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Everything a network needs to publish one post: the final text plus link data and image.
 */
final class Message {

	/**
	 * Final text, already fitted to the network's character limit.
	 *
	 * @var string
	 */
	public $text = '';

	/**
	 * Link to the post (with UTM parameters if enabled).
	 *
	 * @var string
	 */
	public $url = '';

	/**
	 * Post title (plain text).
	 *
	 * @var string
	 */
	public $title = '';

	/**
	 * Short description (plain text, max ~300 characters) for link cards.
	 *
	 * @var string
	 */
	public $description = '';

	/**
	 * Attachment ID of the image to share, 0 if none.
	 *
	 * @var int
	 */
	public $image_id = 0;

	/**
	 * Two-letter language code, e.g. "de".
	 *
	 * @var string
	 */
	public $language = '';

	/**
	 * Post ID.
	 *
	 * @var int
	 */
	public $post_id = 0;

	/**
	 * Hashtags without "#".
	 *
	 * @var string[]
	 */
	public $hashtags = array();

	/**
	 * Whether an image is available.
	 *
	 * @return bool
	 */
	public function has_image() {
		return $this->image_id > 0;
	}

	/**
	 * Local image file not larger than $max_bytes.
	 *
	 * @param int $max_bytes Size limit in bytes, 0 = no limit.
	 * @return array{path:string,mime:string,url:string,alt:string}|null
	 */
	public function image_file( $max_bytes = 0 ) {
		return $this->has_image() ? Media::best_file( $this->image_id, $max_bytes ) : null;
	}

	/**
	 * Public image URL, or '' if the site is not publicly reachable (e.g. localhost).
	 *
	 * @return string
	 */
	public function public_image_url() {
		$file = $this->image_file();
		return ( $file && Media::is_public_url( $file['url'] ) ) ? $file['url'] : '';
	}
}
