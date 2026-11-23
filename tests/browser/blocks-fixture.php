<?php
/** SE-029: real WordPress block rendering on a disposable loopback site only. */

use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Settings\RunnerBranding;

$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) || ! in_array( parse_url( getenv( 'SE_TEST_HTTP_BASE' ) ?: '', PHP_URL_HOST ), array( 'localhost', '127.0.0.1' ), true ) ) {
    exit( "Requires a disposable loopback WordPress installation.\n" );
}
require $root . '/wp-load.php';

function blocks_assert( $value, $message ) {
    if ( ! $value || is_wp_error( $value ) ) {
        throw new RuntimeException( $message );
    }
    return $value;
}

function blocks_snapshot() {
    global $wpdb;
    $attempts = $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id", ARRAY_A );
    foreach ( $attempts as &$attempt ) {
        // Runner resume alone refreshes these existing operational timestamps.
        if ( 'in_progress' === $attempt['status'] ) {
            unset( $attempt['last_activity_at'], $attempt['updated_at'] );
        }
    }
    unset( $attempt );
    return array(
        'attempts' => $attempts,
        'progress' => $wpdb->get_results( "SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id", ARRAY_A ),
        'posts' => $wpdb->get_results( "SELECT ID, post_content FROM {$wpdb->posts} WHERE post_type IN ('sprint_engine_sprint','sprint_engine_step') ORDER BY ID", ARRAY_A ),
        'version' => get_option( 'sprint_engine_version' ),
        'schema' => get_option( 'sprint_engine_schema_version' ),
        'rewrites' => get_option( 'rewrite_rules' ),
        'branding' => get_option( RunnerBranding::OPTION ),
    );
}

if ( 'verify' === ( $argv[1] ?? '' ) ) {
    $fixture = json_decode( file_get_contents( $argv[2] ), true, 512, JSON_THROW_ON_ERROR );
    blocks_assert( $fixture['snapshot'] === blocks_snapshot(), 'Block checks changed content, attempts, progress, branding, versions or rewrites.' );
    echo "PASS: Content, attempt identity/progress, branding, versions and rewrites retained.\n";
    exit;
}

wp_set_current_user( 1 );
switch_theme( wp_get_theme( 'twentytwentyfive' )->exists() ? 'twentytwentyfive' : 'twentytwentytwo' );
update_option( RunnerBranding::OPTION, array( 'font' => 'poppins' ) );
$registered = WP_Block_Type_Registry::get_instance();
$accordion = $registered->is_registered( 'core/accordion' );
$details = $registered->is_registered( 'core/details' );
$content = '';
foreach ( range( 2, 6 ) as $level ) {
    $content .= '<!-- wp:heading {"level":' . $level . '} --><h' . $level . ' class="wp-block-heading" id="blocks-h' . $level . '">Heading level ' . $level . '</h' . $level . '><!-- /wp:heading -->';
}
$content .= <<<'HTML'
<!-- wp:heading {"level":3,"fontSize":"large"} --><h3 class="wp-block-heading has-large-font-size" id="blocks-preset">An intentional preset size</h3><!-- /wp:heading -->
<!-- wp:heading {"style":{"typography":{"fontSize":"38px","fontFamily":"Georgia, serif"},"color":{"text":"#663399"},"spacing":{"margin":{"top":"13px","bottom":"17px"}}}} --><h2 class="wp-block-heading has-text-color" id="blocks-custom" style="color:#663399;margin-top:13px;margin-bottom:17px;font-family:Georgia,serif;font-size:38px">An intentional custom heading</h2><!-- /wp:heading -->
<!-- wp:paragraph --><p id="blocks-paragraph">A paragraph with <a href="#blocks-h2">an ordinary link</a>, <strong>strong emphasis</strong> and <em>gentle emphasis</em>.</p><!-- /wp:paragraph -->
<!-- wp:list --><ul class="wp-block-list"><!-- wp:list-item --><li>First list item</li><!-- /wp:list-item --><!-- wp:list-item --><li>Second list item</li><!-- /wp:list-item --></ul><!-- /wp:list -->
<!-- wp:list {"ordered":true} --><ol class="wp-block-list"><!-- wp:list-item --><li>One ordered action</li><!-- /wp:list-item --></ol><!-- /wp:list -->
<!-- wp:quote --><blockquote class="wp-block-quote"><!-- wp:paragraph --><p>A quote to reflect on.</p><!-- /wp:paragraph --><cite>Fixture author</cite></blockquote><!-- /wp:quote -->
<!-- wp:buttons --><div class="wp-block-buttons"><!-- wp:button --><div class="wp-block-button"><a class="wp-block-button__link wp-element-button" href="#blocks-h2">Filled button</a></div><!-- /wp:button --><!-- wp:button {"className":"is-style-outline"} --><div class="wp-block-button is-style-outline"><a class="wp-block-button__link wp-element-button" href="#blocks-h2">Outline button</a></div><!-- /wp:button --><!-- wp:button {"style":{"color":{"background":"#fff2cc","text":"#663399"},"typography":{"fontSize":"19px"}}} --><div class="wp-block-button"><a class="wp-block-button__link has-text-color has-background has-custom-font-size wp-element-button" id="blocks-button-custom" style="color:#663399;background-color:#fff2cc;font-size:19px" href="#blocks-h2">Authored button</a></div><!-- /wp:button --></div><!-- /wp:buttons -->
HTML;

if ( $details ) {
    $content .= '<!-- wp:details --><details class="wp-block-details" id="blocks-details"><summary>A native Details summary</summary><!-- wp:paragraph --><p>Details panel content.</p><!-- /wp:paragraph --></details><!-- /wp:details -->';
}
if ( $accordion ) {
    foreach ( array( 'right', 'left', 'custom', 'preset', 'hidden' ) as $kind ) {
        $side = 'left' === $kind ? 'left' : 'right';
        $custom = 'custom' === $kind;
        $preset = 'preset' === $kind;
        $show = 'hidden' !== $kind;
        $attrs = wp_json_encode( array( 'iconPosition' => $side, 'showIcon' => $show ) );
        $group_attrs = $preset ? '{"fontSize":"large"}' : '{}';
        $group_class = $preset ? ' has-large-font-size' : '';
        $heading_attrs = $custom ? '{"style":{"color":{"background":"#fff2cc","text":"#663399"},"typography":{"fontSize":"21px"},"spacing":{"padding":{"top":"11px","right":"19px","bottom":"11px","left":"19px"}}}}' : $attrs;
        $heading_style = $custom ? ' style="color:#663399;background-color:#fff2cc;font-size:21px"' : '';
        $heading_class = ( $show ? ' has-icon has-icon-' . $side : '' ) . ( $custom ? ' has-text-color has-background' : '' );
        $toggle_style = $custom ? ' style="padding-top:11px;padding-right:19px;padding-bottom:11px;padding-left:19px"' : '';
        $icon = $show ? '<span class="wp-block-accordion-heading__toggle-icon" aria-hidden="true">+</span>' : '';
        $title = '<span class="wp-block-accordion-heading__toggle-title">The conversation — a longer heading that wraps gracefully on a narrow screen</span>';
        $content .= '<!-- wp:accordion ' . $group_attrs . ' --><div class="wp-block-accordion' . $group_class . '" role="group" id="blocks-accordion-' . $kind . '">';
        foreach ( array( false, true ) as $open ) {
            $content .= '<!-- wp:accordion-item {"openByDefault":' . ( $open ? 'true' : 'false' ) . '} --><div class="wp-block-accordion-item' . ( $open ? ' is-open' : '' ) . '">';
            $content .= '<!-- wp:accordion-heading ' . $heading_attrs . ' --><h3 class="wp-block-accordion-heading' . $heading_class . '"' . $heading_style . '><button type="button" class="wp-block-accordion-heading__toggle"' . $toggle_style . '>' . ( 'left' === $side ? $icon . $title : $title . $icon ) . '</button></h3><!-- /wp:accordion-heading -->';
            $content .= '<!-- wp:accordion-panel --><div class="wp-block-accordion-panel"><!-- wp:paragraph --><p>Native Accordion panel content with <a href="#blocks-h2">a focusable link</a>.</p><!-- /wp:paragraph --></div><!-- /wp:accordion-panel -->';
            $content .= '</div><!-- /wp:accordion-item -->';
        }
        $content .= '</div><!-- /wp:accordion -->';
    }
}
$image = esc_url( includes_url( 'images/w-logo-blue-white-bg.png' ) );
$content .= <<<HTML
<!-- wp:group --><div class="wp-block-group"><!-- wp:paragraph --><p>Group content</p><!-- /wp:paragraph --><!-- wp:columns --><div class="wp-block-columns"><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>First column</p><!-- /wp:paragraph --></div><!-- /wp:column --><!-- wp:column --><div class="wp-block-column"><!-- wp:paragraph --><p>Second column</p><!-- /wp:paragraph --></div><!-- /wp:column --></div><!-- /wp:columns --></div><!-- /wp:group -->
<!-- wp:table --><figure class="wp-block-table"><table><tbody><tr><th>Item</th><th>Outcome</th></tr><tr><td>A small task</td><td>A useful result</td></tr></tbody></table></figure><!-- /wp:table -->
<!-- wp:image --><figure class="wp-block-image"><img src="$image" width="640" height="240" alt="Fixture image"/><figcaption class="wp-element-caption">Image caption</figcaption></figure><!-- /wp:image -->
<!-- wp:gallery --><figure class="wp-block-gallery has-nested-images columns-default is-cropped"><!-- wp:image --><figure class="wp-block-image"><img src="$image" alt="First gallery image"/></figure><!-- /wp:image --><!-- wp:image --><figure class="wp-block-image"><img src="$image" alt="Second gallery image"/></figure><!-- /wp:image --></figure><!-- /wp:gallery -->
<!-- wp:file --><div class="wp-block-file"><a href="#blocks-h2">A fixture document</a><a href="#blocks-h2" class="wp-block-file__button wp-element-button" download>Download</a></div><!-- /wp:file -->
<!-- wp:audio --><figure class="wp-block-audio"><audio controls preload="none" src="/se029-fixture.wav"></audio></figure><!-- /wp:audio -->
<!-- wp:video --><figure class="wp-block-video"><video controls preload="none" poster="$image" src="/se029-fixture.webm"></video></figure><!-- /wp:video -->
<!-- wp:embed {"url":"https://example.invalid/se029-embed","type":"video","providerNameSlug":"fixture","responsive":true} --><figure class="wp-block-embed is-type-video wp-embed-aspect-16-9 wp-has-aspect-ratio"><div class="wp-block-embed__wrapper"><iframe title="Fixture embed" src="https://example.invalid/se029-embed" width="640" height="360"></iframe></div></figure><!-- /wp:embed -->
<!-- wp:code --><pre class="wp-block-code"><code>const result = "A long code example stays inside the content area even on a narrow viewport.";</code></pre><!-- /wp:code -->
<!-- wp:preformatted --><pre class="wp-block-preformatted">Preformatted content remains readable.</pre><!-- /wp:preformatted -->
HTML;

$member = blocks_assert( wp_insert_user( array( 'user_login' => 'blocks-' . wp_generate_password( 8, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) ), 'Member creation failed.' );
$manager = new StructureManager();
$service = new ProgressService();
$urls = array();
foreach ( array( 'welcome', 'step' ) as $surface ) {
    $sprint = blocks_assert( wp_insert_post( array( 'post_type' => 'sprint_engine_sprint', 'post_status' => 'publish', 'post_title' => 'Gutenberg compatibility', 'post_content' => $content ), true ), 'Sprint creation failed.' );
    $step = blocks_assert( $manager->quick_add( $sprint, 'A focused Step title' ), 'Step creation failed.' );
    blocks_assert( wp_update_post( array( 'ID' => $step, 'post_status' => 'publish', 'post_content' => $content ), true ), 'Step content failed.' );
    Meta::save( $sprint, array( '_sprint_engine_launchable' => true ) );
    $urls[ $surface ] = Routes::url( get_post_field( 'post_name', $sprint ) );
    if ( 'step' === $surface ) {
        blocks_assert( $service->start_sprint( $member, $sprint ), 'Attempt creation failed.' );
        $preview_step = $step;
    }
}
$cookies = static function ( $user ) {
    $expires = time() + 3 * HOUR_IN_SECONDS;
    $token = WP_Session_Tokens::get_instance( $user )->create( $expires );
    return array(
        array( 'name' => LOGGED_IN_COOKIE, 'value' => wp_generate_auth_cookie( $user, $expires, 'logged_in', $token ), 'url' => home_url( '/' ) ),
        array( 'name' => AUTH_COOKIE, 'value' => wp_generate_auth_cookie( $user, $expires, 'auth', $token ), 'url' => home_url( '/' ) ),
    );
};
$admin_cookies = $cookies( 1 );
// Generate the private preview nonce against the same administrator session cookie.
foreach ( $admin_cookies as $cookie ) {
    $_COOKIE[ $cookie['name'] ] = $cookie['value'];
}
$urls['preview'] = add_query_arg( array( 'sprint_engine_step_preview' => $preview_step, '_wpnonce' => wp_create_nonce( 'sprint_engine_step_preview_' . $preview_step ) ), home_url( '/' ) );
$urls['home'] = home_url( '/' );
echo wp_json_encode( array(
    'urls' => $urls,
    'member' => $cookies( $member ),
    'admin' => $admin_cookies,
    'wordpress' => get_bloginfo( 'version' ),
    'accordion' => $accordion,
    'details' => $details,
    'snapshot' => blocks_snapshot(),
) );
