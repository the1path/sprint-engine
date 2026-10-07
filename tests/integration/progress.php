<?php
/**
 * SE-003 real WordPress/database checks. Disposable test installation only.
 *
 * @package SprintEngine
 */

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\EnrolmentRepository;
use ThePath\SprintEngine\Progress\StepProgressRepository;
use ThePath\SprintEngine\Progress\ProgressService;

$wordpress = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $wordpress || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
	fwrite( STDERR, "Set SE_TEST_WP_ROOT and SE_TEST_DISPOSABLE=yes for a disposable test site.\n" );
	exit( 1 );
}
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );

// Independent processes use the same public service and database for contention checks.
if ( isset( $argv[1] ) && 'worker' === $argv[1] ) {
	while ( microtime( true ) < (float) $argv[6] ) { usleep( 1000 ); }
	$worker_events = array();
	foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed' ) as $event ) {
		add_action( 'sprint_engine/' . $event, static function ( ...$args ) use ( &$worker_events, $event ) { $worker_events[] = array_merge( array( $event ), $args ); }, 10, 5 );
	}
	$service = new ProgressService();
	$result = 'start' === $argv[2] ? $service->start_sprint( (int) $argv[3], (int) $argv[4] ) : ( 'restart' === $argv[2] ? $service->restart_sprint( (int) $argv[3], (int) $argv[4] ) : $service->complete_step( (int) $argv[3], (int) $argv[4], (int) $argv[5] ) );
	echo wp_json_encode( array( 'result' => is_wp_error( $result ) ? $result->get_error_code() : $result, 'events' => $worker_events ) );
	exit( 0 );
}

$checks = 0;
function sprint_engine_progress_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
	echo 'PASS: ' . $message . PHP_EOL;
}
function sprint_engine_progress_error( $result, $code, $message ) {
	sprint_engine_progress_check( is_wp_error( $result ) && $code === $result->get_error_code(), $message );
}
function sprint_engine_progress_snapshot( $user, $sprint ) {
	global $wpdb;
	return array(
		$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments WHERE user_id = %d AND sprint_id = %d", $user, $sprint ), ARRAY_A ),
		$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress WHERE user_id = %d AND sprint_id = %d ORDER BY step_id", $user, $sprint ), ARRAY_A ),
	);
}

wp_set_current_user( 1 );
$manager = new StructureManager();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_title' => 'Progress five Step fixture' ) );
$other = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_title' => 'Other progress Sprint' ) );
$steps = array();
for ( $i = 0; $i < 5; ++$i ) { $steps[] = $manager->quick_add( $sprint, 'Progress Step ' . ( $i + 1 ) ); }
foreach ( $steps as $published_step ) { wp_update_post( array( 'ID' => $published_step, 'post_status' => 'publish' ) ); }
$foreign = $manager->quick_add( $other, 'Foreign Step' );
// Deliberately use a non-ID order so navigation cannot accidentally follow post IDs.
$steps = array( $steps[2], $steps[4], $steps[0], $steps[3], $steps[1] );
sprint_engine_progress_check( true === $manager->apply_linear_order( $sprint, $steps ), 'Fixture uses the existing authoring service and reordered explicit links.' );
$users = array();
for ( $i = 0; $i < 9; ++$i ) {
	$users[] = wp_insert_user( array( 'user_login' => 'progress-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
}
$user = $users[0];
$enrolments = new EnrolmentRepository();
$progress = new StepProgressRepository();
$now = '2026-09-23 10:00:00';
$service = new ProgressService( static function () use ( &$now ) { return $now; } );
$events = array();
$event_states = array();
foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed' ) as $event ) {
	add_action( 'sprint_engine/' . $event, static function ( ...$args ) use ( &$events, &$event_states, $event, $enrolments ) {
		$events[] = array_merge( array( $event ), $args );
		$event_states[] = $enrolments->find( $args[0], $args[1] );
	}, 10, 5 );
}

$state = $service->get_state( $user, $sprint );
sprint_engine_progress_check( 'not_started' === $state['status'] && null === $state['current_step_id'] && 0.0 === $state['percentage'] && 5 === $state['total_steps'], 'Unstarted state is sensible on a valid non-launchable Sprint.' );
sprint_engine_progress_check( array( array(), array() ) === sprint_engine_progress_snapshot( $user, $sprint ) && ! $events, 'Reading creates no enrolment, Step progress or events.' );
sprint_engine_progress_error( $service->start_sprint( $user, $sprint ), 'sprint_engine_progress_not_launchable', 'Non-launchable start rejected.' );
sprint_engine_progress_check( true === Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ), 'Five-Step Sprint is launchable.' );
foreach ( array( 0, -1, 1.2, true, array(), 'bad', '99999999999999999999999', 999999999 ) as $invalid ) {
	sprint_engine_progress_error( $service->start_sprint( $invalid, $sprint ), 'sprint_engine_progress_user', 'Invalid/nonexistent user rejected.' );
	sprint_engine_progress_error( $service->get_state( $user, $invalid ), 'sprint_engine_progress_sprint', 'Invalid/nonexistent Sprint rejected.' );
	sprint_engine_progress_error( $service->complete_step( $user, $sprint, $invalid ), 'sprint_engine_progress_step', 'Invalid/nonexistent completion Step rejected.' );
}
sprint_engine_progress_error( $service->get_state( $user, $steps[0] ), 'sprint_engine_progress_sprint', 'Wrong post type is not a Sprint.' );

// No current admin identity is required by this internal, caller-authorized API.
wp_set_current_user( 0 );
$state = $service->start_sprint( (string) $user, (string) $sprint );
sprint_engine_progress_check( 'in_progress' === $state['status'] && $steps[0] === $state['current_step_id'], 'Start sets canonical status and calculated start Step, accepting decimal IDs.' );
sprint_engine_progress_check( $now === $state['started_at'] && $now === $state['last_activity_at'] && null === $state['completed_at'], 'Start uses injected UTC timestamps.' );
sprint_engine_progress_check( 1 === count( sprint_engine_progress_snapshot( $user, $sprint )[0] ) && 'started' === $progress->find( $user, $sprint, $steps[0] )['status'], 'Start creates exactly one enrolment and initial Step row.' );
sprint_engine_progress_check( array( array( 'sprint_started', $user, $sprint, $state['attempt_id'], 1 ), array( 'step_started', $user, $sprint, $steps[0], $state['attempt_id'], 1 ) ) === $events, 'Start hooks have specification argument shapes and fire once.' );
sprint_engine_progress_check( 'in_progress' === $event_states[0]['status'], 'Start hooks observe persisted state.' );
$before = sprint_engine_progress_snapshot( $user, $sprint );
$now = '2026-09-23 11:00:00';
sprint_engine_progress_check( $state === $service->start_sprint( $user, $sprint ) && $before === sprint_engine_progress_snapshot( $user, $sprint ) && 2 === count( $events ), 'Repeated start preserves all rows, original start/activity times and hooks.' );
sprint_engine_progress_check( $state === $service->get_state( $user, $sprint ) && $before === sprint_engine_progress_snapshot( $user, $sprint ), 'Canonical reads leave progress unchanged.' );
sprint_engine_progress_error( $service->complete_step( $user, $sprint, $steps[3] ), 'sprint_engine_progress_out_of_order', 'Future incomplete Step rejected.' );
sprint_engine_progress_error( $service->complete_step( $user, $sprint, $foreign ), 'sprint_engine_progress_step', 'Cross-Sprint completion rejected.' );
sprint_engine_progress_error( $service->complete_step( $users[1], $sprint, $steps[0] ), 'sprint_engine_progress_state', 'Unenrolled completion rejected.' );

for ( $i = 0; $i < 5; ++$i ) {
	$now = '2026-09-23 12:0' . $i . ':00';
	$state = $service->complete_step( $user, $sprint, $steps[$i] );
	sprint_engine_progress_check( ! is_wp_error( $state ) && $i + 1 === $state['completed_steps'] && (float) ( 20 * ( $i + 1 ) ) === $state['percentage'], 'Completion calculates exact progress at Step ' . ( $i + 1 ) );
	sprint_engine_progress_check( ( $steps[$i + 1] ?? null ) === $state['current_step_id'], 'Completion follows explicit next link at Step ' . ( $i + 1 ) );
	sprint_engine_progress_check( $now === $progress->find( $user, $sprint, $steps[$i] )['completed_at'], 'Step completion records its UTC timestamp once.' );
	$before = sprint_engine_progress_snapshot( $user, $sprint ); $event_count = count( $events );
	$now = '2026-09-23 13:00:00';
	sprint_engine_progress_check( $state === $service->complete_step( $user, $sprint, $steps[$i] ) && $before === sprint_engine_progress_snapshot( $user, $sprint ) && $event_count === count( $events ), 'Duplicate completion preserves all rows, timestamps, pointer and events.' );
	sprint_engine_progress_check( $state === $service->complete_step( $user, $sprint, $steps[0] ), 'Stale previous-Step request returns current canonical state.' );
}
sprint_engine_progress_check( 'completed' === $state['status'] && null === $state['current_step_id'] && '2026-09-23 12:04:00' === $state['completed_at'], 'Final Step completes Sprint, clears current and sets completion time.' );
sprint_engine_progress_check( $state === $service->start_sprint( $user, $sprint ) && $state === $service->resume_sprint( $user, $sprint ) && $state === $service->get_progress( $user, $sprint ), 'Completed Sprint stays completed at 100%; start/resume never reset it.' );
$counts = array_count_values( array_column( $events, 0 ) );
sprint_engine_progress_check( array( 'sprint_started' => 1, 'step_started' => 5, 'step_completed' => 5, 'sprint_completed' => 1 ) === $counts, 'Every lifecycle action fires exactly once at the intended transition.' );
sprint_engine_progress_check( 5 === count( sprint_engine_progress_snapshot( $user, $sprint )[1] ), 'Exactly five progress rows remain after repeated requests.' );

// Normal resume updates meaningful activity, without resetting current/start/completion.
$second = $users[1];
$service->start_sprint( $second, $sprint );
$service->complete_step( $second, $sprint, $steps[0] );
$row = $enrolments->find( $second, $sprint ); $completed_before = $progress->find( $second, $sprint, $steps[0] );
$now = '2026-09-24 10:00:00'; $event_count = count( $events );
$state = $service->resume_sprint( $second, $sprint );
sprint_engine_progress_check( $steps[1] === $state['current_step_id'] && $row['started_at'] === $state['started_at'] && $now === $state['last_activity_at'], 'Normal resume preserves authoritative pointer and start, updates UTC activity.' );
sprint_engine_progress_check( $completed_before === $progress->find( $second, $sprint, $steps[0] ) && $event_count === count( $events ), 'Resume leaves completed history and Step-start events unchanged.' );

// Corruption must be rejected even when launchable metadata remains true.
foreach ( array( $foreign, $steps[0], 99999999 ) as $bad_link ) {
	update_post_meta( $steps[0], '_sprint_engine_next_step_id', $bad_link );
	$before = sprint_engine_progress_snapshot( $second, $sprint );
	sprint_engine_progress_error( $service->start_sprint( $users[2], $sprint ), 'sprint_engine_progress_structure', 'Corrupt foreign/self/missing chain cannot start.' );
	sprint_engine_progress_error( $service->complete_step( $second, $sprint, $steps[1] ), 'sprint_engine_progress_structure', 'Corrupt chain cannot advance.' );
	sprint_engine_progress_error( $service->resume_sprint( $second, $sprint ), 'sprint_engine_progress_structure', 'Corrupt chain is not guessed during resume.' );
	sprint_engine_progress_check( $before === sprint_engine_progress_snapshot( $second, $sprint ), 'Structure error preserves progress.' );
}
wp_set_current_user( 1 );
$manager->apply_linear_order( $sprint, $steps );

// Direct storage corruption must not be accepted through lossy integer casts.
foreach ( array(
	array( $sprint, '_sprint_engine_start_step_id', $steps[0] . 'invalid' ),
	array( $steps[0], '_sprint_engine_next_step_id', $steps[1] . 'invalid' ),
	array( $steps[0], '_sprint_engine_sprint_id', $sprint . 'invalid' ),
	array( $steps[0], '_sprint_engine_position', '1invalid' ),
	array( $steps[0], '_sprint_engine_mode', 'decision' ),
) as [ $id, $key, $value ] ) {
	$original = get_post_meta( $id, $key, true );
	$wpdb->update( $wpdb->postmeta, array( 'meta_value' => $value ), array( 'post_id' => $id, 'meta_key' => $key ) );
	clean_post_cache( $id );
	sprint_engine_progress_check( is_wp_error( $service->start_sprint( $users[2], $sprint ) ), 'Malformed stored metadata or Decision mode fails safely: ' . $key );
	update_post_meta( $id, $key, $original );
}

// Recovery needs a currently valid chain. Trashing/deleting alone leaves broken links.
wp_trash_post( $steps[1] );
sprint_engine_progress_error( $service->resume_sprint( $second, $sprint ), 'sprint_engine_progress_structure', 'Trashed current Step with unrepaired links fails safely.' );
$remaining = array_values( array_diff( $steps, array( $steps[1] ) ) );
$manager->apply_linear_order( $sprint, $remaining );
$state = $service->resume_sprint( $second, $sprint );
sprint_engine_progress_check( $steps[2] === $state['current_step_id'] && 1 === $state['completed_steps'] && 4 === $state['total_steps'] && 25.0 === $state['percentage'], 'Trashed pointer recovers to first incomplete Step after author repairs remaining chain.' );
sprint_engine_progress_check( $completed_before === $progress->find( $second, $sprint, $steps[0] ) && null !== $progress->find( $second, $sprint, $steps[1] ), 'Recovery retains completed and removed-Step history.' );
wp_delete_post( $steps[2], true );
$remaining = array_values( array_diff( $remaining, array( $steps[2] ) ) );
$manager->apply_linear_order( $sprint, $remaining );
sprint_engine_progress_check( $steps[3] === $service->resume_sprint( $second, $sprint )['current_step_id'], 'Deleted current Step recovers with a valid remaining chain.' );
$manager->save_step( $steps[3], array( '_sprint_engine_sprint_id' => $other ) );
$remaining = array_values( array_diff( $remaining, array( $steps[3] ) ) );
sprint_engine_progress_check( $steps[4] === $service->resume_sprint( $second, $sprint )['current_step_id'], 'Detached current Step recovers without deleting history.' );
sprint_engine_progress_error( $service->complete_step( $second, $sprint, $steps[3] ), 'sprint_engine_progress_step', 'Detached Step cannot complete in its former Sprint.' );
$service->complete_step( $second, $sprint, $steps[4] );
$row = $enrolments->find( $second, $sprint );
$enrolments->update( $row['id'], array( 'status' => 'in_progress', 'current_step_id' => 9999999, 'completed_at' => null ) );
$before = sprint_engine_progress_snapshot( $second, $sprint )[1]; $event_count = count( $events );
$now = '2026-09-25 11:00:00';
$state = $service->resume_sprint( $second, $sprint );
sprint_engine_progress_check( 'completed' === $state['status'] && null === $state['current_step_id'] && $now === $state['completed_at'] && 100.0 === $state['percentage'], 'All-complete stale enrolment repairs completion and clears pointer.' );
sprint_engine_progress_check( $before === sprint_engine_progress_snapshot( $second, $sprint )[1] && $event_count + 1 === count( $events ), 'Completion repair preserves every Step row and emits one transition.' );
$service->resume_sprint( $second, $sprint );
sprint_engine_progress_check( $event_count + 1 === count( $events ), 'Retrying repaired completion fires no duplicate event.' );
$enrolments->update( $row['id'], array( 'status' => 'in_progress', 'current_step_id' => $steps[4] ) );
$service->resume_sprint( $second, $sprint );
sprint_engine_progress_check( $event_count + 1 === count( $events ) && $now === $enrolments->find( $second, $sprint )['completed_at'], 'Repair with existing completion timestamp preserves it and does not repeat completion event.' );
// Completed history outside the current structure is excluded from counts, retained in storage.
sprint_engine_progress_check( 2 === $service->get_progress( $user, $sprint )['completed_steps'] && 5 === count( $progress->completed_ids( $user, $sprint ) ) && 100.0 === $service->get_progress( $user, $sprint )['percentage'], 'Completed state stays 100% while valid count excludes historical Steps.' );

// Separate persistence tests, against the existing tables and unique constraints.
$repo_user = $users[3];
$enrolments->create( array( 'user_id' => $repo_user, 'sprint_id' => $sprint, 'created_at' => $now, 'updated_at' => $now ) );
$repo_row = $enrolments->find( $repo_user, $sprint );
sprint_engine_progress_check( 'not_started' === $repo_row['status'] && null === $repo_row['current_step_id'], 'Repository round-trips canonical defaults and nullable fields.' );
$enrolments->update( $repo_row['id'], array( 'last_activity_at' => $now ) );
sprint_engine_progress_check( $now === $enrolments->find( $repo_user, $sprint )['last_activity_at'], 'Enrolment repository persists activity update.' );
$progress->create( $repo_user, $sprint, $remaining[0], $now );
sprint_engine_progress_check( true === $progress->complete( $repo_user, $sprint, $remaining[0], $now ) && false === $progress->complete( $repo_user, $sprint, $remaining[0], '2026-09-26 12:00:00' ), 'Repository conditional completion persists only one transition.' );
sprint_engine_progress_check( array( $remaining[0] ) === $progress->completed_ids( $repo_user, $sprint ) && $now === $progress->find( $repo_user, $sprint, $remaining[0] )['completed_at'], 'Repository completion query returns IDs with original time preserved.' );
$suppressed = $wpdb->suppress_errors( true );
foreach ( array(
	static function () use ( $enrolments, $repo_user, $sprint, $now ) { $enrolments->create( array( 'user_id' => $repo_user, 'sprint_id' => $sprint, 'created_at' => $now, 'updated_at' => $now ) ); },
	static function () use ( $progress, $repo_user, $sprint, $remaining, $now ) { $progress->create( $repo_user, $sprint, $remaining[0], $now ); },
) as $duplicate ) {
	$failed = false; try { $duplicate(); } catch ( RuntimeException $exception ) { $failed = true; }
	sprint_engine_progress_check( $failed, 'Repository respects database uniqueness.' );
}
$wpdb->suppress_errors( $suppressed );

// Rollback after enrolment insert, Step completion, next entry, and commit failures.
foreach ( array( 'start_step', 'advance', 'next_step', 'commit', 'begin', 'engine' ) as $failure ) {
	$failure_user = $users[4];
	if ( in_array( $failure, array( 'advance', 'next_step' ), true ) ) { $service->start_sprint( $failure_user, $sprint ); }
	$before = sprint_engine_progress_snapshot( $failure_user, $sprint ); $event_count = count( $events );
	$block = static function ( $query ) use ( $failure ) {
		if ( 'engine' === $failure && str_starts_with( $query, 'SHOW TABLE STATUS WHERE Name IN' ) ) { return "SELECT 'MyISAM' AS Engine"; }
		if ( ( 'commit' === $failure && 'COMMIT' === $query ) || ( 'begin' === $failure && 'START TRANSACTION' === $query ) ) { return ''; }
		if ( in_array( $failure, array( 'start_step', 'next_step' ), true ) && str_starts_with( $query, 'INSERT INTO' ) && str_contains( $query, 'sprint_engine_step_progress' ) ) { return ''; }
		if ( 'advance' === $failure && str_starts_with( $query, 'UPDATE' ) && str_contains( $query, 'sprint_engine_enrolments' ) ) { return ''; }
		return $query;
	};
	add_filter( 'query', $block );
	$result = in_array( $failure, array( 'advance', 'next_step' ), true ) ? $service->complete_step( $failure_user, $sprint, $remaining[0] ) : $service->start_sprint( $failure_user, $sprint );
	remove_filter( 'query', $block );
	sprint_engine_progress_error( $result, 'sprint_engine_progress_persistence', 'Persistence failure returned safely: ' . $failure );
	sprint_engine_progress_check( $before === sprint_engine_progress_snapshot( $failure_user, $sprint ) && $event_count === count( $events ), 'Failure rolls back all rows and emits no events: ' . $failure );
}

// Production clock uses UTC even with a non-UTC WordPress site timezone.
$old_timezone = get_option( 'timezone_string' ); $old_offset = get_option( 'gmt_offset' );
update_option( 'timezone_string', 'Pacific/Auckland' ); update_option( 'gmt_offset', 12 );
$before_time = gmdate( 'Y-m-d H:i:s' );
$state = ( new ProgressService() )->start_sprint( $users[5], $sprint );
sprint_engine_progress_check( $state['started_at'] >= $before_time && $state['started_at'] <= gmdate( 'Y-m-d H:i:s' ), 'Default clock stores UTC regardless of site timezone.' );
update_option( 'timezone_string', $old_timezone ); update_option( 'gmt_offset', $old_offset );

// Two independent workers contend for the same first start, then same completion.
function sprint_engine_progress_workers( $operation, $user, $sprint, $step ) {
	$command = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ) );
	foreach ( array( 'pdo_sqlite', 'sqlite3', 'mysqli' ) as $extension ) {
		if ( extension_loaded( $extension ) ) { $command[] = '-d'; $command[] = 'extension=' . $extension; }
	}
	$command = array_merge( $command, array( __FILE__, 'worker', $operation, (string) $user, (string) $sprint, (string) $step, (string) ( microtime( true ) + 1 ) ) );
	$workers = array();
	for ( $i = 0; $i < 2; ++$i ) {
		$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
		if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Cannot start contention worker.' ); }
		fclose( $pipes[0] ); $workers[] = array( $process, $pipes );
	}
	$results = array();
	foreach ( $workers as [ $process, $pipes ] ) {
		$output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] );
		fclose( $pipes[1] ); fclose( $pipes[2] );
		sprint_engine_progress_check( 0 === proc_close( $process ) && '' === $errors, 'Independent contention worker exits cleanly.' );
		$results[] = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
	}
	return $results;
}
$race_user = $users[6];
foreach ( array( 'start', 'complete' ) as $operation ) {
	$results = sprint_engine_progress_workers( $operation, $race_user, $sprint, $remaining[0] );
	sprint_engine_progress_check( is_array( $results[0]['result'] ) && is_array( $results[1]['result'] ) && $results[0]['result'] === $results[1]['result'], 'Concurrent ' . $operation . ' returns one canonical state to both callers.' );
	$race_events = array_count_values( array_column( array_merge( $results[0]['events'], $results[1]['events'] ), 0 ) );
	$expected = 'start' === $operation ? array( 'sprint_started' => 1, 'step_started' => 1 ) : array( 'step_completed' => 1, 'step_started' => 1 );
	sprint_engine_progress_check( $expected === $race_events, 'Concurrent ' . $operation . ' emits each transition only once across processes.' );
}
sprint_engine_progress_check( 1 === count( sprint_engine_progress_snapshot( $race_user, $sprint )[0] ) && array( $remaining[0] ) === $progress->completed_ids( $race_user, $sprint ) && $remaining[1] === $service->get_state( $race_user, $sprint )['current_step_id'], 'Contention leaves one enrolment, one completion and the correct next Step.' );

// All events are after COMMIT: hooks may safely call a read through a new transaction.
$hook_read = null;
$reader = static function ( $event_user, $event_sprint ) use ( &$hook_read, $service ) { $hook_read = $service->get_state( $event_user, $event_sprint ); };
add_action( 'sprint_engine/sprint_started', $reader, 20, 2 );
$service->start_sprint( $users[7], $sprint );
remove_action( 'sprint_engine/sprint_started', $reader, 20 );
sprint_engine_progress_check( is_array( $hook_read ) && 'in_progress' === $hook_read['status'], 'Post-commit listener can open a separate service transaction and read canonical state.' );

// A missing Step row may be safely started/completed in the same transaction.
$eighth = $users[7];
$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $eighth, 'sprint_id' => $sprint, 'step_id' => $remaining[0] ) );
sprint_engine_progress_check( $remaining[1] === $service->complete_step( $eighth, $sprint, $remaining[0] )['current_step_id'], 'Completing current with a missing Step row creates/completes it atomically.' );
// Final-completion failure must roll back terminal Step completion and all hooks.
$before = sprint_engine_progress_snapshot( $eighth, $sprint ); $event_count = count( $events );
$fail_finish = static function ( $query ) { return str_starts_with( $query, 'UPDATE' ) && str_contains( $query, 'sprint_engine_enrolments' ) ? '' : $query; };
add_filter( 'query', $fail_finish );
$result = $service->complete_step( $eighth, $sprint, $remaining[1] );
remove_filter( 'query', $fail_finish );
sprint_engine_progress_error( $result, 'sprint_engine_progress_persistence', 'Final enrolment write failure returns safe error.' );
sprint_engine_progress_check( $before === sprint_engine_progress_snapshot( $eighth, $sprint ) && $event_count === count( $events ), 'Failed finish rolls back terminal completion and publishes no events.' );

$read_failure = static function ( $query ) { return str_starts_with( $query, 'SELECT' ) && str_contains( $query, 'sprint_engine_enrolments' ) ? str_replace( 'sprint_engine_enrolments', 'sprint_engine_nonexistent_table', $query ) : $query; };
add_filter( 'query', $read_failure );
$result = $service->get_state( $eighth, $sprint );
remove_filter( 'query', $read_failure );
sprint_engine_progress_error( $result, 'sprint_engine_progress_persistence', 'Read failure is an error, never mistaken for not_started.' );
sprint_engine_progress_check( ! str_contains( $result->get_error_message(), $wpdb->prefix ) && ! str_contains( $result->get_error_message(), 'SELECT' ), 'Persistence errors expose no table names or SQL.' );

// Single-Step and existing not_started enrolment cases.
$single = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_title' => 'One Step Sprint' ) );
$only = $manager->quick_add( $single, 'Only Step' );
wp_update_post( array( 'ID' => $only, 'post_status' => 'publish' ) );
Meta::save( $single, array( '_sprint_engine_launchable' => true ) );
$ninth = $users[8];
$enrolments->create( array( 'user_id' => $ninth, 'sprint_id' => $single, 'created_at' => $now, 'updated_at' => $now ) );
$unstarted_row = $enrolments->find( $ninth, $single );
sprint_engine_progress_check( 'not_started' === $service->resume_sprint( $ninth, $single )['status'] && $unstarted_row === $enrolments->find( $ninth, $single ), 'Resume never starts an existing not_started enrolment.' );
$service->start_sprint( $ninth, $single );
sprint_engine_progress_check( $unstarted_row['id'] === $enrolments->find( $ninth, $single )['id'], 'Starting existing not_started enrolment reuses the canonical row.' );
sprint_engine_progress_check( 'completed' === $service->complete_step( $ninth, $single, $only )['status'], 'One-Step Sprint completes with its only Step.' );
$before = sprint_engine_progress_snapshot( $ninth, $single );
Meta::save( $single, array( '_sprint_engine_launchable' => false ) );
sprint_engine_progress_check( 'completed' === $service->start_sprint( $ninth, $single )['status'] && $before === sprint_engine_progress_snapshot( $ninth, $single ), 'Disabling launchable does not reset or block an existing completed enrolment.' );
$new_step = $manager->quick_add( $single, 'Later content' );
sprint_engine_progress_error( $service->complete_step( $ninth, $single, $new_step ), 'sprint_engine_progress_out_of_order', 'Completed enrolment cannot complete newly appended content without a reset feature.' );
$manager->save_step( $only, array( '_sprint_engine_sprint_id' => 0 ) );
$manager->save_step( $new_step, array( '_sprint_engine_sprint_id' => 0 ) );
sprint_engine_progress_error( $service->resume_sprint( $ninth, $single ), 'sprint_engine_progress_structure', 'Empty current structure returns clear error without guessing or deleting progress.' );
sprint_engine_progress_check( $before === sprint_engine_progress_snapshot( $ninth, $single ), 'Empty-structure error preserves original completed history.' );
foreach ( array( $single, $only, $new_step ) as $id ) { wp_delete_post( $id, true ); }

// Two real independent restart workers allocate exactly one new attempt.
$before_restart = sprint_engine_progress_snapshot( $user, $sprint );
$results = sprint_engine_progress_workers( 'restart', $user, $sprint, 0 );
sprint_engine_progress_check( is_array( $results[0]['result'] ) && $results[0]['result'] === $results[1]['result'] && 2 === $results[0]['result']['attempt_number'] && 0 === $results[0]['result']['completed_steps'], 'Concurrent restart returns one fresh Attempt 2 to both callers.' );
$race_events = array_count_values( array_column( array_merge( $results[0]['events'], $results[1]['events'] ), 0 ) );
sprint_engine_progress_check( array( 'sprint_started' => 1, 'step_started' => 1 ) === $race_events, 'Concurrent restart publishes one start and first-Step event across processes.' );
$after_restart = sprint_engine_progress_snapshot( $user, $sprint );
sprint_engine_progress_check( 2 === count( $after_restart[0] ) && $before_restart[0][0] === $after_restart[0][0], 'Concurrent restart retains previous completed enrolment.' );

foreach ( $users as $test_user ) {
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $test_user ) );
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $test_user ) );
	wp_delete_user( $test_user );
}
foreach ( array_merge( $steps, array( $foreign, $sprint, $other ) ) as $id ) { wp_delete_post( $id, true ); }
echo 'SE-003 integration checks completed: ' . $checks . " assertions.\n";
