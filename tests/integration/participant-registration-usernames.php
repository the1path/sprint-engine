<?php
/** SE-031.3.1: real native users, collation, policy and bounded login selection. */
use ThePath\SprintEngine\Registration\AccountService;
use ThePath\SprintEngine\Registration\Maintenance;
use ThePath\SprintEngine\Registration\Policy;
use ThePath\SprintEngine\Registration\StateStore;
use ThePath\SprintEngine\Registration\Username;
use ThePath\SprintEngine\Settings\ParticipantAccounts as Accounts;
if('yes'!==getenv('SE_TEST_DISPOSABLE')||!getenv('SE_TEST_WP_ROOT'))exit(2);
ob_start();$_SERVER['HTTPS']='on';$_SERVER['REQUEST_METHOD']='GET';require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if(!defined('SE0313_DISPOSABLE')||!is_file(ABSPATH.'SE0313-DISPOSABLE'))exit(3);
require_once ABSPATH.'wp-admin/includes/user.php';require_once ABSPATH.'wp-admin/includes/template.php';
set_error_handler(static function($s,$m,$f,$l){if(error_reporting()&$s)throw new ErrorException($m,0,$s,$f,$l);return false;});
global $wpdb;$checks=0;$created=array();$password='Synthetic-name-password-32!';
$old_ids=array_map('intval',$wpdb->get_col("SELECT ID FROM $wpdb->users ORDER BY ID"));$old_list=implode(',',$old_ids);
$snapshot=static fn()=>array($wpdb->get_results("SELECT * FROM $wpdb->users WHERE ID IN ($old_list) ORDER BY ID",ARRAY_A),$wpdb->get_results("SELECT * FROM $wpdb->usermeta WHERE user_id IN ($old_list) ORDER BY umeta_id",ARRAY_A));$before=$snapshot();
function name_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);++$checks;echo "PASS: $label\n";}
function name_reset(){Policy::refresh();Maintenance::deactivate();wp_set_current_user(1);update_option('users_can_register',1);update_option('default_role','subscriber');update_option(Accounts::OPTION,Accounts::sanitize(array('mode'=>'built_in','registration_url'=>'','login_url'=>'','disable_native'=>'1','confirmation'=>'1')),false);wp_set_current_user(0);}
function name_choose($first,$last){return (new StateStore())->run(static fn($store)=>Username::generate($store,$first,$last));}
function name_seed($login){global $created,$password;$id=wp_insert_user(array('user_login'=>$login,'user_email'=>'ordinary-'.bin2hex(random_bytes(8)).'@example.invalid','user_pass'=>$password,'role'=>'subscriber'));if(is_wp_error($id))throw new RuntimeException('Native username fixture refused');$created[]=$id;return $id;}
function name_create($first,$last,$token=null,$browser=null){global $created,$password;$values=array('first_name'=>$first,'last_name'=>$last,'email'=>'name-'.bin2hex(random_bytes(8)).'@example.invalid','password'=>$password);$id=(new AccountService())->create($values,$token??bin2hex(random_bytes(16)),$browser??bin2hex(random_bytes(32)),'192.0.2.32',array(),static fn()=>Policy::operational());if(is_int($id))$created[]=$id;return array($id,$values);}
try {
 name_reset();
 $mark=bin2hex(random_bytes(5));
 foreach(array(array('Jane','Smith'.$mark,'jane.smith'.$mark),array('Élodie'.$mark,"O'Connor",'elodie'.$mark.'.oconnor'),array('María-José'.$mark,'D’Ávila','maria.jose'.$mark.'.davila'),array('李','Jones'.$mark,'jones'.$mark),array('Jane'.$mark,'李','jane'.$mark),array(' .Jane-- ','_Smith'.$mark.'.','jane.smith'.$mark)) as [$first,$last,$expected])name_check($expected===name_choose($first,$last),'Deterministic native name transliteration/punctuation and usable components');
 $locale_filter=static fn()=>'de_DE';add_filter('locale',$locale_filter);try{name_check('anne'.$mark.'.muller'===name_choose('Änne'.$mark,'Müller'),'Transliteration is independent of active site locale');}finally{remove_filter('locale',$locale_filter);}
 foreach(array(array('李','أحمد'),array('---','...'),array('','')) as [$first,$last]){$login=name_choose($first,$last);name_check(is_string($login)&&1===preg_match('/^participant_[a-f0-9]{12}$/D',$login)&&validate_username($login),'Unusable name components use bounded compact random login, never email');}
 [$unicode_id,$unicode_values]=name_create('李','أحمد');name_check(is_int($unicode_id)&&1===preg_match('/^participant_[a-f0-9]{12}$/D',get_userdata($unicode_id)->user_login)&&get_user_meta($unicode_id,'first_name',true)==='李'&&get_user_meta($unicode_id,'last_name',true)==='أحمد','All-non-Latin native account keeps exact profile with compact random login');name_check(wp_authenticate($unicode_values['email'],$password) instanceof WP_User,'Random-fallback native account signs in normally by email');
 $prefix='collision'.bin2hex(random_bytes(5));$base=$prefix.'.smith';$ordinary=name_seed(strtoupper($base));$ordinary_before=get_userdata($ordinary)->to_array();
 wp_cache_set(strtolower($base),999999,'userlogins');
 name_check($base.'.2'===name_choose($prefix,'Smith'),'Fresh actual collation check sees uppercase native user despite misleading user cache');wp_cache_delete(strtolower($base),'userlogins');
 foreach(array('.2','.3') as $suffix){[$id,$values]=name_create($prefix,'Smith');name_check(is_int($id)&&get_userdata($id)->user_login===$base.$suffix,'Distinct email with same names receives expected readable suffix '.$suffix);name_check(get_user_meta($id,'first_name',true)===$values['first_name']&&get_user_meta($id,'last_name',true)===$values['last_name']&&get_userdata($id)->display_name===$values['first_name'].' '.$values['last_name'],'Native profile values remain exact');name_check(wp_authenticate($values['email'],$password) instanceof WP_User,'Email remains native sign-in identifier for readable username');$key=get_password_reset_key(get_userdata($id));name_check(is_string($key)&&check_password_reset_key($key,get_userdata($id)->user_login) instanceof WP_User,'Native recovery works for readable username');}
 name_check($ordinary_before===get_userdata($ordinary)->to_array(),'Existing ordinary native identity is not renamed/updated');
 $accent='accent'.bin2hex(random_bytes(5));$force=static fn()=>$accent.'.smíth';add_filter('pre_user_login',$force);try{$seed=name_seed('ordinary-accent');}finally{remove_filter('pre_user_login',$force);}
 $collides=(int)$wpdb->get_var($wpdb->prepare("SELECT ID FROM $wpdb->users WHERE user_login=%s",$accent.'.smith'))===$seed;
 name_check(name_choose($accent,'Smith')===$accent.'.smith'.($collides?'.2':''),'Diacritic collision follows actual users-column collation');
 $long='long'.bin2hex(random_bytes(5)).str_repeat('a',86);$long_base=substr($long,0,60);name_seed($long_base);
 $long_login=name_choose($long,'Unchanged');name_check(60===strlen($long_login)&&substr($long_base,0,58).'.2'===$long_login,'Suffix truncation keeps login within native 60-character boundary');
 for($i=2;$i<=Username::NAME_ATTEMPTS;$i++){$suffix='.'.$i;name_seed(substr($long_base,0,60-strlen($suffix)).$suffix);}
 $fallback=name_choose($long,'Unchanged');name_check(is_string($fallback)&&1===preg_match('/^participant_[a-f0-9]{12}$/D',$fallback),'Twenty occupied readable candidates fall back to cryptographic random suffix');
 $random_collisions=0;$occupy=static function($valid,$candidate)use(&$random_collisions){if(preg_match('/^participant_[a-f0-9]{12}$/D',$candidate)){++$random_collisions;name_seed($candidate);}return $valid;};add_filter('validate_username',$occupy,10,2);
 try{$failure=name_choose($long,'Unchanged');}finally{remove_filter('validate_username',$occupy,10);}
 name_check(is_wp_error($failure)&&Username::RANDOM_ATTEMPTS===$random_collisions,'Actual native collisions on each random candidate stop after three attempts');
 name_reset();$calls=0;$deny=static function()use(&$calls){++$calls;return false;};add_filter('validate_username',$deny);$token=bin2hex(random_bytes(16));$browser=bin2hex(random_bytes(32));$users=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");
 try{[$failure]=name_create('Policy','Denied',$token,$browser);}finally{remove_filter('validate_username',$deny);}
 name_check(is_wp_error($failure)&&1===$calls&&$users===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Native validation denial stops once, without suffix/fallback or insertion');
 [$replay]=name_create('Policy','Denied',$token,$browser);name_check(is_wp_error($replay)&&'registration_replay'===$replay->get_error_code(),'Username policy denial retains committed replay reservation');
 $reserved=static fn()=>array('RESERVED.POLICY');add_filter('illegal_user_logins',$reserved);try{[$failure]=name_create('Reserved','Policy');}finally{remove_filter('illegal_user_logins',$reserved);}
 name_check(is_wp_error($failure)&&'registration_username'===$failure->get_error_code(),'Native reserved logins are rejected case-insensitively without trying alternatives');
 $mutate=static fn()=>'unexpected.native.login';add_filter('pre_user_login',$mutate);try{[$failure]=name_create('Expected','Identity');}finally{remove_filter('pre_user_login',$mutate);}
 name_check(is_wp_error($failure)&&!username_exists('unexpected.native.login'),'Unexpected native login rewrite is rejected before insertion');
 $hooks=0;$reject=static function($errors)use(&$hooks){++$hooks;$errors->add('native-policy','PRIVATE POLICY');return $errors;};add_filter('registration_errors',$reject);try{[$failure]=name_create('Hook','Denied');}finally{remove_filter('registration_errors',$reject);}
 name_check(is_wp_error($failure)&&1===$hooks&&!str_contains($failure->get_error_message(),'PRIVATE'),'Native registration policy fires once and is never retried/bypassed');
 // A native filter can race an uncoordinated creator after WP's early identity check.
 $late=static function($data)use(&$created,$wpdb){$other=$data;$other['user_email']='late-'.bin2hex(random_bytes(8)).'@example.invalid';if(false===$wpdb->insert($wpdb->users,$other))throw new RuntimeException('Late collision fixture failed');$created[]=(int)$wpdb->insert_id;return $data;};add_filter('wp_pre_insert_user_data',$late,20);
 try{[$failure,$values]=name_create('Late','Collision');}finally{remove_filter('wp_pre_insert_user_data',$late,20);}
 name_check(is_wp_error($failure)&&!email_exists($values['email']),'Final authoritative identity guard rejects collision introduced by late native callback');
 name_check($before===$snapshot(),'All pre-existing users and native profile metadata remain byte-for-byte unchanged');
}finally{wp_set_current_user(1);foreach(array_reverse($created) as $id)wp_delete_user($id);Maintenance::deactivate();}
echo "Registration usernames: $checks assertions passed; WordPress $wp_version / PHP ".PHP_VERSION.' / '.$wpdb->dbh->server_info.".\n";ob_end_flush();
