<?php
/** SE-020: disposable WordPress/Core authoring, recovery, REST and transactions. */
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Progress\EnrolmentRepository;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Dashboard\Dashboard;

$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0; $events = array(); $posts = array(); $users = array();
$manager = new StructureManager();
$now = '2026-10-21 10:00:00';
$service = new ProgressService( static function () use ( &$now ) { return $now; } );
foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed' ) as $event ) {
	add_action( 'sprint_engine/' . $event, static function ( ...$args ) use ( &$events, $event ) {
		// A new transaction must be possible, and the committed identity visible.
		$state = ( new ProgressService() )->get_state( $args[0], $args[1] );
		if ( is_wp_error( $state ) || $state['attempt_id'] !== $args[count( $args ) - 2] || $state['attempt_number'] !== end( $args ) ) { throw new RuntimeException( 'Hook identity/commit boundary changed.' ); }
		$events[] = array_merge( array( $event ), $args );
	}, 10, 5 );
}

// Independent processes deliberately hold the actual Sprint lock, not a mock lock.
if ( isset( $argv[1] ) && 'worker' === $argv[1] ) {
	$input = json_decode( file_get_contents( $argv[2] ), true, 512, JSON_THROW_ON_ERROR );
	wp_set_current_user( 1 );
	// Both WordPress processes must finish bootstrapping before either holds a write lock.
	file_put_contents( $input['gate'] . '-booted', 'booted' );
	$deadline = microtime( true ) + 15;
	while ( ! file_exists( $input['gate'] . '-start' ) && microtime( true ) < $deadline ) { usleep( 1000 ); }
	if ( ! file_exists( $input['gate'] . '-start' ) ) { throw new RuntimeException( 'Worker start timed out.' ); }
	if ( isset( $input['attempting'] ) ) { file_put_contents( $input['attempting'], 'attempting operation' ); }
	$locked = false; $held = false;
	$hold = static function ( $sql ) use ( &$locked, &$held, $input ) {
		if ( $locked && ! $held && isset( $input['signal'] ) ) {
			$held = true; file_put_contents( $input['signal'], 'locked' );
			$deadline = microtime( true ) + 15;
			while ( ! file_exists( $input['release'] ) && microtime( true ) < $deadline ) { usleep( 1000 ); }
			if ( ! file_exists( $input['release'] ) ) { throw new RuntimeException( 'Lock release timed out.' ); }
		}
		if ( str_contains( $sql, 'SET ID = ID WHERE ID =' ) ) {
			$locked = true;
		}
		return $sql;
	};
	add_filter( 'query', $hold );
	if ( 'reorder' === $input['operation'] ) {
		$result = $manager->apply_linear_order( $input['sprint'], $input['order'] );
	} elseif ( 'resume' === $input['operation'] ) {
		$result = $service->resume_sprint( $input['user'], $input['sprint'] );
	} else {
		$result = $service->complete_step( $input['user'], $input['sprint'], $input['step'], $input['attempt'] );
	}
	remove_filter( 'query', $hold );
	if ( isset( $input['done'] ) ) { file_put_contents( $input['done'], 'done' ); }
	echo wp_json_encode( array( 'result' => is_wp_error( $result ) ? $result->get_error_code() : $result, 'events' => $events ) );
	exit;
}
function recovery_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks; echo "PASS: $message\n";
}
function recovery_rows( $user, $sprint ) {
	global $wpdb;
	$rows = array();
	foreach ( array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) as $table ) {
		$rows[] = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}$table WHERE user_id=%d AND sprint_id=%d ORDER BY id", $user, $sprint ), ARRAY_A );
	}
	return $rows;
}
function recovery_fixture( $count = 4 ) {
	global $manager, $service, $posts, $users;
	wp_set_current_user( 1 );
	$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'SE-020 recovery', 'post_name' => 'se020-' . wp_generate_password( 12, false ) ) );
	$posts[] = $sprint; $steps = array();
	for ( $i = 0; $i < $count; ++$i ) { $steps[] = recovery_add( $sprint ); }
	if ( true !== Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ) ) { throw new RuntimeException( 'Fixture launch failed.' ); }
	$user = wp_create_user( 'se020-' . wp_generate_password( 12, false ), wp_generate_password() ); $users[] = $user;
	wp_set_current_user( $user );
	$state = $service->start_sprint( $user, $sprint );
	if ( is_wp_error( $state ) ) { throw new RuntimeException( 'Fixture start failed.' ); }
	return array( $user, $sprint, $steps, $state['attempt_id'] );
}
function recovery_add( $sprint ) {
	global $manager, $posts;
	$member = get_current_user_id(); wp_set_current_user( 1 );
	$step = $manager->quick_add( $sprint, 'SE-020 Step ' . wp_generate_password( 8, false ) );
	if ( is_wp_error( $step ) ) { throw new RuntimeException( 'Quick Add failed.' ); }
	$posts[] = $step;
	wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>Instruction ' . $step . '</p><!-- /wp:paragraph -->' ) );
	wp_set_current_user( $member ); return $step;
}
function recovery_order( $sprint, $order ) {
	global $manager;
	$member = get_current_user_id(); wp_set_current_user( 1 );
	$result = $manager->apply_linear_order( $sprint, $order );
	wp_set_current_user( $member );
	if ( true !== $result ) { throw new RuntimeException( 'Authoring reorder failed.' ); }
}
function recovery_rest( $step, $attempt ) {
	$request = new WP_REST_Request( 'POST', '/sprint-engine/v1/steps/' . $step . '/complete' );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	$request->set_body_params( array( 'expected_attempt_id' => $attempt ) );
	$response = rest_get_server()->dispatch( $request );
	return apply_filters( 'rest_post_dispatch', $response, rest_get_server(), $request );
}
function recovery_runner( $sprint ) { return ( new Runner() )->resolve( get_post_field( 'post_name', $sprint ) ); }
function recovery_worker( $input ) {
	$path = tempnam( sys_get_temp_dir(), 'se020-input-' ); file_put_contents( $path, wp_json_encode( $input ) );
	$command = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ) );
	foreach ( array( 'pdo_sqlite', 'sqlite3', 'mysqli' ) as $extension ) {
		if ( extension_loaded( $extension ) ) { $command[] = '-d'; $command[] = 'extension=' . $extension; }
	}
	$process = proc_open( array_merge( $command, array( __FILE__, 'worker', $path ) ), array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Cannot launch worker.' ); }
	fclose( $pipes[0] ); return array( $process, $pipes, $path );
}
function recovery_join( $worker ) {
	[ $process, $pipes, $path ] = $worker;
	$output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] );
	fclose( $pipes[1] ); fclose( $pipes[2] ); unlink( $path );
	// Generic recovery anomaly diagnostics are expected on successful repairs.
	$errors = str_replace( 'Sprint Engine repaired a stale enrolment resume state.', '', $errors );
	$errors = preg_replace( '/\[[^\]]+\]/', '', $errors );
	recovery_check( 0 === proc_close( $process ) && '' === trim( $errors ), 'Independent lock contender exits without errors.' );
	return json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
}

// Insert before current/completed/start, and append. All history comparisons are exact rows.
foreach ( array( 'before-current', 'before-completed', 'beginning', 'append' ) as $case ) {
	[ $user, $sprint, $steps, $attempt ] = recovery_fixture();
	$service->complete_step( $user, $sprint, $steps[0], $attempt );
	if ( 'before-completed' === $case ) { $service->complete_step( $user, $sprint, $steps[1], $attempt ); }
	$before = recovery_rows( $user, $sprint ); $x = recovery_add( $sprint );
	$order = $steps; $index = 'append' === $case ? 4 : ( 'beginning' === $case ? 0 : 1 ); array_splice( $order, $index, 0, array( $x ) );
	recovery_order( $sprint, $order );
	$event_count = count( $events ); $read = $service->get_state( $user, $sprint ); $history = $service->get_attempt_history( $user, $sprint ); ( new Dashboard() )->resolve();
	recovery_check( $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ) && $before[0][0]['current_step_id'] == $history[0]['current_step_id'], 'State/history/Dashboard reads preserve stale pointer and all history: ' . $case );
	recovery_check( 5 === $read['total_steps'] && ( 'before-completed' === $case ? 40.0 : 20.0 ) === $read['percentage'], 'Insertion recalculates progress using current requirements: ' . $case );
	if ( 'append' !== $case ) {
		$stale = recovery_rest( (int) $before[0][0]['current_step_id'], $attempt );
		recovery_check( 409 === $stale->get_status() && str_contains( $stale->get_headers()['Cache-Control'], 'no-store' ) && str_contains( $stale->get_data()['message'], 'Reload' ) && $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Still-valid pointer/attempt cannot complete stale work; private reload conflict and zero writes/events: ' . $case );
		$retry = $service->complete_step( $user, $sprint, $steps[0], $attempt );
		recovery_check( ! is_wp_error( $retry ) && $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Persisted completion retry never advances into new work: ' . $case );
	}
	$now = '2026-10-21 11:00:00'; $entered_at = current_time( 'mysql', true ); $context = recovery_runner( $sprint ); $state = $context['progress'];
	$expected = 'append' === $case ? $steps[1] : $x;
	recovery_check( $expected === $state['current_step_id'] && $expected === $context['step']['id'] && 5 === $state['total_steps'] && array_search( $expected, $order, true ) + 1 === $context['step']['position'], 'Runner entry selects earliest incomplete Step and current position: ' . $case );
	$after = recovery_rows( $user, $sprint );
	recovery_check( $attempt === $state['attempt_id'] && 1 === $state['attempt_number'] && $before[1] === array_slice( $after[1], 0, count( $before[1] ) ) && $entered_at <= $state['last_activity_at'] && current_time( 'mysql', true ) >= $state['last_activity_at'], 'Recovery preserves identity and exact original started/completed rows/timestamps, updates UTC activity: ' . $case );
	$event_count = count( $events ); $service->resume_sprint( $user, $sprint );
	recovery_check( $event_count === count( $events ), 'Repeated recovery does not duplicate starts: ' . $case );
	while ( 'in_progress' === $state['status'] ) { $state = $service->complete_step( $user, $sprint, $state['current_step_id'], $attempt ); if ( is_wp_error( $state ) ) { throw new RuntimeException( 'Revised Sprint failed to finish.' ); } }
	recovery_check( 'completed' === $state['status'] && $attempt === $state['attempt_id'], 'Revised Sprint completes without restart: ' . $case );
}

// Noncontiguous completion history, previously visited Step re-entry, and finalisation
// at a Step which is no longer the terminal Step in the current curriculum.
foreach ( array( 'current-later', 'incomplete-earlier', 'completed-later', 'last-outstanding' ) as $case ) {
	[ $user, $sprint, $steps, $attempt ] = recovery_fixture();
	$service->complete_step( $user, $sprint, $steps[0], $attempt );
	if ( 'last-outstanding' === $case ) { $service->complete_step( $user, $sprint, $steps[1], $attempt ); $service->complete_step( $user, $sprint, $steps[2], $attempt ); }
	$before = recovery_rows( $user, $sprint );
	$order = 'completed-later' === $case ? array( $steps[1], $steps[0], $steps[2], $steps[3] ) : ( 'last-outstanding' === $case ? array( $steps[3], $steps[0], $steps[1], $steps[2] ) : array( $steps[0], $steps[2], $steps[3], $steps[1] ) );
	recovery_order( $sprint, $order );
	$state = $service->resume_sprint( $user, $sprint );
	recovery_check( $order['completed-later' === $case || 'last-outstanding' === $case ? 0 : 1] === $state['current_step_id'], 'Reorder selects earliest outstanding requirement: ' . $case );
	if ( in_array( $case, array( 'last-outstanding', 'completed-later' ), true ) ) {
		$context = recovery_runner( $sprint );
		set_query_var( 'sprint_engine_runner_context', $context );
		ob_start(); include dirname( __DIR__, 2 ) . '/templates/runner.php'; $html = ob_get_clean();
		recovery_check( false === $context['step']['is_terminal'], 'Reordered current Step retains its nonterminal structural meaning: ' . $case );
		$label = 'last-outstanding' === $case ? 'Complete Sprint' : 'Complete &amp; Continue';
		recovery_check( str_contains( $html, '>' . $label . '</button>' ), 'Runner action reflects outstanding requirements: ' . $case );
	}
	$completed_before = array_values( array_filter( $before[1], static function ( $row ) { return 'completed' === $row['status']; } ) );
	while ( 'in_progress' === $state['status'] ) {
		$state = $service->complete_step( $user, $sprint, $state['current_step_id'], $attempt );
		recovery_check( ! is_wp_error( $state ), 'Completion skips previously completed Steps in revised order: ' . $case );
	}
	$after = recovery_rows( $user, $sprint );
	foreach ( $completed_before as $row ) { recovery_check( in_array( $row, $after[1], true ), 'Reorder preserves authoritative completion row and timestamps.' ); }
	foreach ( $before[1] as $row ) { $match = array_values( array_filter( $after[1], static function ( $item ) use ( $row ) { return $item['id'] === $row['id']; } ) ); recovery_check( 1 === count( $match ) && $row['started_at'] === $match[0]['started_at'], 'Re-entry retains original Step-start row/time.' ); }
	recovery_check( $now === $state['completed_at'] && $attempt === $state['attempt_id'] && null === $state['current_step_id'], 'Last outstanding requirement completes Sprint once, including nonterminal Step: ' . $case );
}

foreach ( array( 'trash-current', 'remove-completed', 'move-current', 'detach-current', 'empty', 'malformed' ) as $case ) {
	[ $user, $sprint, $steps, $attempt ] = recovery_fixture( 'empty' === $case ? 1 : 4 );
	if ( 'empty' !== $case ) { $service->complete_step( $user, $sprint, $steps[0], $attempt ); }
	$before = recovery_rows( $user, $sprint ); $removed = 'remove-completed' === $case ? $steps[0] : ( $steps[1] ?? $steps[0] );
	wp_set_current_user( 1 );
	if ( in_array( $case, array( 'move-current', 'detach-current' ), true ) ) {
		$destination = 0;
		if ( 'move-current' === $case ) { [ , $destination ] = recovery_fixture( 1 ); wp_set_current_user( 1 ); }
		recovery_check( true === $manager->save_step( $removed, array( '_sprint_engine_sprint_id' => $destination ) ), 'Authoring moves/detaches current Step and repairs source.' );
	} elseif ( 'malformed' === $case ) { update_post_meta( $steps[0], '_sprint_engine_next_step_id', $steps[0] ); }
	else { recovery_check( true === $manager->trash_step( $sprint, $removed, StructureManager::revision( $sprint ) ), 'Authoring trashes Step and repairs remaining structure: ' . $case ); }
	wp_set_current_user( $user ); $event_count = count( $events );
	if ( in_array( $case, array( 'empty', 'malformed' ), true ) ) {
		recovery_check( is_wp_error( $service->resume_sprint( $user, $sprint ) ) && 'error' === recovery_runner( $sprint )['state'] && $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Empty/malformed chain fails safely with no guessed order or progress change: ' . $case );
		continue;
	}
	$response = recovery_rest( $removed, $attempt );
	recovery_check( ( 'move-current' === $case ? 409 : 404 ) === $response->get_status() && $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Old removed/detached Step submission cannot write: ' . $case );
	recovery_check( is_wp_error( $service->complete_step( $user, $sprint, $removed, $attempt ) ) && $before === recovery_rows( $user, $sprint ), 'Former Sprint rejects removed/foreign Step at service boundary.' );
	$state = $service->resume_sprint( $user, $sprint ); $after = recovery_rows( $user, $sprint );
	recovery_check( ( 'remove-completed' === $case ? $steps[1] : $steps[2] ) === $state['current_step_id'] && 3 === $state['total_steps'] && ( 'remove-completed' === $case ? 0.0 : 33.33 ) === $state['percentage'], 'Removal selects current requirement and excludes removed completions from percentage: ' . $case );
	recovery_check( $before[1] === array_slice( $after[1], 0, count( $before[1] ) ), 'Removed/moved progress remains stored exactly: ' . $case );
}

// Completed and restarted historical attempts are immutable under later structural edits.
[ $user, $sprint, $steps, $attempt ] = recovery_fixture( 2 );
foreach ( $steps as $step ) { $service->complete_step( $user, $sprint, $step, $attempt ); }
$completed = recovery_rows( $user, $sprint ); recovery_add( $sprint );
$event_count = count( $events ); $state = $service->resume_sprint( $user, $sprint );
recovery_check( 'completed' === $state['status'] && 100.0 === $state['percentage'] && $completed === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Completed attempt stays immutable after new work is added.' );
$next = $service->restart_sprint( $user, $sprint, $attempt );
$historical = recovery_rows( $user, $sprint ); $next2 = $service->restart_sprint( $user, $sprint, $next['attempt_id'] );
$before = recovery_rows( $user, $sprint ); $x = recovery_add( $sprint ); $order = wp_list_pluck( Meta::steps( $sprint ), 'ID' ); $order = array_merge( array( $x ), array_values( array_diff( $order, array( $x ) ) ) ); recovery_order( $sprint, $order );
$service->resume_sprint( $user, $sprint ); $after = recovery_rows( $user, $sprint );
recovery_check( array_slice( $before[0], 0, 2 ) === array_slice( $after[0], 0, 2 ) && $before[1] === array_slice( $after[1], 0, count( $before[1] ) ), 'Completed/restarted attempt identities, pointers, timestamps and Step history are immutable during recovery.' );
recovery_check( is_wp_error( $service->complete_step( $user, $sprint, $x, $next['attempt_id'] ) ) && is_wp_error( $service->restart_sprint( $user, $sprint, $attempt ) ), 'SE-019 rejects superseded attempt writes after structural recovery.' );

// All-complete repair is resume-only and preserves existing completion times.
[ $user, $sprint, $steps, $attempt ] = recovery_fixture( 2 );
$service->complete_step( $user, $sprint, $steps[0], $attempt );
wp_set_current_user( 1 ); $manager->trash_step( $sprint, $steps[1], StructureManager::revision( $sprint ) ); wp_set_current_user( $user );
$before = recovery_rows( $user, $sprint ); $event_count = count( $events );
recovery_check( 'in_progress' === $service->get_state( $user, $sprint )['status'] && $before === recovery_rows( $user, $sprint ), 'All-complete read does not complete the attempt.' );
$state = $service->resume_sprint( $user, $sprint );
recovery_check( 'completed' === $state['status'] && $now === $state['completed_at'] && $before[1] === recovery_rows( $user, $sprint )[1] && $event_count + 1 === count( $events ) && 'sprint_completed' === end( $events )[0], 'All-complete resume repairs once with one committed completion hook and no Step changes.' );
( new EnrolmentRepository() )->update( $attempt, array( 'status' => 'in_progress', 'current_step_id' => $steps[1] ) );
$service->resume_sprint( $user, $sprint ); $service->resume_sprint( $user, $sprint );
recovery_check( $now === $service->get_state( $user, $sprint )['completed_at'] && $event_count + 1 === count( $events ), 'Repair retains an existing completion timestamp and never repeats its event.' );

// Fail actual database statements at every new transition boundary.
foreach ( array( 'resume-pointer', 'resume-insert', 'resume-commit', 'complete-row', 'complete-pointer', 'complete-insert', 'complete-finish', 'complete-commit' ) as $failure ) {
	[ $user, $sprint, $steps, $attempt ] = recovery_fixture( 'complete-finish' === $failure ? 1 : 3 );
	$target = $steps[0];
	if ( str_starts_with( $failure, 'resume' ) ) { $target = recovery_add( $sprint ); recovery_order( $sprint, array_merge( array( $target ), $steps ) ); }
	$before = recovery_rows( $user, $sprint ); $event_count = count( $events );
	$block = static function ( $sql ) use ( $failure ) {
		$is_update = str_starts_with( $sql, 'UPDATE' );
		if ( ( str_ends_with( $failure, 'commit' ) && 'COMMIT' === $sql ) || ( str_ends_with( $failure, 'insert' ) && str_starts_with( $sql, 'INSERT INTO' ) && str_contains( $sql, 'sprint_engine_step_progress' ) ) || ( in_array( $failure, array( 'resume-pointer', 'complete-pointer', 'complete-finish' ), true ) && $is_update && str_contains( $sql, 'sprint_engine_enrolments' ) ) || ( 'complete-row' === $failure && $is_update && str_contains( $sql, 'sprint_engine_step_progress' ) ) ) { return ''; }
		return $sql;
	};
	add_filter( 'query', $block );
	$result = str_starts_with( $failure, 'resume' ) ? $service->resume_sprint( $user, $sprint ) : $service->complete_step( $user, $sprint, $target, $attempt );
	remove_filter( 'query', $block );
	recovery_check( is_wp_error( $result ) && 'sprint_engine_progress_persistence' === $result->get_error_code() && $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Failed transition rolls back exact attempt/Step rows and emits no events: ' . $failure );
}

// Actual member guards, including changes after prechecks but before the lock.
foreach ( array( 'draft', 'password', 'launchable', 'step', 'malformed', 'access' ) as $change ) {
	[ $user, $sprint, $steps, $attempt ] = recovery_fixture();
	$before = recovery_rows( $user, $sprint ); $event_count = count( $events ); $denied = false;
	$access = static function ( $allowed ) use ( &$denied ) { return $allowed && ! $denied; };
	$race = static function ( $sql ) use ( $change, $sprint, $steps, &$denied ) {
		if ( str_contains( $sql, 'SET ID = ID WHERE ID =' ) ) {
			if ( 'access' === $change ) { $denied = true; }
			if ( 'draft' === $change ) { wp_update_post( array( 'ID' => $sprint, 'post_status' => 'draft' ) ); }
			if ( 'password' === $change ) { wp_update_post( array( 'ID' => $sprint, 'post_password' => 'secret' ) ); }
			if ( 'launchable' === $change ) { update_post_meta( $sprint, '_sprint_engine_launchable', false ); }
			if ( 'step' === $change ) { wp_update_post( array( 'ID' => $steps[1], 'post_status' => 'draft' ) ); }
			if ( 'malformed' === $change ) { update_post_meta( $steps[0], '_sprint_engine_next_step_id', $steps[0] ); }
		}
		return $sql;
	};
	foreach ( array( 'rest', 'runner' ) as $adapter ) {
		add_filter( 'query', $race ); add_filter( 'sprint_engine/user_can_access_sprint', $access );
		$result = 'rest' === $adapter ? recovery_rest( $steps[0], $attempt ) : recovery_runner( $sprint );
		remove_filter( 'query', $race ); remove_filter( 'sprint_engine/user_can_access_sprint', $access );
		recovery_check( ( 'rest' === $adapter ? in_array( $result->get_status(), array( 403, 404 ), true ) : 'error' === $result['state'] ) && $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Locked adapter guard blocks changed availability/access with no progress/events: ' . $adapter . '/' . $change );
		$denied = false; wp_set_current_user( 1 ); wp_update_post( array( 'ID' => $sprint, 'post_status' => 'publish', 'post_password' => '' ) ); wp_update_post( array( 'ID' => $steps[1], 'post_status' => 'publish' ) ); update_post_meta( $sprint, '_sprint_engine_launchable', true ); recovery_order( $sprint, $steps ); wp_set_current_user( $user );
	}
}
[ $user, $sprint, $steps, $attempt ] = recovery_fixture(); $before = recovery_rows( $user, $sprint ); $event_count = count( $events );
wp_set_current_user( 0 ); recovery_check( 401 === recovery_rest( $steps[0], $attempt )->get_status() && 'login' === recovery_runner( $sprint )['state'], 'Anonymous REST/Runner cannot access instructional progress.' );
wp_set_current_user( 1 ); recovery_check( 409 === recovery_rest( $steps[0], $attempt )->get_status() && $before === recovery_rows( $user, $sprint ) && $event_count === count( $events ), 'Foreign member cannot mutate supplied owner attempt.' );
wp_set_current_user( $user );

// Ordered independent-process contention proves both serial outcomes on the shared lock.
foreach ( array( array( 'resume', 'resume' ), array( 'resume', 'complete' ), array( 'complete', 'resume' ), array( 'reorder', 'complete' ), array( 'complete', 'reorder' ) ) as [ $first, $second ] ) {
	[ $user, $sprint, $steps, $attempt ] = recovery_fixture();
	$service->complete_step( $user, $sprint, $steps[0], $attempt );
	if ( 'reorder' !== $first && 'reorder' !== $second ) { recovery_order( $sprint, array( $steps[0], $steps[2], $steps[1], $steps[3] ) ); }
	$signal = tempnam( sys_get_temp_dir(), 'se020-lock-' ); unlink( $signal ); $release = $signal . '-release';
	$input = array( 'user' => $user, 'sprint' => $sprint, 'attempt' => $attempt, 'step' => $steps[1], 'order' => array( $steps[0], $steps[2], $steps[1], $steps[3] ) );
	$one = recovery_worker( array_merge( $input, array( 'operation' => $first, 'gate' => $signal . '-one', 'signal' => $signal, 'release' => $release ) ) );
	$two = recovery_worker( array_merge( $input, array( 'operation' => $second, 'gate' => $signal . '-two', 'attempting' => $signal . '-attempting', 'done' => $signal . '-done' ) ) );
	$deadline = microtime( true ) + 15;
	while ( ( ! file_exists( $signal . '-one-booted' ) || ! file_exists( $signal . '-two-booted' ) ) && microtime( true ) < $deadline ) { usleep( 1000 ); }
	if ( ! file_exists( $signal . '-one-booted' ) || ! file_exists( $signal . '-two-booted' ) ) { throw new RuntimeException( 'Workers did not finish WordPress bootstrap.' ); }
	file_put_contents( $signal . '-one-start', 'start' );
	$deadline = microtime( true ) + 15;
	while ( ! file_exists( $signal ) && microtime( true ) < $deadline ) { usleep( 1000 ); }
	if ( ! file_exists( $signal ) ) { throw new RuntimeException( 'Worker did not acquire Sprint lock.' ); }
	file_put_contents( $signal . '-two-start', 'start' );
	$deadline = microtime( true ) + 15;
	while ( ! file_exists( $signal . '-attempting' ) && microtime( true ) < $deadline ) { usleep( 1000 ); }
	if ( ! file_exists( $signal . '-attempting' ) ) { throw new RuntimeException( 'Second worker did not attempt Sprint lock.' ); }
	usleep( 100000 );
	recovery_check( ! file_exists( $signal . '-done' ), 'Second process cannot finish while first holds the shared Sprint lock.' );
	file_put_contents( $release, 'release' );
	$results = array( recovery_join( $one ), recovery_join( $two ) ); unlink( $signal ); unlink( $release );
	unlink( $signal . '-attempting' ); unlink( $signal . '-done' );
	foreach ( array( '-one-booted', '-one-start', '-two-booted', '-two-start' ) as $suffix ) { unlink( $signal . $suffix ); }
	clean_post_cache( $sprint ); foreach ( $steps as $step ) { clean_post_cache( $step ); } wp_cache_set_posts_last_changed();
	$state = $service->get_state( $user, $sprint ); $combined = array_merge( $results[0]['events'], $results[1]['events'] );
	if ( 'complete' === $first && 'reorder' === $second ) {
		recovery_check( is_array( $results[0]['result'] ) && true === $results[1]['result'] && 2 === $state['completed_steps'], 'Completion winning lock persists before subsequent author reorder.' );
	} elseif ( 'resume' === $first && 'resume' === $second ) {
		recovery_check( is_array( $results[0]['result'] ) && is_array( $results[1]['result'] ) && 1 === count( $combined ) && 'step_started' === $combined[0][0], 'Two resumes create one recovered Step row/start event under contention.' );
	} else {
		recovery_check( in_array( 'sprint_engine_progress_out_of_order', array_column( $results, 'result' ), true ) && 1 === $state['completed_steps'] && ! in_array( 'step_completed', array_column( $combined, 0 ), true ), 'Earlier requirement prevents stale completion across independent processes: ' . $first . '/' . $second );
	}
	$state = $service->resume_sprint( $user, $sprint );
	recovery_check( $steps[2] === $state['current_step_id'] && $attempt === $state['attempt_id'], 'Contended operations converge on earliest requirement in same attempt.' );
}

$event_keys = array_map( 'serialize', $events );
recovery_check( count( $event_keys ) === count( array_unique( $event_keys ) ), 'Every Step/Sprint lifecycle event retains its documented identity and occurs at most once.' );
wp_set_current_user( 1 );
foreach ( $users as $user ) { $wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $user ) ); $wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $user ) ); wp_delete_user( $user ); }
foreach ( array_reverse( $posts ) as $post ) { wp_delete_post( $post, true ); }
echo "SE-020 completed: $checks assertions.\n";
