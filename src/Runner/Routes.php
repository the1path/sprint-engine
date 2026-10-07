<?php
/**
 * Canonical Sprint route, without public Step permalinks.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Runner;

defined( 'ABSPATH' ) || exit;

/** Owns the rewrite lifecycle and route identification. */
final class Routes {

	const RULE      = '^sprint/([^/]+)/?$';
	const QUERY_VAR = 'sprint_engine_sprint_slug';

	/** Register the rule and dedicated public query variable. */
	public function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'request', array( $this, 'request' ) );
	}

	/** Register before activation flush, and on ordinary init without flushing. */
	public static function register() {
		add_rewrite_rule( self::RULE, 'index.php?' . self::QUERY_VAR . '=$matches[1]', 'top' );
	}

	/** Remove the in-memory rule before the deactivation flush. */
	public static function unregister() {
		global $wp_rewrite;
		unset( $wp_rewrite->extra_rules_top[ self::RULE ] );
	}

	/**
	 * Add the routing variable without exposing any user or Step selector.
	 *
	 * @param array $vars WordPress public query variables.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Prevent query-string feed/preview/post selectors from taking over this route.
	 * Also keep unrelated posts out of the main query and document hooks.
	 *
	 * @param array $vars Parsed WordPress query variables.
	 * @return array
	 */
	public function request( $vars ) {
		$slug = self::slug();
		return null === $slug ? $vars : array(
			self::QUERY_VAR       => $slug,
			'post__in'            => array( 0 ),
			'ignore_sticky_posts' => true,
		);
	}

	/**
	 * Require the matched path, not a query-string flag on an unrelated URL.
	 * The path also prevents query-string overrides of the captured Sprint slug.
	 *
	 * @return string|null Raw captured slug, or null for unrelated requests.
	 */
	public static function slug() {
		global $wp;
		if ( is_admin() || ! $wp || self::RULE !== $wp->matched_rule || ! preg_match( '~' . self::RULE . '~', $wp->request, $matches ) ) {
			return null;
		}
		return $matches[1];
	}

	/**
	 * Build a same-site canonical return URL from a saved WordPress slug.
	 *
	 * @param string $slug Saved Sprint post_name.
	 * @return string
	 */
	public static function url( $slug ) {
		return home_url( user_trailingslashit( '/sprint/' . $slug ) );
	}
}
