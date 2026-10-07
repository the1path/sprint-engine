<?php
/**
 * Database boundary shared by internal progress operations.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Progress;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Explicit operational transaction boundary.

/** Serializes with the existing StructureManager's Sprint row lock. */
final class Transaction {

	/**
	 * Reject recursive progress calls inside an uncommitted operation.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * Execute against a coherent structure/progress snapshot, committing on success.
	 * Caller must not already have an external transaction open.
	 *
	 * @param int      $sprint Sprint ID.
	 * @param callable $operation Operation returning a value or WP_Error.
	 * @return mixed
	 */
	public function run( $sprint, $operation ) {
		global $wpdb;
		if ( self::$active || SPRINT_ENGINE_SCHEMA_VERSION !== get_option( 'sprint_engine_schema_version' ) || get_option( 'sprint_engine_installation_failed' ) ) {
			return self::error();
		}
		self::$active = true;
		$started      = false;
		$previous     = $wpdb->suppress_errors( true );
		try {
			$tables = $wpdb->get_results( $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s, %s, %s)', $wpdb->posts, $wpdb->postmeta, $wpdb->prefix . 'sprint_engine_enrolments', $wpdb->prefix . 'sprint_engine_step_progress' ), ARRAY_A );
			EnrolmentRepository::check( is_array( $tables ) && 4 === count( $tables ) && '' === $wpdb->last_error );
			foreach ( $tables as $table ) {
				EnrolmentRepository::check( 'innodb' === strtolower( $table['Engine'] ?? '' ) );
			}
			EnrolmentRepository::check( false !== $wpdb->query( 'START TRANSACTION' ) );
			$started = true;
			// Same locking protocol as StructureManager; serializes first starts too.
			EnrolmentRepository::check( false !== $wpdb->query( $wpdb->prepare( "UPDATE $wpdb->posts SET ID = ID WHERE ID = %d", $sprint ) ) );
			clean_post_cache( $sprint );
			wp_cache_set_posts_last_changed();
			foreach ( \ThePath\SprintEngine\Content\Meta::steps( $sprint ) as $step ) {
				clean_post_cache( $step->ID );
			}
			$result = $operation();
			if ( is_wp_error( $result ) ) {
				$wpdb->query( 'ROLLBACK' );
			} else {
				EnrolmentRepository::check( false !== $wpdb->query( 'COMMIT' ) );
			}
			return $result;
		} catch ( \Throwable $exception ) {
			if ( $started ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return self::error();
		} finally {
			$wpdb->suppress_errors( $previous );
			self::$active = false;
		}
	}

	/**
	 * Safe failure response, including unsupported transaction engines.
	 *
	 * @return \WP_Error
	 */
	private static function error() {
		error_log( 'Sprint Engine: progress transaction failed; check database availability and transaction support.' ); // phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Generic operational diagnostic; no SQL, identity or exception details.
		return new \WP_Error( 'sprint_engine_progress_persistence', __( 'Progress could not be saved or read safely. Retry, or ask an administrator to check database transaction support.', 'sprint-engine' ) );
	}
}
