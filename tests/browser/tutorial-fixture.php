<?php
/** Disposable SE-030 browser users; activation alone leaves the tutorial absent. */
if ( ! getenv( 'SE_TEST_WP_ROOT' ) || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require getenv( 'SE_TEST_WP_ROOT' ) . '/wp-load.php';
wp_set_current_user( 1 );
// Keep ordinary WordPress updater traffic out of this offline UI fixture.
set_site_transient( 'update_core', (object) array( 'last_checked' => time(), 'version_checked' => $wp_version, 'updates' => array() ) );
set_site_transient( 'update_plugins', (object) array( 'last_checked' => time(), 'checked' => array(), 'response' => array(), 'no_update' => array() ) );
set_site_transient( 'update_themes', (object) array( 'last_checked' => time(), 'checked' => array(), 'response' => array(), 'no_update' => array() ) );
$manifest = array( 'home' => home_url( '/' ), 'settings' => admin_url( 'edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings' ), 'welcome' => admin_url( 'edit.php?post_type=sprint_engine_sprint&page=sprint-engine-welcome' ), 'admins' => array(), 'wordpress' => get_bloginfo( 'version' ) );
foreach ( array( 'administrator', 'administrator', 'administrator', 'subscriber' ) as $index => $role ) {
	$user = wp_insert_user( array( 'user_login' => 'se030-browser-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => $role ) );
	wp_set_current_user( $user );
	$cookie = wp_generate_auth_cookie( $user, time() + HOUR_IN_SECONDS, 'logged_in' );
	$token = wp_parse_auth_cookie( $cookie, 'logged_in' )['token'];
	$auth = wp_generate_auth_cookie( $user, time() + HOUR_IN_SECONDS, 'auth', $token );
	$_COOKIE[ LOGGED_IN_COOKIE ] = $cookie;
	$entry = array( 'user' => $user, 'nonce' => wp_create_nonce( 'wp_rest' ), 'cookie' => array( 'name' => LOGGED_IN_COOKIE, 'value' => $cookie, 'url' => home_url( '/' ) ), 'auth_cookie' => array( 'name' => AUTH_COOKIE, 'value' => $auth, 'url' => home_url( '/' ) ) );
	if ( 'administrator' === $role ) { $manifest['admins'][] = $entry; } else { $manifest['member'] = $entry; }
}
echo wp_json_encode( $manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES );
