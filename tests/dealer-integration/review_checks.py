#!/usr/bin/env python3
"""HTTP and persistence tests for the LOCAL administrator-only review workspace."""
import html
import json
from pathlib import Path
import re
import secrets
import subprocess
import time
import http_checks as h

URL = h.BASE + '/?autoagora_connect=workspace'

def wp(code):
    result = subprocess.run(['wp', '--path=' + str(h.ROOT / '.staging/wp'), '--user=stage-admin', 'eval', "if(wp_get_environment_type()!=='local'){throw new Exception('Local only');}" + code], capture_output=True, text=True)
    if result.returncode:
        (h.PRIVATE / 'review-cli-error.log').write_text(result.stderr + result.stdout)
        raise AssertionError('Local fixture operation failed; inspect private log.')
h.wp = wp

def data(code): return h.data(code)
def page(session, tab='connections'): return h.request(URL + '&tab=' + tab, session=session)
def nonce(raw): return re.search(r'name="workspace_nonce" value="([^"]+)"', raw).group(1)
def action(session, body, token=None):
    if token is None: token = nonce(page(session)[3])
    return h.request(URL, 'POST', {'workspace_nonce': token, **body}, session=session, form=True)
def check(name, actual, expected=True): h.check(name, actual, expected)

def main():
    check('private mode is the default', data('\\AutoAgora\\Dealer\\Review::privateMode()'))
    admin = h.login('admin'); dealer = h.login('dealer_one')
    for role, session in [('visitor', None), ('dealer', dealer)]:
        for view in ['workspace', 'connections', 'authorize']:
            check(role + ' cannot access ' + view, h.request(h.BASE + '/?autoagora_connect=' + view, session=session)[0], 404)
        for path in ['/listings', '/catalog', '/oauth/token']:
            check(role + ' cannot access private API ' + path, h.request(h.API + path, session=session)[0], 404)
    status, headers, _, raw = page(admin)
    check('administrator workspace renders', status, 200)
    check('workspace is uncached and noindexed', 'no-store' in headers.get('Cache-Control','') and 'noindex' in headers.get('X-Robots-Tag',''))
    check('workspace uses existing shared style and brand', '/style.css' in raw and 'autoagora-logo.png' in raw and 'btn btn-primary' in raw)
    check('workspace contains no client secrets or passwords', all(item not in raw for item in [h.CLIENT['client_secret'], h.ACCOUNTS['admin']['password']]))
    check('missing form nonce is rejected', action(admin, {'workspace_action':'add_dealer','dealer_name':'CSRF blocked'}, '')[0], 403)
    name = 'Review Motors ' + secrets.token_hex(3)
    response = action(admin, {'workspace_action':'add_dealer','dealer_name':name})
    check('administrator creates a private test dealer', response[0], 302)
    dealer_id = int(data("get_users(['meta_key'=>'_ag_dealer_test_account','meta_value'=>'1','orderby'=>'ID','order'=>'DESC','number'=>1])[0]->ID"))
    profile_id = int(data("get_posts(['post_type'=>'dealer_profile','post_status'=>'any','author'=>" + str(dealer_id) + ",'numberposts'=>1])[0]->ID"))
    check('new account is marked as test data', data(f"(bool)get_user_meta({dealer_id},'_ag_dealer_test_account',true)"))
    check('new dealer profile is tagged and draft', data(f"[get_post_status({profile_id}),(bool)get_post_meta({profile_id},'_ag_dealer_sandbox',true)]"), ['draft', True])
    base = {'dealer_id':dealer_id,'client_id':h.CLIENT['client_id']}
    check('test dealer connects through real grant persistence', action(admin, {'workspace_action':'connect', **base})[0], 302)
    check('grant is active', bool(data(f"\\AutoAgora\\Dealer\\Grants::active('{h.CLIENT['client_id']}',{dealer_id})")))
    check('non-test account cannot be connected', 'Choose a test dealer' in action(admin, {'workspace_action':'connect',**base,'dealer_id':h.ACCOUNTS.get('admin',{}).get('id',1)})[3])
    response = action(admin, {'workspace_action':'add_stock',**base,'return_tab':'stock'})
    (h.PRIVATE / 'review-last-response.html').write_text(response[3])
    check('sample stock imports using shared listing service', response[0], 302)
    listing_id = int(data(f"get_posts(['post_type'=>'car','post_status'=>'any','author'=>{dealer_id},'numberposts'=>1])[0]->ID"))
    listing = lambda: data(f"\\AutoAgora\\Dealer\\Listings::read({listing_id},['dealer'=>{dealer_id}])")
    record = listing(); photos = [p['id'] for p in record['photos']]
    check('new stock awaits moderation and remains private', [record['post_status'],record['publicly_available'],data(f"(bool)get_post_meta({listing_id},'_ag_dealer_sandbox',true)")], ['pending',False,True])
    check('photos are stored outside the public folder', all(str(h.ROOT / '.private/review-media') in data(f"get_attached_file({p})") for p in photos))
    check('photos use protected administrator route', all('/ag-review-media/' in p['url'] for p in record['photos']))
    for p in record['photos']:
        for role, session in [('visitor',None),('dealer',dealer)]:
            check(role + ' cannot fetch protected photo ' + str(p['id']), h.request(p['url'],session=session)[0], 404)
        check('administrator can fetch protected photo ' + str(p['id']), h.request(p['url'],session=admin)[0], 200)
        raw_url = p['url'].replace('/ag-review-media/', '/wp-content/uploads/')
        check('photo absent from public upload folder ' + str(p['id']), not (h.ROOT / '.staging/wp/wp-content/uploads' / p['url'].split('/ag-review-media/')[1]).exists())
        check('public upload URL does not serve image ' + str(p['id']), h.request(raw_url)[0] in [301,302,404])
    edit = {'workspace_action':'edit_stock',**base,'listing_id':listing_id,'version':record['version'],'price':'15900','description':'Private UI review vehicle','photo_order':json.dumps(list(reversed(photos))),'return_tab':'stock'}
    check('stock edit submits successfully', action(admin,edit)[0], 302)
    record = listing()
    check('price and photo order persist', [record['fields']['price'],[p['id'] for p in record['photos']]], [15900,list(reversed(photos))])
    check('stale edit cannot overwrite later work', 'Listing changed' in action(admin,edit)[3])
    check('invalid price is rejected', 'positive whole number' in action(admin,{**edit,'version':record['version'],'price':'-1'})[3])
    check('stock remains unchanged after rejected writes', listing()['fields']['price'],15900)
    for status_action in ['sold','withdrawn']:
        check('administrator can mark ' + status_action, action(admin,{'workspace_action':status_action,**base,'listing_id':listing_id,'version':listing()['version']})[0],302)
    record = listing()
    check('withdrawal retains stock and all photos', [record['status'],record['post_status'],len(record['photos'])],['withdrawn','draft',2])
    wp(f"wp_update_post(['ID'=>{listing_id},'post_status'=>'publish']);wp_update_post(['ID'=>{profile_id},'post_status'=>'publish']);")
    check('attempted publication is forced private', data(f"[get_post_status({listing_id}),get_post_status({profile_id})]"),['private','private'])
    for role, session in [('visitor',None),('dealer',dealer)]:
        for id_, post_type in [(listing_id,'car'),(profile_id,'dealer_profile')]:
            check(role + ' cannot read private ' + post_type, h.request(h.BASE + f'/?post_type={post_type}&p={id_}',session=session)[0],404)
        check(role + ' cannot access test author', h.request(h.BASE + f'/?author={dealer_id}',session=session)[0],404)
        for path in [f'/wp-json/wp/v2/users/{dealer_id}',f'/wp-json/wp/v2/media/{photos[0]}']:
            check(role + ' cannot read private REST object ' + path, h.request(h.BASE + path,session=session)[0],404)
        check(role + ' search omits test dealer result link', ('href="' + html.escape(data(f'get_permalink({profile_id})'),quote=True) + '"') not in h.request(h.BASE + '/?s=' + name.replace(' ','+'),session=session)[3])
    check('public native post query excludes test records', data(f"(function(){{wp_set_current_user(0);return get_posts(['post_type'=>['car','dealer_profile'],'post_status'=>['private','draft','pending','publish'],'suppress_filters'=>false,'post__in'=>[{listing_id},{profile_id}]]);}})()"),[])
    check('public user query excludes test dealer', data(f"(function(){{wp_set_current_user(0);return get_users(['include'=>[{dealer_id}]]);}})()"),[])
    check('sitemap query excludes review metadata', 'NOT EXISTS' in json.dumps(data("apply_filters('wp_sitemaps_posts_query_args', [], 'car')")))
    check('administrator activity view renders', page(admin,'activity')[0],200)
    check('administrator stock view includes private record', '15900' in page(admin,'stock')[3])
    check('empty search has usable feedback', 'No matching dealers' in h.request(URL+'&q=zz-no-match-zz',session=admin)[3])
    check('disconnect succeeds', action(admin,{'workspace_action':'disconnect',**base})[0],302)
    check('disconnect revokes the grant', bool(data(f"\\AutoAgora\\Dealer\\Grants::active('{h.CLIENT['client_id']}',{dealer_id})")),False)
    check('disconnect keeps stock and photos', len(listing()['photos']),2)
    check('disconnected account cannot mutate stock', 'Connect this test dealer' in action(admin,edit)[3])
    # Persistent privacy hooks survive the API kill switch and missing dependencies.
    runtime = h.PRIVATE / 'runtime.php'; original = runtime.read_text()
    try:
        runtime.write_text(original.replace("define('AUTOAGORA_DEALER_API_ENABLED', true)", "define('AUTOAGORA_DEALER_API_ENABLED', false)"))
        time.sleep(3)
        check('API off still protects private media', h.request(record['photos'][0]['url'])[0],404)
        check('API off still protects private listing', h.request(h.BASE+f'/?post_type=car&p={listing_id}')[0],404)
    finally:
        runtime.write_text(original)
        time.sleep(3)
    autoload = h.ROOT / '.staging/wp/wp-content/themes/bricks-child/includes/dealer-integration/vendor/autoload.php'
    parked = autoload.with_suffix('.review-disabled')
    try:
        autoload.rename(parked)
        check('missing dependencies still protect test photo', h.request(record['photos'][0]['url'])[0],404)
        check('missing dependencies still protect test profile', h.request(h.BASE+f'/?post_type=dealer_profile&p={profile_id}')[0],404)
        check('missing dependencies do not fatal on account page', h.request(h.BASE+'/my-account/',session=dealer)[0],200)
    finally:
        parked.rename(autoload)
    check('admin Tools entry redirects before headers', h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-integrations',session=admin)[0],302)
    check('retired anonymous demo redirects to private workspace', h.request('http://127.0.0.1:8898/')[1].get('Location'), URL)
    check('retired anonymous demo cannot write', h.request('http://127.0.0.1:8898/', 'POST', {})[0],410)
    (h.PRIVATE/'review-results.json').write_text(json.dumps(h.RESULTS,indent=2))
    print('Completed',len(h.RESULTS),'private review checks.')

if __name__=='__main__': main()
