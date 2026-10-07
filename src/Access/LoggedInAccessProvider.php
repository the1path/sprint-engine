<?php
/**
 * Initial access policy without commerce or membership dependencies.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Access;

use ThePath\SprintEngine\Content\Meta;

defined( 'ABSPATH' ) || exit;

/** Permits the authenticated WordPress user to enter a valid Sprint. */
final class LoggedInAccessProvider implements AccessProvider {

	/**
	 * Check identity and Sprint existence; publication is a Runner concern.
	 *
	 * @param int $user_id WordPress user ID.
	 * @param int $sprint_id Sprint ID.
	 * @return bool
	 */
	public function can_access( $user_id, $sprint_id ) {
		return is_int( $user_id ) && $user_id > 0 && get_current_user_id() === $user_id &&
			(bool) get_userdata( $user_id ) && is_int( $sprint_id ) && Meta::valid_post( $sprint_id, 'sprint_engine_sprint' );
	}
}
