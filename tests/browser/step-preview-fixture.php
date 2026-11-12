<?php
/** Set up and verify a disposable WordPress site for the Step preview browser test. */
$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) {
    exit( 1 );
}
require $root . '/wp-load.php';

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEnginePro\Database\Installer as ProInstaller;
use ThePath\SprintEnginePro\Workspace\Repository;

function require_fixture( $condition, $message ) {
    if ( ! $condition || is_wp_error( $condition ) ) {
        throw new RuntimeException( $message );
    }
    return $condition;
}

function preview_member_rows() {
    global $wpdb;
    $tables = array( $wpdb->prefix . 'sprint_engine_enrolments', $wpdb->prefix . 'sprint_engine_step_progress' );
    if ( class_exists( ProInstaller::class ) ) {
        $tables[] = ProInstaller::table();
    }
    return array_map( static fn( $table ) => $wpdb->get_results( "SELECT * FROM `$table` ORDER BY id", ARRAY_A ), $tables );
}

$guard_path = WPMU_PLUGIN_DIR . '/sprint-engine-test-step-preview.php';
if ( isset( $argv[1] ) ) {
    $fixture = json_decode( file_get_contents( $argv[1] ), true, 512, JSON_THROW_ON_ERROR );
    require_fixture( $fixture['memberRows'] === preview_member_rows(), 'Preview changed participant rows.' );
    $published = get_post( $fixture['publishedId'] );
    $draft = get_post( $fixture['draftId'] );
    require_fixture( 'draft' === $draft->post_status && 'SE-023 draft edit' === $draft->post_title && 'draft' === get_post_status( $fixture['draftSprintId'] ), 'Draft preview failed to autosave or changed publication.' );
    require_fixture( 'publish' === $published->post_status && 'SE-023 published Step' === $published->post_title && str_contains( $published->post_content, 'Saved Step instructions' ), 'Autosave overwrote published content.' );
    require_fixture( 'SE-023 private editor title' === wp_get_post_autosave( $published->ID, 1 )->post_title, 'Administrator autosave missing.' );
    require_fixture( 'SE-023 second editor title' === wp_get_post_autosave( $published->ID, $fixture['secondId'] )->post_title, 'Other administrator autosave missing.' );
    unlink( $guard_path );
    echo "PASS: Exact Core attempt/progress rows and optional Pro Workspace rows preserved.\n";
    echo "PASS: Native autosaves retained author ownership and publication states.\n";
    exit;
}

wp_set_current_user( 1 );
$second = require_fixture( wp_insert_user( array( 'user_login' => 'preview-editor', 'user_pass' => wp_generate_password(), 'role' => 'administrator' ) ), 'Second administrator fixture failed.' );
$member = require_fixture( wp_insert_user( array( 'user_login' => 'preview-member', 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) ), 'Member fixture failed.' );
$manager = new StructureManager();
$draft_sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'draft', 'post_title' => 'SE-023 unpublished Sprint' ) );
$draft = require_fixture( $manager->quick_add( $draft_sprint, 'SE-023 draft Step' ), 'Draft Step fixture failed.' );
$sprint = wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'SE-023 published Sprint' ) );
$published = require_fixture( $manager->quick_add( $sprint, 'SE-023 published Step' ), 'Published Step fixture failed.' );
$finish = require_fixture( $manager->quick_add( $sprint, 'SE-023 final Step' ), 'Final Step fixture failed.' );
$orphan = wp_insert_post( array( 'post_type' => 'sprint_engine_step', 'post_status' => 'draft', 'post_title' => 'SE-023 Step without a parent', 'post_content' => '<!-- wp:paragraph --><p>Standalone draft instructions.</p><!-- /wp:paragraph -->' ) );

// A local PNG and a one-second PCM audio track exercise native media delivery.
$chunk = static fn( $type, $bytes ) => pack( 'N', strlen( $bytes ) ) . $type . $bytes . pack( 'N', crc32( $type . $bytes ) );
$image = wp_upload_bits( 'step-preview.png', null, "\x89PNG\r\n\x1a\n" . $chunk( 'IHDR', pack( 'NNC5', 480, 240, 8, 2, 0, 0, 0 ) ) . $chunk( 'IDAT', gzcompress( str_repeat( "\0" . str_repeat( "\x62\xaa\x9d", 480 ), 240 ) ) ) . $chunk( 'IEND', '' ) );
$image_id = wp_insert_attachment( array( 'post_title' => 'Preview banner', 'post_mime_type' => 'image/png', 'guid' => $image['url'] ), $image['file'] );
wp_update_attachment_metadata( $image_id, array( 'width' => 480, 'height' => 240, 'file' => _wp_relative_upload_path( $image['file'] ) ) );
update_post_meta( $image_id, '_wp_attachment_image_alt', 'Preview banner' );
set_post_thumbnail( $published, $image_id );
$samples = str_repeat( "\x80", 8000 );
$audio = wp_upload_bits( 'step-preview.wav', null, 'RIFF' . pack( 'V', 36 + strlen( $samples ) ) . 'WAVEfmt ' . pack( 'VvvVVvv', 16, 1, 1, 8000, 8000, 1, 8 ) . 'data' . pack( 'V', strlen( $samples ) ) . $samples );

$content = sprintf( <<<'HTML'
<!-- wp:paragraph -->
<p>Saved Step instructions.</p>
<!-- /wp:paragraph -->

<!-- wp:image -->
<figure class="wp-block-image"><img src="%s" alt="Preview block image"/></figure>
<!-- /wp:image -->

<!-- wp:audio -->
<figure class="wp-block-audio"><audio controls src="%s"></audio></figure>
<!-- /wp:audio -->

<!-- wp:embed {"url":"https://example.invalid/step-preview","type":"video","className":"wp-embed-aspect-16-9 wp-has-aspect-ratio"} -->
<figure class="wp-block-embed is-type-video wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper">
https://example.invalid/step-preview
</div></figure>
<!-- /wp:embed -->

<!-- wp:list -->
<ul class="wp-block-list"><!-- wp:list-item --><li>Choose one change.</li><!-- /wp:list-item --></ul>
<!-- /wp:list -->
HTML, esc_url( $image['url'] ), esc_url( $audio['url'] ) );
$field = array( 'fieldId' => '12345678-1234-4234-8234-000000000001', 'prompt' => 'Reflect on your change', 'responseType' => 'long_text' );
if ( class_exists( ProInstaller::class ) ) {
    $content .= "\n\n<!-- wp:sprint-engine-pro/workspace-response " . wp_json_encode( $field ) . ' /-->';
}
wp_update_post( array( 'ID' => $draft, 'post_content' => '<!-- wp:paragraph --><p>Saved draft instructions.</p><!-- /wp:paragraph -->' ) );
wp_update_post( array( 'ID' => $published, 'post_status' => 'publish', 'post_content' => $content ) );
wp_update_post( array( 'ID' => $finish, 'post_status' => 'publish' ) );
require_fixture( Meta::save( $published, array( '_sprint_engine_mode' => 'task', '_sprint_engine_stage_label' => 'Reflect', '_sprint_engine_estimated_minutes' => 9 ) ), 'Step metadata fixture failed.' );
require_fixture( Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) ), 'Launchable fixture failed.' );
update_option( 'sprint_engine_runner_branding', array( 'logo_id' => $image_id, 'primary' => '#125c55' ) );

wp_set_current_user( $member );
$state = require_fixture( ( new ProgressService() )->start_sprint( $member, $sprint ), 'Existing attempt fixture failed.' );
if ( class_exists( ProInstaller::class ) ) {
    require_fixture( ( new Repository() )->save(
        array( 'user_id' => $member, 'sprint_id' => $sprint, 'attempt_number' => $state['attempt_number'], 'step_id' => $published, 'field_id' => $field['fieldId'] ),
        $field,
        'Private saved participant response.'
    ), 'Existing Workspace response fixture failed.' );
}

if ( ! is_dir( WPMU_PLUGIN_DIR ) ) {
    mkdir( WPMU_PLUGIN_DIR );
}
require_fixture( file_put_contents( $guard_path, <<<'PHP'
<?php
add_filter( 'pre_oembed_result', static function ( $result, $url ) {
    return 'https://example.invalid/step-preview' === $url ? '<iframe src="https://example.invalid/frame" title="Preview embed" width="560" height="315"></iframe>' : $result;
}, 10, 2 );
add_action( 'parse_request', static function ( $wp ) {
    if ( '' !== $wp->request || ! isset( $_GET['sprint_engine_step_preview'] ) || isset( $wp->query_vars['rest_route'] ) ) {
        return;
    }
    $unexpected = static function () { throw new RuntimeException( 'Preview invoked a participant hook.' ); };
    foreach ( array( 'sprint_started', 'step_started', 'step_completed', 'sprint_completed', 'user_can_access_sprint', 'runner_template' ) as $hook ) {
        add_filter( 'sprint_engine/' . $hook, $unexpected );
    }
    if ( isset( $_GET['sprint_engine_test_deny_edit'] ) ) {
        add_filter( 'user_has_cap', static function ( $caps, $required, $args ) {
            if ( 'edit_post' === $args[0] ) {
                unset( $caps['manage_options'] );
            }
            return $caps;
        }, 10, 3 );
    }
    add_filter( 'query', static function ( $sql ) {
        if ( preg_match( '/sprint_engine_(?:enrolments|step_progress|pro_workspace_responses)/i', $sql ) ) {
            throw new RuntimeException( 'Preview touched participant storage.' );
        }
        return $sql;
    } );
} );
PHP ), 'Could not install disposable preview assertions.' );

$cookies = static function ( $user ) {
    $expires = time() + 3600;
    $token = WP_Session_Tokens::get_instance( $user )->create( $expires );
    return array(
        array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user, $expires, 'logged_in', $token ), 'url' => home_url( '/' ) ),
        array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $user, $expires, 'auth', $token ), 'url' => home_url( '/' ) ),
    );
};
$editor = static fn( $id ) => admin_url( 'post.php?post=' . $id . '&action=edit' );
echo wp_json_encode( array(
    'draftEditor' => $editor( $draft ),
    'publishedEditor' => $editor( $published ),
    'orphanEditor' => $editor( $orphan ),
    'sprintEditor' => $editor( $sprint ),
    'runner' => \ThePath\SprintEngine\Runner\Routes::url( get_post( $sprint )->post_name ),
    'draftId' => $draft,
    'draftSprintId' => $draft_sprint,
    'publishedId' => $published,
    'secondId' => $second,
    'proActive' => class_exists( ProInstaller::class ),
    'memberRows' => preview_member_rows(),
    'adminCookies' => $cookies( 1 ),
    'secondCookies' => $cookies( $second ),
    'memberCookies' => $cookies( $member ),
) );
