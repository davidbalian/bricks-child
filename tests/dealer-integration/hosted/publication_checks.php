<?php
/** Isolated unit contract: hosted staging publication must never change moderation elsewhere. */
define('ABSPATH', __DIR__);
define('AUTOAGORA_DEALER_PUBLIC_ENABLED', true);
$environment='production';$private=false;$filter='publish';
function wp_get_environment_type(){return $GLOBALS['environment'];}
function get_option($key,$default=false){return $key==='autoagora_dealer_admin_release'?$GLOBALS['private']:$default;}
function apply_filters($key,$value,...$args){return $GLOBALS['filter'];}
require dirname(__DIR__,3).'/includes/dealer-integration/Review.php';
require dirname(__DIR__,3).'/includes/dealer-integration/Support.php';
$cases=[['production',false,'publish','pending'],['local',false,'publish','pending'],['development',false,'publish','pending'],['staging',true,'publish','pending'],['staging',false,'pending','pending'],['staging',false,'private','pending'],['staging',false,true,'pending'],['staging',false,'publish','publish']];
foreach($cases as [$environment,$private,$filter,$expected]){
 $actual=\AutoAgora\Dealer\Support::submissionStatus(1,2);
 if($actual!==$expected){fwrite(STDERR,'Publication gate failed'.PHP_EOL);exit(1);}
}
echo 'Passed '.count($cases).' environment and moderation guard checks.'.PHP_EOL;
