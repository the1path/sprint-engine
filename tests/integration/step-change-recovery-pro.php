<?php
/** Optional SE-020 acceptance with an unchanged disposable merged Pro installation. */
$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) || 'yes' !== getenv( 'SE_TEST_PRO' ) ) { exit( 1 ); }
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEnginePro\Workspace\Repository;
use ThePath\SprintEnginePro\History\Model;
wp_set_current_user( 1 );
if ( is_wp_error( activate_plugin( 'sprint-engine-pro/sprint-engine-pro.php' ) ) ) { throw new RuntimeException( 'Disposable Pro activation failed.' ); }
// Initialize Pro after activation and register through the actual REST lifecycle.
( new ThePath\SprintEnginePro\Plugin() )->boot();
( new ThePath\SprintEnginePro\Workspace\Module() )->initialize();
rest_get_server();
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0;
function recovery_pro_check( $ok, $message ) { global $checks; if ( ! $ok ) { throw new RuntimeException( $message ); } ++$checks; echo "PASS: $message\n"; }
function recovery_pro_save( $step, $attempt, $value ) {
	$request = new WP_REST_Request( 'POST', '/sprint-engine-pro/v1/workspace/steps/' . $step . '/fields/12345678-1234-4234-8234-123456789abc/response' );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) ); $request->set_header( 'Content-Type', 'application/json' );
	$request->set_body( wp_json_encode( array( 'expected_attempt_id' => (string) $attempt, 'value' => $value ) ) );
	return rest_do_request( $request );
}
function recovery_pro_snapshot() {
	global $wpdb;
	return array_map( static function ( $table ) use ( $wpdb ) { return $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A ); }, array( $wpdb->prefix . 'sprint_engine_enrolments', $wpdb->prefix . 'sprint_engine_step_progress', ThePath\SprintEnginePro\Database\Installer::table() ) );
}
$posts = array(); $user = 0; $manager = new StructureManager(); $service = new ProgressService(); $repository = new Repository();
try {
	$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'SE-020 Workspace recovery' ) ); $posts[] = $sprint;
	$steps = array();
	foreach ( array( 'A', 'B', 'X' ) as $label ) {
		$step = $manager->quick_add( $sprint, 'Workspace ' . $label ); $posts[] = $step; $steps[] = $step;
		wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'post_content' => '<!-- wp:sprint-engine-pro/workspace-response {"fieldId":"12345678-1234-4234-8234-123456789abc","prompt":"Preserve my work","responseType":"long_text"} /-->' ) );
	}
	// X initially belongs elsewhere; moving it is the real authoring insertion.
	$other = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Source Sprint' ) ); $posts[] = $other;
	$manager->save_step( $steps[2], array( '_sprint_engine_sprint_id' => $other ) ); Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
	$user = wp_create_user( 'se020-pro-' . wp_generate_password( 12, false ), wp_generate_password() ); wp_set_current_user( $user );
	$state = $service->start_sprint( $user, $sprint ); $attempt = $state['attempt_id'];
	$service->complete_step( $user, $sprint, $steps[0], $attempt );
	recovery_pro_check( 200 === recovery_pro_save( $steps[1], $attempt, 'B original saved response' )->get_status(), 'Workspace binds initial saved B response to the Core attempt.' );
	$saved = $repository->list_attempt( $user, $sprint, 1 ); $history = $service->get_attempt_history( $user, $sprint );
	wp_set_current_user( 1 ); $manager->save_step( $steps[2], array( '_sprint_engine_sprint_id' => $sprint ) ); $manager->apply_linear_order( $sprint, array( $steps[0], $steps[2], $steps[1] ) ); wp_set_current_user( $user );
	$before = recovery_pro_snapshot(); $service->get_state( $user, $sprint );
	recovery_pro_check( $history === $service->get_attempt_history( $user, $sprint ) && $before === recovery_pro_snapshot(), 'Core state/history remain read-only across structural drift with Pro active.' );
	$state = $service->resume_sprint( $user, $sprint );
	recovery_pro_check( $steps[2] === $state['current_step_id'] && $attempt === $state['attempt_id'] && 1 === $state['attempt_number'] && $saved === $repository->list_attempt( $user, $sprint, 1 ), 'Recovery preserves Workspace attempt identity and exact saved response bytes.' );
	$before = recovery_pro_snapshot();
	recovery_pro_check( 403 === recovery_pro_save( $steps[1], $attempt, 'Stale B overwrite' )->get_status() && $before === recovery_pro_snapshot(), 'Workspace rejects noncurrent B after recovery without changing Core or Pro rows.' );
	recovery_pro_check( 200 === recovery_pro_save( $steps[2], $attempt, 'New X response' )->get_status(), 'Recovered X saves on the same existing attempt.' );
	$service->complete_step( $user, $sprint, $steps[2], $attempt );
	$answers = $repository->list_attempt( $user, $sprint, 1 );
	recovery_pro_check( in_array( $saved[0], $answers, true ) && 2 === count( $answers ) && $steps[1] === $service->get_state( $user, $sprint )['current_step_id'], 'Returning to visited B retains original saved response and newly saved X.' );
	$service->complete_step( $user, $sprint, $steps[1], $attempt );
	$completed = $service->get_attempt_history( $user, $sprint )[0]; $replacement = $service->restart_sprint( $user, $sprint, $attempt );
	$before = recovery_pro_snapshot();
	recovery_pro_check( 409 === recovery_pro_save( $steps[0], $attempt, 'Stale attempt' )->get_status() && $before === recovery_pro_snapshot(), 'Workspace expected-attempt protection still rejects superseded identity.' );
	$context = ( new Model() )->resolve( array( 'view' => 'workbook', 'sprint_id' => (string) $sprint, 'attempt_number' => '1' ) );
	recovery_pro_check( 'ready' === $context['state'] && 'completed' === $context['run']['status'] && $completed === $service->get_attempt_history( $user, $sprint )[1] && $answers === $repository->list_attempt( $user, $sprint, 1 ) && $before === recovery_pro_snapshot(), 'Historical Workbook reads retain completed identity and exact response/progress history without writes.' );
	$third = $service->restart_sprint( $user, $sprint, $replacement['attempt_id'] );
	$before = recovery_pro_snapshot();
	$context = ( new Model() )->resolve( array( 'view' => 'workbook', 'sprint_id' => (string) $sprint, 'attempt_number' => '2' ) );
	recovery_pro_check( 'ready' === $context['state'] && 'restarted' === $context['run']['status'] && $before === recovery_pro_snapshot(), 'Restarted historical Workbook stays read-only after recovery and further restarts.' );
} finally {
	wp_set_current_user( 1 );
	foreach ( array_reverse( $posts ) as $post ) { wp_delete_post( $post, true ); }
	if ( $user ) { foreach ( array( ThePath\SprintEnginePro\Database\Installer::table(), $wpdb->prefix . 'sprint_engine_step_progress', $wpdb->prefix . 'sprint_engine_enrolments' ) as $table ) { $wpdb->delete( $table, array( 'user_id' => $user ) ); } wp_delete_user( $user ); }
	if ( isset( $sprint ) ) { delete_option( 'sprint_engine_pro_sprint_title_' . $sprint ); }
}
echo "SE-020 Pro compatibility completed: $checks assertions.\n";
