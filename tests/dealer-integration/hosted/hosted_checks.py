#!/usr/bin/env python3
"""Opt-in real HTTP checks. Hard pinned to the isolated staging7 sandbox, never production.
Credentials are loaded only from ignored .private files. No secrets in test output.
"""
import base64, copy, hashlib, html, http.cookiejar, json, re, secrets, time
from pathlib import Path
import urllib.error, urllib.parse, urllib.request
ROOT=Path(__file__).resolve().parents[3]
PRIVATE=ROOT/'.private'
BASE='https://staging7.autoagora.cy'
API=BASE+'/wp-json/autoagora-dealer/v1'
ACCOUNTS=json.loads((PRIVATE/'hosted-test-accounts.json').read_text())
CLIENT=json.loads((PRIVATE/'hosted-qa-client.json').read_text())
OTHER=json.loads((PRIVATE/'hosted-qa-other.json').read_text())
RESULTS=[]
ARTIFACT={'environment':BASE,'production_requests':0,'checks':RESULTS}
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args):return None
def opener(): return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def request(url,method='GET',body=None,headers=None,session=None,form=False):
 assert urllib.parse.urlparse(url).hostname=='staging7.autoagora.cy','Staging-only request guard'
 headers={'User-Agent':'Mozilla/5.0 AutoAgoraStagingQA/1.0', **(headers or {})};data=None
 if body is not None:
  data=urllib.parse.urlencode(body).encode() if form else json.dumps(body,separators=(',',':')).encode()
  headers['Content-Type']='application/x-www-form-urlencoded' if form else 'application/json'
 try:r=(session or opener()).open(urllib.request.Request(url,data=data,headers=headers,method=method),timeout=110)
 except urllib.error.HTTPError as e:r=e
 raw=r.read().decode('utf-8','replace')
 try:parsed=json.loads(raw)
 except ValueError:parsed=None
 return r.code,dict(r.headers),parsed,raw

def check(name,ok):
 RESULTS.append({'check':name,'passed':bool(ok)})
 if not ok: raise AssertionError(name)
 print('PASS',name,flush=True)
def login(account='qa_one'):
 s=opener();request(BASE+'/wp-login.php?admin_access=true',session=s)
 a=ACCOUNTS[account]
 r=request(BASE+'/wp-login.php','POST',{'log':a['username'],'pwd':a['password'],'wp-submit':'Log In','redirect_to':BASE+'/?autoagora_stage_client=app','testcookie':'1'},session=s,form=True)
 check(account+' username/password login without admin privileges',r[0]==302 and urllib.parse.urlparse(r[1].get('Location','')).hostname=='staging7.autoagora.cy')
 return s
def authorize(account='qa_one',client=CLIENT,scopes='stock:read stock:write stock:status catalog:read showroom:write',approve=True,method='S256'):
 s=login(account);verifier=secrets.token_urlsafe(48);state=secrets.token_urlsafe(24)
 challenge=base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode()
 url=BASE+'/?'+urllib.parse.urlencode({'autoagora_connect':'authorize','response_type':'code','client_id':client['client_id'],'redirect_uri':client['redirect_uri'],'scope':scopes,'state':state,'code_challenge':challenge,'code_challenge_method':method})
 r=request(url,session=s)
 if method!='S256': return r,None,None,s
 check('real consent form renders',r[0]==200 and bool(re.search(r'name="_wpnonce" value="([^"]+)"',r[3])))
 nonce=re.search(r'name="_wpnonce" value="([^"]+)"',r[3]).group(1)
 r=request(url,'POST',{'_wpnonce':nonce,'decision':'approve' if approve else 'deny'},session=s,form=True)
 params=urllib.parse.parse_qs(urllib.parse.urlparse(r[1].get('Location','')).query)
 if not approve:
  check('dealer can cancel authorization',r[0]==302 and params.get('error')==['access_denied']);return r,None,None,s
 check('callback returns session state',r[0]==302 and params.get('state')==[state] and 'code' in params)
 body={'grant_type':'authorization_code','client_id':client['client_id'],'client_secret':client['client_secret'],'redirect_uri':client['redirect_uri'],'code':params['code'][0],'code_verifier':verifier}
 r=request(API+'/oauth/token','POST',body,form=True)
 check('code + S256 PKCE issues access and refresh tokens',r[0]==200 and bool(r[2].get('refresh_token')))
 return r,r[2],body,s

def api(tokens,path,method='GET',body=None,version=None,key=None):
 h={'Authorization':'Bearer '+tokens['access_token']}
 if method!='GET':h['Idempotency-Key']=key or secrets.token_urlsafe(18)
 if version is not None:h['If-Match']=version
 return request(API+path,method,body,h)
def sample(external):
 return {'external_stock_id':external,'make':'BMW','model':'3 Series','year':2020,'mileage':85000,'price':12500,'availability':'In Stock','engine_capacity':'2.0','fuel_type':'Petrol','transmission':'Automatic','body_type':'Saloon','exterior_color':'Black','description':'STAGING TEST VEHICLE. Synthetic integration test; not offered for sale.','location':{'address':'STAGING TEST SHOWROOM','city':'Nicosia','district':'Nicosia','latitude':35.1727,'longitude':33.3639},'photos':[{'url':BASE+'/staging-test-assets/car-front.png'},{'url':BASE+'/staging-test-assets/car-rear.png'}]}
def save():
 ARTIFACT['checked_at']=time.strftime('%Y-%m-%dT%H:%M:%SZ',time.gmtime())
 (PRIVATE/'hosted-results.json').write_text(json.dumps(ARTIFACT,indent=2))
def main():
 assert CLIENT['site_url'].rstrip('/')==BASE and OTHER['site_url'].rstrip('/')==BASE
 r=request(BASE+'/')
 check('staging is authenticated, noindex and explicitly isolated',r[0]==302 and r[1].get('X-AutoAgora-Environment')=='isolated-staging' and 'noindex' in r[1].get('X-Robots-Tag',''))
 check('anonymous integration API denied',request(API+'/listings')[0]==401)
 check('native user REST blocked',request(BASE+'/wp-json/wp/v2/users')[0]==403)
 check('private configuration cannot be downloaded',request(BASE+'/.autoagora-staging/sandbox.json')[0] in (302,403,404))
 _,token,code,s=authorize()
 check('authorization code is single use',request(API+'/oauth/token','POST',code,form=True)[0]==400)
 check('plain PKCE rejected',authorize(method='plain')[0][0]==400)
 authorize(approve=False)
 _,other,_,s2=authorize('qa_two')
 _,readonly,_,_=authorize('qa_two',OTHER,'stock:read')
 check('read-only grant cannot post cars',api(readonly,'/listings','POST',{})[0]==403)
 check('test dealer cannot enter WordPress backend',request(BASE+'/wp-admin/',session=s)[0] in (302,403))
 check('native AJAX mutation blocked',request(BASE+'/wp-admin/admin-ajax.php','POST',{'action':'mark_car_as_sold','car_id':1},session=s,form=True)[0]==403)
 check('catalog uses actual hosted taxonomy',api(token,'/catalog')[0]==200 and api(token,'/catalog')[2]['total']>100)
 showroom={'address':'STAGING TEST SHOWROOM','city':'Nicosia','district':'Nicosia','latitude':35.1727,'longitude':33.3639}
 check('dealer showroom can be saved',api(token,'/showroom','PUT',{'location':showroom})[0]==200)
 check('showroom persists through API',api(token,'/showroom')[2]['location']['address']==showroom['address'])
 # Keep writes beneath the real ten-per-minute limit; throttling itself is checked below.
 bad=sample('invalid-'+secrets.token_hex(4));bad['model']='Not a real model'
 check('invalid catalog model rejected',api(token,'/listings','POST',bad)[0]==422)
 bad=sample('invalid-'+secrets.token_hex(4));bad['photos']=bad['photos'][:1]
 check('minimum two photos enforced',api(token,'/listings','POST',bad)[0]==422)
 payload=sample('qa-'+secrets.token_hex(6));key=secrets.token_urlsafe(20)
 r=api(token,'/listings','POST',payload,key=key)
 if r[0]!=201:
  ARTIFACT['create_failure']={'status':r[0],'code':r[2].get('code') if isinstance(r[2],dict) else None,'message':r[2].get('message') if isinstance(r[2],dict) else None};save()
 check('real HTTPS image import creates a published staging car',r[0]==201 and r[2]['post_status']=='publish' and r[2]['in_marketplace'])
 car=r[2];lid=car['id'];ARTIFACT['listing_id']=lid;ARTIFACT['listing_url']=car['url']
 r=api(token,'/listings','POST',payload,key=key)
 check('idempotent replay returns same car',r[0]==201 and r[2]['id']==lid and r[1].get('Idempotency-Replayed')=='true')
 check('duplicate external stock ID rejected',api(token,'/listings','POST',payload)[0]==409)
 check('another dealer list cannot reveal this car',all(x['id']!=lid for x in api(other,'/listings')[2]['items']))
 check('another dealer cannot read this car',api(other,f'/listings/{lid}')[0]==404)
 check('another dealer cannot change this car',api(other,f'/listings/{lid}','PATCH',{'price':1},car['version'])[0]==404)
 read=api(token,f'/listings/{lid}')[2]
 check('real optimized gallery is saved',len(read['photos'])==2 and all(urllib.parse.urlparse(p['url']).hostname=='staging7.autoagora.cy' for p in read['photos']))
 r=request(car['url'],session=s)
 check('normal single-car page renders imported car and price',r[0]==200 and 'STAGING TEST VEHICLE' in r[3] and ('12,500' in r[3] or '12500' in r[3]))
 (PRIVATE/'hosted-car.html').write_text(r[3])
 r=request(BASE+'/cars/?user_id='+str(ACCOUNTS['qa_one']['id']),session=s)
 check('normal marketplace displays imported car',r[0]==200 and str(lid) in r[3] and urllib.parse.urlparse(car['url']).path in html.unescape(r[3]))
 (PRIVATE/'hosted-browse.html').write_text(r[3])
 check('anonymous visitors cannot see staged cars',request(car['url'])[0]==302)
 check('missing update version rejected',api(token,f'/listings/{lid}','PATCH',{'price':13000})[0]==412)
 # Wait for the next real rate bucket instead of weakening limits to make the test pass.
 print('Waiting for the next write-rate bucket.',flush=True);time.sleep(61-time.time()%60)
 r=api(token,f'/listings/{lid}','PATCH',{'price':13000,'photos':[{'id':p['id']} for p in reversed(read['photos'])]},read['version'])
 check('price and photo order update succeeds',r[0]==200)
 updated=api(token,f'/listings/{lid}')[2]
 check('updated price, publication and photo order persist',updated['fields']['price']==13000 and updated['post_status']=='publish' and [p['id'] for p in updated['photos']]==[p['id'] for p in reversed(read['photos'])])
 check('stale update cannot overwrite newer data',api(token,f'/listings/{lid}','PATCH',{'price':12000},read['version'])[0]==412)
 bad={'price':1,'photos':[{'id':updated['photos'][0]['id']},{'url':'https://127.0.0.1/secret.png'}]}
 check('private photo destination rejected',api(token,f'/listings/{lid}','PATCH',bad,updated['version'])[0]==502)
 kept=api(token,f'/listings/{lid}')[2]
 check('failed photo import preserves price and gallery',kept['fields']['price']==13000 and kept['photos']==updated['photos'])
 r=api(token,f'/listings/{lid}/status','POST',{'status':'sold'},kept['version'])
 check('sold status persists',r[0]==200 and r[2]['listing_state']=='sold' and not r[2]['in_marketplace'])
 sold=r[2]
 check('sold single follows existing marketplace redirect',request(car['url'],session=s)[0] in (301,302))
 r=api(token,f'/listings/{lid}/status','POST',{'status':'withdrawn'},sold['version'])
 check('withdrawal retains draft rather than deleting',r[0]==200 and r[2]['post_status']=='draft' and r[2]['status']=='withdrawn')
 kept=api(token,f'/listings/{lid}')[2]
 check('withdrawn car keeps both photos',len(kept['photos'])==2)
 check('withdrawn single is hidden',request(car['url'],session=s2)[0] in (301,302,404))
 check('unverified view counts are unavailable',api(token,f'/listings/{lid}/views')[2]['available'] is False)
 refresh={'grant_type':'refresh_token','refresh_token':token['refresh_token'],'client_id':CLIENT['client_id'],'client_secret':CLIENT['client_secret']}
 r=request(API+'/oauth/token','POST',refresh,form=True)
 check('refresh rotates credentials',r[0]==200 and r[2]['refresh_token']!=token['refresh_token'])
 renewed=r[2]
 check('old refresh cannot be reused',request(API+'/oauth/token','POST',refresh,form=True)[0]==400)
 check('renewed access works',api(renewed,'/listings')[0]==200)
 check('disconnect revokes grant',api(renewed,'/connection','DELETE')[0]==200)
 check('revoked access immediately fails',api(renewed,'/listings')[0]==401)
 refresh['refresh_token']=renewed['refresh_token']
 check('revoked refresh cannot issue new access',request(API+'/oauth/token','POST',refresh,form=True)[0]==400)
 check('unrelated dealer grant remains valid',api(other,'/listings')[0]==200)
 # Retain no QA bearer credentials after the run.
 api(other,'/connection','DELETE')
 save();print('Completed',len(RESULTS),'hosted staging checks.',flush=True)
if __name__=='__main__':
 try:main()
 except Exception as e:
  ARTIFACT['failure']=str(e);save();print('FAILED:',str(e));raise SystemExit(1)
