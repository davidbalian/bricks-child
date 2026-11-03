#!/usr/bin/env python3
"""Offline checks for environment-local public activation; no WordPress/network writes."""
from pathlib import Path
import json, subprocess, tempfile
ROOT=Path(__file__).resolve().parents[2]
cases=[
 ('default',{}, {},False,False,True),
 ('review',{'autoagora_dealer_admin_release':1},{},True,False,True),
 ('runtime alone cannot enable public',{'autoagora_dealer_admin_release':1},{'public_enabled':True,'rules_verified':True},True,False,True),
 ('option alone cannot enable public',{'autoagora_dealer_public_release':1},{},True,False,True),
 ('string flags rejected',{'autoagora_dealer_public_release':1},{'public_enabled':'true','rules_verified':'true'},True,False,True),
 ('approved public',{'autoagora_dealer_public_release':1},{'public_enabled':True,'rules_verified':True},True,True,False),
 ('unverified rules block writes',{'autoagora_dealer_public_release':1},{'public_enabled':True,'rules_verified':False},True,True,False),
 ('review option remains fail closed',{'autoagora_dealer_admin_release':1,'autoagora_dealer_public_release':1},{'public_enabled':True,'rules_verified':True},True,True,True),
]
for name,options,config,enabled,public,private in cases:
 with tempfile.TemporaryDirectory(prefix='ag-public-release-') as tmp:
  base=Path(tmp);(base/'public_html').mkdir();(base/'.autoagora-dealer').mkdir()
  (base/'.autoagora-dealer/runtime.json').write_text(json.dumps({'encryption':'x'*64,**config}))
  code="""
  define('ABSPATH',$argv[1].'/public_html/');$opts=json_decode($argv[2],true);
  function get_option($key){global $opts;return $opts[$key]??false;}
  require $argv[3].'/includes/dealer-integration/Installation.php';
  require $argv[3].'/includes/dealer-integration/Review.php';
  \\AutoAgora\\Dealer\\Installation::load();
  $flags=[];foreach(['AUTOAGORA_DEALER_API_ENABLED','AUTOAGORA_DEALER_PUBLIC_ENABLED','AUTOAGORA_DEALER_ONBOARDING_ENABLED','AUTOAGORA_DEALER_PRIVATE_SELLERS_ENABLED','AUTOAGORA_CONNECT_SIGNUP_ENABLED','AUTOAGORA_DEALER_RULES_VERIFIED']as $key){$flags[$key]=defined($key)&&constant($key);}
  echo json_encode([$flags,\\AutoAgora\\Dealer\\Review::privateMode()]);
  """
  r=subprocess.run(['php','-r',code,str(base),json.dumps(options),str(ROOT)],capture_output=True,text=True,check=True)
  flags,actual_private=json.loads(r.stdout)
  assert flags['AUTOAGORA_DEALER_API_ENABLED']==enabled,name
  for key in ['AUTOAGORA_DEALER_PUBLIC_ENABLED','AUTOAGORA_DEALER_ONBOARDING_ENABLED','AUTOAGORA_DEALER_PRIVATE_SELLERS_ENABLED','AUTOAGORA_CONNECT_SIGNUP_ENABLED']:assert flags[key]==public,name
  assert actual_private==private,name
  assert flags['AUTOAGORA_DEALER_RULES_VERIFIED']==(public and config.get('rules_verified') is True),name
  print('PASS '+name)
print('8 offline public-release configuration cases passed.')
