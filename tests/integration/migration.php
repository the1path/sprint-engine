<?php
/** SE-012 genuine v1 upgrade, partial DDL retry and fresh v2 disposable checks. */
use ThePath\SprintEngine\Database\Installer;
use ThePath\SprintEngine\Database\Migrations;
use ThePath\SprintEngine\Plugin;
use ThePath\SprintEngine\Dashboard\Routes as DashboardRoutes;
use ThePath\SprintEngine\Runner\Routes as RunnerRoutes;

$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0;
function migration_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks; echo "PASS: $message\n";
}
wp_set_current_user( 1 );
$manager = new ThePath\SprintEngine\Content\StructureManager();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Legacy upgrade fixture' ) );
$steps = array( $manager->quick_add( $sprint, 'Legacy first' ), $manager->quick_add( $sprint, 'Legacy second' ) );
foreach ( $steps as $published_step ) { wp_update_post( array( 'ID' => $published_step, 'post_status' => 'publish' ) ); }
ThePath\SprintEngine\Content\Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
$second = wp_create_user( 'migration-' . wp_generate_password( 12, false ), wp_generate_password() );
$users = array( 1, $second );
$prefix = $wpdb->prefix;
$schema = get_option( 'sprint_engine_schema_version' );
$version = get_option( 'sprint_engine_version' );
$branding = get_option( 'sprint_engine_runner_branding' );
$saved_rewrite = clone $wp_rewrite;
$saved_rules = get_option( 'rewrite_rules' );
$saved_permalinks = get_option( 'permalink_structure' );
$content = $wpdb->get_results( "SELECT * FROM $wpdb->posts ORDER BY ID", ARRAY_A );
$metadata = $wpdb->get_results( "SELECT * FROM $wpdb->postmeta ORDER BY meta_id", ARRAY_A );
// Isolated owned tables on the same disposable backend; core table properties stay intact.
$wpdb->prefix = $prefix . 'se012_' . strtolower( wp_generate_password( 6, false ) ) . '_';
$enrolments = $wpdb->prefix . 'sprint_engine_enrolments';
$progress = $wpdb->prefix . 'sprint_engine_step_progress';
try {
	migration_check( ( new Installer() )->install(), 'Fresh v2 install succeeds with non-default prefix.' );
	foreach ( Migrations::version_two() as $sql ) { migration_check( array() === dbDelta( $sql, false ), 'Fresh schema includes expected columns/defaults/indexes and repeat has no changes.' ); }
	$wpdb->query( "DROP TABLE $progress" );
	$wpdb->query( "DROP TABLE $enrolments" );
	foreach ( Migrations::version_one() as $sql ) { dbDelta( $sql ); }
	update_option( 'sprint_engine_schema_version', '1' );
	update_option( 'sprint_engine_version', '0.1.0' );
	// Genuine old public rewrites: Runner present, Dashboard absent before upgrade.
	$wp_rewrite->set_permalink_structure( '/%postname%/' );
	DashboardRoutes::unregister();
	RunnerRoutes::register();
	flush_rewrite_rules( false );
	$old_rules = get_option( 'rewrite_rules' );
	migration_check( isset( $old_rules[RunnerRoutes::RULE] ) && ! isset( $old_rules[DashboardRoutes::RULE] ), 'Corrected 0.1 fixture has Runner rewrites but no Dashboard route.' );
	// Observe actual flush calls and actual regeneration, not just version markers.
	$rewrite_spy = new class() extends WP_Rewrite {
		public $sprint_engine_flushes = array();
		public function flush_rules( $hard = true ) {
			$this->sprint_engine_flushes[] = $hard;
			parent::flush_rules( $hard );
		}
	};
	foreach ( get_object_vars( $wp_rewrite ) as $key => $value ) { $rewrite_spy->$key = $value; }
	$wp_rewrite = $rewrite_spy;
	$generations = 0;
	$count_generation = static function () use ( &$generations ) { ++$generations; };
	add_action( 'generate_rewrite_rules', $count_generation );
	$now = '2025-01-02 03:04:05';
	foreach ( array( 'completed', 'in_progress' ) as $index => $status ) {
		$wpdb->insert( $enrolments, array( 'user_id' => $users[$index], 'sprint_id' => $sprint, 'status' => $status, 'current_step_id' => $index ? $steps[1] : null, 'started_at' => $now, 'last_activity_at' => $now, 'completed_at' => $index ? null : $now, 'created_at' => $now, 'updated_at' => $now ) );
		$wpdb->insert( $progress, array( 'user_id' => $users[$index], 'sprint_id' => $sprint, 'step_id' => $steps[0], 'status' => 'completed', 'started_at' => $now, 'completed_at' => $now, 'updated_at' => $now ) );
	}
	$wpdb->insert( $progress, array( 'user_id' => 1, 'sprint_id' => $sprint, 'step_id' => $steps[1], 'status' => 'completed', 'started_at' => $now, 'completed_at' => $now, 'updated_at' => $now ) );
	$wpdb->insert( $progress, array( 'user_id' => $second, 'sprint_id' => $sprint, 'step_id' => $steps[1], 'status' => 'started', 'started_at' => $now, 'updated_at' => $now ) );
	$tables = array( $enrolments, $progress );
	$before = array();
	foreach ( $tables as $table ) { $before[$table] = $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A ); }
	$block = static function ( $sql ) use ( $progress ) {
		return str_starts_with( $sql, 'ALTER TABLE' ) && str_contains( $sql, $progress ) && str_contains( $sql, 'DROP INDEX' ) ? 'INVALID SE012 DDL' : $sql;
	};
	add_filter( 'query', $block );
	( new Plugin() )->maybe_upgrade();
	migration_check( get_option( 'sprint_engine_installation_failed' ), 'Simulated index removal failure is reported after partial additive DDL.' );
	remove_filter( 'query', $block );
	migration_check( array() === $rewrite_spy->sprint_engine_flushes && 0 === $generations && $old_rules === get_option( 'rewrite_rules' ), 'Failed installation performs no rewrite flush/regeneration and retains old rules.' );
	migration_check( '1' === get_option( 'sprint_engine_schema_version' ) && '0.1.0' === get_option( 'sprint_engine_version' ), 'Failure retains prior schema/plugin markers.' );
	$blocked_state = ( new ThePath\SprintEngine\Progress\ProgressService() )->get_state( 1, $sprint );
	migration_check( is_wp_error( $blocked_state ) && 'sprint_engine_progress_persistence' === $blocked_state->get_error_code(), 'Progress fails safely during partial migration.' );
	foreach ( $tables as $table ) {
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A );
		foreach ( $rows as &$row ) { migration_check( 1 === (int) $row['attempt_number'], 'Legacy row becomes Attempt 1.' ); unset( $row['attempt_number'] ); } unset( $row );
		migration_check( $before[$table] === $rows, 'Partial migration preserves every ID/status/pointer/timestamp in ' . $table );
	}
	( new Plugin() )->maybe_upgrade();
	migration_check( ! get_option( 'sprint_engine_installation_failed' ) && '2' === get_option( 'sprint_engine_schema_version' ) && '0.2.0' === get_option( 'sprint_engine_version' ), 'Actual maybe_upgrade retry completes safely and records 0.2.0/schema 2 only after verification.' );
	migration_check( array( false ) === $rewrite_spy->sprint_engine_flushes && 1 === $generations, 'Successful version upgrade invokes exactly one soft flush and regenerates rewrites once.' );
	$new_rules = get_option( 'rewrite_rules' );
	migration_check( isset( $new_rules[RunnerRoutes::RULE], $new_rules[DashboardRoutes::RULE] ), 'Version upgrade preserves Runner and installs Dashboard rewrites without activation.' );
	migration_check( post_type_exists( 'sprint_engine_sprint' ) && post_type_exists( 'sprint_engine_step' ), 'Upgrade makes both content registrations available before refresh.' );
	// Parse the canonical URL using real WordPress persisted rewrites; no manual flush.
	$_SERVER['REQUEST_URI'] = wp_parse_url( DashboardRoutes::url(), PHP_URL_PATH );
	$_SERVER['PHP_SELF'] = '/index.php';
	$_SERVER['PATH_INFO'] = '';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_GET = array();
	$wp->matched_rule = ''; $wp->matched_query = '';
	$wp->parse_request();
	migration_check( DashboardRoutes::matches() && 1 === $wp->query_vars[DashboardRoutes::QUERY_VAR] && array(0) === $wp->query_vars['post__in'], 'Canonical Dashboard resolves through WordPress immediately after upgrade, without activation or Permalinks save.' );
	$dashboard = ( new ThePath\SprintEngine\Dashboard\Dashboard() )->resolve();
	migration_check( 200 === $dashboard['status'] && in_array( $sprint, array_column( $dashboard['sections']['completed'], 'sprint_id' ), true ), 'Resolved post-upgrade Dashboard reads preserved completed member progress.' );
	( new Plugin() )->maybe_upgrade();
	( new Plugin() )->maybe_upgrade();
	migration_check( array( false ) === $rewrite_spy->sprint_engine_flushes && 1 === $generations && $new_rules === get_option( 'rewrite_rules' ), 'Subsequent ordinary upgrade checks cause zero additional flushes/regenerations.' );
	// Schema-only retries preserve their install behaviour without version-based flush.
	update_option( 'sprint_engine_schema_version', '1' );
	( new Plugin() )->maybe_upgrade();
	migration_check( '2' === get_option( 'sprint_engine_schema_version' ) && array( false ) === $rewrite_spy->sprint_engine_flushes && 1 === $generations, 'Schema-only installation succeeds without another version-triggered rewrite flush.' );
	$service = new ThePath\SprintEngine\Progress\ProgressService();
	$completed_state = $service->get_state( 1, $sprint );
	$active_state = $service->get_state( $second, $sprint );
	migration_check( 'completed' === $completed_state['status'] && 100.0 === $completed_state['percentage'] && 1 === $completed_state['attempt_number'] && null === $completed_state['current_step_id'], 'Migrated completed member stays completed at 100 percent on Attempt 1.' );
	migration_check( 'in_progress' === $active_state['status'] && 50.0 === $active_state['percentage'] && $steps[1] === $active_state['current_step_id'] && $now === $active_state['started_at'], 'Migrated active member retains resume pointer, original timestamp and 50 percent progress.' );
	foreach ( $tables as $table ) {
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A );
		foreach ( $rows as &$row ) { unset( $row['attempt_number'] ); } unset( $row );
		migration_check( $before[$table] === $rows, 'Successful upgrade preserves all original fields in ' . $table );
		$new = $before[$table][0]; unset( $new['id'] ); $new['attempt_number'] = 2;
		migration_check( 1 === $wpdb->insert( $table, $new ), 'Old unique index no longer blocks Attempt 2.' );
		$previous = $wpdb->suppress_errors( true );
		migration_check( false === $wpdb->insert( $table, $new ), 'New unique index rejects duplicate within one attempt.' );
		$wpdb->suppress_errors( $previous );
	}
	$after = array(); foreach ( $tables as $table ) { $after[$table] = $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A ); }
	migration_check( ( new Installer() )->install(), 'Repeated migration succeeds.' );
	foreach ( $tables as $table ) { migration_check( $after[$table] === $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A ), 'Repeat installation does not mutate data.' ); }
	migration_check( $branding === get_option( 'sprint_engine_runner_branding' ) && $content === $wpdb->get_results( "SELECT * FROM $wpdb->posts ORDER BY ID", ARRAY_A ) && $metadata === $wpdb->get_results( "SELECT * FROM $wpdb->postmeta ORDER BY meta_id", ARRAY_A ), 'Migration preserves settings and exact authored content/metadata.' );
	// Also exercise an ordinary upgrade from pristine v1, without an injected failure.
	foreach ( $tables as $table ) { $wpdb->query( "DROP TABLE $table" ); }
	foreach ( Migrations::version_one() as $sql ) { dbDelta( $sql ); }
	foreach ( $before as $table => $rows ) { foreach ( $rows as $row ) { $wpdb->insert( $table, $row ); } }
	update_option( 'sprint_engine_schema_version', '1' );
	update_option( 'sprint_engine_version', '0.1.0' );
	DashboardRoutes::unregister(); RunnerRoutes::register();
	flush_rewrite_rules( false ); // Old-version fixture setup only, before upgrade.
	$rewrite_spy->sprint_engine_flushes = array(); $generations = 0;
	$old_rules = get_option( 'rewrite_rules' );
	migration_check( isset( $old_rules[RunnerRoutes::RULE] ) && ! isset( $old_rules[DashboardRoutes::RULE] ), 'Pristine v1 content/progress schema and Runner-only rewrites are established.' );
	( new Plugin() )->maybe_upgrade();
	migration_check( '0.2.0' === get_option( 'sprint_engine_version' ) && '2' === get_option( 'sprint_engine_schema_version' ) && ! get_option( 'sprint_engine_installation_failed' ), 'Ordinary pristine 0.1.0 to 0.2.0 upgrade succeeds through maybe_upgrade without activation.' );
	migration_check( array( false ) === $rewrite_spy->sprint_engine_flushes && 1 === $generations, 'Pristine version upgrade performs exactly one soft flush and real regeneration.' );
	$wp->matched_rule = ''; $wp->matched_query = ''; $wp->parse_request();
	$dashboard = ( new ThePath\SprintEngine\Dashboard\Dashboard() )->resolve();
	migration_check( DashboardRoutes::matches() && isset( get_option( 'rewrite_rules' )[RunnerRoutes::RULE] ) && 200 === $dashboard['status'], 'Dashboard canonical path and member state work immediately after pristine upgrade with Runner retained.' );
	foreach ( $tables as $table ) {
		$rows = $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A );
		$attempts = array_unique( array_map( 'intval', array_column( $rows, 'attempt_number' ) ) );
		foreach ( $rows as &$row ) { unset( $row['attempt_number'] ); } unset( $row );
		migration_check( array( 1 ) === $attempts && $before[$table] === $rows, 'Pristine upgrade changes only intended Attempt-1 schema fields; all original progress data retained.' );
	}
	( new Plugin() )->maybe_upgrade();
	migration_check( array( false ) === $rewrite_spy->sprint_engine_flushes && 1 === $generations && $content === $wpdb->get_results( "SELECT * FROM $wpdb->posts ORDER BY ID", ARRAY_A ) && $metadata === $wpdb->get_results( "SELECT * FROM $wpdb->postmeta ORDER BY meta_id", ARRAY_A ), 'Pristine-upgrade ordinary request does not reflush and preserves authored content/metadata.' );
} finally {
	if ( isset( $count_generation ) ) { remove_action( 'generate_rewrite_rules', $count_generation ); }
	$wp_rewrite = $saved_rewrite;
	update_option( 'rewrite_rules', $saved_rules );
	update_option( 'permalink_structure', $saved_permalinks );
	$wpdb->query( "DROP TABLE IF EXISTS $progress" );
	$wpdb->query( "DROP TABLE IF EXISTS $enrolments" );
	$wpdb->prefix = $prefix;
	update_option( 'sprint_engine_schema_version', $schema );
	update_option( 'sprint_engine_version', $version );
	delete_option( 'sprint_engine_installation_failed' );
}
require_once ABSPATH . '/wp-admin/includes/user.php';
wp_delete_user( $second );
foreach ( array_merge( $steps, array( $sprint ) ) as $id ) { wp_delete_post( $id, true ); }
echo "SE-012 migration completed: $checks assertions.\n";
