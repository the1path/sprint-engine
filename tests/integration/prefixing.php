<?php
/** WPORG-001 registration, storage and notice checks on disposable WordPress. */

$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
	exit( 1 );
}
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH . 'wp-admin/includes/screen.php';

function sprint_engine_prefix_check( $condition, $message ) {
	if ( ! $condition ) {
		throw new RuntimeException( $message );
	}
	echo 'PASS: ' . $message . PHP_EOL;
}

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Plugin;

global $wpdb;
wp_set_current_user( 1 );
sprint_engine_prefix_check( class_exists( Plugin::class ) && ! class_exists( 'SprintEngine\\Plugin' ), 'Vendor namespace loads without a pre-release alias.' );
foreach ( array( 'sprint_engine_sprint', 'sprint_engine_step' ) as $type ) {
	sprint_engine_prefix_check( strlen( $type ) <= 20 && post_type_exists( $type ), 'Canonical CPT registered: ' . $type );
	$meta = get_registered_meta_keys( 'post', $type );
	foreach ( Meta::defaults( $type ) as $key => $default ) {
		sprint_engine_prefix_check( str_starts_with( $key, '_sprint_engine_' ) && isset( $meta[ $key ] ), 'Canonical protected meta registered: ' . $key );
	}
}
sprint_engine_prefix_check( ! post_type_exists( 'se_sprint' ) && ! post_type_exists( 'se_step' ), 'Short CPT registrations are absent.' );
foreach ( array( 'enrolments', 'step_progress' ) as $suffix ) {
	$table = $wpdb->prefix . 'sprint_engine_' . $suffix;
	sprint_engine_prefix_check( $table === $wpdb->get_var( $wpdb->prepare( 'SHOW TABLES LIKE %s', $wpdb->esc_like( $table ) ) ), 'Canonical operational table exists: ' . $suffix );
}
sprint_engine_prefix_check( '0.2.0' === SPRINT_ENGINE_VERSION && '2' === SPRINT_ENGINE_SCHEMA_VERSION, 'Unreleased attempt version and schema are correct.' );
sprint_engine_prefix_check( has_action( 'wp_ajax_sprint_engine_structure' ) && ! has_action( 'wp_ajax_se_structure' ), 'Only canonical structure AJAX action is registered.' );
sprint_engine_prefix_check( has_action( 'admin_post_sprint_engine_reset_branding' ) && ! has_action( 'admin_post_se_reset_branding' ), 'Only canonical branding action is registered.' );
do_action( 'rest_api_init' );
$routes = rest_get_server()->get_routes();
sprint_engine_prefix_check( isset( $routes['/sprint-engine/v1/sprints/(?P<id>[^/]+)/start'] ), 'Compliant member REST namespace is retained.' );
sprint_engine_prefix_check( isset( $routes['/sprint-engine/v1/sprints/(?P<id>[^/]+)/restart'] ), 'Restart uses the compliant member REST namespace.' );

// Exercise actual notice output on related/unrelated screens and capabilities.
$old_failure = get_option( 'sprint_engine_installation_failed', false );
update_option( 'sprint_engine_installation_failed', true );
$plugin = new Plugin();
foreach ( array( 'dashboard' => false, 'edit-post' => false, 'edit-page' => false, 'plugins' => true, 'sprint_engine_sprint' => true, 'sprint_engine_step' => true, 'sprint_engine_sprint_page_sprint-engine-settings' => true ) as $screen => $expected ) {
	set_current_screen( $screen );
	ob_start();
	$plugin->installation_notice();
	$output = ob_get_clean();
	sprint_engine_prefix_check( ( '' !== $output ) === $expected, 'Installation notice scope: ' . $screen );
}
wp_set_current_user( 0 );
set_current_screen( 'plugins' );
ob_start();
$plugin->installation_notice();
sprint_engine_prefix_check( '' === ob_get_clean(), 'Installation notice requires administrator capability.' );
wp_set_current_user( 1 );
delete_option( 'sprint_engine_installation_failed' );
ob_start();
$plugin->installation_notice();
sprint_engine_prefix_check( '' === ob_get_clean(), 'Installation notice clears after recovery.' );
if ( false !== $old_failure ) {
	update_option( 'sprint_engine_installation_failed', $old_failure );
}
echo "WPORG-001 integration checks completed.\n";

sprint_engine_prefix_check(in_array('sprint_engine_dashboard', apply_filters('query_vars', array()), true), 'Namespaced Dashboard query variable registered.');
sprint_engine_prefix_check(!isset($routes['/sprint-engine/v1/dashboard']) && !isset($routes['/sprint-engine/v1/attempts']) && !isset($routes['/sprint-engine/v1/sprints']) && !isset($routes['/sprint-engine/v1/history']), 'Dashboard adds no public REST read or history endpoint.');
