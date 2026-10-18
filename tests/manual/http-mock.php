<?php
/**
 * TEST ONLY – never on a live site.
 *
 * Copy to wp-content/mu-plugins/ to intercept the API calls of all PostVoyage networks.
 * Requests are written to wp-content/http-mock.jsonl and answered with fake responses,
 * so nothing is posted for real. Remove the file after testing.
 *
 * @package PostVoyage
 */

define( 'POSTVOYAGE_HTTP_MOCK', true );
add_filter( 'pre_http_request', function ( $pre, $args, $url ) {
	$log = WP_CONTENT_DIR . '/http-mock.jsonl';
	$body = is_string( $args['body'] ?? '' ) ? (string) $args['body'] : wp_json_encode( $args['body'] );
	file_put_contents( $log, wp_json_encode( array(
		'method'  => $args['method'],
		'url'     => $url,
		'headers' => $args['headers'],
		'body'    => strlen( $body ) > 4000 ? substr( $body, 0, 300 ) . '…[' . strlen( $body ) . ' bytes]' : $body,
	) ) . "\n", FILE_APPEND );

	$json = function ( $data, $code = 200, $headers = array() ) {
		return array( 'headers' => $headers, 'body' => wp_json_encode( $data ), 'response' => array( 'code' => $code, 'message' => 'OK' ), 'cookies' => array(), 'filename' => null );
	};
	$host = wp_parse_url( $url, PHP_URL_HOST );
	$path = (string) wp_parse_url( $url, PHP_URL_PATH );

	if ( 'fail.example.org' === $host ) return $json( array( 'error' => 'Service down' ), 503 );
	if ( 'mastodon.example' === $host ) {
		if ( str_contains( $path, '/media' ) ) return $json( array( 'id' => 'media1' ) );
		if ( str_contains( $path, 'verify_credentials' ) ) return $json( array( 'acct' => 'tester' ) );
		return $json( array( 'url' => 'https://mastodon.example/@tester/111' ) );
	}
	if ( 'bsky.social' === $host ) {
		if ( str_contains( $path, 'createSession' ) ) return $json( array( 'accessJwt' => 'jwt', 'did' => 'did:plc:abc', 'handle' => 'tester.bsky.social' ) );
		if ( str_contains( $path, 'uploadBlob' ) ) return $json( array( 'blob' => array( '$type' => 'blob', 'ref' => array( '$link' => 'bafk' ), 'mimeType' => 'image/jpeg', 'size' => 1234 ) ) );
		return $json( array( 'uri' => 'at://did:plc:abc/app.bsky.feed.post/3kxyz', 'cid' => 'c' ) );
	}
	if ( 'api.telegram.org' === $host ) return $json( array( 'ok' => true, 'result' => array( 'message_id' => 42, 'username' => 'pvbot', 'title' => 'Channel' ) ) );
	if ( 'discord.com' === $host ) return $json( array( 'id' => '9', 'channel_id' => '8', 'guild_id' => '7', 'name' => 'Hook' ) );
	if ( 'graph.facebook.com' === $host ) return $json( array( 'id' => '123_456', 'name' => 'Page' ) );
	if ( 'graph.threads.net' === $host ) {
		if ( str_ends_with( $path, '/threads' ) ) return $json( array( 'id' => 'container1' ) );
		if ( str_ends_with( $path, '/threads_publish' ) ) return $json( array( 'id' => 'media1' ) );
		return $json( array( 'permalink' => 'https://www.threads.net/@tester/post/abc', 'username' => 'tester' ) );
	}
	if ( 'api.linkedin.com' === $host ) {
		if ( str_contains( $path, 'userinfo' ) ) return $json( array( 'sub' => 'abc123', 'name' => 'Alex' ) );
		return $json( array(), 201, array( 'x-restli-id' => 'urn:li:share:777' ) );
	}
	if ( 'api.x.com' === $host ) return $json( array( 'data' => array( 'id' => '999', 'username' => 'tester' ) ), 201 );
	if ( 'hooks.example.org' === $host ) return $json( array( 'ok' => true ) );
	return $pre;
}, 10, 3 );
