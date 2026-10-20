<?php
/** SE-018 disposable presentation fixture; never packaged. */
if ( 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
$helper = getenv( 'SE_TEST_WP_ROOT' ) . '/wp-content/mu-plugins/sprint-engine-completion-followup-fixture.php';
if ( isset( $argv[1] ) ) {
    if ( 'verify-read' === ( $argv[2] ?? '' ) ) {
        require getenv( 'SE_TEST_WP_ROOT' ) . '/wp-load.php';
        $fixture = json_decode( file_get_contents( $argv[1] ), true );
        foreach ( array( 'sprint_engine_enrolments', 'sprint_engine_step_progress' ) as $index => $table ) {
            $expected = array_values( array_filter( $fixture['snapshot'][$index], static function ( $row ) use ( $fixture ) { return (int) $row['sprint_id'] === $fixture['ids']['completed']; } ) );
            $actual = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}{$table} WHERE user_id IN (%d,%d) AND sprint_id = %d ORDER BY id", $fixture['user'], $fixture['other'], $fixture['ids']['completed'] ), ARRAY_A );
            if ( $expected !== $actual ) { throw new RuntimeException( 'Completion browser reads mutated canonical attempts/progress.' ); }
        }
        echo "PASS: Both members' exact completed enrolment/Step rows and timestamps unchanged by browser rendering.\n";
        exit;
    }
    if ( 'no-extension' === ( $argv[2] ?? '' ) || 'cleanup' === ( $argv[2] ?? '' ) ) {
        if ( is_file( $helper ) ) { unlink( $helper ); }
        if ( 'no-extension' === $argv[2] ) { exit; }
    }
    require __DIR__ . '/dashboard-fixture.php';
    exit;
}
ob_start();
require __DIR__ . '/dashboard-fixture.php';
$fixture = json_decode( ob_get_clean(), true );
$service = new ThePath\SprintEngine\Progress\ProgressService();
wp_set_current_user( $fixture['other'] );
$service->start_sprint( $fixture['other'], $fixture['ids']['completed'] );
foreach ( $fixture['steps']['completed'] as $step ) { $service->complete_step( $fixture['other'], $fixture['ids']['completed'], $step, test_attempt_id( $fixture['other'], $fixture['ids']['completed'] ) ); }
foreach ( array( 'user', 'other' ) as $member ) {
    $state = $service->get_state( $fixture[$member], $fixture['ids']['completed'] );
    $fixture['completed'][$member] = array( 'sprint_id' => $fixture['ids']['completed'], 'attempt_id' => $state['attempt_id'], 'attempt_number' => $state['attempt_number'] );
}
$fixture['snapshot'] = sprint_engine_dashboard_browser_snapshot( $fixture );
file_put_contents( $helper, <<<'PHP'
<?php
// SE018 disposable callbacks; trusted presentation only.
foreach ( array( 'runner_completed_actions' => 'Existing completion action', 'runner_completed_followup_actions' => 'Recommended next Sprint' ) as $hook => $label ) {
    add_action( 'sprint_engine/' . $hook, static function ( $completed ) use ( $hook, $label ) {
        printf( '<p class="%s" data-context="%s"><a class="se-runner__button se-runner__button--secondary" href="%s">%s</a></p>', esc_attr( $hook ), esc_attr( wp_json_encode( $completed ) ), esc_url( ThePath\SprintEngine\Dashboard\Routes::url() ), esc_html( $label ) );
    } );
}
PHP
);
echo wp_json_encode( $fixture );
