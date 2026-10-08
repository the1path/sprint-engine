<?php
/** SE-016 canonical read-only attempt history on disposable WordPress only. */
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Progress\EnrolmentRepository;

$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0;
function history_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks; echo "PASS: $message\n";
}
function history_rows() {
	global $wpdb;
	return array(
		$wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id", ARRAY_A ),
		$wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id", ARRAY_A ),
	);
}
function history_read( $service, $user, $sprint = null ) {
	$before = history_rows(); $queries = array();
	$guard = static function ( $sql ) use ( &$queries ) {
		$queries[] = $sql;
		if ( ! preg_match( '/^\s*SELECT\b/i', $sql ) ) { throw new RuntimeException( 'History attempted a non-SELECT query.' ); }
		if ( str_contains( $sql, 'sprint_engine_step_progress' ) ) { throw new RuntimeException( 'History queried Step progress.' ); }
		return $sql;
	};
	add_filter( 'query', $guard );
	try { $result = $service->get_attempt_history( $user, $sprint ); }
	finally { remove_filter( 'query', $guard ); }
	$attempt_queries = array_values( array_filter( $queries, static function ( $sql ) { return str_contains( $sql, 'sprint_engine_enrolments' ); } ) );
	history_check( ! is_wp_error( $result ) && 1 === count( $attempt_queries ), 'Exactly one enrolment SELECT; no lifecycle transaction, writes or Step queries.' );
	history_check( $before === history_rows(), 'All enrolment and Step rows remain byte/logically identical.' );
	return $result;
}
function history_project( $row ) {
	return array(
		'attempt_id' => (int) $row['id'], 'sprint_id' => (int) $row['sprint_id'],
		'attempt_number' => (int) $row['attempt_number'], 'status' => $row['status'],
		'current_step_id' => null === $row['current_step_id'] ? null : (int) $row['current_step_id'],
		'started_at' => $row['started_at'], 'last_activity_at' => $row['last_activity_at'],
		'completed_at' => $row['completed_at'], 'created_at' => $row['created_at'], 'updated_at' => $row['updated_at'],
	);
}
wp_set_current_user( 1 );
$manager = new StructureManager(); $repo = new EnrolmentRepository();
$sprints = array(); $steps = array(); $users = array();
foreach ( array( 'History first', 'History second' ) as $title ) {
	$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => $title ) );
	$sprints[] = $sprint; $steps[$sprint] = array( $manager->quick_add( $sprint, 'First' ), $manager->quick_add( $sprint, 'Second' ) );
	foreach ( $steps[$sprint] as $step ) { wp_update_post( array( 'ID' => $step, 'post_status' => 'publish' ) ); }
	Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
}
for ( $i = 0; $i < 3; ++$i ) { $users[] = wp_create_user( 'history-' . wp_generate_password( 12, false ), wp_generate_password() ); }
$user = $users[0]; $sprint = $sprints[0]; $now = '2026-10-08 09:00:00';
$lifecycle = new ProgressService( static function () use ( &$now ) { return $now; } );
$reader = new ProgressService( static function () { throw new RuntimeException( 'History invoked the lifecycle clock.' ); } );
history_check( array() === history_read( $reader, $users[2] ), 'No persisted attempts returns an empty array without enrolment creation.' );
$state = $lifecycle->start_sprint( $user, $sprint );
$one = history_read( $reader, (string) $user, (string) $sprint );
history_check( array( history_project( $repo->find( $user, $sprint ) ) ) === $one, 'One user, one Sprint, one active attempt; exact public metadata and numeric IDs.' );
history_check( ! array_key_exists( 'user_id', $one[0] ) && ! array_key_exists( 'id', $one[0] ) && 10 === count( $one[0] ), 'Only the ten public fields are projected, with no user ID or repository ID.' );
for ( $run = 1; $run <= 2; ++$run ) {
	$now = '2026-10-08 ' . ( 9 + $run ) . ':00:00';
	foreach ( $steps[$sprint] as $step ) { $lifecycle->complete_step( $user, $sprint, $step ); }
	$now = 2 === $run ? '2026-10-08 08:00:00' : '2026-10-08 12:00:00'; // Sequence, not timestamps, determines newest.
	$lifecycle->restart_sprint( $user, $sprint );
}
$lifecycle->start_sprint( $user, $sprints[1] );
$lifecycle->start_sprint( $users[1], $sprint );
$history = history_read( $reader, $user, $sprint );
history_check( array( 3, 2, 1 ) === array_column( $history, 'attempt_number' ) && 3 === count( array_unique( array_column( $history, 'attempt_id' ) ) ), 'Runs 1, 2, 3 retained with distinct identities, newest sequence first.' );
history_check( array( 'in_progress', 'completed', 'completed' ) === array_column( $history, 'status' ) && null === $history[1]['current_step_id'] && $steps[$sprint][0] === $history[0]['current_step_id'], 'Completed and active metadata, including null/current Step, are preserved.' );
$raw = array_filter( history_rows()[0], static function ( $row ) use ( $user, $sprint ) { return (int) $row['user_id'] === $user && (int) $row['sprint_id'] === $sprint; } );
$raw = array_reverse( array_values( $raw ) );
history_check( array_map( 'history_project', $raw ) === $history, 'All five timestamp fields are returned unchanged for every attempt.' );
$all = history_read( $reader, $user );
history_check( array( $sprint, $sprint, $sprint, $sprints[1] ) === array_column( $all, 'sprint_id' ) && array( 3, 2, 1, 1 ) === array_column( $all, 'attempt_number' ), 'Global history groups by Sprint ID ascending and sequence descending.' );
history_check( array( $all[3] ) === history_read( $reader, $user, $sprints[1] ), 'Optional filter selects only that Sprint.' );
$other = history_read( $reader, $users[1] );
history_check( 1 === count( $other ) && ! in_array( $other[0]['attempt_id'], array_column( $all, 'attempt_id' ), true ), 'SQL-level user isolation excludes another member attempts.' );
$runtime = $lifecycle->get_state( $user, $sprint );
history_read( $reader, $user );
history_check( $runtime === $lifecycle->get_state( $user, $sprint ) && $runtime === $lifecycle->get_progress( $user, $sprint ), 'Existing canonical state/progress remain unchanged after history reads.' );

$invalid = array( 0, -1, '0', '', '1 OR 1=1', '1.2', 1.0, true, false, array(), new stdClass(), PHP_INT_MAX, '999999999999999999999999999' );
foreach ( array_merge( array( null ), $invalid ) as $input ) {
	$result = $reader->get_attempt_history( $input );
	history_check( is_wp_error( $result ) && 'sprint_engine_progress_user' === $result->get_error_code(), 'Malformed or nonexistent user fails safely.' );
}
foreach ( array_merge( $invalid, array( $steps[$sprint][0], 1 ) ) as $input ) {
	$result = $reader->get_attempt_history( $user, $input );
	history_check( is_wp_error( $result ) && 'sprint_engine_progress_sprint' === $result->get_error_code(), 'Malformed, nonexistent or wrong-type Sprint fails safely.' );
}
// Legacy/corrupt non-positive attempts must never enter the public history.
$repo->create( array( 'user_id' => $user, 'sprint_id' => $sprint, 'attempt_number' => 0, 'status' => 'not_started', 'created_at' => $now, 'updated_at' => $now ) );
history_check( $all === history_read( $reader, $user ), 'Attempt number zero is excluded in SQL.' );
Meta::save( $sprint, array( '_sprint_engine_launchable' => false ) );
history_check( $history === history_read( $reader, $user, $sprint ), 'Unlaunchable Sprint history remains readable.' );
$manager->apply_linear_order( $sprint, array_reverse( $steps[$sprint] ) );
wp_update_post( array( 'ID' => $steps[$sprint][0], 'post_content' => '<!-- wp:paragraph --><p>Changed content, removed prior blocks.</p><!-- /wp:paragraph -->' ) );
history_check( $history === history_read( $reader, $user, $sprint ), 'Step reorder and content changes preserve attempt history.' );
$repo->update( $history[0]['attempt_id'], array( 'current_step_id' => 999999999 ) );
update_post_meta( $steps[$sprint][0], '_sprint_engine_next_step_id', $steps[$sprint][0] );
$history[0]['current_step_id'] = 999999999;
$events = 0; $access = 0;
$listener = static function () use ( &$events ) { ++$events; };
$access_listener = static function () use ( &$access ) { ++$access; return false; };
foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed' ) as $event ) { add_action( 'sprint_engine/' . $event, $listener ); }
add_filter( 'sprint_engine/user_can_access_sprint', $access_listener );
wp_set_current_user( $users[1] );
history_check( $history === history_read( $reader, $user, $sprint ) && 0 === $events && 0 === $access, 'Stale pointer/broken structure preserved; no clock, lifecycle events or AccessManager; trusted caller selects user.' );
foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed' ) as $event ) { remove_action( 'sprint_engine/' . $event, $listener ); }
remove_filter( 'sprint_engine/user_can_access_sprint', $access_listener );
wp_set_current_user( 1 );
wp_trash_post( $sprint );
history_check( $history === history_read( $reader, $user, $sprint ), 'Trashed Sprint can still be identified by a history filter.' );
$before_deleted = history_read( $reader, $user );
wp_delete_post( $sprint, true );
history_check( $before_deleted === history_read( $reader, $user ), 'Global history retains persisted Sprint IDs after post deletion.' );
history_check( 'sprint_engine_progress_sprint' === $reader->get_attempt_history( $user, $sprint )->get_error_code(), 'Deleted post cannot safely be identified as a supplied Sprint filter.' );

$previous_suppression = $wpdb->suppress_errors;
$failure = static function ( $sql ) { return str_contains( $sql, 'sprint_engine_enrolments' ) ? str_replace( 'sprint_engine_enrolments', 'sprint_engine_missing_history_table', $sql ) : $sql; };
add_filter( 'query', $failure ); ob_start(); $error = $reader->get_attempt_history( $user ); $output = ob_get_clean(); remove_filter( 'query', $failure );
history_check( is_wp_error( $error ) && 'sprint_engine_progress_persistence' === $error->get_error_code() && '' === $output && ! str_contains( $error->get_error_message(), $wpdb->prefix ), 'Persistence failure returns generic error with no SQL/table output.' );
history_check( $previous_suppression === $wpdb->suppress_errors, 'Database error suppression restored after failure.' );
foreach ( array( 'sprint_engine_schema_version' => '1', 'sprint_engine_installation_failed' => true ) as $option => $value ) {
	$old = get_option( $option, false ); update_option( $option, $value );
	history_check( 'sprint_engine_progress_persistence' === $reader->get_attempt_history( $user )->get_error_code(), 'Incomplete schema/installation fails without repair.' );
	false === $old ? delete_option( $option ) : update_option( $option, $old );
}
history_check( '2' === SPRINT_ENGINE_SCHEMA_VERSION && '2' === get_option( 'sprint_engine_schema_version' ), 'Core schema remains 2.' );
$before_upgrade = history_rows();
update_option( 'sprint_engine_version', '0.2.0' );
( new ThePath\SprintEngine\Plugin() )->maybe_upgrade();
history_check( '0.2.1' === get_option( 'sprint_engine_version' ) && '2' === get_option( 'sprint_engine_schema_version' ) && $before_upgrade === history_rows(), '0.2.0 to 0.2.1 upgrade retains schema 2 and every historical progress row.' );
foreach ( $users as $id ) {
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $id ) ); wp_delete_user( $id );
}
foreach ( array_merge( $steps[$sprints[0]], $steps[$sprints[1]], $sprints ) as $id ) { wp_delete_post( $id, true ); }
echo "SE-016 attempt history completed: $checks assertions.\n";
