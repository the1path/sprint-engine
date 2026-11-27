<?php
/**
 * Single access-decision boundary for member callers.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Access;

defined( 'ABSPATH' ) || exit;

/** Delegates to the provider and exposes the specified integration filter. */
final class AccessManager {

	/**
	 * Configured policy.
	 *
	 * @var AccessProvider
	 */
	private $provider;

	/**
	 * Use the logged-in policy unless an integration supplies a provider.
	 *
	 * @param AccessProvider|null $provider Policy implementation.
	 */
	public function __construct( ?AccessProvider $provider = null ) {
		$this->provider = $provider ?? new LoggedInAccessProvider();
	}

	/**
	 * Apply the provider decision and the public integration override.
	 * Authentication and member-visible publication checks belong to the caller.
	 *
	 * @param int $user_id Authenticated user ID.
	 * @param int $sprint_id Sprint ID.
	 * @return bool
	 */
	public function can_access( $user_id, $sprint_id ) {
		$allowed = $this->provider->can_access( $user_id, $sprint_id );
		$allowed = (bool) apply_filters( 'sprint_engine/user_can_access_sprint', $allowed, $user_id, $sprint_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public hook spelling required by the specification.
		if ( \ThePath\SprintEngine\Tutorial\Marker::exists( $sprint_id ) ) {
			return is_int( $user_id ) && $user_id > 0 && get_current_user_id() === $user_id && user_can( $user_id, 'manage_options' );
		}
		return $allowed;
	}

	/**
	 * Classify guest presentation without invoking a member policy with a fake user.
	 * Availability remains the caller's prerequisite; this never grants access.
	 * One coordinated classifier is supported; collisions fail to unknown.
	 *
	 * @param int $sprint_id Ready Sprint ID.
	 * @return string open, restricted or unknown.
	 */
	public function participant_access_kind( $sprint_id ) {
		if ( ! is_int( $sprint_id ) || $sprint_id < 1 || ! \ThePath\SprintEngine\Content\Meta::valid_post( $sprint_id, 'sprint_engine_sprint' ) ) {
			return 'unknown';
		}
		if ( \ThePath\SprintEngine\Tutorial\Marker::exists( $sprint_id ) ) {
			return 'restricted';
		}
		$kind = $this->provider instanceof LoggedInAccessProvider && false === has_filter( 'sprint_engine/user_can_access_sprint' ) ? 'open' : 'unknown';
		global $wp_filter;
		$hook = $wp_filter['sprint_engine/participant_access_kind'] ?? null;
		if ( $hook ) {
			$count = 0;
			foreach ( $hook->callbacks as $callbacks ) {
				$count += count( $callbacks );
			}
			if ( 1 !== $count ) {
				return 'unknown';
			}
		}
		/**
		 * One trusted integration must coordinate all applicable restrictions.
		 * This assertion concerns signup presentation, never member authorisation.
		 *
		 * @param string $kind Core open or unknown baseline.
		 * @param int    $sprint_id Core-resolved ready Sprint.
		 */
		$kind = apply_filters( 'sprint_engine/participant_access_kind', $kind, $sprint_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Additive public presentation seam.
		return in_array( $kind, array( 'open', 'restricted', 'unknown' ), true ) ? $kind : 'unknown';
	}
}
