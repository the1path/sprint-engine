<?php
/** SE-031.3: real native WP/account/storage contracts on an explicitly disposable site. */
use ThePath\SprintEngine\Registration\AccountService;
use ThePath\SprintEngine\Registration\BrowserState;
use ThePath\SprintEngine\Registration\Budget;
use ThePath\SprintEngine\Registration\Input;
use ThePath\SprintEngine\Registration\Maintenance;
use ThePath\SprintEngine\Registration\Policy;
use ThePath\SprintEngine\Registration\StateStore;
use ThePath\SprintEngine\Settings\ParticipantAccounts as Accounts;

if (!getenv('SE_TEST_WP_ROOT') || 'yes'!==getenv('SE_TEST_DISPOSABLE')) exit("Explicit disposable WordPress required.\n");
ob_start(); $_SERVER['HTTPS']='on'; $_SERVER['REQUEST_METHOD']='GET';
require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if(!defined('SE0313_DISPOSABLE')||!SE0313_DISPOSABLE||!is_file(ABSPATH.'SE0313-DISPOSABLE'))throw new RuntimeException('Marked isolated registration test site required.');
require_once ABSPATH.'wp-admin/includes/user.php';
error_reporting(E_ALL);
set_error_handler(static function($severity,$message,$file,$line){throw new ErrorException($message,0,$severity,$file,$line);});
$checks=0;
function registration_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);++$checks;echo "PASS: $label\n";}
function registration_values($email=null,$password=null){return array('first_name'=>"Élodie O'Connor",'last_name'=>'李 أحمد','email'=>$email??('synthetic-'.bin2hex(random_bytes(8)).'@example.invalid'),'password'=>$password??"Native-'\\-🔒-correct-password");}
function registration_create($values,$eligible=null,$token=null,$browser=null,$peer='192.0.2.1',$errors=array()){
 return (new AccountService())->create($values,$token??bin2hex(random_bytes(16)),$browser??bin2hex(random_bytes(32)),$peer,$errors,$eligible??static fn()=>Policy::operational());
}
function registration_confirm(){update_option(Accounts::OPTION,Accounts::sanitize(array('mode'=>'built_in','registration_url'=>'','login_url'=>'','disable_native'=>'1','confirmation'=>'1')),false);}
function registration_reset_state(){Maintenance::deactivate();registration_confirm();}
global $wpdb;
wp_set_current_user(1); delete_option(StateStore::OPTION); update_option('users_can_register',1);update_option('default_role','subscriber');
update_option(Accounts::OPTION,array('mode'=>'built_in','registration_url'=>'','login_url'=>'','disable_native'=>true));
registration_check(!Policy::confirmed()&&!Policy::operational()&&!Policy::native_closed(),'SE-031.2 pending preference cannot activate either route');
registration_check('ready'===(new StateStore())->health(),'Native mysqli multiple owned named locks and bounded row probe pass');
registration_confirm(); registration_check(Policy::operational()&&Policy::native_closed(),'Explicit checked confirmation activates valid HTTPS replacement and native closure');
unset($_SERVER['HTTPS']);registration_check(!Policy::operational()&&Policy::native_closed(),'HTTP offers no built-in signup and cannot reopen an already-confirmed HTTPS native gate');$_SERVER['HTTPS']='on';
update_option('default_role','administrator');registration_check(!Policy::confirmed()&&!Policy::native_closed(),'Unsafe default role revokes confirmation');
update_option('default_role','subscriber');registration_check(!Policy::operational(),'Returning to previous role never silently reconfirms');registration_confirm();
get_role('subscriber')->add_cap('edit_posts');registration_check(!Policy::operational()&&!Policy::confirmed(),'Elevated Subscriber revokes confirmation');get_role('subscriber')->remove_cap('edit_posts');registration_confirm();
update_option('users_can_register',0);registration_check(!Policy::confirmed()&&!Policy::native_closed(),'WP registration flag revokes both opt-in decisions');update_option('users_can_register',1);registration_confirm();
$wpdb->query($wpdb->prepare("UPDATE $wpdb->options SET autoload='no' WHERE option_name=%s",'users_can_register'));wp_cache_delete('alloptions','options');get_option('users_can_register');
$wpdb->query($wpdb->prepare("UPDATE $wpdb->options SET option_value=%s WHERE option_name=%s",'0','users_can_register'));Policy::refresh();registration_check(!Policy::operational(),'Authoritative refresh sees concurrent policy changes even when individual non-autoloaded option was cached');
update_option('users_can_register',1);registration_confirm();

foreach(array(" Élodie O'Connor ",'李 أحمد','Nguyễn','किरण','A\\B',str_repeat('界',100),str_repeat('𐐀',100)) as $name){$v=registration_values();$v['first_name']=$name;$r=Input::validate($v);registration_check(!$r['errors']&&trim($name)===$r['values']['first_name'],'International name accepted without alphabet restriction');}
foreach(array(''," \u{2003} ","A\nB",'<b>A</b>',str_repeat('界',101),str_repeat('𐐀',101),"A\0B",array('structured')) as $name){$v=registration_values();$v['first_name']=$name;registration_check(isset(Input::validate($v)['errors']['first_name']),'Blank, control, markup, overlong or structured name rejected');}
foreach(array('tiny',' leading-password','trailing-password ',"control\tpassword",str_repeat('a',4097),"invalid-utf8-\xff",array('structured')) as $password){$v=registration_values(null,$password);registration_check(isset(Input::validate($v)['errors']['password']),'Unsafe/unsupported password rejected without transformation');}
foreach(array('native@example.invalid',str_repeat('a',64).'@'.str_repeat('b',20).'.invalid') as $email){$v=registration_values($email);registration_check(!Input::validate($v)['errors'],'Native valid email fits users-table width');}
foreach(array('no-at-sign','bad+<tag>@example.invalid',str_repeat('a',90).'@example.invalid',array('structured')) as $email){$v=registration_values();$v['email']=$email;registration_check(isset(Input::validate($v)['errors']['email']),'Invalid/overlong/structured email rejected');}

$native_calls=array();$listener=static function()use(&$native_calls){$native_calls[]=current_filter();};
foreach(array('user_registration_email','register_post','registration_errors','user_register') as $hook)add_filter($hook,static function($value=null)use($listener){$listener();return $value;},10,1);
wp_set_current_user(0);
foreach(array("Native-'\\-🔒-correct-password",str_repeat('a',4096),str_repeat('界',1365).'x',str_repeat('𐐀',1024)) as $password){
 $v=registration_values(null,$password);$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");$id=registration_create($v);
 registration_check(is_int($id)&&$id>1,'Native account inserted for exact supported password byte boundary');
 $u=get_userdata($id);registration_check($u->user_email===$v['email']&&get_user_meta($id,'first_name',true)===$v['first_name']&&get_user_meta($id,'last_name',true)===$v['last_name'],'Native names, accents, apostrophes and email preserved exactly');
 registration_check(array('subscriber')===$u->roles&&$u->has_cap('read')&&!$u->has_cap('edit_posts'),'New identity is explicitly safe Subscriber');
 registration_check(wp_check_password($password,$u->user_pass,$id)&&!wp_check_password($password.'x',$u->user_pass,$id),'Native hash preserves entire password bytes without truncation');
 $signed=wp_signon(array('user_login'=>$v['email'],'user_password'=>$password,'remember'=>false));registration_check($signed instanceof WP_User&&$signed->ID===$id,'Native email wp_signon accepts identical credential');
 registration_check(!is_user_logged_in(),'wp_signon does not fabricate a current-request identity');
 $reset=get_password_reset_key($u);registration_check(is_string($reset)&&check_password_reset_key($reset,$u->user_login) instanceof WP_User,'Native reset key lifecycle works');
 reset_password($u,'Synthetic-reset-password-31!');registration_check(wp_authenticate($v['email'],'Synthetic-reset-password-31!') instanceof WP_User,'Native reset and subsequent email authentication work');
 registration_check($before+1===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Exactly one account created');
}
registration_check(count(array_filter($native_calls,static fn($v)=>'user_register'===$v))===4,'Native creation lifecycle fires once per account');
registration_check(!has_filter('send_auth_cookies')&&!is_user_logged_in(),'No direct auth or bypass filter installed');
foreach(array('user_registration_email','register_post','registration_errors','user_register') as $hook)remove_all_filters($hook,10);

$v=registration_values();$token=bin2hex(random_bytes(16));$browser=bin2hex(random_bytes(32));
$first=registration_create($v,null,$token,$browser);$again=registration_create($v,null,$token,$browser);
registration_check(is_int($first)&&is_wp_error($again)&&'registration_replay'===$again->get_error_code(),'Same browser/token is atomically one use');
$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");$duplicate=registration_create($v);registration_check(is_wp_error($duplicate)&&'registration_existing'===$duplicate->get_error_code()&&$before===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Existing email never updates/recreates an account');
$denier=static function($e){$e->add('provider_private','PRIVATE PROVIDER DEBUG');return $e;};add_filter('registration_errors',$denier);
$denied=registration_create(registration_values());registration_check(is_wp_error($denied)&&!str_contains($denied->get_error_message(),'PRIVATE'),'Native registration policy denial remains authoritative and safe');remove_filter('registration_errors',$denier);
$email_filter=static fn()=> 'native-filtered-'.bin2hex(random_bytes(6)).'@example.invalid';add_filter('user_registration_email',$email_filter);$filtered=registration_create(registration_values());registration_check(is_int($filtered)&&str_starts_with(get_userdata($filtered)->user_email,'native-filtered-'),'Native filtered final email is revalidated and used');remove_filter('user_registration_email',$email_filter);
$bad_email=static fn()=> array('invalid');add_filter('user_registration_email',$bad_email);registration_check(is_wp_error(registration_create(registration_values())),'Malformed filtered identity fails safely');remove_filter('user_registration_email',$bad_email);
$change=static function($data){$data['user_email']='unexpected@example.invalid';return $data;};add_filter('wp_pre_insert_user_data',$change,20);$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");registration_check(is_wp_error(registration_create(registration_values()))&&$before===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Permanent final-data guard blocks altered identity before insert');remove_filter('wp_pre_insert_user_data',$change,20);
$elevate=static function($id){$u=new WP_User($id);$u->add_cap('edit_posts');};add_action('user_register',$elevate);$v=registration_values();$denied=registration_create($v);registration_check(is_wp_error($denied)&&email_exists($v['email'])&&!is_user_logged_in(),'Unexpected post-insert privilege retains account but denies authentication');remove_action('user_register',$elevate);
registration_check(is_wp_error(registration_create(registration_values(),static fn()=>false)),'Final eligibility denial prevents creation');
$before=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");$bad=registration_create(registration_values(),null,null,null,'192.0.2.2',array('first_name'=>'Safe error'));registration_check(is_wp_error($bad)&&$before===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Input failures reserve budget without creating account');

registration_reset_state();$browser=bin2hex(random_bytes(32));
for($i=0;$i<7;$i++){$r=registration_create(registration_values(),null,null,$browser,'192.0.2.3',array('first_name'=>'Safe error'));registration_check(is_wp_error($r)&&($i<6?'registration_fields':'registration_rate')===$r->get_error_code(),'Production browser submission budget '.$i);}
registration_check(Policy::native_closed(),'Rate rejection never reopens native signup');
foreach(array(array('peer',40),array('email',3),array('site',100)) as [$dimension,$limit]){
 registration_reset_state();
 for($i=0;$i<=$limit;$i++){
  $result=(new StateStore())->run(static fn($store,$state)=>Budget::reserve($store,$state,'token-'.$i,'browser-'.$i,'email'===$dimension?'same@example.invalid':'email-'.$i.'@example.invalid','peer'===$dimension?'192.0.2.4':'192.0.2.'.($i+5)));
  registration_check($i<$limit?true===$result:is_wp_error($result)&&'registration_rate'===$result->get_error_code(),'Production '.$dimension.' budget '.$i);
 }
}
$raw=$wpdb->get_row($wpdb->prepare("SELECT option_value,autoload FROM $wpdb->options WHERE option_name=%s",StateStore::OPTION),ARRAY_A);
registration_check(in_array($raw['autoload'],array('no','off','auto-off'),true)&&!str_contains($raw['option_value'],'example.invalid')&&!str_contains($raw['option_value'],'192.0.2')&&!str_contains($raw['option_value'],$browser),'One non-autoloaded row stores only HMAC keys/counters/expiry');
$rows=json_decode($raw['option_value'],true);registration_check(count($rows['entries'])<=StateStore::MAX_ENTRIES&&strlen($raw['option_value'])<=StateStore::MAX_BYTES,'Physical row is bounded');
$full=array('version'=>1,'entries'=>array());for($i=0;$i<StateStore::MAX_ENTRIES;$i++)$full['entries'][hash('sha256','capacity'.$i)]=array('expires'=>time()+300,'count'=>1);
update_option(StateStore::OPTION,wp_json_encode($full),false);$r=registration_create(registration_values());registration_check(is_wp_error($r)&&'registration_store'===$r->get_error_code()&&Policy::native_closed(),'Fixed production capacity fails closed without native bypass');
foreach($full['entries'] as &$entry)$entry['expires']=time()-1;unset($entry);update_option(StateStore::OPTION,wp_json_encode($full),false);Maintenance::purge();registration_check(array()===json_decode(get_option(StateStore::OPTION),true)['entries'],'Periodic purge expires full capacity with bounded work');
update_option(StateStore::OPTION,'corrupt',false);registration_check(!Policy::operational()&&!Policy::confirmed()&&!Policy::native_closed(),'Corrupt structural storage revokes confirmation and returns native policy');delete_option(StateStore::OPTION);registration_check(!Policy::operational(),'Repair alone never silently reconfirms structural failure');registration_confirm();
delete_option(StateStore::OPTION);registration_check(!Policy::operational()&&!Policy::confirmed()&&!Policy::native_closed(),'Missing live replay row revokes operation instead of silently resetting one-use state');registration_confirm();registration_check(Policy::operational()&&is_string(get_option(StateStore::OPTION)),'New confirmation proves initial state persistence before becoming operational');
$r=(new StateStore())->run(static function($store,$state){global $wpdb;$store->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name=%s",StateStore::OPTION));$store->write($state);return true;});registration_check(is_wp_error($r),'Authoritative read-back detects a disappeared state row instead of accepting a no-op UPDATE');Policy::operational();registration_confirm();
$retries=$wpdb->reconnect_retries;$r=(new StateStore())->run(static function($store){global $wpdb;registration_check(0===$wpdb->reconnect_retries,'Native auto-reconnect disabled only inside critical section');throw new RuntimeException('synthetic');});registration_check(is_wp_error($r)&&$retries===$wpdb->reconnect_retries,'Exception restores wpdb retry policy');
registration_check(null===$wpdb->get_var($wpdb->prepare('SELECT IS_USED_LOCK(%s)',StateStore::lock_name())),'Exception releases mutex');
$r=(new StateStore())->run(static function($store){global $wpdb;$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',StateStore::lock_name()));$store->assert_owned();return true;});registration_check(is_wp_error($r),'Lost named-lock ownership fails closed');

$_COOKIE=array();$token=BrowserState::token(123);registration_check(is_string(BrowserState::verify(123,$token)),'Browser/action/Sprint-bound token validates');
registration_check(false===BrowserState::verify(124,$token)&&false===BrowserState::verify(123,$token.'x'),'Cross-Sprint and tampered token rejected');
foreach(array('HTTP_ORIGIN'=>'https://evil.example.invalid','HTTP_REFERER'=>'https://evil.example.invalid/path','HTTP_SEC_FETCH_SITE'=>'same-site') as $header=>$value){$_SERVER[$header]=$value;registration_check(false===BrowserState::verify(123,$token),'Incompatible supplied provenance rejected: '.$header);unset($_SERVER[$header]);}
$_SERVER['HTTP_ORIGIN']='null';registration_check(is_string(BrowserState::verify(123,$token))&&false===BrowserState::verify(123,''),'Null provenance still requires valid token');unset($_SERVER['HTTP_ORIGIN']);
$saved=$_COOKIE;$_COOKIE=array();registration_check(false===BrowserState::verify(123,$token),'Missing browser cookie rejects token');$_COOKIE=$saved;
$old=(time()-1201).'.'.bin2hex(random_bytes(16));$expired=$old.'.'.hash_hmac('sha256','registration-v1|register|123|'.$_COOKIE[BrowserState::COOKIE].'|'.$old.'|'.Accounts::get()['confirmation'],wp_salt('nonce'));
registration_check(false===BrowserState::verify(123,$expired),'Correctly signed expired form token is rejected');
$wrong=time().'.'.bin2hex(random_bytes(16));$wrong_action=$wrong.'.'.hash_hmac('sha256','registration-v1|different-action|123|'.$_COOKIE[BrowserState::COOKIE].'|'.$wrong.'|'.Accounts::get()['confirmation'],wp_salt('nonce'));
registration_check(false===BrowserState::verify(123,$wrong_action),'Correctly signed different action is rejected');
$old_browser=(time()-1801).'.'.bin2hex(random_bytes(32));$_COOKIE[BrowserState::COOKIE]=$old_browser.'.'.hash_hmac('sha256','registration-v1|browser|'.$old_browser,wp_salt('nonce'));
registration_check(false===BrowserState::verify(123,$token),'Correctly signed expired browser state is rejected');$_COOKIE=$saved;
BrowserState::receipt(123,$first);registration_check('pending'===BrowserState::result(123)&&'none'===BrowserState::result(124),'Receipt grants no authentication and is scoped to original Sprint');
wp_set_current_user($first);registration_check('matched'===BrowserState::result(123)&&empty($_COOKIE[BrowserState::COOKIE]),'Expected fresh identity clears opaque receipt/browser state');wp_set_current_user(0);
BrowserState::receipt(123,$first);wp_set_current_user(1);registration_check('mismatch'===BrowserState::result(123),'Different fresh identity cannot satisfy authentication receipt');
$lifecycle_token=BrowserState::token(123);$generation=Accounts::get()['confirmation'];
$member_before=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");Maintenance::schedule();registration_check((bool)wp_next_scheduled(Maintenance::HOOK),'Confirmed setup schedules bounded periodic purge');Maintenance::deactivate();registration_check(false===get_option(StateStore::OPTION,false)&&!wp_next_scheduled(Maintenance::HOOK)&&$member_before===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Deactivation clears temporary state/cron only, retaining accounts');
registration_confirm();registration_check($generation!==Accounts::get()['confirmation']&&false===BrowserState::verify(123,$lifecycle_token)&&is_string(BrowserState::verify(123,BrowserState::token(123))),'Reconfirmation after cleanup uses a new generation: old forms stay invalid, new forms work');Maintenance::deactivate();
echo "Participant registration: $checks assertions passed; WordPress $wp_version / PHP ".PHP_VERSION." / ".$wpdb->db_version().".\n";
ob_end_flush();
