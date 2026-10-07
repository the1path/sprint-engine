<?php
/**
 * Step progress persistence; business rules belong to ProgressService.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Progress;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dedicated operational tables; identifiers use only the trusted WordPress prefix (WP 6.0 compatible).

/** Queries and stores one row per user/Sprint/attempt/Step. */
final class StepProgressRepository {

	/**
	 * Find a Step row.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @param int $step Step ID.
	 * @param int $attempt Attempt number; legacy callers default to Attempt 1.
	 * @return array|null
	 */
	public function find( $user, $sprint, $step, $attempt = 1 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sprint_engine_step_progress';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d AND sprint_id = %d AND step_id = %d AND attempt_number = %d", $user, $sprint, $step, $attempt ), ARRAY_A );
		EnrolmentRepository::check( '' === $wpdb->last_error );
		return $row;
	}

	/**
	 * Insert a started row. The service checks existence under its lock first.
	 *
	 * @param int    $user User ID.
	 * @param int    $sprint Sprint ID.
	 * @param int    $step Step ID.
	 * @param string $now UTC timestamp.
	 * @param int    $attempt Attempt number; legacy callers default to Attempt 1.
	 * @return void
	 */
	public function create( $user, $sprint, $step, $now, $attempt = 1 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sprint_engine_step_progress';
		EnrolmentRepository::check(
			1 === $wpdb->insert(
				$table,
				array(
					'attempt_number' => $attempt,
					'user_id'        => $user,
					'sprint_id'      => $sprint,
					'step_id'        => $step,
					'status'         => 'started',
					'started_at'     => $now,
					'updated_at'     => $now,
				)
			)
		);
	}

	/**
	 * Complete a started row without overwriting an existing completion time.
	 *
	 * @param int    $user User ID.
	 * @param int    $sprint Sprint ID.
	 * @param int    $step Step ID.
	 * @param string $now UTC timestamp.
	 * @param int    $attempt Attempt number; legacy callers default to Attempt 1.
	 * @return bool Whether a transition was persisted.
	 */
	public function complete( $user, $sprint, $step, $now, $attempt = 1 ) {
		global $wpdb;
		$table  = $wpdb->prefix . 'sprint_engine_step_progress';
		$result = $wpdb->query( $wpdb->prepare( "UPDATE $table SET status = 'completed', completed_at = COALESCE(completed_at, %s), updated_at = %s WHERE user_id = %d AND sprint_id = %d AND step_id = %d AND attempt_number = %d AND status = 'started'", $now, $now, $user, $sprint, $step, $attempt ) );
		EnrolmentRepository::check( false !== $result );
		return 1 === $result;
	}

	/**
	 * Return completed IDs, including historical content no longer in the Sprint.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @param int $attempt Attempt number; legacy callers default to Attempt 1.
	 * @return int[]
	 */
	public function completed_ids( $user, $sprint, $attempt = 1 ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sprint_engine_step_progress';
		$ids   = $wpdb->get_col( $wpdb->prepare( "SELECT step_id FROM $table WHERE user_id = %d AND sprint_id = %d AND attempt_number = %d AND status = 'completed' ORDER BY step_id", $user, $sprint, $attempt ) );
		EnrolmentRepository::check( '' === $wpdb->last_error );
		return array_map( 'intval', $ids );
	}
}
