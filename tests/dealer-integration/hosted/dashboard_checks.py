#!/usr/bin/env python3
"""HTTP dashboard acceptance and ownership checks. Pinned to loopback or staging7 only.
Run local fixture setup first for --local. Hosted credentials stay in ignored .private files.
"""
from pathlib import Path
from html.parser import HTMLParser
import copy,http.cookiejar,json,re,secrets,sys,urllib.request,urllib.error,urllib.parse
ROOT=Path(__file__).resolve().parents[3]; PRIVATE=ROOT/'.private'
LOCAL=sys.argv[1:]==['--local']
BASE='http://127.0.0.1:8899' if LOCAL else 'https://staging7.autoagora.cy'
URL=BASE+'/?autoagora_connect=workspace'
if LOCAL:
 ACCOUNTS=json.loads((PRIVATE/'dashboard-fixture/accounts.json').read_text());ACCOUNT=ACCOUNTS['one'];OTHER=ACCOUNTS['two'];FOREIGN_ID=0
else:
 ACCOUNTS=json.loads((PRIVATE/'hosted-test-accounts.json').read_text());ACCOUNT=ACCOUNTS['alekos'];OTHER=ACCOUNTS['qa_one'];FOREIGN_ID=json.loads((PRIVATE/'hosted-results.json').read_text())['listing_id']
CLIENT='dashboard-fixture' if LOCAL else 'stage-demo';RESULTS=[]
class NoRedirect(urllib.request.HTTPRedirectHandler):
 def redirect_request(self,*args):return None
def session():return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()),NoRedirect())
def request(url,method='GET',body=None,s=None):
 assert urllib.parse.urlsplit(url).netloc==urllib.parse.urlsplit(BASE).netloc,'Forbidden request host'
 data=urllib.parse.urlencode(body).encode() if body is not None else None
 headers={'User-Agent':'Mozilla/5.0 AutoAgoraDashboardQA/1.0'}
 if data is not None:headers['Content-Type']='application/x-www-form-urlencoded'
 try:r=(s or session()).open(urllib.request.Request(url,data=data,headers=headers,method=method),timeout=100)
 except urllib.error.HTTPError as e:r=e
 return r.code,dict(r.headers),r.read().decode('utf8','replace')
def check(name,ok):
 RESULTS.append({'check':name,'passed':bool(ok)});print(('PASS ' if ok else 'FAIL ')+name,flush=True)
 if not ok:raise AssertionError(name)
class Forms(HTMLParser):
 def __init__(self,raw):super().__init__();self.forms=[];self.current=None;self.textarea=None;self.select=None;self.feed(raw)
 def handle_starttag(self,tag,attrs):
  a=dict(attrs)
  if tag=='form':self.current={};self.forms.append(self.current)
  if self.current is not None and tag=='input' and 'name'in a:self.current[a['name']]=a.get('value','')
  if self.current is not None and tag=='select':self.select=a.get('name')
  if self.current is not None and tag=='option' and self.select and (self.select not in self.current or 'selected'in a):self.current[self.select]=a.get('value','')
  if self.current is not None and tag=='textarea':self.textarea=a.get('name');self.current[self.textarea]=''
 def handle_endtag(self,tag):
  if tag=='form':self.current=None
  if tag=='textarea':self.textarea=None
  if tag=='select':self.select=None
 def handle_data(self,data):
  if self.current is not None and self.textarea:self.current[self.textarea]+=data

def login(a):
 s=session();request(BASE+'/wp-login.php?admin_access=true',s=s)
 r=request(BASE+'/wp-login.php','POST',{'log':a['username'],'pwd':a['password'],'wp-submit':'Log In','redirect_to':URL,'testcookie':'1'},s)
 check('existing WordPress login returns to dashboard',r[0]==302 and r[1].get('Location','').rstrip('/')==URL)
 return s
def page(s,tab='connections',extra=''):return request(URL+'&tab='+tab+extra,s=s)
def form(s,action,lid=None):
 raw=page(s,'stock' if action in ['edit_stock','sold','withdrawn','add_stock'] else 'connections')[2]
 choices=[f for f in Forms(raw).forms if f.get('workspace_action')==action and (lid is None or f.get('listing_id')==str(lid))]
 if not choices:raise AssertionError('Expected dashboard action form missing: '+action)
 return choices[0]
def post(s,body):return request(URL,'POST',body,s)
def connect(s):
 r=post(s,form(s,'connect'))
 check('dashboard starts real dealer OAuth consent',r[0]==302 and 'autoagora_connect=authorize' in r[1].get('Location',''))
 url=r[1]['Location'];r=request(url,s=s)
 nonce=re.search(r'name="_wpnonce" value="([^"]+)"',r[2])
 check('consent is available without administrator access',r[0]==200 and nonce is not None)
 r=request(url,'POST',{'_wpnonce':nonce.group(1),'decision':'approve'},s)
 check('consent returns to registered staging callback',r[0]==302 and 'autoagora_stage_client=callback' in r[1].get('Location',''))
 r=request(r[1]['Location'],s=s)
 check('code exchange returns to the same dashboard session',r[0]==302 and 'saved=connected' in r[1].get('Location',''))
def create(s):
 body=form(s,'add_stock');r=post(s,body)
 check('dashboard posts synthetic car through real API',r[0]==302 and 'saved=stock_created' in r[1].get('Location',''))
 r2=post(s,body)
 check('repeated create form is safely idempotent',r2[0]==302 and 'saved=stock_created'in r2[1].get('Location',''))
 f=form(s,'edit_stock');return int(f['listing_id'])
def main():
 global FOREIGN_ID
 check('anonymous dashboard is denied or requires login',request(URL)[0] in (302,403,404))
 s=login(ACCOUNT)
 if LOCAL:
  other=login(OTHER);connect(other);FOREIGN_ID=create(other)
 connect(s)
 for tab in ['connections','stock','activity']:
  r=page(s,tab)
  check(tab+' tab renders for the dealership account',r[0]==200 and 'agw-shell'in r[2])
  check(tab+' response is private and not indexed','no-store'in r[1].get('Cache-Control','') and 'noindex'in r[1].get('X-Robots-Tag',''))
  check(tab+' hides reviewer management and dealer creation','Reviewer access' not in r[2] and 'id="add-dealer"' not in r[2] and 'WordPress admin' not in r[2])
  check(tab+' contains only this account identity',OTHER['username'] not in r[2] and f'name="dealer_id" value="{OTHER["id"]}"' not in r[2])
 check('reviewer-management URL is forbidden',page(s,'access')[0]==403)
 check('WordPress admin remains inaccessible',request(BASE+'/wp-admin/',s=s)[0] in (302,403))
 check('native REST is not authenticated as admin',request(BASE+'/wp-json/wp/v2/users?context=edit',s=s)[0] in (401,403))
 base=form(s,'connect')
 forged={**base,'dealer_id':OTHER['id']}
 check('forged dealer ID cannot authorize another account',post(s,forged)[0]==403)
 check('forged client cannot create a grant',post(s,{**base,'client_id':'not-your-client'})[0]==403)
 check('missing form nonce cannot mutate',post(s,{**base,'workspace_nonce':''})[0]==403)
 check('dealer cannot create another dealer',post(s,{**base,'workspace_action':'add_dealer','dealer_name':'Forbidden'})[0]==403)
 check('dealer cannot manage reviewer access',post(s,{**base,'workspace_action':'reviewer_create','username':'forbidden'})[0]==403)
 check('forged dealer filter cannot expose other stock',f'id="stock-{FOREIGN_ID}"'not in page(s,'stock','&dealer='+str(OTHER['id']))[2])
 lid=create(s)
 check('other dealer stock is absent from stock dialogs',f'id="stock-{FOREIGN_ID}"'not in page(s,'stock')[2])
 body=form(s,'edit_stock',lid);original=copy.deepcopy(body)
 check('foreign listing ID cannot be edited',post(s,{**body,'listing_id':FOREIGN_ID})[0]==404)
 check('foreign listing ID cannot be withdrawn',post(s,{**body,'workspace_action':'withdrawn','listing_id':FOREIGN_ID})[0]==404)
 body.update(price='13600',description='STAGING DASHBOARD QA. Synthetic test car.')
 photos=json.loads(body['photo_order']);body['photo_order']=json.dumps(list(reversed(photos)))
 r=post(s,body)
 check('own price description and photo order save',r[0]==302 and 'saved=stock_updated'in r[1].get('Location',''))
 current=form(s,'edit_stock',lid)
 check('edit persists and preserves ownership',current['price']=='13600' and 'STAGING DASHBOARD QA'in current['description'] and json.loads(current['photo_order'])==list(reversed(photos)) and int(current['dealer_id'])==ACCOUNT['id'])
 check('stale version cannot overwrite a newer edit',post(s,original)[0]==412)
 bad={**current,'photo_order':json.dumps([photos[0],999999999])}
 check('unowned gallery attachment is rejected',post(s,bad)[0] in (403,422,502))
 check('failed gallery edit preserves gallery',form(s,'edit_stock',lid)['photo_order']==current['photo_order'])
 r=post(s,form(s,'withdrawn',lid))
 check('own withdrawal succeeds without deleting car',r[0]==302 and bool(form(s,'edit_stock',lid)))
 current=form(s,'edit_stock',lid);current['price']='13700'
 check('own withdrawn listing can be resubmitted',post(s,current)[0]==302)
 r=post(s,form(s,'sold',lid));check('own sold transition succeeds',r[0]==302 and 'saved=sold'in r[1].get('Location',''))
 disconnect=form(s,'disconnect');check('own connection can be disconnected',post(s,disconnect)[0]==302)
 check('disconnect removes active connection controls',not any(f.get('workspace_action')=='disconnect' for f in Forms(page(s)[2]).forms))
 current=form(s,'edit_stock',lid);current['price']='13800'
 check('dealer can still manage own stock after disconnect',post(s,current)[0]==302)
 check('cannot post imports without authorization',post(s,form(s,'add_stock'))[0]==409)
 connect(s)
 # Keep the acceptance-test car withdrawn; preserve the original showcase car.
 post(s,form(s,'withdrawn',lid))
 check('legacy tester link redirects to full dashboard',request(BASE+'/?autoagora_stage_client=app',s=s)[1].get('Location')==URL)
 logout=form(s,'logout');check('dashboard signs out the WordPress session',post(s,logout)[0]==302)
 check('signed-out session cannot reopen dashboard',request(URL,s=s)[0] in (302,403,404))
 RESULTS.append({'test_listing_id':lid,'retained_as':'withdrawn'})
 print('Completed',sum('passed'in r for r in RESULTS),'dashboard checks.',flush=True)
if __name__=='__main__':
 try:main()
 except Exception as e:
  print('FAILED:',str(e),flush=True);RESULTS.append({'failure':str(e)});raise
 finally:(PRIVATE/('dashboard-local-results.json'if LOCAL else'dashboard-hosted-results.json')).write_text(json.dumps({'base':BASE,'checks':RESULTS},indent=2))
