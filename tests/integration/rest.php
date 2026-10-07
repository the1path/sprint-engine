<?php
/** SE-005 disposable REST and HTTP journey checks. @package SprintEngine */
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Runner\Routes;

$wordpress = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $wordpress || getenv( 'SE_TEST_DISPOSABLE' ) !== 'yes' ) {
	exit( 1 );
}
ob_start();
$_SERVER['HTTP_HOST']      = '127.0.0.1:8094';
$_SERVER['REQUEST_METHOD'] = 'GET';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
error_reporting( E_ALL );
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);
$checks = 0;
function check_write( $ok, $label ) {
	global $checks;
	if ( ! $ok ) {
		throw new RuntimeException( $label );
	}
	++$checks;
	echo "PASS: $label\n";
}
function request_write( $resource, $id, $operation, $body = array(), $nonce = true, $method = 'POST' ) {
	$r = new WP_REST_Request( $method, '/sprint-engine/v1/' . $resource . '/' . $id . '/' . $operation );
	$r->set_body_params( $body );
	if ( $nonce !== false ) {
		$r->set_header( 'X-WP-Nonce', $nonce === true ? wp_create_nonce( 'wp_rest' ) : $nonce );
	}
	return rest_get_server()->dispatch( $r );
}
function rows_write( $user ) {
	global $wpdb;
	return array( $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments WHERE user_id=%d", $user ), ARRAY_A ), $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress WHERE user_id=%d ORDER BY id", $user ), ARRAY_A ) );
}
// Disposable tests only: verify per-request action assets from authorized contexts.
function action_assets_write( $slug, $expected ) {
	global $wp;
	$wp->matched_rule = Routes::RULE;
	$wp->request      = 'sprint/' . $slug . '/';
	wp_dequeue_script( 'sprint-engine-runner' );
	wp_deregister_script( 'sprint-engine-runner' );
	$runner = new Runner();
	$runner->prepare();
	$runner->enqueue();
	check_write( wp_script_is( 'sprint-engine-runner', 'enqueued' ) === $expected, 'Only writable authorized context enqueues actions' );
	if ( $expected ) {
		check_write( str_contains( wp_scripts()->get_data( 'sprint-engine-runner', 'data' ), wp_create_nonce( 'wp_rest' ) ), 'Action config uses the current REST nonce' );
	}
}

wp_set_current_user( 1 );
$manager = new StructureManager();
$sprint  = wp_insert_post(
	array(
		'post_type'   => 'sprint_engine_sprint',
		'post_status' => 'publish',
		'post_title'  => 'REST fixture',
		'post_name'   => 'rest-' . wp_generate_password( 8, false ),
	)
);
$steps   = array();
for ( $i = 1;$i <= 5;++$i ) {
	$steps[] = $manager->quick_add( $sprint, 'PRIVATE STEP ' . $i );
}
foreach ( $steps as $published_step ) { wp_update_post( array( 'ID' => $published_step, 'post_status' => 'publish' ) ); }
update_post_meta( $sprint, '_sprint_engine_launchable', true );
$other   = wp_insert_post(
	array(
		'post_type'   => 'sprint_engine_sprint',
		'post_status' => 'publish',
		'post_title'  => 'Other',
	)
);
$foreign = $manager->quick_add( $other, 'PRIVATE FOREIGN' );
wp_update_post( array( 'ID' => $foreign, 'post_status' => 'publish' ) );
Meta::save( $other, array( '_sprint_engine_launchable' => true ) );
$user   = wp_insert_user(
	array(
		'user_login' => 'rest-' . wp_generate_password( 10, false ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$second = wp_insert_user(
	array(
		'user_login' => 'rest-' . wp_generate_password( 10, false ),
		'user_pass'  => wp_generate_password(),
		'role'       => 'subscriber',
	)
);
$slug   = get_post_field( 'post_name', $sprint );
$url    = Routes::url( $slug );
$runner = new Runner();
$routes = rest_get_server()->get_routes();
foreach ( $routes as $path => $handlers ) {
	if ( str_contains( $path, '(?P<id>' ) && str_starts_with( $path, '/sprint-engine/v1/' ) ) {
		check_write( array_keys( $handlers[0]['methods'] ) === array( 'POST' ), 'Mutation is POST only: ' . $path );
	}
}
foreach ( array( array( 'sprints', $sprint, 'start' ), array( 'steps', $steps[0], 'complete' ), array( 'sprints', $sprint, 'restart' ) ) as $args ) {
	wp_set_current_user( 0 );
	check_write( request_write( ...$args )->get_status() === 401, 'Anonymous write rejected' );
	wp_set_current_user( $user );
	check_write( request_write( ...array_merge( $args, array( array(), false ) ) )->get_status() === 403, 'Missing nonce rejected' );
	check_write( request_write( ...array_merge( $args, array( array(), 'invalid' ) ) )->get_status() === 403, 'Invalid nonce rejected' );
	check_write( request_write( ...array_merge( $args, array( array(), true, 'GET' ) ) )->get_status() === 404, 'GET cannot mutate' );
	foreach ( array( '0', '-1', '1.5', 'abc', '999999999999999999999999', '%2F', '%5C', '1%00', '1e2', '+1' ) as $bad ) {
		check_write( request_write( $args[0], $bad, $args[2] )->get_status() === 400, 'Malformed identifier rejected' );
	}
}
check_write( $runner->resolve( $slug )['state'] === 'not_started' && rows_write( $user ) === array( array(), array() ), 'Start view performs no write' );
action_assets_write( $slug, true );
$deny = static function () {
	return false;
};
add_filter( 'sprint_engine/user_can_access_sprint', $deny );
action_assets_write( $slug, false );
check_write( request_write( 'sprints', $sprint, 'start' )->get_status() === 403, 'Access denial blocks Start' );
check_write( request_write( 'steps', $steps[0], 'complete' )->get_status() === 403, 'Access denial blocks Complete' );
remove_filter( 'sprint_engine/user_can_access_sprint', $deny );
foreach ( array( array( 'post_status' => 'draft' ), array( 'post_password' => 'secret' ) ) as $change ) {
	wp_update_post( array_merge( array( 'ID' => $sprint ), $change ) );
	check_write( request_write( 'sprints', $sprint, 'start' )->get_status() === 404, 'Unavailable Sprint rejected' );
	check_write( request_write( 'steps', $steps[0], 'complete' )->get_status() === 404, 'Completion rejects unavailable parent' );
	wp_update_post(
		array(
			'ID'            => $sprint,
			'post_status'   => 'publish',
			'post_password' => '',
		)
	);
}
update_post_meta( $sprint, '_sprint_engine_launchable', false );
check_write( request_write( 'sprints', $sprint, 'start' )->get_status() === 404, 'Non-launchable rejected' );
update_post_meta( $sprint, '_sprint_engine_launchable', true );
update_post_meta( $steps[0], '_sprint_engine_next_step_id', $foreign );
check_write( request_write( 'sprints', $sprint, 'start' )->get_status() === 404, 'Invalid structure rejected' );

wp_set_current_user( 1 );
$manager->apply_linear_order( $sprint, $steps );
wp_set_current_user( $user );
$r    = request_write(
	'sprints',
	$sprint,
	'start',
	array(
		'user_id' => $second,
		'id'      => $other,
	)
);
$data = $r->get_data();
check_write( $r->get_status() === 200 && $data['current_step_id'] === $steps[0] && $data['runner_url'] === $url, 'Start returns canonical Step and URL; body id ignored' );
$before = rows_write( $user );
check_write( request_write( 'sprints', $sprint, 'start' )->get_data() === $data && rows_write( $user ) === $before, 'Repeated Start preserves rows and timestamps' );
foreach ( array( 'draft', 'pending', 'private', 'future' ) as $status ) {
    wp_update_post( array( 'ID' => $steps[1], 'post_status' => $status, 'edit_date' => true, 'post_date' => '2036-01-01 00:00:00', 'post_date_gmt' => '2036-01-01 00:00:00' ) );
    check_write( $status === get_post_status( $steps[1] ), 'REST fixture has actual Step publication state: ' . $status );
    foreach ( array( array( 'sprints', $sprint, 'start' ), array( 'sprints', $sprint, 'restart' ), array( 'steps', $steps[0], 'complete' ) ) as $args ) {
        $blocked = request_write( ...$args );
        check_write( 404 === $blocked->get_status() && ! str_contains( wp_json_encode( $blocked->get_data() ), 'PRIVATE' ) && $before === rows_write( $user ), 'REST ' . $args[2] . ' rejects an unpublished associated Step and retains exact attempt/progress: ' . $status );
    }
}
wp_update_post( array( 'ID' => $steps[1], 'post_status' => 'publish', 'edit_date' => true, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00' ) );
check_write( request_write( 'sprints', $sprint, 'start' )->get_data() === $data && $before === rows_write( $user ), 'Republishing restores REST access to the original attempt without resetting rows.' );
check_write( rows_write( $second ) === array( array(), array() ), 'Request identity cannot select another member' );
check_write( request_write( 'steps', $foreign, 'complete', array( 'sprint_id' => $sprint ) )->get_status() === 409, 'Foreign Step cannot complete progress in supplied Sprint' );
check_write( request_write( 'steps', $steps[2], 'complete' )->get_status() === 409, 'Future Step rejected' );
$events = 0;
$hook   = static function () use ( &$events ) {
	++$events;
};
add_action( 'sprint_engine/step_completed', $hook );
foreach ( $steps as $index => $step ) {
	$r    = request_write(
		'steps',
		$step,
		'complete',
		array(
			'sprint_id' => $other,
			'user_id'   => $second,
		)
	);
	$data = $r->get_data();
	check_write( $r->get_status() === 200 && $data['sprint_id'] === $sprint && $data['progress']['percentage'] === (float) ( ( $index + 1 ) * 20 ), 'Completion derives parent and canonical percentage' );
	$before = rows_write( $user );
	check_write( request_write( 'steps', $step, 'complete' )->get_data() === $data && rows_write( $user ) === $before, 'Completion retry preserves rows/timestamps/state' );
	check_write( $events === $index + 1, 'Completion event fires once' );
	check_write( ! str_contains( wp_json_encode( $data ), 'PRIVATE' ) && ! isset( $data['meta'] ), 'Response contains no private content/meta' );
	$context = $runner->resolve( $slug );
	check_write( $index === 4 ? $context['state'] === 'completed' : $context['step']['id'] === $steps[ $index + 1 ], 'Refresh/resume resolves canonical state' );
}
check_write( $data['current_step_id'] === null && $data['sprint_completed'] === true, 'Final completion clears current Step' );
$before = rows_write( $user );
check_write( request_write( 'sprints', $sprint, 'start' )->get_data()['state'] === 'completed' && rows_write( $user ) === $before, 'Completed Sprint cannot restart' );
$failure = static function ( $query ) {
	return str_starts_with( $query, 'SHOW TABLE STATUS WHERE Name IN' ) && str_contains( $query, 'sprint_engine_enrolments' ) ? '' : $query;
};
add_filter( 'query', $failure );
$r = request_write( 'sprints', $sprint, 'start' );
remove_filter( 'query', $failure );
check_write( $r->get_status() === 503 && ! str_contains( wp_json_encode( $r->get_data() ), 'SELECT' ), 'Persistence failure maps to safe 503' );

action_assets_write( $slug, true );

// Real cookie/nonce HTTP requests use configuration issued to that exact session.
if ( getenv( 'SE_TEST_HTTP_BASE' ) ) {
	$base = rtrim( getenv( 'SE_TEST_HTTP_BASE' ), '/' );
	if ( ! in_array( wp_parse_url( $base, PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
		throw new RuntimeException( 'Loopback only' );
	}
	$cookie = LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $second, time() + 3600, 'logged_in' );
	$http   = static function ( $target, $method = 'GET', $nonce = null ) use ( $cookie ) {
		$headers = array( 'Cookie' => $cookie );
		if ( $nonce !== null ) {
			$headers['X-WP-Nonce'] = $nonce;
		}
		$r = wp_remote_request(
			$target,
			array(
				'method'  => $method,
				'headers' => $headers,
				'timeout' => 30,
			)
		);
		if ( is_wp_error( $r ) ) {
			throw new RuntimeException( $r->get_error_message() );
		} return $r;
	};
	$page   = $http( $url );
	$html   = wp_remote_retrieve_body( $page );
	check_write( str_contains( $html, 'Start Sprint' ) && str_contains( $html, 'runner.js' ), 'HTTP start offers action and scoped JS' );
	check_write( str_contains( wp_remote_retrieve_header( $page, 'cache-control' ), 'no-store' ), 'Runner retains private no-store' );
	for ( $i = 0;$i <= 5;++$i ) {
		preg_match( '/var sprintEngineRunner = (\{[^\n]+\});/', $html, $matches );
		$config = json_decode( $matches[1] ?? '', true );
		check_write( is_array( $config ) && $config['runnerUrl'] === $url, 'HTTP page supplies session nonce and canonical URL' );
		if ( $i === 0 ) {
			// Alternate two real cookie sessions at the identical canonical URL.
			$first_cookie = LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $user, time() + 3600, 'logged_in' );
			for ( $round = 0; $round < 3; ++$round ) {
				$first_page = wp_remote_get( $url, array( 'headers' => array( 'Cookie' => $first_cookie ) ) );
				$first_html = wp_remote_retrieve_body( $first_page );
				check_write( str_contains( $first_html, 'Sprint complete' ) && str_contains( $first_html, 'Restart Sprint' ), 'Member A retains completed state with restart action' );
				$second_html = wp_remote_retrieve_body( $http( $url ) );
				check_write( str_contains( $second_html, 'Start Sprint' ) && str_contains( $second_html, $config['nonce'] ), 'Member B retains independent Start and session nonce' );
			}
			$bad_nonce = $http( $config['endpoint'], 'POST', 'invalid' );
			check_write( str_contains( wp_remote_retrieve_header( $bad_nonce, 'cache-control' ), 'no-store' ), 'Core invalid-nonce response is no-store' );
			$missing_nonce = $http( $config['endpoint'], 'POST' );
			check_write( str_contains( wp_remote_retrieve_header( $missing_nonce, 'cache-control' ), 'no-store' ), 'Anonymous/missing-nonce response is no-store' );
			$malformed = $http( rest_url( 'sprint-engine/v1/sprints/0/start' ), 'POST', $config['nonce'] );
			check_write( wp_remote_retrieve_response_code( $malformed ) === 400 && str_contains( wp_remote_retrieve_header( $malformed, 'cache-control' ), 'no-store' ), 'Malformed write response is no-store' );
			check_write( wp_remote_retrieve_response_code( $http( $config['endpoint'], 'POST' ) ) === 401, 'HTTP cookie without nonce rejected' );
			check_write( wp_remote_retrieve_response_code( $http( $config['endpoint'], 'POST', 'invalid' ) ) === 403, 'HTTP invalid nonce rejected' );}
		$result = $http( $config['endpoint'], 'POST', $config['nonce'] );
		$body   = json_decode( wp_remote_retrieve_body( $result ), true );
		check_write( wp_remote_retrieve_response_code( $result ) === 200 && $body['runner_url'] === $url, 'HTTP action succeeds at canonical URL' );
		$retry = $http( $config['endpoint'], 'POST', $config['nonce'] );
		check_write( json_decode( wp_remote_retrieve_body( $retry ), true ) === $body, 'HTTP double submission is idempotent' );
		$html = wp_remote_retrieve_body( $http( $url ) );
		check_write( $i === 5 ? str_contains( $html, 'Sprint complete' ) : str_contains( $html, 'PRIVATE STEP ' . ( $i + 1 ) ), 'HTTP refresh shows next canonical state' );
	}
	check_write( str_contains( $html, 'runner.js' ) && str_contains( $html, '/restart' ) && str_contains( $html, 'Restart Sprint' ), 'Completed page has restart action/config/script' );
	$public = wp_remote_retrieve_body( $http( $base . '/' ) );
	check_write( ! str_contains( $public, 'sprintEngineRunner' ) && ! str_contains( $public, 'runner.js' ), 'Unrelated public page has no member configuration' );
}
// Restart uses the same authenticated boundaries and never accepts client attempt identity.
wp_set_current_user( $user );
$old_rows = rows_write( $user );
$other_rows = rows_write( $second );
wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'draft' ) );
$blocked_restart = request_write( 'sprints', $sprint, 'restart' );
check_write( 404 === $blocked_restart->get_status() && ! str_contains( wp_json_encode( $blocked_restart->get_data() ), 'PRIVATE' ) && $old_rows === rows_write( $user ), 'Completed attempt cannot Restart with a Draft Step; exact completed history is retained.' );
wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'publish' ) );
$deny = static function () { return false; };
add_filter( 'sprint_engine/user_can_access_sprint', $deny );
check_write( 403 === request_write( 'sprints', $sprint, 'restart' )->get_status(), 'Access denial blocks Restart' );
remove_filter( 'sprint_engine/user_can_access_sprint', $deny );
update_post_meta( $sprint, '_sprint_engine_launchable', false );
check_write( 404 === request_write( 'sprints', $sprint, 'restart' )->get_status(), 'Unavailable Restart rejected' );
update_post_meta( $sprint, '_sprint_engine_launchable', true );
$r = request_write( 'sprints', $sprint, 'restart', array( 'id' => $other, 'user_id' => $second, 'attempt_number' => 99, 'attempt_id' => 99 ) );
$data = $r->get_data();
check_write( 200 === $r->get_status() && 2 === $data['attempt_number'] && is_int( $data['attempt_id'] ) && 'in_progress' === $data['state'] && 0 === $data['progress']['completed_steps'] && $steps[0] === $data['current_step_id'] && $url === $data['runner_url'], 'Restart returns server allocated Attempt 2 with zero progress' );
$after = rows_write( $user );
check_write( $old_rows[0][0] === $after[0][0] && $old_rows[1] === array_slice( $after[1], 0, count( $old_rows[1] ) ), 'REST restart preserves all prior progress exactly' );
check_write( rows_write( $second ) === $other_rows, 'REST restart cannot change another user' );
check_write( $data === request_write( 'sprints', $sprint, 'restart' )->get_data() && $after === rows_write( $user ), 'REST restart retry returns same attempt and no extra rows' );
check_write( 2 === request_write( 'sprints', $sprint, 'start' )->get_data()['attempt_number'], 'Start consistently returns latest attempt metadata' );
check_write( 2 === request_write( 'steps', $steps[0], 'complete' )->get_data()['attempt_number'], 'Complete consistently returns latest attempt metadata' );
wp_set_current_user( $second );
check_write( rows_write( $second ) === $other_rows, 'Cross-user attempt state remains independent' );
check_write( 409 === request_write( 'sprints', $other, 'restart' )->get_status(), 'Restart before first completion returns safe conflict' );
if ( getenv( 'SE_TEST_HTTP_BASE' ) ) {
 $html = wp_remote_retrieve_body( $http( $url ) );
 preg_match( '/var sprintEngineRunner = (\{[^\n]+\});/', $html, $matches );
 $config = json_decode( $matches[1] ?? '', true );
 $endpoint = rest_url( 'sprint-engine/v1/sprints/' . $sprint . '/restart' );
 foreach ( array( null => 401, 'invalid' => 403 ) as $nonce => $status ) {
  $result = $http( $endpoint, 'POST', '' === $nonce ? null : $nonce );
  check_write( $status === wp_remote_retrieve_response_code( $result ) && str_contains( wp_remote_retrieve_header( $result, 'cache-control' ), 'no-store' ), 'Restart HTTP auth error has no-store' );
 }
 $result = $http( $endpoint, 'POST', $config['nonce'] );
 $body = json_decode( wp_remote_retrieve_body( $result ), true );
 check_write( 200 === wp_remote_retrieve_response_code( $result ) && 2 === $body['attempt_number'] && 0 === $body['progress']['completed_steps'] && str_contains( wp_remote_retrieve_header( $result, 'cache-control' ), 'no-store' ), 'Real cookie/nonce restart succeeds with private no-store' );
 check_write( $body === json_decode( wp_remote_retrieve_body( $http( $endpoint, 'POST', $config['nonce'] ) ), true ), 'Real HTTP restart retry remains idempotent' );
}
wp_set_current_user( 1 );
foreach ( array( $user, $second ) as $id ) {
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $id ) );
	wp_delete_user( $id );}
foreach ( array_merge( $steps, array( $foreign, $other, $sprint ) ) as $id ) {
	wp_delete_post( $id, true );}
echo "SE-005 completed: $checks assertions.\n";
ob_end_flush();
