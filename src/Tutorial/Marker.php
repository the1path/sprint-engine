<?php
/**
 * Protected tutorial identity shared with discovery integrations.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Tutorial;

defined( 'ABSPATH' ) || exit;

/** Marker presence is restrictive, including empty, malformed or duplicate rows. */
final class Marker {

	const KEY = '_sprint_engine_tutorial_admin_only';

	/**
	 * Do not use truthiness, a title, a slug or the installation option for access.
	 *
	 * @param int $sprint Sprint ID.
	 * @return bool
	 */
	public static function exists( $sprint ) {
		return metadata_exists( 'post', $sprint, self::KEY );
	}

	/**
	 * Only a canonical marker is safe for installer reuse; never repair ambiguity.
	 *
	 * @param int $sprint Sprint ID.
	 * @return bool
	 */
	public static function canonical( $sprint ) {
		return array( '1' ) === get_post_meta( $sprint, self::KEY, false );
	}
}
