<?php
/**
 * SE-002.1 checks against a disposable WordPress installation, without a browser.
 * Run after foundation.php with the same SE_TEST_* environment variables.
 *
 * @package SprintEngine
 */

use ThePath\SprintEngine\Content\Authoring;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureAdmin;
use ThePath\SprintEngine\Content\StructureManager;

$wordpress = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $wordpress || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
	fwrite( STDERR, "Set SE_TEST_WP_ROOT and SE_TEST_DISPOSABLE=yes for a disposable test site.\n" );
	exit( 1 );
}
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/post.php';
require_once ABSPATH . 'wp-admin/includes/template.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );

function sprint_engine_structure_check( $condition, $message ) {
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	echo 'PASS: ' . $message . PHP_EOL;
}

function sprint_engine_structure_state( $sprint ) {
	$state = array( 'sprint' => Meta::read( $sprint, 'sprint_engine_sprint' ) );
	foreach ( Meta::steps( $sprint ) as $step ) {
		$state[ $step->ID ] = Meta::read( $step->ID, 'sprint_engine_step' );
	}
	return $state;
}

function sprint_engine_structure_chain( $sprint, $order, $message ) {
	$valid = ( $order[0] ?? 0 ) === (int) get_post_meta( $sprint, '_sprint_engine_start_step_id', true );
	$valid = $valid && $order === wp_list_pluck( Meta::steps( $sprint ), 'ID' );
	foreach ( $order as $index => $id ) {
		$meta = Meta::read( $id, 'sprint_engine_step' );
		$valid = $valid && $sprint === $meta['_sprint_engine_sprint_id'] && $index + 1 === $meta['_sprint_engine_position'] && ( $order[ $index + 1 ] ?? 0 ) === $meta['_sprint_engine_next_step_id'];
	}
	sprint_engine_structure_check( $valid, $message );
}

wp_set_current_user( 1 );
$manager = new StructureManager();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_title' => 'Linear manager' ) );
$other = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_title' => 'Destination' ) );
$auto = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'auto-draft', 'post_title' => 'First save' ) );
ob_start(); StructureAdmin::render( $auto ); $locked = ob_get_clean();
sprint_engine_structure_check( str_contains( $locked, 'Sprint Structure' ) && str_contains( $locked, 'Save this Sprint to start adding Steps.' ) && str_contains( $locked, 'se-structure-locked' ) && str_contains( $locked, 'disabled' ) && ! str_contains( $locked, 'se-linear-manager' ) && ! str_contains( $locked, 'se-add-step' ), 'Auto-draft shows a headed, disabled Structure placeholder without active actions or sortable.' );
ob_start(); StructureAdmin::render( $sprint ); $html = ob_get_clean();
sprint_engine_structure_check( str_contains( $html, 'se-linear-manager' ) && ! str_contains( $html, 'se-publication-warning' ) && ! Meta::publication_readiness( $sprint )['ready'], 'Saved empty Draft has an active manager but no all-published success or unpublished warning.' );
ob_start(); ( new Authoring() )->setup( get_post( $sprint ) ); $html = ob_get_clean();
sprint_engine_structure_check( str_contains( $html, 'data-check="step_publication" data-complete="false"' ), 'Empty Sprint publication checklist remains incomplete.' );
foreach ( array( 'sprint_engine_sprint' => 'Sprint', 'sprint_engine_step' => 'Step' ) as $type => $label ) {
	$labels = get_post_type_object( $type )->labels;
	sprint_engine_structure_check( 'Add ' . $label === $labels->add_new && 'Add New ' . $label === $labels->add_new_item && 'Edit ' . $label === $labels->edit_item, $type . ' uses product-specific create/edit labels.' );
	sprint_engine_structure_check( use_block_editor_for_post_type( $type ), $type . ' still supports Gutenberg.' );
}
sprint_engine_structure_check( is_wp_error( Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ) ), 'Empty Sprint cannot become launchable.' );
foreach ( array( '', '   ', '<b></b>', array(), null ) as $title ) {
	sprint_engine_structure_check( is_wp_error( $manager->quick_add( $sprint, $title ) ) && array() === Meta::steps( $sprint ), 'Quick Add rejects empty or malformed title.' );
}
foreach ( array( 0, -1, 9999999, 'abc', array() ) as $invalid ) {
	sprint_engine_structure_check( is_wp_error( $manager->quick_add( $invalid, 'Do not create' ) ), 'Invalid Sprint ID is rejected.' );
}
$steps = array();
for ( $index = 1; $index <= 5; ++$index ) {
	$id = $manager->quick_add( $sprint, 'Step ' . $index, StructureManager::revision( $sprint ) );
	sprint_engine_structure_check( is_int( $id ) && 'draft' === get_post_status( $id ), 'Quick Add creates draft Step ' . $index );
	$steps[] = $id;
	sprint_engine_structure_chain( $sprint, $steps, 'Append maintains positions, explicit links, parent and start through Step ' . $index );
}
sprint_engine_structure_check( ! metadata_exists( 'post', $steps[4], '_sprint_engine_next_step_id' ), 'Final Step has an absent next-Step reference.' );
sprint_engine_structure_check( array() === Authoring::warnings( $sprint ), 'Quick Add happy path has no orphan/loop warnings.' );
$draft_state = sprint_engine_structure_state( $sprint );
$status_revision = StructureManager::revision( $sprint );
sprint_engine_structure_check( is_wp_error( Meta::save( $sprint, array( '_sprint_engine_launchable' => true, '_sprint_engine_completion_message' => 'Must not save' ) ) ) && $draft_state === sprint_engine_structure_state( $sprint ), 'Linear Draft Steps cannot become Launchable; invalid candidate saves no other metadata.' );
foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $index => $status ) {
    wp_update_post( array( 'ID' => $steps[$index], 'post_status' => $status, 'edit_date' => true, 'post_date' => '2036-01-01 00:00:00', 'post_date_gmt' => '2036-01-01 00:00:00' ) );
}
// A published fixture must have a past date to avoid core scheduling it.
wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'publish', 'edit_date' => true, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00' ) );
ob_start(); StructureAdmin::render( $sprint ); $html = ob_get_clean();
foreach ( array( 'publish', 'draft', 'pending', 'private', 'future' ) as $status ) {
    sprint_engine_structure_check( str_contains( $html, 'data-status="' . $status . '">' . esc_html( get_post_status_object( $status )->label ) ), 'Server fragment shows registered status label and stable marker: ' . $status );
}
sprint_engine_structure_check( 1 === substr_count( $html, 'se-publication-warning' ) && str_contains( $html, '4 Steps are not published.' ), 'Mixed statuses show one aggregate warning with the exact unpublished count.' );
sprint_engine_structure_check( $draft_state === sprint_engine_structure_state( $sprint ) && $status_revision === StructureManager::revision( $sprint ) && StructureManager::is_linear( $sprint ), 'Publication changes preserve parent, positions, start, next links, structural revision and linear validity.' );
foreach ( $steps as $step ) { wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'edit_date' => true, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00' ) ); }
ob_start(); StructureAdmin::render( $sprint ); $html = ob_get_clean();
sprint_engine_structure_check( ! str_contains( $html, 'se-publication-warning' ) && Meta::publication_readiness( $sprint )['ready'], 'All Published Steps remove the aggregate warning and satisfy publication readiness.' );
sprint_engine_structure_check( true === Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ), 'A complete linear Sprint with Published Steps becomes launchable.' );
wp_update_post( array( 'ID' => $steps[1], 'post_status' => 'draft' ) );
ob_start(); StructureAdmin::render( $sprint ); $html = ob_get_clean();
sprint_engine_structure_check( str_contains( $html, '1 Step is not published. Publish it' ) && 1 === substr_count( $html, 'se-publication-warning' ), 'One Draft shows singular publication warning.' );
sprint_engine_structure_check( is_wp_error( Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ) ) && Meta::read( $sprint, 'sprint_engine_sprint' )['_sprint_engine_launchable'], 'Already Launchable metadata is retained; saving an invalid checked candidate is rejected.' );
wp_update_post( array( 'ID' => $steps[1], 'post_status' => 'publish' ) );
$revision = StructureManager::revision( $sprint );
$order = array( $steps[3], $steps[1], $steps[4], $steps[0], $steps[2] );
sprint_engine_structure_check( true === $manager->apply_linear_order( $sprint, $order, $revision ), 'Reorder accepts a complete permutation.' );
sprint_engine_structure_chain( $sprint, $order, 'Reorder rewrites every position, link, start and terminal.' );
sprint_engine_structure_check( Meta::read( $sprint, 'sprint_engine_sprint' )['_sprint_engine_launchable'] && array() === Authoring::warnings( $sprint ), 'Valid reorder preserves launchable and has no structure warnings.' );
$before = sprint_engine_structure_state( $sprint );
sprint_engine_structure_check( true === $manager->apply_linear_order( $sprint, $order ) && $before === sprint_engine_structure_state( $sprint ), 'Repeated identical reorder is idempotent.' );
sprint_engine_structure_check( is_wp_error( $manager->apply_linear_order( $sprint, $steps, $revision ) ) && $before === sprint_engine_structure_state( $sprint ), 'Stale browser order is rejected without altering state.' );
sprint_engine_structure_check( is_wp_error( $manager->quick_add( $sprint, 'Stale add', $revision ) ) && $before === sprint_engine_structure_state( $sprint ), 'Stale Quick Add creates no extra Step.' );
$foreign = $manager->quick_add( $other, 'Foreign Step' );
$invalid_orders = array(
	array_merge( array_slice( $order, 0, 4 ), array( $foreign ) ),
	array_merge( array_slice( $order, 0, 4 ), array( $order[0] ) ),
	array_slice( $order, 0, 4 ),
	array(), null, 'invalid', array( 'wrong' => $order[0] ),
	array_merge( array_slice( $order, 0, 4 ), array( -1 ) ),
	array_merge( array_slice( $order, 0, 4 ), array( 1.5 ) ),
	array_merge( array_slice( $order, 0, 4 ), array( true ) ),
	array_merge( array_slice( $order, 0, 4 ), array( array() ) ),
	array_merge( array_slice( $order, 0, 4 ), array( '9999999999999999999999999' ) ),
	array_merge( array_slice( $order, 0, 4 ), array( 9999999 ) ),
	array_merge( array_slice( $order, 0, 4 ), array( $sprint ) ),
);
foreach ( $invalid_orders as $index => $invalid_order ) {
	sprint_engine_structure_check( is_wp_error( $manager->apply_linear_order( $sprint, $invalid_order ) ) && $before === sprint_engine_structure_state( $sprint ), 'Invalid full order rejected without partial changes: ' . $index );
}

// Fail a write after earlier writes succeeded; both database and cache must roll back.
$block_update = static function ( $check, $id, $key ) use ( $order ) {
	return $id === $order[1] && '_sprint_engine_position' === $key ? false : $check;
};
add_filter( 'update_post_metadata', $block_update, 10, 3 );
$result = $manager->apply_linear_order( $sprint, array_reverse( $order ) );
remove_filter( 'update_post_metadata', $block_update, 10 );
sprint_engine_structure_check( is_wp_error( $result ) && $before === sprint_engine_structure_state( $sprint ), 'Mid-order write failure rolls back every earlier mutation and clears caches.' );
$block_start = static function ( $check, $id, $key ) use ( $sprint ) {
	return $id === $sprint && '_sprint_engine_start_step_id' === $key ? false : $check;
};
add_filter( 'update_post_metadata', $block_start, 10, 3 );
$result = $manager->apply_linear_order( $sprint, array_reverse( $order ) );
remove_filter( 'update_post_metadata', $block_start, 10 );
sprint_engine_structure_check( is_wp_error( $result ) && $before === sprint_engine_structure_state( $sprint ), 'Failure saving final Sprint start rolls back all Step link writes.' );
$block_commit = static function ( $query ) { return 'COMMIT' === $query ? '' : $query; };
add_filter( 'query', $block_commit );
$result = $manager->apply_linear_order( $sprint, array_reverse( $order ) );
remove_filter( 'query', $block_commit );
sprint_engine_structure_check( is_wp_error( $result ) && $before === sprint_engine_structure_state( $sprint ), 'A failed commit never reports success and rolls back the requested structure.' );
$unsupported_engine = static function ( $query ) { return str_starts_with( $query, 'SHOW TABLE STATUS WHERE Name IN' ) ? "SELECT 'MyISAM' AS Engine UNION ALL SELECT 'MyISAM' AS Engine" : $query; };
add_filter( 'query', $unsupported_engine );
$result = $manager->apply_linear_order( $sprint, array_reverse( $order ) );
remove_filter( 'query', $unsupported_engine );
sprint_engine_structure_check( is_wp_error( $result ) && $before === sprint_engine_structure_state( $sprint ), 'Nontransactional content tables are rejected before writes.' );
$block_append = static function ( $check, $id, $key ) use ( $order ) {
	return $id === end( $order ) && '_sprint_engine_next_step_id' === $key ? false : $check;
};
add_filter( 'update_post_metadata', $block_append, 10, 3 );
$result = $manager->quick_add( $sprint, 'Must roll back' );
remove_filter( 'update_post_metadata', $block_append, 10 );
sprint_engine_structure_check( is_wp_error( $result ) && $before === sprint_engine_structure_state( $sprint ) && ! get_posts( array( 'post_type' => 'sprint_engine_step', 'post_status' => 'draft', 'title' => 'Must roll back' ) ), 'Failed Quick Add rolls back the new post, its metadata and existing links.' );

// Stale/corrupt structures can be repaired explicitly from their full active list.
update_post_meta( $order[0], '_sprint_engine_next_step_id', $foreign );
sprint_engine_structure_check( ! StructureManager::is_linear( $sprint ) && is_wp_error( Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ) ), 'Invalid stored chain cannot be approved as launchable.' );
sprint_engine_structure_check( true === $manager->apply_linear_order( $sprint, $order ) && StructureManager::is_linear( $sprint ), 'Save order repairs stale foreign links.' );
wp_trash_post( $order[2] );
$trashed_state = sprint_engine_structure_state( $sprint );
sprint_engine_structure_check( is_wp_error( $manager->apply_linear_order( $sprint, $order ) ) && $trashed_state === sprint_engine_structure_state( $sprint ), 'A submitted trashed Step is rejected.' );
wp_untrash_post( $order[2] );

// Normal metabox saves cannot override calculated links or stale start values.
$authoring = new Authoring();
$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $order[0] ), 'sprint_engine_meta' => array( '_sprint_engine_position' => 99, '_sprint_engine_next_step_id' => $foreign, '_sprint_engine_stage_label' => '<b>Review</b>', '_sprint_engine_estimated_minutes' => '8', '_sprint_engine_mode' => 'task', '_sprint_engine_sprint_id' => $sprint ) );
$authoring->save( $order[0] );
$meta = Meta::read( $order[0], 'sprint_engine_step' );
sprint_engine_structure_check( 1 === $meta['_sprint_engine_position'] && $order[1] === $meta['_sprint_engine_next_step_id'] && 'Review' === $meta['_sprint_engine_stage_label'] && 8 === $meta['_sprint_engine_estimated_minutes'] && 'task' === $meta['_sprint_engine_mode'], 'Step editor saves editable metadata and ignores forged calculated fields.' );
$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $sprint ), 'sprint_engine_meta' => array( '_sprint_engine_start_step_id' => $steps[0], '_sprint_engine_launchable' => '1' ) );
$authoring->save( $sprint );
sprint_engine_structure_check( $order[0] === (int) get_post_meta( $sprint, '_sprint_engine_start_step_id', true ), 'A stale Sprint metabox cannot undo an AJAX start change.' );
$_POST = array();
$before_move = sprint_engine_structure_state( $sprint );
// Move an incoming start into an empty Sprint to exercise a failing destination write.
$empty = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_title' => 'Empty destination' ) );
$block_move = static function ( $check, $id, $key ) use ( $empty ) { return $id === $empty && '_sprint_engine_start_step_id' === $key ? false : $check; };
add_filter( 'update_post_metadata', $block_move, 10, 3 );
$result = $manager->save_step( $order[0], array( '_sprint_engine_sprint_id' => $empty ) );
remove_filter( 'update_post_metadata', $block_move, 10 );
sprint_engine_structure_check( is_wp_error( $result ) && $before_move === sprint_engine_structure_state( $sprint ) && array() === Meta::steps( $empty ), 'Failed parent change rolls back parent and both structures.' );
$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $order[0] ), 'sprint_engine_meta' => array( '_sprint_engine_sprint_id' => $other, '_sprint_engine_mode' => 'content' ) );
$authoring->save( $order[0] );
$_POST = array();
sprint_engine_structure_chain( $sprint, array_slice( $order, 1 ), 'Step editor parent change rebuilds source positions and start.' );
sprint_engine_structure_chain( $other, array( $foreign, $order[0] ), 'Step editor parent change appends to destination and rebuilds links.' );
sprint_engine_structure_check( true === $manager->save_step( $foreign, array( '_sprint_engine_sprint_id' => 0 ) ), 'Individual Step can be detached without manual unlink controls.' );
sprint_engine_structure_chain( $other, array( $order[0] ), 'Detach repairs the remaining Sprint.' );
Meta::save( $other, array( '_sprint_engine_launchable' => true ) );
sprint_engine_structure_check( true === $manager->save_step( $order[0], array( '_sprint_engine_sprint_id' => 0 ) ), 'Last Step can be detached.' );
sprint_engine_structure_check( 0 === (int) get_post_meta( $other, '_sprint_engine_start_step_id', true ) && ! Meta::read( $other, 'sprint_engine_sprint' )['_sprint_engine_launchable'], 'Removing last Step clears start and launchable.' );

ob_start(); $authoring->render( get_post( $sprint ) ); $html = ob_get_clean();
sprint_engine_structure_check( str_contains( $html, 'se-drag' ) && str_contains( $html, 'Start' ) && str_contains( $html, 'Final' ) && str_contains( $html, 'se-next-title' ) && str_contains( $html, 'se-add-step' ) && ! str_contains( $html, 'name="sprint_engine_meta[_sprint_engine_start_step_id]"' ), 'Manager renders handles, calculated links, Start/Final, Quick Add and read-only start.' );
ob_start(); $authoring->render( get_post( $order[1] ) ); $html = ob_get_clean();
sprint_engine_structure_check( str_contains( $html, 'Position:' ) && str_contains( $html, 'Next Step:' ) && ! str_contains( $html, 'name="sprint_engine_meta[_sprint_engine_position]"' ) && ! str_contains( $html, 'name="sprint_engine_meta[_sprint_engine_next_step_id]"' ), 'Step editor renders calculated position/next without editable controls.' );
set_current_screen( 'edit-sprint_engine_step' );
( new StructureAdmin() )->enqueue();
sprint_engine_structure_check( ! wp_script_is( 'sprint-engine-structure', 'enqueued' ), 'Manager assets are not enqueued on unrelated admin screens.' );
set_current_screen( 'sprint_engine_sprint' );
( new StructureAdmin() )->enqueue();
sprint_engine_structure_check( wp_script_is( 'sprint-engine-structure', 'enqueued' ) && in_array( 'jquery-ui-sortable', wp_scripts()->registered['sprint-engine-structure']->deps, true ), 'Sprint editor enqueues WordPress Sortable and local assets.' );

// Exercise the actual authenticated AJAX action and JSON envelope.
define( 'DOING_AJAX', true );
class SE_Structure_Ajax_End extends RuntimeException {}
add_filter( 'wp_die_ajax_handler', static function () { return static function () { throw new SE_Structure_Ajax_End(); }; } );
function sprint_engine_structure_ajax( $sprint, $operation, $extra = array(), $nonce = null ) {
	$_POST = array_merge( array( 'sprint' => (string) $sprint, 'operation' => $operation, 'nonce' => $nonce ?? wp_create_nonce( 'sprint_engine_structure_' . $sprint ), 'revision' => StructureManager::revision( $sprint ) ), $extra );
	$_REQUEST = $_POST;
	ob_start();
	try { do_action( 'wp_ajax_sprint_engine_structure' ); } catch ( SE_Structure_Ajax_End $exception ) {}
	$result = json_decode( ob_get_clean(), true );
	$_POST = array(); $_REQUEST = array();
	return $result;
}
$ajax_before = sprint_engine_structure_state( $sprint );
$response = sprint_engine_structure_ajax( $auto, 'refresh' );
sprint_engine_structure_check( false === $response['success'] && 'auto-draft' === get_post_status( $auto ) && ! Meta::steps( $auto ), 'Read-only refresh rejects an unsaved Sprint without saving it or creating Steps.' );
wp_update_post( array( 'ID' => $auto, 'post_status' => 'draft' ) );
$refresh_before = sprint_engine_structure_state( $auto );
$response = sprint_engine_structure_ajax( $auto, 'refresh', array( 'title' => 'Ignored', 'order' => '[]', 'revision' => 'irrelevant' ) );
sprint_engine_structure_check( true === $response['success'] && str_contains( $response['data']['html'], 'se-linear-manager' ) && ! str_contains( $response['data']['html'], 'se-structure-locked' ) && $refresh_before === sprint_engine_structure_state( $auto ), 'First-save refresh returns only the normal active fragment and ignores write payloads without mutation.' );
$response = sprint_engine_structure_ajax( $auto, 'refresh', array(), 'invalid' );
sprint_engine_structure_check( false === $response['success'] && $refresh_before === sprint_engine_structure_state( $auto ), 'Refresh requires the Sprint nonce.' );
foreach ( array( $steps[0], 99999999 ) as $invalid_sprint ) {
    $response = sprint_engine_structure_ajax( $invalid_sprint, 'refresh' );
    sprint_engine_structure_check( false === $response['success'], 'Refresh rejects missing or wrong-type Sprint.' );
}
$deny_edit = static function ( $caps, $cap, $user, $args ) use ( $auto ) { return $auto === ( $args[0] ?? 0 ) ? array( 'do_not_allow' ) : $caps; };
add_filter( 'map_meta_cap', $deny_edit, 10, 4 );
$response = sprint_engine_structure_ajax( $auto, 'refresh' );
remove_filter( 'map_meta_cap', $deny_edit, 10 );
sprint_engine_structure_check( false === $response['success'] && $refresh_before === sprint_engine_structure_state( $auto ), 'manage_options alone cannot refresh without edit_post.' );
$response = sprint_engine_structure_ajax( $sprint, 'add', array( 'title' => 'Nonce fail' ), 'invalid' );
sprint_engine_structure_check( false === $response['success'] && $ajax_before === sprint_engine_structure_state( $sprint ), 'AJAX rejects an invalid nonce without creating content.' );
sprint_engine_structure_check( ! has_action( 'wp_ajax_nopriv_sprint_engine_structure' ), 'No anonymous AJAX mutation handler is exposed.' );
$response = sprint_engine_structure_ajax( $sprint, 'order', array( 'order' => '{broken' ) );
sprint_engine_structure_check( false === $response['success'] && $ajax_before === sprint_engine_structure_state( $sprint ), 'AJAX rejects malformed order JSON without writes.' );
$response = sprint_engine_structure_ajax( $sprint, 'add', array( 'title' => '<b>AJAX</b> draft' ) );
sprint_engine_structure_check( true === $response['success'] && str_contains( $response['data']['html'], 'AJAX draft' ) && StructureManager::is_linear( $sprint ), 'Authenticated AJAX Quick Add returns an updated valid manager.' );
sprint_engine_structure_check( str_contains( $response['data']['html'], 'data-status="draft">Draft' ) && str_contains( $response['data']['html'], 'se-publication-warning' ), 'Quick Add immediately returns its Draft badge and aggregate publication warning.' );
$ajax_order = array_reverse( wp_list_pluck( Meta::steps( $sprint ), 'ID' ) );
$response = sprint_engine_structure_ajax( $sprint, 'order', array( 'order' => wp_json_encode( $ajax_order ) ) );
sprint_engine_structure_check( true === $response['success'], 'Authenticated AJAX saves a reordered list.' );
sprint_engine_structure_chain( $sprint, $ajax_order, 'AJAX derives positions and links from IDs alone.' );
$response = sprint_engine_structure_ajax( $sprint, 'unknown' );
sprint_engine_structure_check( false === $response['success'], 'Unknown AJAX operation is rejected.' );
$before_remove = sprint_engine_structure_state( $sprint );
foreach ( array(
    array( array( 'step' => (string) $ajax_order[0] ), 'invalid' ),
    array( array( 'step' => (string) $foreign ), null ),
    array( array( 'step' => array( $ajax_order[0] ) ), null ),
    array( array( 'step' => (string) $ajax_order[0], 'revision' => 'stale' ), null ),
) as $case ) {
    $response = sprint_engine_structure_ajax( $sprint, 'remove', $case[0], $case[1] );
    sprint_engine_structure_check( false === $response['success'] && $before_remove === sprint_engine_structure_state( $sprint ), 'AJAX Remove rejects invalid nonce, foreign/malformed Step or stale revision.' );
}
$response = sprint_engine_structure_ajax( $sprint, 'remove', array( 'step' => (string) $ajax_order[0] ) );
sprint_engine_structure_check( true === $response['success'] && 'trash' === get_post_status( $ajax_order[0] ) && str_contains( $response['data']['html'], 'se-linear-manager' ) && StructureManager::is_linear( $sprint ), 'AJAX Remove returns fresh fragment after verified trash and structure repair.' );
$subscriber = wp_insert_user( array( 'user_login' => 'structure-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
foreach ( array( 0, $subscriber ) as $user ) {
	wp_set_current_user( $user );
	$response = sprint_engine_structure_ajax( $auto, 'refresh' );
	ob_start(); StructureAdmin::render( $auto ); $hidden = ob_get_clean();
	sprint_engine_structure_check( false === $response['success'] && '' === $hidden, 'Unauthorized user has no Structure UI or read-only refresh access.' );
	$state = sprint_engine_structure_state( $sprint );
	sprint_engine_structure_check( is_wp_error( $manager->apply_linear_order( $sprint, $ajax_order ) ) && is_wp_error( $manager->quick_add( $sprint, 'Denied' ) ) && $state === sprint_engine_structure_state( $sprint ), 'Unauthorized service caller cannot reorder or quick-create.' );
	$response = sprint_engine_structure_ajax( $sprint, 'add', array( 'title' => 'Denied' ) );
	sprint_engine_structure_check( false === $response['success'] && $state === sprint_engine_structure_state( $sprint ), 'Even a valid nonce cannot bypass AJAX capabilities.' );
	$response = sprint_engine_structure_ajax( $sprint, 'remove', array( 'step' => (string) $ajax_order[1] ) );
	ob_start(); StructureAdmin::render( $sprint ); $hidden = ob_get_clean();
	sprint_engine_structure_check( false === $response['success'] && '' === $hidden && $state === sprint_engine_structure_state( $sprint ), 'Unauthorized Remove has neither UI nor AJAX access.' );
}
wp_set_current_user( 1 );
foreach ( array_unique( array_merge( $steps, $ajax_order, array( $foreign, $sprint, $other, $empty, $auto ) ) ) as $id ) { wp_delete_post( $id, true ); }
echo "SE-002.1 integration checks completed.\n";
