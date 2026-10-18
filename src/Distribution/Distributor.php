<?php
/**
 * Sends posts to the networks.
 *
 * @package PostVoyage
 */

namespace PostVoyage\Distribution;

use PostVoyage\Content\TemplateEngine;
use PostVoyage\Log\Logger;
use PostVoyage\Networks\Registry;
use PostVoyage\Networks\Result;
use PostVoyage\Storage\Account;
use PostVoyage\Storage\AccountRepository;
use PostVoyage\Storage\PostMeta;
use PostVoyage\Storage\RuleRepository;

defined( 'ABSPATH' ) || exit;

/**
 * Runs the distribution of one post: matches rules, renders messages, publishes,
 * logs and schedules retries.
 */
final class Distributor {

	/** Total attempts per account including the first one. */
	const MAX_ATTEMPTS = 3;

	/**
	 * Registers the background job handlers.
	 */
	public static function register() {
		add_action( Scheduler::HOOK_DISTRIBUTE, array( self::class, 'run_scheduled' ), 10, 1 );
		add_action( Scheduler::HOOK_RETRY, array( self::class, 'run_retry' ), 10, 3 );
	}

	/**
	 * Background job after publishing.
	 *
	 * @param int $post_id Post ID.
	 */
	public static function run_scheduled( $post_id ) {
		self::distribute( (int) $post_id );
	}

	/**
	 * Background retry for one account.
	 *
	 * @param int    $post_id    Post ID.
	 * @param string $account_id Account ID.
	 * @param int    $attempt    Attempt number.
	 */
	public static function run_retry( $post_id, $account_id, $attempt ) {
		self::distribute( (int) $post_id, array( (string) $account_id ), false, (int) $attempt );
	}

	/**
	 * Distributes a post.
	 *
	 * @param int      $post_id     Post ID.
	 * @param string[] $account_ids Limit to these accounts (empty = as configured by the rules).
	 * @param bool     $manual      Manual "share now": ignores the per-post switch and shares again.
	 * @param int      $attempt     Attempt number (retries).
	 * @return array<string,Result> Results by account ID.
	 */
	public static function distribute( $post_id, array $account_ids = array(), $manual = false, $attempt = 1 ) {
		$post = get_post( $post_id );
		if ( ! $post || 'publish' !== $post->post_status ) {
			return array();
		}
		if ( ! $manual && ( ! PostMeta::is_enabled( $post->ID ) || '' !== $post->post_password ) ) {
			return array();
		}

		$results = array();
		foreach ( self::jobs( $post, $account_ids, $manual ) as $job ) {
			list( $account, $rule ) = $job;

			if ( ! $manual && PostMeta::was_shared( $post->ID, $account->id ) ) {
				continue;
			}

			/**
			 * Lets code veto sharing a post to an account.
			 *
			 * @param bool     $share   Whether to share.
			 * @param \WP_Post $post    Post.
			 * @param Account  $account Account.
			 */
			if ( ! apply_filters( 'postvoyage_should_share', true, $post, $account ) ) {
				continue;
			}

			if ( ! PostMeta::lock( $post->ID, $account->id ) ) {
				continue;
			}

			$result                  = self::share( $post, $account, $rule );
			$results[ $account->id ] = $result;
			PostMeta::unlock( $post->ID, $account->id );

			if ( ! $result->success && $result->retryable && $attempt < self::MAX_ATTEMPTS ) {
				Scheduler::schedule( Scheduler::HOOK_RETRY, array( $post->ID, $account->id, $attempt + 1 ), time() + 5 * MINUTE_IN_SECONDS * $attempt );
			}
		}

		return $results;
	}

	/**
	 * Determines account → rule pairs. If several rules target the same account, the first wins.
	 *
	 * @param \WP_Post $post        Post.
	 * @param string[] $account_ids Limit to these accounts.
	 * @param bool     $manual      Manual share.
	 * @return array<string,array{0:Account,1:array}>
	 */
	private static function jobs( \WP_Post $post, array $account_ids, $manual ) {
		$accounts = AccountRepository::enabled();
		$jobs     = array();

		foreach ( RuleMatcher::matching( $post ) as $rule ) {
			$targets = $rule['accounts'] ? $rule['accounts'] : array_keys( $accounts );
			foreach ( $targets as $id ) {
				if ( isset( $accounts[ $id ] ) && ! isset( $jobs[ $id ] ) ) {
					$jobs[ $id ] = array( $accounts[ $id ], $rule );
				}
			}
		}

		// Manual sharing works even if no rule matches: use the default template.
		if ( $manual ) {
			$wanted = $account_ids ? $account_ids : ( $jobs ? array() : array_keys( $accounts ) );
			foreach ( $wanted as $id ) {
				if ( isset( $accounts[ $id ] ) && ! isset( $jobs[ $id ] ) ) {
					$jobs[ $id ] = array( $accounts[ $id ], RuleRepository::blank() );
				}
			}
		}

		if ( $account_ids ) {
			$jobs = array_intersect_key( $jobs, array_flip( $account_ids ) );
		}

		return $jobs;
	}

	/**
	 * Shares to one account and records the result.
	 *
	 * @param \WP_Post $post    Post.
	 * @param Account  $account Account.
	 * @param array    $rule    Rule.
	 * @return Result
	 * @throws \RuntimeException Internally, caught and logged.
	 */
	private static function share( \WP_Post $post, Account $account, array $rule ) {
		$network = Registry::get( $account->network );
		$message = null;

		try {
			if ( ! $network ) {
				throw new \RuntimeException( __( 'Network not available.', 'postvoyage-socialmedia-poster' ) );
			}

			$template = PostMeta::message( $post->ID );
			if ( '' === trim( $template ) ) {
				$template = $rule['templates'][ $account->network ] ?? $rule['template'];
			}

			$message = TemplateEngine::build( $post, $template, $account, $network, ! empty( $rule['attach_image'] ) );
			$result  = $network->publish( $message, $account );
		} catch ( \Throwable $e ) {
			$result = Result::error( $e->getMessage() );
		}

		Logger::log( $post->ID, $account, $result );
		PostMeta::record_share( $post->ID, $account->id, $result->success ? 'success' : 'error', $result->remote_url );

		/**
		 * Fires after a post was shared (successfully or not).
		 *
		 * @param Result                                $result  Result.
		 * @param \WP_Post                              $post    Post.
		 * @param Account                               $account Account.
		 * @param \PostVoyage\Content\Message|null $message Message.
		 */
		do_action( 'postvoyage_shared', $result, $post, $account, $message );

		return $result;
	}
}
