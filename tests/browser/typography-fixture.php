<?php
/** Disposable SE-024 browser fixtures. */

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Dashboard\Routes as DashboardRoutes;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Runner\Routes as RunnerRoutes;
use ThePath\SprintEngine\Settings\RunnerBranding;

$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
    exit( 1 );
}
require $root . '/wp-load.php';

function typography_assert( $condition, $message ) {
    if ( ! $condition || is_wp_error( $condition ) ) {
        throw new RuntimeException( $message );
    }
    return $condition;
}

function typography_snapshot() {
    global $wpdb;
    $attempts = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id", ARRAY_A );
    foreach ( $attempts as &$attempt ) {
        if ( 'in_progress' === $attempt['status'] ) {
            // Opening an active Runner normally refreshes these two resume timestamps.
            unset( $attempt['last_activity_at'], $attempt['updated_at'] );
        }
    }
    unset( $attempt );
    return array(
        'attempts' => $attempts,
        'progress' => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id", ARRAY_A ),
        'version'  => get_option( 'sprint_engine_version' ),
        'schema'   => get_option( 'sprint_engine_schema_version' ),
        'rewrite'  => get_option( 'rewrite_rules' ),
    );
}

wp_set_current_user( 1 );
$modern_theme = wp_get_theme( 'twentytwentyfive' )->exists();
$block_theme = $modern_theme ? 'twentytwentyfive' : 'twentytwentytwo';
$mode = $argv[1] ?? 'create';
if ( 'classic' === $mode ) {
    typography_assert( wp_get_theme( 'twentytwentyone' )->exists(), 'Twenty Twenty-One fixture theme is missing.' );
    switch_theme( 'twentytwentyone' );
    exit;
}
if ( 'verify' === $mode ) {
    $fixture = json_decode( file_get_contents( $argv[2] ), true, 512, JSON_THROW_ON_ERROR );
    typography_assert( $fixture['snapshot'] === typography_snapshot(), 'Typography changed progress, attempt identity, versions or rewrites.' );
    typography_assert( false === get_option( RunnerBranding::OPTION ), 'Reset should delete the branding option.' );
    echo "PASS: Branding reset, participant rows, attempt identity, versions and rewrites.\n";
    exit;
}

switch_theme( $block_theme );
if ( ! $modern_theme ) {
    // Exercise a saved global body selection and the WP 6.0 preset syntax.
    $styles_id = WP_Theme_JSON_Resolver::get_user_global_styles_post_id();
    typography_assert( wp_update_post( array(
        'ID' => $styles_id,
        'post_content' => wp_json_encode( array(
            'version' => 2,
            'isGlobalStylesUserThemeJSON' => true,
            'styles' => array( 'typography' => array( 'fontFamily' => 'var(--wp--preset--font-family--source-serif-pro)' ) ),
        ) ),
    ), true ), 'Legacy Global Styles fixture failed.' );
}

// Start with a real pre-typography option row.
update_option( RunnerBranding::OPTION, array( 'primary' => '#482070', 'corner_style' => 'rounded' ) );
$member = typography_assert( wp_insert_user( array( 'user_login' => 'typography-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) ), 'Member fixture failed.' );
$manager = new StructureManager();
$service = new ProgressService();
$preset = $modern_theme ? 'fira-code' : 'system-font';
$content = <<<HTML
<!-- wp:paragraph -->
<p id="typography-plain">Save your place and return when you are ready to practise the next Step.</p>
<!-- /wp:paragraph -->
<!-- wp:heading {"fontFamily":"$preset"} -->
<h2 class="wp-block-heading has-$preset-font-family" id="typography-preset">A heading with its own font</h2>
<!-- /wp:heading -->
<!-- wp:buttons -->
<div class="wp-block-buttons"><!-- wp:button {"style":{"typography":{"fontFamily":"Georgia, serif"}}} -->
<div class="wp-block-button" style="font-family:Georgia,serif"><a class="wp-block-button__link wp-element-button" id="typography-authored-link" href="#typography-preset">An authored block button</a></div>
<!-- /wp:button --></div>
<!-- /wp:buttons -->
HTML;

$urls = array();
foreach ( array( 'welcome' => 'Plan the next week', 'active' => 'Find time for the work that matters', 'complete' => 'Reflect on the change' ) as $key => $title ) {
    $sprint = typography_assert( wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => $title ), true ), 'Sprint fixture failed.' );
    $step = typography_assert( $manager->quick_add( $sprint, 'Make the first change' ), 'Step fixture failed.' );
    typography_assert( wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'post_content' => $content ), true ), 'Step publication failed.' );
    Meta::save( $step, array( '_sprint_engine_stage_label' => 'Getting started' ) );
    Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
    $urls[ $key ] = RunnerRoutes::url( get_post_field( 'post_name', $sprint ) );
    if ( 'welcome' !== $key ) {
        $state = typography_assert( $service->start_sprint( $member, $sprint ), 'Fixture attempt failed.' );
        if ( 'complete' === $key ) {
            typography_assert( $service->complete_step( $member, $sprint, $step, $state['attempt_id'] ), 'Fixture completion failed.' );
        } else {
            $urls['editor'] = admin_url( 'post.php?post=' . $step . '&action=edit' );
        }
    }
}

$cookies = static function ( $user ) {
    $expires = time() + HOUR_IN_SECONDS;
    $token = WP_Session_Tokens::get_instance( $user )->create( $expires );
    return array(
        array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user, $expires, 'logged_in', $token ), 'url' => home_url( '/' ) ),
        array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $user, $expires, 'auth', $token ), 'url' => home_url( '/' ) ),
    );
};
echo wp_json_encode( array(
    'urls' => $urls + array(
        'dashboard' => DashboardRoutes::url(),
        'settings' => admin_url( 'edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings' ),
        'home' => home_url( '/' ),
    ),
    'admin' => $cookies( 1 ),
    'member' => $cookies( $member ),
    'websiteFont' => $modern_theme ? 'Manrope' : 'Source Serif 4',
    'preset' => $preset,
    'snapshot' => typography_snapshot(),
) );
