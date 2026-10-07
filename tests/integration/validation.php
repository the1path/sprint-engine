<?php
/** SE-010.1 regression checks on the existing disposable WordPress fixture. */
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\Authoring;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Settings\RunnerBranding;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Progress\ProgressService;

if ( 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) || ! getenv( 'SE_TEST_WP_ROOT' ) ) { exit( 1 ); }
require getenv( 'SE_TEST_WP_ROOT' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
function sprint_engine_validation_check( $condition, $message ) {
    if ( ! $condition ) { throw new RuntimeException( $message ); }
    echo 'PASS: ' . $message . PHP_EOL;
}
function sprint_engine_validation_rest( $id, $meta ) {
    $request = new WP_REST_Request( 'POST', '/wp/v2/' . get_post_type( $id ) . '/' . $id );
    $request->set_body_params( array( 'meta' => $meta ) );
    return rest_get_server()->dispatch( $request );
}
function sprint_engine_validation_submit( $id, $fields ) {
    $_POST = array( 'sprint_engine_authoring_nonce' => wp_create_nonce( 'sprint_engine_authoring_' . $id ), 'sprint_engine_meta' => $fields );
    ( new Authoring() )->save( $id );
    $_POST = array();
}
wp_set_current_user( 1 );
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Validation fixture', 'post_name' => 'validation-' . wp_generate_password( 8, false ) ) );
$manager = new StructureManager();
$step = $manager->quick_add( $sprint, 'Validation Step' );
wp_update_post( array( 'ID' => $step, 'post_status' => 'publish' ) );
$member = wp_insert_user( array( 'user_login' => 'validation-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$branding = get_option( RunnerBranding::OPTION, null );
try {
    Meta::save( $sprint, array( '_sprint_engine_launchable' => true, '_sprint_engine_estimated_duration_value' => '6', '_sprint_engine_estimated_duration_unit' => 'hours' ) );
    ( new ProgressService() )->start_sprint( $member, $sprint );
    $history = array();
    foreach ( array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) as $table ) { $history[$table] = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}{$table} ORDER BY id", ARRAY_A ); }
    $revision = StructureManager::revision( $sprint );
    $before = Meta::read( $sprint, 'sprint_engine_sprint' );
    foreach ( array( '4-6', 'four', '0', '-1', '1.234', 'NaN', 'Infinity', '<b>4</b>', array(), new stdClass() ) as $bad ) {
        sprint_engine_validation_submit( $sprint, array( '_sprint_engine_estimated_duration_value' => $bad, '_sprint_engine_estimated_duration_unit' => 'days', '_sprint_engine_launchable' => '1' ) );
        sprint_engine_validation_check( $before === Meta::read( $sprint, 'sprint_engine_sprint' ) && str_contains( get_transient( 'sprint_engine_authoring_error_1_' . $sprint )['message'], 'Estimated duration' ), 'Invalid duration retains value/unit and records actionable notice.' );
        sprint_engine_validation_check( 400 === sprint_engine_validation_rest( $sprint, array( '_sprint_engine_estimated_duration_value' => $bad ) )->get_status(), 'Native REST rejects invalid duration.' );
    }
    set_current_screen( 'sprint_engine_sprint' ); $GLOBALS['post'] = get_post( $sprint );
    $_GET['meta-box-loader'] = '1';
    ob_start(); ( new Authoring() )->notice(); $hidden = ob_get_clean();
    unset( $_GET['meta-box-loader'] );
    sprint_engine_validation_check( '' === $hidden && get_transient( 'sprint_engine_authoring_error_1_' . $sprint ), 'Gutenberg hidden metabox reload does not consume author notice.' );
    ob_start(); ( new Authoring() )->notice( true ); $notice = ob_get_clean();
    sprint_engine_validation_check( str_contains( $notice, 'notice notice-error' ) && str_contains( $notice, 'Sprint Engine fields were not saved:' ) && ! str_contains( $notice, '<b>4</b>' ), 'Accessible visible notice uses general wording and never echoes unsafe input.' );
    $context = ( new Runner() )->resolve( get_post_field( 'post_name', $sprint ) );
    sprint_engine_validation_check( '6 hours' === $context['duration']['label'], 'Runner retains 6 hours after rejected replacement.' );
    foreach ( array( '4', '4.50', '0.5' ) as $valid ) {
        sprint_engine_validation_check( true === Meta::save( $sprint, array( '_sprint_engine_estimated_duration_value' => $valid ) ), 'Valid one-value duration saves.' );
    }
    update_post_meta( $sprint, '_sprint_engine_estimated_minutes', 240 );
    sprint_engine_validation_submit( $sprint, array( '_sprint_engine_estimated_duration_value' => '', '_sprint_engine_launchable' => '1' ) );
    sprint_engine_validation_check( array() === Meta::sprint_duration( $sprint ) && ! metadata_exists( 'post', $sprint, '_sprint_engine_estimated_minutes' ), 'Intentional blank clears duration without reviving legacy minutes.' );
    Meta::save( $step, array( '_sprint_engine_estimated_minutes' => 60 ) );
    $before_step = Meta::read( $step, 'sprint_engine_step' );
    foreach ( array( 0, '0', '-1', '1.5', '60 mins', 'one hour', '<b>60</b>', array(), new stdClass(), false ) as $bad ) {
        sprint_engine_validation_submit( $step, array( '_sprint_engine_estimated_minutes' => $bad, '_sprint_engine_sprint_id' => 0 ) );
        sprint_engine_validation_check( $before_step === Meta::read( $step, 'sprint_engine_step' ) && str_contains( get_transient( 'sprint_engine_authoring_error_1_' . $step )['message'], 'Estimated minutes' ), 'Invalid minutes preserve estimate and parent before structural writes.' );
        sprint_engine_validation_check( 400 === sprint_engine_validation_rest( $step, array( '_sprint_engine_estimated_minutes' => $bad ) )->get_status(), 'Native REST rejects invalid Step minutes.' );
    }
    sprint_engine_validation_check( true === $manager->save_step( $step, array( '_sprint_engine_estimated_minutes' => '' ) ) && 0 === Meta::read( $step, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Blank Step estimate clears intentionally.' );
    sprint_engine_validation_check( true === $manager->save_step( $step, array( '_sprint_engine_stage_label' => 'Any custom phase / 2026' ) ), 'Unset internal zero allows unrelated free-text Step saves.' );
    foreach ( array( 1, 10, 60, 120 ) as $valid ) { sprint_engine_validation_check( true === $manager->save_step( $step, array( '_sprint_engine_estimated_minutes' => $valid ) ), 'Positive whole minutes save.' ); }
    sprint_engine_validation_check( 200 === sprint_engine_validation_rest( $step, array( '_sprint_engine_estimated_minutes' => null ) )->get_status() && 0 === Meta::read( $step, 'sprint_engine_step' )['_sprint_engine_estimated_minutes'], 'Native REST null intentionally removes optional integer metadata.' );
    // SE-010.2: server fallback maps to native controls even without JavaScript.
    foreach ( array(
        array( $sprint, '_sprint_engine_estimated_duration_value', '4-6', 'se-duration-help' ),
        array( $sprint, '_sprint_engine_estimated_duration_unit', 'weeks', '' ),
        array( $step, '_sprint_engine_estimated_minutes', '60 mins', 'se-minutes-help' ),
        array( $step, '_sprint_engine_mode', 'decision', '' ),
        array( $step, '_sprint_engine_sprint_id', $step, '' ),
    ) as $case ) {
        list( $id, $field, $invalid, $help ) = $case;
        sprint_engine_validation_submit( $id, array( $field => $invalid ) );
        $fallback = get_transient( 'sprint_engine_authoring_error_1_' . $id );
        sprint_engine_validation_check( array( $field ) === $fallback['fields'], 'Fallback identifies ' . $field );
        $error = Meta::validate( $id, get_post_type( $id ), array( $field => $invalid ) );
        sprint_engine_validation_check( 400 === $error->get_error_data()['status'] && array( $field ) === $error->get_error_data()['fields'], 'Field identity preserves REST status.' );
        set_current_screen( get_post_type( $id ) ); $GLOBALS['post'] = get_post( $id );
        $authoring = new Authoring();
        $_GET['se-metabox-response'] = '1';
        ob_start(); $authoring->notice( true ); $hidden = ob_get_clean();
        unset( $_GET['se-metabox-response'] );
        sprint_engine_validation_check( '' === $hidden && $fallback === get_transient( 'sprint_engine_authoring_error_1_' . $id ), 'Redirected hidden response preserves field identity.' );
        ob_start(); $authoring->render( get_post( $id ) ); $html = ob_get_clean();
        $dom = new DOMDocument();
        $previous = libxml_use_internal_errors( true ); $dom->loadHTML( '<?xml encoding="utf-8" ?>' . $html ); libxml_clear_errors(); libxml_use_internal_errors( $previous );
        $xpath = new DOMXPath( $dom );
        $control = $xpath->query( '//*[@id="' . $field . '"]' )->item(0);
        $output = $xpath->query( '//*[@id="' . $field . '-error"]' );
        sprint_engine_validation_check( 'true' === $control->getAttribute( 'aria-invalid' ) && str_contains( $control->getAttribute('aria-describedby'), $field . '-error' ) && ( ! $help || str_contains( $control->getAttribute('aria-describedby'), $help ) ), 'Invalid control retains help and error relationships.' );
        sprint_engine_validation_check( 1 === $output->length && ! $output->item(0)->hasAttribute('hidden') && str_contains( $output->item(0)->textContent, $fallback['message'] ), 'One visible escaped error beside its field.' );
        sprint_engine_validation_submit( $id, array() );
        sprint_engine_validation_check( ! get_transient( 'sprint_engine_authoring_error_1_' . $id ), 'Valid save clears stale feedback.' );
    }
    foreach ( array( 'http://example.org/next', 'https://example.org/next?a=1&b=2' ) as $url ) {
        sprint_engine_validation_check( true === Meta::save( $sprint, array( '_sprint_engine_completion_cta_label' => 'Next', '_sprint_engine_completion_cta_url' => $url, '_sprint_engine_completion_message' => "Done\n<b>Next</b>" ) ), 'Complete HTTP(S) CTA pair saves with plain multiline message.' );
    }
    $before = Meta::read( $sprint, 'sprint_engine_sprint' );
    $invalid_pairs = array(
        array( '_sprint_engine_completion_cta_label' => '' ), array( '_sprint_engine_completion_cta_url' => '' ),
        array( '_sprint_engine_completion_cta_label' => array() ), array( '_sprint_engine_completion_cta_label' => new stdClass() ),
    );
    foreach ( array( 'example.org', 'javascript:alert(1)', 'data:text/html,x', 'ftp://example.org', 'https://', 'https://bad host', 'https://example.org/<b>x</b>', array(), new stdClass() ) as $url ) { $invalid_pairs[] = array( '_sprint_engine_completion_cta_url' => $url ); }
    foreach ( $invalid_pairs as $pair ) {
        sprint_engine_validation_submit( $sprint, $pair );
        sprint_engine_validation_check( $before === Meta::read( $sprint, 'sprint_engine_sprint' ) && get_transient( 'sprint_engine_authoring_error_1_' . $sprint ), 'Invalid CTA retains the complete saved pair and other custom fields.' );
        sprint_engine_validation_check( 400 === sprint_engine_validation_rest( $sprint, $pair )->get_status() && $before === Meta::read( $sprint, 'sprint_engine_sprint' ), 'Partial native REST CTA validation uses saved counterpart without writes.' );
    }
    sprint_engine_validation_check( "Done\nNext" === $before['_sprint_engine_completion_message'], 'Completion message keeps plain multiline sanitization.' );
    sprint_engine_validation_check( true === Meta::save( $sprint, array( '_sprint_engine_completion_cta_label' => '', '_sprint_engine_completion_cta_url' => '' ) ), 'Both blank CTA fields intentionally disable CTA.' );
    foreach ( array( array( $sprint, '_sprint_engine_estimated_duration_unit', 'weeks' ), array( $sprint, '_sprint_engine_launchable', 'yes' ), array( $sprint, '_sprint_engine_launchable', null ), array( $sprint, '_sprint_engine_completion_message', array() ), array( $step, '_sprint_engine_mode', 'decision' ), array( $step, '_sprint_engine_sprint_id', $step ), array( $step, '_sprint_engine_stage_label', array() ) ) as $case ) {
        $old = Meta::read( $case[0], get_post_type( $case[0] ) );
        sprint_engine_validation_submit( $case[0], array( $case[1] => $case[2] ) );
        sprint_engine_validation_check( $old === Meta::read( $case[0], get_post_type( $case[0] ) ) && get_transient( 'sprint_engine_authoring_error_1_' . $case[0] ), 'Invalid authoring field fails visibly and retains previous state.' );
    }
    sprint_engine_validation_check( $revision === StructureManager::revision( $sprint ), 'Rejected custom input leaves start/position/next relationships intact.' );
    foreach ( $history as $table => $rows ) { sprint_engine_validation_check( $rows === $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}{$table} ORDER BY id", ARRAY_A ), 'Validation never changes ' . $table ); }
    update_option( RunnerBranding::OPTION, RunnerBranding::defaults() );
    foreach ( array( 'primary', 'primary_text', 'background', 'surface', 'text', 'muted' ) as $key ) {
        update_option( RunnerBranding::OPTION, RunnerBranding::sanitize( array( $key => '#Ab1234' ) ) );
        $GLOBALS['wp_settings_errors'] = array();
        $valid = RunnerBranding::sanitize( array( $key => array(), 'corner_style' => 'rounded' ) );
        sprint_engine_validation_check( '#ab1234' === $valid[$key] && 'rounded' === $valid['corner_style'] && str_contains( get_settings_errors( RunnerBranding::OPTION )[0]['message'], 'six-digit' ), 'Invalid colour retains prior value, names the problem and allows valid sibling.' );
    }
    $GLOBALS['wp_settings_errors'] = array();
    $valid = RunnerBranding::sanitize( array( 'primary' => '#ABCDEF', 'primary_text' => '' ) );
    sprint_engine_validation_check( '#abcdef' === $valid['primary'] && '' === $valid['primary_text'] && ! get_settings_errors( RunnerBranding::OPTION ), 'Valid uppercase colour and Automatic normalize without false errors.' );
    $GLOBALS['wp_settings_errors'] = array();
    $valid = RunnerBranding::sanitize( array( 'corner_style' => array() ) );
    sprint_engine_validation_check( $valid['corner_style'] === RunnerBranding::get()['corner_style'] && get_settings_errors( RunnerBranding::OPTION ), 'Invalid corner style preserves saved value and reports settings error.' );
} finally {
    $_POST = array();
    if ( null === $branding ) { delete_option( RunnerBranding::OPTION ); } else { update_option( RunnerBranding::OPTION, $branding ); }
    wp_delete_post( $step, true ); wp_delete_post( $sprint, true ); wp_delete_user( $member );
    foreach ( array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) as $table ) { $wpdb->delete( $wpdb->prefix . $table, array( 'sprint_id' => $sprint ), array( '%d' ) ); }
}
echo "SE-010.1 validation checks completed.\n";
