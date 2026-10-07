<?php
/**
 * SE-001 integration checks against a disposable WordPress installation.
 *
 * Run: SE_TEST_WP_ROOT=/path/to/wordpress php tests/integration/foundation.php
 * WARNING: creates/deletes Sprint Engine tables. Never use a real site's database.
 *
 * @package SprintEngine
 */

$wordpress = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $wordpress || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
	fwrite( STDERR, "Set SE_TEST_WP_ROOT and SE_TEST_DISPOSABLE=yes for a disposable test site.\n" );
	exit( 1 );
}

require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';

error_reporting( E_ALL );
set_error_handler(
	static function ( $severity, $message, $file, $line ) {
		throw new ErrorException( $message, 0, $severity, $file, $line );
	}
);

/**
 * Fail independently of PHP's assertion configuration.
 *
 * @param bool   $condition Expected truthy result.
 * @param string $message   Check description.
 */
function sprint_engine_test_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}

global $wpdb;
sprint_engine_test_check( 'wp_' !== $wpdb->prefix, 'Test uses a non-default table prefix.' );
$plugin = 'sprint-engine/sprint-engine.php';
wp_set_current_user( 1 );
ob_start();
$result = activate_plugin( $plugin );
$output = ob_get_clean();
sprint_engine_test_check( ! is_wp_error( $result ) && '' === $output, 'Fresh activation produces no output, warnings, notices, or fatal errors.' );
sprint_engine_test_check( SPRINT_ENGINE_VERSION === get_option( 'sprint_engine_version' ), 'Plugin version stored.' );
sprint_engine_test_check( SPRINT_ENGINE_SCHEMA_VERSION === get_option( 'sprint_engine_schema_version' ), 'Schema version stored separately.' );

foreach ( array( 'sprint_engine_sprint', 'sprint_engine_step' ) as $type ) {
	$post_type = get_post_type_object( $type );
	sprint_engine_test_check( $post_type && $post_type->show_ui && $post_type->show_in_rest && ! $post_type->publicly_queryable && ! $post_type->rewrite, $type . ' is private and available to the block editor.' );
	sprint_engine_test_check( post_type_supports( $type, 'editor' ), $type . ' supports native blocks.' );
}

$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_title' => 'Foundation test', 'post_status' => 'publish' ), true );
$step   = wp_insert_post( array( 'post_type' => 'sprint_engine_step', 'post_title' => 'Private step', 'post_content' => '<!-- wp:paragraph --><p>Private content</p><!-- /wp:paragraph -->', 'post_status' => 'publish' ), true );
sprint_engine_test_check( ! is_wp_error( $sprint ) && ! is_wp_error( $step ), 'Administrator can create Sprint and Step content.' );

$enrolments = $wpdb->prefix . 'sprint_engine_enrolments';
$progress   = $wpdb->prefix . 'sprint_engine_step_progress';
$now        = gmdate( 'Y-m-d H:i:s' );
$enrolment  = array( 'user_id' => 1, 'sprint_id' => $sprint, 'created_at' => $now, 'updated_at' => $now );
$completed  = array( 'user_id' => 1, 'sprint_id' => $sprint, 'step_id' => $step, 'status' => 'completed', 'completed_at' => $now, 'updated_at' => $now );
sprint_engine_test_check( 1 === $wpdb->insert( $enrolments, $enrolment ), 'Enrolment table accepts a row with nullable timestamps and current Step.' );
sprint_engine_test_check( 1 === $wpdb->insert( $progress, $completed ), 'Progress table accepts completed progress.' );
$previous = $wpdb->suppress_errors( true );
sprint_engine_test_check( false === $wpdb->insert( $enrolments, $enrolment ), 'Unique enrolment key rejects duplicates.' );
sprint_engine_test_check( false === $wpdb->insert( $progress, $completed ), 'Unique progress key rejects duplicates.' );
$wpdb->suppress_errors( $previous );

$before_enrolments = $wpdb->get_results( "SELECT * FROM $enrolments", ARRAY_A );
$before_progress   = $wpdb->get_results( "SELECT * FROM $progress", ARRAY_A );
sprint_engine_test_check( ( new ThePath\SprintEngine\Database\Installer() )->install(), 'Repeat installation succeeds.' );
foreach ( ThePath\SprintEngine\Database\Migrations::version_two() as $sql ) {
	sprint_engine_test_check( array() === dbDelta( $sql ), 'Second dbDelta pass needs no schema changes.' );
}
deactivate_plugins( $plugin );
sprint_engine_test_check( null === get_post_type_object( 'sprint_engine_step' ), 'Deactivation unregisters in-memory content structures.' );
ob_start();
$result = activate_plugin( $plugin );
$output = ob_get_clean();
sprint_engine_test_check( ! is_wp_error( $result ) && '' === $output, 'Reactivation is clean.' );
sprint_engine_test_check( $before_enrolments === $wpdb->get_results( "SELECT * FROM $enrolments", ARRAY_A ), 'Reactivation preserves enrolments exactly.' );
sprint_engine_test_check( $before_progress === $wpdb->get_results( "SELECT * FROM $progress", ARRAY_A ), 'Reactivation preserves progress exactly.' );
sprint_engine_test_check( null !== get_post( $sprint ) && null !== get_post( $step ), 'Deactivation/reactivation preserves content.' );

$schema_queries = 0;
$count_schema   = static function ( $query ) use ( &$schema_queries ) {
	if ( preg_match( '/^(CREATE|ALTER) TABLE/i', $query ) ) {
		++$schema_queries;
	}
	return $query;
};
add_filter( 'query', $count_schema );
( new ThePath\SprintEngine\Plugin() )->maybe_upgrade();
remove_filter( 'query', $count_schema );
sprint_engine_test_check( 0 === $schema_queries, 'Normal requests do not rerun schema installation.' );
update_option( 'sprint_engine_version', '0.0.0' );
( new ThePath\SprintEngine\Plugin() )->maybe_upgrade();
sprint_engine_test_check( SPRINT_ENGINE_VERSION === get_option( 'sprint_engine_version' ), 'Version changes trigger installation without reactivation.' );

$die_handler = static function () {
	return static function ( $message ) {
		throw new RuntimeException( $message );
	};
};
add_filter( 'wp_die_handler', $die_handler );
$original_wp_version = $wp_version;
$wp_version          = '4.9';
try {
	ThePath\SprintEngine\Plugin::activate();
	throw new LogicException( 'Unsupported WordPress activation was allowed.' );
} catch ( RuntimeException $exception ) {
	sprint_engine_test_check( false !== strpos( $exception->getMessage(), 'requires PHP' ), 'Unsupported WordPress receives an actionable compatibility message.' );
} finally {
	$wp_version = $original_wp_version;
}
try {
	ThePath\SprintEngine\Plugin::activate( true );
	throw new LogicException( 'Network activation was allowed.' );
} catch ( RuntimeException $exception ) {
	sprint_engine_test_check( false !== strpos( $exception->getMessage(), 'individual sites' ), 'Network activation fails safely before partial provisioning.' );
}
remove_filter( 'wp_die_handler', $die_handler );

$server = rest_get_server();
foreach ( array( 0, wp_create_user( 'foundation-' . wp_generate_password( 12, false ), 'test-only-password' ), 1 ) as $user_id ) {
	wp_set_current_user( $user_id );
	foreach ( array( '/wp/v2/sprint_engine_sprint', '/wp/v2/sprint_engine_sprint/' . $sprint, '/wp/v2/sprint_engine_step', '/wp/v2/sprint_engine_step/' . $step ) as $route ) {
		$response = $server->dispatch( new WP_REST_Request( 'GET', $route ) );
		sprint_engine_test_check( 1 === $user_id ? 200 === $response->get_status() : in_array( $response->get_status(), array( 401, 403 ), true ), 'Native REST authoring is administrator-only: user ' . $user_id . ', ' . $route );
	}
}
wp_set_current_user( 1 );

// A failed upgrade must not record a new version or erase surviving data.
$wpdb->query( "DROP TABLE $progress" );
delete_option( 'sprint_engine_schema_version' );
$block_create = static function ( $query ) use ( $progress ) {
	return 0 === strpos( $query, 'CREATE TABLE ' . $progress ) ? 'INVALID SE TEST SQL' : $query;
};
add_filter( 'query', $block_create );
sprint_engine_test_check( ! ( new ThePath\SprintEngine\Database\Installer() )->install(), 'A database installation failure is reported.' );
remove_filter( 'query', $block_create );
sprint_engine_test_check( false === get_option( 'sprint_engine_schema_version' ), 'Failed migration does not advance schema version.' );
sprint_engine_test_check( (bool) get_option( 'sprint_engine_installation_failed' ), 'Failed migration records an administrator notice.' );
sprint_engine_test_check( ( new ThePath\SprintEngine\Database\Installer() )->install(), 'Installation recovers after database failure.' );
sprint_engine_test_check( false === get_option( 'sprint_engine_installation_failed' ), 'Successful retry clears the failure notice.' );
sprint_engine_test_check( $before_enrolments === $wpdb->get_results( "SELECT * FROM $enrolments", ARRAY_A ), 'Recovery preserves surviving enrolments.' );

update_option( 'sprint_engine_schema_version', '999' );
sprint_engine_test_check( ! ( new ThePath\SprintEngine\Database\Installer() )->install(), 'A newer schema cannot be silently downgraded.' );
sprint_engine_test_check( '999' === get_option( 'sprint_engine_schema_version' ), 'Newer schema marker is retained.' );
update_option( 'sprint_engine_schema_version', SPRINT_ENGINE_SCHEMA_VERSION );
( new ThePath\SprintEngine\Database\Installer() )->install();

define( 'WP_UNINSTALL_PLUGIN', $plugin );
require WP_PLUGIN_DIR . '/sprint-engine/uninstall.php';
sprint_engine_test_check( $before_enrolments === $wpdb->get_results( "SELECT * FROM $enrolments", ARRAY_A ) && null !== get_post( $step ), 'Uninstall retains content and operational data.' );
echo "SE-001 integration checks completed.\n";
