<?php
/** SE-004.1 admin checks. Run only on disposable WordPress after foundation. */
use ThePath\SprintEngine\Content\RunnerAdmin;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Runner\Availability;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Progress\ProgressService;

$wordpress = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $wordpress || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( "Disposable WordPress required.\n" ); }
ob_start();
$_SERVER['HTTP_HOST'] = '127.0.0.1:8094';
$_SERVER['REQUEST_METHOD'] = 'POST';
require $wordpress . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
require_once ABSPATH . 'wp-admin/includes/ajax-actions.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0;
function sprint_engine_admin_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
	echo 'PASS: ' . $message . PHP_EOL;
}
function sprint_engine_admin_capture( $callback ) { ob_start(); $callback(); return ob_get_clean(); }
wp_set_current_user( 1 );
$admin = new RunnerAdmin();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Admin URL fixture', 'post_name' => 'admin-url-' . wp_generate_password( 8, false ) ) );
$slug = get_post_field( 'post_name', $sprint );
$step = ( new StructureManager() )->quick_add( $sprint, 'Unchanged Step' );
wp_update_post( array( 'ID' => $step, 'post_status' => 'publish' ) );
Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
$auto = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'auto-draft', 'post_title' => 'Auto Draft' ) );
$schema = get_option( 'sprint_engine_schema_version' );
$rewrites = get_option( 'rewrite_rules' );
sprint_engine_admin_check( '' !== $slug && '' === Availability::reason( get_post( $sprint ) ), 'Saved native slug and shared readiness are available.' );
$html = sprint_engine_admin_capture( static function () use ( $admin, $sprint ) { $admin->render( get_post( $sprint ) ); } );
sprint_engine_admin_check( str_contains( $html, 'name="post_name"' ) && str_contains( $html, esc_attr( $slug ) ), 'Editor displays native post_name input.' );
sprint_engine_admin_check( str_contains( $html, esc_url( Routes::url( $slug ) ) ) && str_contains( $html, 'View Runner' ), 'Editor displays canonical helper URL and available View Runner.' );
$empty = sprint_engine_admin_capture( static function () use ( $admin, $auto ) { $admin->render( get_post( $auto ) ); } );
sprint_engine_admin_check( str_contains( $empty, 'Save this Sprint first' ) && ! str_contains( $empty, 'se-runner-url' ), 'Auto-draft has no invented permanent URL.' );
$actions = apply_filters( 'post_row_actions', array( 'edit' => 'Edit' ), get_post( $sprint ) );
sprint_engine_admin_check( isset( $actions['edit'], $actions['sprint_engine_runner'] ) && str_contains( $actions['sprint_engine_runner'], esc_url( Routes::url( $slug ) ) ), 'Registered row action preserves existing actions and uses canonical route.' );
sprint_engine_admin_check( ! isset( $admin->row_actions( array(), get_post( $step ) )['sprint_engine_runner'] ), 'Steps receive no Runner action.' );
foreach ( array( 'draft', 'password', 'launchable', 'invalid' ) as $case ) {
	if ( 'draft' === $case ) { wp_update_post( array( 'ID' => $sprint, 'post_status' => 'draft' ) ); }
	if ( 'password' === $case ) { wp_update_post( array( 'ID' => $sprint, 'post_password' => 'protected' ) ); }
	if ( 'launchable' === $case ) { update_post_meta( $sprint, '_sprint_engine_launchable', false ); }
	if ( 'invalid' === $case ) { update_post_meta( $sprint, '_sprint_engine_start_step_id', 99999999 ); }
	$html = sprint_engine_admin_capture( static function () use ( $admin, $sprint ) { $admin->render( get_post( $sprint ) ); } );
	sprint_engine_admin_check( ! str_contains( $html, 'View Runner' ) && str_contains( $html, 'Runner unavailable' ) && str_contains( $html, esc_url( Routes::url( $slug ) ) ), $case . ' retains canonical URL without active navigation.' );
	sprint_engine_admin_check( ! isset( $admin->row_actions( array(), get_post( $sprint ) )['sprint_engine_runner'] ) && 404 === ( new Runner() )->resolve( $slug )['status'], $case . ' list and frontend agree on unavailability.' );
	wp_update_post( array( 'ID' => $sprint, 'post_status' => 'publish', 'post_password' => '' ) );
	update_post_meta( $sprint, '_sprint_engine_launchable', true );
	update_post_meta( $sprint, '_sprint_engine_start_step_id', $step );
}
set_current_screen( 'edit-sprint_engine_sprint' );
$GLOBALS['post'] = get_post( $sprint );
$columns = apply_filters( 'manage_sprint_engine_sprint_posts_columns', array( 'title' => 'Title', 'date' => 'Date' ) );
sprint_engine_admin_check( isset( $columns['sprint_engine_runner'], $columns['date'], $columns['title'] ), 'Runner column preserves normal columns.' );
$html = sprint_engine_admin_capture( static function () use ( $sprint ) { do_action( 'manage_sprint_engine_sprint_posts_custom_column', 'sprint_engine_runner', $sprint ); } );
sprint_engine_admin_check( str_contains( $html, esc_url( Routes::url( $slug ) ) ), 'Runner column points to canonical URL.' );
$table = _get_list_table( 'WP_Posts_List_Table', array( 'screen' => get_current_screen() ) );
$html = sprint_engine_admin_capture( static function () use ( $table ) { $table->inline_edit(); } );
sprint_engine_admin_check( 1 === substr_count( $html, 'name="post_name"' ), 'Actual Quick Edit renders exactly one native slug field.' );
$html = sprint_engine_admin_capture( static function () use ( $sprint ) { get_inline_data( get_post( $sprint ) ); } );
sprint_engine_admin_check( str_contains( $html, '<div class="post_name">' . $slug . '</div>' ), 'Native hidden row data supplies current post_name to core inline-edit-post.' );
$html = sprint_engine_admin_capture( static function () use ( $admin ) { $admin->quick_edit( 'sprint_engine_runner', 'sprint_engine_step' ); } );
sprint_engine_admin_check( '' === $html, 'No Step Quick Edit additions.' );
( new ProgressService() )->start_sprint( 1, $sprint );
function sprint_engine_admin_state( $sprint, $step ) {
	global $wpdb;
	return array( get_the_title( $sprint ), Meta::read( $sprint, 'sprint_engine_sprint' ), Meta::read( $step, 'sprint_engine_step' ), get_post_field( 'post_name', $step ), $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id", ARRAY_A ), $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id", ARRAY_A ) );
}
$before = sprint_engine_admin_state( $sprint, $step );
// Exercise the native editor/metabox persistence API with the rendered field.
$result = edit_post( wp_slash( array( 'post_ID' => $sprint, 'post_type' => 'sprint_engine_sprint', 'post_name' => 'My Brilliant Sprint', 'user_ID' => 1 ) ) );
sprint_engine_admin_check( $sprint === $result && 'my-brilliant-sprint' === get_post_field( 'post_name', $sprint ), 'Native editor save sanitises and writes post_name.' );
sprint_engine_admin_check( $before === sprint_engine_admin_state( $sprint, $step ), 'Slug save preserves title, structure, Step slug and all progress rows.' );
sprint_engine_admin_check( $sprint === get_page_by_path( 'my-brilliant-sprint', OBJECT, array( 'sprint_engine_sprint' ) )->ID && ! get_page_by_path( $slug, OBJECT, array( 'sprint_engine_sprint' ) ), 'New slug resolves same Sprint; old slug resolves no separate Sprint.' );
sprint_engine_admin_check( 200 === ( new Runner() )->resolve( 'my-brilliant-sprint' )['status'] && 404 === ( new Runner() )->resolve( $slug )['status'], 'Existing Runner resolves renamed Sprint and rejects old slug.' );
// Real core AJAX boundary, including nonce/capability failures.
define( 'DOING_AJAX', true );
class SE_Admin_Ajax_End extends RuntimeException {}
add_filter( 'wp_die_ajax_handler', static function () { return static function () { throw new SE_Admin_Ajax_End(); }; } );
function sprint_engine_admin_inline( $sprint, $name, $nonce = null ) {
	$_POST = array( 'post_ID' => $sprint, 'post_type' => 'sprint_engine_sprint', 'post_name' => $name, 'post_title' => get_the_title( $sprint ), '_status' => 'publish', '_inline_edit' => $nonce ?? wp_create_nonce( 'inlineeditnonce' ), 'screen' => 'edit-sprint_engine_sprint', 'post_view' => 'list' );
	$_REQUEST = $_POST;
	( new RunnerAdmin() )->guard_slug();
	ob_start();
	try { wp_ajax_inline_save(); } catch ( SE_Admin_Ajax_End $exception ) {}
	ob_end_clean();
	$_POST = $_REQUEST = array();
}
sprint_engine_admin_inline( $sprint, 'Quick Edit Sprint' );
sprint_engine_admin_check( 'quick-edit-sprint' === get_post_field( 'post_name', $sprint ), 'Actual core inline-save updates and sanitises native slug.' );
sprint_engine_admin_inline( $sprint, array( 'malformed' ) );
sprint_engine_admin_check( 'quick-edit-sprint' === get_post_field( 'post_name', $sprint ), 'Malformed Quick Edit input is discarded safely.' );
sprint_engine_admin_inline( $sprint, 'bad-nonce', 'invalid' );
sprint_engine_admin_check( 'quick-edit-sprint' === get_post_field( 'post_name', $sprint ), 'Core rejects invalid nonce.' );
wp_set_current_user( 0 );
sprint_engine_admin_inline( $sprint, 'unauthorised' );
sprint_engine_admin_check( 'quick-edit-sprint' === get_post_field( 'post_name', $sprint ), 'Core rejects unauthorised slug mutation.' );
wp_set_current_user( 1 );
sprint_engine_admin_check( $before === sprint_engine_admin_state( $sprint, $step ), 'Quick Edit and rejected writes preserve unrelated content and progress.' );
$occupied = 'occupied-' . $sprint;
$duplicate = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Duplicate', 'post_name' => $occupied ) );
sprint_engine_admin_inline( $sprint, $occupied );
sprint_engine_admin_check( ( $occupied . '-2' ) === get_post_field( 'post_name', $sprint ), 'WordPress enforces native published slug uniqueness.' );
$revision = wp_create_post_autosave( array( 'post_ID' => $sprint, 'post_type' => 'sprint_engine_sprint', 'post_title' => 'Autosave', 'post_content' => 'Autosave content', 'post_excerpt' => '', 'post_name' => 'autosave-slug' ) );
sprint_engine_admin_check( ! is_wp_error( $revision ) && ( $occupied . '-2' ) === get_post_field( 'post_name', $sprint ), 'Autosave revision does not overwrite canonical slug.' );
foreach ( array( 'edit-sprint_engine_sprint', 'sprint_engine_step', 'dashboard' ) as $screen ) {
	wp_dequeue_script( 'sprint-engine-runner-admin' );
	set_current_screen( $screen );
	$admin->enqueue();
	sprint_engine_admin_check( ! wp_script_is( 'sprint-engine-runner-admin', 'enqueued' ), 'Copy script absent on ' . $screen );
}
set_current_screen( 'sprint_engine_sprint' );
$admin->enqueue();
sprint_engine_admin_check( wp_script_is( 'sprint-engine-runner-admin', 'enqueued' ), 'Copy script enqueues on Sprint editor only.' );
$admin->add_box();
sprint_engine_admin_check( isset( $GLOBALS['wp_meta_boxes']['sprint_engine_sprint']['normal']['default']['sprint_engine_runner_url'] ), 'Runner metabox registered for Sprint editor.' );
sprint_engine_admin_check( ! get_post_type_object( 'sprint_engine_sprint' )->public && ! get_post_type_object( 'sprint_engine_sprint' )->publicly_queryable && false === get_post_type_object( 'sprint_engine_sprint' )->rewrite, 'Private CPT routing remains unchanged.' );
sprint_engine_admin_check( $schema === get_option( 'sprint_engine_schema_version' ) && $rewrites === get_option( 'rewrite_rules' ), 'No schema change or slug-triggered rewrite flush.' );
$keys = array_keys( get_registered_meta_keys( 'post', 'sprint_engine_sprint' ) );
sprint_engine_admin_check( ! array_filter( $keys, static function ( $key ) { return str_contains( $key, 'slug' ); } ), 'No separate slug metadata registered.' );
// SE-009: exercise native hooks and the actual main list query.
$list = new ThePath\SprintEngine\Content\StepListAdmin();
set_current_screen( 'edit-sprint_engine_step' );
$columns = apply_filters( 'manage_sprint_engine_step_posts_columns', array( 'cb' => '', 'title' => 'Title', 'date' => 'Date' ) );
sprint_engine_admin_check( array( 'cb', 'title', 'sprint_engine_parent', 'sprint_engine_stage', 'date' ) === array_keys( $columns ), 'Native Step list includes Parent Sprint, Stage and Date.' );
$html = sprint_engine_admin_capture( static function () use ( $step ) { do_action( 'manage_sprint_engine_step_posts_custom_column', 'sprint_engine_parent', $step ); } );
sprint_engine_admin_check( str_contains( $html, esc_url( get_edit_post_link( $sprint ) ) ) && str_contains( $html, get_the_title( $sprint ) ), 'Parent column links its title to the Sprint editor.' );
update_post_meta( $step, '_sprint_engine_stage_label', 'Custom phase' );
sprint_engine_admin_check( 'Custom phase' === sprint_engine_admin_capture( static function () use ( $list, $step ) { $list->column( 'sprint_engine_stage', $step ); } ), 'Stage column displays custom text.' );
$unassigned = wp_insert_post( array( 'post_type' => 'sprint_engine_step', 'post_title' => 'Unassigned Step', 'post_status' => 'draft' ) );
sprint_engine_admin_check( '—' === sprint_engine_admin_capture( static function () use ( $list, $unassigned ) { $list->column( 'sprint_engine_parent', $unassigned ); } ), 'Unassigned Step uses a safe placeholder.' );
$html = sprint_engine_admin_capture( static function () { do_action( 'restrict_manage_posts', 'sprint_engine_step' ); } );
sprint_engine_admin_check( str_contains( $html, 'All Sprints' ) && str_contains( $html, 'value="' . $sprint . '"' ) && ! str_contains( $html, 'value="' . $step . '"' ), 'Parent dropdown lists real Sprints only.' );
sprint_engine_admin_check( '' === sprint_engine_admin_capture( static function () use ( $list ) { $list->dropdown( 'post' ); } ), 'Parent dropdown omitted on other list types.' );
$original_main = $GLOBALS['wp_the_query'];
$_GET['sprint_engine_parent_sprint'] = (string) $sprint;
$query = new WP_Query(); $GLOBALS['wp_the_query'] = $query;
$query->query( array( 'post_type' => 'sprint_engine_step', 'post_status' => array( 'draft', 'publish' ), 'posts_per_page' => 1, 's' => 'Unchanged' ) );
sprint_engine_admin_check( array( $step ) === wp_list_pluck( $query->posts, 'ID' ) && 1 === (int) $query->found_posts && 1 === $query->get( 'posts_per_page' ), 'Real main query filters by Sprint and retains search and pagination.' );
foreach ( array( (string) $step, '-1', 'abc', array( $sprint ), '999999999999999999999' ) as $bad ) {
 $_GET['sprint_engine_parent_sprint'] = $bad; $query = new WP_Query(); $GLOBALS['wp_the_query'] = $query;
 $query->query( array( 'post_type' => 'sprint_engine_step', 'post_status' => 'draft' ) );
 sprint_engine_admin_check( ! $query->get( 'meta_query' ), 'Invalid parent filter safely falls back to All Sprints.' );
}
$_GET['sprint_engine_parent_sprint'] = (string) $sprint;
set_current_screen( 'front' ); $GLOBALS['current_screen'] = null;
$query = new WP_Query(); $GLOBALS['wp_the_query'] = $query;
$query->query( array( 'post_type' => 'sprint_engine_step' ) );
sprint_engine_admin_check( ! $query->get( 'meta_query' ), 'Frontend main query is unaffected by parent filter input.' );
set_current_screen( 'edit-sprint_engine_step' ); $secondary = new WP_Query( array( 'post_type' => 'sprint_engine_step' ) );
sprint_engine_admin_check( ! $secondary->get( 'meta_query' ), 'Secondary admin query is unaffected.' );
$GLOBALS['wp_the_query'] = $original_main; $_GET = array();
sprint_engine_admin_check( apply_filters( 'disable_months_dropdown', false, 'sprint_engine_step' ) && ! apply_filters( 'disable_months_dropdown', false, 'post' ), 'Only Steps suppress the native month dropdown.' );
foreach ( array( $auto, $duplicate, $sprint, $step, $unassigned ) as $id ) { wp_delete_post( $id, true ); }
echo $checks . " admin assertions passed.\n";
ob_end_flush();
