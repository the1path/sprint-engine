<?php
/**
 * Enrolment persistence. Callers own validation and transaction boundaries.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Progress;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Dedicated operational tables; identifiers use only the trusted WordPress prefix (WP 6.0 compatible).

/** Persists attempts and reads canonical user/Sprint history. */
final class EnrolmentRepository {

	/**
	 * List persisted positive attempts, independent of present content structure.
	 *
	 * @param int      $user Validated user ID.
	 * @param int|null $sprint Optional validated Sprint ID.
	 * @return array[] Sprint ID ascending, then attempt number and ID descending.
	 */
	public function list_attempts( $user, $sprint = null ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sprint_engine_enrolments';
		$sql   = $wpdb->prepare( "SELECT id, sprint_id, attempt_number, status, current_step_id, started_at, last_activity_at, completed_at, created_at, updated_at FROM $table WHERE user_id = %d AND attempt_number > 0", $user );
		if ( null !== $sprint ) {
			$sql .= $wpdb->prepare( ' AND sprint_id = %d', $sprint );
		}
		$rows = $wpdb->get_results( $sql . ' ORDER BY sprint_id ASC, attempt_number DESC, id DESC', ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Query and optional filter prepared above; ordering is static.
		self::check( is_array( $rows ) && '' === $wpdb->last_error );
		foreach ( $rows as &$row ) {
			foreach ( array( 'id', 'sprint_id', 'attempt_number', 'current_step_id' ) as $key ) {
				$row[ $key ] = null === $row[ $key ] ? null : (int) $row[ $key ];
			}
		}
		unset( $row );
		return $rows;
	}

	/**
	 * Find the latest attempt by sequence, never by timestamps.
	 *
	 * @param int $user User ID.
	 * @param int $sprint Sprint ID.
	 * @return array|null
	 */
	public function find( $user, $sprint ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sprint_engine_enrolments';
		$row   = $wpdb->get_row( $wpdb->prepare( "SELECT * FROM $table WHERE user_id = %d AND sprint_id = %d AND attempt_number > 0 ORDER BY attempt_number DESC LIMIT 1", $user, $sprint ), ARRAY_A );
		self::check( '' === $wpdb->last_error );
		if ( $row ) {
			foreach ( array( 'id', 'user_id', 'sprint_id', 'current_step_id', 'attempt_number' ) as $key ) {
				$row[ $key ] = null === $row[ $key ] ? null : (int) $row[ $key ];
			}
		}
		return $row;
	}

	/**
	 * Insert a row; the database unique key remains the final duplicate guard.
	 *
	 * @param array $row Validated fields including UTC timestamps.
	 * @return void
	 */
	public function create( $row ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sprint_engine_enrolments';
		self::check( 1 === $wpdb->insert( $table, $row ) );
	}

	/**
	 * Update state/activity fields on an existing enrolment.
	 *
	 * @param int   $id Enrolment ID.
	 * @param array $fields Validated changes.
	 * @return void
	 */
	public function update( $id, $fields ) {
		global $wpdb;
		$table = $wpdb->prefix . 'sprint_engine_enrolments';
		self::check( false !== $wpdb->update( $table, $fields, array( 'id' => $id ) ) );
	}

	/**
	 * Turn database failures into exceptions for the service transaction boundary.
	 *
	 * @param bool $success Whether persistence succeeded.
	 * @return void
	 * @throws \RuntimeException On persistence failure, without exposing SQL.
	 */
	public static function check( $success ) {
		if ( ! $success ) {
			throw new \RuntimeException( 'Sprint Engine persistence failed.' );
		}
	}
}
