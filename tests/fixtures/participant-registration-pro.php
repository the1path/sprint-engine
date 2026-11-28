<?php
/** Actual unmodified Pro retention fixture; guarded disposable CLI only. */
if('yes'!==getenv('SE_TEST_DISPOSABLE')||!getenv('SE_TEST_WP_ROOT'))exit(2);
require getenv('SE_TEST_WP_ROOT').'/wp-load.php';
if(!defined('SE0313_DISPOSABLE')||!is_file(ABSPATH.'SE0313-DISPOSABLE'))exit(3);
if('snapshot'===($argv[1]??'')){
 global $wpdb;$snapshot=array();$names=$wpdb->get_col("SHOW TABLES LIKE '{$wpdb->prefix}sprint_engine%'");sort($names);
 foreach($names as $table){$rows=array_map('wp_json_encode',$wpdb->get_results("SELECT * FROM `$table`",ARRAY_A));sort($rows);$snapshot[$table]=hash('sha256',implode("\n",$rows));}
 $record=get_option('se0313_retained');$objects=$record['native_objects']??array();if(count($objects)!==3)throw new RuntimeException('Retention fixture missing');
 $snapshot['objects']=hash('sha256',serialize(array_map(static fn($id)=>array((array)get_post($id),get_post_meta($id)),$objects)));
 $meta=get_user_meta($record['member']);unset($meta['session_tokens']); // Native sign-on legitimately creates authentication sessions.
 $snapshot['member']=hash('sha256',serialize(array((array)get_userdata($record['member'])->data,$meta)));
 echo wp_json_encode($snapshot);exit;
}
if(!class_exists('ThePath\\SprintEnginePro\\Plugin'))throw new RuntimeException('Actual Pro not loaded');
wp_set_current_user(1);$manager=new ThePath\SprintEngine\Content\StructureManager();
$sprint=wp_insert_post(array('post_type'=>'sprint_engine_sprint','post_status'=>'publish','post_title'=>'Existing participant work','post_name'=>'existing-member-work'));
$steps=array();foreach(array('A','B') as $letter){$step=$manager->quick_add($sprint,'Existing Step '.$letter);if(is_wp_error($step))throw new RuntimeException('Step setup');$steps[]=$step;wp_update_post(array('ID'=>$step,'post_status'=>'publish','post_content'=>'<!-- wp:sprint-engine-pro/workspace-response {"fieldId":"12345678-1234-4234-8234-123456789abc","prompt":"Existing response","responseType":"long_text"} /-->'));}
ThePath\SprintEngine\Content\Meta::save($sprint,array('_sprint_engine_launchable'=>true));
$member=(int)get_option('se0313_fixtures')['member']['id'];
update_option('sp_existing_member',$member);wp_set_current_user($member);rest_get_server();
$service=new ThePath\SprintEngine\Progress\ProgressService();$state=$service->start_sprint($member,$sprint);if(is_wp_error($state))throw new RuntimeException('Start failed');$attempt=$state['attempt_id'];
$r=new WP_REST_Request('POST','/sprint-engine-pro/v1/workspace/steps/'.$steps[0].'/fields/12345678-1234-4234-8234-123456789abc/response');$r->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));$r->set_param('expected_attempt_id',(string)$attempt);$r->set_param('value','Historical response retained');
$response=rest_do_request($r);if(200!==$response->get_status())throw new RuntimeException('Workspace fixture status '.$response->get_status());
$r=new WP_REST_Request('POST','/sprint-engine-pro/v1/notes/steps/'.$steps[0]);$r->set_header('X-WP-Nonce',wp_create_nonce('wp_rest'));$r->set_param('expected_attempt_id',(string)$attempt);$r->set_param('expected_revision','absent');$r->set_param('value','Historical private Notes retained');
$notes=rest_do_request($r);if(200!==$notes->get_status())throw new RuntimeException('Notes fixture status '.$notes->get_status());
foreach($steps as $step){$out=$service->complete_step($member,$sprint,$step,$attempt);if(is_wp_error($out))throw new RuntimeException('Complete failed');}
$new=$service->restart_sprint($member,$sprint,$attempt);if(is_wp_error($new))throw new RuntimeException('Restart failed');
wp_set_current_user(1);$objects=array();foreach(array('sprint_engine_pro_cl','sprint_engine_pro_jr') as $type){$objects[]=wp_insert_post(array('post_type'=>$type,'post_status'=>'draft','post_title'=>'Synthetic retained draft'));}
$storage=ThePath\SprintEnginePro\Resources\Storage::root();if(is_wp_error($storage))throw new RuntimeException('Private disposable storage not configured');
$bytes="Retained private synthetic resource\n";$key=bin2hex(random_bytes(32)).'.bin';file_put_contents($storage.DIRECTORY_SEPARATOR.$key,$bytes);
$resource=ThePath\SprintEnginePro\Resources\Record::save(0,array('version'=>1,'revision'=>wp_generate_uuid4(),'title'=>'Retained synthetic resource','description'=>'Private regression fixture','sprint_ids'=>array($sprint),'completion_ids'=>array(),'file'=>array('key'=>$key,'name'=>'retained.txt','size'=>strlen($bytes),'sha256'=>hash('sha256',$bytes))),null);
if(is_wp_error($resource))throw new RuntimeException('Resource fixture save refused');$objects[]=$resource;
foreach($objects as $object)if(!is_int($object)||$object<1||!get_post($object))throw new RuntimeException('Retained native object missing');
$fields=array('sprint_id'=>$sprint,'member'=>$member,'attempt'=>$new['attempt_id'],'steps'=>$steps,'native_objects'=>$objects);
update_option('se0313_retained',$fields);echo "Retained native Pro fixture created.\n";
