#!/usr/bin/env python3
"""Opt-in staging7-only verified signup/OAuth/listing checks. Synthetic credentials stay in ignored .private files.
Requires preconfigured signup-qa.json approved test phones; never uses partner handoff phones.
"""
from pathlib import Path
import importlib.util,json,hashlib,base64,secrets,html,re,urllib.parse,time
ROOT=Path(__file__).resolve().parents[3];P=ROOT/'.private/connect-release';Q=ROOT/'.private/postaki-setup/signup-qa.json'
spec=importlib.util.spec_from_file_location('staging_http',ROOT/'tests/dealer-integration/hosted/hosted_checks.py');h=importlib.util.module_from_spec(spec);spec.loader.exec_module(h)
BASE=h.BASE;API=h.API;C=h.CLIENT;assert BASE=='https://staging7.autoagora.cy' and C['client_id']=='stage-qa'
qa=json.loads(Q.read_text());checks=[];TOKENS=[];artifact={'environment':BASE,'production_requests':0,'checks':checks,'accounts':{}}
def check(name,ok):
 checks.append({'check':name,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
 if not ok:raise AssertionError(name)
def field(raw,name):
 m=re.search(r'name="'+re.escape(name)+r'"[^>]*value="([^"]*)"',raw);assert m,'Missing form '+name;return html.unescape(m[1])
def auth(c,t):
 verifier=secrets.token_urlsafe(48);state=secrets.token_urlsafe(24)
 q={'autoagora_connect':'authorize','response_type':'code','client_id':c['client_id'],'redirect_uri':c['redirect_uri'],'scope':'stock:read stock:write stock:status catalog:read showroom:write','state':state,'code_challenge':base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode(),'code_challenge_method':'S256','account_type':t}
 return BASE+'/?'+urllib.parse.urlencode(q),verifier,state
def get(u,s):return h.request(u,session=s)
def post(u,s,op,raw=None,**data):
 raw=raw or get(u,s)[3];return h.request(u,'POST',{'connect_action':op,'connect_csrf':field(raw,'connect_csrf'),**data},session=s,form=True)
def nextpage(u,s,r):
 check('Browser returns to the same OAuth request',r[0]==302 and html.unescape(r[1].get('Location',''))==u);return get(u,s)
def save():Q.write_text(json.dumps(qa,indent=2));Q.chmod(0o600)
def api(token,path,method='GET',body=None,version=None):return h.api(token,path,method,body,version)
def run(t,foreign=None):
 a=qa[t];u,verifier,state=auth(C,t);s=h.opener();r=get(u,s)
 check(t+' connection opens branded login without directory',r[0]==200 and 'dealer_password' in r[3] and 'Find your dealership' not in r[3] and 'name="candidate"' not in r[3]);check(t+' signup is configured','Create an AutoAgora account' in r[3])
 if not a.get('created'):
  r=nextpage(u,s,post(u,s,'signup',r[3]));check(t+' signup confirms phone and selected type','name="signup_phone"' in r[3] and bool(re.search('value="'+t+'"\\s+selected=',r[3])))
  r=nextpage(u,s,post(u,s,'send-code',r[3],signup_phone=a['phone'],signup_type=t));check(t+' approved test phone reaches verification','name="signup_code"' in r[3])
  r=nextpage(u,s,post(u,s,'verify-code',r[3],signup_code=a['code']));check(t+' OTP proof unlocks account details','name="signup_password"' in r[3])
  r=post(u,s,'create-account',r[3],first_name='Signup QA',last_name='Private' if t=='private' else 'Dealer',company_name='Signup QA Test Motors',signup_password=a['password'],signup_confirm=a['password'])
  check(t+' signup creates a non-admin session and resumes OAuth',r[0]==302 and html.unescape(r[1].get('Location',''))==u);a['created']=True;save();r=get(u,s)
 else:r=nextpage(u,s,post(u,s,'signin',r[3],dealer_login=a['phone'],dealer_password=a['password']))
 check(t+' signed-in account sees consent',r[0]==200 and 'Authorize connection' in r[3]);nonce=field(r[3],'_wpnonce')
 r=h.request(u,'POST',{'_wpnonce':nonce,'decision':'approve'},session=s,form=True);params=urllib.parse.parse_qs(urllib.parse.urlparse(r[1].get('Location','')).query)
 check(t+' callback carries code and original state',r[0]==302 and params.get('state')==[state] and bool(params.get('code')))
 body={'grant_type':'authorization_code','client_id':C['client_id'],'client_secret':C['client_secret'],'redirect_uri':C['redirect_uri'],'code':params['code'][0],'code_verifier':verifier,'autoagora_id':'457'}
 r=h.request(API+'/oauth/token','POST',body,form=True);check(t+' real token exchange returns account identity',r[0]==200 and set(r[2].get('account',{}))=={'id','name'});tok=r[2];TOKENS.append(tok);a['id']=tok['account']['id'];save()
 check(t+' identity belongs to the new account',isinstance(a['id'],int) and a['id']!=457 and tok['account']['name']==('Signup QA Private' if t=='private' else 'Signup QA Test Motors'))
 refresh={'grant_type':'refresh_token','client_id':C['client_id'],'client_secret':C['client_secret'],'refresh_token':tok['refresh_token']}
 r=h.request(API+'/oauth/token','POST',refresh,form=True);check(t+' refresh includes the same account identity',r[0]==200 and r[2]['account']==tok['account']);tok=r[2];TOKENS.append(tok)
 check(t+' refresh still rotates credentials',tok['refresh_token']!=refresh['refresh_token'])
 if not a.get('listing'):
  payload=h.sample('signup-qa-'+t+'-'+secrets.token_hex(6));r=api(tok,'/listings','POST',payload)
  check(t+' car import publishes only on staging',r[0]==201 and r[2]['post_status']=='publish' and urllib.parse.urlparse(r[2]['url']).hostname=='staging7.autoagora.cy');a['listing']=r[2];save()
 car=a['listing'];read=api(tok,'/listings/'+str(car['id']));check(t+' API reads its own new car',read[0]==200 and read[2]['id']==car['id']);read=read[2]
 if read['post_status']=='publish':
  r=h.request(car['url'],session=s);check(t+' new car renders on normal staging site',r[0]==200 and 'STAGING TEST VEHICLE' in r[3] and 'critical error' not in r[3]);(P/('signup-'+t+'-car.html')).write_text(r[3])
 check(t+' imported photos are real staging files',len(read['photos'])==2 and all(h.request(p['url'])[0]==200 for p in read['photos']))
 if foreign:check('One new seller cannot read another seller’s API record',api(tok,'/listings/'+str(foreign))[0]==404)
 r=api(tok,'/listings/'+str(car['id']),'PATCH',{'price':12700},read['version']);check(t+' can update only its own car',r[0]==200);read=r[2]
 if t=='private':
  r=api(tok,'/listings/'+str(car['id'])+'/status','POST',{'status':'withdrawn'},read['version']);check('Private seller withdrawal retains its staging car',r[0]==200 and r[2]['status']=='withdrawn')
 for tab in ['connections','stock','activity']:
  r=get(BASE+'/?autoagora_connect=workspace&tab='+tab,s);check(t+' own '+tab+' dashboard renders',r[0]==200 and 'id="workspace-main"' in r[3] and 'WordPress admin' not in r[3] and 'Fatal error' not in r[3] and 'Warning<' not in r[3]);(P/('signup-'+t+'-'+tab+'.html')).write_text(r[3])
  if tab=='stock' and t=='private':check('Private dashboard uses private-seller labels','<h1>Your listings</h1>' in r[3] and '<th>Seller</th>' in r[3])
  if tab=='stock':check(t+' dashboard includes only its own imported record',str(car['id']) in r[3] and (foreign is None or ('data-open="stock-'+str(foreign)+'"') not in r[3]))
 check(t+' WordPress backend stays denied',get(BASE+'/wp-admin/',s)[0] in (302,403));check(t+' native REST stays denied',get(BASE+'/wp-json/wp/v2/users',s)[0]==403)
 # A new login with the other signup hint must keep this existing account.
 wrong,_,_=auth(C,'dealer' if t=='private' else 'private');fresh=h.opener();r=post(wrong,fresh,'signin',dealer_login=a['phone'],dealer_password=a['password']);check(t+' later login accepts a different signup hint',r[0]==302 and html.unescape(r[1].get('Location',''))==wrong)
 r=api(tok,'/connection','DELETE');check(t+' QA grant disconnects',r[0]==200);check(t+' revoked QA token stops working',api(tok,'/listings')[0]==401)
 artifact['accounts'][t]={'id':a['id'],'listing_id':car['id'],'listing_url':car['url']};return car['id']
try:
 for path in ['/wp-admin/admin-ajax.php','/wp-admin/admin-post.php','/?autoagora_connect=dealer-login']:
  check('Anonymous native mutation denied: '+path,h.request(BASE+path,'POST',{'action':'ag_register_user'},form=True)[0]==403)
 # Hosting closes XML-RPC requests before a verifiable application response; source guard is checked locally.
 print('INFO XML-RPC hosting rule closes the connection; not counted as an application test.',flush=True)
 postaki=json.loads((ROOT/'.private/postaki-setup/credentials.json').read_text());u,_,_=auth(postaki,'private');r=h.request(u);check('Postaki exact localhost callback opens revised login',r[0]==200 and 'dealer_password' in r[3] and 'Find your dealership' not in r[3]);private=run('private');run('dealer',private)
finally:
 for tok in TOKENS:
  try:api(tok,'/connection','DELETE')
  except Exception:pass
 artifact['checked_at']=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime());(P/'hosted-signup-results.json').write_text(json.dumps(artifact,indent=2))
print(str(len(checks))+' hosted signup/OAuth/dashboard checks passed. No production requests or SMS.')
