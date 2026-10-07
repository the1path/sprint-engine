<?php
/** Disposable SE-008 browser fixtures and retention verification. */
$root = getenv('SE_TEST_WP_ROOT');
if (!$root || getenv('SE_TEST_DISPOSABLE') !== 'yes') exit(1);
require $root . '/wp-load.php';
wp_set_current_user(1);
function branding_snapshot() {
    global $wpdb;
    return array(get_option('sprint_engine_version'),get_option('sprint_engine_schema_version'),get_option('rewrite_rules'),$wpdb->get_results("SELECT * FROM {$wpdb->posts} ORDER BY ID", ARRAY_A),$wpdb->get_results("SELECT * FROM {$wpdb->postmeta} ORDER BY meta_id",ARRAY_A),$wpdb->get_results("SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments ORDER BY id",ARRAY_A),$wpdb->get_results("SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress ORDER BY id",ARRAY_A));
}
if(isset($argv[1])) {
    $fixture=json_decode(file_get_contents($argv[1]),true);
    if (branding_snapshot() !== $fixture['snapshot']) throw new RuntimeException('Branding save/reset altered content, progress, versions or rewrites');
    if(get_option('sprint_engine_runner_branding',false)!==false) throw new RuntimeException('Reset did not delete option');
    foreach($fixture['posts'] as $id) wp_delete_post($id,true);
    wp_delete_attachment($fixture['logo'],true);
    echo "PASS: Real HTTP save/reset retained exact content/progress/version/rewrite snapshots; option deleted.\n";
    exit;
}
delete_option('sprint_engine_runner_branding');
$models=array();
foreach(array(array(),array('primary'=>'#482070','surface'=>'#fffafa'),array('primary'=>'#ffffcc','corner_style'=>'rounded'),array('primary'=>'#123456','primary_text'=>'#abcdef')) as $config) { update_option('sprint_engine_runner_branding',$config); $models[]=array('config'=>ThePath\SprintEngine\Settings\RunnerBranding::get(),'variables'=>ThePath\SprintEngine\Settings\RunnerBranding::variables()); }
delete_option('sprint_engine_runner_branding');
$sprint=wp_insert_post(array('post_type'=>'sprint_engine_sprint','post_status'=>'publish','post_title'=>'Branding example','post_name'=>'branding-'.wp_generate_password(8,false)));
$step=(new ThePath\SprintEngine\Content\StructureManager())->quick_add($sprint,'Example Step');
wp_update_post(array('ID'=>$step,'post_status'=>'publish'));
ThePath\SprintEngine\Content\Meta::save($sprint,array('_sprint_engine_launchable'=>true));
$upload=wp_upload_bits('se008-logo.png',null,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+aN9sAAAAASUVORK5CYII='));
$logo=wp_insert_attachment(array('post_title'=>'SE008 test logo','post_mime_type'=>'image/png','guid'=>$upload['url']),$upload['file']);
wp_update_attachment_metadata($logo,array('width'=>1,'height'=>1,'file'=>_wp_relative_upload_path($upload['file'])));
update_post_meta($logo,'_wp_attachment_image_alt','Example organisation');
echo wp_json_encode(array('models'=>$models,'url'=>admin_url('edit.php?post_type=sprint_engine_sprint&page=sprint-engine-settings'),'runner'=>ThePath\SprintEngine\Runner\Routes::url(get_post_field('post_name',$sprint)),'logo'=>$logo,'posts'=>array($sprint,$step),'snapshot'=>branding_snapshot(),'cookies'=>array(array('name'=>LOGGED_IN_COOKIE,'value'=>wp_generate_auth_cookie(1,time()+3600,'logged_in'),'url'=>home_url('/')),array('name'=>AUTH_COOKIE,'value'=>wp_generate_auth_cookie(1,time()+3600,'auth'),'url'=>home_url('/')))));
