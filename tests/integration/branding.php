<?php
/** SE-008 checks. Disposable WordPress only. */
use ThePath\SprintEngine\Settings\RunnerBranding as Branding;
use ThePath\SprintEngine\Settings\SettingsPage;
use ThePath\SprintEngine\Runner\Runner;
$root = getenv( 'SE_TEST_WP_ROOT' );
if ( ! $root || 'yes' !== getenv( 'SE_TEST_DISPOSABLE' ) ) { exit(1); }
ob_start();
$_SERVER['HTTP_HOST'] = '127.0.0.1:8094';
require $root . '/wp-load.php';
require_once ABSPATH . 'wp-admin/includes/admin.php';
error_reporting(E_ALL);
set_error_handler(static function($severity,$message,$file,$line){ throw new ErrorException($message,0,$severity,$file,$line); });
$count = 0;
function check_branding($condition,$message) { global $count; if (!$condition) throw new RuntimeException($message); $count++; echo "PASS: $message\n"; }
wp_set_current_user(1);
$page = new SettingsPage();
$page->register();
$page->menu();
$menu = $GLOBALS['submenu']['edit.php?post_type=sprint_engine_sprint'];
check_branding(count(array_filter($menu, static fn($item) => $item[2] === 'sprint-engine-settings' && $item[1] === 'manage_options')) === 1, 'Settings submenu and capability');
$before = array();
foreach (array('posts','postmeta','sprint_engine_enrolments','sprint_engine_step_progress') as $table) { $before[$table] = $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table} ORDER BY 1", ARRAY_A); }
$versions = array(get_option('sprint_engine_version'),get_option('sprint_engine_schema_version'),get_option('rewrite_rules'));
delete_option(Branding::OPTION);
check_branding(Branding::get() === Branding::defaults(), 'Missing option returns complete defaults');
$baseline = Branding::variables();
foreach(array('primary'=>'#205b48','background'=>'#f3f6f5','surface'=>'#ffffff','text'=>'#203630','muted'=>'#50645d','accent'=>'#276a55','radius'=>'1rem','control-radius'=>'0.5rem') as $key=>$value) check_branding($baseline['--se-'.$key] === $value, 'Default '.$key);
check_branding(Branding::logo() === '', 'Default has no broken image');
remove_filter('sanitize_option_'.Branding::OPTION,array(Branding::class,'sanitize'));
foreach(array('bad',array('primary'=>'#123456'),array('primary'=>array(),'corner_style'=>array(),'logo_id'=>array())) as $raw) {
 update_option(Branding::OPTION,$raw); $read=Branding::get(); check_branding(count($read)===8 && $read['surface']==='#ffffff','Corrupt/partial storage merged safely');
}
delete_option(Branding::OPTION); $page->register();
foreach(array('primary','primary_text','background','surface','text','muted') as $key) {
    update_option(Branding::OPTION, array($key=>'#Ab1234','evil'=>'body{}'));
    check_branding(Branding::get()[$key] === '#ab1234' && !isset(get_option(Branding::OPTION)['evil']), 'Save valid '.$key.' and strip unknown keys');
    update_option(Branding::OPTION, array($key=>'#fff;}body{display:none'));
    check_branding(Branding::get()[$key] === '#ab1234' && !str_contains(Branding::css(),'display'), 'Reject CSS injection '.$key);
}
foreach(array_keys(Branding::RADII) as $corner) { update_option(Branding::OPTION,array('corner_style'=>$corner)); check_branding(Branding::get()['corner_style'] === $corner, 'Corner '.$corner); }
update_option(Branding::OPTION,array('corner_style'=>'99px;evil'));
check_branding(Branding::get()['corner_style'] === 'rounded','Invalid corner retains prior value');
foreach(array(null,'broken',array('primary'=>array()),array('logo_id'=>array()),array('primary'=>'#fff')) as $bad) { check_branding(count(Branding::sanitize($bad)) === 8, 'Malformed values safely normalized'); }
update_option(Branding::OPTION,array('primary_text'=>'#123456'));
check_branding(Branding::get()['primary_text'] === '#123456' && Branding::variables()['--se-on-primary'] === '#123456', 'Custom primary text is saved and emitted exactly');
update_option(Branding::OPTION,array('primary_text'=>''));
check_branding(Branding::get()['primary_text'] === '' && Branding::variables()['--se-on-primary'] === Branding::foreground(Branding::get()['primary']), 'Clearing primary text restores automatic contrast');
$image = wp_insert_attachment(array('post_title'=>'Logo','post_mime_type'=>'image/png','guid'=>home_url('/logo.png')));
$file = wp_insert_attachment(array('post_title'=>'Document','post_mime_type'=>'application/pdf','guid'=>home_url('/document.pdf')));
update_post_meta($image, '_wp_attached_file', 'logo.png');
update_post_meta($image, '_wp_attachment_metadata', array('width'=>180,'height'=>60,'file'=>'logo.png'));
update_post_meta($image, '_wp_attachment_image_alt', 'Logo " <script>');
update_option(Branding::OPTION,array('logo_id'=>$image));
check_branding(Branding::get()['logo_id'] === $image && str_contains(Branding::logo(),'<img') && !str_contains(Branding::logo(),'<script>'), 'Image accepted and escaped by WordPress');
foreach(array($file,999999999,'',-1,'1.5',true,1.0,array(),null) as $bad) {
 $GLOBALS['wp_settings_errors']=array();
 update_option(Branding::OPTION,array('logo_id'=>$bad));
 check_branding(Branding::get()['logo_id'] === $image && count(get_settings_errors(Branding::OPTION)) > 0, 'Invalid logo retains saved image and reports settings error');
}
update_option(Branding::OPTION,array('logo_id'=>0));
check_branding(Branding::get()['logo_id'] === 0 && Branding::logo() === '', 'Explicit zero intentionally removes logo');
foreach(array('#000000'=>'#ffffff','#205b48'=>'#ffffff','#ffffee'=>'#000000','#ffffff'=>'#000000') as $hex=>$foreground) check_branding(Branding::foreground($hex) === $foreground, 'Primary contrast '.$hex);
wp_styles()->queue = array(); wp_scripts()->queue = array();
$page->enqueue('index.php');
check_branding(!wp_style_is('sprint-engine-runner-settings','enqueued') && !wp_script_is('media-views','enqueued'), 'Unrelated admin omits settings assets');
$page->enqueue(get_plugin_page_hookname('sprint-engine-settings','edit.php?post_type=sprint_engine_sprint'));
check_branding(wp_style_is('sprint-engine-runner-settings','enqueued') && wp_script_is('wp-color-picker','enqueued') && wp_script_is('media-views','enqueued'), 'Settings assets and core facilities scoped');
check_branding(wp_styles()->registered['sprint-engine-runner-settings']->ver === ThePath\SprintEngine\Assets::version('assets/css/runner-settings.css'), 'Admin asset fingerprint');
ob_start(); $page->render(); $html = ob_get_clean();
check_branding(str_contains($html,'se-branding-preview') && str_contains($html,'name="_wpnonce"') && str_contains($html,'options.php'), 'Preview and Settings API nonce form');
$GLOBALS['wp_styles'] = new WP_Styles();
set_query_var('sprint_engine_sprint_slug',null);
(new Runner())->enqueue();
check_branding(!wp_style_is('sprint-engine-runner','enqueued'), 'Unrelated frontend omits branding');
// Test the real request guard using the route query variable.
$wp->matched_rule = ThePath\SprintEngine\Runner\Routes::RULE; $wp->request = 'sprint/example/';
(new Runner())->enqueue();
check_branding(str_contains(implode('',wp_styles()->get_data('sprint-engine-runner','after') ?: array()),'body.se-runner{--se-primary:'), 'Runner tracked inline CSS');
add_filter('wp_die_handler',static fn()=>static function(){throw new RuntimeException('denied');});
$member = wp_insert_user(array('user_login'=>'branding-'.wp_generate_password(8,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber'));
wp_set_current_user($member);
foreach(array('render','reset') as $method) { try { $page->$method(); throw new LogicException('not denied'); } catch(RuntimeException $e) { check_branding($e->getMessage()==='denied','Capability protects '.$method); } }
wp_set_current_user(1); require_once ABSPATH . 'wp-admin/includes/user.php'; wp_delete_user($member); $_REQUEST['_wpnonce']='invalid';
try {$page->reset(); throw new LogicException('not denied');} catch(RuntimeException $e) {check_branding($e->getMessage()==='denied','Reset nonce enforced');}
wp_delete_attachment($image,true); wp_delete_attachment($file,true);
delete_option(Branding::OPTION);
foreach($before as $table=>$rows) check_branding($rows === $wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table} ORDER BY 1",ARRAY_A),'Branding leaves '.$table.' intact');
check_branding($versions === array(get_option('sprint_engine_version'),get_option('sprint_engine_schema_version'),get_option('rewrite_rules')),'No version/schema/rewrite changes');
check_branding(Branding::variables() === $baseline,'Reset defaults restore baseline');
echo "Completed $count branding checks.\n";
