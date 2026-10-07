<?php
/**
 * Repeatable database installation.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Database;

defined( 'ABSPATH' ) || exit;

// phpcs:disable WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.InterpolatedNotPrepared -- Live schema migration; identifiers derive only from the trusted site prefix.

/** Installs owned tables without deleting existing data. */
final class Installer {

	/**
	 * Apply schema version 2 and only record success after verification.
	 *
	 * @return bool Whether installation succeeded.
	 */
	public function install() {
		global $wpdb;

		if ( version_compare( (string) get_option( 'sprint_engine_schema_version', '0' ), SPRINT_ENGINE_SCHEMA_VERSION, '>' ) ) {
			return $this->failed();
		}

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		$previous = $wpdb->suppress_errors( true );
		try {
			foreach ( Migrations::version_two() as $table => $sql ) {
				dbDelta( $sql );
				if ( $wpdb->last_error ) {
					return $this->failed();
				}
				// phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Installation must verify live schema, not cached application data.
				$found = $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) );
				if ( $found !== $table ) {
					return $this->failed();
				}
				// A later successful ALTER can clear last_error after an earlier failure.
				// Require an empty dry-run plan before marking the schema installed.
				if ( array() !== dbDelta( $sql, false ) || $wpdb->last_error ) {
					return $this->failed();
				}
				// dbDelta's dry run does not compare every column attribute, such as nullability.
				$fields = $wpdb->get_results( "SHOW FULL COLUMNS FROM `$table`", ARRAY_A );
				$field  = array_column( $fields ?? array(), null, 'Field' )['attempt_number'] ?? null;
				if ( $wpdb->last_error || ! $field || 'NO' !== $field['Null'] || '1' !== (string) $field['Default'] || ! preg_match( '/^bigint(?:\(20\))? unsigned$/i', $field['Type'] ) ) {
					return $this->failed();
				}
				// dbDelta adds replacement keys but does not remove the old unique keys.
				// Verify the replacement before dropping anything; MySQL DDL may commit.
				$progress = $wpdb->prefix . 'sprint_engine_step_progress' === $table;
				$old      = $progress ? 'user_sprint_step' : 'user_sprint';
				$new      = $progress ? 'user_sprint_attempt_step' : 'user_sprint_attempt';
				$columns  = $progress ? array( 'user_id', 'sprint_id', 'attempt_number', 'step_id' ) : array( 'user_id', 'sprint_id', 'attempt_number' );
				$indexes  = $this->indexes( $table );
				if ( ! $this->unique_key( $indexes, $new, $columns ) ) {
					return $this->failed();
				}
				if ( isset( $indexes[ $old ] ) && false === $wpdb->query( "ALTER TABLE `$table` DROP INDEX `$old`" ) ) {
					return $this->failed();
				}
				$indexes = $this->indexes( $table );
				if ( isset( $indexes[ $old ] ) || ! $this->unique_key( $indexes, $new, $columns ) || $wpdb->last_error ) {
					return $this->failed();
				}
			}
		} finally {
			$wpdb->suppress_errors( $previous );
		}

		update_option( 'sprint_engine_schema_version', SPRINT_ENGINE_SCHEMA_VERSION, false );
		update_option( 'sprint_engine_version', SPRINT_ENGINE_VERSION, false );
		delete_option( 'sprint_engine_installation_failed' );
		return true;
	}

	/**
	 * Inspect ordered live index columns (also supported by official SQLite).
	 *
	 * @param string $table Trusted owned table.
	 * @return array Index name => rows ordered by key position.
	 */
	private function indexes( $table ) {
		global $wpdb;
		$rows = $wpdb->get_results( "SHOW INDEX FROM `$table`", ARRAY_A );
		if ( $wpdb->last_error || ! is_array( $rows ) ) {
			return array();
		}
		$indexes = array();
		foreach ( $rows as $row ) {
			$indexes[ $row['Key_name'] ][ (int) $row['Seq_in_index'] ] = $row;
		}
		foreach ( $indexes as &$index ) {
			ksort( $index );
		}
		return $indexes;
	}

	/**
	 * Verify an exact unique composite key before recording the new schema.
	 *
	 * @param array  $indexes Live indexes.
	 * @param string $name Expected index name.
	 * @param array  $columns Expected ordered columns.
	 * @return bool
	 */
	private function unique_key( $indexes, $name, $columns ) {
		$rows = array_values( $indexes[ $name ] ?? array() );
		return array_column( $rows, 'Column_name' ) === $columns && array( 0 ) === array_values( array_unique( array_map( 'intval', array_column( $rows, 'Non_unique' ) ) ) );
	}

	/**
	 * Record a safe diagnostic without logging SQL or private data.
	 *
	 * @return false
	 */
	private function failed() {
		if ( ! get_option( 'sprint_engine_installation_failed' ) ) {
			// phpcs:ignore WordPress.PHP.DevelopmentFunctions.error_log_error_log -- Required migration diagnostic; contains no database details.
			error_log( 'Sprint Engine: database installation failed; check database permissions and schema compatibility.' );
		}
		update_option( 'sprint_engine_installation_failed', true, false );
		return false;
	}
}
