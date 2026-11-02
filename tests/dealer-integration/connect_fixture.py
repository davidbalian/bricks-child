#!/usr/bin/env python3
"""Opt-in loopback-only onboarding fixture. Never deploy the generated MU file."""
from pathlib import Path
import json, subprocess, sys, secrets, time
ROOT=Path(__file__).resolve().parents[2];WP=ROOT/'.staging/wp';P=ROOT/'.private/connect-fixture'
P.mkdir(mode=0o700,exist_ok=True)
MU=WP/'wp-content/mu-plugins/ag-connect-fixture.php'
TESTPHONES=P/'signup-phones.json'
if not TESTPHONES.exists():
 TESTPHONES.write_text(json.dumps({'codes':{'+3579000'+f'{i:04}':''.join(str(secrets.randbelow(10)) for _ in range(6)) for i in range(1,51)},'expires_at':int(time.time())+86400*14}));TESTPHONES.chmod(0o600)
if sys.argv[1:]==['restore']:
 MU.unlink(missing_ok=True)
 subprocess.run(['python3',str(ROOT/'tests/dealer-integration/sync-local.py')],check=True)
 print('Normal private local fixture restored.');raise SystemExit()
MU.write_text("""<?php
defined('ABSPATH')||exit;
if(wp_get_environment_type()!=='local'||wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1'){wp_die('Loopback fixture only');}
define('AUTOAGORA_DEALER_PUBLIC_ENABLED',true);
define('AUTOAGORA_DEALER_ONBOARDING_ENABLED',true);
define('AUTOAGORA_CONNECT_SIGNUP_ENABLED',(bool)get_option('_ag_connect_fixture_signup_enabled',true));
$signup=json_decode(file_get_contents(SIGNUPFILE),true);
define('AUTOAGORA_CONNECT_TEST_PHONES',array_map(static fn($code)=>hash('sha256',$code),$signup['codes']));
define('AUTOAGORA_CONNECT_TEST_EXPIRES',(int)$signup['expires_at']);
define('AUTOAGORA_DEALER_PRIVATE_SELLERS_ENABLED',(bool)get_option('_ag_connect_fixture_private_enabled',true));
add_filter('pre_option_autoagora_dealer_admin_release',static fn()=>0);
add_filter('autoagora_dealer_api_eligible',static function($ok,$id){return $ok&&!get_user_meta($id,'_ag_connect_fixture_excluded',true);},99,2);
""".replace('SIGNUPFILE',json.dumps(str(TESTPHONES))))
subprocess.run(['python3',str(ROOT/'tests/dealer-integration/sync-local.py')],check=True)
code="""<?php
if(wp_get_environment_type()!=='local'||wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1'){WP_CLI::error('Local only');}
global $wpdb;
update_option('_ag_connect_fixture_private_enabled',true,false);update_option('_ag_connect_fixture_signup_enabled',true,false);
$path=ACCOUNTFILE;
if(!is_file($path)){
 $accounts=[];
 // Invalid password hashes, no contacts: synthetic pagination fixtures, never usable logins.
 for($i=0;$i<201;$i++){
  $login='connect-pagination-'.$i;
  if(!username_exists($login)){
   $wpdb->insert($wpdb->users,['user_login'=>$login,'user_pass'=>'!disabled-synthetic-fixture','user_nicename'=>$login,'user_email'=>'','display_name'=>'Pagination Fixture '.$i,'user_registered'=>current_time('mysql')]);
   $id=(int)$wpdb->insert_id;$u=new WP_User($id);$u->set_role('dealership');
   update_user_meta($id,'_ag_connect_fixture_excluded',1);
  }
 }
 foreach(['owner'=>'Cyprus Motors Ltd','other'=>'Island Auto','ambiguous'=>'Cyprus Motor Co','legacy'=>'Legacy Road Motors']as$key=>$name){
  $login='connect-fixture-'.$key;$u=get_user_by('login',$login);
  $password=bin2hex(random_bytes(24));
  if($u){wp_set_password($password,$u->ID);$id=$u->ID;}
  else{$id=wp_insert_user(['user_login'=>$login,'user_pass'=>$password,'user_email'=>'','display_name'=>$name,'role'=>'dealership']);}
  if(is_wp_error($id)){WP_CLI::error('Fixture account failure');}
  update_user_meta($id,'dealership_name',$name);
  update_user_meta($id,'_ag_dealer_showroom',['city'=>'Nicosia']);
  if($key==='legacy'){update_user_meta($id,'_ag_connect_fixture_excluded',1);}
  $accounts[$key]=['id'=>$id,'username'=>$login,'password'=>$password];
 }
 file_put_contents($path,wp_json_encode($accounts));chmod($path,0600);
}
if(!is_file(CLIENTFILE)){(new \\AutoAgora\\Dealer\\Cli())->client(['connect-fixture'],['name'=>'Postaki local fixture','redirect'=>'http://localhost:3000/api/v1/autoagora/callback','output'=>CLIENTFILE]);}
$accounts=json_decode(file_get_contents($path),true);
if(!isset($accounts['private'])){
 $password=bin2hex(random_bytes(24));$id=wp_insert_user(['user_login'=>'connect-fixture-private','user_pass'=>$password,'user_email'=>'','display_name'=>'Private Fixture Seller','role'=>'client']);
 if(is_wp_error($id)){WP_CLI::error('Private fixture account failure');}
 $accounts['private']=['id'=>$id,'username'=>'connect-fixture-private','password'=>$password];file_put_contents($path,wp_json_encode($accounts));chmod($path,0600);
}
delete_transient('ag_dealer_directory_v1');delete_transient('ag_dealer_directory_v2');
""".replace('ACCOUNTFILE',json.dumps(str(P/'accounts.json'))).replace('CLIENTFILE',json.dumps(str(P/'client.json')))
source=P/'seed.php';source.write_text(code)
r=subprocess.run(['wp','--path='+str(WP),'--user=stage-admin','eval-file',str(source)],capture_output=True,text=True)
if r.returncode:
 (P/'setup-error.log').write_text(r.stdout+r.stderr);raise SystemExit('Local fixture failed; inspect private setup log.')
print('Local onboarding fixture ready with a real account beyond the first 200 catalogue rows.')
