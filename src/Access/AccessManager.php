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
		return (bool) apply_filters( 'sprint_engine/user_can_access_sprint', $allowed, $user_id, $sprint_id ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores -- Public hook spelling required by the specification.
	}
}
