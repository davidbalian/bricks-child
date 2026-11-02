#!/usr/bin/env python3
"""Real login/signup/OAuth/API checks against disposable loopback WordPress only."""
import base64, concurrent.futures, hashlib, html, json, re, secrets, subprocess, urllib.parse
from pathlib import Path
import http_checks as h
P=h.PRIVATE/'connect-fixture';A=json.loads((P/'accounts.json').read_text());C=json.loads((P/'client.json').read_text());RESULTS=[]
PHONES=json.loads((P/'signup-phones.json').read_text())['codes'];CREATED=[]
def check(name,ok):
 RESULTS.append({'check':name,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
 if not ok:raise AssertionError(name)
def wp(expr):
 out=P/'data.json'
 code="if(wp_get_environment_type()!=='local'||wp_parse_url(home_url(),PHP_URL_HOST)!=='127.0.0.1'){throw new Exception('Local only');}file_put_contents("+json.dumps(str(out))+",wp_json_encode("+expr+"));"
 r=subprocess.run(['wp','--path='+str(h.ROOT/'.staging/wp'),'--user=stage-admin','eval',code],capture_output=True,text=True)
 if r.returncode:(P/'cli-error.log').write_text(r.stdout+r.stderr);raise AssertionError('Local data check failed')
 return json.loads(out.read_text())
def url(**overrides):
 verifier=secrets.token_urlsafe(48);state=secrets.token_urlsafe(24)
 q={'autoagora_connect':'authorize','response_type':'code','client_id':C['client_id'],'redirect_uri':C['redirect_uri'],'scope':'stock:read stock:write stock:status catalog:read showroom:write','state':state,'code_challenge':base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode(),'code_challenge_method':'S256',**overrides}
 return h.BASE+'/?'+urllib.parse.urlencode(q),verifier,state
def page(u,s):return h.request(u,session=s)
def field(raw,key):
 m=re.search(r'name="'+re.escape(key)+r'"[^>]*value="([^"]*)"',raw)
 if not m:raise AssertionError('Missing form control '+key)
 return html.unescape(m[1])
def post(u,s,op,raw=None,**values):
 raw=raw if raw is not None else page(u,s)[3]
 return h.request(u,'POST',{'connect_csrf':field(raw,'connect_csrf'),'connect_action':op,**values},session=s,form=True)
def move(u,s,op,**values):
 r=post(u,s,op,**values)
 if r[0]==302:
  assert html.unescape(r[1]['Location'])==u,'Unexpected signup return'
  return page(u,s)
 return r
def new_phone():
 for phone in PHONES:
  if not wp('username_exists(sanitize_user('+json.dumps(phone)+',true))'):
   return phone
 raise AssertionError('Synthetic signup numbers exhausted')
def signup_start(hint='private'):
 h.limits();u,v,state=url(account_type=hint);s=h.opener();r=move(u,s,'signup');return u,v,state,s,r
def send(u,s,phone,t='private'):
 return move(u,s,'send-code',signup_phone=phone,signup_type=t)
def verify(u,s,phone,code=None):return move(u,s,'verify-code',signup_code=code or PHONES[phone])
def details(u,s,t='private',raw=None,**extra):
 password=secrets.token_urlsafe(24);data={'first_name':'Synthetic','last_name':'Seller','company_name':'Synthetic Motors','signup_password':password,'signup_confirm':password,**extra}
 r=post(u,s,'create-account',raw=raw,**data)
 return r,password

def authorize(u,s,verifier,state,approve=True,other_cookie=None):
 r=page(u,s);check('Verified account reaches consent',r[0]==200 and 'Authorize connection' in r[3])
 r=h.request(u,'POST',{'_wpnonce':field(r[3],'_wpnonce'),'decision':'approve' if approve else 'deny'},session=s,form=True)
 params=urllib.parse.parse_qs(urllib.parse.urlparse(r[1].get('Location','')).query)
 check('Callback keeps original state',r[0]==302 and params.get('state')==[state])
 if not approve:check('Denial returns OAuth access_denied',params.get('error')==['access_denied']);return None
 check('Callback carries a code, not account identity',bool(params.get('code')) and 'account' not in params and 'access_token' not in params)
 body={'grant_type':'authorization_code','client_id':C['client_id'],'client_secret':C['client_secret'],'redirect_uri':C['redirect_uri'],'code':params['code'][0],'code_verifier':verifier,'autoagora_id':str(A['other']['id'])}
 r=h.request(h.API+'/oauth/token','POST',body,session=other_cookie,form=True)
 check('S256 code exchange succeeds',r[0]==200 and bool(r[2].get('refresh_token')))
 check('Code remains single-use',h.request(h.API+'/oauth/token','POST',body,form=True)[0]==400)
 check('Identity contains only numeric ID and name',set(r[2].get('account',{}))=={'id','name'} and isinstance(r[2]['account']['id'],int) and isinstance(r[2]['account']['name'],str))
 check('Token responses stay private', 'no-store' in r[1].get('Cache-Control',''))
 return r[2]
def existing(key,hint=None):
 h.limits();u,v,state=url(**({'account_type':hint} if hint is not None else {}));s=h.opener();a=A[key]
 r=post(u,s,'signin',dealer_login=a['username'],dealer_password=a['password'])
 check('Existing '+key+' logs in with '+str(hint)+' hint',r[0]==302 and html.unescape(r[1].get('Location',''))==u)
 return u,v,state,s

def main():
 h.limits();u,v,state=url(account_type='dealer');s=h.opener();r=page(u,s)
 check('Connect opens branded login immediately',r[0]==200 and 'dealer_password' in r[3] and 'autoagora-logo.png' in r[3])
 check('No public matching or other account names',all(x not in r[3] for x in ['Find your dealership','name="candidate"','Cyprus Motors','Island Auto','dealership_name']))
 check('Page loads only scoped component and button styles','button-kit/buttons.css' in r[3] and '/style.css' not in r[3])
 check('Login stays no-store and noindex','no-store' in r[1].get('Cache-Control','') and 'noindex' in r[1].get('X-Robots-Tag',''))
 check('Signup is available within validated OAuth','Create an AutoAgora account' in r[3])
 cookie=next(c for handler in s.handlers if hasattr(handler,'cookiejar') for c in handler.cookiejar if c.name=='ag_connect')
 check('CSRF cookie is HttpOnly and SameSite Lax',cookie.has_nonstandard_attr('HttpOnly') and cookie.get_nonstandard_attr('SameSite')=='Lax')
 check('Missing CSRF is rejected',h.request(u,'POST',{'connect_action':'signin'},session=s,form=True)[0]==403)
 for op in ['find','choose','request','request-form']:
  check('Retired public '+op+' action is unavailable',post(u,s,op,dealership_name='Cyprus Motors',candidate='f'*32)[0]==400)
 for label,override in [('unknown client',{'client_id':'unknown'}),('unregistered callback',{'redirect_uri':'https://evil.example/callback'}),('plain PKCE',{'code_challenge_method':'plain'}),('invalid scope',{'scope':'stock:read admin:all'}),('unsupported type',{'account_type':'administrator'}),('array type',{'account_type':['dealer']})]:
  uu,_,_=url(**override);check(label+' is rejected before signup',page(uu,h.opener())[0]==400)
 other,_,_=url(account_type='dealer');check('CSRF remains bound to exact OAuth request',post(other,s,'signup',r[3])[0]==403)
 direct=h.BASE+'/?autoagora_connect=dealer-login';rr=page(direct,h.opener());check('Standalone login cannot create accounts','Create an AutoAgora account' not in rr[3])
 for unsafe in ['https://evil.example/','/wp-admin/',h.BASE+'/wp-admin/',h.BASE+'/?rest_route=/wp/v2/users',h.BASE+'/?autoagora_connect=dealer-login']:
  check('Unsafe standalone return is replaced',wp('\\AutoAgora\\Dealer\\Connect::safeReturn('+json.dumps(unsafe)+')')==h.BASE+'/?autoagora_connect=workspace')
 r=post(u,s,'signin',dealer_login=A['owner']['username'],dealer_password='wrong-password')
 check('Wrong password remains on branded login',r[0]==401 and 'Sign-in failed' in r[3] and 'Location' not in r[1])
 for key,hint,role in [('owner','private','dealership'),('private','dealer','client'),('private',None,'client')]:
  uu,vv,ss,session=existing(key,hint);tok=authorize(uu,session,vv,ss)
  check('Existing type is unchanged by URL',wp('get_userdata('+str(A[key]['id'])+')->roles')==[role])
  check('Identity comes from grant, ignoring supplied account ID',tok['account']['id']==A[key]['id'])
  if key=='owner':owner_session=session
 # Switching account preserves the entire authorization request and clears the session.
 rr=page(uu,session);rr=h.request(uu,'POST',{'_wpnonce':field(rr[3],'_wpnonce'),'decision':'switch-account'},session=session,form=True)
 check('Different-account action returns to same OAuth request',rr[0]==302 and html.unescape(rr[1]['Location'])==uu and 'dealer_password' in page(uu,session)[3])
 uu,vv,ss,session=existing('owner','private');authorize(uu,session,vv,ss,False)
 signup_security()
 created_private=complete_signup('private',owner_session)
 complete_signup('dealer',owner_session,foreign=created_private)
 concurrent_signup()
 # Internal operator search is retained, including legacy ACF data shapes.
 for city in [{'label':'Nicosia','value':'nicosia'},['Nicosia'],{'unexpected':['nested']}]:
  result=wp("(static function(){update_user_meta("+str(A['legacy']['id'])+",'car_city',json_decode("+json.dumps(json.dumps(city))+",true));delete_transient('ag_dealer_directory_v2');set_error_handler(static function($n,$m,$file){if(str_contains($file,'DealerDirectory.php'))throw new \\RuntimeException($m);});$r=\\AutoAgora\\Dealer\\DealerDirectory::search('Cyprus Motors','',true);restore_error_handler();return count($r)>0;})()")
  check('Internal search handles legacy city shape without warnings',result)
 # Failed login throttling is not bypassed by changing signup hint.
 h.limits();s=h.opener();uu,_,_=url();raw=page(uu,s)[3]
 for _ in range(6):rr=post(uu,s,'signin',raw,dealer_login=A['other']['username'],dealer_password='incorrect')
 check('Repeated password attempts are throttled',rr[0]==429 and rr[1].get('Retry-After')=='900')
 (P/'results.json').write_text(json.dumps({'environment':h.BASE,'production_requests':0,'checks':RESULTS},indent=2));print(str(len(RESULTS))+' local contract checks passed. No production requests.')

def signup_security():
 u,v,state,s,r=signup_start();phone=new_phone()
 check('Signup hint preselects private without granting a role',bool(re.search(r'value="private"\s+selected=',r[3])))
 check('Signup creates no account before verification',not wp('username_exists(sanitize_user('+json.dumps(phone)+',true))'))
 check('Cannot finish without OTP',post(u,s,'create-account',first_name='Fake',last_name='Seller',signup_password='password-test123',signup_confirm='password-test123')[0]==403)
 check('Unsupported signup role is rejected',send(u,s,phone,'administrator')[0]==422)
 check('Unapproved test number never reaches an SMS provider',send(u,s,'+35799999999')[0]==422)
 check('Malformed phone is rejected',send(u,s,'hello '+phone)[0]==422)
 h.limits();r=send(u,s,phone);check('Approved test number reaches verification step',r[0]==200 and 'signup_code' in r[3])
 # Knowing a valid code in another browser/flow does not create proof in this one.
 t=h.opener();uu,_,_=url(account_type='private');page(uu,t)
 check('Code cannot be checked in a different flow',post(uu,t,'verify-code',signup_code=PHONES[phone])[0]==403)
 for _ in range(5):rr=verify(u,s,phone,'000000' if PHONES[phone]!='000000' else '111111')
 rr=verify(u,s,phone);check('Five bad checks lock the challenge',rr[0]==429)
 # Expiring or restarting the browser flow cannot retain old verified proof.
 h.limits();r=send(u,s,phone);r=verify(u,s,phone);check('Correct code unlocks details',r[0]==200 and 'signup_password' in r[3])
 check('Code cannot be reused after verification',verify(u,s,phone)[0]==429)
 result=wp("(static function(){global $wpdb;$rows=$wpdb->get_results(\"SELECT option_name,option_value FROM {$wpdb->options} WHERE option_name LIKE '_transient_ag_connect_%'\");foreach($rows as$r){$x=maybe_unserialize($r->option_value);if(is_array($x)&&isset($x['signup']['verified_until'])){$x['signup']['verified_until']=time()-1;update_option($r->option_name,$x,false);}}return true;})()")
 rr,_=details(u,s);check('Expired proof cannot create an account',result and rr[0]==403)
 r=move(u,s,'signin-form');check('Returning to login drops signup state',r[0]==200 and 'dealer_password' in r[3])
 # A duplicate phone is disclosed only after verifying it, and never claims or changes the existing user.
 h.limits();u,v,state,s,r=signup_start('dealer');phone=new_phone();old=wp('get_user_meta('+str(A['owner']['id'])+',\'phone_number\',true)')
 try:
  wp('update_user_meta('+str(A['owner']['id'])+',\'phone_number\','+json.dumps(phone)+')')
  r=send(u,s,phone,'dealer');check('Signup does not enumerate accounts before OTP',r[0]==200 and 'already has' not in r[3])
  r=verify(u,s,phone);rr,_=details(u,s,'dealer');check('Duplicate phone requires existing password login',rr[0]==422 and 'already has an AutoAgora account' in rr[3])
  check('Duplicate signup cannot claim or change existing account',wp('get_userdata('+str(A['owner']['id'])+')->roles')==['dealership'])
 finally:wp('update_user_meta('+str(A['owner']['id'])+',\'phone_number\','+json.dumps(old)+')')
 # Feature-off removes UI and rejects direct mutations.
 try:
  wp("update_option('_ag_connect_fixture_signup_enabled',false,false)");rr=page(u,s)
  check('Signup feature flag hides creation UI','Create an AutoAgora account' not in rr[3]);check('Disabled signup rejects direct POST',post(u,s,'send-code',signup_phone=phone,signup_type='private')[0]==400)
 finally:wp("update_option('_ag_connect_fixture_signup_enabled',true,false)")

def complete_signup(t,owner_session,foreign=None):
 u,v,state,s,r=signup_start(t);phone=new_phone();r=send(u,s,phone,t);r=verify(u,s,phone)
 bad=post(u,s,'create-account',first_name='Synthetic',last_name='Seller',company_name='Synthetic Motors',signup_password='short',signup_confirm='different')
 check(t+' requires a strong matching password',bad[0]==422 and 'value="short"' not in bad[3])
 if t=='dealer':
  with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
   attempts=list(pool.map(lambda _:details(u,s,t,raw=bad[3],role='administrator',account_type='private',signup_phone='+35799999999'),range(2)))
  check('Concurrent final submission creates an account only once',sorted(x[0][0] for x in attempts)==[302,403])
  r,password=next(x for x in attempts if x[0][0]==302)
 else:r,password=details(u,s,t,role='administrator',account_type='dealer',signup_phone='+35799999999')
 check(t+' verified signup resumes exact OAuth request',r[0]==302 and html.unescape(r[1].get('Location',''))==u)
 uid=wp('(int)username_exists(sanitize_user('+json.dumps(phone)+',true))');CREATED.append(uid)
 check(t+' account has only the selected supported role',wp('get_userdata('+str(uid)+')->roles')==['dealership' if t=='dealer' else 'client'] and not wp('user_can('+str(uid)+",'manage_options')"))
 check(t+' account uses server-verified phone',wp('get_user_meta('+str(uid)+",'phone_number',true)")==phone)
 check(t+' signup grants no application access before consent',wp('\\AutoAgora\\Dealer\\Grants::active('+json.dumps(C['client_id'])+','+str(uid)+')') is None)
 old_browser=h.opener();# A replay without this browser's proof cannot create another account.
 check(t+' signup replay in another browser is rejected',h.request(u,'POST',{'connect_action':'create-account','connect_csrf':field(bad[3],'connect_csrf')},session=old_browser,form=True)[0]==403)
 tokens=authorize(u,s,v,state,other_cookie=owner_session);check(t+' token identity is the newly verified account',tokens['account']['id']==uid and tokens['account']['name']==('Synthetic Motors' if t=='dealer' else 'Synthetic Seller'))
 original_refresh=tokens['refresh_token'];newname='Renamed '+t+' '+secrets.token_hex(3);wp('wp_update_user([\'ID\'=>'+str(uid)+",'display_name'=>"+json.dumps(newname)+'])')
 refresh={'grant_type':'refresh_token','client_id':C['client_id'],'client_secret':C['client_secret'],'refresh_token':original_refresh,'autoagora_id':str(A['other']['id'])}
 rr=h.request(h.API+'/oauth/token','POST',refresh,session=owner_session,form=True)
 check(t+' refresh returns current grant owner and updated name',rr[0]==200 and rr[2]['account']=={'id':uid,'name':newname});tokens=rr[2]
 check(t+' refresh rotates token',tokens['refresh_token']!=original_refresh);check(t+' old refresh cannot replay',h.request(h.API+'/oauth/token','POST',refresh,form=True)[0]==400)
 h.limits();payload=h.sample('signup-'+secrets.token_hex(8));rr=h.api(tokens,'/listings','POST',payload)
 check(t+' can submit an actual car with photos',rr[0]==201 and len(h.api(tokens,'/listings/'+str(rr[2]['id']))[2]['photos'])==2)
 lid=rr[2]['id'];check(t+' owns its submitted pending car',rr[2]['post_status']=='pending' and wp("(int)get_post_field('post_author',"+str(lid)+')')==uid)
 if foreign:check('New dealer cannot access another new seller’s car',h.api(tokens,'/listings/'+str(foreign))[0]==404)
 altered={**payload,'external_stock_id':'bad-'+secrets.token_hex(4),'autoagora_id':A['other']['id']};check('Listing body cannot select another account',h.api(tokens,'/listings','POST',altered)[0]==422)
 check('There is no partner account-type-change endpoint',h.api(tokens,'/account/type','POST',{'account_type':'dealer'})[0]==404)
 rr=h.api(tokens,'/listings/'+str(lid),'PATCH',{'price':12600},rr[2]['version']);check(t+' can update own car',rr[0]==200)
 rr=h.api(tokens,'/listings/'+str(lid)+'/status','POST',{'status':'withdrawn'},rr[2]['version']);check(t+' withdrawal retains car and photos',rr[0]==200 and rr[2]['status']=='withdrawn' and len(h.api(tokens,'/listings/'+str(lid))[2]['photos'])==2)
 if t=='private':
  try:
   wp('wp_update_user([\'ID\'=>'+str(uid)+",'role'=>'dealership'])");check('AutoAgora-managed type change retains token ownership',h.api(tokens,'/listings/'+str(lid))[0]==200)
  finally:wp('wp_update_user([\'ID\'=>'+str(uid)+",'role'=>'client'])")
  try:
   wp("update_option('_ag_connect_fixture_private_enabled',false,false)");check('Private token is denied when private support is disabled',h.api(tokens,'/listings')[0] in (401,403))
   check('Private signup hint does not block an existing dealer login',page(url(account_type='private')[0],h.opener())[0]==200)
  finally:wp("update_option('_ag_connect_fixture_private_enabled',true,false)")
 # Failed token requests must not leak account metadata.
 check('Wrong client secret returns no account identity', 'account' not in h.request(h.API+'/oauth/token','POST',{**refresh,'client_secret':'wrong'},form=True)[2])
 h.api(tokens,'/connection','DELETE');check(t+' disconnect immediately invalidates token',h.api(tokens,'/listings')[0]==401)
 fresh=h.opener();relogin,_,_=url(account_type='dealer' if t=='private' else 'private');rr=post(relogin,fresh,'signin',dealer_login=phone,dealer_password=password)
 check(t+' new account can later sign in with its verified phone',rr[0]==302 and html.unescape(rr[1].get('Location',''))==relogin)
 (P/('created-'+t+'.json')).write_text(json.dumps({'id':uid,'username':phone,'password':password,'listing_id':lid}));(P/('created-'+t+'.json')).chmod(0o600)
 return lid
def concurrent_signup():
 h.limits();phone=new_phone();flows=[]
 for t in ['private','dealer']:
  u,v,state=url(account_type=t);s=h.opener();move(u,s,'signup');send(u,s,phone,t);r=verify(u,s,phone);flows.append((u,s,t,r[3]))
 with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
  results=list(pool.map(lambda f:details(f[0],f[1],f[2],raw=f[3]),flows))
 check('Separate verified flows cannot duplicate the same phone',sorted(x[0][0] for x in results)==[302,422])
 count=wp("count(get_users(['meta_key'=>'phone_number','meta_value'=>"+json.dumps(phone)+"]))")
 check('Phone concurrency leaves exactly one supported account',count==1)

if __name__=='__main__':main()
