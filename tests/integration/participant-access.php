<?php
/** SE-031.2: actual WordPress settings, classification and Runner boundaries. */
use ThePath\SprintEngine\Settings\ParticipantAccounts as Accounts;
use ThePath\SprintEngine\Settings\ParticipantAccountsAdmin as Admin;
use ThePath\SprintEngine\Access\AccessManager;
use ThePath\SprintEngine\Access\AccessProvider;
use ThePath\SprintEngine\Runner\Runner;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;

if (!getenv('SE_TEST_WP_ROOT') || 'yes' !== getenv('SE_TEST_DISPOSABLE')) { fwrite(STDERR,"Disposable WordPress required.\n"); exit(1); }
ob_start();
$_SERVER['SERVER_NAME']='127.0.0.1'; $_SERVER['HTTP_HOST']='127.0.0.1'; $_SERVER['REQUEST_URI']='/'; $_SERVER['REQUEST_METHOD']='GET';
require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
require_once ABSPATH.'wp-admin/includes/class-wp-screen.php';
require_once ABSPATH.'wp-admin/includes/screen.php';
require_once ABSPATH.'wp-admin/includes/template.php';
require_once ABSPATH.'wp-admin/includes/plugin.php';
require_once ABSPATH.'wp-admin/includes/user.php';
error_reporting(E_ALL);
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$count=0;
function participant_check($ok,$name){global $count;if(!$ok)throw new RuntimeException($name);++$count;echo 'PASS: '.$name."\n";}
function participant_html($context){set_query_var('sprint_engine_runner_context',$context);ob_start();include dirname(__DIR__,2).'/templates/runner.php';return ob_get_clean();}
function participant_config($mode,$registration='',$login='',$disable=false){return array('mode'=>$mode,'registration_url'=>$registration,'login_url'=>$login,'disable_native'=>$disable);}
function participant_save($value){update_option(Accounts::OPTION,Accounts::sanitize($value),false);}
function participant_rows(){global $wpdb;return array_map(static fn($table)=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table} ORDER BY 1",ARRAY_A),array('sprint_engine_enrolments','sprint_engine_step_progress'));}

wp_set_current_user(1);
$admin=new Admin();
delete_option(Accounts::OPTION);
participant_check(Accounts::defaults()===Accounts::get(),'Absent configuration is safe unset');
$admin->register();
global $wpdb,$wp_registered_settings;
$autoload=$wpdb->get_var($wpdb->prepare("SELECT autoload FROM $wpdb->options WHERE option_name=%s",Accounts::OPTION));
participant_check(in_array($autoload,array('no','off','auto-off'),true),'Settings storage is non-autoloaded on current and floor');
participant_check(false===$wp_registered_settings[Accounts::OPTION]['show_in_rest'],'Participant option is not REST-exposed');
participant_check(''!==Accounts::status(),'Fresh/upgrade unset remains actionable');
foreach(array('dashboard','edit-sprint_engine_sprint','edit-sprint_engine_step','sprint_engine_sprint_page_sprint-engine-settings','sprint_engine_sprint_page_sprint-engine-welcome','edit-page','plugins','profile','users') as $screen){
 set_current_screen($screen);ob_start();$admin->notice();$html=ob_get_clean();
 $expected=in_array($screen,array('dashboard','edit-sprint_engine_sprint','edit-sprint_engine_step','sprint_engine_sprint_page_sprint-engine-settings','sprint_engine_sprint_page_sprint-engine-welcome'),true);
 participant_check($expected===str_contains($html,'Review participant setup')&&!str_contains($html,'is-dismissible'),'Setup notice scoped and persistent: '.$screen);
}
$member=wp_insert_user(array('user_login'=>'participant_'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber'));
wp_set_current_user($member);set_current_screen('dashboard');ob_start();$admin->notice();Admin::render();$html=ob_get_clean();
participant_check(''===$html,'Subscriber sees no setup notice or admin fields');wp_set_current_user(1);

$page=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Accounts','post_name'=>'participant-accounts'));
$registration=get_permalink($page);
participant_check($registration===Accounts::destination($registration),'Published passwordless same-site page accepted');
participant_save(participant_config('external',$registration,'','1'));$saved=Accounts::get();
participant_check('external'===$saved['mode']&&null===$saved['disable_native']&&''===Accounts::status(),'Valid external resolves setup without inferring native choice');
participant_save(participant_config('external',$registration,'','0'));participant_check(false===Accounts::get()['disable_native'],'Explicit external-mode checkbox opt-out is retained before first built-in save');
$saved=Accounts::get();
$bad=array('invalid',array(),array('mode'=>'external'),array_merge($saved,array('extra'=>'tampered')),array_merge($saved,array('mode'=>array('invitation'))),array_merge($saved,array('mode'=>'EXTERNAL')),array_merge($saved,array('registration_url'=>array('bad'))),array_merge($saved,array('login_url'=>'https://outside.invalid/')),array_merge($saved,array('disable_native'=>array(1))),array_merge($saved,array('disable_native'=>'yes')),participant_config('external',''));
foreach($bad as $input){participant_save($input);participant_check($saved===Accounts::get(),'Whole invalid submission preserves previous valid configuration');}
wp_update_post(array('ID'=>$page,'post_status'=>'draft'));
participant_check(''===Accounts::destination($registration)&&''!==Accounts::status(),'Page unpublication invalidates external registration at runtime');
wp_update_post(array('ID'=>$page,'post_status'=>'publish','post_password'=>'secret'));
participant_check(''===Accounts::destination($registration),'Page password invalidates existing destination');
wp_update_post(array('ID'=>$page,'post_password'=>''));
participant_save(participant_config('built_in','','','1'));
participant_check(true===Accounts::get()['disable_native']&&str_contains(Accounts::status(),'not operational'),'First deliberate built-in save persists checked but dormant preference');
participant_save(participant_config('built_in','','','0'));
participant_check(false===Accounts::get()['disable_native'],'Explicit opt-out persists');
participant_save(participant_config('invitation','','','0'));
participant_check(''===Accounts::status(),'Invitation-only resolves setup');
participant_save(participant_config('built_in','','','0'));
participant_check(false===Accounts::get()['disable_native'],'Returning to built-in does not re-check opted-out preference');
ob_start();Admin::render();$html=ob_get_clean();
participant_check(str_contains($html,'not protected')&&str_contains($html,'dormant now')&&str_contains($html,'options-general.php'),'Dormancy and unchecked warning remain visible with direct General link');
$flag=get_option('users_can_register');$role=get_option('default_role');
update_option('users_can_register',0);update_option('default_role','administrator');
participant_check(2===count(Accounts::native_policy_problems()),'Flag and unsafe default role both diagnosed');
update_option('users_can_register',1);update_option('default_role','subscriber');
participant_check(array()===Accounts::native_policy_problems(),'Safe Subscriber/native policy recognised without enabling form');
get_role('subscriber')->add_cap('edit_posts');participant_check(1===count(Accounts::native_policy_problems()),'Elevated Subscriber capability refused');get_role('subscriber')->remove_cap('edit_posts');
get_role('subscriber')->remove_cap('read');participant_check(1===count(Accounts::native_policy_problems()),'Missing Subscriber read capability refused');get_role('subscriber')->add_cap('read');
update_option('users_can_register',$flag);update_option('default_role',$role);

// Exercise exact origin, default ports and subdirectory boundary without HTTP fetches.
$home=static fn()=> 'https://example.invalid/subsite/';add_filter('home_url',$home);
foreach(array('https://example.invalid/subsite/account','https://example.invalid:443/subsite/account?action=register','https://example.invalid/subsite/wp-login.php?action=register','https://example.invalid/subsite/compte-%C3%A9') as $url){participant_check($url===Accounts::destination($url),'Valid exact-origin account destination: '.$url);}
$invalid=array('//example.invalid/subsite/account','http://example.invalid/subsite/account','https://example.invalid:444/subsite/account','https://other.invalid/subsite/account','https://example.invalid.evil/subsite/account','https://user@example.invalid/subsite/account','javascript:alert(1)','https://example.invalid/subsite-other/account','https://example.invalid/account','https://example.invalid/subsite/../account','https://example.invalid/subsite/%2e%2e/account','https://example.invalid/subsite/%252e%252e/account','https://example.invalid/subsite//account','https://example.invalid/subsite/account%0d%0aHeader:x','https://example.invalid/subsite/account?redirect_to=https://outside.invalid','https://example.invalid/subsite/account?re%64irect_to=/','https://example.invalid/subsite/account?return_to=/','https://example.invalid/subsite/account?x[]=y','https://example.invalid/subsite/account#fragment','https://example.invalid/subsite/wp-login.php?action=logout','https://example.invalid/subsite/wp-login.php?action=rp','https://example.invalid/subsite/wp-admin/admin-post.php','https://example.invalid/subsite/my-account/customer-logout/','https://example.invalid/subsite/sprint/test/','https://example.invalid/subsite/sprint-engine/dashboard/','https://example.invalid/subsite/account?rest_route=/users','https://example.invalid/subsite/account?wc-ajax=checkout','https://example.invalid/subsite/account?x=%250a',"https://example.invalid/subsite/account\0",str_repeat('x',2049));
foreach($invalid as $i=>$url){participant_check(''===Accounts::destination($url),'Unsafe URL rejected #'.$i);}
foreach(array('https://example.invalid/subsite/wp-login.php?action=register&key=reset-secret','https://example.invalid/subsite/account?token=secret','https://example.invalid/subsite/account?action=delete_account','https://example.invalid/subsite/wordpress/wp-admin/admin-post.php','https://example.invalid/subsite/account%3faction=logout','https://example.invalid/subsite/account?continue=https://outside.invalid') as $url){participant_check(''===Accounts::destination($url),'Encoded/auth-mutation/secret destination refused');}
remove_filter('home_url',$home);
$invalid_home=static fn()=>'/relative-home/';add_filter('home_url',$invalid_home);participant_check(''===Accounts::destination('https://example.invalid/account'),'Invalidated home origin fails safely without PHP notices');remove_filter('home_url',$invalid_home);

$manager=new StructureManager();$id=wp_insert_post(array('post_type'=>'sprint_engine_sprint','post_status'=>'publish','post_title'=>'PRIVATE SPRINT TITLE','post_name'=>'participant-boundary','post_content'=>'PRIVATE OVERVIEW'));
$step=$manager->quick_add($id,'PRIVATE STEP TITLE');wp_update_post(array('ID'=>$step,'post_status'=>'publish','post_content'=>'PRIVATE STEP BODY'));Meta::save($id,array('_sprint_engine_launchable'=>true));
$slug=get_post_field('post_name',$id);$canonical=Routes::url($slug);$runner=new Runner();$access=new AccessManager();
$progress=new ThePath\SprintEngine\Progress\ProgressService();wp_set_current_user($member);$state=$progress->start_sprint($member,$id);$progress->complete_step($member,$id,$step,$state['attempt_id']);$progress->restart_sprint($member,$id,$state['attempt_id']);wp_set_current_user(0);$before=participant_rows();
participant_check('open'===$access->participant_access_kind($id),'Unextended Core classified open');
$member_calls=0;$member_filter=static function($allow)use(&$member_calls){++$member_calls;return $allow;};add_filter('sprint_engine/user_can_access_sprint',$member_filter);
participant_check('unknown'===$access->participant_access_kind($id)&&0===$member_calls,'Member filter makes guest unknown without fabricated policy call');
$classifier=static fn()=> 'open';add_filter('sprint_engine/participant_access_kind',$classifier);
participant_check('open'===$access->participant_access_kind($id),'One coordinated classifier can explicitly assert open');
$collision=static fn()=> 'restricted';add_filter('sprint_engine/participant_access_kind',$collision,20);
participant_check('unknown'===$access->participant_access_kind($id),'Conflicting classifiers fail closed');remove_filter('sprint_engine/participant_access_kind',$collision,20);remove_filter('sprint_engine/participant_access_kind',$classifier);
foreach(array(true,1,'OPEN',null,array('open'),new stdClass()) as $value){$invalid_filter=static fn()=> $value;add_filter('sprint_engine/participant_access_kind',$invalid_filter);participant_check('unknown'===$access->participant_access_kind($id),'Invalid classifier output is unknown');remove_filter('sprint_engine/participant_access_kind',$invalid_filter);}
remove_filter('sprint_engine/user_can_access_sprint',$member_filter);
$custom=new AccessManager(new class implements AccessProvider {public function can_access($user_id,$sprint_id){throw new RuntimeException('Guest invoked provider');}});
participant_check('unknown'===$custom->participant_access_kind($id),'Custom provider is unknown without invoking it');
delete_option(Accounts::OPTION);
participant_check('login'===$runner->resolve($slug)['state'],'Unset retains native redirect');
$gate_calls=0;$denials=array();$gate_value=false;$gate=static function($show,$sprint)use(&$gate_calls,&$gate_value,$id){participant_check(false===$show&&$id===$sprint,'Gate receives unchanged arguments');++$gate_calls;return $gate_value;};$denial=static function($sprint)use(&$denials){$denials[]=$sprint;echo '<p>SAFE INFORMATION</p>';};
add_filter('sprint_engine/runner_show_public_access_gate',$gate,10,2);add_action('sprint_engine/runner_access_denied_actions',$denial);
foreach(array('built_in','invitation','external') as $mode){participant_save(participant_config($mode,$registration));foreach(array(false,true,1,'true') as $value){$gate_value=$value;$gate_calls=0;$denials=array();$context=$runner->resolve($slug);$html=participant_html($context);
 participant_check(1===$gate_calls&&(true===$value?403:200)===$context['status']&&($denials===(true===$value?array($id):array())),'Configured guest gate/denial timing and exact-true preserved: '.$mode);
 participant_check(!str_contains($html,'PRIVATE')&&!str_contains($html,'wp_rest')&&!str_contains($html,'sprintEngineRunner')&&!str_contains($html,'Start Sprint')&&!isset($context['progress']),'Guest omits content, progress, Start and write configuration');
 participant_check(('external'===$mode)===str_contains($html,'Create account'),'Only working external open destination offers account creation');
 parse_str(wp_parse_url($context['guest']['login_url'],PHP_URL_QUERY),$query);participant_check($canonical===$query['redirect_to'],'Native login return is server canonical');
}}
$gate_value=true;add_filter('sprint_engine/user_can_access_sprint',$member_filter);$context=$runner->resolve($slug);participant_check('unknown'===$context['guest']['kind']&&''===$context['guest']['signup_url'],'Old integration without classifier gets neutral guest screen');
$classifier=static fn()=> 'open';add_filter('sprint_engine/participant_access_kind',$classifier);$gate_calls=0;$denials=array();$context=$runner->resolve($slug);participant_html($context);participant_check(''!==$context['guest']['signup_url']&&403===$context['status']&&1===$gate_calls&&array($id)===$denials,'Explicit open plus Pro-style gate retains denial and enables only safe handoff');remove_filter('sprint_engine/participant_access_kind',$classifier);remove_filter('sprint_engine/user_can_access_sprint',$member_filter);
remove_filter('sprint_engine/runner_show_public_access_gate',$gate);remove_action('sprint_engine/runner_access_denied_actions',$denial);
$class_calls=0;$class=static function($kind)use(&$class_calls){++$class_calls;return $kind;};add_filter('sprint_engine/participant_access_kind',$class);
foreach(array('draft','private','pending','trash') as $status){wp_set_current_user(1);wp_update_post(array('ID'=>$id,'post_status'=>$status));wp_set_current_user(0);$class_calls=0;participant_check(404===$runner->resolve($slug)['status']&&0===$class_calls,'Unready '.$status.' bypasses classification');}
wp_set_current_user(1);wp_update_post(array('ID'=>$id,'post_status'=>'publish','post_password'=>'secret'));wp_set_current_user(0);$class_calls=0;participant_check(404===$runner->resolve($slug)['status']&&0===$class_calls,'Password blocks classification');
wp_set_current_user(1);wp_update_post(array('ID'=>$id,'post_password'=>''));update_post_meta($id,'_sprint_engine_launchable',false);wp_set_current_user(0);participant_check(404===$runner->resolve($slug)['status'],'Unlaunchable remains unavailable');
wp_set_current_user(1);Meta::save($id,array('_sprint_engine_launchable'=>true));update_post_meta($step,'_sprint_engine_next_step_id',$step);wp_set_current_user(0);participant_check(404===$runner->resolve($slug)['status'],'Broken chain remains unavailable');
wp_set_current_user(1);update_post_meta($step,'_sprint_engine_next_step_id',0);wp_update_post(array('ID'=>$step,'post_status'=>'draft'));wp_set_current_user(0);participant_check(404===$runner->resolve($slug)['status'],'Draft Step remains unavailable');wp_set_current_user(1);wp_update_post(array('ID'=>$step,'post_status'=>'publish'));wp_set_current_user(0);
foreach(array('1','','corrupt') as $marker){update_post_meta($id,ThePath\SprintEngine\Tutorial\Marker::KEY,$marker);$class_calls=0;participant_check('login'===$runner->resolve($slug)['state']&&0===$class_calls&&'restricted'===$access->participant_access_kind($id),'Tutorial marker, including corrupt, retains admin sign-in');}delete_post_meta($id,ThePath\SprintEngine\Tutorial\Marker::KEY);remove_filter('sprint_engine/participant_access_kind',$class);
participant_check(404===$runner->resolve('missing-participant')['status']&&404===$runner->resolve('bad%2fslug')['status'],'Missing and malformed never promote to signup');
participant_check($before===participant_rows(),'All guest/settings reads preserve actual completed history and restarted attempt');
wp_set_current_user($member);participant_check('in_progress'===$runner->resolve($slug)['state'],'Existing authenticated resume preserved');
$deny=static fn()=>false;add_filter('sprint_engine/user_can_access_sprint',$deny);participant_check(403===$runner->resolve($slug)['status']&&!isset($runner->resolve($slug)['guest']),'Actual member denial unchanged');remove_filter('sprint_engine/user_can_access_sprint',$deny);
wp_set_current_user(1);wp_delete_post($id,true);wp_delete_post($step,true);wp_delete_post($page,true);wp_delete_user($member);delete_option(Accounts::OPTION);
echo "NOT RUN: Full network installation; this focused suite uses single-site WordPress.\n";
echo "Completed $count participant access checks.\n";
ob_end_flush();
