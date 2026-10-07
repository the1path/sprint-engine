<?php
/**
 * SE-002 checks using real WordPress APIs on a disposable installation.
 * Run after foundation.php with the same SE_TEST_* environment variables.
 *
 * @package SprintEngine
 */

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\Authoring;

$wordpress = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $wordpress || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
	fwrite( STDERR, "Set SE_TEST_WP_ROOT and SE_TEST_DISPOSABLE=yes for a disposable test site.\n" );
	exit( 1 );
}
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );

function sprint_engine_authoring_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}

function sprint_engine_authoring_request( $method, $route, $params = array() ) {
	$request = new WP_REST_Request( $method, '/wp/v2/' . $route );
	if ( 'GET' === $method ) {
		$request->set_query_params( $params );
	} else {
		$request->set_body_params( $params );
	}
	return rest_get_server()->dispatch( $request );
}

wp_set_current_user( 1 );
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_title' => 'Authoring Sprint', 'post_status' => 'draft' ) );
$other  = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_title' => 'Other Sprint', 'post_status' => 'draft' ) );
$steps  = array();
$blocks = '<!-- wp:paragraph --><p>Native block content.</p><!-- /wp:paragraph -->';
for ( $position = 1; $position <= 5; ++$position ) {
	$response = sprint_engine_authoring_request( 'POST', 'sprint_engine_step', array( 'title' => 'Step ' . $position, 'content' => $blocks, 'status' => 'draft', 'meta' => array( '_sprint_engine_sprint_id' => $sprint, '_sprint_engine_position' => $position, '_sprint_engine_stage_label' => '<b>Stage</b>', '_sprint_engine_estimated_minutes' => 5, '_sprint_engine_mode' => 5 === $position ? 'task' : 'content' ) ) );
	sprint_engine_authoring_check( 201 === $response->get_status(), 'Native REST creates Step ' . $position . ' with metadata.' );
	$steps[] = $response->get_data()['id'];
}
foreach ( $steps as $index => $step ) {
	$next = $steps[ $index + 1 ] ?? null;
	$response = sprint_engine_authoring_request( 'POST', 'sprint_engine_step/' . $step, array( 'meta' => array( '_sprint_engine_next_step_id' => $next ) ) );
	sprint_engine_authoring_check( 200 === $response->get_status(), 'Linear next-Step link saves: ' . $step );
}
$before_draft = get_post( $sprint )->to_array();
$before_meta = Meta::read( $sprint, 'sprint_engine_sprint' );
$response = sprint_engine_authoring_request( 'POST', 'sprint_engine_sprint/' . $sprint, array( 'title' => 'Rejected title', 'content' => 'Rejected content', 'meta' => array( '_sprint_engine_start_step_id' => $steps[0], '_sprint_engine_launchable' => true ) ) );
sprint_engine_authoring_check( 400 === $response->get_status() && str_contains( $response->get_data()['message'], 'Publish every Sprint Step' ) && $before_draft === get_post( $sprint )->to_array() && $before_meta === Meta::read( $sprint, 'sprint_engine_sprint' ), 'Native REST rejects Launchable Draft Steps before any content or metadata writes.' );
ob_start(); ( new Authoring() )->setup( get_post( $sprint ) ); $html = ob_get_clean();
sprint_engine_authoring_check( str_contains( $html, 'data-check="step_publication" data-complete="false"' ), 'Checklist separates Draft Step publication from structural order.' );
foreach ( $steps as $step ) {
    $response = sprint_engine_authoring_request( 'POST', 'sprint_engine_step/' . $step, array( 'status' => 'publish' ) );
    sprint_engine_authoring_check( 200 === $response->get_status(), 'Native REST publishes Step without changing its structure.' );
}
$response = sprint_engine_authoring_request( 'POST', 'sprint_engine_sprint/' . $sprint, array( 'content' => $blocks, 'excerpt' => 'Native outcome', 'meta' => array( '_sprint_engine_estimated_minutes' => 25, '_sprint_engine_start_step_id' => $steps[0], '_sprint_engine_launchable' => true ) ) );
sprint_engine_authoring_check( 200 === $response->get_status(), 'Sprint start and launchable save in one native REST request.' );
sprint_engine_authoring_check( array_merge( Meta::defaults( 'sprint_engine_sprint' ), array( '_sprint_engine_estimated_minutes' => 25, '_sprint_engine_start_step_id' => $steps[0], '_sprint_engine_launchable' => true ) ) === Meta::read( $sprint, 'sprint_engine_sprint' ), 'Sprint metadata round trips.' );
sprint_engine_authoring_check( array( '_sprint_engine_sprint_id' => $sprint, '_sprint_engine_position' => 5, '_sprint_engine_stage_label' => 'Stage', '_sprint_engine_estimated_minutes' => 5, '_sprint_engine_mode' => 'task', '_sprint_engine_next_step_id' => 0 ) === Meta::read( $steps[4], 'sprint_engine_step' ), 'All Step fields round trip, including sanitised label and terminal reference.' );
sprint_engine_authoring_check( ! metadata_exists( 'post', $steps[4], '_sprint_engine_next_step_id' ), 'Terminal null is stored as an absent optional link.' );
sprint_engine_authoring_check( $steps === wp_list_pluck( Meta::steps( $sprint ), 'ID' ), 'Five associated Steps are displayed in position order.' );
sprint_engine_authoring_check( array() === Authoring::warnings( $sprint ), 'A complete five-Step chain has no structure warnings.' );
$response = sprint_engine_authoring_request( 'GET', 'sprint_engine_sprint/' . $sprint, array( 'context' => 'edit' ) );
sprint_engine_authoring_check( 25 === $response->get_data()['meta']['_sprint_engine_estimated_minutes'] && true === $response->get_data()['meta']['_sprint_engine_launchable'], 'Native REST exposes typed metadata in edit context.' );
foreach ( array( $sprint, $steps[0] ) as $id ) {
	sprint_engine_authoring_check( use_block_editor_for_post( $id ), 'Native block editor remains enabled for ' . get_post_type( $id ) );
	sprint_engine_authoring_check( $blocks === get_post( $id )->post_content && has_blocks( $id ), 'Native Gutenberg content survives REST authoring.' );
}
$foreign = wp_insert_post( array( 'post_type' => 'sprint_engine_step', 'post_title' => 'Foreign Step', 'post_status' => 'draft' ) );
sprint_engine_authoring_check( true === Meta::save( $foreign, array( '_sprint_engine_sprint_id' => $other ) ), 'Service assigns a Step to another Sprint.' );
$invalid_edits = array(
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_next_step_id' => $foreign ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_next_step_id' => $steps[0] ) ),
	array( 'sprint_engine_sprint/' . $sprint, array( '_sprint_engine_start_step_id' => $foreign ) ),
	array( 'sprint_engine_sprint/' . $other, array( '_sprint_engine_launchable' => true ) ),
	array( 'sprint_engine_sprint/' . $sprint, array( '_sprint_engine_start_step_id' => null ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_sprint_id' => $other, '_sprint_engine_next_step_id' => $foreign ) ),
	array( 'sprint_engine_step/' . $steps[1], array( '_sprint_engine_sprint_id' => $other, '_sprint_engine_next_step_id' => $foreign ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_mode' => 'decision' ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_mode' => array( 'task' ) ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_sprint_id' => $steps[1] ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_sprint_id' => 9999999 ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_position' => -1 ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_position' => 1.5 ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_position' => 'no' ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_position' => array( 1 ) ) ),
	array( 'sprint_engine_step/' . $steps[0], array( '_sprint_engine_estimated_minutes' => -2 ) ),
	array( 'sprint_engine_sprint/' . $sprint, array( '_sprint_engine_start_step_id' => -1 ) ),
);
foreach ( $invalid_edits as $index => list( $route, $meta ) ) {
	$id = (int) basename( $route );
	$before = Meta::read( $id, get_post_type( $id ) );
	$response = sprint_engine_authoring_request( 'POST', $route, array( 'title' => 'Must not save', 'meta' => $meta ) );
	sprint_engine_authoring_check( 400 === $response->get_status(), 'REST rejects invalid structural edit ' . $index );
	sprint_engine_authoring_check( $before === Meta::read( $id, get_post_type( $id ) ) && 'Must not save' !== get_the_title( $id ), 'Rejected REST edit leaves metadata and post unchanged: ' . $index );
}
foreach ( array( -1, '1abc', 1.5, true, array(), '999999999999999999999999999999' ) as $bad_integer ) {
	sprint_engine_authoring_check( is_wp_error( Meta::save( $steps[0], array( '_sprint_engine_position' => $bad_integer ) ) ), 'Service rejects malformed integer: ' . wp_json_encode( $bad_integer ) );
}
sprint_engine_authoring_check( is_wp_error( Meta::save( $sprint, array( '_sprint_engine_launchable' => 'yes' ) ) ), 'Service rejects ambiguous boolean.' );
sprint_engine_authoring_check( true === Meta::save( $foreign, array( '_sprint_engine_estimated_minutes' => '', '_sprint_engine_position' => '0', '_sprint_engine_stage_label' => 'Review <b>now</b>' ) ), 'Blank minutes and zero position normalise safely.' );
sprint_engine_authoring_check( ! metadata_exists( 'post', $foreign, '_sprint_engine_estimated_minutes' ) && 'Review now' === get_post_meta( $foreign, '_sprint_engine_stage_label', true ), 'Optional minutes absent and text sanitised.' );

// Test the native metabox boundary, including nonce and capability failures.
$authoring = new Authoring();
$_POST = array( 'sprint_engine_meta' => array( '_sprint_engine_estimated_minutes' => '7' ) );
$authoring->save( $foreign );
sprint_engine_authoring_check( 0 === Meta::read( $foreign, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Missing nonce cannot mutate metadata.' );
$_POST['sprint_engine_authoring_nonce'] = 'invalid';
$authoring->save( $foreign );
sprint_engine_authoring_check( 0 === Meta::read( $foreign, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Invalid nonce cannot mutate metadata.' );
$_POST['sprint_engine_authoring_nonce'] = wp_create_nonce( 'sprint_engine_authoring_' . $foreign );
wp_update_post( array( 'ID' => $foreign, 'post_title' => 'Metabox hook test' ) );
sprint_engine_authoring_check( 7 === Meta::read( $foreign, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Nonce-verified metabox save persists.' );
$_POST['sprint_engine_meta'] = array( '_sprint_engine_estimated_minutes' => '8', '_sprint_engine_mode' => 'decision' );
$authoring->save( $foreign );
sprint_engine_authoring_check( 7 === Meta::read( $foreign, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'] && get_transient( 'sprint_engine_authoring_error_1_' . $foreign ), 'Invalid metabox submission preserves all fields and records an admin notice.' );
$_POST = array();
$revision = wp_insert_post( array( 'post_type' => 'revision', 'post_parent' => $foreign, 'post_status' => 'inherit', 'post_title' => 'Revision test' ) );
$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $revision ), 'sprint_engine_meta' => array( '_sprint_engine_estimated_minutes' => 99 ) );
$authoring->save( $revision );
sprint_engine_authoring_check( 7 === Meta::read( $foreign, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Revision save cannot alter parent structural metadata.' );
$_POST = array();

// A cycle is advisory; no branching or general graph validation is introduced.
sprint_engine_authoring_check( true === Meta::save( $steps[4], array( '_sprint_engine_next_step_id' => $steps[0] ) ) && count( Authoring::warnings( $sprint ) ) > 0, 'A multi-Step loop produces a bounded warning.' );
Meta::save( $steps[4], array( '_sprint_engine_next_step_id' => null ) );
Meta::save( $steps[0], array( '_sprint_engine_next_step_id' => null ) );
sprint_engine_authoring_check( count( Authoring::warnings( $sprint ) ) > 0, 'Disconnected Steps produce an orphan warning.' );
Meta::save( $steps[0], array( '_sprint_engine_next_step_id' => $steps[1] ) );

ob_start();
$authoring->render( get_post( $steps[0] ) );
$html = ob_get_clean();
sprint_engine_authoring_check( ! str_contains( $html, 'name="sprint_engine_meta[_sprint_engine_next_step_id]"' ) && ! str_contains( $html, 'name="sprint_engine_meta[_sprint_engine_position]"' ) && str_contains( $html, 'Next Step:' ) && str_contains( $html, get_the_title( $steps[1] ) ), 'Position and next Step are read-only in the Step editor.' );
ob_start();
$authoring->render( get_post( $sprint ) );
$html = ob_get_clean();
sprint_engine_authoring_check( str_contains( $html, 'se-add-step' ) && str_contains( $html, 'post=' . $steps[4] ) && ! str_contains( $html, 'name="sprint_engine_meta[_sprint_engine_start_step_id]"' ), 'Sprint metabox offers Quick Add, associated edit links and read-only start.' );

// Core REST authentication and protected meta permissions remain authoritative.
// SE-009: native editor save/read boundaries and advisory setup state.
$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $sprint ), 'sprint_engine_meta' => array( '_sprint_engine_launchable' => '1', '_sprint_engine_completion_message' => "Well done.\n\n<b>Next</b> & more", '_sprint_engine_completion_cta_label' => '<b>Feedback</b>', '_sprint_engine_completion_cta_url' => 'https://example.org/feedback?a=1&b=2' ) );
$authoring->save( $sprint ); $_POST = array();
$completion = Meta::read( $sprint, 'sprint_engine_sprint' );
sprint_engine_authoring_check( "Well done.\n\nNext & more" === $completion['_sprint_engine_completion_message'] && 'Feedback' === $completion['_sprint_engine_completion_cta_label'] && 'https://example.org/feedback?a=1&b=2' === $completion['_sprint_engine_completion_cta_url'], 'Completion fields round trip through nonce-protected metabox save with plain multiline sanitization.' );
$response = sprint_engine_authoring_request( 'POST', 'sprint_engine_sprint/' . $sprint, array( 'meta' => array( '_sprint_engine_completion_message' => "REST\nmessage", '_sprint_engine_completion_cta_url' => 'javascript:alert(1)' ) ) );
sprint_engine_authoring_check( 400 === $response->get_status() && $completion === Meta::read( $sprint, 'sprint_engine_sprint' ), 'Native metadata REST rejects unsafe CTA and preserves all previous custom fields.' );
foreach ( array( 'javascript:alert(1)', 'data:text/html,bad', 'https://', 'https://bad host/', 'not a URL', array() ) as $bad_url ) {
 sprint_engine_authoring_check( '' === Meta::completion_url( $bad_url ), 'Malformed completion destination omitted.' );
}
ob_start(); $authoring->setup( get_post( $sprint ) ); $html = ob_get_clean();
sprint_engine_authoring_check( str_contains( $html, 'before they click Start Sprint' ) && str_contains( $html, 'usually do not need a separate Welcome Step' ), 'Sprint setup explains introduction and first work Step.' );
foreach ( array( 'introduction', 'steps', 'structure', 'step_publication', 'launchable' ) as $check ) {
 sprint_engine_authoring_check( str_contains( $html, 'data-check="' . $check . '" data-complete="true"' ), 'Saved setup checks ' . $check );
}
sprint_engine_authoring_check( str_contains( $html, 'data-check="published" data-complete="false"' ), 'Draft checklist distinguishes publication from launchability.' );
ob_start(); $authoring->setup( get_post( $other ) ); $empty = ob_get_clean();
sprint_engine_authoring_check( str_contains( $empty, 'data-check="introduction" data-complete="false"' ) && str_contains( $empty, 'data-check="structure" data-complete="false"' ), 'Empty introduction and invalid structure remain to do.' );
$empty_sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_content' => '<!-- wp:paragraph --><p>&nbsp;</p><!-- /wp:paragraph -->' ) );
ob_start(); $authoring->setup( get_post( $empty_sprint ) ); $empty = ob_get_clean();
sprint_engine_authoring_check( str_contains( $empty, 'data-check="steps" data-complete="false"' ) && str_contains( $empty, 'data-check="launchable" data-complete="false"' ) && str_contains( $empty, 'data-check="introduction" data-complete="false"' ), 'Empty Sprint checklist does not count empty markup as introduction or invent Steps.' );
wp_update_post( array( 'ID' => $empty_sprint, 'post_excerpt' => 'An excerpt introduction' ) );
ob_start(); $authoring->setup( get_post( $empty_sprint ) ); $empty = ob_get_clean();
sprint_engine_authoring_check( str_contains( $empty, 'data-check="introduction" data-complete="true"' ), 'Saved excerpt counts as an introduction.' );
wp_delete_post( $empty_sprint, true );
wp_update_post( array( 'ID' => $sprint, 'post_status' => 'publish' ) );
ob_start(); $authoring->setup( get_post( $sprint ) ); $html = ob_get_clean();
sprint_engine_authoring_check( str_contains( $html, 'data-check="published" data-complete="true"' ) && str_contains( $html, 'View Runner' ), 'Published valid Sprint checklist uses existing View Runner availability.' );
update_post_meta( $steps[0], '_sprint_engine_next_step_id', 0 );
ob_start(); $authoring->setup( get_post( $sprint ) ); $html = ob_get_clean();
sprint_engine_authoring_check( str_contains( $html, 'data-check="structure" data-complete="false"' ) && ! str_contains( $html, 'View Runner' ), 'Checklist uses existing linear validity when the stored chain breaks.' );
update_post_meta( $steps[0], '_sprint_engine_next_step_id', $steps[1] );
Meta::save( $foreign, array( '_sprint_engine_stage_label' => 'My custom phase' ) );
ob_start(); $authoring->render( get_post( $foreign ) ); $html = ob_get_clean();
sprint_engine_authoring_check( str_contains( $html, 'value="My custom phase"' ) && str_contains( $html, 'list="se-stage-suggestions"' ) && str_contains( $html, 'The phase of the Sprint' ) && str_contains( $html, 'value="Analyse"' ), 'Stage remains free text with help and suggestions.' );
sprint_engine_authoring_check( str_contains( $html, 'Back to Sprint' ) && str_contains( $html, esc_url( get_edit_post_link( $other ) ) ), 'Step links directly back to its assigned Sprint editor.' );
delete_post_meta( $foreign, '_sprint_engine_sprint_id' );
ob_start(); $authoring->render( get_post( $foreign ) ); $html = ob_get_clean();
sprint_engine_authoring_check( ! str_contains( $html, 'Back to Sprint' ), 'Unassigned Step has no broken parent navigation.' );
update_post_meta( $foreign, '_sprint_engine_sprint_id', $other );

$subscriber = wp_insert_user( array( 'user_login' => 'authoring-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
foreach ( array( 0, $subscriber ) as $user ) {
	wp_set_current_user( $user );
	foreach ( array( 'sprint_engine_sprint/' . $sprint, 'sprint_engine_step/' . $steps[0] ) as $route ) {
		$response = sprint_engine_authoring_request( 'POST', $route, array( 'meta' => array( '_sprint_engine_estimated_minutes' => 100 ) ) );
		sprint_engine_authoring_check( in_array( $response->get_status(), array( 401, 403 ), true ), 'Unauthorized REST write rejected: ' . $route );
	}
	sprint_engine_authoring_check( ! current_user_can( 'edit_post_meta', $steps[0], '_sprint_engine_mode' ) && is_wp_error( Meta::save( $steps[0], array( '_sprint_engine_mode' => 'task' ) ) ), 'Protected metadata and service deny unauthorized writes.' );
	$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $foreign ), 'sprint_engine_meta' => array( '_sprint_engine_estimated_minutes' => 99 ) );
	$authoring->save( $foreign );
	sprint_engine_authoring_check( 7 === Meta::read( $foreign, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Valid nonce without administrator capability cannot save.' );
}
$_POST = array();
wp_set_current_user( 1 );
$response = sprint_engine_authoring_request( 'POST', 'sprint_engine_sprint/' . $sprint, array( 'meta' => array( '_sprint_engine_start_step_id' => null, '_sprint_engine_launchable' => false ) ) );
sprint_engine_authoring_check( 200 === $response->get_status() && 0 === Meta::read( $sprint, 'sprint_engine_sprint' )['_sprint_engine_start_step_id'], 'Start and launchable can be cleared together.' );
Meta::save( $sprint, array( '_sprint_engine_start_step_id' => $steps[0], '_sprint_engine_launchable' => true ) );
wp_trash_post( $steps[0] );
sprint_engine_authoring_check( is_wp_error( Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ) ) && count( Authoring::warnings( $sprint ) ) > 0, 'Trashed start Step is invalid and surfaces a warning.' );
$response = sprint_engine_authoring_request( 'POST', 'sprint_engine_sprint/' . $sprint, array( 'title' => 'Content remains editable' ) );
sprint_engine_authoring_check( 200 === $response->get_status(), 'Unrelated content save allows subsequent metabox repair of a deleted link.' );
sprint_engine_authoring_check( true === Meta::save( $sprint, array( '_sprint_engine_start_step_id' => null, '_sprint_engine_launchable' => false ) ), 'Metabox service can repair a trashed start link.' );
wp_untrash_post( $steps[0] );
// Simulate stale trusted-plugin data to exercise the warning, not a public write path.
update_post_meta( $steps[4], '_sprint_engine_next_step_id', $foreign );
sprint_engine_authoring_check( count( Authoring::warnings( $sprint ) ) > 0, 'Existing broken cross-Sprint links are warned about.' );
Meta::save( $steps[4], array( '_sprint_engine_next_step_id' => null ) );
// The explicit autosave guard is evaluated last because constants cannot be reset.
define( 'DOING_AUTOSAVE', true );
$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $foreign ), 'sprint_engine_meta' => array( '_sprint_engine_estimated_minutes' => 99 ) );
$authoring->save( $foreign );
sprint_engine_authoring_check( 7 === Meta::read( $foreign, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Autosave cannot change structural metadata.' );
$_POST = array();
foreach ( array_merge( $steps, array( $foreign, $sprint, $other ) ) as $id ) {
	wp_delete_post( $id, true );
}
echo "SE-002 integration checks completed.\n";
