<?php
/**
 * Sprint access provider contract.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Access;

defined( 'ABSPATH' ) || exit;

/** Supplies an entitlement decision, independently of Runner presentation. */
interface AccessProvider {

	/**
	 * Decide whether an authenticated user may access a Sprint.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $sprint_id Sprint ID.
	 * @return bool
	 */
	public function can_access( $user_id, $sprint_id );
}
