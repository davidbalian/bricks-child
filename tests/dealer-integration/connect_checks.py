#!/usr/bin/env python3
"""Real HTTP + database checks pinned to the disposable local WordPress fixture."""
import base64,concurrent.futures,hashlib,html,json,re,secrets,subprocess,urllib.parse
from pathlib import Path
import http_checks as h
P=h.PRIVATE/'connect-fixture';A=json.loads((P/'accounts.json').read_text());C=json.loads((P/'client.json').read_text());RESULTS=[]
def check(name,ok):
 RESULTS.append({'check':name,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
 if not ok:raise AssertionError(name)
def wp(expr):
 out=P/'data.json'
 code="if(wp_get_environment_type()!=='local'){throw new Exception('Local only');}file_put_contents("+json.dumps(str(out))+",wp_json_encode("+expr+"));"
 r=subprocess.run(['wp','--path='+str(h.ROOT/'.staging/wp'),'--user=stage-admin','eval',code],capture_output=True,text=True)
 if r.returncode:(P/'cli-error.log').write_text(r.stdout+r.stderr);raise AssertionError('Local data check failed')
 return json.loads(out.read_text())
def limits():h.limits()
def url(**overrides):
 verifier=secrets.token_urlsafe(48);state=secrets.token_urlsafe(24)
 q={'autoagora_connect':'authorize','response_type':'code','client_id':C['client_id'],'redirect_uri':C['redirect_uri'],'scope':'stock:read stock:write stock:status catalog:read showroom:write','state':state,'code_challenge':base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode(),'code_challenge_method':'S256',**overrides}
 return h.BASE+'/?'+urllib.parse.urlencode(q),verifier,state
def page(u,s):return h.request(u,session=s)
def post(u,s,operation,raw,**fields):
 csrf=re.search(r'name="connect_csrf" value="([^"]+)"',raw)
 return h.request(u,'POST',{'connect_csrf':html.unescape(csrf[1]) if csrf else '', 'connect_action':operation,**fields},session=s,form=True)
def match(u,s,name='Cyprsu Motors'):
 r=page(u,s);r=post(u,s,'find',r[3],dealership_name=name,dealership_city='Nicosia');return r
def token(raw):return re.search(r'name="candidate" value="([^"]+)"',raw)[1]
def choose(u,s,raw):return post(u,s,'choose',raw,candidate=token(raw))
def signin(u,s,raw,key='owner',password=None):
 return post(u,s,'signin',raw,dealer_login=A[key]['username'],dealer_password=password or A[key]['password'])
def main():
 limits()
 for label,meta in [('ACF label/value city',{'label':'Nicosia','value':'nicosia'}),('Legacy list city',['Nicosia']),('Malformed city',{'unexpected':['nested']})]:
  raw=json.dumps(meta,separators=(',',':'))
  result=wp("(static function(){$id="+str(A['legacy']['id'])+";update_user_meta($id,'car_city',json_decode("+json.dumps(raw)+",true));delete_transient('ag_dealer_directory_v2');set_error_handler(static function($n,$m,$file){if(str_contains($file,'DealerDirectory.php')){throw new \\RuntimeException($m);}});$result=\\AutoAgora\\Dealer\\DealerDirectory::search('Cyprus Motors');restore_error_handler();return count($result)>0?'safe':'failed';})()")
  check(label+' cannot emit directory or header warnings',result=='safe')
 u,v,state=url();s=h.opener();r=page(u,s)
 check('First connection renders branded dealership form directly',r[0]==200 and 'Find your dealership' in r[3] and 'autoagora-logo.png' in r[3] and 'wp-login.php' not in r[3] and 'Powered by WordPress' not in r[3])
 check('Page is private/noindex and loads only scoped shared UI', 'no-store' in r[1].get('Cache-Control','') and 'noindex' in r[1].get('X-Robots-Tag','') and 'button-kit/buttons.css' in r[3] and '/style.css' not in r[3])
 cookie=next(c for handler in s.handlers if hasattr(handler,'cookiejar') for c in handler.cookiejar if c.name=='ag_connect')
 check('Browser CSRF cookie is HttpOnly and SameSite',cookie.has_nonstandard_attr('HttpOnly') and cookie.get_nonstandard_attr('SameSite')=='Lax')
 r=h.request(u,'POST',{'connect_action':'find','dealership_name':'Cyprus Motors'},session=s,form=True)
 check('Missing form CSRF rejected',r[0]==403)
 for label,over in [('unknown client',{'client_id':'unknown'}),('unregistered callback',{'redirect_uri':'https://evil.example/callback'}),('plain PKCE',{'code_challenge_method':'plain'}),('invalid scope',{'scope':'stock:read admin:all'})]:
  check(label+' rejected before showing dealership lookup',page(url(**over)[0],h.opener())[0]==400)
 r=match(u,s)
 check('Fuzzy typo finds dealership past row 200',r[0]==200 and 'Cyprus Motors Ltd' in r[3])
 check('Near names remain separate choices',r[3].count('name="candidate"')>=2)
 check('Matches expose no login/contact/internal account ID',A['owner']['username'] not in r[3] and 'user_id' not in r[3] and 'name="dealer_id"' not in r[3] and 'score' not in r[3])
 otherbrowser=h.opener();raw=page(u,otherbrowser)[3]
 check('Selection token is bound to browser session',post(u,otherbrowser,'choose',raw,candidate=token(r[3]))[0]==403)
 check('Forged candidate is rejected',post(u,s,'choose',r[3],candidate='f'*32)[0]==403)
 r=choose(u,s,r[3]);check('Selection requires existing account password',r[0]==200 and 'dealer_password' in r[3] and 'Cyprus Motors Ltd' in r[3])
 r=signin(u,s,r[3],password='incorrect-fixture-password');check('Incorrect password stays on branded form',r[0]==401 and 'Sign-in failed' in r[3] and 'Location' not in r[1])
 r=signin(u,s,r[3],key='other');check('Other dealer credentials cannot claim selected match',r[0]==401 and page(h.BASE+'/?autoagora_connect=dealer-login',s)[0]==200)
 r=signin(u,s,r[3]);check('Owner login resumes exact OAuth request without admin redirect',r[0]==302 and r[1].get('Location')==u)
 r=page(u,s);check('Existing OAuth consent continues after branded sign-in',r[0]==200 and 'Connect Postaki local fixture' in r[3] and 'Authorize connection' in r[3] and 'autoagora-logo.png' in r[3])
 check('Consent security policy permits bundled brand assets',"style-src 'self' 'unsafe-inline'" in r[1].get('Content-Security-Policy','') and "img-src 'self' data:" in r[1].get('Content-Security-Policy',''))
 nonce=re.search(r'name="_wpnonce" value="([^"]+)"',r[3])[1]
 r=h.request(u,'POST',{'_wpnonce':nonce,'decision':'approve'},session=s,form=True)
 callback=urllib.parse.urlparse(r[1].get('Location',''));params=urllib.parse.parse_qs(callback.query)
 check('Consent returns code and original state to registered callback',r[0]==302 and callback.netloc=='localhost:3000' and params.get('state')==[state] and 'code' in params)
 r=h.request(h.API+'/oauth/token','POST',{'grant_type':'authorization_code','client_id':C['client_id'],'client_secret':C['client_secret'],'redirect_uri':C['redirect_uri'],'code':params['code'][0],'code_verifier':v},form=True)
 check('Original PKCE verifier exchanges for real API tokens',r[0]==200 and bool(r[2].get('refresh_token')))
 access=r[2]['access_token'];refresh=r[2]['refresh_token']
 check('Token reads own stock through dealer API',h.request(h.API+'/listings',headers={'Authorization':'Bearer '+access})[0]==200)
 h.request(h.API+'/oauth/revoke','POST',{'client_id':C['client_id'],'client_secret':C['client_secret'],'token':refresh},form=True)
 check('Token cannot access WordPress admin',h.request(h.BASE+'/wp-admin/index.php',headers={'Authorization':'Bearer '+access})[0] in [302,403])
 # Queue is private and never creates accounts or authorizations.
 limits();u2,_,_=url();q=h.opener();r=match(u2,q,'Unlisted Synthetic Motors')
 check('No match offers manual onboarding',r[0]==200 and 'Request access' in r[3])
 before=wp("count(get_users(['role'=>'dealership','fields'=>'ID']))")
 r=post(u2,q,'request',r[3],dealership_name='Unlisted Synthetic Motors',dealership_city='Paphos',contact_name='Synthetic Operator',contact='operator@example.invalid')
 check('Manual request returns a reference',r[0]==200 and 'Request received' in r[3] and 'Reference:' in r[3])
 check('Manual request creates no dealership account',wp("count(get_users(['role'=>'dealership','fields'=>'ID']))")==before)
 check('Request is stored in private review queue',wp("count(get_posts(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','numberposts'=>-1]))")>=1)
 pending=wp("count(get_posts(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','numberposts'=>-1]))")
 rr=match(u2,q,'Unlisted Synthetic Motors');rr=post(u2,q,'request',rr[3],dealership_name='Unlisted Synthetic Motors',dealership_city='Paphos',contact_name='Synthetic Operator',contact='operator@example.invalid')
 check('Repeated request is deduplicated without another account claim',rr[0]==200 and wp("count(get_posts(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','numberposts'=>-1]))")==pending)
 check('Request data has no public REST endpoint',h.request(h.BASE+'/wp-json/wp/v2/ag_dealer_onboard')[0]==404)
 limits();parallel=[]
 for _ in range(2):
  ps=h.opener();pu,_,_=url();pr=match(pu,ps,'Concurrent Synthetic Motors');parallel.append((pu,ps,pr[3]))
 before_pending=wp("count(get_posts(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','numberposts'=>-1]))")
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  submitted=list(pool.map(lambda args:post(args[0],args[1],'request',args[2],dealership_name='Concurrent Synthetic Motors',contact_name='Concurrent Operator',contact='concurrent@example.invalid'),parallel))
 check('Concurrent duplicate requests share one review record',all(x[0]==200 for x in submitted) and wp("count(get_posts(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','numberposts'=>-1]))") in [before_pending,before_pending+1])
 admin=h.login('admin');ar=h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-onboarding',session=admin)
 check('Administrator sees pending contact and matches',ar[0]==200 and 'operator@example.invalid' in ar[3])
 nonce=re.search(r'name="_wpnonce" value="([^"]+)"',ar[3])[1]
 ar=h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-onboarding','POST',{'_wpnonce':nonce,'operation':'search','dealership_name':'Legacy Road Motors'},session=admin,form=True)
 check('Admin matcher searches whole account catalogue including ineligible dealers',ar[0]==200 and 'Review account #' in ar[3] and 'Legacy Road Motors' in ar[3])
 ar=h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-onboarding',session=admin)
 nonce=re.search(r'name="_wpnonce" value="([^"]+)"',ar[3])[1];rid=re.search(r'name="request_id" value="(\d+)"',ar[3])[1]
 ar=h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-onboarding','POST',{'_wpnonce':nonce,'operation':'resolve','request_id':rid},session=admin,form=True)
 check('Admin can close a reviewed request without granting access',ar[0]==302 and wp("get_post_status("+rid+")")=='draft')
 x=h.opener();u3,_,_=url();xr=match(u3,x,'Legacy Road Motors')
 check('Public matching excludes ineligible accounts', 'name="candidate"' not in xr[3])
 check('Admin queue denied to anonymous visitors',h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-onboarding')[0] in [302,403])
 denied=h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-onboarding',session=s)
 check('Existing dealer cannot view private approval contacts',denied[0] in [302,403] and 'operator@example.invalid' not in denied[3])
 check('External standalone return is replaced with safe dashboard',wp("\\AutoAgora\\Dealer\\Connect::safeReturn('https://evil.example/')")==h.BASE+'/?autoagora_connect=workspace')
 check('Admin standalone return is replaced with safe dashboard',wp("\\AutoAgora\\Dealer\\Connect::safeReturn('"+h.BASE+"/wp-admin/')")==h.BASE+'/?autoagora_connect=workspace')
 check('Greek accents and legal suffix normalize consistently',wp("\\AutoAgora\\Dealer\\DealerDirectory::normalize('Άλφα Motors Ltd')")==wp("\\AutoAgora\\Dealer\\DealerDirectory::normalize('αλφα motors')"))
 check('Word order differences are matched',wp("\\AutoAgora\\Dealer\\DealerDirectory::score('Motors Cyprus','Cyprus Motors Ltd')")>=95)
 # A database rename must invalidate the cached catalogue in the next request.
 wp("update_user_meta("+str(A['other']['id'])+",'dealership_name','Renamed Island Auto')")
 check('Account-name changes invalidate cached matches',wp("\\AutoAgora\\Dealer\\DealerDirectory::search('Renamed Island Auto','',true)")[0]['name']=='Renamed Island Auto')
 wp("update_user_meta("+str(A['other']['id'])+",'dealership_name','Island Auto')")
 # Current query must not reuse a different OAuth flow's CSRF token.
 a,b=h.opener(),h.opener();ua,_,_=url();ub,_,_=url();ra=page(ua,a);rb=page(ub,a)
 check('CSRF is bound to original OAuth query',post(ub,a,'find',ra[3],dealership_name='Cyprus Motors')[0]==403)
 # Repeated requests cannot duplicate account claims, and brute-force attempts are bounded.
 limits();t=h.opener();ut,_,_=url();rr=page(ut,t);rr=post(ut,t,'direct',rr[3])
 for _ in range(6):rr=signin(ut,t,rr[3],password='incorrect-fixture-password')
 check('Repeated password attempts are rate limited',rr[0]==429 and rr[1].get('Retry-After')=='900')
 private_checks()
 (P/'results.json').write_text(json.dumps({'environment':h.BASE,'production_requests':0,'checks':RESULTS},indent=2))
 print(str(len(RESULTS))+' local onboarding checks passed. No production requests.')
def private_checks():
 limits();s=h.opener();u,v,state=url(account_type='private');r=page(u,s)
 check('Private Connect link opens branded seller sign-in without catalogue lookup',r[0]==200 and 'Private seller account' in r[3] and 'Find dealership' not in r[3])
 check('Private mode does not permit fuzzy searching personal accounts',post(u,s,'find',r[3],dealership_name='Private Fixture Seller')[0]==400)
 r=page(u,s);r=signin(u,s,r[3],key='owner');check('Dealer credentials cannot satisfy a private seller connection',r[0]==401)
 r=signin(u,s,r[3],key='private');check('Existing normal account signs in without role changes',r[0]==302 and r[1].get('Location')==u and wp('get_userdata('+str(A['private']['id'])+')->roles')==['client'])
 r=page(u,s);check('Private seller receives normal OAuth consent',r[0]==200 and 'Authorize connection' in r[3])
 mismatch=page(url(account_type='dealer')[0],s)
 check('Signed-in private account cannot consent as a dealer',mismatch[0]==200 and 'Use your dealership account' in mismatch[3] and 'Authorize connection' not in mismatch[3])
 nonce=re.search(r'name="_wpnonce" value="([^"]+)"',r[3])[1]
 r=h.request(u,'POST',{'_wpnonce':nonce,'decision':'approve'},session=s,form=True);params=urllib.parse.parse_qs(urllib.parse.urlparse(r[1].get('Location','')).query)
 check('Private consent preserves callback state',r[0]==302 and params.get('state')==[state] and 'code' in params)
 r=h.request(h.API+'/oauth/token','POST',{'grant_type':'authorization_code','client_id':C['client_id'],'client_secret':C['client_secret'],'redirect_uri':C['redirect_uri'],'code':params['code'][0],'code_verifier':v},form=True)
 check('Private account exchanges code and PKCE for tokens',r[0]==200 and bool(r[2].get('refresh_token')))
 tokens=r[2];auth={'Authorization':'Bearer '+tokens['access_token']}
 payload=h.sample('private-connect-'+secrets.token_hex(8));auth['Idempotency-Key']=secrets.token_hex(16)
 r=h.request(h.API+'/listings','POST',payload,headers=auth)
 check('Normal account can post a car through the existing API',r[0]==201 and bool(r[2].get('id')))
 listing=r[2]['id'];version=r[2]['version']
 check('Car belongs to private seller with pending moderation',wp("(int)get_post_field('post_author',"+str(listing)+")")==A['private']['id'] and r[2]['post_status']=='pending' and wp('get_userdata('+str(A['private']['id'])+')->roles')==['client'])
 foreign=wp("wp_insert_post(['post_type'=>'car','post_status'=>'pending','post_title'=>'Ownership fixture','post_author'=>"+str(A['owner']['id'])+"])")
 check('Private token cannot read another seller’s car',h.request(h.API+'/listings/'+str(foreign),headers=auth)[0]==404)
 altered={**payload,'external_stock_id':'invalid-type-'+secrets.token_hex(5),'account_type':'dealer'};auth['Idempotency-Key']=secrets.token_hex(16)
 check('Listing JSON cannot promote seller type',h.request(h.API+'/listings','POST',altered,headers=auth)[0]==422)
 auth['Idempotency-Key']=secrets.token_hex(16);auth['If-Match']=version
 r=h.request(h.API+'/listings/'+str(listing),'PATCH',{'price':12600},headers=auth)
 check('Private seller can update their own car',r[0]==200)
 auth['Idempotency-Key']=secrets.token_hex(16);auth['If-Match']=r[2]['version']
 r=h.request(h.API+'/listings/'+str(listing)+'/status','POST',{'status':'withdrawn'},headers=auth)
 check('Private seller can withdraw while retaining car data',r[0]==200 and r[2]['status']=='withdrawn' and wp('get_post('+str(listing)+')!==null'))
 # Keep the generated PHP unchanged; exercise each request's feature constant through a local-only DB fixture switch.
 try:
  wp("update_option('_ag_connect_fixture_private_enabled',false,false)")
  check('Private feature is disabled in the local runtime',wp('\\AutoAgora\\Dealer\\Support::privateSellersEnabled()') is False)
  disabled=h.request(h.API+'/listings',headers=auth)
  if disabled[0] not in [401,403]: (P/'disabled-feature-response.json').write_text(json.dumps({'status':disabled[0],'body':disabled[2]},indent=2))
  check('Private API token is denied when private-seller feature is disabled',disabled[0] in [401,403])
  check('Private authorize link is denied when feature is disabled',page(url(account_type='private')[0],h.opener())[0]==400)
 finally:wp("update_option('_ag_connect_fixture_private_enabled',true,false)")
 h.request(h.API+'/oauth/revoke','POST',{'client_id':C['client_id'],'client_secret':C['client_secret'],'token':tokens['refresh_token']},form=True)
 check('Private grant can be revoked normally',h.request(h.API+'/listings',headers=auth)[0]==401)
 check('Unsupported account_type is rejected',page(url(account_type='admin')[0],h.opener())[0]==400)
 check('Private individuals are absent from dealership fuzzy results',wp("\\AutoAgora\\Dealer\\DealerDirectory::search('Private Fixture Seller','',true)")==[])
 limits();q=h.opener();uq,_,_=url(account_type='private');qr=page(uq,q);qr=post(uq,q,'request-form',qr[3]);qr=post(uq,q,'request',qr[3],dealership_name='Synthetic Private Seller',contact='private@example.invalid')
 check('Private seller can request access without creating an account',qr[0]==200 and 'Request received' in qr[3])
 check('Private access request records its requested account type',wp("get_post_meta(get_posts(['post_type'=>'ag_dealer_onboard','post_status'=>'pending','numberposts'=>1,'orderby'=>'ID','order'=>'DESC'])[0]->ID,'_ag_onboard_account_type',true)")=='private')
if __name__=='__main__':main()
