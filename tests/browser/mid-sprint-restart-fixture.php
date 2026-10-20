<?php
/** Disposable SE-019 browser history verification; never packaged. */
if ( 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
if ( ! isset( $argv[1] ) || 'verify' !== ( $argv[2] ?? '' ) ) {
	require __DIR__ . '/fixture.php';
	exit;
}
require getenv( 'SE_TEST_WP_ROOT' ) . '/wp-load.php';
$fixture = json_decode( file_get_contents( $argv[1] ), true );
$sprint = end( $fixture['posts'] );
$history = ( new ThePath\SprintEngine\Progress\ProgressService() )->get_attempt_history( $fixture['user'], $sprint );
if ( array( 'completed', 'restarted', 'restarted' ) !== array_column( $history, 'status' ) || array( 3, 2, 1 ) !== array_column( $history, 'attempt_number' ) ) { throw new RuntimeException( 'Browser attempt status/sequence mismatch.' ); }
foreach ( array( 1 => 1, 2 => 0, 3 => 6 ) as $attempt => $expected ) {
	$count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sprint_engine_step_progress WHERE user_id=%d AND sprint_id=%d AND attempt_number=%d AND status='completed'", $fixture['user'], $sprint, $attempt ) );
	if ( $expected !== (int) $count ) { throw new RuntimeException( 'Historical browser progress was changed by a stale write.' ); }
}
if ( null !== $history[1]['completed_at'] || null !== $history[2]['completed_at'] ) { throw new RuntimeException( 'Restart fabricated completion timestamps.' ); }
echo "PASS: Browser retains Attempts 1/2 as restarted with original completion counts (1/0), null completion timestamps and completed Attempt 3 (6 Steps).\n";
