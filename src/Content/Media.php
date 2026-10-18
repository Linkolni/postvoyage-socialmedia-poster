<?php
/**
 * Image helpers.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Content;

defined( 'ABSPATH' ) || exit;

/**
 * Finds a suitable local image file for upload.
 */
final class Media {

	/**
	 * Returns the largest image size of an attachment that fits into $max_bytes.
	 *
	 * @param int $attachment_id Attachment ID.
	 * @param int $max_bytes     Size limit in bytes, 0 = no limit.
	 * @return array{path:string,mime:string,url:string,alt:string}|null
	 */
	public static function best_file( $attachment_id, $max_bytes = 0 ) {
		if ( ! wp_attachment_is_image( $attachment_id ) ) {
			return null;
		}

		$alt   = trim( (string) get_post_meta( $attachment_id, '_wp_attachment_image_alt', true ) );
		$sizes = array( 'full', 'large', 'medium_large', 'medium' );

		foreach ( $sizes as $size ) {
			$path = 'full' === $size ? get_attached_file( $attachment_id ) : self::size_path( $attachment_id, $size );
			if ( ! $path || ! is_readable( $path ) ) {
				continue;
			}
			if ( $max_bytes > 0 && filesize( $path ) > $max_bytes ) {
				continue;
			}
			$src  = wp_get_attachment_image_src( $attachment_id, $size );
			$type = wp_check_filetype( $path );
			return array(
				'path' => $path,
				'mime' => $type['type'] ? $type['type'] : 'image/jpeg',
				'url'  => $src ? $src[0] : '',
				'alt'  => $alt,
			);
		}

		return null;
	}

	/**
	 * Whether a URL can be fetched by external services (not localhost, .test, .local, private IP).
	 *
	 * @param string $url URL.
	 * @return bool
	 */
	public static function is_public_url( $url ) {
		$host = (string) wp_parse_url( $url, PHP_URL_HOST );
		if ( '' === $host || 'localhost' === $host ) {
			return false;
		}
		foreach ( array( '.test', '.local', '.localhost', '.invalid', '.example' ) as $tld ) {
			if ( substr( $host, -strlen( $tld ) ) === $tld ) {
				return false;
			}
		}
		if ( filter_var( $host, FILTER_VALIDATE_IP ) ) {
			return (bool) filter_var( $host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE );
		}
		return true;
	}

	/**
	 * File path of an intermediate image size.
	 *
	 * @param int    $attachment_id Attachment ID.
	 * @param string $size          Size name.
	 * @return string
	 */
	private static function size_path( $attachment_id, $size ) {
		$data = wp_get_attachment_metadata( $attachment_id );
		if ( empty( $data['sizes'][ $size ]['file'] ) ) {
			return '';
		}
		$original = get_attached_file( $attachment_id );
		return $original ? path_join( dirname( $original ), $data['sizes'][ $size ]['file'] ) : '';
	}
}
