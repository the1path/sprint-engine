<?php
/** Disposable SE-006 browser fixture. Prints a local JSON cookie/URL manifest. */
$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if ( isset( $argv[1] ) ) {
    $fixture = json_decode( file_get_contents( $argv[1] ), true );
    if ( isset( $argv[2] ) && 'retention' === $argv[2] ) {
        require_once ABSPATH . 'wp-admin/includes/plugin.php';
        $snapshot = static function () use ( $wpdb, $fixture ) {
            return array( $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id", ARRAY_A ), $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id", ARRAY_A ), array_map( 'get_post', $fixture['posts'] ), array_map( 'get_post_meta', $fixture['posts'] ) );
        };
        $before = $snapshot();
        deactivate_plugins( 'sprint-engine/sprint-engine.php' );
        $result = activate_plugin( 'sprint-engine/sprint-engine.php' );
        if ( is_wp_error( $result ) || $before != $snapshot() ) { throw new RuntimeException( 'Journey retention failed' ); }
        $service = new ThePath\SprintEngine\Progress\ProgressService();
        $sprint = end( $fixture['posts'] );
        if ( 'completed' !== $service->get_state( $fixture['user'], $sprint )['status'] || $fixture['posts'][0] !== $service->get_state( $fixture['second_user'], $sprint )['current_step_id'] ) { throw new RuntimeException( 'Two-user state retention failed' ); }
        $attempts = $wpdb->get_results( $wpdb->prepare( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments WHERE user_id=%d AND sprint_id=%d ORDER BY attempt_number", $fixture['user'], $sprint ), ARRAY_A );
        if ( 2 !== count( $attempts ) || array( 'completed', 'completed' ) !== array_column( $attempts, 'status' ) || array( 1, 2 ) !== array_map( 'intval', array_column( $attempts, 'attempt_number' ) ) ) { throw new RuntimeException( 'Browser attempts not preserved' ); }
        foreach ( array( 1, 2 ) as $attempt ) {
            $count = $wpdb->get_var( $wpdb->prepare( "SELECT COUNT(*) FROM {$wpdb->prefix}sprint_engine_step_progress WHERE user_id=%d AND sprint_id=%d AND attempt_number=%d AND status='completed'", $fixture['user'], $sprint, $attempt ) );
            if ( 6 !== (int) $count ) { throw new RuntimeException( 'Browser historical progress missing' ); }
        }
        echo "PASS: Both completed browser attempts retain six independent Step completions.\n";
        echo "PASS: Two-user browser journey content, metadata and exact progress rows retained after deactivate/reactivate.\n";
        exit;
    }
    foreach ( $fixture['posts'] as $id ) { wp_delete_post( $id, true ); }
    $wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $fixture['user'] ) );
    $wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $fixture['user'] ) );
    wp_delete_user( $fixture['user'] );
    if ( isset( $fixture['second_user'] ) ) {
        $wpdb->delete( $wpdb->prefix . 'sprint_engine_step_progress', array( 'user_id' => $fixture['second_user'] ) );
        $wpdb->delete( $wpdb->prefix . 'sprint_engine_enrolments', array( 'user_id' => $fixture['second_user'] ) );
        wp_delete_user( $fixture['second_user'] );
    }
    exit;
}
wp_set_current_user( 1 );
$manager = new ThePath\SprintEngine\Content\StructureManager();
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Build a more thoughtful weekly review and choose what matters most', 'post_name' => 'ux-browser-' . wp_generate_password( 8, false ), 'post_content' => '<!-- wp:paragraph --><p>Make space to reflect, notice what matters, and choose your next action.</p><!-- /wp:paragraph -->' ) );
$steps = array();
$images = array();
// Deterministic landscape/portrait PNGs make cropping visible without external media.
function sprint_engine_banner_png( $width, $height ) {
    $chunk = static function ( $type, $data ) { return pack( 'N', strlen( $data ) ) . $type . $data . pack( 'N', crc32( $type . $data ) ); };
    $pixels = '';
    for ( $y = 0; $y < $height; ++$y ) {
        $pixels .= "\0";
        for ( $x = 0; $x < $width; ++$x ) {
            $circle = ( ( $x - $width / 2 ) ** 2 + ( $y - $height / 2 ) ** 2 ) < ( min( $width, $height ) / 4 ) ** 2;
            $pixels .= $circle ? "\xe4\xbb\x72" : pack( 'CCC', 32 + (int) ( 45 * $x / $width ), 91 + (int) ( 45 * $y / $height ), 72 );
        }
    }
    return "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNCCCCC', $width, $height, 8, 2, 0, 0, 0 ) ) . $chunk( 'IDAT', gzcompress( $pixels ) ) . $chunk( 'IEND', '' );
}
foreach ( array( 'Sprint banner', 'Step banner' ) as $alt ) {
    $width = 'Sprint banner' === $alt ? 800 : 350;
    $height = 'Sprint banner' === $alt ? 350 : 600;
    $upload = wp_upload_bits( 'se010-banner.png', null, sprint_engine_banner_png( $width, $height ) );
    $image = wp_insert_attachment( array( 'post_title' => $alt, 'post_mime_type' => 'image/png', 'guid' => $upload['url'] ), $upload['file'] );
    wp_update_attachment_metadata( $image, array( 'width' => $width, 'height' => $height, 'file' => _wp_relative_upload_path( $upload['file'] ) ) );
    update_post_meta( $image, '_wp_attachment_image_alt', $alt );
    $images[] = $image;
}
set_post_thumbnail( $sprint, $images[0] );
$media = <<<'HTML'
<!-- wp:heading --><h2 class="wp-block-heading">Reflect and prepare</h2><!-- /wp:heading -->
<!-- wp:list --><ul class="wp-block-list"><li>Notice what worked.</li><li>Choose one change.</li></ul><!-- /wp:list -->
<!-- wp:quote --><blockquote class="wp-block-quote"><p>Small actions create room for progress.</p></blockquote><!-- /wp:quote -->
<!-- wp:image {"align":"wide"} --><figure class="wp-block-image alignwide"><img width="1600" height="900" src="https://example.invalid/fixture.svg" alt="A quiet landscape"/></figure><!-- /wp:image -->
<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images columns-2"><!-- wp:image --><figure class="wp-block-image"><img src="https://example.invalid/fixture.svg" alt="First view"/></figure><!-- /wp:image --><!-- wp:image --><figure class="wp-block-image"><img src="https://example.invalid/fixture.svg" alt="Second view"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->
<!-- wp:embed {"url":"https://www.youtube.com/watch?v=test","type":"video","providerNameSlug":"youtube","responsive":true,"className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} --><figure class="wp-block-embed is-type-video is-provider-youtube wp-block-embed-youtube wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper"><iframe width="1280" height="720" src="https://www.youtube.com/embed/test" title="Fixture video"></iframe></div></figure><!-- /wp:embed -->
<!-- wp:audio --><figure class="wp-block-audio"><audio controls src="https://example.invalid/test.wav"></audio></figure><!-- /wp:audio -->
<!-- wp:video --><figure class="wp-block-video"><video controls width="1280" height="720" src="https://example.invalid/test.mp4"></video></figure><!-- /wp:video -->
<!-- wp:file --><div class="wp-block-file"><object class="wp-block-file__embed" data="https://example.invalid/test.pdf" type="application/pdf" width="100%" height="900" aria-label="Worksheet"></object><a href="https://example.invalid/test.pdf">Worksheet PDF</a><a class="wp-block-file__button wp-element-button" href="https://example.invalid/test.pdf" download>Download</a></div><!-- /wp:file -->
<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><td>Long table column with details</td><td>Another column of details</td><td>Further details for reflection</td><td>Additional notes to consider</td></tr></tbody></table></figure><!-- /wp:table -->
<!-- wp:preformatted --><pre class="wp-block-preformatted">A deliberately wide preformatted line: 123456789012345678901234567890123456789012345678901234567890123456789012345678901234567890</pre><!-- /wp:preformatted -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#se-runner-action">Review complete</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
<!-- wp:code --><pre class="wp-block-code"><code>choose_one_action(); // A deliberately long code example to exercise internal scrolling and bounded content.</code></pre><!-- /wp:code -->
HTML;
for ( $i = 0; $i < 6; ++$i ) {
    $step = $manager->quick_add( $sprint, 2 === $i ? 'Reflect on what you have learned and decide which practical changes will make the biggest difference' : 'Choose your next action — ' . ( $i + 1 ) );
    wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'post_content' => '<!-- wp:paragraph --><p>Think about one moment that made a difference this week. Write down what happened and why it mattered.</p><!-- /wp:paragraph -->' . ( 2 === $i ? $media : '' ) ) );
    ThePath\SprintEngine\Content\Meta::save( $step, array( '_sprint_engine_stage_label' => 'Reflect', '_sprint_engine_mode' => 'task', '_sprint_engine_estimated_minutes' => 10 ) );
    $steps[] = $step;
}
ThePath\SprintEngine\Content\Meta::save( $sprint, array( '_sprint_engine_launchable' => true, '_sprint_engine_estimated_minutes' => 60, '_sprint_engine_completion_message' => "You made it.\n\nChoose your next step.", '_sprint_engine_completion_cta_label' => 'Your next step', '_sprint_engine_completion_cta_url' => home_url( '/?se-completion-next=1' ) ) );
ThePath\SprintEngine\Content\Meta::save( $sprint, array( '_sprint_engine_estimated_duration_value' => '1.50', '_sprint_engine_estimated_duration_unit' => 'hours' ) );
set_post_thumbnail( $steps[0], $images[1] );
$second = wp_insert_user( array( 'user_login' => 'ux-b-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$user = wp_insert_user( array( 'user_login' => 'ux-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$wp_rewrite->set_permalink_structure( '/%postname%/' );
flush_rewrite_rules( false );
echo wp_json_encode( array( 'url' => ThePath\SprintEngine\Runner\Routes::url( get_post_field( 'post_name', $sprint ) ), 'cookie' => array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user, time() + 3600, 'logged_in' ), 'url' => home_url( '/' ) ), 'second_user' => $second, 'second_cookie' => array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $second, time() + 3600, 'logged_in' ), 'url' => home_url( '/' ) ), 'user' => $user, 'posts' => array_merge( $steps, $images, array( $sprint ) ) ) );
