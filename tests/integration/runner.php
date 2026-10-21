<?php
/**
 * SE-004 route/access/Runner checks on disposable WordPress only.
 * Optional SE_TEST_HTTP_BASE exercises an already-running loopback test server.
 *
 * @package SprintEngine
 */

use ThePath\SprintEngine\Access\AccessProvider;
use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Access\LoggedInAccessProvider;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Progress\EnrolmentRepository;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Plugin;

$wordpress = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $wordpress || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
	fwrite( STDERR, "Set SE_TEST_WP_ROOT and SE_TEST_DISPOSABLE=yes for a disposable test site.\n" );
	exit( 1 );
}
ob_start(); // Keep CLI headers available for actual template_redirect checks.
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['HTTP_HOST'] = '127.0.0.1:8094';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require $wordpress . '/wp-load.php';
require_once dirname( __DIR__ ) . '/attempt-identity.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
error_reporting( E_ALL );
set_error_handler( static function ( $severity, $message, $file, $line ) { throw new ErrorException( $message, 0, $severity, $file, $line ); } );
$checks = 0;
function sprint_engine_runner_check( $condition, $message ) {
	global $checks;
	if ( ! $condition ) { throw new RuntimeException( $message ); }
	++$checks;
	echo 'PASS: ' . $message . PHP_EOL;
}
function sprint_engine_runner_html( $context ) {
	set_query_var( 'sprint_engine_runner_context', $context );
	ob_start();
	include dirname( __DIR__, 2 ) . '/templates/runner.php';
	return ob_get_clean();
}
function sprint_engine_runner_shell( $html ) {
	preg_match( '/<div class="se-runner__shell">.*?<\/main>\s*<\/div>/s', $html, $match );
	return preg_replace( '/>\s+</', '><', $match[0] ?? '' );
}
function sprint_engine_runner_rows( $user, $sprint ) {
	global $wpdb;
	return array(
		$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments WHERE user_id = %d AND sprint_id = %d", $user, $sprint ), ARRAY_A ),
		$wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress WHERE user_id = %d AND sprint_id = %d ORDER BY step_id", $user, $sprint ), ARRAY_A ),
	);
}
function sprint_engine_runner_request( $path ) {
	global $wp;
	$_SERVER['REQUEST_URI'] = $path;
	$_SERVER['PHP_SELF'] = '/index.php';
	$_SERVER['PATH_INFO'] = '';
	$_SERVER['REQUEST_METHOD'] = 'GET';
	$_GET = array();
	parse_str( wp_parse_url( $path, PHP_URL_QUERY ) ?? '', $_GET );
	$wp->matched_rule = '';
	$wp->matched_query = '';
	$wp->parse_request();
}
function sprint_engine_runner_http( $path, $user = 0 ) {
	$base = getenv( 'SE_TEST_HTTP_BASE' );
	if ( ! $base || ! in_array( wp_parse_url( $base, PHP_URL_HOST ), array( '127.0.0.1', 'localhost' ), true ) ) { throw new RuntimeException( 'HTTP tests require a loopback server.' ); }
	$headers = array();
	if ( $user ) { $headers['Cookie'] = LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie( $user, time() + 3600, 'logged_in' ); }
	$response = wp_remote_get( rtrim( $base, '/' ) . $path, array( 'redirection' => 0, 'headers' => $headers, 'timeout' => 30 ) );
	if ( is_wp_error( $response ) ) { throw new RuntimeException( 'Loopback request failed: ' . $response->get_error_message() ); }
	return $response;
}

wp_set_current_user( 1 );
$manager = new StructureManager();
$service = new ProgressService();
$runner = new Runner();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Runner <em>Title</em>', 'post_name' => 'runner-fixture-' . wp_generate_password( 8, false ), 'post_content' => '<!-- wp:paragraph --><p>AUTHORIZED OVERVIEW</p><!-- /wp:paragraph -->' ) );
$slug = get_post_field( 'post_name', $sprint );
$other = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Foreign Sprint', 'post_name' => 'runner-foreign-' . wp_generate_password( 8, false ) ) );
$foreign = $manager->quick_add( $other, 'FOREIGN SECRET TITLE' );
wp_update_post( array( 'ID' => $foreign, 'post_status' => 'publish', 'post_content' => 'FOREIGN SECRET CONTENT' ) );
Meta::save( $other, array( '_sprint_engine_launchable' => true ) );
$steps = array();
for ( $i = 1; $i <= 5; ++$i ) {
	$step = $manager->quick_add( $sprint, 'STEP SECRET TITLE ' . $i );
	wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>STEP SECRET CONTENT ' . $i . '</p><!-- /wp:paragraph --><!-- wp:audio --><figure class="wp-block-audio"><audio controls src="https://example.invalid/native-audio.mp3"></audio></figure><!-- /wp:audio -->' ) );
	Meta::save( $step, array( '_sprint_engine_stage_label' => 'Stage ' . $i, '_sprint_engine_mode' => 1 === $i ? 'task' : 'content', '_sprint_engine_estimated_minutes' => 1 === $i ? 10 : 0 ) );
	$steps[] = $step;
}
Meta::save( $sprint, array( '_sprint_engine_launchable' => true, '_sprint_engine_estimated_minutes' => 25 ) );
$user = wp_insert_user( array( 'user_login' => 'runner-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$outsider = wp_insert_user( array( 'user_login' => 'runner-other-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$old_permalink = get_option( 'permalink_structure' );
$wp_rewrite->set_permalink_structure( '/%postname%/' );
Plugin::activate();
sprint_engine_runner_check( isset( get_option( 'rewrite_rules' )[Routes::RULE] ), 'Activation registers Runner rule before flushing rewrites.' );
sprint_engine_runner_check( in_array( Routes::QUERY_VAR, apply_filters( 'query_vars', array() ), true ), 'Dedicated Runner query variable is registered.' );
sprint_engine_runner_check( ! get_post_type_object( 'sprint_engine_step' )->publicly_queryable && ! get_post_type_object( 'sprint_engine_step' )->rewrite, 'Steps retain non-public standalone URLs.' );
sprint_engine_runner_check( interface_exists( AccessProvider::class ) && is_subclass_of( LoggedInAccessProvider::class, AccessProvider::class ), 'Access provider contract exists and default implements it.' );
$provider = new LoggedInAccessProvider();
wp_set_current_user( $user );
sprint_engine_runner_check( $provider->can_access( $user, $sprint ), 'Default provider permits authenticated existing user and valid Sprint.' );
sprint_engine_runner_check( ! $provider->can_access( $outsider, $sprint ) && ! $provider->can_access( $user, $steps[0] ) && ! $provider->can_access( $user, 999999999 ), 'Default provider rejects another identity, wrong type and missing Sprint.' );
$policy = new class() implements AccessProvider {
	public $args;
	public function can_access( $user_id, $sprint_id ) { $this->args = array( $user_id, $sprint_id ); return false; }
};
$access = new AccessManager( $policy );
sprint_engine_runner_check( ! $access->can_access( $user, $sprint ) && array( $user, $sprint ) === $policy->args, 'AccessManager delegates exact IDs to supplied provider.' );
$filter_args = null;
$grant = static function ( $decision, $id, $sprint_id ) use ( &$filter_args ) { $filter_args = array( $decision, $id, $sprint_id ); return true; };
add_filter( 'sprint_engine/user_can_access_sprint', $grant, 10, 3 );
sprint_engine_runner_check( $access->can_access( $user, $sprint ) && array( false, $user, $sprint ) === $filter_args, 'Public filter can override provider denial with documented arguments.' );
wp_set_current_user( 0 );
$login = $runner->resolve( $slug );
sprint_engine_runner_check( 'login' === $login['state'] && ! isset( $login['title'], $login['step'] ), 'Authentication cannot be bypassed by an access filter grant.' );
parse_str( wp_parse_url( $login['login_url'], PHP_URL_QUERY ), $login_query );
sprint_engine_runner_check( Routes::url( $slug ) === $login_query['redirect_to'] && str_contains( $login['login_url'], 'wp-login.php' ), 'Anonymous login flow preserves full canonical Sprint URL.' );
remove_filter( 'sprint_engine/user_can_access_sprint', $grant );
sprint_engine_runner_check( ! $provider->can_access( $user, $sprint ), 'Default provider does not treat a supplied user ID as login.' );
wp_set_current_user( $user );
$deny = static function () { return false; };
add_filter( 'sprint_engine/user_can_access_sprint', $deny );
$context = $runner->resolve( $slug );
sprint_engine_runner_check( 403 === $context['status'] && ! str_contains( wp_json_encode( $context ), 'SECRET' ) && ! isset( $context['title'], $context['progress'] ), 'Access filter denial returns 403 without private context.' );
sprint_engine_runner_check( array( array(), array() ) === sprint_engine_runner_rows( $user, $sprint ), 'Denied access performs no progress writes.' );
remove_filter( 'sprint_engine/user_can_access_sprint', $deny );

// SE-017: only a Core-resolved ready Sprint may enter the presentation gateway.
$denied_calls = array(); $completed_calls = array(); $followup_calls = array(); $gate_calls = array();
$denied_listener = static function ( ...$args ) use ( &$denied_calls ) { $denied_calls[] = $args; echo '<p>DENIAL ACTION</p>'; return true; };
$completed_listener = static function ( $completed ) use ( &$completed_calls ) { $completed_calls[] = $completed; echo '<p>COMPLETED ACTION</p>'; };
$followup_listener = static function ( ...$args ) use ( &$followup_calls ) { $followup_calls[] = $args; echo '<p><a href="#se-runner-main">FOLLOWUP ACTION</a></p>'; };
$gate_value = false;
$gate_filter = static function ( $default, $id ) use ( &$gate_calls, &$gate_value ) { $gate_calls[] = array( $default, $id ); return $gate_value; };
add_action( 'sprint_engine/runner_access_denied_actions', $denied_listener );
add_action( 'sprint_engine/runner_completed_actions', $completed_listener );
add_action( 'sprint_engine/runner_completed_followup_actions', $followup_listener );
add_filter( 'sprint_engine/runner_show_public_access_gate', $gate_filter, 10, 2 );
add_filter( 'sprint_engine/user_can_access_sprint', $deny );
$denied = $runner->resolve( $slug ); $denied_html = sprint_engine_runner_html( $denied );
sprint_engine_runner_check( array( array( $sprint ) ) === $denied_calls && array() === $gate_calls && array() === $completed_calls && array() === $followup_calls, 'Authenticated denial action receives only the resolved ready Sprint ID; anonymous filter and completion action do not run.' );
sprint_engine_runner_check( array( 'state', 'status', 'message', 'access_denied_sprint_id' ) === array_keys( $denied ) && 'error' === $denied['state'] && 403 === $denied['status'] && ! str_contains( $denied_html, 'SECRET' ) && ! str_contains( $denied_html, 'AUTHORIZED OVERVIEW' ) && ! str_contains( $denied_html, 'se-runner-action' ), 'Denial keeps generic error semantics with no content, member progress or write controls even when its callback returns true.' );
remove_action( 'sprint_engine/runner_access_denied_actions', $denied_listener );
sprint_engine_runner_check( sprint_engine_runner_shell( str_replace( '<p>DENIAL ACTION</p>', '', $denied_html ) ) === sprint_engine_runner_shell( sprint_engine_runner_html( $denied ) ), 'Without a denial listener the existing error shell is unchanged.' );
add_action( 'sprint_engine/runner_access_denied_actions', $denied_listener );
wp_set_current_user( 0 );
foreach ( array( false, null, 0, 1, 'true', '1', array( true ), new stdClass() ) as $gate_value ) {
	$gate_calls = array(); $denied_calls = array();
	$default_login = $runner->resolve( $slug );
	sprint_engine_runner_check( 'login' === $default_login['state'] && 302 === $default_login['status'] && array( array( false, $sprint ) ) === $gate_calls && array() === $denied_calls, 'Only exact boolean true opts in; other filter values preserve login without a denial render.' );
}
$gate_value = true; $gate_calls = array(); $denied_calls = array();
$_REQUEST = array( 'sprint_id' => $other, 'attempt_id' => 999, 'attempt_number' => 99, 'user_id' => $outsider );
$gateway = $runner->resolve( $slug ); $gateway_html = sprint_engine_runner_html( $gateway );
parse_str( wp_parse_url( $gateway['login_url'], PHP_URL_QUERY ), $gateway_query );
sprint_engine_runner_check( 403 === $gateway['status'] && 'error' === $gateway['state'] && array( array( false, $sprint ) ) === $gate_calls && array( array( $sprint ) ) === $denied_calls && array() === $completed_calls && array() === $followup_calls, 'Anonymous opt-in uses the same recognised denial and only the path-resolved Sprint, ignoring supplied identities.' );
sprint_engine_runner_check( $denied['message'] === $gateway['message'] && Routes::url( $slug ) === $gateway_query['redirect_to'] && str_contains( $gateway_html, '>Login</a>' ) && str_contains( $gateway_html, '<meta name="robots" content="noindex, nofollow">' ) && ! str_contains( $gateway_html, 'SECRET' ) && ! str_contains( $gateway_html, 'AUTHORIZED OVERVIEW' ) && ! isset( $gateway['progress'], $gateway['title'], $gateway['step'] ), 'Opted-in screen remains generic/noindex and offers login back to canonical Runner without private context.' );
sprint_engine_runner_check( array( array(), array() ) === sprint_engine_runner_rows( $user, $sprint ) && array( array(), array() ) === sprint_engine_runner_rows( $outsider, $sprint ), 'Both denial presentations create no member progress.' );
$_REQUEST = array(); wp_set_current_user( $user );

// Every unavailable case is exercised under both denial and anonymous opt-in.
$assert_unavailable = static function ( $invalid ) use ( $runner, $user, &$gate_calls, &$denied_calls, &$completed_calls, &$followup_calls ) {
	$previous_user = get_current_user_id();
	foreach ( array( $user, 0 ) as $member ) {
		wp_set_current_user( $member ); $gate_calls = array(); $denied_calls = array();
		$blocked = $runner->resolve( $invalid ); $blocked_html = sprint_engine_runner_html( $blocked );
		sprint_engine_runner_check( array() === $completed_calls && array() === $followup_calls, 'Neither completion action fires for missing, malformed or unavailable Runner context.' );
		sprint_engine_runner_check( 404 === $blocked['status'] && ! isset( $blocked['access_denied_sprint_id'] ) && array() === $gate_calls && array() === $denied_calls && ! str_contains( $blocked_html, 'SECRET' ) && ! str_contains( $blocked_html, '>Login</a>' ), 'Unavailable content never reaches the public filter or denial action for member ' . $member );
	}
	wp_set_current_user( $previous_user );
};
foreach ( array( 'missing-sprint', get_post_field( 'post_name', $steps[0] ), array(), '', '%2fsecret', '..%5csecret' ) as $invalid ) {
	sprint_engine_runner_check( 404 === $runner->resolve( $invalid )['status'], 'Missing/wrong-type/malformed slug fails as unavailable.' );
	$assert_unavailable( $invalid );
}
wp_set_current_user( 1 );
foreach ( array( 'draft', 'pending', 'private', 'future', 'trash' ) as $status ) {
	wp_update_post( array( 'ID' => $sprint, 'post_status' => $status, 'post_date' => '2036-01-01 00:00:00', 'post_date_gmt' => '2036-01-01 00:00:00' ) );
	sprint_engine_runner_check( $status === get_post_status( $sprint ), 'Fixture has intended publication state: ' . $status );
	sprint_engine_runner_check( 404 === $runner->resolve( $slug )['status'], 'Unpublished Sprint is unavailable: ' . $status );
	$assert_unavailable( $slug );
}
wp_update_post( array( 'ID' => $sprint, 'post_status' => 'publish', 'post_name' => $slug, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00' ) );
Meta::save( $sprint, array( '_sprint_engine_launchable' => false ) );
sprint_engine_runner_check( 404 === $runner->resolve( $slug )['status'], 'Non-launchable Sprint is unavailable.' );
$assert_unavailable( $slug );
Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
update_post_meta( $steps[0], '_sprint_engine_next_step_id', $foreign );
sprint_engine_runner_check( 404 === $runner->resolve( $slug )['status'], 'Invalid foreign-linked Sprint is unavailable.' );
$assert_unavailable( $slug );
wp_set_current_user( 1 ); $manager->apply_linear_order( $sprint, $steps ); wp_set_current_user( $user );
wp_update_post( array( 'ID' => $sprint, 'post_password' => 'test-only' ) );
sprint_engine_runner_check( 404 === $runner->resolve( $slug )['status'], 'Runner never bypasses a Sprint post password.' );
$assert_unavailable( $slug );
wp_update_post( array( 'ID' => $sprint, 'post_password' => '' ) );
wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'draft' ) );
$assert_unavailable( $slug );
wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'publish' ) );
remove_filter( 'sprint_engine/user_can_access_sprint', $deny );
remove_filter( 'sprint_engine/runner_show_public_access_gate', $gate_filter );
$denied_calls = array();
wp_set_current_user( $user );

// Start state is read-only and standard WordPress content filters run after access.
$content_calls = array();
$content_filter = static function ( $html ) use ( &$content_calls ) { $content_calls[] = get_the_ID(); return $html . '<p>CONTENT FILTER RAN</p>'; };
add_filter( 'the_content', $content_filter, 99 );
$previous_post = $GLOBALS['post'] ?? null;
$context = $runner->resolve( $slug );
sprint_engine_runner_check( 'not_started' === $context['state'] && '25 minutes' === $context['duration']['label'] && ! isset( $context['step'] ), 'Authorized Start context contains overview/duration, not a Step.' );
sprint_engine_runner_check( str_contains( $context['overview'], 'AUTHORIZED OVERVIEW</p>' ) && str_contains( $context['overview'], 'CONTENT FILTER RAN' ) && array( $sprint ) === $content_calls, 'Overview Gutenberg content uses normal filters and correct post context.' );
sprint_engine_runner_check( $previous_post === ( $GLOBALS['post'] ?? null ), 'Content rendering restores the previous global post.' );
$html = sprint_engine_runner_html( $context );
sprint_engine_runner_check( ! str_contains( $html, 'id="se-runner-restart"' ) && ! str_contains( $html, 'id="se-restart-dialog"' ), 'Never-started Runner does not offer mid-Sprint Restart or its dialog.' );
sprint_engine_runner_check( str_contains( $html, 'Ready to start' ) && str_contains( $html, '25 minutes' ) && str_contains( $html, 'Runner &lt;em&gt;Title&lt;/em&gt;' ), 'Start HTML shows state/duration and escapes the Sprint title.' );
sprint_engine_runner_check( ! str_contains( $html, 'STEP SECRET' ) && ! str_contains( $html, 'Complete &amp; Continue' ) && ! str_contains( $html, '<form' ), 'Start page includes no Step, completion control or mutation form.' );
sprint_engine_runner_check( array( array(), array() ) === sprint_engine_runner_rows( $user, $sprint ), 'Viewing Start does not create enrolment or Step progress.' );
sprint_engine_runner_check( str_contains( $html, 'se-runner__button--primary' ) && str_contains( $html, '>Start Sprint</button>' ), 'Start uses a reusable primary control.' );
sprint_engine_runner_check( str_contains( $html, 'href="' . esc_url( \ThePath\SprintEngine\Dashboard\Routes::url() ) . '">Back to My Sprints</a>' ), 'Runner Start returns to the canonical Dashboard.' );
sprint_engine_runner_check( array() === $completed_calls && array() === $followup_calls && array() === $denied_calls, 'Presentation actions do not fire before Start.' );

// Path, not request IDs or arbitrary query vars, selects the Sprint.
foreach ( array( '/sprint/' . $slug, '/sprint/' . $slug . '/' ) as $path ) {
	sprint_engine_runner_request( $path );
	sprint_engine_runner_check( $slug === Routes::slug(), 'Canonical rewrite matches with or without trailing slash.' );
}
sprint_engine_runner_request( '/?sprint_engine_sprint_slug=' . $slug );
sprint_engine_runner_check( null === Routes::slug(), 'A query flag on an unrelated URL cannot invoke Runner.' );
$unrelated = array( 'p' => 12, 'feed' => 'rss2' );
sprint_engine_runner_check( $unrelated === ( new Routes() )->request( $unrelated ), 'Unrelated query variables remain unchanged.' );
sprint_engine_runner_request( '/sprint/' . $slug . '/?sprint_engine_sprint_slug=foreign&user_id=' . $outsider . '&step_id=' . $foreign . '&feed=rss2&p=' . $foreign );
sprint_engine_runner_check( $slug === Routes::slug() && ! isset( $wp->query_vars['feed'], $wp->query_vars['p'] ) && array( 0 ) === $wp->query_vars['post__in'], 'Path capture wins over slug/feed/post tampering and suppresses unrelated query content.' );
$_REQUEST = array( 'user_id' => $outsider, 'step_id' => $foreign );
$service->start_sprint( $user, $sprint );
$context = $runner->resolve( $slug );
sprint_engine_runner_check( 'in_progress' === $context['state'] && $steps[0] === $context['step']['id'], 'Runner resumes ProgressService current Step despite request-supplied IDs.' );
sprint_engine_runner_check( $service->get_progress( $user, $sprint ) === $context['progress'], 'Runner progress values are the unmodified service projection.' );
$html = sprint_engine_runner_html( $context );
sprint_engine_runner_check( str_contains( $html, 'STEP SECRET CONTENT 1' ) && ! str_contains( $html, 'STEP SECRET CONTENT 2' ) && ! str_contains( $html, 'FOREIGN SECRET' ), 'Exactly the current Step is rendered.' );
sprint_engine_runner_check( str_contains( $html, '<audio controls' ) && str_contains( $html, 'wp-block-audio' ) && str_contains( $html, 'CONTENT FILTER RAN' ), 'Native Audio and paragraph blocks render through standard content filters.' );
sprint_engine_runner_check( str_contains( $html, '0 of 5 complete' ) && str_contains( $html, 'value="0"' ) && str_contains( $html, 'Stage 1' ), 'Read-only progress distinguishes first Step from completed count and shows stage.' );
sprint_engine_runner_check( str_contains( $html, 'Step 1 of 5' ) && str_contains( $html, 'About 10 minutes' ) && str_contains( $html, 'Put this into practice' ), 'Current position, configured duration and task cue render separately from completed count.' );
sprint_engine_runner_check( str_contains( $html, '>Complete &amp; Continue</button>' ) && str_contains( $html, '>Save &amp; Exit</a>' ) && ! str_contains( $html, 'Continue where you left off' ), 'Intermediate action and exit render without misleading continuity message.' );
sprint_engine_runner_check( preg_match( '~<nav[^>]*>.*?id="se-runner-restart".*?>Save &amp; Exit</a>.*?</nav>~s', $html ) && 1 === substr_count( $html, 'id="se-runner-restart"' ) && 1 === substr_count( $html, 'id="se-runner-action"' ), 'Active Restart sits next to Save & Exit with distinct action IDs.' );
sprint_engine_runner_check( str_contains( $html, '<dialog ' ) && str_contains( $html, 'aria-labelledby="se-restart-title"' ) && str_contains( $html, 'value="cancel" autofocus>Cancel' ) && str_contains( $html, 'value="restart">Restart' ) && str_contains( $html, 'Any unsaved responses will be lost.' ), 'Active restart confirmation explains retained history/unsaved work with explicit Cancel and Restart.' );
$exit_before = sprint_engine_runner_rows( $user, $sprint );
sprint_engine_runner_check( str_contains( sprint_engine_runner_html( $context ), 'href="' . esc_url( \ThePath\SprintEngine\Dashboard\Routes::url() ) . '">Save &amp; Exit</a>' ) && $exit_before === sprint_engine_runner_rows( $user, $sprint ), 'Save & Exit is a canonical Dashboard link and rendering performs no write.' );
sprint_engine_runner_check( str_contains( $html, 'aria-labelledby="se-progress-label"' ) && str_contains( $html, 'role="status" aria-live="polite"' ) && str_contains( $html, 'href="#se-runner-main"' ), 'Semantic progress, live feedback and skip link remain accessible.' );
sprint_engine_runner_check( array() === $completed_calls && array() === $followup_calls && array() === $denied_calls, 'Presentation actions do not fire during progress.' );
$display = $runner->resolve( $slug );
$display['progress']['percentage'] = 33.33;
$display_html = sprint_engine_runner_html( $display );
sprint_engine_runner_check( str_contains( $display_html, '— 33%' ) && str_contains( $display_html, 'value="33.33"' ) && 33.33 === $display['progress']['percentage'], 'Display rounding preserves the canonical numeric value in semantic progress and context.' );
$display['step']['position'] = null;
$display['step']['is_terminal'] = false;
sprint_engine_runner_check( ! str_contains( sprint_engine_runner_html( $display ), 'Step 1 of 5' ), 'Unknown position is omitted rather than guessed.' );
sprint_engine_runner_check( array( array(), array() ) === sprint_engine_runner_rows( $outsider, $sprint ), 'Request-supplied user ID never creates or updates another user progress.' );
$before = sprint_engine_runner_rows( $user, $sprint )[1];
sprint_engine_runner_check( $steps[0] === $runner->resolve( $slug )['step']['id'] && $before === sprint_engine_runner_rows( $user, $sprint )[1], 'Returning resumes same Step without completing it.' );
$service->complete_step( $user, $sprint, $steps[0], test_attempt_id( $user, $sprint ) );
sprint_engine_runner_check( $steps[1] === $runner->resolve( $slug )['step']['id'], 'After service advancement Runner displays the new canonical Step.' );
$middle = $runner->resolve( $slug );
$publication_before = sprint_engine_runner_rows( $user, $sprint );
sprint_engine_runner_check( '' === \ThePath\SprintEngine\Runner\Availability::reason( get_post( $sprint ) ), 'All Published Steps satisfy canonical runtime readiness.' );
foreach ( array( 'draft', 'pending', 'private', 'future' ) as $status ) {
    wp_update_post( array( 'ID' => $steps[1], 'post_status' => $status, 'edit_date' => true, 'post_date' => '2036-01-01 00:00:00', 'post_date_gmt' => '2036-01-01 00:00:00' ) );
    sprint_engine_runner_check( $status === get_post_status( $steps[1] ), 'Step fixture has actual publication state: ' . $status );
    sprint_engine_runner_check( 'Runner unavailable — one or more Sprint Steps are not published.' === \ThePath\SprintEngine\Runner\Availability::reason( get_post( $sprint ) ), 'Canonical Availability explains unpublished Steps without titles/content: ' . $status );
    $blocked = $runner->resolve( $slug );
    sprint_engine_runner_check( 404 === $blocked['status'] && ! isset( $blocked['step'], $blocked['progress'], $blocked['title'] ) && ! str_contains( sprint_engine_runner_html( $blocked ), 'STEP SECRET' ) && $publication_before === sprint_engine_runner_rows( $user, $sprint ), 'Unpublished current Step blocks Runner without private content or progress changes: ' . $status );
}
wp_update_post( array( 'ID' => $steps[1], 'post_status' => 'publish', 'edit_date' => true, 'post_date' => '2020-01-01 00:00:00', 'post_date_gmt' => '2020-01-01 00:00:00' ) );
sprint_engine_runner_check( $steps[1] === $runner->resolve( $slug )['step']['id'] && $publication_before[1] === sprint_engine_runner_rows( $user, $sprint )[1], 'Republishing restores the same current Step and exact Step progress history.' );
$middle_html = sprint_engine_runner_html( $middle );
sprint_engine_runner_check( 2 === $middle['step']['position'] && str_contains( $middle_html, 'Step 2 of 5' ) && str_contains( $middle_html, '1 of 5 complete' ) && ! str_contains( $middle_html, 'About ' ) && ! str_contains( $middle_html, 'Put this into practice' ), 'Middle Step uses canonical counts and omits unset duration and content-mode task cue.' );
$repo = new EnrolmentRepository(); $row = $repo->find( $user, $sprint );
$repo->update( $row['id'], array( 'current_step_id' => $foreign ) );
sprint_engine_runner_check( $steps[1] === $runner->resolve( $slug )['step']['id'] && $steps[1] === $repo->find( $user, $sprint )['current_step_id'], 'Runner invokes SE-003 safe recovery for a stale foreign pointer.' );
wp_update_post( array( 'ID' => $steps[1], 'post_password' => 'test-only' ) );
sprint_engine_runner_check( 404 === $runner->resolve( $slug )['status'], 'Password-protected current Step content is not exposed.' );
wp_update_post( array( 'ID' => $steps[1], 'post_password' => '' ) );

$failure = static function ( $query ) { return str_starts_with( $query, 'SHOW TABLE STATUS WHERE Name IN' ) && str_contains( $query, 'sprint_engine_enrolments' ) ? '' : $query; };
add_filter( 'query', $failure );
$error_context = $runner->resolve( $slug );
remove_filter( 'query', $failure );
sprint_engine_runner_check( 503 === $error_context['status'] && ! str_contains( wp_json_encode( $error_context ), 'SECRET' ) && ! isset( $error_context['progress'] ), 'ProgressService failure becomes safe recoverable 503 context.' );
$error_html = sprint_engine_runner_html( $error_context );
sprint_engine_runner_check( array() === $completed_calls && array() === $followup_calls && array() === $denied_calls && ! isset( $error_context['access_denied_sprint_id'] ), 'Persistence 503 is never treated as access denial.' );
sprint_engine_runner_check( str_contains( $error_html, 'Try again' ) && str_contains( $error_html, 'Back to My Sprints' ) && ! str_contains( $error_html, 'STEP SECRET' ) && ! str_contains( $error_html, 'se-runner-action' ), 'Persistence error renders useful recovery and exit without content or a write control.' );
remove_filter( 'the_content', $content_filter, 99 );

// Request response hooks, template replacement validation and asset scoping.
$runner->prepare();
$template = $runner->template( '/unrelated/theme.php' );
sprint_engine_runner_check( str_ends_with( wp_normalize_path( $template ), '/sprint-engine/templates/runner.php' ) && is_readable( $template ), 'Matched request selects the shipped plugin template.' );
sprint_engine_runner_check( defined( 'DONOTCACHEPAGE' ) && DONOTCACHEPAGE && ! is_admin_bar_showing() && ! has_action( 'template_redirect', 'redirect_canonical' ), 'Member response opts out of page cache, admin toolbar and normal canonical redirects.' );
sprint_engine_runner_check( str_contains( $runner->document_title( 'Normal' ), 'Runner <em>Title</em>' ), 'Authorized document title identifies the Sprint.' );
$runner->enqueue();
sprint_engine_runner_check( wp_style_is( 'sprint-engine-runner', 'enqueued' ), 'Runner request enqueues its own stylesheet.' );
sprint_engine_runner_check( wp_styles()->registered['sprint-engine-runner']->ver === ThePath\SprintEngine\Assets::version( 'assets/css/runner.css' ) && wp_scripts()->registered['sprint-engine-runner']->ver === ThePath\SprintEngine\Assets::version( 'assets/js/runner.js' ), 'Runner enqueues content-fingerprinted CSS and JavaScript.' );
$replacement = WP_PLUGIN_DIR . '/sprint-engine/templates/se004-test-template.php';
file_put_contents( $replacement, '<?php /* Disposable controlled replacement. */' );
$replacement_filter = static function ( $default, $context ) use ( $replacement ) { return $replacement; };
add_filter( 'sprint_engine/runner_template', $replacement_filter, 10, 2 );
sprint_engine_runner_check( realpath( $replacement ) === $runner->template_path( $context ), 'Template filter accepts readable PHP within controlled plugin code.' );
remove_filter( 'sprint_engine/runner_template', $replacement_filter );
unlink( $replacement );
foreach ( array( null, array(), false, "bad\0path", 'https://example.invalid/runner.php', 'php://filter/resource=runner.php', ABSPATH . 'wp-config.php', __FILE__, ABSPATH, __DIR__ . '/missing.php', WP_PLUGIN_DIR . '/sprint-engine/assets/css/runner.css' ) as $invalid ) {
	$bad = static function () use ( $invalid ) { return $invalid; };
	add_filter( 'sprint_engine/runner_template', $bad );
	sprint_engine_runner_check( realpath( $template ) === realpath( $runner->template_path( $context ) ), 'Invalid/outside/non-PHP template filter fails back to shipped template.' );
	remove_filter( 'sprint_engine/runner_template', $bad );
}
sprint_engine_runner_request( '/ordinary-page/' );
wp_dequeue_style( 'sprint-engine-runner' );
$runner->enqueue();
sprint_engine_runner_check( ! wp_style_is( 'sprint-engine-runner', 'enqueued' ) && '/normal.php' === $runner->template( '/normal.php' ) && 'Normal' === $runner->document_title( 'Normal' ), 'Assets, template and title hooks leave unrelated URLs alone.' );
sprint_engine_runner_check( ! str_contains( file_get_contents( $template ), 'get_header(' ) && ! str_contains( file_get_contents( $template ), 'get_footer(' ), 'Runner template has no normal theme header/footer dependency.' );
$rest_routes = array_keys( rest_get_server()->get_routes() );
sprint_engine_runner_check( 4 === count( array_filter( $rest_routes, static function ( $route ) { return str_starts_with( $route, '/sprint-engine/' ); } ) ), 'Only the member namespace index and three write routes are registered.' );
sprint_engine_runner_check( '2' === SPRINT_ENGINE_SCHEMA_VERSION, 'Attempt-aware schema version.' );

if ( getenv( 'SE_TEST_HTTP_BASE' ) ) {
	$path = '/sprint/' . $slug . '/';
	$response = sprint_engine_runner_http( $path );
	sprint_engine_runner_check( 302 === wp_remote_retrieve_response_code( $response ), 'HTTP anonymous canonical request redirects to login.' );
	parse_str( wp_parse_url( wp_remote_retrieve_header( $response, 'location' ), PHP_URL_QUERY ), $return_query );
	sprint_engine_runner_check( Routes::url( $slug ) === $return_query['redirect_to'], 'HTTP login redirect preserves full canonical return destination.' );
	sprint_engine_runner_check( ! str_contains( wp_remote_retrieve_body( $response ), 'SECRET' ), 'HTTP anonymous response reveals no Step content.' );
	$response = sprint_engine_runner_http( $path, $outsider ); $body = wp_remote_retrieve_body( $response );
	sprint_engine_runner_check( 200 === wp_remote_retrieve_response_code( $response ) && str_contains( $body, 'Ready to start' ) && ! str_contains( $body, 'STEP SECRET' ), 'HTTP authenticated unstarted user sees standalone Start state.' );
	sprint_engine_runner_check( array( array(), array() ) === sprint_engine_runner_rows( $outsider, $sprint ), 'Actual HTTP Start page creates no progress.' );
	sprint_engine_runner_check( str_contains( wp_remote_retrieve_header( $response, 'cache-control' ), 'private' ) && str_contains( wp_remote_retrieve_header( $response, 'cache-control' ), 'no-store' ) && 'noindex, nofollow' === wp_remote_retrieve_header( $response, 'x-robots-tag' ), 'HTTP member responses prevent shared caching and indexing.' );
	$response = sprint_engine_runner_http( $path . '?user_id=' . $outsider . '&step_id=' . $foreign . '&sprint_engine_sprint_slug=wrong&feed=rss2', $user );
	$body = wp_remote_retrieve_body( $response );
	sprint_engine_runner_check( 200 === wp_remote_retrieve_response_code( $response ) && str_contains( $body, 'STEP SECRET CONTENT 2' ) && ! str_contains( $body, 'STEP SECRET CONTENT 1' ) && ! str_contains( $body, 'FOREIGN SECRET' ), 'HTTP tampering still renders only authenticated canonical current Step.' );
	sprint_engine_runner_check( str_contains( $body, 'runner.css' ) && str_contains( $body, 'se-runner--in_progress' ) && str_contains( $body, '<audio controls' ), 'HTTP Runner includes shell stylesheet and native audio markup.' );
	$response = sprint_engine_runner_http( rtrim( $path, '/' ), $user );
	sprint_engine_runner_check( 200 === wp_remote_retrieve_response_code( $response ) && str_contains( wp_remote_retrieve_body( $response ), 'STEP SECRET CONTENT 2' ), 'HTTP route works without trailing slash and without theme canonical redirection.' );
	$response = sprint_engine_runner_http( '/sprint/no-such-sprint/', $user );
	sprint_engine_runner_check( 404 === wp_remote_retrieve_response_code( $response ) && str_contains( wp_remote_retrieve_body( $response ), 'Sprint unavailable' ), 'HTTP unknown Sprint renders safe standalone 404.' );
	wp_update_post( array( 'ID' => $steps[0], 'post_name' => 'private-step-' . $steps[0] ) );
	foreach ( array( '%2fsecret', '..%5csecret', '%00bad', get_post_field( 'post_name', $steps[0] ) ) as $bad_slug ) {
		$response = sprint_engine_runner_http( '/sprint/' . $bad_slug . '/', $user );
		sprint_engine_runner_check( 404 === wp_remote_retrieve_response_code( $response ) && ! str_contains( wp_remote_retrieve_body( $response ), 'STEP SECRET' ), 'HTTP encoded/malformed/Step slug exposes no private content.' );
	}
	$response = sprint_engine_runner_http( '/?sprint_engine_sprint_slug=' . $slug . '&step_id=' . $steps[0], $user );
	sprint_engine_runner_check( ! str_contains( wp_remote_retrieve_body( $response ), 'var sprintEngineRunner' ) && ! str_contains( wp_remote_retrieve_body( $response ), 'STEP SECRET' ), 'HTTP query flag cannot invoke Runner on the homepage.' );
	$response = sprint_engine_runner_http( '/' );
	sprint_engine_runner_check( ! str_contains( wp_remote_retrieve_body( $response ), 'assets/css/runner.css' ), 'HTTP unrelated homepage does not enqueue Runner CSS.' );
} else {
	echo "SKIP: HTTP smoke checks; set SE_TEST_HTTP_BASE to the disposable loopback server.\n";
}

foreach ( array_slice( $steps, 1 ) as $step ) {
	$active = $runner->resolve( $slug );
	sprint_engine_runner_check( ( $step === $steps[4] ) === $active['step']['is_terminal'], 'is_terminal still identifies only the validated explicit terminal link.' );
	if ( $step === $steps[4] ) {
		sprint_engine_runner_check( str_contains( sprint_engine_runner_html( $active ), '>Complete Sprint</button>' ), 'Terminal Step offers Complete Sprint using the existing action ID.' );
		sprint_engine_runner_request( '/sprint/' . $slug . '/' ); $runner->prepare(); $runner->enqueue();
		sprint_engine_runner_check( str_contains( str_replace( '\/', '/', wp_scripts()->get_data( 'sprint-engine-runner', 'data' ) ), 'steps/' . $step . '/complete' ), 'Terminal action uses the same existing completion endpoint.' );
	}
	$service->complete_step( $user, $sprint, $step, test_attempt_id( $user, $sprint ) );
}
wp_set_current_user( $user );
$before = sprint_engine_runner_rows( $user, $sprint );
$context = $runner->resolve( $slug ); $html = sprint_engine_runner_html( $context );
sprint_engine_runner_check( array( array( 'sprint_id' => $sprint, 'attempt_id' => $context['progress']['attempt_id'], 'attempt_number' => 1 ) ) === $completed_calls, 'Completion action fires once with the current member canonical completed-attempt identity.' );
sprint_engine_runner_check( array( array( $completed_calls[0] ) ) === $followup_calls, 'Follow-up receives exactly one array argument identical to the existing canonical completion payload.' );
sprint_engine_runner_check( 'completed' === $context['state'] && ! isset( $context['step'] ) && 100.0 === $context['progress']['percentage'], 'Completed context contains 100% progress and no reopened Step.' );
sprint_engine_runner_check( str_contains( $html, '100%' ) && str_contains( $html, 'Restart Sprint' ) && str_contains( $html, '>Back to My Sprints</a>' ), 'Completion shows whole 100%, safe exit and restart button.' );
sprint_engine_runner_check( str_contains( $html, 'href="' . esc_url( \ThePath\SprintEngine\Dashboard\Routes::url() ) . '">Back to My Sprints</a>' ), 'Runner completion returns to the canonical Dashboard separately from the authored CTA.' );
sprint_engine_runner_check( str_contains( $html, 'Sprint complete' ) && ! str_contains( $html, 'STEP SECRET' ) && $before === sprint_engine_runner_rows( $user, $sprint ), 'Completion view is read-only and reveals no Step content.' );
sprint_engine_runner_check( str_contains( $html, 'You have completed this Sprint. Your progress is saved.' ) && ! str_contains( $html, 'se-runner__button--primary' ), 'Blank completion configuration preserves default text and has no CTA.' );
add_shortcode( 'sprint_engine_completion_test', static function () { throw new RuntimeException( 'Completion executed shortcode' ); } );
update_post_meta( $sprint, '_sprint_engine_completion_message', "Well done & thanks.\n\n[sprint_engine_completion_test] <b>Next</b>" );
update_post_meta( $sprint, '_sprint_engine_completion_cta_label', 'Feedback & next' );
update_post_meta( $sprint, '_sprint_engine_completion_cta_url', 'https://example.org/feedback?a=1&b=2' );
$html = sprint_engine_runner_html( $runner->resolve( $slug ) );
sprint_engine_runner_check( strpos( $html, 'Feedback &amp; next</a>' ) < strpos( $html, '<p>COMPLETED ACTION</p>' ) && strpos( $html, '<p>COMPLETED ACTION</p>' ) < strpos( $html, '>Restart Sprint</button>' ), 'Completion action follows creator message/CTA and precedes intact restart and Dashboard controls.' );
sprint_engine_runner_check( strpos( $html, '>Restart Sprint</button>' ) < strrpos( $html, '>Back to My Sprints</a>' ) && preg_match( '~>Back to My Sprints</a>\s*<p><a href="#se-runner-main">FOLLOWUP ACTION</a></p>\s*</div>~', $html ), 'Follow-up fires immediately after the completion Dashboard link, after restart and inside the completion container.' );
remove_action( 'sprint_engine/runner_completed_actions', $completed_listener );
remove_action( 'sprint_engine/runner_completed_followup_actions', $followup_listener );
sprint_engine_runner_check( sprint_engine_runner_shell( str_replace( array( '<p>COMPLETED ACTION</p>', '<p><a href="#se-runner-main">FOLLOWUP ACTION</a></p>' ), '', $html ) ) === sprint_engine_runner_shell( sprint_engine_runner_html( $runner->resolve( $slug ) ) ), 'No completion listeners preserve the existing completed shell.' );
$completed_calls = array(); $followup_calls = array(); add_action( 'sprint_engine/runner_completed_actions', $completed_listener );
add_action( 'sprint_engine/runner_completed_followup_actions', $followup_listener );
$_REQUEST = array( 'user_id' => $outsider, 'sprint_id' => $other, 'attempt_id' => 999, 'attempt_number' => 99 );
for ( $render = 0; $render < 2; ++$render ) { sprint_engine_runner_html( $runner->resolve( $slug ) ); }
sprint_engine_runner_check( 2 === count( $completed_calls ) && $completed_calls[0] === $completed_calls[1] && $context['progress']['attempt_id'] === $completed_calls[0]['attempt_id'] && 1 === $completed_calls[0]['attempt_number'] && $sprint === $completed_calls[0]['sprint_id'] && $before === sprint_engine_runner_rows( $user, $sprint ), 'Repeated completion renders ignore submitted attempt selectors and preserve every progress row/timestamp.' );
sprint_engine_runner_check( array( array( $completed_calls[0] ), array( $completed_calls[1] ) ) === $followup_calls, 'Both repeated follow-up renders ignore every request-supplied identity.' );
$_REQUEST = array();
$completed_calls = array(); $followup_calls = array();
wp_set_current_user( 0 ); sprint_engine_runner_html( $context );
wp_set_current_user( $user );
add_filter( 'sprint_engine/user_can_access_sprint', $deny );
sprint_engine_runner_html( $runner->resolve( $slug ) );
remove_filter( 'sprint_engine/user_can_access_sprint', $deny );
sprint_engine_runner_check( '' === sprint_engine_runner_html( array() ) && '' === sprint_engine_runner_html( array( 'state' => null ) ) && array() === $completed_calls && array() === $followup_calls, 'Anonymous completed, denied completed, missing and malformed template contexts never fire either completion action.' );
$render_writes = array();
$render_context = $runner->resolve( $slug );
$track_render_writes = static function ( $sql ) use ( &$render_writes ) { if ( preg_match( '/^\s*(INSERT|UPDATE|DELETE|REPLACE|ALTER|CREATE|DROP)\b/i', $sql ) ) { $render_writes[] = $sql; } return $sql; };
add_filter( 'query', $track_render_writes );
sprint_engine_runner_html( $render_context );
remove_filter( 'query', $track_render_writes );
sprint_engine_runner_check( array() === $render_writes && $before === sprint_engine_runner_rows( $user, $sprint ), 'Rendering both completion hooks performs no database mutations, including progress or entitlement writes.' );
remove_action( 'sprint_engine/runner_completed_actions', $completed_listener );
remove_action( 'sprint_engine/runner_completed_followup_actions', $followup_listener );
remove_action( 'sprint_engine/runner_access_denied_actions', $denied_listener );
sprint_engine_runner_check( str_contains( $html, '<p>Well done &amp; thanks.</p>' ) && str_contains( $html, '[sprint_engine_completion_test] Next' ) && ! str_contains( $html, '<b>Next</b>' ), 'Completion is escaped multiline text without HTML or shortcode execution.' );
sprint_engine_runner_check( str_contains( $html, 'se-runner__button--primary' ) && str_contains( $html, 'Feedback &amp; next</a>' ) && str_contains( $html, esc_url( 'https://example.org/feedback?a=1&b=2' ) ), 'Completed Runner renders valid primary CTA label and destination.' );
foreach ( array( array( '', 'https://example.org' ), array( 'Feedback', '' ), array( 'Feedback', 'javascript:alert(1)' ) ) as $cta ) {
 update_post_meta( $sprint, '_sprint_engine_completion_cta_label', $cta[0] ); update_post_meta( $sprint, '_sprint_engine_completion_cta_url', $cta[1] );
 sprint_engine_runner_check( ! str_contains( sprint_engine_runner_html( $runner->resolve( $slug ) ), 'se-runner__button--primary' ), 'Partial or unsafe CTA omitted.' );
}
sprint_engine_runner_check( $before === sprint_engine_runner_rows( $user, $sprint ) && 100.0 === $runner->resolve( $slug )['progress']['percentage'], 'Completion customisation preserves exact progress and 100 percent.' );
remove_shortcode( 'sprint_engine_completion_test' );
if ( getenv( 'SE_TEST_HTTP_BASE' ) ) {
	$response = sprint_engine_runner_http( '/sprint/' . $slug . '/', $user );
	sprint_engine_runner_check( 200 === wp_remote_retrieve_response_code( $response ) && str_contains( wp_remote_retrieve_body( $response ), 'Sprint complete' ) && ! str_contains( wp_remote_retrieve_body( $response ), 'STEP SECRET' ), 'HTTP completed member sees completion instead of a reopened Step.' );
	// Trusted callbacks must exist in the HTTP worker, not just this CLI process.
	if ( ! is_dir( WPMU_PLUGIN_DIR ) ) { mkdir( WPMU_PLUGIN_DIR, 0755, true ); }
	$fixture_file = WPMU_PLUGIN_DIR . '/se017-presentation-' . wp_generate_password( 12, false ) . '.php';
	$fixture_code = '<?php
add_filter("sprint_engine/user_can_access_sprint", static function ($allowed, $user, $id) { return TARGET === $id ? false : $allowed; }, 10, 3);
add_filter("sprint_engine/runner_show_public_access_gate", static function ($default, $id) { return TARGET === $id ? GATE : $default; }, 10, 2);
add_action("sprint_engine/runner_access_denied_actions", static function ($id) { echo "<p>HTTP DENIAL ID " . (int) $id . "</p>"; return true; });';
	try {
		foreach ( array( 'false', 'null', '1', '"true"', 'array(true)', 'true' ) as $gate_literal ) {
			file_put_contents( $fixture_file, str_replace( array( 'TARGET', 'GATE' ), array( (string) $sprint, $gate_literal ), $fixture_code ) );
			$response = sprint_engine_runner_http( '/sprint/' . $slug . '/?sprint_id=' . $other . '&attempt_id=999&user_id=' . $outsider );
			$body = wp_remote_retrieve_body( $response );
			if ( 'true' !== $gate_literal ) {
				sprint_engine_runner_check( 302 === wp_remote_retrieve_response_code( $response ) && ! str_contains( $body, 'HTTP DENIAL' ), 'HTTP non-boolean opt-in preserves the default login redirect: ' . $gate_literal );
				continue;
			}
			sprint_engine_runner_check( 403 === wp_remote_retrieve_response_code( $response ) && '' === wp_remote_retrieve_header( $response, 'location' ) && str_contains( $body, 'HTTP DENIAL ID ' . $sprint ) && str_contains( $body, '>Login</a>' ) && str_contains( $body, esc_url( wp_login_url( Routes::url( $slug ) ) ) ), 'HTTP exact opt-in renders recognised 403 and canonical Login link instead of redirect, ignoring request identities.' );
			sprint_engine_runner_check( str_contains( wp_remote_retrieve_header( $response, 'cache-control' ), 'private, no-store' ) && 'noindex, nofollow' === wp_remote_retrieve_header( $response, 'x-robots-tag' ) && str_contains( $body, '<meta name="robots" content="noindex, nofollow">' ), 'HTTP public gateway is private/no-store and noindex in headers and metadata.' );
			sprint_engine_runner_check( ! str_contains( $body, 'SECRET' ) && ! str_contains( $body, 'AUTHORIZED OVERVIEW' ) && ! str_contains( $body, 'Runner &lt;em&gt;Title' ) && ! str_contains( $body, 'sprintEngineRunner' ), 'HTTP gateway exposes no Sprint title, overview, Step data, progress controls or REST nonce configuration.' );
		}
		$response = sprint_engine_runner_http( '/sprint/' . $slug . '/', $user ); $body = wp_remote_retrieve_body( $response );
		sprint_engine_runner_check( 403 === wp_remote_retrieve_response_code( $response ) && str_contains( $body, 'HTTP DENIAL ID ' . $sprint ) && str_contains( $body, $denied['message'] ) && ! str_contains( $body, '>Login</a>' ) && ! str_contains( $body, 'SECRET' ) && ! str_contains( $body, 'Sprint complete' ), 'HTTP authenticated AccessManager denial remains generic 403; returning true from presentation action grants nothing.' );
		foreach ( array( 'draft', 'password', 'unlaunchable', 'broken', 'step-draft' ) as $invalid_state ) {
			wp_set_current_user( 1 );
			if ( 'draft' === $invalid_state ) { wp_update_post( array( 'ID' => $sprint, 'post_status' => 'draft' ) ); }
			if ( 'password' === $invalid_state ) { wp_update_post( array( 'ID' => $sprint, 'post_password' => 'test-only' ) ); }
			if ( 'unlaunchable' === $invalid_state ) { Meta::save( $sprint, array( '_sprint_engine_launchable' => false ) ); }
			if ( 'broken' === $invalid_state ) { update_post_meta( $steps[0], '_sprint_engine_next_step_id', $foreign ); }
			if ( 'step-draft' === $invalid_state ) { wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'draft' ) ); }
			foreach ( array( 0, $user ) as $member ) {
				$response = sprint_engine_runner_http( '/sprint/' . $slug . '/', $member ); $body = wp_remote_retrieve_body( $response );
				sprint_engine_runner_check( 404 === wp_remote_retrieve_response_code( $response ) && ! str_contains( $body, 'HTTP DENIAL' ) && ! str_contains( $body, '>Login</a>' ) && ! str_contains( $body, 'SECRET' ), 'HTTP unavailable content never advertises a denial action: ' . $invalid_state . '/' . $member );
			}
			wp_update_post( array( 'ID' => $sprint, 'post_status' => 'publish', 'post_password' => '', 'post_name' => $slug ) );
			Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
			wp_set_current_user( 1 ); $manager->apply_linear_order( $sprint, $steps ); wp_set_current_user( $user );
			wp_update_post( array( 'ID' => $steps[0], 'post_status' => 'publish' ) );
		}
		sprint_engine_runner_check( $before === sprint_engine_runner_rows( $user, $sprint ), 'HTTP denial/gateway/readiness cases preserve exact completed progress and timestamps.' );
	} finally {
		unlink( $fixture_file );
	}
}

// A historical completed attempt must not select the new completion extension.
$first_attempt = $context['progress']['attempt_id'];
$restarted = $service->restart_sprint( $user, $sprint, test_attempt_id( $user, $sprint ) );
$completed_calls = array(); $followup_calls = array(); add_action( 'sprint_engine/runner_completed_actions', $completed_listener );
add_action( 'sprint_engine/runner_completed_followup_actions', $followup_listener );
sprint_engine_runner_html( $runner->resolve( $slug ) );
sprint_engine_runner_check( 2 === $restarted['attempt_number'] && array() === $completed_calls && array() === $followup_calls, 'Restarted attempt in progress never fires completion action for its historical completed attempt.' );
foreach ( $steps as $step ) { $service->complete_step( $user, $sprint, $step, test_attempt_id( $user, $sprint ) ); }
sprint_engine_runner_html( $runner->resolve( $slug ) );
sprint_engine_runner_check( array( array( 'sprint_id' => $sprint, 'attempt_id' => $restarted['attempt_id'], 'attempt_number' => 2 ) ) === $completed_calls && $first_attempt !== $restarted['attempt_id'], 'New completion context selects canonical Attempt 2 rather than the historical completed attempt.' );
sprint_engine_runner_check( array( array( $completed_calls[0] ) ) === $followup_calls, 'Follow-up also selects only canonical Attempt 2.' );
wp_set_current_user( $outsider );
$service->start_sprint( $outsider, $sprint );
foreach ( $steps as $step ) { $service->complete_step( $outsider, $sprint, $step, test_attempt_id( $outsider, $sprint ) ); }
$member_attempts = array();
foreach ( array( $user => $outsider, $outsider => $user ) as $member => $supplied_member ) {
	wp_set_current_user( $member );
	$completed_calls = array(); $followup_calls = array();
	$_GET = $_REQUEST = array( 'user_id' => $supplied_member, 'attempt_id' => $restarted['attempt_id'], 'attempt_number' => 2, 'sprint_id' => $other );
	$member_state = $service->get_state( $member, $sprint );
	sprint_engine_runner_html( $runner->resolve( $slug ) );
	$expected_completed = array( 'sprint_id' => $sprint, 'attempt_id' => $member_state['attempt_id'], 'attempt_number' => $member_state['attempt_number'] );
	sprint_engine_runner_check( array( $expected_completed ) === $completed_calls && array( array( $expected_completed ) ) === $followup_calls, 'Both completion actions bind to authenticated member despite another member identity: ' . $member );
	$member_attempts[] = $member_state['attempt_id'];
}
sprint_engine_runner_check( $member_attempts[0] !== $member_attempts[1], 'Two independent completed members receive distinct canonical attempt IDs.' );
$_GET = $_REQUEST = array(); wp_set_current_user( $user );
remove_action( 'sprint_engine/runner_completed_actions', $completed_listener );
remove_action( 'sprint_engine/runner_completed_followup_actions', $followup_listener );
$before = sprint_engine_runner_rows( $user, $sprint );

wp_set_current_user( 1 );
Plugin::deactivate();
sprint_engine_runner_check( ! isset( get_option( 'rewrite_rules' )[Routes::RULE] ), 'Deactivation removes Runner rewrite without deleting content or progress.' );
Plugin::activate();
sprint_engine_runner_check( isset( get_option( 'rewrite_rules' )[Routes::RULE] ) && $before === sprint_engine_runner_rows( $user, $sprint ), 'Reactivation restores routing and retains completed progress.' );
$wp_rewrite->set_permalink_structure( $old_permalink );
flush_rewrite_rules( false );
foreach ( array( $user, $outsider ) as $id ) {
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $id ) );
	$wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $id ) );
	wp_delete_user( $id );
}
foreach ( array_merge( $steps, array( $foreign, $sprint, $other ) ) as $id ) { wp_delete_post( $id, true ); }
$_GET = array(); $_REQUEST = array();
echo 'SE-004 integration checks completed: ' . $checks . " assertions.\n";
ob_end_flush();
