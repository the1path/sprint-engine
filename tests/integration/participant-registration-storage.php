<?php
/** Actual restricted-grant proof, using a throwaway options clone, never live state. */
if('yes'!==getenv('SE_TEST_DISPOSABLE')||'yes'!==getenv('SE_TEST_DB_ADMIN')||!getenv('SE_TEST_WP_ROOT'))exit(2);
$_SERVER['HTTPS']='on';require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if(!defined('SE0313_DISPOSABLE')||!is_file(ABSPATH.'SE0313-DISPOSABLE'))exit(3);
require_once ABSPATH.'wp-admin/includes/template.php';
use ThePath\SprintEngine\Settings\ParticipantAccounts as Accounts;
use ThePath\SprintEngine\Registration\Policy;
use ThePath\SprintEngine\Registration\StateStore;
wp_set_current_user(1);global $wpdb;$original=$wpdb;$previous=$original->suppress_errors(true);$limited=null;$user='signup_probe_'.bin2hex(random_bytes(5));$password=bin2hex(random_bytes(24));$table=$original->prefix.'signup_probe_'.bin2hex(random_bytes(5));$created=false;$cloned=false;$checks=0;
if(!preg_match('/^[A-Za-z0-9_]+$/D',DB_NAME)||!preg_match('/^[A-Za-z0-9_]{1,64}$/D',$table)||!preg_match('/^[A-Za-z0-9_]+$/D',$original->options))exit(4);
function storage_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);++$checks;echo "PASS: $label\n";}
try{
 $created=false!==$original->query($original->prepare('CREATE USER %s@%s IDENTIFIED BY %s',$user,'127.0.0.1',$password));
 if(!$created)throw new RuntimeException('Disposable database-administrator grants required; no security check was skipped silently.');
 $cloned=false!==$original->query("CREATE TABLE `$table` LIKE `$original->options`");if(!$cloned)throw new RuntimeException('Disposable options clone failed.');
 if(false===$original->query($original->prepare("INSERT INTO `$table` SELECT * FROM `$original->options` WHERE option_name<>%s",StateStore::OPTION)))throw new RuntimeException('Options fixture copy failed.');
 $config=Accounts::defaults();$config['mode']='built_in';$config['disable_native']=true;
 foreach(array(Accounts::OPTION=>maybe_serialize($config),'users_can_register'=>'1','default_role'=>'subscriber') as $key=>$value)$original->query($original->prepare("REPLACE INTO `$table` (option_name,option_value,autoload) VALUES (%s,%s,'no')",$key,$value));
 if(false===$original->query($original->prepare('GRANT SELECT, UPDATE ON `'.DB_NAME.'`.`'.$table.'` TO %s@%s',$user,'127.0.0.1')))throw new RuntimeException('Restricted disposable grant failed.');
 $limited=new wpdb($user,$password,DB_NAME,DB_HOST);$limited->set_prefix($original->prefix);$limited->options=$table;$wpdb=$limited;Policy::refresh();wp_cache_delete('notoptions','options');
 storage_check('ready'===(new StateStore())->health(),'Read/named-lock readiness succeeds with SELECT/UPDATE but no INSERT grant');
 $candidate=Accounts::sanitize(array_merge($config,array('confirmation'=>'1')));
 storage_check(''===$candidate['confirmation']&&!Policy::confirmed(),'Initial persistence denial cannot enable or confirm registration');
 storage_check(in_array('sprint_engine_confirmation_store',array_column(get_settings_errors(Accounts::OPTION),'code'),true),'Actual write failure produces actionable setup guidance');
 storage_check(0===(int)$original->get_var($original->prepare("SELECT COUNT(*) FROM `$table` WHERE option_name=%s",StateStore::OPTION)),'Failed preparation creates no temporary state or account');
}finally{
 $wpdb=$original;if($limited)$limited->close();Policy::refresh();wp_cache_delete('notoptions','options');
 if($cloned)$original->query("DROP TABLE `$table`");if($created)$original->query($original->prepare('DROP USER %s@%s',$user,'127.0.0.1'));$original->suppress_errors($previous);
}
echo "Restricted-grant storage: $checks assertions passed.\n";
