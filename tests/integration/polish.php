<?php
/** SE-010 focused checks using the existing disposable WordPress test setup. */
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\Authoring;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Progress\ProgressService;

if ( 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) || ! getenv( 'SE_TEST_WP_ROOT' ) ) { exit( 1 ); }
require getenv( 'SE_TEST_WP_ROOT' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function sprint_engine_polish_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    echo 'PASS: ' . $message . PHP_EOL;
}
function sprint_engine_polish_rest( $id, $meta ) {
    $request = new WP_REST_Request( 'POST', '/wp/v2/sprint_engine_sprint/' . $id );
    $request->set_body_params( array( 'meta' => $meta ) );
    return rest_get_server()->dispatch( $request );
}
function sprint_engine_polish_history( $sprint ) {
    global $wpdb;
    return array(
        $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments WHERE sprint_id = %d", $sprint ), ARRAY_A ),
        $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress WHERE sprint_id = %d ORDER BY id", $sprint ), ARRAY_A ),
    );
}
wp_set_current_user( 1 );
$manager = new StructureManager();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Polish fixture', 'post_name' => 'polish-' . wp_generate_password( 8, false ) ) );
$steps = array();
for ( $i = 0; $i < 4; ++$i ) { $steps[] = $manager->quick_add( $sprint, 'Polish Step ' . $i ); }
foreach ( $steps as $published_step ) { wp_update_post( array( 'ID' => $published_step, 'post_status' => 'publish' ) ); }
Meta::save( $sprint, array( '_sprint_engine_launchable' => true, '_sprint_engine_estimated_minutes' => 240 ) );
sprint_engine_polish_check( '240 minutes' === Meta::sprint_duration( $sprint )['label'], 'Legacy minutes retain their original timescale.' );
update_post_meta( $sprint, '_sprint_engine_estimated_duration_value', '4' );
delete_post_meta( $sprint, '_sprint_engine_estimated_duration_unit' );
sprint_engine_polish_check( '240 minutes' === Meta::sprint_duration( $sprint )['label'], 'Incomplete new metadata pair falls back to legacy minutes.' );
foreach ( array( array( '1', 'minutes', '1 minute' ), array( '2', 'minutes', '2 minutes' ), array( '1', 'hours', '1 hour' ), array( '4.00', 'hours', '4 hours' ), array( '1', 'days', '1 day' ), array( '3', 'days', '3 days' ), array( '1.50', 'hours', '1.5 hours' ), array( '2.5', 'days', '2.5 days' ), array( '0.01', 'hours', '0.01 hours' ) ) as $case ) {
    $result = Meta::save( $sprint, array( '_sprint_engine_estimated_duration_value' => $case[0], '_sprint_engine_estimated_duration_unit' => $case[1] ) );
    sprint_engine_polish_check( true === $result && $case[2] === Meta::sprint_duration( $sprint )['label'], 'Duration: ' . $case[2] );
}
sprint_engine_polish_check( ! metadata_exists( 'post', $sprint, '_sprint_engine_estimated_minutes' ), 'New save retires only Sprint legacy minutes.' );
$before = Meta::read( $sprint, 'sprint_engine_sprint' );
foreach ( array( '-1', '0', '0.00', 'NaN', 'INF', '1e3', '1.234', '<b>4</b>', 'color:red', array(), new stdClass(), true, null, '1000000000' ) as $invalid ) {
    sprint_engine_polish_check( is_wp_error( Meta::save( $sprint, array( '_sprint_engine_estimated_duration_value' => $invalid ) ) ) && $before === Meta::read( $sprint, 'sprint_engine_sprint' ), 'Malformed duration rejected without writes.' );
}
sprint_engine_polish_check( is_wp_error( Meta::save( $sprint, array( '_sprint_engine_estimated_duration_unit' => 'weeks' ) ) ), 'Duration unit allowlist enforced.' );
foreach ( array( array( '_sprint_engine_estimated_duration_value' => 'NaN' ), array( '_sprint_engine_estimated_duration_value' => array( '4' ) ), array( '_sprint_engine_estimated_duration_unit' => 'weeks' ) ) as $invalid ) {
    sprint_engine_polish_check( 400 === sprint_engine_polish_rest( $sprint, $invalid )->get_status(), 'Native REST rejects invalid duration metadata.' );
}
update_post_meta( $sprint, '_sprint_engine_estimated_minutes', 240 );
sprint_engine_polish_check( 200 === sprint_engine_polish_rest( $sprint, array( '_sprint_engine_estimated_duration_value' => '', '_sprint_engine_estimated_duration_unit' => 'hours' ) )->get_status() && array() === Meta::sprint_duration( $sprint ) && ! metadata_exists( 'post', $sprint, '_sprint_engine_estimated_minutes' ), 'Native REST clear cannot revive legacy duration.' );
update_post_meta( $sprint, '_sprint_engine_estimated_minutes', 240 );
Meta::save( $sprint, array( '_sprint_engine_estimated_duration_value' => '' ) );
sprint_engine_polish_check( array() === Meta::sprint_duration( $sprint ), 'Metabox service clear cannot revive legacy duration.' );
sprint_engine_polish_check( 200 === sprint_engine_polish_rest( $sprint, array( '_sprint_engine_estimated_duration_value' => '4.00', '_sprint_engine_estimated_duration_unit' => 'hours' ) )->get_status() && '4' === Meta::sprint_duration( $sprint )['value'], 'REST saves normalized new duration.' );
$_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $sprint ), 'sprint_engine_meta' => array( '_sprint_engine_estimated_duration_value' => '4.00', '_sprint_engine_estimated_duration_unit' => 'hours', '_sprint_engine_launchable' => '1' ) );
( new Authoring() )->save( $sprint );
$_POST = array();
sprint_engine_polish_check( '4 hours' === Meta::sprint_duration( $sprint )['label'], 'Nonce-protected Sprint metabox saves new duration.' );
Meta::save( $steps[0], array( '_sprint_engine_estimated_minutes' => 10, '_sprint_engine_stage_label' => 'Reflect', '_sprint_engine_mode' => 'task' ) );
ob_start(); ( new Authoring() )->render( get_post( $sprint ) ); $html = ob_get_clean();
sprint_engine_polish_check( str_contains( $html, 'Estimated duration (optional)' ) && ! str_contains( $html, 'Estimated minutes' ), 'Sprint UI uses duration value and unit.' );
ob_start(); ( new Authoring() )->render( get_post( $steps[0] ) ); $html = ob_get_clean();
sprint_engine_polish_check( str_contains( $html, 'Estimated minutes (optional)' ) && 10 === Meta::read( $steps[0], 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Step minute UI and storage unchanged.' );

$theme = get_theme_support( 'post-thumbnails' );
foreach ( array( false, array( 'post' ), true ) as $support ) {
    remove_theme_support( 'post-thumbnails' );
    if ( true === $support ) { add_theme_support( 'post-thumbnails' ); }
    elseif ( $support ) { add_theme_support( 'post-thumbnails', $support ); }
    $post_before = current_theme_supports( 'post-thumbnails', 'post' );
    $page_before = current_theme_supports( 'post-thumbnails', 'page' );
    ( new Authoring() )->thumbnail_support();
    sprint_engine_polish_check( current_theme_supports( 'post-thumbnails', 'sprint_engine_sprint' ) && current_theme_supports( 'post-thumbnails', 'sprint_engine_step' ) && post_type_supports( 'sprint_engine_step', 'thumbnail' ) && $post_before === current_theme_supports( 'post-thumbnails', 'post' ) && $page_before === current_theme_supports( 'post-thumbnails', 'page' ), 'Native thumbnail support preserves ordinary theme choices.' );
}
remove_theme_support( 'post-thumbnails' );
if ( true === $theme ) { add_theme_support( 'post-thumbnails' ); } elseif ( $theme ) { add_theme_support( 'post-thumbnails', $theme[0] ); }
$images = array();
foreach ( array( 'Sprint banner', 'Step banner' ) as $alt ) {
    $image = wp_insert_attachment( array( 'post_title' => $alt, 'post_mime_type' => 'image/png', 'guid' => home_url( '/fixture.png' ) ) );
    update_post_meta( $image, '_wp_attached_file', 'fixture.png' );
    update_post_meta( $image, '_wp_attachment_image_alt', $alt );
    wp_update_attachment_metadata( $image, array( 'width' => 1600, 'height' => 700, 'file' => 'fixture.png' ) );
    $images[] = $image;
}
set_post_thumbnail( $sprint, $images[0] ); set_post_thumbnail( $steps[0], $images[1] );
$user = wp_insert_user( array( 'user_login' => 'polish-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$runner = new Runner(); $service = new ProgressService(); $slug = get_post_field( 'post_name', $sprint );
wp_set_current_user( 0 );
sprint_engine_polish_check( ! isset( $runner->resolve( $slug )['featured_image'] ), 'Anonymous context exposes no image.' );
wp_set_current_user( $user );
$context = $runner->resolve( $slug );
sprint_engine_polish_check( str_contains( $context['featured_image'], 'alt="Sprint banner"' ) && '4 hours' === $context['duration']['label'], 'Start uses Sprint image, native alt and central duration.' );
sprint_engine_polish_check( 403 === sprint_engine_polish_rest( $sprint, array( '_sprint_engine_estimated_duration_value' => '8' ) )->get_status(), 'Member cannot edit protected duration.' );
$service->start_sprint( $user, $sprint );
$context = $runner->resolve( $slug );
sprint_engine_polish_check( str_contains( $context['featured_image'], 'alt="Step banner"' ) && ! isset( $context['duration'] ) && 10 === $context['step']['estimated_minutes'], 'Current Step uses only its own banner and minutes.' );
$history = sprint_engine_polish_history( $sprint );
sprint_engine_polish_check( is_wp_error( $manager->trash_step( $sprint, $steps[0], StructureManager::revision( $sprint ) ) ), 'Member cannot remove a Step.' );
wp_set_current_user( 1 );
Meta::save( $sprint, array( '_sprint_engine_estimated_duration_value' => '2', '_sprint_engine_estimated_duration_unit' => 'days' ) );
sprint_engine_polish_check( $history === sprint_engine_polish_history( $sprint ), 'Duration edit never changes progress.' );

$revision = StructureManager::revision( $sprint );
foreach ( array( 0, -1, '1', array(), $sprint, 99999999 ) as $invalid ) {
    sprint_engine_polish_check( is_wp_error( $manager->trash_step( $sprint, $invalid, $revision ) ) && $revision === StructureManager::revision( $sprint ), 'Invalid removal ID rejected without writes.' );
}
sprint_engine_polish_check( is_wp_error( $manager->trash_step( $sprint, $steps[0], 'stale' ) ) && is_wp_error( $manager->trash_step( $sprint, $steps[0], null ) ), 'Removal requires a current revision.' );
$snapshot = get_post_meta( $steps[0] );
$deny_delete = static function ( $allcaps, $caps, $args ) use ( $steps ) { if ( 'delete_post' === $args[0] && ( $args[2] ?? 0 ) === $steps[0] ) { $allcaps['manage_options'] = false; } return $allcaps; };
add_filter( 'user_has_cap', $deny_delete, 10, 3 );
$result = $manager->trash_step( $sprint, $steps[0], $revision );
remove_filter( 'user_has_cap', $deny_delete, 10 );
sprint_engine_polish_check( is_wp_error( $result ) && $revision === StructureManager::revision( $sprint ), 'Step delete capability required even for an administrator.' );
foreach ( array( 'pre_trash_post', 'update_post_metadata', 'query' ) as $failure ) {
    $block = static function ( $value, $id = null, $key = null ) use ( $failure, $steps ) {
        if ( 'pre_trash_post' === $failure ) { return false; }
        if ( 'query' === $failure ) { return 'COMMIT' === $value ? '' : $value; }
        return $id === $steps[1] && '_sprint_engine_position' === $key ? false : $value;
    };
    add_filter( $failure, $block, 10, 3 );
    $result = $manager->trash_step( $sprint, $steps[0], $revision );
    remove_filter( $failure, $block, 10 );
    sprint_engine_polish_check( is_wp_error( $result ) && $revision === StructureManager::revision( $sprint ) && 'publish' === get_post_status( $steps[0] ) && $snapshot === get_post_meta( $steps[0] ), 'Removal rolls back structure, status and metadata on ' . $failure . ' failure.' );
}
foreach ( array( $steps[1], $steps[0], $steps[3], $steps[2] ) as $index => $step ) {
    sprint_engine_polish_check( true === $manager->trash_step( $sprint, $step, StructureManager::revision( $sprint ) ) && 'trash' === get_post_status( $step ), 'Remove middle/first/final/only Step via core Trash: ' . $index );
    sprint_engine_polish_check( ! metadata_exists( 'post', $step, '_sprint_engine_sprint_id' ) && ! metadata_exists( 'post', $step, '_sprint_engine_position' ) && ! metadata_exists( 'post', $step, '_sprint_engine_next_step_id' ), 'Removed Step has no structural membership.' );
    sprint_engine_polish_check( $history === sprint_engine_polish_history( $sprint ), 'Removal retains exact historical progress and current pointer.' );
    if ( $index < 3 ) { sprint_engine_polish_check( StructureManager::is_linear( $sprint ), 'Remaining positions, start and terminal form one valid chain.' ); }
    if ( 1 === $index ) {
        wp_set_current_user( $user );
        $context = $runner->resolve( $slug );
        sprint_engine_polish_check( $steps[2] === $context['step']['id'] && '' === $context['featured_image'], 'Existing resume recovers removed current Step; missing Step image has no Sprint fallback.' );
        $history = sprint_engine_polish_history( $sprint );
        wp_set_current_user( 1 );
    }
}
sprint_engine_polish_check( ! Meta::read( $sprint, 'sprint_engine_sprint' )['_sprint_engine_launchable'] && 0 === (int) get_post_meta( $sprint, '_sprint_engine_start_step_id', true ), 'Empty Sprint clears start and Launchable.' );
sprint_engine_polish_check( is_wp_error( $manager->trash_step( $sprint, $steps[0], StructureManager::revision( $sprint ) ) ), 'Already trashed Step rejected.' );
wp_untrash_post( $steps[0] );
sprint_engine_polish_check( array() === Meta::steps( $sprint ) && 10 === Meta::read( $steps[0], 'sprint_engine_step' )['_sprint_engine_estimated_minutes'] && $images[1] === get_post_thumbnail_id( $steps[0] ) && 'Reflect' === get_post_meta( $steps[0], '_sprint_engine_stage_label', true ), 'Restore leaves Step unassigned and retains authored metadata/image.' );
wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'publish' ) );
$manager->save_step( $steps[0], array( '_sprint_engine_sprint_id' => $sprint ) );
Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
wp_set_current_user( $user ); $service->resume_sprint( $user, $sprint ); $service->complete_step( $user, $sprint, $steps[0] );
$context = $runner->resolve( $slug );
sprint_engine_polish_check( 'completed' === $context['state'] && str_contains( $context['featured_image'], 'alt="Sprint banner"' ), 'Completion returns Sprint banner.' );
update_post_meta( $sprint, '_thumbnail_id', 99999999 );
sprint_engine_polish_check( '' === $runner->resolve( $slug )['featured_image'], 'Missing image attachment renders nothing.' );
delete_post_thumbnail( $sprint );
sprint_engine_polish_check( '' === $runner->resolve( $slug )['featured_image'], 'No Sprint image renders nothing.' );
wp_set_current_user( 1 );
foreach ( array_merge( $steps, array( $sprint ) ) as $id ) { wp_delete_post( $id, true ); }
foreach ( $images as $id ) { wp_delete_attachment( $id, true ); }
$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $user ) );
$wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $user ) );
wp_delete_user( $user );
echo "SE-010 focused integration checks completed.\n";
