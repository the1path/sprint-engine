<?php
/** SE-013 read-only Dashboard, route, privacy and isolation checks. */
use ThePath\SprintEngine\Dashboard\Dashboard;
use ThePath\SprintEngine\Dashboard\Routes;
use ThePath\SprintEngine\Runner\Routes as RunnerRoutes;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Progress\EnrolmentRepository;
use ThePath\SprintEngine\Plugin;

$root = getenv('SE_TEST_WP_ROOT');
if (!$root || 'yes' !== getenv('SE_TEST_DISPOSABLE')) { exit(1); }
ob_start();
$_SERVER['HTTP_HOST'] = '127.0.0.1:8097';
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['REQUEST_URI'] = '/';
$_SERVER['REQUEST_METHOD'] = 'GET';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
error_reporting(E_ALL);
set_error_handler(static function ($severity, $message, $file, $line) { throw new ErrorException($message, 0, $severity, $file, $line); });
function sprint_engine_dashboard_check($ok, $message) {
    if (!$ok) { throw new RuntimeException($message); }
    echo 'PASS: ' . $message . PHP_EOL;
}
function sprint_engine_dashboard_rows() {
    global $wpdb;
    return array($wpdb->get_results("SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id", ARRAY_A), $wpdb->get_results("SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id", ARRAY_A));
}
function sprint_engine_dashboard_html($context) {
    set_query_var('sprint_engine_dashboard_context', $context);
    ob_start(); include dirname(__DIR__, 2) . '/templates/dashboard.php'; return ob_get_clean();
}
function sprint_engine_dashboard_shell($html) {
    preg_match('/<div class="se-dashboard__shell">.*?<\/main>\s*<\/div>/s', $html, $match);
    return $match[0] ?? '';
}
function sprint_engine_dashboard_request($path) {
    global $wp;
    $_SERVER['REQUEST_URI'] = $path; $_SERVER['PHP_SELF'] = '/index.php'; $_SERVER['PATH_INFO'] = '';
    $_GET = array(); parse_str(wp_parse_url($path, PHP_URL_QUERY) ?? '', $_GET);
    $wp->matched_rule = ''; $wp->matched_query = ''; $wp->parse_request();
}
function sprint_engine_dashboard_http($path, $user = 0) {
    $base = getenv('SE_TEST_HTTP_BASE');
    if (!in_array(wp_parse_url($base, PHP_URL_HOST), array('localhost', '127.0.0.1'), true)) { throw new RuntimeException('Loopback only'); }
    $headers = $user ? array('Cookie' => LOGGED_IN_COOKIE . '=' . wp_generate_auth_cookie($user, time()+3600, 'logged_in')) : array();
    $response = wp_remote_get(rtrim($base, '/') . $path, array('redirection'=>0,'headers'=>$headers,'timeout'=>30));
    if (is_wp_error($response)) { throw new RuntimeException('Loopback request failed'); }
    return $response;
}

wp_set_current_user(1);
$manager = new StructureManager(); $service = new ProgressService(); $dashboard = new Dashboard();
$posts = array(); $sprints = array(); $steps = array();
foreach (array('Zulu available', 'Alpha available', 'Alpha available', 'Z recent active', 'A older active', 'A older active', 'Z recent complete', 'A older complete', 'A older complete', 'DENIED SECRET', 'DRAFT SECRET', 'UNLAUNCHABLE SECRET', 'INVALID SECRET', 'PASSWORD SECRET') as $title) {
    $id = wp_insert_post(array('post_type'=>'sprint_engine_sprint','post_status'=>'publish','post_title'=>$title,'post_excerpt'=>'Excerpt ' . $title,'post_content'=>'FULL CONTENT NEVER EXCERPT'));
    $sprints[] = $id; $posts[] = $id;
    $steps[$id] = array($manager->quick_add($id, 'First'), $manager->quick_add($id, 'Second'));
    $posts = array_merge($posts, $steps[$id]);
    foreach ($steps[$id] as $step) { wp_update_post(array('ID'=>$step,'post_status'=>'publish')); }
    Meta::save($id, array('_sprint_engine_launchable'=>true));
}
Meta::save($sprints[0], array('_sprint_engine_estimated_duration_value'=>'1.5','_sprint_engine_estimated_duration_unit'=>'hours'));
wp_update_post(array('ID'=>$sprints[1],'post_excerpt'=>''));
wp_update_post(array('ID'=>$sprints[10],'post_status'=>'draft'));
Meta::save($sprints[11], array('_sprint_engine_launchable'=>false));
update_post_meta($steps[$sprints[12]][0], '_sprint_engine_next_step_id', $steps[$sprints[0]][0]);
wp_update_post(array('ID'=>$sprints[13],'post_password'=>'test-only'));
$image = wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'Card image','guid'=>home_url('/dashboard-fixture.png')));
update_post_meta($image, '_wp_attached_file', 'dashboard-fixture.png');
wp_update_attachment_metadata($image, array('width'=>800,'height'=>400,'file'=>'dashboard-fixture.png'));
update_post_meta($image, '_wp_attachment_image_alt', 'Dashboard card image');
set_post_thumbnail($sprints[0], $image);
$denied_image=wp_insert_attachment(array('post_mime_type'=>'image/png','post_title'=>'DENIED IMAGE SECRET','guid'=>home_url('/denied-dashboard-image.png')));
update_post_meta($denied_image, '_wp_attached_file', 'denied-dashboard-image.png');
wp_update_attachment_metadata($denied_image, array('width'=>800,'height'=>400,'file'=>'denied-dashboard-image.png'));
update_post_meta($denied_image, '_wp_attachment_image_alt', 'DENIED IMAGE SECRET');
set_post_thumbnail($sprints[9], $denied_image);
$user = wp_insert_user(array('user_login'=>'dashboard-' . wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber'));
$other = wp_insert_user(array('user_login'=>'dashboard-b-' . wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber'));
$deny = static function ($allow, $member, $id) use ($sprints) { return $allow && in_array($id, $sprints, true) && $id !== $sprints[9]; };
add_filter('sprint_engine/user_can_access_sprint', $deny, 10, 3);
$old_permalink = get_option('permalink_structure'); $wp_rewrite->set_permalink_structure('/%postname%/'); Plugin::activate();
sprint_engine_dashboard_check(isset(get_option('rewrite_rules')[Routes::RULE],get_option('rewrite_rules')[RunnerRoutes::RULE]), 'Activation registers both application rewrites before flush.');
sprint_engine_dashboard_check(home_url('/sprint-engine/dashboard/') === Routes::url() && '^sprint-engine/dashboard/?$' === Routes::RULE, 'Canonical Dashboard URL and exact rewrite.');
sprint_engine_dashboard_check(in_array(Routes::QUERY_VAR, apply_filters('query_vars',array()),true), 'Namespaced Dashboard query variable.');
foreach (array('/sprint-engine/dashboard/', '/sprint-engine/dashboard') as $path) {
    sprint_engine_dashboard_request($path); sprint_engine_dashboard_check(Routes::matches(), 'Exact Dashboard path matches with optional trailing slash.');
}
foreach (array('/?sprint_engine_dashboard=1','/ordinary-page/?sprint_engine_dashboard=1','/sprint-engine/dashboard/extra/','/sprint-engine/dashboard/%0a','/dashboard/','/sprints/') as $path) {
    sprint_engine_dashboard_request($path); sprint_engine_dashboard_check(!Routes::matches(), 'Unrelated or spoofed path cannot activate Dashboard: ' . $path);
}
$vars = array('p'=>12,'feed'=>'rss2');
sprint_engine_dashboard_check($vars === (new Routes())->request($vars) && '/normal.php' === $dashboard->template('/normal.php'), 'Unrelated request and template remain intact.');
sprint_engine_dashboard_request('/sprint/' . get_post_field('post_name',$sprints[0]) . '/');
sprint_engine_dashboard_check(!Routes::matches() && get_post_field('post_name',$sprints[0]) === RunnerRoutes::slug(), 'Runner route unchanged.');
sprint_engine_dashboard_request('/sprint-engine/dashboard/?feed=rss2&p=12&sprint_engine_dashboard=0&user_id=' . $other);
sprint_engine_dashboard_check(Routes::matches() && array(0) === $wp->query_vars['post__in'] && !isset($wp->query_vars['p'],$wp->query_vars['feed'],$wp->query_vars['user_id']), 'Matched Dashboard strips unrelated and identity selectors.');
wp_set_current_user(0); $login = $dashboard->resolve(); parse_str(wp_parse_url($login['login_url'],PHP_URL_QUERY),$query);
sprint_engine_dashboard_check('login' === $login['state'] && Routes::url() === $query['redirect_to'] && !isset($login['sections']), 'Anonymous response exposes no member data and returns login to canonical Dashboard.');
wp_set_current_user($user);
foreach (array(3,4,5,6,7,8) as $index) {
    $id = $sprints[$index]; $service->start_sprint($user,$id); $service->complete_step($user,$id,$steps[$id][0]);
    if ($index >= 6) { $service->complete_step($user,$id,$steps[$id][1]); }
    $row = (new EnrolmentRepository())->find($user,$id);
    (new EnrolmentRepository())->update($row['id'], array('last_activity_at'=>$index===3?'2026-09-02 10:00:00':'2026-09-01 10:00:00','completed_at'=>$index>=6?($index===6?'2026-09-02 10:00:00':'2026-09-01 10:00:00'):null));
}
$before = sprint_engine_dashboard_rows(); $received = null;
$filter = static function ($items,$member) use (&$received) { $received=array($items,$member); return $items; };
add_filter('sprint_engine/dashboard_items',$filter,10,2);
$context = $dashboard->resolve(); $html = sprint_engine_dashboard_html($context);
remove_filter('sprint_engine/dashboard_items',$filter);
$header_calls = array();
$header_listener = static function ($authorized) use (&$header_calls) {
    $header_calls[] = $authorized;
    echo '<a class="history-test-action" href="' . esc_url(home_url('/extension/')) . '">' . esc_html('<Safe extension action>') . '</a>';
};
add_action('sprint_engine/dashboard_header_actions',$header_listener);
$extended_html = sprint_engine_dashboard_html($context);
sprint_engine_dashboard_check(array($context) === $header_calls, 'Header action fires exactly once with the authorized Dashboard context.');
$intro_position = strpos($extended_html,'Choose a Sprint to start,');
$action_position = strpos($extended_html,'<a class="history-test-action"');
sprint_engine_dashboard_check(false !== $action_position && $intro_position < $action_position && $action_position < strpos($extended_html,'</header>') && $action_position < strpos($extended_html,'<section'), 'Extension output appears after intro and before Sprint sections inside header.');
sprint_engine_dashboard_check(str_contains($extended_html,'&lt;Safe extension action&gt;') && !str_contains($extended_html,'<Safe extension action>'), 'Trusted callback owns contextual output escaping.');
sprint_engine_dashboard_check('' !== sprint_engine_dashboard_shell($html) && sprint_engine_dashboard_shell($html) === sprint_engine_dashboard_shell(preg_replace('/<a class="history-test-action".*?<\/a>/', '', $extended_html)), 'Listener adds only its output; remaining Dashboard shell is unchanged.');
$header_calls = array();
sprint_engine_dashboard_request('/ordinary-page/');
$dashboard->prepare();
sprint_engine_dashboard_check('/normal.php' === $dashboard->template('/normal.php') && array() === $header_calls, 'Unrelated page does not render Dashboard or fire header hook.');
sprint_engine_dashboard_request('/sprint-engine/dashboard/');
remove_action('sprint_engine/dashboard_header_actions',$header_listener);
sprint_engine_dashboard_check(sprint_engine_dashboard_shell($html) === sprint_engine_dashboard_shell(sprint_engine_dashboard_html($context)), 'No listener preserves exact Dashboard shell output.');
sprint_engine_dashboard_check($before === sprint_engine_dashboard_rows(), 'Exact enrolment and Step progress rows/timestamps unchanged by resolve and render.');
sprint_engine_dashboard_check(200 === $context['status'] && array('in_progress','not_started','completed') === array_keys($context['sections']), 'Sections use locked display order.');
foreach (array(0,3,6) as $index) {
    $id=$sprints[$index]; $snapshot=sprint_engine_dashboard_rows();
    wp_update_post(array('ID'=>$steps[$id][0],'post_status'=>'draft'));
    $blocked=$dashboard->resolve(); $blocked_html=sprint_engine_dashboard_html($blocked);
    sprint_engine_dashboard_check(!str_contains($blocked_html,get_post_field('post_title',$id)) && !str_contains($blocked_html,RunnerRoutes::url(get_post_field('post_name',$id))) && $snapshot===sprint_engine_dashboard_rows(), 'Unpublished Step excludes Available, In Progress or Completed card without changing progress: ' . $index);
    wp_update_post(array('ID'=>$steps[$id][0],'post_status'=>'publish'));
    $restored=sprint_engine_dashboard_html($dashboard->resolve());
    sprint_engine_dashboard_check(str_contains($restored,get_post_field('post_title',$id)) && $snapshot===sprint_engine_dashboard_rows(), 'Republishing restores Dashboard eligibility and exact existing attempt/progress: ' . $index);
}
foreach (array('not_started'=>array($sprints[1],$sprints[2],$sprints[0]),'in_progress'=>array($sprints[3],$sprints[4],$sprints[5]),'completed'=>array($sprints[6],$sprints[7],$sprints[8])) as $state=>$ids) {
    sprint_engine_dashboard_check($ids === array_column($context['sections'][$state],'sprint_id'), 'Deterministic date/title/ID ordering including ties: ' . $state);
}
sprint_engine_dashboard_check(9 === count($received[0]) && $received[1] === $user && !in_array($sprints[9],array_column($received[0],'sprint_id'),true), 'Items filter receives only authorized canonical items and current user.');
sprint_engine_dashboard_check(null === $context['sections']['not_started'][0]['attempt_id'] && 1 === $context['sections']['in_progress'][0]['attempt_number'], 'Available has no attempt; active uses current attempt.');
foreach (array_slice($sprints,9) as $id) {
    sprint_engine_dashboard_check(!str_contains($html,get_post_field('post_title',$id)) && !str_contains($html,RunnerRoutes::url(get_post_field('post_name',$id))) && !str_contains($html,get_post_field('post_excerpt',$id)), 'Excluded Sprint title/excerpt/action URL absent: ' . $id);
}
sprint_engine_dashboard_check(str_contains($html,'Estimated time: 1.5 hours') && !str_contains($html,'FULL CONTENT NEVER EXCERPT'), 'Authored duration; no automatic content excerpt.');
sprint_engine_dashboard_check(!str_contains($html,'DENIED IMAGE SECRET') && !str_contains($html,'denied-dashboard-image.png'), 'Access-denied image alt, markup and source URL never leak.');
sprint_engine_dashboard_check('' === $context['sections']['not_started'][0]['excerpt'] && '' === $context['sections']['not_started'][0]['featured_image'] && str_contains($html,'Dashboard card image'), 'Optional authored excerpt and valid image; no fallback artwork.');
sprint_engine_dashboard_check(str_contains($html,'1 of 2 complete — 50%') && str_contains($html,'aria-labelledby="se-dashboard-progress-') && str_contains($html,'role="status"'), 'Progress and restart status have accessible labels.');
sprint_engine_dashboard_check(str_contains($html,'>Start Sprint</a>') && str_contains($html,'>Continue Sprint</a>') && str_contains($html,'>Restart Sprint</button>') && str_contains($html,'>View Completed Sprint</a>') && !str_contains($html,'Attempt 1'), 'Correct card actions without history UI.');
$escaped = $context;
$escaped['sections']['not_started'][0]['title']='<script>alert(1)</script>';
$escaped['sections']['not_started'][0]['excerpt']='<img src=x onerror=alert(1)>';
$escaped_html=sprint_engine_dashboard_html($escaped);
sprint_engine_dashboard_check(str_contains($escaped_html,'&lt;script&gt;alert(1)&lt;/script&gt;') && str_contains($escaped_html,'&lt;img src=x onerror=alert(1)&gt;'), 'Titles and authored excerpts escape stored HTML rather than executing it.');
// A second user's canonical state is independent, regardless of request selectors.
wp_set_current_user($other); $_REQUEST = array('user_id'=>$user,'attempt_number'=>99);
$second = $dashboard->resolve();
sprint_engine_dashboard_check(9 === count($second['sections']['not_started']) && !$second['sections']['completed'] && !$second['sections']['in_progress'], 'Second user sees own Available states, never the first member attempts.');
wp_set_current_user($user);
$history_before = sprint_engine_dashboard_rows(); $restart = $service->restart_sprint($user,$sprints[6]);
$latest = $dashboard->resolve();
sprint_engine_dashboard_check(2 === $restart['attempt_number'] && in_array($sprints[6],array_column($latest['sections']['in_progress'],'sprint_id'),true) && !in_array($sprints[6],array_column($latest['sections']['completed'],'sprint_id'),true), 'Latest restarted attempt alone determines classification; no history duplicate.');
$history_after=sprint_engine_dashboard_rows();
foreach ($history_before as $table=>$rows) {
    sprint_engine_dashboard_check(count(array_udiff($rows,$history_after[$table],static function ($left,$right) { return strcmp(wp_json_encode($left),wp_json_encode($right)); }))===0, 'Restart and Dashboard preserve every previous progress row exactly.');
}
$active_row=(new EnrolmentRepository())->find($user,$sprints[3]);
(new EnrolmentRepository())->update($active_row['id'],array('current_step_id'=>$steps[$sprints[0]][0]));
$stale=sprint_engine_dashboard_rows();$dashboard->resolve();
sprint_engine_dashboard_check($stale===sprint_engine_dashboard_rows(), 'Dashboard never invokes resume repair for a stale current pointer; Runner owns repair.');
(new EnrolmentRepository())->update($active_row['id'],array('current_step_id'=>$active_row['current_step_id']));
(new EnrolmentRepository())->update($active_row['id'],array('last_activity_at'=>null));
$null_sorted=$dashboard->resolve();
sprint_engine_dashboard_check($sprints[3]===end($null_sorted['sections']['in_progress'])['sprint_id'], 'Null activity sorts after valid timestamps.');
(new EnrolmentRepository())->update($active_row['id'],array('last_activity_at'=>$active_row['last_activity_at']));
$failure = static function ($sql) { return str_starts_with($sql,'SHOW TABLE STATUS WHERE Name IN') ? '' : $sql; };
add_filter('query',$failure); $error = $dashboard->resolve(); remove_filter('query',$failure);
sprint_engine_dashboard_check(503 === $error['status'] && !isset($error['sections']) && !str_contains(sprint_engine_dashboard_html($error),'Zulu'), 'Canonical read failure gives generic 503 with no guessed actions or partial cards.');
$deny_all = static function () { return false; }; add_filter('sprint_engine/user_can_access_sprint',$deny_all,99);
$empty = sprint_engine_dashboard_html($dashboard->resolve()); remove_filter('sprint_engine/user_can_access_sprint',$deny_all,99);
sprint_engine_dashboard_check(str_contains($empty,'No Sprints are available to you yet.') && !str_contains($empty,'<section'), 'Useful empty state omits empty sections and counts.');
$method = new ReflectionMethod(Dashboard::class,'timestamp'); $method->setAccessible(true);
foreach (array(null,'bad','2026-02-30 00:00:00',"2026-01-01 00:00:00\0") as $invalid) { sprint_engine_dashboard_check(PHP_INT_MIN === $method->invoke(null,$invalid), 'Invalid/null timestamps sort last without warnings.'); }
$default = dirname((new ReflectionClass(Dashboard::class))->getFileName(),3) . '/templates/dashboard.php';
foreach (array('php://filter/resource=' . $default,$default . "\0",__FILE__,ABSPATH . 'wp-content/uploads/override.php') as $path) {
    $override = static function () use ($path) { return $path; }; add_filter('sprint_engine/dashboard_template',$override);
    sprint_engine_dashboard_check($default === $dashboard->template_path($context), 'Unsafe template override rejected.'); remove_filter('sprint_engine/dashboard_template',$override);
}
$trusted = static function () { return WP_PLUGIN_DIR . '/sprint-engine/templates/dashboard.php'; }; add_filter('sprint_engine/dashboard_template',$trusted);
sprint_engine_dashboard_check(realpath($trusted()) === $dashboard->template_path($context), 'Readable plugin PHP template accepted.'); remove_filter('sprint_engine/dashboard_template',$trusted);
sprint_engine_dashboard_request('/sprint-engine/dashboard/'); $dashboard->prepare(); $dashboard->template('/normal.php'); $dashboard->enqueue();
sprint_engine_dashboard_check(defined('DONOTCACHEPAGE') && DONOTCACHEPAGE && !is_admin_bar_showing() && false === has_action('template_redirect','redirect_canonical'), 'Matched response disables page cache, admin bar and normal canonical redirect.');
sprint_engine_dashboard_check(!$wp_query->posts && !$wp_query->is_home && !$wp_query->is_feed, 'Application query carries no unrelated posts/home/feed flags.');
sprint_engine_dashboard_check(wp_style_is('sprint-engine-dashboard','enqueued') && wp_script_is('sprint-engine-dashboard','enqueued'), 'Namespaced Dashboard assets enqueued with completed cards.');
$style = wp_styles()->get_data('sprint-engine-dashboard','after');
sprint_engine_dashboard_check(str_contains(implode('',$style),'body.se-dashboard{') && str_contains(implode('',$style),ThePath\SprintEngine\Settings\RunnerBranding::css()), 'Dashboard reuses all current branding and derived values.');
wp_set_current_user(1); $settings_before = sprint_engine_dashboard_rows(); ob_start(); (new ThePath\SprintEngine\Settings\SettingsPage())->render(); $settings=ob_get_clean();
sprint_engine_dashboard_check(str_contains($settings,'Member Dashboard') && str_contains($settings,esc_url(Routes::url())) && $settings_before === sprint_engine_dashboard_rows(), 'Existing capability-protected Settings page discovers canonical Dashboard without progress writes.');
wp_set_current_user($user);
$die_handler=static function () { return static function ($message,$title,$args) { throw new RuntimeException('settings-denied-' . $args['response']); }; };
add_filter('wp_die_handler',$die_handler);
$denied=false;
try { (new ThePath\SprintEngine\Settings\SettingsPage())->render(); } catch (RuntimeException $exception) { $denied='settings-denied-403'===$exception->getMessage(); }
remove_filter('wp_die_handler',$die_handler);
sprint_engine_dashboard_check($denied && $settings_before===sprint_engine_dashboard_rows(), 'Member cannot render administrator Settings; existing manage_options boundary remains.');
foreach (array(0,3,7) as $index) {
    $ctx=(new Runner())->resolve(get_post_field('post_name',$sprints[$index])); $snapshot=sprint_engine_dashboard_rows();
    set_query_var('sprint_engine_runner_context',$ctx); ob_start(); include dirname(__DIR__,2) . '/templates/runner.php'; $runner_html=ob_get_clean();
    sprint_engine_dashboard_check(str_contains($runner_html,'href="' . esc_url(Routes::url()) . '"') && str_contains($runner_html,$index===3?'Save &amp; Exit':'Back to My Sprints') && $snapshot===sprint_engine_dashboard_rows(), 'Start/active/completed Runner return links point to Dashboard and template reads do not write.');
}
if (getenv('SE_TEST_HTTP_BASE')) {
    $anon=sprint_engine_dashboard_http('/sprint-engine/dashboard/');
    sprint_engine_dashboard_check(302===wp_remote_retrieve_response_code($anon) && str_contains(wp_remote_retrieve_header($anon,'location'),rawurlencode(Routes::url())) && !str_contains(wp_remote_retrieve_body($anon),'Zulu'), 'Real anonymous HTTP redirect preserves Dashboard return and leaks no title.');
    $response=sprint_engine_dashboard_http('/sprint-engine/dashboard/?feed=rss2&p=' . $sprints[0],$user);
    sprint_engine_dashboard_check(200===wp_remote_retrieve_response_code($response) && str_contains(wp_remote_retrieve_header($response,'cache-control'),'private, no-store') && 'noindex, nofollow'===wp_remote_retrieve_header($response,'x-robots-tag') && str_contains(wp_remote_retrieve_body($response),'<meta name="robots" content="noindex, nofollow">'), 'Real member HTTP response is private/no-store/noindex and ignores feed/post selectors.');
}
remove_filter('sprint_engine/user_can_access_sprint',$deny);
wp_set_current_user(1); $retained=sprint_engine_dashboard_rows(); Plugin::deactivate();
sprint_engine_dashboard_check(!isset(get_option('rewrite_rules')[Routes::RULE]) && !isset(get_option('rewrite_rules')[RunnerRoutes::RULE]) && $retained===sprint_engine_dashboard_rows(), 'Deactivation removes both rules and retains exact progress.');
Plugin::activate();
sprint_engine_dashboard_check(isset(get_option('rewrite_rules')[Routes::RULE],get_option('rewrite_rules')[RunnerRoutes::RULE]) && $retained===sprint_engine_dashboard_rows(), 'Reactivation restores both rules without changing progress.');
foreach ($posts as $id) { wp_delete_post($id,true); } wp_delete_attachment($image,true); wp_delete_attachment($denied_image,true);
foreach (array($user,$other) as $member) { $wpdb->delete($wpdb->prefix . 'sprint_engine_step_progress',array('user_id'=>$member)); $wpdb->delete($wpdb->prefix . 'sprint_engine_enrolments',array('user_id'=>$member)); wp_delete_user($member); }
$wp_rewrite->set_permalink_structure($old_permalink); flush_rewrite_rules(false);
restore_error_handler(); ob_end_flush();
