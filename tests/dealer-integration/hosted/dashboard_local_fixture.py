#!/usr/bin/env python3
"""Prepare/restore an isolated local adapter fixture. Hosted source guards stay unchanged.
Only private test copies are adapted to the existing loopback WP root; never deploy these copies.
"""
from pathlib import Path
import json,subprocess,sys
ROOT=Path(__file__).resolve().parents[3]
WP=ROOT/'.staging/wp'; PRIVATE=ROOT/'.private/dashboard-fixture'; PRIVATE.mkdir(mode=0o700,exist_ok=True)
WRAPPER=WP/'wp-content/mu-plugins/ag-dashboard-fixture.php'
def wp(code):
 r=subprocess.run(['wp','--path='+str(WP),'eval',"if(wp_get_environment_type()!=='local'||!in_array(wp_parse_url(home_url(),PHP_URL_HOST),['127.0.0.1','localhost'])){throw new Exception('Local fixture only');}"+code],capture_output=True,text=True)
 if r.returncode:
  (PRIVATE/'setup-error.log').write_text(r.stdout+r.stderr);raise SystemExit('Fixture setup failed. Inspect private log.')
 return r.stdout
runtime=ROOT/'.private/runtime.php';backup=PRIVATE/'runtime-before.php';opts=PRIVATE/'options-before.json'
if sys.argv[1:] == ['restore']:
 WRAPPER.unlink(missing_ok=True)
 if backup.exists():runtime.write_bytes(backup.read_bytes());backup.unlink()
 if opts.exists():
  old=json.loads(opts.read_text());wp("update_option('autoagora_dealer_admin_release',"+json.dumps(old['release'])+");")
  opts.unlink()
 subprocess.run(['python3',str(ROOT/'tests/dealer-integration/sync-local.py')],check=True)
 print('Local private-review fixture restored.');raise SystemExit()
if not backup.exists():backup.write_bytes(runtime.read_bytes());backup.chmod(0o600)
if not opts.exists():
 path=str(opts);wp('file_put_contents('+json.dumps(path)+",wp_json_encode(['release'=>get_option('autoagora_dealer_admin_release')]));")
# Runtime file is ignored and used exclusively by this disposable loopback WordPress installation.
s=runtime.read_text();s=s.replace("define('AUTOAGORA_DEALER_PUBLIC_ENABLED', false)","define('AUTOAGORA_DEALER_PUBLIC_ENABLED', true)")
if "define('AUTOAGORA_DEALER_PUBLIC_ENABLED'" not in s:s=s.replace('<?php',"<?php\ndefine('AUTOAGORA_DEALER_PUBLIC_ENABLED', true);",1)
runtime.write_text(s)
wp("update_option('autoagora_dealer_admin_release',0);")
accountfile=PRIVATE/'accounts.json'
code="""
$p=ACCOUNTFILE;$a=is_file($p)?json_decode(file_get_contents($p),true):[];
foreach(['one','two'] as $n){
 if(!isset($a[$n])){$login='dashboard-fixture-'.$n;$u=get_user_by('login',$login);if($u){throw new Exception('Unexpected existing fixture user');}$pass=bin2hex(random_bytes(24));$id=wp_insert_user(['user_login'=>$login,'user_pass'=>$pass,'user_email'=>'','display_name'=>'Dashboard Fixture '.$n,'role'=>'dealership']);if(is_wp_error($id)){throw new Exception('Could not create fixture user');}$a[$n]=['id'=>$id,'username'=>$login,'password'=>$pass];}
 $u=new WP_User($a[$n]['id']);foreach(['manage_options','edit_others_posts','publish_posts']as$c){$u->add_cap($c,false);}
}
file_put_contents($p,wp_json_encode($a));chmod($p,0600);
if(!is_file(CLIENTFILE)){(new \\AutoAgora\\Dealer\\Cli())->client(['dashboard-fixture'],['name'=>'Staging connection tester','redirect'=>'http://127.0.0.1:8899/?autoagora_stage_client=callback','output'=>CLIENTFILE]);}
""".replace('ACCOUNTFILE',json.dumps(str(accountfile))).replace('CLIENTFILE',json.dumps(str(PRIVATE/'demo-client.json')))
wp(code)
accounts=json.loads(accountfile.read_text());ids=[a['id'] for a in accounts.values()]
sub=WP/'wp-content/mu-plugins/ag-dashboard-fixture';sub.mkdir(exist_ok=True)
for source,name in [('staging-client.php','client.php'),('staging-workspace.php','workspace.php')]:
 code=(ROOT/'tests/dealer-integration/hosted'/source).read_text()
 code=code.replace("'staging'","'local'").replace("'/home/customer/www/staging7.autoagora.cy/public_html'",json.dumps(str(WP))).replace("'staging7.autoagora.cy'","'127.0.0.1'")
 code=code.replace("home_url('/staging-test-assets/car-front.png')","'https://images.example.com/fixture-one.png'").replace("home_url('/staging-test-assets/car-rear.png')","'https://images.example.com/fixture-two.png'")
 (sub/name).write_text(code)
# Adapt only the local template copy's presentation guard, not tracked production source.
p=WP/'wp-content/themes/bricks-child/includes/dealer-integration/workspace-template.php'
p.write_text((ROOT/'includes/dealer-integration/workspace-template.php').read_text().replace("wp_get_environment_type() === 'staging'","wp_get_environment_type() === 'local'"))
WRAPPER.write_text("""<?php
if(wp_get_environment_type()!=='local'||wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1'){wp_die('Local fixture only');}
$agStagePrivate=PRIVATE;
$ids=IDS;
add_filter('autoagora_dealer_api_eligible',static fn($ok,$id)=>$ok&&in_array((int)$id,$ids,true),999,2);
add_filter('pre_http_request',static function($pre,$args,$url){return wp_parse_url($url,PHP_URL_HOST)==='127.0.0.1'?false:$pre;},PHP_INT_MAX,3);
add_filter('login_redirect',static fn($url,$requested,$user)=>$user instanceof WP_User&&in_array((int)$user->ID,$ids,true)?home_url('/?autoagora_connect=workspace'):$url,999,3);
require __DIR__.'/ag-dashboard-fixture/client.php';
require __DIR__.'/ag-dashboard-fixture/workspace.php';
""".replace('PRIVATE',json.dumps(str(PRIVATE))).replace('IDS',json.dumps(ids)))
print('Local dashboard adapter fixture ready. No hosted source guard was changed.')

# Exercise the exact hosted backend/mutation and native REST gates in the local fixture too.
guard=(ROOT/'tests/dealer-integration/hosted/staging-guard.php').read_text()
start=guard.index("// Test users must use the scoped API.")
end=guard.index("// Remind operators",start)
with WRAPPER.open('a') as f:f.write('\n$agStageDealers=$ids;\n'+guard[start:end])
