<?php
/** Real MySQL/MariaDB transaction and health-to-mutex replay-state regressions. */
use ThePath\SprintEngine\Registration\AccountService;
use ThePath\SprintEngine\Registration\BrowserState;
use ThePath\SprintEngine\Registration\Maintenance;
use ThePath\SprintEngine\Registration\Policy;
use ThePath\SprintEngine\Registration\StateStore;
use ThePath\SprintEngine\Settings\ParticipantAccounts as Accounts;

if ('yes'!==getenv('SE_TEST_DISPOSABLE')||!getenv('SE_TEST_WP_ROOT')) exit(2);
ob_start(); $_SERVER['HTTPS']='on'; $_SERVER['REQUEST_METHOD']='GET';
require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if(!defined('SE0313_DISPOSABLE')||!is_file(ABSPATH.'SE0313-DISPOSABLE'))exit(3);
require_once ABSPATH.'wp-admin/includes/template.php';
global $wpdb;

// An independent native connection holds the real mutex until the parent has
// completed health() and is observably waiting in GET_LOCK, then deletes state.
if ('--delete-worker'===($argv[1]??'')) {
 $ready=$argv[2];$parent=(int)$argv[3];$lock=StateStore::lock_name();
 if(1!==(int)$wpdb->get_var($wpdb->prepare('SELECT GET_LOCK(%s,0)',$lock)))exit(4);
 file_put_contents($ready,'held');$deleted=false;$deadline=microtime(true)+8;
 try {
  do {
   $sql=$wpdb->get_var($wpdb->prepare('SELECT INFO FROM information_schema.PROCESSLIST WHERE ID=%d',$parent));
   if(is_string($sql)&&str_contains($sql,'GET_LOCK(')&&str_contains($sql,$lock)) {
    $deleted=1===$wpdb->query($wpdb->prepare("DELETE FROM $wpdb->options WHERE option_name=%s",StateStore::OPTION));break;
   }
   usleep(2000);
  }while(microtime(true)<$deadline);
 }finally{$wpdb->get_var($wpdb->prepare('SELECT RELEASE_LOCK(%s)',$lock));}
 ob_end_clean();echo json_encode(array('deleted_while_waiting'=>$deleted));exit($deleted?0:5);
}

set_error_handler(static function($severity,$message,$file,$line){if(error_reporting()&$severity)throw new ErrorException($message,0,$severity,$file,$line);return false;});
$checks=0;
function database_check($ok,$label){global $checks;if(!$ok)throw new RuntimeException($label);++$checks;echo "PASS: $label\n";}
function database_confirm(){wp_set_current_user(1);update_option('users_can_register',1);update_option('default_role','subscriber');update_option(Accounts::OPTION,Accounts::sanitize(array('mode'=>'built_in','registration_url'=>'','login_url'=>'','disable_native'=>'1','confirmation'=>'1')),false);wp_set_current_user(0);}
function database_reset(){Policy::refresh();Maintenance::deactivate();database_confirm();}
function database_values(){return array('first_name'=>'Database','last_name'=>'Synthetic','email'=>'db-'.bin2hex(random_bytes(8)).'@example.invalid','password'=>'Synthetic-database-password-31!');}
function database_create($token,$browser,$errors=array()){return (new AccountService())->create(database_values(),$token,$browser,'192.0.2.31',$errors,static fn()=>Policy::operational());}
function database_delete_while_waiting($operation){
 global $wpdb;
 $ready=tempnam(sys_get_temp_dir(),'se0313-db-');unlink($ready);
 $args=array(PHP_BINARY,'-c',php_ini_loaded_file(),__FILE__,'--delete-worker',$ready,(string)$wpdb->dbh->thread_id);
 $process=proc_open($args,array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,null,array('bypass_shell'=>true));
 if(!is_resource($process))throw new RuntimeException('Independent database worker unavailable');
 fclose($pipes[0]);$deadline=microtime(true)+8;
 while(!is_file($ready)&&microtime(true)<$deadline)usleep(5000);
 try {
  if(!is_file($ready))throw new RuntimeException('Worker failed to acquire registration mutex');
  $result=$operation();$output=stream_get_contents($pipes[1]);$errors=stream_get_contents($pipes[2]);
 }finally{fclose($pipes[1]);fclose($pipes[2]);$exit=proc_close($process);if(is_file($ready))unlink($ready);}
 database_check(0===$exit&&''===$errors&&true===(json_decode($output,true)['deleted_while_waiting']??false),'Independent worker deleted state only after health and while parent waited for mutex');
 return $result;
}

database_reset();$db=$wpdb->dbh;$driver=new mysqli_driver();$report=$driver->report_mode;
$table=$wpdb->prefix.'registration_transaction_probe';
if(!preg_match('/^[A-Za-z0-9_]+$/D',$table))throw new RuntimeException('Unsafe fixture identifier');
$wpdb->query("CREATE TABLE `$table` (id INT PRIMARY KEY) ENGINE=InnoDB");
$observer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);$retries=$wpdb->reconnect_retries;
try {
 foreach(array(MYSQLI_REPORT_OFF,MYSQLI_REPORT_ERROR,MYSQLI_REPORT_ERROR|MYSQLI_REPORT_STRICT) as $mode){
  mysqli_report($mode);
  database_check('ready'===(new StateStore())->health()&&$mode===$driver->report_mode,'Autocommit probe succeeds and preserves native mysqli error-report mode '.$mode);
  foreach(array('START TRANSACTION','START TRANSACTION READ ONLY','START TRANSACTION WITH CONSISTENT SNAPSHOT') as $start){
   mysqli_query($db,$start);mysqli_query($db,'SAVEPOINT caller_marker');
   try {
    database_check('unsupported'===(new StateStore())->health(),'Reject empty/read-only/snapshot transaction before any table work: '.$start);
    database_check(true===mysqli_query($db,'RELEASE SAVEPOINT caller_marker'),'Health preserves caller transaction and savepoints');
   }finally{mysqli_rollback($db);}
  }
 }
 mysqli_report($report);
 mysqli_query($db,'SET autocommit=0');
 try{database_check('unsupported'===(new StateStore())->health()&&'0'===$wpdb->get_var('SELECT @@autocommit'),'Autocommit-off is rejected without changing caller session');}
 finally{mysqli_rollback($db);mysqli_autocommit($db,true);}
 mysqli_begin_transaction($db);mysqli_query($db,"INSERT INTO `$table` VALUES (1)");
 try {
  database_check('unsupported'===(new StateStore())->health(),'Reject active write transaction');
  database_check(1===(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")&&0===(int)$observer->get_var("SELECT COUNT(*) FROM `$table`"),'Probe neither rolls back nor commits caller write');
 }finally{mysqli_rollback($db);}

 $before=$observer->get_var($observer->prepare("SELECT option_value FROM $wpdb->options WHERE option_name=%s",StateStore::OPTION));
 $result=(new StateStore())->run(static function($store,$state)use($db){mysqli_begin_transaction($db);mysqli_query($db,'SAVEPOINT caller_marker');$state['entries'][str_repeat('a',64)]=array('expires'=>time()+300,'count'=>1);$store->write($state);return true;});
 try {
  database_check(is_wp_error($result),'Transaction introduced inside mutex prevents state write');
  database_check($before===$observer->get_var($observer->prepare("SELECT option_value FROM $wpdb->options WHERE option_name=%s",StateStore::OPTION)),'Rejected write leaves committed replay state unchanged');
  database_check(true===mysqli_query($db,'RELEASE SAVEPOINT caller_marker'),'Owned guard leaves callback transaction open for caller cleanup');
  database_check(null===$observer->get_var($observer->prepare('SELECT IS_USED_LOCK(%s)',StateStore::lock_name()))&&$retries===$wpdb->reconnect_retries,'Failure releases captured connection mutex and restores retry policy');
 }finally{mysqli_rollback($db);}

 foreach(array('registration_errors','wp_pre_insert_user_data') as $hook){
  database_reset();$token=bin2hex(random_bytes(16));$browser=bin2hex(random_bytes(32));$users=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");
  $begin=static function($value)use($db,$table){mysqli_begin_transaction($db);mysqli_query($db,"INSERT INTO `$table` VALUES (1)");return $value;};
  add_filter($hook,$begin,5);
  try{$result=database_create($token,$browser);}finally{remove_filter($hook,$begin,5);}
  try {
   database_check(is_wp_error($result)&&$users===(int)$observer->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Native callback transaction cannot insert/authenticate account: '.$hook);
   database_check(1===(int)$wpdb->get_var("SELECT COUNT(*) FROM `$table`")&&0===(int)$observer->get_var("SELECT COUNT(*) FROM `$table`"),'Registration never commits/rolls back callback work');
  }finally{mysqli_rollback($db);}
  $retry=database_create($token,$browser);
  database_check(is_wp_error($retry)&&'registration_replay'===$retry->get_error_code(),'Committed reservation survives callback rollback; replay remains consumed');
 }

 foreach(array(false,true) as $initializing){
  database_reset();$_COOKIE=array();$token=BrowserState::token(123);$browser=BrowserState::verify(123,$token);$generation=Accounts::get()['confirmation'];
  $used=database_create($token,$browser,array('first_name'=>'Synthetic invalid input'));
  database_check(is_wp_error($used)&&'registration_fields'===$used->get_error_code(),'Consume signed form before live-state deletion');
  $users=(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users");
  $result=database_delete_while_waiting(static fn()=> $initializing?(new StateStore())->initialize():database_create($token,$browser));
  database_check($initializing?false===$result:is_wp_error($result),'Missing state after mutex wait fails closed for '.($initializing?'setup':'registration'));
  Policy::refresh();
  database_check(null===$wpdb->get_var($wpdb->prepare("SELECT option_value FROM $wpdb->options WHERE option_name=%s",StateStore::OPTION))&&!Policy::confirmed(),'Missing live row stays missing and revokes confirmation without empty-store recreation');
  database_check($users===(int)$wpdb->get_var("SELECT COUNT(*) FROM $wpdb->users"),'Consumed form cannot create another account across deletion race');
  database_confirm();
  database_check($generation!==Accounts::get()['confirmation']&&false===BrowserState::verify(123,$token),'Explicit recovery creates a fresh generation that rejects the old consumed form');
 }
 database_reset();Maintenance::deactivate();
 $called=false;$result=(new StateStore())->run(static function()use(&$called){$called=true;return true;});
 database_check(is_wp_error($result)&&!$called&&false===get_option(StateStore::OPTION,false),'Ordinary operation cannot initialize an unconfirmed absent store');
 database_confirm();database_check(Policy::operational(),'Explicit setup remains able to initialize a fresh unconfirmed store');
}finally{
 mysqli_report($report);mysqli_rollback($db);mysqli_autocommit($db,true);$observer->close();$wpdb->query("DROP TABLE `$table`");Maintenance::deactivate();
}
echo "Registration database: $checks assertions passed; WordPress $wp_version / PHP ".PHP_VERSION.' / '.$db->server_info.".\n";
ob_end_flush();
