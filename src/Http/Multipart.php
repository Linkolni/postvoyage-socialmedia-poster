<?php
/**
 * Builder for multipart/form-data bodies.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Http;

defined( 'ABSPATH' ) || exit;

/**
 * Builds multipart bodies for file uploads, which wp_remote_post() cannot do on its own.
 * Pure PHP, unit tested.
 */
final class Multipart {

	/**
	 * Builds a multipart body.
	 *
	 * @param array<string,string>                                         $fields   Plain fields.
	 * @param array<string,array{filename:string,mime:string,data:string}> $files    Files by field name.
	 * @param string                                                       $boundary Boundary (generated if empty).
	 * @return array{body:string,content_type:string}
	 */
	public static function build( array $fields, array $files, $boundary = '' ) {
		if ( '' === $boundary ) {
			$boundary = 'postvoyage-' . bin2hex( random_bytes( 12 ) );
		}

		$eol  = "\r\n";
		$body = '';

		foreach ( $fields as $name => $value ) {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="' . self::quote( $name ) . '"' . $eol . $eol;
			$body .= $value . $eol;
		}

		foreach ( $files as $name => $file ) {
			$body .= '--' . $boundary . $eol;
			$body .= 'Content-Disposition: form-data; name="' . self::quote( $name ) . '"; filename="' . self::quote( $file['filename'] ) . '"' . $eol;
			$body .= 'Content-Type: ' . $file['mime'] . $eol . $eol;
			$body .= $file['data'] . $eol;
		}

		$body .= '--' . $boundary . '--' . $eol;

		return array(
			'body'         => $body,
			'content_type' => 'multipart/form-data; boundary=' . $boundary,
		);
	}

	/**
	 * Escapes quotes and line breaks in header parameters.
	 *
	 * @param string $value Value.
	 * @return string
	 */
	private static function quote( $value ) {
		return str_replace( array( '"', "\r", "\n" ), array( '%22', '', '' ), (string) $value );
	}
}
