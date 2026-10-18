<?php
/**
 * Smoke test against a local WordPress. Run with:
 *   wp eval-file wp-content/plugins/postvoyage-socialmedia-poster/tests/manual/smoke.php
 *
 * Requires tests/manual/http-mock.php as mu-plugin (nothing is posted for real).
 * PostVoyage accounts, rules and settings are backed up and restored; test posts,
 * terms and the test image are deleted at the end.
 *
 * @package PostVoyage
 */

if ( ! defined( 'POSTVOYAGE_HTTP_MOCK' ) ) {
	WP_CLI::error( 'HTTP mock not active. Copy tests/manual/http-mock.php to wp-content/mu-plugins/ first.' );
}

use PostVoyage\Storage\AccountRepository;
use PostVoyage\Storage\RuleRepository;
use PostVoyage\Storage\PostMeta;
use PostVoyage\Distribution\Distributor;
use PostVoyage\Log\Logger;

$backup = array();
foreach ( array( 'postvoyage_accounts', 'postvoyage_rules', 'postvoyage_settings' ) as $option ) {
	$backup[ $option ] = get_option( $option );
}
$created_posts = array();
$created_terms = array();

$term = static function ( $name, $parent = 0 ) use ( &$created_terms ) {
	$existing = term_exists( $name, 'category', $parent );
	if ( $existing ) {
		return array( 'term_id' => (int) $existing['term_id'] );
	}
	$new             = wp_insert_term( $name, 'category', array( 'parent' => $parent ) );
	$created_terms[] = (int) $new['term_id'];
	return $new;
};

@unlink( WP_CONTENT_DIR . '/http-mock.jsonl' );
update_option( 'postvoyage_accounts', array() );
update_option( 'postvoyage_settings', array_merge( PostVoyage\Storage\Settings::defaults(), array( 'delay' => 0 ) ) );

$defs = array(
	'mastodon' => array( 'instance' => 'https://mastodon.example', 'access_token' => 'tok-m', 'visibility' => 'public', 'char_limit' => '500', 'attach_image' => '1' ),
	'bluesky'  => array( 'identifier' => '@tester.bsky.social', 'app_password' => 'app-pw', 'service' => '' ),
	'telegram' => array( 'bot_token' => '123:SECRET', 'chat_id' => '@pvchannel', 'send_image' => '1' ),
	'discord'  => array( 'webhook_url' => 'https://discord.com/api/webhooks/1/abc', 'use_embed' => '1' ),
	'facebook' => array( 'page_id' => '555', 'access_token' => 'fb-tok' ),
	'threads'  => array( 'access_token' => 'th-tok' ),
	'linkedin' => array( 'access_token' => 'li-tok' ),
	'x'        => array( 'api_key' => 'k', 'api_secret' => 's', 'access_token' => 't', 'access_token_secret' => 'ts' ),
	'webhook'  => array( 'url' => 'https://hooks.example.org/in', 'secret' => 'shh' ),
);
$ids = array();
foreach ( $defs as $network => $values ) {
	$acc = AccountRepository::from_input( $network, array( 'label' => ucfirst( $network ) . ' Test', 'enabled' => '1', 'values' => $values ) );
	if ( is_wp_error( $acc ) ) { echo "ERR $network: " . $acc->get_error_message() . "\n"; continue; }
	AccountRepository::save( $acc );
	$ids[ $network ] = $acc->id;
}
// failing account for retry test.
$fail = AccountRepository::from_input( 'webhook', array( 'label' => 'Failing hook', 'enabled' => '1', 'values' => array( 'url' => 'https://fail.example.org/x' ) ) );
AccountRepository::save( $fail );

// Stored secrets must be encrypted.
$raw = get_option( 'postvoyage_accounts' );
echo 'Mastodon token stored as: ' . substr( $raw[ $ids['mastodon'] ]['values']['access_token'], 0, 8 ) . "...\n";
echo 'Decrypted: ' . AccountRepository::find( $ids['mastodon'] )->get( 'access_token' ) . "\n";

foreach ( AccountRepository::all() as $a ) {
	$r = PostVoyage\Networks\Registry::get( $a->network )->test( $a );
	printf( "TEST %-14s %s %s\n", $a->label, $r->success ? 'OK ' : 'ERR', $r->message );
}

// Categories and rules.
$books = $term( 'PV Test Bücher' );
$scifi = $term( 'PV Test Science Fiction', $books['term_id'] );
$other = $term( 'PV Test Intern' );
update_option( 'postvoyage_rules', array() );
RuleRepository::save( RuleRepository::sanitize( array(
	'name' => 'Bücher → alle', 'enabled' => '1', 'categories' => array( $books['term_id'] ), 'include_children' => '1', 'attach_image' => '1',
	'template' => "📚 {title}\n\n{excerpt}\n\n{url}\n\n{hashtags} {category_hashtags}",
	'templates' => array( 'x' => "{title} {url} {hashtags}" ),
) ) );
RuleRepository::save( RuleRepository::sanitize( array(
	'name' => 'Intern → nur Webhook', 'enabled' => '1', 'categories' => array( $other['term_id'] ), 'accounts' => array( $ids['webhook'] ), 'template' => 'Intern: {title} {url}',
) ) );

// Image.
$img = imagecreatetruecolor( 1200, 630 ); imagefill( $img, 0, 0, imagecolorallocate( $img, 30, 120, 200 ) );
$tmp = wp_tempnam( 'pv' ) . '.jpg'; imagejpeg( $img, $tmp, 85 );
require_once ABSPATH . 'wp-admin/includes/media.php'; require_once ABSPATH . 'wp-admin/includes/file.php'; require_once ABSPATH . 'wp-admin/includes/image.php';
$att = media_handle_sideload( array( 'name' => 'cover.jpg', 'tmp_name' => $tmp ), 0 );
update_post_meta( $att, '_wp_attachment_image_alt', 'Blaues Cover' );

$long = str_repeat( 'Dies ist ein langer Absatz über ein großartiges Buch mit Ümlauten & „Anführungszeichen“. ', 30 );
$post_id = wp_insert_post( array(
	'post_title' => 'Die Reise der „PostVoyage“ – ein Test mit Ümlauten',
	'post_content' => "<!-- wp:paragraph --><p>$long</p><!-- /wp:paragraph -->",
	'post_status' => 'draft', 'post_category' => array( $scifi['term_id'] ), 'tags_input' => array( 'Social Media', 'E-Book', '2026', 'Lesen' ),
) );
set_post_thumbnail( $post_id, $att );
$created_posts[] = $post_id;

wp_publish_post( $post_id );
echo 'Queued after publish: ' . ( wp_next_scheduled( 'postvoyage_distribute', array( $post_id ) ) ? 'yes' : 'NO' ) . "\n";

// Run the cron job directly.
do_action( 'postvoyage_distribute', $post_id );
do_action( 'postvoyage_distribute', $post_id ); // second run must not double post.

$log = Logger::query( array( 'per_page' => 50 ) );
echo "Log entries: {$log['total']}\n";
foreach ( array_reverse( $log['rows'] ) as $row ) {
	printf( "  %-7s %-14s %s %s\n", $row->status, $row->account_label, $row->remote_url, $row->message );
}
echo 'Retry scheduled for failing account: ' . ( wp_next_scheduled( 'postvoyage_retry', array( $post_id, $fail->id, 2 ) ) ? 'yes' : 'NO' ) . "\n";

// Post in "Intern" → only webhook.
$p2 = wp_insert_post( array( 'post_title' => 'Interner Post', 'post_content' => 'x', 'post_status' => 'publish', 'post_category' => array( $other['term_id'] ) ) );
$created_posts[] = $p2;
do_action( 'postvoyage_distribute', $p2 );
$shares = PostMeta::shares( $p2 );
echo 'Intern post shared to: ' . implode( ', ', array_map( function ( $id ) { return AccountRepository::find( $id )->label; }, array_keys( $shares ) ) ) . "\n";

// Post with sharing disabled.
$p3 = wp_insert_post( array( 'post_title' => 'Nicht teilen', 'post_content' => 'x', 'post_status' => 'draft', 'post_category' => array( $books['term_id'] ) ) );
$created_posts[] = $p3;
update_post_meta( $p3, PostMeta::ENABLED, '0' );
wp_publish_post( $p3 );
do_action( 'postvoyage_distribute', $p3 );
echo 'Disabled post shares: ' . count( PostMeta::shares( $p3 ) ) . "\n";

// Manual share now with custom message.
update_post_meta( $p3, PostMeta::MESSAGE, 'Manuell: {title} {url}' );
$res = Distributor::distribute( $p3, array( $ids['mastodon'] ), true );
echo 'Manual share: ' . count( $res ) . ' result(s), success=' . ( reset( $res )->success ? 'yes' : 'no' ) . "\n";

// Clean up.
wp_clear_scheduled_hook( 'postvoyage_retry', array( $post_id, $fail->id, 2 ) );
foreach ( $created_posts as $id ) {
	wp_delete_post( $id, true );
}
wp_delete_attachment( $att, true );
foreach ( $created_terms as $id ) {
	wp_delete_term( $id, 'category' );
}
foreach ( $backup as $option => $value ) {
	false === $value ? delete_option( $option ) : update_option( $option, $value, false );
}
WP_CLI::success( 'Smoke test finished. Requests: wp-content/http-mock.jsonl, details: PostVoyage → Log.' );
