<?php
/** SE-020 disposable browser fixture; author edits use real Core operations. */
$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require $root . '/wp-load.php';
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Runner\Routes;
$manager = new StructureManager(); $service = new ProgressService();
function browser_recovery_rows( $user, $sprint ) {
	global $wpdb;
	return array_map( static function ( $table ) use ( $wpdb, $user, $sprint ) { return $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}$table WHERE user_id=%d AND sprint_id=%d ORDER BY id", $user, $sprint ), ARRAY_A ); }, array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) );
}
wp_set_current_user( 1 );
if ( isset( $argv[1] ) ) {
	$f = json_decode( file_get_contents( $argv[1] ), true, 512, JSON_THROW_ON_ERROR );
	if ( 'edit' === $argv[2] ) {
		// The initial Runner visit legitimately updates resume activity.
		$f['before'] = browser_recovery_rows( $f['user'], $f['sprint'] );
		file_put_contents( $argv[1], wp_json_encode( $f ) );
		$x = $manager->quick_add( $f['sprint'], 'New required Step X' );
		wp_update_post( array( 'ID' => $x, 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>New required instruction X.</p><!-- /wp:paragraph -->' ) );
		$result = $manager->apply_linear_order( $f['sprint'], array( $f['steps'][0], $x, $f['steps'][1], $f['steps'][2], $f['steps'][3] ) );
		if ( true !== $result || $f['before'] !== browser_recovery_rows( $f['user'], $f['sprint'] ) ) { throw new RuntimeException( 'Author edit mutated progress.' ); }
		echo wp_json_encode( array( 'inserted' => $x ) );
	} elseif ( 'last-outstanding' === $argv[2] ) {
		$before = browser_recovery_rows( $f['user'], $f['sprint'] );
		$order = wp_list_pluck( Meta::steps( $f['sprint'] ), 'ID' );
		$last = $f['steps'][3];
		$result = $manager->apply_linear_order( $f['sprint'], array_merge( array( $last ), array_values( array_diff( $order, array( $last ) ) ) ) );
		$state = $service->get_state( $f['user'], $f['sprint'] );
		if ( true !== $result || $last !== $state['current_step_id'] || 1 !== $state['total_steps'] - $state['completed_steps'] || ! get_post_meta( $last, '_sprint_engine_next_step_id', true ) || $before !== browser_recovery_rows( $f['user'], $f['sprint'] ) ) { throw new RuntimeException( 'Nonterminal last-outstanding fixture failed.' ); }
		echo "PASS: Last outstanding Step moved before completed Steps without changing progress.\n";
	} elseif ( 'stale' === $argv[2] ) {
		if ( $f['before'] !== browser_recovery_rows( $f['user'], $f['sprint'] ) ) { throw new RuntimeException( 'Stale HTTP completion mutated progress.' ); }
		echo "PASS: Stale HTTP completion preserves exact progress rows.\n";
	} elseif ( 'verify' === $argv[2] ) {
		$state = $service->get_state( $f['user'], $f['sprint'] ); $rows = browser_recovery_rows( $f['user'], $f['sprint'] );
		if ( 'completed' !== $state['status'] || $state['attempt_id'] !== $f['attempt'] || 1 !== $state['attempt_number'] || 5 !== $state['completed_steps'] || 1 !== count( $rows[0] ) ) { throw new RuntimeException( 'Browser revised Sprint failed.' ); }
		foreach ( $f['before'][1] as $row ) {
			$matches = array_values( array_filter( $rows[1], static function ( $item ) use ( $row ) { return $row['id'] === $item['id']; } ) );
			if ( 1 !== count( $matches ) || $row['started_at'] !== $matches[0]['started_at'] || ( 'completed' === $row['status'] && $row !== $matches[0] ) ) { throw new RuntimeException( 'Browser history was rewritten.' ); }
		}
		echo "PASS: Browser retains same attempt, exact completed history and original started rows; revised Sprint completed.\n";
	}
	exit;
}
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'SE-020 browser recovery', 'post_name' => 'se020-browser-' . wp_generate_password( 8, false ) ) );
$steps = array();
foreach ( array( 'A', 'B', 'C', 'D' ) as $label ) {
	$step = $manager->quick_add( $sprint, 'Recovery Step ' . $label ); $steps[] = $step;
	wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>Instruction ' . $label . '.</p><!-- /wp:paragraph -->' ) );
}
$manager->apply_linear_order( $sprint, array( $steps[0], $steps[2], $steps[1], $steps[3] ) );
Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
$user = wp_create_user( 'se020-browser-' . wp_generate_password( 8, false ), wp_generate_password() ); wp_set_current_user( $user );
$state = $service->start_sprint( $user, $sprint );
foreach ( array( $steps[0], $steps[2] ) as $step ) { $state = $service->complete_step( $user, $sprint, $step, $state['attempt_id'] ); }
wp_set_current_user( 1 ); $manager->apply_linear_order( $sprint, $steps ); wp_set_current_user( $user );
$expires = time() + 3600; $token = WP_Session_Tokens::get_instance( $user )->create( $expires );
echo wp_json_encode( array( 'user' => $user, 'sprint' => $sprint, 'steps' => $steps, 'attempt' => $state['attempt_id'], 'before' => browser_recovery_rows( $user, $sprint ), 'url' => Routes::url( get_post_field( 'post_name', $sprint ) ), 'cookie' => array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user, $expires, 'logged_in', $token ), 'domain' => '127.0.0.1', 'path' => '/', 'httpOnly' => true, 'secure' => false, 'sameSite' => 'Lax' ) ) );
