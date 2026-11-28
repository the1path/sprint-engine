<?php
/** Optional read-only diagnostics on an explicitly marked isolated Pro test site. */
if (!defined('SE0313_DISPOSABLE') || !is_file(ABSPATH.'SE0313-DISPOSABLE')) return;
add_filter('sprint_engine/runner_show_public_access_gate',static function($show){$GLOBALS['se0313_gate_count']=1+($GLOBALS['se0313_gate_count']??0);return $show;},PHP_INT_MAX);
add_action('sprint_engine/runner_access_denied_actions',static function(){$GLOBALS['se0313_denial_count']=1+($GLOBALS['se0313_denial_count']??0);},PHP_INT_MAX);
add_action('wp_footer',static function(){echo '<!-- registration gates='.($GLOBALS['se0313_gate_count']??0).' denials='.($GLOBALS['se0313_denial_count']??0).' -->';});
if(get_option('se0313_pro_classifier'))add_filter('sprint_engine/participant_access_kind',static fn($kind,$id)=>(int)get_option('se0313_pro_classifier')===$id?'open':'unknown',10,2);
