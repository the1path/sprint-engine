<?php
/** Disposable test hooks only. Never package or install on an ordinary site. */
if (!defined('SE0313_DISPOSABLE') || !SE0313_DISPOSABLE || !is_file(ABSPATH.'SE0313-DISPOSABLE')) return;
$se_test_behavior=get_option('se0313_behavior','');
if ('reject'===$se_test_behavior) add_filter('authenticate',static fn()=>new WP_Error('synthetic','PRIVATE AUTH DEBUG'),PHP_INT_MAX);
if ('suppress'===$se_test_behavior) add_filter('send_auth_cookies','__return_false',PHP_INT_MAX);
if ('deny-registration'===$se_test_behavior) add_filter('registration_errors',static function($errors){$errors->add('synthetic','PRIVATE APPROVAL DEBUG');return $errors;},PHP_INT_MAX);
if ('deny-access'===$se_test_behavior) add_filter('sprint_engine/user_can_access_sprint','__return_false');
if (in_array($se_test_behavior,array('unknown','restricted','gate-open','deny-access'),true)) {
 if ('unknown'!==$se_test_behavior) add_filter('sprint_engine/participant_access_kind',static fn()=> 'restricted'===$GLOBALS['se_test_behavior']?'restricted':'open');
 add_filter('sprint_engine/runner_show_public_access_gate',static function(){file_put_contents(SE0313_ROOT.'/gate-calls-'.hash('sha256',DB_NAME).'.log',"gate\n",FILE_APPEND|LOCK_EX);return true;});
 add_action('sprint_engine/runner_access_denied_actions',static function(){file_put_contents(SE0313_ROOT.'/gate-calls-'.hash('sha256',DB_NAME).'.log',"denial\n",FILE_APPEND|LOCK_EX);echo '<p>SAFE INFORMATION ACTION</p>';});
}
if ('unknown'===$se_test_behavior) add_filter('sprint_engine/user_can_access_sprint','__return_true');
if ('mfa'===$se_test_behavior) add_action('user_register',static function($id){update_user_meta($id,'_two_factor_enabled_providers',array('Two_Factor_Email'));update_user_meta($id,'_two_factor_provider','Two_Factor_Email');});
if ('kill-before'===$se_test_behavior) add_filter('registration_errors',static function($errors){global $wpdb;$killer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);if(!$killer->dbh->kill($wpdb->dbh->thread_id))throw new RuntimeException('Disposable connection kill failed');return $errors;},PHP_INT_MAX);
if ('kill-insert'===$se_test_behavior) add_filter('query',static function($sql){global $wpdb;if(preg_match('/^INSERT INTO `?'.preg_quote($wpdb->users,'/').'`? /i',$sql)){$killer=new wpdb(DB_USER,DB_PASSWORD,DB_NAME,DB_HOST);if(!$killer->dbh->kill($wpdb->dbh->thread_id))throw new RuntimeException('Disposable connection kill failed');}return $sql;},PHP_INT_MAX);
if ('throw-after'===$se_test_behavior) add_action('user_register',static function(){throw new RuntimeException('PRIVATE INTERRUPTED HOOK');});
if ('mail-fail'===$se_test_behavior) add_filter('pre_wp_mail',static fn()=>false,PHP_INT_MAX);
if ('slow'===$se_test_behavior) add_action('register_post',static function(){file_put_contents(SE0313_ROOT.'/registration-held-'.hash('sha256',DB_NAME),'held');usleep(3000000);});
if ('unpublish-step'===$se_test_behavior) add_filter('registration_errors',static function($errors){global $wpdb;$id=(int)get_option('se0313_fixtures')['open']['step'];$wpdb->update($wpdb->posts,array('post_status'=>'draft'),array('ID'=>$id));return $errors;},PHP_INT_MAX);
if ('reconfirm'===$se_test_behavior) add_filter('registration_errors',static function($errors){$config=ThePath\SprintEngine\Settings\ParticipantAccounts::get();$config['confirmation']=ThePath\SprintEngine\Registration\Policy::confirmation();update_option(ThePath\SprintEngine\Settings\ParticipantAccounts::OPTION,$config,false);return $errors;},PHP_INT_MAX);
