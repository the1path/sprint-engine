<?php
/** Disposable browser authoring fixture. Never use against an authored site. */
$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require $root . '/wp-load.php';
wp_set_current_user( 1 );
$classic_fixture = WPMU_PLUGIN_DIR . '/se-authoring-browser-fixture.php';
if ( isset( $argv[1] ) ) {
    $fixture = json_decode( file_get_contents( $argv[1] ), true );
    foreach ( ThePath\SprintEngine\Content\Meta::steps( $fixture['sprint'] ) as $step ) { wp_delete_post( $step->ID, true ); }
    if ( isset( $fixture['removed'] ) ) { wp_delete_post( $fixture['removed'], true ); }
    wp_delete_post( $fixture['sprint'], true );
    if ( is_file( $classic_fixture ) ) { unlink( $classic_fixture ); }
    exit;
}
if ( ! is_dir( WPMU_PLUGIN_DIR ) ) { mkdir( WPMU_PLUGIN_DIR ); }
file_put_contents( $classic_fixture, '<?php add_filter("use_block_editor_for_post", static function ($use) { return isset($_GET["sprint_engine_test_classic"]) ? false : $use; });' );
echo wp_json_encode( array(
    'sprint' => 0,
    'url' => admin_url( 'post-new.php?post_type=sprint_engine_sprint' ),
    'cookies' => array(
        array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( 1, time() + 3600, 'logged_in' ), 'url' => home_url( '/' ) ),
        array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( 1, time() + 3600, 'auth' ), 'url' => home_url( '/' ) ),
    ),
) );
