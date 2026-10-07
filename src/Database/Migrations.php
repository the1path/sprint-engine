<?php
/**
 * Versioned, additive schema definitions.
 *
 * @package SprintEngine
 */

namespace ThePath\SprintEngine\Database;

defined( 'ABSPATH' ) || exit;

/** Defines historical and current schemas without changing operational data. */
final class Migrations {

	/**
	 * Current attempt-aware schema; DEFAULT 1 maps all legacy rows to Attempt 1.
	 *
	 * @return array Table name => CREATE TABLE SQL.
	 */
	public static function version_two() {
		$tables = self::version_one();
		foreach ( $tables as $table => $sql ) {
			$sql              = str_replace( 'sprint_id bigint(20) unsigned NOT NULL,', "sprint_id bigint(20) unsigned NOT NULL,\n\t\t\t\tattempt_number bigint(20) unsigned NOT NULL DEFAULT 1,", $sql );
			$sql              = str_replace( 'UNIQUE KEY user_sprint (user_id,sprint_id)', "UNIQUE KEY user_sprint_attempt (user_id,sprint_id,attempt_number),\n\t\t\t\tKEY user_sprint_lookup (user_id,sprint_id)", $sql );
			$sql              = str_replace( 'UNIQUE KEY user_sprint_step (user_id,sprint_id,step_id)', 'UNIQUE KEY user_sprint_attempt_step (user_id,sprint_id,attempt_number,step_id)', $sql );
			$tables[ $table ] = $sql;
		}
		return $tables;
	}

	/**
	 * Build dbDelta-compatible SQL with the current site's prefix/collation.
	 *
	 * @return array Table name => CREATE TABLE SQL.
	 */
	public static function version_one() {
		global $wpdb;
		$enrolments = $wpdb->prefix . 'sprint_engine_enrolments';
		$progress   = $wpdb->prefix . 'sprint_engine_step_progress';
		$collation  = $wpdb->get_charset_collate();

		return array(
			$enrolments => "CREATE TABLE $enrolments (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				sprint_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'not_started',
				current_step_id bigint(20) unsigned DEFAULT NULL,
				started_at datetime DEFAULT NULL,
				last_activity_at datetime DEFAULT NULL,
				completed_at datetime DEFAULT NULL,
				created_at datetime NOT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_sprint (user_id,sprint_id),
				KEY user_id (user_id),
				KEY sprint_id (sprint_id)
			) $collation;",
			$progress   => "CREATE TABLE $progress (
				id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
				user_id bigint(20) unsigned NOT NULL,
				sprint_id bigint(20) unsigned NOT NULL,
				step_id bigint(20) unsigned NOT NULL,
				status varchar(20) NOT NULL DEFAULT 'started',
				started_at datetime DEFAULT NULL,
				completed_at datetime DEFAULT NULL,
				updated_at datetime NOT NULL,
				PRIMARY KEY  (id),
				UNIQUE KEY user_sprint_step (user_id,sprint_id,step_id),
				KEY user_id (user_id),
				KEY sprint_id (sprint_id),
				KEY step_id (step_id)
			) $collation;",
		);
	}
}
