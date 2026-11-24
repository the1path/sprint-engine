<?php
/** SE-030 acceptance; run only against a fresh disposable, inactive Core site. */
use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Dashboard\Dashboard;
use ThePath\SprintEngine\Plugin;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Tutorial\Content;
use ThePath\SprintEngine\Tutorial\Installer;
use ThePath\SprintEngine\Tutorial\Marker;
use ThePath\SprintEngine\Tutorial\Onboarding;

if ( ! getenv( 'SE_TEST_WP_ROOT' ) || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
ob_start();
$_SERVER['REQUEST_METHOD'] = 'GET';
require getenv( 'SE_TEST_WP_ROOT' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );

if ( 'worker' === ( $argv[1] ?? '' ) ) {
	wp_set_current_user( 1 );
	while ( microtime( true ) < (float) $argv[2] ) { usleep( 10000 ); }
	$tutorial_id = ( new Installer() )->install();
	if ( is_wp_error( $tutorial_id ) ) { throw new RuntimeException( $tutorial_id->get_error_message() ); }
	echo json_encode( $tutorial_id );
	exit;
}

$checks = 0;
function tutorial_check( $ok, $message ) {
	global $checks;
	if ( ! $ok ) { throw new RuntimeException( $message ); }
	++$checks;
	echo "PASS: $message\n";
}
function tutorial_posts() {
	global $wpdb;
	return $wpdb->get_results( "SELECT ID, post_title, post_status, post_content FROM $wpdb->posts WHERE post_type IN ('sprint_engine_sprint','sprint_engine_step') ORDER BY ID", ARRAY_A );
}
function tutorial_rows() {
	global $wpdb;
	return array_map( static function ( $table ) use ( $wpdb ) { return $wpdb->get_results( "SELECT * FROM $table ORDER BY id", ARRAY_A ); }, array( $wpdb->prefix . 'sprint_engine_enrolments', $wpdb->prefix . 'sprint_engine_step_progress' ) );
}
function tutorial_request( $resource, $tutorial_id, $operation, $attempt = null ) {
	$request = new WP_REST_Request( 'POST', '/sprint-engine/v1/' . $resource . '/' . $tutorial_id . '/' . $operation );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) );
	if ( null !== $attempt ) { $request->set_param( 'expected_attempt_id', $attempt ); }
	return rest_get_server()->dispatch( $request );
}
function tutorial_control( $method, $choice, $nonce ) {
	$_SERVER['REQUEST_METHOD'] = $method;
	$_POST = array( 'choice' => $choice );
	$_REQUEST = array( '_wpnonce' => $nonce );
	$die = static function () { return static function ( $message ) { throw new RuntimeException( 'DIED: ' . $message ); }; };
	$redirect = static function () { throw new RuntimeException( 'REDIRECTED' ); };
	add_filter( 'wp_die_handler', $die );
	add_filter( 'wp_redirect', $redirect );
	try { ( new Onboarding() )->handle(); } catch ( RuntimeException $exception ) { return $exception->getMessage(); }
	finally { remove_filter( 'wp_die_handler', $die ); remove_filter( 'wp_redirect', $redirect ); $_POST = array(); $_REQUEST = array(); $_SERVER['REQUEST_METHOD'] = 'GET'; }
	return 'Unexpected completion';
}

wp_set_current_user( 1 );
tutorial_check( 'wp_' !== $wpdb->prefix && array() === tutorial_posts(), 'Fresh non-default-prefix site starts without Sprint content.' );
$die = static function () { return static function ( $message ) { throw new RuntimeException( $message ); }; };
$fail_schema = static function ( $query ) { return str_starts_with( $query, 'CREATE TABLE ' ) && str_contains( $query, 'sprint_engine_step_progress' ) ? 'INVALID SE030 SCHEMA' : $query; };
add_filter( 'wp_die_handler', $die ); add_filter( 'query', $fail_schema );
try { activate_plugin( 'sprint-engine/sprint-engine.php' ); } catch ( RuntimeException $exception ) { /* Expected safe activation refusal. */ }
remove_filter( 'query', $fail_schema ); remove_filter( 'wp_die_handler', $die );
tutorial_check( 'installing' === get_option( Onboarding::OPTION ) && array() === tutorial_posts() && ! is_plugin_active( 'sprint-engine/sprint-engine.php' ), 'Failed first activation offers no tutorial and retains retryable first-install intent.' );
tutorial_check( ! is_wp_error( activate_plugin( 'sprint-engine/sprint-engine.php' ) ), 'Successful first site activation.' );
tutorial_check( 'pending' === get_option( Onboarding::OPTION ) && array() === tutorial_posts(), 'Activation records pending onboarding but creates no content.' );
set_current_screen( 'plugins' );
ob_start(); ( new Onboarding() )->notice(); $notice = ob_get_clean();
tutorial_check( str_contains( $notice, 'Choose how to get started' ), 'Administrator has a clear post-activation welcome link.' );
ob_start(); Onboarding::panel(); $panel = ob_get_clean();
tutorial_check( str_contains( $panel, 'Install Tutorial Sprint' ) && str_contains( $panel, 'I know how to use Sprint Engine' ) && str_contains( $panel, 'only WordPress administrators' ), 'Both choices and visibility explanation appear before opt-in.' );
tutorial_check( str_starts_with( tutorial_control( 'GET', 'install', wp_create_nonce( 'sprint_engine_tutorial' ) ), 'DIED:' ) && array() === tutorial_posts(), 'State-changing GET is rejected.' );
tutorial_check( str_starts_with( tutorial_control( 'POST', 'install', 'invalid' ), 'DIED:' ) && array() === tutorial_posts(), 'Invalid nonce is rejected without content writes.' );
tutorial_check( 'REDIRECTED' === tutorial_control( 'POST', 'skip', wp_create_nonce( 'sprint_engine_tutorial' ) ) && 'skipped' === get_option( Onboarding::OPTION ) && array() === tutorial_posts(), 'Explicit skip persists without creating content.' );
$stale_activation = static function () { return 'installing'; };
add_filter( 'pre_option_' . Onboarding::OPTION, $stale_activation );
Onboarding::finish_install();
remove_filter( 'pre_option_' . Onboarding::OPTION, $stale_activation );
tutorial_check( 'skipped' === get_option( Onboarding::OPTION ), 'Concurrent activation retry cannot replace a saved choice through stale option cache.' );
deactivate_plugins( 'sprint-engine/sprint-engine.php' ); activate_plugin( 'sprint-engine/sprint-engine.php' );
tutorial_check( 'skipped' === get_option( Onboarding::OPTION ) && array() === tutorial_posts(), 'Deactivation/reactivation preserves the choice and creates nothing.' );
delete_option( Onboarding::OPTION ); update_option( 'sprint_engine_version', '0.3.0' );
( new \ThePath\SprintEngine\Database\Installer() )->install();
tutorial_check( 'existing' === get_option( Onboarding::OPTION ) && array() === tutorial_posts(), 'Existing 0.3.0 upgrade gets no first-install prompt or content.' );
delete_option( Onboarding::OPTION ); delete_option( 'sprint_engine_version' ); delete_option( 'sprint_engine_schema_version' );
( new \ThePath\SprintEngine\Database\Installer() )->install();
tutorial_check( 'existing' === get_option( Onboarding::OPTION ), 'Owned tables identify an existing install independently of version options.' );

// Fail after multiple real draft/Step writes. Every post, meta and identity rolls back.
$step_writes = 0;
$fail = static function ( $empty, $post ) use ( &$step_writes ) { return 'sprint_engine_step' === $post['post_type'] && 3 === ++$step_writes ? true : $empty; };
add_filter( 'wp_insert_post_empty_content', $fail, 10, 2 );
$failed = ( new Installer() )->install();
remove_filter( 'wp_insert_post_empty_content', $fail );
tutorial_check( is_wp_error( $failed ) && array() === tutorial_posts() && 0 === Installer::find() && 'existing' === get_option( Onboarding::OPTION ), 'Partial failure rolls back Sprint, Steps, marker and decision; retry remains available.' );
$commit_fail = static function ( $query ) { return 'COMMIT' === $query ? 'INVALID SE030 COMMIT' : $query; };
add_filter( 'query', $commit_fail ); $failed = ( new Installer() )->install(); remove_filter( 'query', $commit_fail );
tutorial_check( is_wp_error( $failed ) && array() === tutorial_posts() && 0 === Installer::find(), 'Failed commit also rolls back every authored post and marker.' );

// Two independent processes race for the one site installation identity.
$command = array( PHP_BINARY, '-d', 'extension_dir=' . ini_get( 'extension_dir' ) );
foreach ( array( 'pdo_sqlite', 'sqlite3', 'mysqli' ) as $extension ) { if ( extension_loaded( $extension ) ) { $command[] = '-d'; $command[] = 'extension=' . $extension; } }
$command = array_merge( $command, array( __FILE__, 'worker', (string) ( microtime( true ) + 1 ) ) );
$workers = array();
for ( $i = 0; $i < 2; ++$i ) {
	$process = proc_open( $command, array( 0 => array( 'pipe', 'r' ), 1 => array( 'pipe', 'w' ), 2 => array( 'pipe', 'w' ) ), $pipes );
	if ( ! is_resource( $process ) ) { throw new RuntimeException( 'Cannot start tutorial worker.' ); }
	fclose( $pipes[0] ); $workers[] = array( $process, $pipes );
}
$tutorial_ids = array();
foreach ( $workers as [ $process, $pipes ] ) {
	$output = stream_get_contents( $pipes[1] ); $errors = stream_get_contents( $pipes[2] ); fclose( $pipes[1] ); fclose( $pipes[2] );
	tutorial_check( 0 === proc_close( $process ) && '' === $errors, 'Independent tutorial installation worker exits cleanly.' );
	$tutorial_ids[] = json_decode( $output, true, 512, JSON_THROW_ON_ERROR );
}
wp_cache_flush();
$tutorial_id = $tutorial_ids[0];
tutorial_check( is_int( $tutorial_id ) && $tutorial_id === $tutorial_ids[1] && $tutorial_id === Installer::find() && 7 === count( tutorial_posts() ), 'Concurrent opt-ins create exactly one Sprint and six Steps, returning the same identity.' );
tutorial_check( $tutorial_id === ( new Installer() )->install() && 7 === count( tutorial_posts() ), 'Refresh, repeat click and retry reuse the same tutorial unchanged.' );
tutorial_check( '' === Availability::reason( get_post( $tutorial_id ) ) && Marker::canonical( $tutorial_id ) && StructureManager::is_linear( $tutorial_id ), 'Tutorial is published, passwordless, Launchable and structurally ready.' );
$steps = Meta::steps( $tutorial_id );
foreach ( $steps as $index => $step ) {
	$definition = Content::steps()[$index];
	$blocks = array_filter( parse_blocks( $step->post_content ), static function ( $block ) { return null !== $block['blockName']; } );
	tutorial_check( 'publish' === $step->post_status && '' === $step->post_password && $definition['body'] === $step->post_content && count( $blocks ) >= 4 && array( 'core/paragraph' ) === array_values( array_unique( array_column( $blocks, 'blockName' ) ) ), 'Original Step ' . ( $index + 1 ) . ' is complete, published native Gutenberg content.' );
}
$slug = get_post_field( 'post_name', $tutorial_id );
tutorial_check( 'not_started' === ( new Runner() )->resolve( $slug )['state'] && str_contains( Routes::url( $slug ), '/sprint/' ), 'Administrator can open the actual canonical Runner before starting.' );

$users = array();
foreach ( array( 'administrator', 'subscriber', 'subscriber', 'editor' ) as $role ) { $users[] = wp_insert_user( array( 'user_login' => 'tutorial-' . wp_generate_password( 12, false ), 'user_pass' => wp_generate_password(), 'role' => $role ) ); }
$admin = $users[0];
$ordinary = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_title' => 'My separate Sprint', 'post_status' => 'publish' ) );
$ordinary_step = ( new StructureManager() )->quick_add( $ordinary, 'My own action' ); wp_update_post( array( 'ID' => $ordinary_step, 'post_status' => 'publish' ) ); Meta::save( $ordinary, array( '_sprint_engine_launchable' => true ) );
tutorial_check( ! Marker::exists( $ordinary ), 'An administrator’s ordinary Sprint never receives the special marker.' );
$identity = get_option( Installer::OPTION );
update_option( Installer::OPTION, (string) $ordinary );
tutorial_check( is_wp_error( ( new Installer() )->install() ) && ! Marker::exists( $ordinary ), 'Conflicting installation pointer cannot adopt or overwrite an ordinary Sprint.' );
update_option( Installer::OPTION, $identity );
add_post_meta( $ordinary, Marker::KEY, '1', true );
tutorial_check( is_wp_error( ( new Installer() )->install() ), 'Multiple marked Sprint identities fail closed without choosing by title or slug.' );
delete_post_meta( $ordinary, Marker::KEY );
$allow = static function () { return true; };
$deny = static function () { return false; };
add_filter( 'sprint_engine/user_can_access_sprint', $allow, PHP_INT_MAX );
foreach ( array_merge( array( 0 ), array_slice( $users, 1 ) ) as $user ) {
	wp_set_current_user( $user ); $before = tutorial_rows();
	tutorial_check( ! ( new AccessManager() )->can_access( $user, $tutorial_id ), 'Anonymous/member/editor cannot bypass the marker with a permissive last-priority filter: ' . $user );
	$context = ( new Runner() )->resolve( $slug );
	tutorial_check( in_array( $context['state'], array( 'login', 'error' ), true ) && ! isset( $context['title'], $context['step'], $context['progress'] ), 'Direct Runner reveals no tutorial title, Step body or progress: ' . $user );
	foreach ( array( array( 'sprints', $tutorial_id, 'start' ), array( 'sprints', $tutorial_id, 'restart' ), array( 'steps', $steps[0]->ID, 'complete' ) ) as $args ) {
		tutorial_check( ( $user ? 403 : 401 ) === tutorial_request( ...$args )->get_status(), 'Direct REST mutation denied: ' . $user . '/' . $args[2] );
	}
	tutorial_check( $before === tutorial_rows(), 'Denied REST/Runner requests leave every user’s progress unchanged.' );
	if ( $user ) {
		$dashboard = ( new Dashboard() )->resolve(); $listed = array_merge( ...array_values( $dashboard['sections'] ) );
		tutorial_check( ! in_array( $tutorial_id, array_column( $listed, 'sprint_id' ), true ) && in_array( $ordinary, array_column( $listed, 'sprint_id' ), true ), 'Member Dashboard excludes tutorial and keeps ordinary Sprint: ' . $user );
		$inject = static function ( $items ) use ( $tutorial_id ) { $items[] = array( 'sprint_id' => $tutorial_id, 'state' => 'not_started', 'title' => 'Injected tutorial' ); return $items; };
		add_filter( 'sprint_engine/dashboard_items', $inject );
		$filtered = ( new Dashboard() )->resolve();
		remove_filter( 'sprint_engine/dashboard_items', $inject );
		tutorial_check( ! in_array( $tutorial_id, array_column( array_merge( ...array_values( $filtered['sections'] ) ), 'sprint_id' ), true ), 'Dashboard extension cannot reinsert the marked tutorial for a member.' );
		tutorial_check( 200 === tutorial_request( 'sprints', $ordinary, 'start' )->get_status(), 'Normal members can still start their own ordinary Sprint.' );
	}
	ob_start(); Onboarding::panel(); ( new Onboarding() )->notice(); $hidden = ob_get_clean();
	tutorial_check( '' === $hidden && is_wp_error( ( new Installer() )->install() ), 'Non-administrators see no onboarding and cannot install.' );
}
remove_filter( 'sprint_engine/user_can_access_sprint', $allow, PHP_INT_MAX );
add_filter( 'sprint_engine/user_can_access_sprint', $deny, PHP_INT_MAX );
wp_set_current_user( $admin );
tutorial_check( ( new AccessManager() )->can_access( $admin, $tutorial_id ) && ! ( new AccessManager() )->can_access( $admin, $ordinary ), 'Admin tutorial access survives external denial; ordinary policy is unchanged.' );
$started = tutorial_request( 'sprints', $tutorial_id, 'start' );
tutorial_check( 200 === $started->get_status(), 'Second real administrator starts the tutorial through REST.' );
$attempt = $started->get_data()['attempt_id'];
foreach ( $steps as $step ) {
	$context = ( new Runner() )->resolve( $slug );
	tutorial_check( $step->ID === $context['step']['id'] && str_contains( $context['step']['content'], '<p' ), 'Administrator resumes and reads the canonical current tutorial Step.' );
	tutorial_check( 200 === tutorial_request( 'steps', $step->ID, 'complete', $attempt )->get_status(), 'Administrator explicitly completes the tutorial Step.' );
}
tutorial_check( 'completed' === ( new Runner() )->resolve( $slug )['state'] && 200 === tutorial_request( 'sprints', $tutorial_id, 'restart', $attempt )->get_status(), 'Administrator finishes and restarts tutorial with a new attempt.' );
tutorial_check( 2 === count( ( new ProgressService() )->get_attempt_history( $admin, $tutorial_id ) ), 'Tutorial restart retains both real administrator attempts.' );
remove_filter( 'sprint_engine/user_can_access_sprint', $deny, PHP_INT_MAX );
wp_set_current_user( 1 );
$server = rest_get_server();
foreach ( array( $tutorial_id => 'sprint_engine_sprint', $steps[0]->ID => 'sprint_engine_step' ) as $post_id => $type ) {
	$request = new WP_REST_Request( 'GET', '/wp/v2/' . $type . '/' . $post_id ); $request->set_param( 'context', 'edit' );
	tutorial_check( 200 === $server->dispatch( $request )->get_status(), 'Installed tutorial reopens in the native editor REST API.' );
	wp_set_current_user( $users[1] ); tutorial_check( 403 === $server->dispatch( $request )->get_status(), 'Member cannot read native tutorial authoring content.' ); wp_set_current_user( 1 );
}
wp_update_post( array( 'ID' => $tutorial_id, 'post_title' => 'Renamed example', 'post_name' => 'renamed-example' ) );
wp_update_post( array( 'ID' => $steps[0]->ID, 'post_content' => '<!-- wp:paragraph --><p>My saved edit</p><!-- /wp:paragraph -->' ) );
Meta::save( $tutorial_id, array( '_sprint_engine_completion_message' => 'My edited finish' ) );
$saved = tutorial_posts(); $rows = tutorial_rows();
( new \ThePath\SprintEngine\Database\Installer() )->install();
deactivate_plugins( 'sprint-engine/sprint-engine.php' ); activate_plugin( 'sprint-engine/sprint-engine.php' );
$reused = ( new Installer() )->install();
tutorial_check( $tutorial_id === $reused && Marker::canonical( $tutorial_id ), 'Rename, editor saves, upgrade and reactivation preserve tutorial identity: ' . ( is_wp_error( $reused ) ? $reused->get_error_code() : $reused ) );
tutorial_check( $saved === tutorial_posts(), 'Repeated opt-in and upgrades never overwrite authored tutorial edits.' );
tutorial_check( $rows === tutorial_rows(), 'Upgrade and reactivation retain every administrator and member attempt.' );
ob_start(); Onboarding::panel(); $panel = ob_get_clean();
tutorial_check( str_contains( $panel, Routes::url( 'renamed-example' ) ) && str_contains( $panel, 'Open Tutorial Sprint' ), 'Settings Open link follows the current saved slug.' );
ob_start(); ( new Onboarding() )->editor_notice( get_post( $tutorial_id ) ); $notice = ob_get_clean();
tutorial_check( str_contains( $notice, 'administrators only' ) && ! str_contains( $notice, 'is-dismissible' ) && isset( ( new Onboarding() )->post_states( array(), get_post( $tutorial_id ) )['sprint_engine_tutorial'] ), 'Editor and Sprint list identify only the marked tutorial.' );

foreach ( array( '', '0', 'false', array( 'unexpected' => true ) ) as $malformed ) {
	update_post_meta( $tutorial_id, Marker::KEY, $malformed ); wp_set_current_user( $users[1] );
	tutorial_check( Marker::exists( $tutorial_id ) && ! ( new AccessManager() )->can_access( $users[1], $tutorial_id ), 'Empty/false/malformed present marker fails closed for members.' );
	wp_set_current_user( 1 ); tutorial_check( is_wp_error( ( new Installer() )->install() ), 'Installer refuses to overwrite ambiguous marker state.' );
}
update_post_meta( $tutorial_id, Marker::KEY, '1' ); add_post_meta( $tutorial_id, Marker::KEY, '1' );
tutorial_check( is_wp_error( ( new Installer() )->install() ), 'Duplicate marker rows are ambiguous, never a reinstall signal.' );
delete_post_meta( $tutorial_id, Marker::KEY ); add_post_meta( $tutorial_id, Marker::KEY, '1', true );
wp_trash_post( $tutorial_id );
tutorial_check( $tutorial_id === ( new Installer() )->install() && $saved === tutorial_posts() && '' === Availability::reason( get_post( $tutorial_id ) ), 'Explicit restoration reuses trashed tutorial with its edits, publication and Steps intact.' );
wp_delete_post( $tutorial_id, true );
$new_id = ( new Installer() )->install();
tutorial_check( is_int( $new_id ) && $new_id !== $tutorial_id && 6 === count( Meta::steps( $new_id ) ) && '' === Availability::reason( get_post( $new_id ) ), 'Explicit reinstall after permanent deletion creates one new ready tutorial.' );
// Deletion retains authored old Steps/history, following normal Core data-retention rules.
tutorial_check( $rows === tutorial_rows() && get_post( $steps[0]->ID ), 'Permanent Sprint deletion/reinstall does not erase historical attempts or separately authored Step posts.' );
echo 'SE-030 tutorial checks completed: ' . $checks . " assertions.\n";
