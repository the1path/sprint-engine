<?php
/** Disposable loopback-only SE-031.2 fixture; manifest contains local auth cookies. */
if (!getenv('SE_TEST_WP_ROOT') || 'yes'!==getenv('SE_TEST_DISPOSABLE')) exit(1);
require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if (!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true)||'local'!==wp_get_environment_type()) exit(2);
wp_set_current_user(1); require_once ABSPATH.'wp-admin/includes/plugin.php';
if (isset($argv[1])) {
 $f=json_decode(file_get_contents($argv[1]),true);$command=$argv[2]??'inspect';
 if('closure'===$command)update_option('sprint_engine_participant_test_close_native',($argv[3]??'')==='on');
 if('page'===$command)wp_update_post(array('ID'=>$f['registration_page'],'post_status'=>$argv[3]));
 if('unsafe_role'===$command){$r=get_role('subscriber');if('on'===$argv[3])$r->add_cap('edit_posts');else $r->remove_cap('edit_posts');}
 if('flag'===$command)update_option('users_can_register',(int)$argv[3]);
 if('mode'===$command)update_option(ThePath\SprintEngine\Settings\ParticipantAccounts::OPTION,array('mode'=>$argv[3],'registration_url'=>$f['registration'],'login_url'=>'','disable_native'=>($argv[4]??'')!=='off'),false);
 if('settings_raw'===$command)update_option(ThePath\SprintEngine\Settings\ParticipantAccounts::OPTION,json_decode($argv[3],true),false);
 if('rtl'===$command)update_option('sprint_engine_participant_test_rtl','on'===($argv[3]??''));
 if('inspect'===$command){global $wpdb;echo json_encode(array('users'=>(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'settings'=>ThePath\SprintEngine\Settings\ParticipantAccounts::get(),'native_flag'=>get_option('users_can_register'),'role'=>get_option('default_role'),'attempts'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id",ARRAY_A),'steps'=>$wpdb->get_results("SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id",ARRAY_A)));}
 exit;
}
file_put_contents(ABSPATH.'sprint-engine-participant-test-marker','DISPOSABLE SE-031.2');
file_put_contents(WPMU_PLUGIN_DIR.'/participant-test.php', <<<'MU'
<?php
// TEST ONLY: neither classifier policy nor operational readiness ships in Core.
if(!is_file(ABSPATH.'sprint-engine-participant-test-marker')||'local'!==wp_get_environment_type()||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),array('localhost','127.0.0.1'),true))return;
add_filter('sprint_engine/participant_access_kind',function($kind,$id){$map=get_option('sprint_engine_participant_test_kinds',array());return $map[$id]??$kind;},10,2);
add_filter('sprint_engine/runner_show_public_access_gate',function($show,$id){return 'restricted'===(get_option('sprint_engine_participant_test_kinds',array())[$id]??'')?true:$show;},10,2);
add_filter('sprint_engine/user_can_access_sprint',function($allow,$user,$id){return 'restricted'===(get_option('sprint_engine_participant_test_kinds',array())[$id]??'')?false:$allow;},10,3);
add_action('login_form_register',function(){
 if(is_multisite()||!get_option('sprint_engine_participant_test_close_native'))return;
 $config=ThePath\SprintEngine\Settings\ParticipantAccounts::get();
 if('built_in'!==$config['mode']||true!==$config['disable_native']||ThePath\SprintEngine\Settings\ParticipantAccounts::native_policy_problems())return;
 nocache_headers();header('Cache-Control: private, no-store, max-age=0');header('X-Robots-Tag: noindex, nofollow');
 $return='';$raw=$_REQUEST['redirect_to']??'';
 if(is_string($raw)&&strlen($raw)<=2048){foreach(get_option('sprint_engine_participant_test_routes',array()) as $url){if($raw===$url){$return=$url;break;}}}
 $body='<p>DISPOSABLE NATIVE CLOSURE: registration is handled by the configured participant journey.</p><p><a href="'.esc_url(wp_login_url($return)).'">Sign in</a> <a href="'.esc_url(wp_lostpassword_url($return)).'">Recover password</a></p>';
 if($return)$body.='<p><a href="'.esc_url($return).'">Return to original Sprint</a></p>';
 wp_die($body,'Participant accounts',array('response'=>403));
},0);
add_filter('language_attributes',function($value){return get_option('sprint_engine_participant_test_rtl')?'lang="ar" dir="rtl"':$value;});
add_filter('gettext',function($translation,$text,$domain){return get_option('sprint_engine_participant_test_rtl')&&'sprint-engine'===$domain&&in_array($text,array('Sign in','Create account','Your next step starts here'),true)?str_repeat('تجربة المشاركة والوصول ',3):$translation;},10,3);
MU
);
update_option('users_can_register',1);update_option('default_role','subscriber');delete_option(ThePath\SprintEngine\Settings\ParticipantAccounts::OPTION);
$f=array('home'=>home_url('/'),'settings'=>admin_url('edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings'),'routes'=>array());$manager=new ThePath\SprintEngine\Content\StructureManager();$map=array();
foreach(array('open','restricted','unknown','tutorial','corrupt','draft','password','unlaunchable','broken') as $kind){
 $id=wp_insert_post(array('post_type'=>'sprint_engine_sprint','post_status'=>'publish','post_title'=>'PRIVATE '.$kind,'post_name'=>'participant-'.$kind,'post_content'=>'PRIVATE INTRO'));
 $step=$manager->quick_add($id,'PRIVATE STEP');wp_update_post(array('ID'=>$step,'post_status'=>'publish','post_content'=>'PRIVATE STEP BODY'));
 ThePath\SprintEngine\Content\Meta::save($id,array('_sprint_engine_launchable'=>true));
 if('draft'===$kind)wp_update_post(array('ID'=>$id,'post_status'=>'draft'));
 if('password'===$kind)wp_update_post(array('ID'=>$id,'post_password'=>'fixture-only'));
 if('unlaunchable'===$kind)update_post_meta($id,'_sprint_engine_launchable',false);
 if('broken'===$kind)update_post_meta($step,'_sprint_engine_next_step_id',$step);
 if(in_array($kind,array('tutorial','corrupt'),true))update_post_meta($id,ThePath\SprintEngine\Tutorial\Marker::KEY,'corrupt'===$kind?'':'1');
 if(in_array($kind,array('open','restricted','unknown'),true))$map[$id]=$kind;
 $f['routes'][$kind]=array('id'=>$id,'step'=>$step,'url'=>ThePath\SprintEngine\Runner\Routes::url(get_post_field('post_name',$id)));
}
update_option('sprint_engine_participant_test_kinds',$map,false);update_option('sprint_engine_participant_test_routes',array_column($f['routes'],'url'),false);
$p=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Existing account provider','post_name'=>'participant-provider','post_content'=>'Provider fixture deliberately ignores return hints. Use original Sprint link.'));$f['registration_page']=$p;$f['registration']=get_permalink($p);
$p=wp_insert_post(array('post_type'=>'page','post_status'=>'publish','post_title'=>'Privacy','post_name'=>'participant-privacy'));update_option('wp_page_for_privacy_policy',$p);
foreach(array('administrator','subscriber') as $role){
 $password=wp_generate_password(30,false);$user=wp_insert_user(array('user_login'=>'participant_browser_'.wp_generate_password(12,false),'user_email'=>'browser-'.wp_generate_password(10,false).'@example.invalid','user_pass'=>$password,'role'=>$role));wp_set_current_user($user);
 $cookie=wp_generate_auth_cookie($user,time()+HOUR_IN_SECONDS,'logged_in');$token=wp_parse_auth_cookie($cookie,'logged_in')['token'];$auth=wp_generate_auth_cookie($user,time()+HOUR_IN_SECONDS,'auth',$token);
 $f[$role]=array('id'=>$user,'password'=>$password,'email'=>get_userdata($user)->user_email,'cookies'=>array(array('name'=>LOGGED_IN_COOKIE,'value'=>$cookie,'url'=>home_url('/')),array('name'=>AUTH_COOKIE,'value'=>$auth,'url'=>home_url('/'))));
}
wp_set_current_user(1);set_site_transient('update_core',(object)array('updates'=>array(),'version_checked'=>$wp_version,'last_checked'=>time()));set_site_transient('update_plugins',(object)array('last_checked'=>time(),'checked'=>array(),'response'=>array(),'no_update'=>array()));set_site_transient('update_themes',(object)array('last_checked'=>time(),'checked'=>array(),'response'=>array(),'no_update'=>array()));
echo wp_json_encode($f,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES);
