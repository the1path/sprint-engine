<?php
/** SE-012 attempt isolation and restart checks on disposable WordPress only. */
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
function attempt_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks; echo "PASS: $message\n";
}
function attempt_rows( $user, $sprint, $attempt = null ) {
	global $wpdb;
	$rows = array();
	foreach ( array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) as $suffix ) {
		$sql = $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}$suffix WHERE user_id=%d AND sprint_id=%d", $user, $sprint );
		if ( null !== $attempt ) { $sql .= $wpdb->prepare( ' AND attempt_number=%d', $attempt ); }
		$rows[] = $wpdb->get_results( $sql . ' ORDER BY id', ARRAY_A );
	}
	return $rows;
}
wp_set_current_user( 1 );
$manager = new StructureManager();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Attempt isolation' ) );
$steps = array();
for ( $i = 0; $i < 5; ++$i ) { $steps[] = $manager->quick_add( $sprint, 'Attempt Step ' . $i ); }
foreach ( $steps as $published_step ) { wp_update_post( array( 'ID' => $published_step, 'post_status' => 'publish' ) ); }
Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
$users = array();
for ( $i = 0; $i < 2; ++$i ) { $users[] = wp_create_user( 'attempt-' . wp_generate_password( 12, false ), wp_generate_password() ); }
$user = $users[0];
$now = '2026-10-02 10:00:00';
$service = new ProgressService( static function () use ( &$now ) { return $now; } );
$repo = new EnrolmentRepository();
$events = array();
$listeners = array();
foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed' ) as $event ) {
	$listeners[$event] = static function ( ...$args ) use ( &$events, $event, $repo ) {
		$row = $repo->find( $args[0], $args[1] );
		attempt_check( $row['id'] === $args[count($args)-2] && $row['attempt_number'] === end($args), 'Hook observes committed attempt identity: ' . $event );
		$events[] = array_merge( array( $event ), $args );
	};
	add_action( 'sprint_engine/' . $event, $listeners[$event], 10, 5 );
}
$state = $service->get_state( $user, $sprint );
attempt_check( null === $state['attempt_id'] && null === $state['attempt_number'], 'Never started has null attempt identity.' );
attempt_check( 'sprint_engine_progress_restart_unavailable' === $service->restart_sprint( $user, $sprint )->get_error_code(), 'Restart never acts as first Start.' );
$state = $service->start_sprint( $user, $sprint );
attempt_check( 1 === $state['attempt_number'] && is_int( $state['attempt_id'] ), 'First Start creates Attempt 1.' );
$before = attempt_rows( $user, $sprint );
attempt_check( $state === $service->start_sprint( $user, $sprint ) && $before === attempt_rows( $user, $sprint ), 'Start retry preserves Attempt 1.' );
attempt_check( $state === $service->restart_sprint( $user, $sprint ) && $before === attempt_rows( $user, $sprint ), 'Active restart returns unchanged state without starting over.' );
foreach ( $steps as $step ) { $state = $service->complete_step( $user, $sprint, $step ); }
$history = attempt_rows( $user, $sprint, 1 );
attempt_check( 'completed' === $state['status'] && $now === $state['completed_at'], 'Attempt 1 completes with own timestamps.' );
attempt_check( $state === $service->start_sprint( $user, $sprint ) && $history === attempt_rows( $user, $sprint, 1 ), 'Start after completion never restarts.' );

// Failed restart after new enrolment insert must roll back that insert and events.
foreach ( array( 'step', 'commit' ) as $failure ) {
	$before = attempt_rows( $user, $sprint ); $count = count( $events );
	$block = static function ( $sql ) use ( $failure ) {
		return ( 'commit' === $failure && 'COMMIT' === $sql ) || ( 'step' === $failure && str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, 'sprint_engine_step_progress' ) ) ? '' : $sql;
	};
	add_filter( 'query', $block );
	$result = $service->restart_sprint( $user, $sprint );
	remove_filter( 'query', $block );
	attempt_check( is_wp_error( $result ) && 'sprint_engine_progress_persistence' === $result->get_error_code() && $before === attempt_rows( $user, $sprint ) && $count === count( $events ), 'Restart rollback retains all rows and emits no events: ' . $failure );
}
Meta::save( $sprint, array( '_sprint_engine_launchable' => false ) );
attempt_check( 'sprint_engine_progress_not_launchable' === $service->restart_sprint( $user, $sprint )->get_error_code(), 'Restart requires current launchability.' );
Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
$now = '2026-10-02 11:00:00';
$state = $service->restart_sprint( $user, $sprint );
attempt_check( 2 === $state['attempt_number'] && $steps[0] === $state['current_step_id'] && 0 === $state['completed_steps'] && 0.0 === $state['percentage'] && $now === $state['started_at'] && null === $state['completed_at'], 'Attempt 2 starts immediately at first Step with zero progress and own timestamps.' );
attempt_check( $history === attempt_rows( $user, $sprint, 1 ), 'Restart preserves Attempt 1 byte/logically unchanged.' );
$before = attempt_rows( $user, $sprint ); $count = count( $events );
attempt_check( $state === $service->restart_sprint( $user, $sprint ) && $before === attempt_rows( $user, $sprint ) && $count === count( $events ), 'Duplicate restart creates no Attempt 3 and no events.' );
$state = $service->complete_step( $user, $sprint, $steps[0] );
attempt_check( 1 === $state['completed_steps'] && $history === attempt_rows( $user, $sprint, 1 ), 'Attempt 2 completion changes only Attempt 2.' );
$before = attempt_rows( $user, $sprint );
attempt_check( $state === $service->complete_step( $user, $sprint, $steps[0] ) && $before === attempt_rows( $user, $sprint ), 'Completion retries are scoped to current attempt.' );
$row = $repo->find( $user, $sprint );
$repo->update( $row['id'], array( 'current_step_id' => 999999999 ) );
attempt_check( $steps[1] === $service->resume_sprint( $user, $sprint )['current_step_id'] && $history === attempt_rows( $user, $sprint, 1 ), 'Stale pointer recovery uses only current attempt.' );
foreach ( array_slice( $steps, 1 ) as $step ) { $state = $service->complete_step( $user, $sprint, $step ); }
attempt_check( 'completed' === $state['status'] && 2 === $state['attempt_number'], 'Attempt 2 completes independently.' );
$second_history = attempt_rows( $user, $sprint, 2 );
// A changed current structure must not mix historical completion into Attempt 3.
$manager->save_step( $steps[0], array( '_sprint_engine_sprint_id' => 0 ) );
$current = array_slice( $steps, 1 );
$now = '2026-10-02 09:00:00'; // Deliberately earlier timestamp: sequence is authoritative.
$state = $service->restart_sprint( $user, $sprint );
attempt_check( 3 === $state['attempt_number'] && $current[0] === $state['current_step_id'] && 0 === $state['completed_steps'] && 4 === $state['total_steps'], 'Attempt 3 uses current structure and sequence rather than timestamps.' );
attempt_check( $history === attempt_rows( $user, $sprint, 1 ) && $second_history === attempt_rows( $user, $sprint, 2 ), 'Structure changes and restart do not rewrite past attempts.' );
attempt_check( array( '1', '2', '3' ) === array_map( 'strval', array_column( attempt_rows( $user, $sprint )[0], 'attempt_number' ) ), 'Attempt allocation is strictly sequential.' );
attempt_check( 1 === $service->start_sprint( $users[1], $sprint )['attempt_number'], 'Different users have independent sequences.' );
$legacy = $repo->find( $users[1], $sprint );
$repo->update( $legacy['id'], array( 'status' => 'not_started' ) );
$before = attempt_rows( $users[1], $sprint );
$result = $service->restart_sprint( $users[1], $sprint );
attempt_check( is_wp_error( $result ) && 'sprint_engine_progress_restart_unavailable' === $result->get_error_code() && $before === attempt_rows( $users[1], $sprint ), 'Legacy not_started enrolment is never silently restarted.' );
foreach ( $listeners as $event => $listener ) { remove_action( 'sprint_engine/' . $event, $listener ); }
$counts = array_count_values( array_column( array_filter( $events, static function ( $event ) use ( $user ) { return $event[1] === $user; } ), 0 ) );
attempt_check( 3 === $counts['sprint_started'] && 2 === $counts['sprint_completed'], 'New starts/completions fire once per attempt with trailing identity.' );
foreach ( $users as $id ) {
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $id ) );
	wp_delete_user( $id );
}
foreach ( array_merge( $steps, array( $sprint ) ) as $id ) { wp_delete_post( $id, true ); }
echo "SE-012 attempts completed: $checks assertions.\n";
