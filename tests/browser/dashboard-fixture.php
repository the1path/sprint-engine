<?php
/** Disposable SE-013 browser fixtures; never packaged. */
use ThePath\SprintEngine\Content\Meta;
use ThePath\SprintEngine\Content\StructureManager;
use ThePath\SprintEngine\Progress\ProgressService;
use ThePath\SprintEngine\Dashboard\Routes;
use ThePath\SprintEngine\Runner\Routes as RunnerRoutes;
$root=getenv('SE_TEST_WP_ROOT');
if (!$root || 'yes'!==getenv('SE_TEST_DISPOSABLE')) { exit(1); }
require $root . '/wp-load.php'; require_once ABSPATH . 'wp-admin/includes/user.php';
function sprint_engine_dashboard_browser_snapshot($fixture) {
    global $wpdb;
    return array($wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sprint_engine_enrolments WHERE user_id IN (%d,%d) ORDER BY id",$fixture['user'],$fixture['other']),ARRAY_A),$wpdb->get_results($wpdb->prepare("SELECT * FROM {$wpdb->prefix}sprint_engine_step_progress WHERE user_id IN (%d,%d) ORDER BY id",$fixture['user'],$fixture['other']),ARRAY_A));
}
if (isset($argv[1])) {
    $fixture=json_decode(file_get_contents($argv[1]),true); $mode=$argv[2] ?? 'cleanup';
    if ($mode==='brand') {
        update_option(ThePath\SprintEngine\Settings\RunnerBranding::OPTION, array('primary'=>'#482070','primary_text'=>'#ffffff','background'=>'#e9e0f0','surface'=>'#fffafa','text'=>'#191020','muted'=>'#554460','corner_style'=>'rounded','logo_id'=>$fixture['image']));
        exit;
    }
    if ($mode==='unbrand') {
        if (false===$fixture['branding']) { delete_option(ThePath\SprintEngine\Settings\RunnerBranding::OPTION); } else { update_option(ThePath\SprintEngine\Settings\RunnerBranding::OPTION,$fixture['branding']); }
        exit;
    }
    if ($mode==='verify-read') {
        if ($fixture['snapshot']!==sprint_engine_dashboard_browser_snapshot($fixture)) { throw new RuntimeException('Browser Dashboard/Start reads mutated progress'); }
        echo "PASS: Exact browser enrolment/Step rows unchanged by Dashboard and Runner Start navigation.\n"; exit;
    }
    if ($mode==='verify-journey') {
        wp_set_current_user($fixture['user']); $service=new ProgressService();
        $state=$service->get_state($fixture['user'],$fixture['ids']['completed']);
        if ($state['attempt_number']!==2 || $state['completed_steps']!==0 || $state['current_step_id']!==$fixture['steps']['completed'][0]) { throw new RuntimeException('Restart journey state wrong'); }
        $all=sprint_engine_dashboard_browser_snapshot($fixture);
        foreach ($fixture['snapshot'][0] as $row) { if ($row['sprint_id']==$fixture['ids']['completed'] && !in_array($row,$all[0],true)) { throw new RuntimeException('Historical attempt changed'); } }
        foreach ($fixture['snapshot'][1] as $row) { if ($row['sprint_id']==$fixture['ids']['completed'] && !in_array($row,$all[1],true)) { throw new RuntimeException('Historical Step changed'); } }
        require_once ABSPATH . 'wp-admin/includes/plugin.php'; $before=sprint_engine_dashboard_browser_snapshot($fixture);
        deactivate_plugins('sprint-engine/sprint-engine.php'); $result=activate_plugin('sprint-engine/sprint-engine.php');
        if (is_wp_error($result) || $before!==sprint_engine_dashboard_browser_snapshot($fixture)) { throw new RuntimeException('Lifecycle retention failed'); }
        echo "PASS: Browser restart creates canonical Attempt 2 at first Step, zero completed, exact history and lifecycle retention.\n"; exit;
    }
    wp_set_current_user(1);
    foreach($fixture['posts'] as $id) { wp_delete_post($id,true); } wp_delete_attachment($fixture['image'],true);
    foreach(array($fixture['user'],$fixture['other']) as $member) { $wpdb->delete($wpdb->prefix.'sprint_engine_step_progress',array('user_id'=>$member));$wpdb->delete($wpdb->prefix.'sprint_engine_enrolments',array('user_id'=>$member)); wp_delete_user($member); }
    $helper=WPMU_PLUGIN_DIR.'/sprint-engine-dashboard-fixture.php';
    if (is_file($helper) && str_contains(file_get_contents($helper),'SE013 disposable')) { unlink($helper); }
    exit;
}
wp_set_current_user(1); $manager=new StructureManager(); $service=new ProgressService();
$fixture=array('ids'=>array(),'steps'=>array(),'posts'=>array());
$fixture['branding']=get_option(ThePath\SprintEngine\Settings\RunnerBranding::OPTION,false);
foreach(array('available'=>'Make room for what matters','active'=>'Build a thoughtful weekly review','completed'=>'Choose your next practical action','denied'=>'SE013 inaccessible private title','unavailable'=>'SE013 not launchable') as $key=>$title) {
    $id=wp_insert_post(array('post_type'=>'sprint_engine_sprint','post_status'=>'publish','post_title'=>$title,'post_name'=>'se013-'.$key.'-'.wp_generate_password(6,false),'post_excerpt'=>$key==='active'?'':'A focused process you can work through one Step at a time.'));
    $fixture['ids'][$key]=$id; $fixture['posts'][]=$id;
    foreach(array('Reflect on what matters','Choose one action') as $step_title) { $step=$manager->quick_add($id,$step_title); $fixture['steps'][$key][]=$step; $fixture['posts'][]=$step; wp_update_post(array('ID'=>$step,'post_status'=>'publish','post_content'=>'<!-- wp:paragraph --><p>Pause, reflect, and write down one useful next step.</p><!-- /wp:paragraph -->')); }
    Meta::save($id,array('_sprint_engine_launchable'=>$key!=='unavailable','_sprint_engine_estimated_duration_value'=>'1.5','_sprint_engine_estimated_duration_unit'=>'hours','_sprint_engine_completion_message'=>'A useful step forward.','_sprint_engine_completion_cta_label'=>'Your next step','_sprint_engine_completion_cta_url'=>home_url('/?sprint-engine-next=1')));
    $fixture['urls'][$key]=RunnerRoutes::url(get_post_field('post_name',$id));
}
$upload=wp_upload_bits('sprint-engine-dashboard-fixture.png',null,base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII='));
$image=wp_insert_attachment(array('post_title'=>'Dashboard fixture','post_mime_type'=>'image/png','guid'=>$upload['url']),$upload['file']);
wp_update_attachment_metadata($image,array('width'=>1,'height'=>1,'file'=>_wp_relative_upload_path($upload['file']))); update_post_meta($image,'_wp_attachment_image_alt','A quiet moment'); set_post_thumbnail($fixture['ids']['available'],$image); set_post_thumbnail($fixture['ids']['denied'],$image); $fixture['image']=$image;
$fixture['user']=wp_insert_user(array('user_login'=>'se013-browser-'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber'));
$fixture['other']=wp_insert_user(array('user_login'=>'se013-browser-b-'.wp_generate_password(10,false),'user_pass'=>wp_generate_password(),'role'=>'subscriber'));
wp_set_current_user($fixture['user']);
foreach(array('active','completed') as $key) { $service->start_sprint($fixture['user'],$fixture['ids'][$key]); $service->complete_step($fixture['user'],$fixture['ids'][$key],$fixture['steps'][$key][0]); if($key==='completed')$service->complete_step($fixture['user'],$fixture['ids'][$key],$fixture['steps'][$key][1]); }
// Server-side access boundary for just these disposable members, including leak tests.
if (!is_dir(WPMU_PLUGIN_DIR))mkdir(WPMU_PLUGIN_DIR,0755,true);
$allowed=array_values(array_diff($fixture['ids'],array($fixture['ids']['denied'])));
file_put_contents(WPMU_PLUGIN_DIR.'/sprint-engine-dashboard-fixture.php',"<?php\n// SE013 disposable browser access fixture; never packaged.\nadd_filter('sprint_engine/user_can_access_sprint', static function (\$allowed, \$user, \$sprint) { return in_array(\$user, ".var_export(array($fixture['user'],$fixture['other']),true).", true) ? \$allowed && in_array(\$sprint, ".var_export($allowed,true).", true) : \$allowed; }, 10, 3);\n");
$wp_rewrite->set_permalink_structure('/%postname%/'); ThePath\SprintEngine\Plugin::activate();
$fixture['url']=Routes::url();
foreach(array('user'=>'cookie','other'=>'other_cookie') as $member=>$key)$fixture[$key]=array('name'=>LOGGED_IN_COOKIE,'value'=>wp_generate_auth_cookie($fixture[$member],time()+3600,'logged_in'),'url'=>home_url('/'));
$fixture['snapshot']=sprint_engine_dashboard_browser_snapshot($fixture);
echo wp_json_encode($fixture);
