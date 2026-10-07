<?php
/**
 * Built-in member Dashboard route.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Dashboard;

defined( 'ABSPATH' ) || exit;

/** Owns exact-path identification and the rewrite lifecycle. */
final class Routes {

	const RULE      = '^sprint-engine/dashboard/?$';
	const QUERY_VAR = 'sprint_engine_dashboard';

	/** Register without flushing on ordinary requests. */
	public function register_hooks() {
		add_action( 'init', array( __CLASS__, 'register' ) );
		add_filter( 'query_vars', array( $this, 'query_vars' ) );
		add_filter( 'request', array( $this, 'request' ) );
	}

	/** Register before the activation flush. */
	public static function register() {
		add_rewrite_rule( self::RULE, 'index.php?' . self::QUERY_VAR . '=1', 'top' );
	}

	/** Remove before the deactivation flush. */
	public static function unregister() {
		global $wp_rewrite;
		unset( $wp_rewrite->extra_rules_top[ self::RULE ] );
	}

	/**
	 * Register the namespaced routing flag.
	 *
	 * @param array $vars Public variables.
	 * @return array
	 */
	public function query_vars( $vars ) {
		$vars[] = self::QUERY_VAR;
		return $vars;
	}

	/**
	 * Discard unrelated post/feed/home selectors only on the matched path.
	 *
	 * @param array $vars Parsed variables.
	 * @return array
	 */
	public function request( $vars ) {
		return self::matches() ? array(
			self::QUERY_VAR       => 1,
			'post__in'            => array( 0 ),
			'ignore_sticky_posts' => true,
		) : $vars;
	}

	/**
	 * Require both the actual path and matched rule, never a query-string flag.
	 *
	 * @return bool
	 */
	public static function matches() {
		global $wp;
		return ! is_admin() && $wp && self::RULE === $wp->matched_rule && 1 === preg_match( '~' . self::RULE . '~D', $wp->request );
	}

	/**
	 * Canonical member return destination; no WordPress Page is needed.
	 *
	 * @return string
	 */
	public static function url() {
		return home_url( user_trailingslashit( '/sprint-engine/dashboard' ) );
	}
}
