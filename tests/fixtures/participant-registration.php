<?php
/** CLI control plane for the committed browser/security tests; JSON on stdin. */
if ('yes'!==getenv('SE_TEST_DISPOSABLE') || !getenv('SE_TEST_WP_ROOT')) exit(2);
$_SERVER['HTTPS']='on';$_SERVER['REQUEST_METHOD']='GET';
require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if (!defined('SE0313_DISPOSABLE') || !is_file(ABSPATH.'SE0313-DISPOSABLE')) exit(3);
require_once ABSPATH.'wp-admin/includes/plugin.php';
use ThePath\SprintEngine\Settings\ParticipantAccounts as Accounts;
use ThePath\SprintEngine\Registration\Policy;
use ThePath\SprintEngine\Registration\StateStore;
use ThePath\SprintEngine\Runner\Routes;
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
$input=json_decode(stream_get_contents(STDIN),true);$action=$input['action']??'inspect';
wp_set_current_user(1);global $wpdb;
if ('setup'===$action) {
 $fixtures=array();foreach(array('open'=>'registration-open','international'=>'Café 東京','second'=>'registration-second','tutorial'=>'registration-tutorial','draft'=>'registration-draft') as $key=>$slug){
  $id=wp_insert_post(array('post_type'=>'sprint_engine_sprint','post_status'=>'draft'===$key?'draft':'publish','post_title'=>'PRIVATE REGISTRATION CANARY','post_name'=>$slug,'post_content'=>'PRIVATE OVERVIEW CANARY'));
  $step=(new StructureManager())->quick_add($id,'PRIVATE STEP CANARY');wp_update_post(array('ID'=>$step,'post_status'=>'publish','post_content'=>'PRIVATE BODY CANARY'));
  Meta::save($id,array('_sprint_engine_launchable'=>true));if('tutorial'===$key)update_post_meta($id,ThePath\SprintEngine\Tutorial\Marker::KEY,'');
  $fixtures[$key]=array('id'=>$id,'step'=>$step,'url'=>Routes::url(get_post_field('post_name',$id)));
 }
 $password=bin2hex(random_bytes(20));$id=wp_insert_user(array('user_login'=>'existing_'.bin2hex(random_bytes(8)),'user_email'=>'existing-'.time().'@example.invalid','user_pass'=>$password,'role'=>'subscriber'));
 $fixtures['member']=array('id'=>$id,'email'=>get_userdata($id)->user_email,'password'=>$password);
 update_option('se0313_fixtures',$fixtures,false);update_option('users_can_register',1);update_option('default_role','subscriber');$input['mode']='built_in';$input['confirm']=true;$action='config';
}
if ('config'===$action) {
 if(array_key_exists('behavior',$input))update_option('se0313_behavior',$input['behavior'],false);
 $config=Accounts::get();foreach(array('mode','registration_url','login_url','disable_native') as $key)if(array_key_exists($key,$input))$config[$key]=$input[$key];
 if(array_key_exists('confirm',$input))$config['confirmation']=$input['confirm']?'1':'0';
 if(null===$config['disable_native']&&'built_in'===$config['mode'])$config['disable_native']='1';
 update_option(Accounts::OPTION,Accounts::sanitize($config),false);
}
if ('reset-state'===$action) {$config=Accounts::get();ThePath\SprintEngine\Registration\Maintenance::deactivate();if('built_in'===$config['mode']){$config['confirmation']='1';update_option(Accounts::OPTION,Accounts::sanitize($config),false);}}
if ('policy'===$action) update_option($input['key'],$input['value']);
if ('mfa'===$action) {$mfa=getenv('SE_TEST_MFA_PLUGIN')?:'two-factor/two-factor.php';if($input['enabled']){$result=activate_plugin($mfa);if(is_wp_error($result))throw new RuntimeException('Selected MFA plugin cannot activate on this stack; use a supported version, never bypass its requirements.');}else deactivate_plugins($mfa);}
if ('pro'===$action) {
 if('off'!==$input['order']&&!is_plugin_active('sprint-engine-pro/sprint-engine-pro.php')){$result=activate_plugin('sprint-engine-pro/sprint-engine-pro.php');if(is_wp_error($result))throw new RuntimeException('Actual Pro activation failed');}
 $active=array('sprint-engine/sprint-engine.php','sprint-engine-pro/sprint-engine-pro.php');
 if('off'===$input['order'])$active=array($active[0]);elseif('pro-first'===$input['order'])$active=array_reverse($active);
 update_option('active_plugins',$active); // Next independent request establishes actual load order.
}
if ('pro-classifier'===$action) update_option('se0313_pro_classifier',$input['enabled']?(int)get_option('se0313_fixtures')['open']['id']:0,false);
if ('pro-policy'===$action) {(new ThePath\SprintEnginePro\Access\SprintAccess())->save((int)get_option('se0313_fixtures')['second']['id'],'restricted');update_post_meta((int)get_option('se0313_fixtures')['second']['id'],'sprint_engine_pro_information_url',home_url('/information/'));}
if ('deactivate'===$action) deactivate_plugins('sprint-engine/sprint-engine.php');
if ('activate'===$action) activate_plugin('sprint-engine/sprint-engine.php');
if ('expire'===$action) { $s=json_decode(get_option(StateStore::OPTION),true);foreach($s['entries'] as &$entry)$entry['expires']=time()-1;unset($entry);update_option(StateStore::OPTION,wp_json_encode($s),false); }
if ('repair-steps'===$action) wp_update_post(array('ID'=>(int)get_option('se0313_fixtures')['open']['step'],'post_status'=>'publish'));
$fixtures=get_option('se0313_fixtures');$users=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");
$rows=array();foreach(array('sprint_engine_enrolments','sprint_engine_step_progress') as $table)$rows[$table]=$wpdb->get_results("SELECT * FROM {$wpdb->prefix}{$table} ORDER BY 1",ARRAY_A);
$out=array('fixtures'=>$fixtures,'users'=>$users,'rows'=>$rows,'operational'=>Policy::operational(),'closed'=>Policy::native_closed(),'config'=>Accounts::get(),'gate_log'=>'gate-calls-'.hash('sha256',DB_NAME).'.log','lock_signal'=>'registration-held-'.hash('sha256',DB_NAME),'wp'=>$wp_version,'php'=>PHP_VERSION,'db'=>$wpdb->db_version());
if(isset($input['email'])){$u=get_user_by('email',$input['email']);$out['user']=$u?array('id'=>$u->ID,'login'=>$u->user_login,'roles'=>$u->roles,'first_name'=>get_user_meta($u->ID,'first_name',true),'last_name'=>get_user_meta($u->ID,'last_name',true),'password_ok'=>isset($input['password'])&&wp_check_password($input['password'],$u->user_pass,$u->ID)):null;}
echo wp_json_encode($out);
