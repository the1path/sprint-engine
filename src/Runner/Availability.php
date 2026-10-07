<?php
/**
 * Shared content readiness checks, independent of member access and progress.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Runner;

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;

defined( 'ABSPATH' ) || exit;

/** Explains the existing Runner's content prerequisites to administrators. */
final class Availability {

	/**
	 * Return an admin-only reason, or an empty string when content is ready.
	 *
	 * @param \WP_Post|null $sprint Sprint object.
	 * @return string
	 */
	public static function reason( $sprint ) {
		if ( ! $sprint || 'sprint_engine_sprint' !== $sprint->post_type || 'publish' !== $sprint->post_status ) {
			return __( 'Runner unavailable — Sprint is not published.', 'sprint-engine' );
		}
		if ( '' !== $sprint->post_password ) {
			return __( 'Runner unavailable — Sprint is password protected.', 'sprint-engine' );
		}
		if ( ! Meta::read( $sprint->ID, 'sprint_engine_sprint' )['_sprint_engine_launchable'] ) {
			return __( 'Runner unavailable — Sprint is not Launchable.', 'sprint-engine' );
		}
		if ( ! StructureManager::is_linear( $sprint->ID ) ) {
			return __( 'Runner unavailable — Sprint structure needs attention.', 'sprint-engine' );
		}
		if ( ! Meta::publication_readiness( $sprint->ID )['ready'] ) {
			return __( 'Runner unavailable — one or more Sprint Steps are not published.', 'sprint-engine' );
		}
		return '';
	}
}
