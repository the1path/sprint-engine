<?php
/** Optional SE-030 acceptance against an unchanged disposable Pro installation. */
use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Dashboard\Dashboard;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Tutorial\Installer;
use ThePath\SprintEnginePro\Access\AccessPolicy;
use ThePath\SprintEnginePro\Access\SprintAccess;
use ThePath\SprintEnginePro\Entitlements\EntitlementManager;
use ThePath\SprintEnginePro\Entitlements\EntitlementProvider;

if ( ! getenv( 'SE_TEST_WP_ROOT' ) || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit( 1 ); }
require getenv( 'SE_TEST_WP_ROOT' ) . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
wp_set_current_user( 1 );
if ( is_wp_error( activate_plugin( 'sprint-engine-pro/sprint-engine-pro.php' ) ) ) { throw new RuntimeException( 'Disposable Pro activation failed.' ); }
$pro = new ThePath\SprintEnginePro\Plugin(); $pro->boot();
if ( ! $pro->is_booted() ) { throw new RuntimeException( 'Pro must actually boot for this acceptance test.' ); }
$tutorial = ( new Installer() )->install();
if ( is_wp_error( $tutorial ) ) { throw new RuntimeException( $tutorial->get_error_message() ); }
( new SprintAccess() )->save( $tutorial, 'restricted' );
$member = wp_insert_user( array( 'user_login' => 'se030-pro-' . wp_generate_password( 10, false ), 'user_pass' => wp_generate_password(), 'role' => 'subscriber' ) );
$provider = new class() implements EntitlementProvider {
	public function grants( int $user_id, int $sprint_id ): bool { return true; }
};
$manager = new EntitlementManager( array( $provider ) );
$policy = new AccessPolicy( $manager );
$policy->register_hooks();
wp_set_current_user( $member );
if ( ! $manager->grants( $member, $tutorial ) || ( new AccessManager() )->can_access( $member, $tutorial ) ) { throw new RuntimeException( 'Actual Pro entitlement must never expose the administrator tutorial.' ); }
echo "PASS: Actual Pro entitlement provider grants; tutorial access remains denied (Pro may also independently veto).\n";
$force = static fn() => true;
add_filter( 'sprint_engine/user_can_access_sprint', $force, PHP_INT_MAX );
if ( ( new AccessManager() )->can_access( $member, $tutorial ) ) { throw new RuntimeException( 'Core marker must remain final after an explicit integration grant.' ); }
remove_filter( 'sprint_engine/user_can_access_sprint', $force, PHP_INT_MAX );
echo "PASS: Core's final tutorial marker vetoes an explicit integration grant with actual Pro loaded.\n";
$context = ( new Runner() )->resolve( get_post_field( 'post_name', $tutorial ) );
if ( 403 !== $context['status'] || isset( $context['step'], $context['progress'], $context['title'] ) ) { throw new RuntimeException( 'Pro tutorial Runner disclosure.' ); }
$dashboard = ( new Dashboard() )->resolve();
foreach ( $dashboard['sections'] as $items ) { if ( in_array( $tutorial, array_column( $items, 'sprint_id' ), true ) ) { throw new RuntimeException( 'Pro tutorial Dashboard disclosure.' ); } }
echo "PASS: Pro-entitled member sees no tutorial content or Core Dashboard listing.\n";
$step = ThePath\SprintEngine\Content\Meta::steps( $tutorial )[0]->ID;
foreach ( array( array( 'sprints', $tutorial, 'start' ), array( 'sprints', $tutorial, 'restart' ), array( 'steps', $step, 'complete' ) ) as [ $resource, $id, $action ] ) {
	$request = new WP_REST_Request( 'POST', "/sprint-engine/v1/$resource/$id/$action" );
	$request->set_header( 'X-WP-Nonce', wp_create_nonce( 'wp_rest' ) ); $request->set_param( 'expected_attempt_id', 1 );
	if ( 403 !== rest_get_server()->dispatch( $request )->get_status() ) { throw new RuntimeException( 'Pro tutorial mutation must be denied.' ); }
	echo "PASS: Actual Pro entitlement cannot bypass tutorial REST $action.\n";
}
wp_set_current_user( 1 );
if ( ! ( new AccessManager() )->can_access( 1, $tutorial ) || 'not_started' !== ( new Runner() )->resolve( get_post_field( 'post_name', $tutorial ) )['state'] ) { throw new RuntimeException( 'Administrator must run tutorial with Pro active.' ); }
echo "PASS: Signed-in administrator can open tutorial with Pro active and restriction metadata present.\n";
echo "NOTE: Pro public Directory/Collections/Journeys exclusion is owned by PRO-018 and is not asserted by this Core-only acceptance test.\n";
