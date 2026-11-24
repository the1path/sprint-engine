<?php
/**
 * Internal, composable content transaction boundary.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Content;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery -- Explicit content transaction boundary.

/** Allows a content installer to compose normal StructureManager operations. */
final class Transaction {

	/**
	 * Whether this request owns a content transaction.
	 *
	 * @var bool
	 */
	private static $active = false;

	/**
	 * Whether a composed operation failed.
	 *
	 * @var bool
	 */
	private static $failed = false;

	/**
	 * Run one atomic content operation. Nested failures make the whole unit fail.
	 * Callers must not already own an external/progress database transaction.
	 *
	 * @param callable $operation Operation returning a value or WP_Error.
	 * @param bool     $with_options Whether the operation also owns option writes.
	 * @return mixed
	 */
	public function run( $operation, $with_options = false ) {
		global $wpdb;
		if ( self::$active ) {
			$result       = $operation();
			self::$failed = self::$failed || is_wp_error( $result );
			return $result;
		}
		$previous = $wpdb->suppress_errors( true );
		$started  = false;
		$touched  = array();
		$track    = static function ( $id ) use ( &$touched ) {
			$touched[ $id ] = $id;
		};
		try {
			$query  = $with_options ? $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s, %s)', $wpdb->posts, $wpdb->postmeta, $wpdb->options ) : $wpdb->prepare( 'SHOW TABLE STATUS WHERE Name IN (%s, %s)', $wpdb->posts, $wpdb->postmeta );
			$tables = $wpdb->get_results( $query, ARRAY_A ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- Both alternatives are prepared immediately above.
			if ( $wpdb->last_error || ! is_array( $tables ) || ( $with_options ? 3 : 2 ) !== count( $tables ) || array_filter(
				$tables,
				static function ( $table ) {
					return 'innodb' !== strtolower( $table['Engine'] ?? '' );
				}
			) || false === $wpdb->query( 'START TRANSACTION' ) ) {
				return self::error();
			}
			$started      = true;
			self::$active = true;
			self::$failed = false;
			add_action( 'clean_post_cache', $track );
			add_action( 'wp_insert_post', $track );
			$result = $operation();
			if ( is_wp_error( $result ) || self::$failed ) {
				$wpdb->query( 'ROLLBACK' );
				return is_wp_error( $result ) ? $result : self::error();
			}
			if ( false === $wpdb->query( 'COMMIT' ) ) {
				$wpdb->query( 'ROLLBACK' );
				return self::error();
			}
			return $result;
		} catch ( \Throwable $exception ) {
			if ( $started ) {
				$wpdb->query( 'ROLLBACK' );
			}
			return self::error();
		} finally {
			remove_action( 'clean_post_cache', $track );
			remove_action( 'wp_insert_post', $track );
			foreach ( $touched as $id ) {
				clean_post_cache( $id );
				wp_cache_delete( $id, 'post_meta' );
			}
			wp_cache_set_posts_last_changed();
			self::$active = false;
			self::$failed = false;
			$wpdb->suppress_errors( $previous );
		}
	}

	/**
	 * Safe persistence response.
	 *
	 * @return \WP_Error
	 */
	private static function error() {
		return new \WP_Error( 'sprint_engine_content_transaction', __( 'Content could not be saved safely. Retry, or ask an administrator to check database transaction support.', 'sprint-engine' ) );
	}
}
