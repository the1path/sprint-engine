<?php
/** SE-019: real disposable WordPress persistence, REST, rollback and contention. */
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
$service = new ProgressService();
$events = array();
foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed' ) as $event ) {
	add_action( 'sprint_engine/' . $event, static function ( ...$args ) use ( &$events, $event ) {
		// A second service transaction is possible only after the first commits.
		$state = ( new ProgressService() )->get_state( $args[0], $args[1] );
		if ( is_wp_error( $state ) || $state['attempt_number'] < end( $args ) ) { throw new RuntimeException( 'Event was not post-commit with the documented identity.' ); }
		$events[] = array_merge( array( $event ), $args );
	}, 10, 5 );
}
if ( isset( $argv[1] ) && 'worker' === $argv[1] ) {
	while ( microtime( true ) < (float) $argv[7] ) { usleep( 1000 ); }
	$result = 'restart' === $argv[2] ? $service->restart_sprint( (int) $argv[3], (int) $argv[4], (int) $argv[5] ) : $service->complete_step( (int) $argv[3], (int) $argv[4], (int) $argv[6], (int) $argv[5] );
	echo wp_json_encode( array( 'result' => is_wp_error( $result ) ? $result->get_error_code() : $result, 'events' => $events ) );
	exit( 0 );
}
$checks = 0;
function restart_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks; echo "PASS: $message\n";
}
function restart_rows( $user, $sprint ) {
	global $wpdb;
	$result = array();
	foreach ( array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) as $suffix ) {
		$result[] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}$suffix WHERE user_id=%d AND sprint_id=%d ORDER BY id", $user, $sprint ), ARRAY_A );
	}
	return $result;
}
// Dispatch applies the registered private/no-store projection exactly as REST does.
function restart_rest( $sprint, $expected = null, $step = null ) {
	$route = null === $step ? 'sprints/' . $sprint . '/restart' : 'steps/' . $step . '/complete';
	$request = new WP_REST_Request( 'POST', '/sprint-engine/v1/' . $route );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	if ( null !== $expected ) { $request->set_body_params( array( 'expected_attempt_id' => $expected ) ); }
	$response = rest_get_server()->dispatch( $request );
	return apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
}
function restart_workers( $operations, $user, $sprint, $attempt, $step ) {
	$command = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ) );
	foreach ( array( 'pdo_sqlite', 'sqlite3', 'mysqli' ) as $extension ) {
		if ( extension_loaded( $extension ) ) { $command[] = '-d'; $command[] = 'extension=' . $extension; }
	}
	$when = (string) ( microtime( true ) + 1 );
	$workers = array();
	foreach ( $operations as $operation ) {
		$process = proc_open( array_merge( $command, array( __FILE__, 'worker', $operation, (string) $user, (string) $sprint, (string) $attempt, (string) $step, $when ) ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Cannot start contention process.' ); }
		fclose( $pipes[0] ); $workers[] = array( $process, $pipes );
	}
	$result = array();
	foreach ( $workers as [ $process, $pipes ] ) {
		$output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		restart_check( 0 === proc_close( $process ) && '' === $errors, 'Independent contention process exits cleanly.' );
		$result[] = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
	}
	return $result;
}
wp_set_current_user( 1 );
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'SE-019 restart' ) );
$manager = new StructureManager();
$steps = array();
for ( $i = 0; $i < 3; ++$i ) {
	$steps[] = $manager->quick_add( $sprint, 'Restart Step ' . $i );
	wp_update_post( array( 'ID' => end( $steps ), 'post_status' => 'publish' ) );
}
Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
$users = array();
for ( $i = 0; $i < 5; ++$i ) { $users[] = wp_create_user( 'se019-' . wp_generate_password( 12, false ), wp_generate_password() ); }
$user = $users[0];
wp_set_current_user( $user );
$first = $service->start_sprint( $user, $sprint );
$service->complete_step( $user, $sprint, $steps[0], $first['attempt_id'] );
$before = restart_rows( $user, $sprint );
foreach ( array( null, 0, -1, true, array( 1 ), '1.5', '01', '999999999999999999999999' ) as $bad ) {
	restart_check( 400 === restart_rest( $sprint, $bad )->get_status() && $before === restart_rows( $user, $sprint ), 'Missing/malformed restart attempt fails closed without writes.' );
	restart_check( 400 === restart_rest( $sprint, $bad, $steps[1] )->get_status() && $before === restart_rows( $user, $sprint ), 'Missing/malformed completion attempt fails closed without writes.' );
}
// Each failure happens inside the real database transaction, after any prior writes.
foreach ( array( 'supersede', 'enrolment', 'step', 'commit' ) as $failure ) {
	$count = count( $events );
	$block = static function ( $sql ) use ( $failure ) {
		if ( ( 'commit' === $failure && 'COMMIT' === $sql ) || ( 'supersede' === $failure && str_starts_with( $sql, 'UPDATE' ) && str_contains( $sql, "'restarted'" ) ) || ( 'enrolment' === $failure && str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, 'sprint_engine_enrolments' ) ) || ( 'step' === $failure && str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, 'sprint_engine_step_progress' ) ) ) { return ''; }
		return $sql;
	};
	add_filter( 'query', $block );
	$result = $service->restart_sprint( $user, $sprint, $first['attempt_id'] );
	remove_filter( 'query', $block );
	restart_check( is_wp_error( $result ) && 'sprint_engine_progress_persistence' === $result->get_error_code() && $before === restart_rows( $user, $sprint ) && $count === count( $events ), 'Atomic rollback restores supersession and all progress/events: ' . $failure );
}
$events = array();
$second = $service->restart_sprint( $user, $sprint, $first['attempt_id'] );
restart_check( 2 === $second['attempt_number'] && $first['attempt_id'] !== $second['attempt_id'] && $steps[0] === $second['current_step_id'] && 0 === $second['completed_steps'] && null === $second['completed_at'], 'In-progress Attempt 1 restarts into Attempt 2 at Step 1 with zero completions.' );
$expected_old = $before[0][0]; $expected_old['status'] = 'restarted';
$after = restart_rows( $user, $sprint );
restart_check( $expected_old === $after[0][0] && $before[1] === array_slice( $after[1], 0, count( $before[1] ) ), 'Only source status changes; every ID, timestamp, pointer and Step record survives.' );
restart_check( array( 'sprint_started', 'step_started' ) === array_column( $events, 0 ) && $second['attempt_id'] === $events[0][3] && 2 === $events[1][5], 'New start/Step hooks fire once after commit with unchanged argument shapes; no completed hook.' );
foreach ( array( null, $steps[0], $steps[1] ) as $step ) {
	$response = restart_rest( $sprint, $first['attempt_id'], $step );
	restart_check( 409 === $response->get_status() && str_contains( $response->get_headers()['Cache-Control'], 'no-store' ) && $after === restart_rows( $user, $sprint ), 'Superseded restart/Step writes reject stale identity, including the same Step ID, with private no-store.' );
}
restart_check( $second['attempt_id'] === $service->resume_sprint( $user, $sprint )['attempt_id'], 'Resume selects only the replacement attempt.' );
$third = $service->restart_sprint( $user, $sprint, $second['attempt_id'] );
$history = $service->get_attempt_history( $user, $sprint );
restart_check( array( 'in_progress', 'restarted', 'restarted' ) === array_column( $history, 'status' ) && array( 3, 2, 1 ) === array_column( $history, 'attempt_number' ), 'History returns every restarted attempt in canonical order after multiple restarts.' );
foreach ( $steps as $step ) { $service->complete_step( $user, $sprint, $step, $third['attempt_id'] ); }
$completed = restart_rows( $user, $sprint );
$fourth = $service->restart_sprint( $user, $sprint, $third['attempt_id'] );
$now = restart_rows( $user, $sprint );
restart_check( 4 === $fourth['attempt_number'] && $completed[0] === array_slice( $now[0], 0, 3 ) && $completed[1] === array_slice( $now[1], 0, count( $completed[1] ) ), 'Completed restart and all earlier restarted histories remain exactly intact.' );
$before = restart_rows( $user, $sprint );
wp_set_current_user( 0 );
restart_check( 401 === restart_rest( $sprint, $fourth['attempt_id'] )->get_status(), 'Anonymous restart is rejected.' );
wp_set_current_user( $users[1] );
$owned = $service->start_sprint( $users[1], $sprint );
$other_before = restart_rows( $users[1], $sprint );
restart_check( 409 === restart_rest( $sprint, $fourth['attempt_id'] )->get_status() && $before === restart_rows( $user, $sprint ), 'Foreign attempt identity cannot mutate another member.' );
restart_check( $other_before === restart_rows( $users[1], $sprint ) && $owned['attempt_id'] !== $fourth['attempt_id'], 'Foreign identity also leaves the caller own active attempt untouched.' );
wp_set_current_user( $user );
// Change entitlement/readiness between the controller precheck and locked guard.
foreach ( array( 'access', 'launchable', 'draft', 'password', 'step' ) as $change ) {
	$denied = false;
	$access = static function ( $allowed ) use ( &$denied ) { return $allowed && ! $denied; };
	$race = static function ( $sql ) use ( $change, $sprint, $steps, &$denied ) {
		if ( str_contains( $sql, 'SET ID = ID WHERE ID =' ) ) {
			if ( 'access' === $change ) { $denied = true; }
			if ( 'launchable' === $change ) { update_post_meta( $sprint, '_sprint_engine_launchable', false ); }
			if ( 'draft' === $change ) { wp_update_post( array( 'ID' => $sprint, 'post_status' => 'draft' ) ); }
			if ( 'password' === $change ) { wp_update_post( array( 'ID' => $sprint, 'post_password' => 'private' ) ); }
			if ( 'step' === $change ) { wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'draft' ) ); }
		}
		return $sql;
	};
	add_filter( 'sprint_engine/user_can_access_sprint', $access ); add_filter( 'query', $race );
	$response = restart_rest( $sprint, $fourth['attempt_id'] );
	remove_filter( 'query', $race ); remove_filter( 'sprint_engine/user_can_access_sprint', $access );
	restart_check( ( 'access' === $change ? 403 : 404 ) === $response->get_status() && $before === restart_rows( $user, $sprint ), 'Locked member guard rejects changed availability/authorization before mutation: ' . $change );
	wp_set_current_user( 1 );
	wp_update_post( array( 'ID' => $sprint, 'post_status' => 'publish', 'post_password' => '' ) );
	wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'publish' ) );
	update_post_meta( $sprint, '_sprint_engine_launchable', true );
	wp_set_current_user( $user );
}
// A corrupt latest restarted row is an error, never a fallback to older history.
$repo = new EnrolmentRepository();
$repo->update( $fourth['attempt_id'], array( 'status' => 'restarted' ) );
$orphan = restart_rows( $user, $sprint );
foreach ( array( 'get_state', 'resume_sprint', 'start_sprint' ) as $operation ) {
	restart_check( is_wp_error( $service->$operation( $user, $sprint ) ) && $orphan === restart_rows( $user, $sprint ), 'Invalid latest restarted attempt fails safely without selecting/editing historical state: ' . $operation );
}
$repo->update( $fourth['attempt_id'], array( 'status' => 'in_progress' ) );
// Real independent processes share a captured source identity, never resolve latest.
foreach ( array( array( 'restart', 'restart' ), array( 'restart', 'complete' ), array( 'complete', 'restart' ) ) as $index => $operations ) {
	$race_user = $users[$index + 2];
	$source = $service->start_sprint( $race_user, $sprint );
	$results = restart_workers( $operations, $race_user, $sprint, $source['attempt_id'], $steps[0] );
	$state = $service->get_state( $race_user, $sprint );
	$rows = restart_rows( $race_user, $sprint );
	restart_check( 2 === $state['attempt_number'] && 0 === $state['completed_steps'] && 2 === count( $rows[0] ) && 'restarted' === $rows[0][0]['status'], 'Contention creates one replacement, never completes its same-ID Step: ' . implode( '/', $operations ) );
	$combined = array_merge( $results[0]['events'], $results[1]['events'] );
	restart_check( 1 === count( array_filter( $combined, static function ( $event ) { return 'sprint_started' === $event[0]; } ) ), 'Only the committed replacement publishes a Sprint start across processes.' );
	if ( $operations[0] === $operations[1] ) {
		restart_check( in_array( 'sprint_engine_progress_stale_attempt', array_column( $results, 'result' ), true ) && 2 === count( $combined ), 'Concurrent identical restarts produce one success, one conflict and one first-Step hook.' );
	}
}
wp_set_current_user( 1 );
foreach ( $users as $id ) {
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $id ) );
	wp_delete_user( $id );
}
foreach ( array_merge( $steps, array( $sprint ) ) as $id ) { wp_delete_post( $id, true ); }
echo "SE-019 completed: $checks assertions.\n";
