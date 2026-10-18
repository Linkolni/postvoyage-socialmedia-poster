<?php
/**
 * PHPUnit bootstrap for pure unit tests (no WordPress needed).
 *
 * @package PostVoyage
 */

// Source files bail out without ABSPATH.
define( 'ABSPATH', __DIR__ . '/' );

require dirname( __DIR__ ) . '/vendor/autoload.php';

spl_autoload_register(
	static function ( $class_name ) {
		if ( 0 !== strpos( $class_name, 'PostVoyage\\' ) || 0 === strpos( $class_name, 'PostVoyage\\Tests\\' ) ) {
			return;
		}
		$file = dirname( __DIR__ ) . '/src/' . str_replace( '\\', '/', substr( $class_name, strlen( 'PostVoyage\\' ) ) ) . '.php';
		if ( is_readable( $file ) ) {
			require $file;
		}
	}
);
