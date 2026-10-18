<?php
/**
 * Renders post templates with variables.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Content;

use PostVoyage\Networks\NetworkInterface;
use PostVoyage\Storage\Account;
use PostVoyage\Storage\Settings;
use WP_Post;

defined( 'ABSPATH' ) || exit;

/**
 * Replaces {variables} in templates with post data and builds the final Message.
 */
final class TemplateEngine {

	/**
	 * Available variables with a description, for the admin UI.
	 *
	 * @return array<string,string>
	 */
	public static function variables_help() {
		$help = array(
			'{title}'             => __( 'Post title', 'postvoyage-socialmedia-poster' ),
			'{excerpt}'           => __( 'Excerpt (or the beginning of the content)', 'postvoyage-socialmedia-poster' ),
			'{content}'           => __( 'Full content as plain text', 'postvoyage-socialmedia-poster' ),
			'{url}'               => __( 'Link to the post (with UTM parameters if enabled)', 'postvoyage-socialmedia-poster' ),
			'{permalink}'         => __( 'Link to the post without UTM parameters', 'postvoyage-socialmedia-poster' ),
			'{shortlink}'         => __( 'WordPress short link (?p=123)', 'postvoyage-socialmedia-poster' ),
			'{hashtags}'          => __( 'Tags as hashtags', 'postvoyage-socialmedia-poster' ),
			'{category_hashtags}' => __( 'Categories as hashtags', 'postvoyage-socialmedia-poster' ),
			'{tags}'              => __( 'Tags, comma separated', 'postvoyage-socialmedia-poster' ),
			'{categories}'        => __( 'Categories, comma separated', 'postvoyage-socialmedia-poster' ),
			'{author}'            => __( 'Author name', 'postvoyage-socialmedia-poster' ),
			'{date}'              => __( 'Publish date', 'postvoyage-socialmedia-poster' ),
			'{site_name}'         => __( 'Site title', 'postvoyage-socialmedia-poster' ),
			'{site_url}'          => __( 'Site address', 'postvoyage-socialmedia-poster' ),
			'{language}'          => __( 'Language code of the post', 'postvoyage-socialmedia-poster' ),
			'{network}'           => __( 'Name of the network', 'postvoyage-socialmedia-poster' ),
			'{meta:field}'        => __( 'Value of the custom field "field"', 'postvoyage-socialmedia-poster' ),
		);

		/**
		 * Filters the variable list shown in the admin.
		 *
		 * @param array<string,string> $help Variable => description.
		 */
		return (array) apply_filters( 'postvoyage_variables_help', $help );
	}

	/**
	 * Builds the message for one network/account.
	 *
	 * @param WP_Post          $post         Post.
	 * @param string           $template     Template text.
	 * @param Account          $account      Target account.
	 * @param NetworkInterface $network      Target network.
	 * @param bool             $attach_image Whether to include the featured image.
	 * @return Message
	 */
	public static function build( WP_Post $post, $template, Account $account, NetworkInterface $network, $attach_image ) {
		$vars = self::variables( $post, $network );

		$message              = new Message();
		$message->post_id     = $post->ID;
		$message->url         = $vars['{url}'];
		$message->title       = $vars['{title}'];
		$message->description = TextFitter::fit( $vars['{excerpt}'], 300 );
		$message->language    = $vars['{language}'];
		$message->hashtags    = Hashtags::from_names( self::term_names( $post, 'post_tag' ) );
		$message->image_id    = $attach_image ? (int) get_post_thumbnail_id( $post ) : 0;

		$text  = self::render( $template, $vars, $post );
		$limit = $network->char_limit( $account, $message->has_image() );

		$message->text = TextFitter::fit( $text, $limit, $network->url_length() );

		/**
		 * Filters the message before it is sent.
		 *
		 * @param Message          $message Message.
		 * @param WP_Post          $post    Post.
		 * @param Account          $account Target account.
		 * @param NetworkInterface $network Target network.
		 */
		return apply_filters( 'postvoyage_message', $message, $post, $account, $network );
	}

	/**
	 * Replaces variables in a template.
	 *
	 * @param string               $template Template.
	 * @param array<string,string> $vars     Variables.
	 * @param WP_Post              $post     Post (for {meta:...}).
	 * @return string
	 */
	public static function render( $template, array $vars, WP_Post $post ) {
		$text = preg_replace_callback(
			'~\{meta:([A-Za-z0-9_\-]+)\}~',
			static function ( $m ) use ( $post ) {
				$value = get_post_meta( $post->ID, $m[1], true );
				return is_scalar( $value ) ? self::plain( (string) $value ) : '';
			},
			$template
		);

		$text = strtr( (string) $text, $vars );

		// Tidy up: trailing spaces, more than one empty line, empty lines at the ends.
		$text = (string) preg_replace( "~[ \t]+\n~", "\n", $text );
		$text = (string) preg_replace( "~\n{3,}~", "\n\n", $text );
		return trim( $text );
	}

	/**
	 * All variables for a post.
	 *
	 * @param WP_Post          $post    Post.
	 * @param NetworkInterface $network Network.
	 * @return array<string,string>
	 */
	public static function variables( WP_Post $post, NetworkInterface $network ) {
		$content    = self::plain( strip_shortcodes( excerpt_remove_blocks( $post->post_content ) ) );
		$excerpt    = '' !== trim( $post->post_excerpt ) ? self::plain( $post->post_excerpt ) : wp_trim_words( $content, 55, '…' );
		$tags       = self::term_names( $post, 'post_tag' );
		$categories = self::term_names( $post, 'category' );
		$permalink  = (string) get_permalink( $post );
		$author     = get_userdata( (int) $post->post_author );

		$vars = array(
			'{title}'             => self::plain( get_the_title( $post ) ),
			'{excerpt}'           => $excerpt,
			'{content}'           => $content,
			'{url}'               => self::tracking_url( $permalink, $network ),
			'{permalink}'         => $permalink,
			'{shortlink}'         => (string) wp_get_shortlink( $post->ID ),
			'{hashtags}'          => Hashtags::format( Hashtags::from_names( $tags ) ),
			'{category_hashtags}' => Hashtags::format( Hashtags::from_names( $categories ) ),
			'{tags}'              => implode( ', ', $tags ),
			'{categories}'        => implode( ', ', $categories ),
			'{author}'            => $author ? $author->display_name : '',
			'{date}'              => (string) get_the_date( '', $post ),
			'{site_name}'         => self::plain( get_bloginfo( 'name' ) ),
			'{site_url}'          => home_url( '/' ),
			'{language}'          => Language::of_post( $post->ID ),
			'{network}'           => $network->label(),
		);

		/**
		 * Filters the template variables. Add your own as '{name}' => 'value'.
		 *
		 * @param array<string,string> $vars    Variables.
		 * @param WP_Post              $post    Post.
		 * @param NetworkInterface     $network Network.
		 */
		return (array) apply_filters( 'postvoyage_template_variables', $vars, $post, $network );
	}

	/**
	 * Adds UTM parameters if enabled.
	 *
	 * @param string           $url     URL.
	 * @param NetworkInterface $network Network.
	 * @return string
	 */
	private static function tracking_url( $url, NetworkInterface $network ) {
		$settings = Settings::all();
		if ( empty( $settings['utm_enabled'] ) || '' === $url ) {
			return $url;
		}
		$params = array();
		foreach ( array( 'utm_source', 'utm_medium', 'utm_campaign' ) as $key ) {
			$value = str_replace( '{network}', $network->id(), (string) $settings[ $key ] );
			if ( '' !== $value ) {
				$params[ $key ] = rawurlencode( $value );
			}
		}
		return add_query_arg( $params, $url );
	}

	/**
	 * Term names of a taxonomy.
	 *
	 * @param WP_Post $post     Post.
	 * @param string  $taxonomy Taxonomy.
	 * @return string[]
	 */
	private static function term_names( WP_Post $post, $taxonomy ) {
		$terms = get_the_terms( $post, $taxonomy );
		if ( ! is_array( $terms ) ) {
			return array();
		}
		$names = array();
		foreach ( $terms as $term ) {
			// "Uncategorized" is not worth a hashtag.
			if ( 'category' === $taxonomy && (int) get_option( 'default_category' ) === $term->term_id ) {
				continue;
			}
			$names[] = self::plain( $term->name );
		}
		return $names;
	}

	/**
	 * HTML to plain text: tags removed, entities decoded, whitespace normalised.
	 *
	 * @param string $html HTML.
	 * @return string
	 */
	public static function plain( $html ) {
		$text = wp_strip_all_tags( (string) $html );
		$text = html_entity_decode( $text, ENT_QUOTES | ENT_HTML5, 'UTF-8' );
		$text = (string) preg_replace( '~[ \t\x{00A0}]+~u', ' ', $text );
		$text = (string) preg_replace( "~\s*\n\s*~", "\n", $text );
		return trim( $text );
	}
}
