#!/usr/bin/env python3
"""Local-only username/password reviewer boundaries; credentials remain in memory."""
import html
import json
import re
import secrets
import time
import review_checks as r
h=r.h
LOGIN=h.BASE+'/?autoagora_connect=review-login'

def sign_in(session, username, password):
    raw=h.request(LOGIN,session=session)[3]
    token=re.search(r'name="review_csrf" value="([^"]+)"',raw).group(1)
    return h.request(LOGIN,'POST',{'review_csrf':token,'review_username':username,'review_password':password},session=session,form=True)

def main():
    h.limits(); admin=h.login('admin'); ordinary=h.login('dealer_one')
    username='review-'+secrets.token_hex(5)
    before=r.data("(function(){global $wpdb;return [(int)$wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->users}\"),(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\\AutoAgora\\Dealer\\Support::table('clients'))];})()")
    access_page=h.request(r.URL+'&tab=access',session=admin)
    r.check('administrator access management renders completely',access_page[0]==200 and all(field in access_page[3] for field in ['name="reviewer_username"','name="reviewer_days"','name="workspace_nonce"']))
    response=r.action(admin,{'workspace_action':'create_reviewer','reviewer_username':username,'reviewer_days':'30'})
    r.check('administrator creates a username-only reviewer',response[0],200)
    password=html.unescape(re.search(r'id="reviewer-created-password"[^>]*value="([^"]+)"',response[3]).group(1))
    r.check('generated password has strong entropy and is shown masked',len(password)>=32 and 'type="password" readonly' in response[3])
    r.check('credential receipt is private and uncacheable','no-store' in response[1].get('Cache-Control','') and 'no-referrer' in response[1].get('Referrer-Policy',''))
    id_=int(r.data(f"get_user_by('login','{username}')->ID"))
    r.check('reviewer has no email',r.data(f"get_userdata({id_})->user_email"),'')
    r.check('reviewer gets only the integration role',r.data(f"get_userdata({id_})->roles"),['ag_dealer_reviewer'])
    r.check('reviewer has no backend or content-management capabilities',r.data(f"[user_can({id_},'manage_options'),user_can({id_},'edit_posts'),user_can({id_},'upload_files'),user_can({id_},'list_users'),user_can({id_},'read_private_posts')]"),[False]*5)
    r.check('reviewer password is hashed',r.data(f"str_starts_with(get_userdata({id_})->user_pass,'$wp$') || str_starts_with(get_userdata({id_})->user_pass,'$2y$')"))
    r.check('application passwords are unavailable',r.data(f"wp_is_application_passwords_available_for_user(get_userdata({id_}))"),False)
    r.check('duplicate reviewer cannot replace credentials',r.action(admin,{'workspace_action':'create_reviewer','reviewer_username':username,'reviewer_days':'30'})[0],422)
    r.check('ordinary dealer cannot create reviewers',r.action(ordinary,{'workspace_action':'create_reviewer','reviewer_username':'blocked','reviewer_days':'30'},'')[0],404)
    r.check('reviewer creation requires administrator nonce',r.action(admin,{'workspace_action':'create_reviewer','reviewer_username':'blocked'},'')[0],403)
    anonymous=h.opener(); status,headers,_,raw=h.request(LOGIN,session=anonymous)
    r.check('dedicated sign-in page renders',status,200)
    r.check('sign-in requires username and password only','name="review_username"' in raw and 'name="review_password"' in raw and 'type="email"' not in raw)
    r.check('sign-in page cannot be framed or indexed','frame-ancestors' in headers.get('Content-Security-Policy','') and 'noindex' in headers.get('X-Robots-Tag',''))
    r.check('sign-in CSRF cookie is HttpOnly and SameSite Strict',any(c.name=='ag_review_login_csrf' and 'httponly' in {k.lower() for k in c._rest} and any(k.lower()=='samesite' and v.lower()=='strict' for k,v in c._rest.items()) for processor in anonymous.handlers if hasattr(processor,'cookiejar') for c in processor.cookiejar))
    r.check('missing login CSRF token is rejected',h.request(LOGIN,'POST',{'review_username':username,'review_password':password},session=anonymous,form=True)[0],403)
    other=h.opener(); other_raw=h.request(LOGIN,session=other)[3]
    other_csrf=re.search(r'name="review_csrf" value="([^"]+)"',other_raw).group(1)
    r.check('cross-browser login CSRF token is rejected',h.request(LOGIN,'POST',{'review_csrf':other_csrf,'review_username':username,'review_password':password},session=anonymous,form=True)[0],403)
    r.check('administrator credentials cannot enter through reviewer login',sign_in(h.opener(),h.ACCOUNTS['admin']['username'],h.ACCOUNTS['admin']['password'])[0],401)
    reviewer=h.opener(); result=sign_in(reviewer,username,password)
    r.check('reviewer signs in using the generated password',result[0],302)
    r.check('reviewer sign-in returns only to the workspace',result[1].get('Location'),r.URL)
    r.check('reviewer session lifetime is one hour',r.data(f"apply_filters('auth_cookie_expiration',172800,{id_},False)"),3600)
    status,_,_,raw=r.page(reviewer)
    r.check('reviewer opens the private workspace',status,200)
    r.check('reviewer sees no admin or reviewer-management controls','WordPress admin' not in raw and 'Reviewer access' not in raw and 'Sign out' in raw)
    r.check('reviewer cannot select the access management tab','Private reviewer accounts' not in h.request(r.URL+'&tab=access',session=reviewer)[3])
    r.check('reviewer cannot create another reviewer',r.action(reviewer,{'workspace_action':'create_reviewer','reviewer_username':'cannot-create'})[0],403)
    for path in ['/wp-admin/','/wp-admin/users.php','/wp-admin/admin-ajax.php','/wp-admin/admin-post.php']:
        result=h.request(h.BASE+path,session=reviewer)
        r.check('reviewer cannot open '+path,result[0]==404 or (path=='/wp-admin/' and result[0]==302 and result[1].get('Location')==r.URL))
    r.check('reviewer cannot use the dealer API',h.request(h.API+'/listings',session=reviewer)[0],403)
    r.check('reviewer cannot use native WordPress REST',h.request(h.BASE+'/wp-json/wp/v2/users',session=reviewer)[0],403)
    r.check('reviewer cannot POST to unrelated public flows',h.request(h.BASE+'/add-listing/','POST',{'action':'add_new_car_listing'},session=reviewer)[0],403)
    # An untagged real-style customer record exists only in this disposable local fixture.
    customer='customer-'+secrets.token_hex(5)
    real_id=int(r.data(f"wp_insert_user(['user_login'=>'{customer}','user_pass'=>wp_generate_password(32),'role'=>'dealership','display_name'=>'Private real-style customer'])"))
    real_car=int(r.data(f"wp_insert_post(['post_type'=>'car','post_status'=>'pending','post_author'=>{real_id},'post_title'=>'Real-style customer stock'])"))
    r.check('reviewer cannot connect a real customer', 'Choose a test dealer' in r.action(reviewer,{'workspace_action':'connect','dealer_id':real_id,'client_id':h.CLIENT['client_id']})[3])
    r.check('real customer data stays out of the workspace','Private real-style customer' not in r.page(reviewer)[3] and 'Real-style customer stock' not in r.page(reviewer,'stock')[3])
    test_name='Reviewer demo '+secrets.token_hex(3)
    r.check('reviewer can create a private test dealership',r.action(reviewer,{'workspace_action':'add_dealer','dealer_name':test_name})[0],302)
    dealer_id=int(r.data("get_users(['role'=>'dealership','meta_key'=>'_ag_dealer_test_account','meta_value'=>'1','orderby'=>'ID','order'=>'DESC','number'=>1])[0]->ID"))
    base={'dealer_id':dealer_id,'client_id':h.CLIENT['client_id']}
    r.check('reviewer sees synthetic test dealers',test_name in r.page(reviewer)[3])
    r.check('reviewer can connect only a test dealer',r.action(reviewer,{'workspace_action':'connect',**base})[0],302)
    r.check('reviewer can add synthetic local stock',r.action(reviewer,{'workspace_action':'add_stock',**base})[0],302)
    car=int(r.data(f"get_posts(['post_type'=>'car','post_status'=>'any','author'=>{dealer_id},'numberposts'=>1])[0]->ID"))
    record=r.data(f"\\AutoAgora\\Dealer\\Listings::read({car},['dealer'=>{dealer_id}])")
    r.check('reviewer-created stock remains pending and tagged',[record['post_status'],r.data(f"(bool)get_post_meta({car},'_ag_dealer_sandbox',true)")],['pending',True])
    r.check('reviewer can read tagged test image',h.request(record['photos'][0]['url'],session=reviewer)[0],200)
    stock_raw=r.page(reviewer,'stock')[3]
    thumbnails=re.findall(r'<img src="([^"]*ag-review-media/[^"]+)"',stock_raw)
    r.check('reviewer thumbnails are protected and readable',bool(thumbnails) and h.request(html.unescape(thumbnails[0]),session=reviewer)[0]==200)
    orphan='unowned-'+secrets.token_hex(5)+'.png'
    r.wp(f"copy(get_stylesheet_directory().'/screenshot.png',\\AutoAgora\\Dealer\\Review::storage().'/{orphan}');")
    orphan_url=h.BASE+'/ag-review-media/'+orphan
    r.check('reviewer cannot read an untagged private file',h.request(orphan_url,session=reviewer)[0],404)
    r.check('anonymous visitor still cannot read test image',h.request(record['photos'][0]['url'])[0],404)
    edit={'workspace_action':'edit_stock',**base,'listing_id':real_car,'version':record['version'],'price':'9000','description':'blocked'}
    r.check('reviewer cannot edit untagged real stock','not part of the selected test account' in r.action(reviewer,edit)[3])
    r.check('reviewer cannot edit without CSRF nonce',r.action(reviewer,{**edit,'listing_id':car},'')[0],403)
    r.check('reviewer can edit tagged test stock',r.action(reviewer,{**edit,'listing_id':car})[0],302)
    r.check('reviewer cannot publish through form injection',r.data(f"get_post_status({car})"),'pending')
    # Native login cannot bypass this endpoint's rate limit or role boundary.
    native=h.opener();h.request(h.BASE+'/wp-login.php?admin_access=true',session=native)
    h.request(h.BASE+'/wp-login.php','POST',{'log':username,'pwd':password,'wp-submit':'Log In'},session=native,form=True)
    r.check('reviewer cannot bypass the private login through wp-login',h.request(r.URL,session=native)[0],404)
    # Logout is CSRF-protected and actually destroys the current session.
    logout_nonce=re.search(r'name="review_nonce" value="([^"]+)"',r.page(reviewer)[3]).group(1)
    logout=h.BASE+'/?autoagora_connect=review-logout'
    r.check('reviewer logout requires a nonce',h.request(logout,'POST',{},session=reviewer,form=True)[0],403)
    r.check('reviewer can sign out',h.request(logout,'POST',{'review_nonce':logout_nonce},session=reviewer,form=True)[0],302)
    r.check('signed-out reviewer cannot see workspace',h.request(r.URL,session=reviewer)[0],404)
    h.limits();r.check('reviewer can sign back in',sign_in(reviewer,username,password)[0],302)
    r.wp(f"update_user_meta({id_},'_ag_review_expires',time()-1);")
    r.check('expiry immediately blocks an existing session',r.page(reviewer)[0],404)
    r.wp(f"update_user_meta({id_},'_ag_review_expires',time()+DAY_IN_SECONDS);")
    r.check('administrator can revoke reviewer',r.action(admin,{'workspace_action':'revoke_reviewer','reviewer_id':id_})[0],302)
    r.check('revocation immediately blocks existing sessions',r.page(reviewer)[0],404)
    r.check('revoked account cannot sign in again',sign_in(h.opener(),username,password)[0],401)
    h.limits()
    renewed=r.action(admin,{'workspace_action':'renew_reviewer','reviewer_id':id_,'reviewer_days':'30'})
    r.check('administrator can renew a username without email',renewed[0],200)
    new_password=html.unescape(re.search(r'id="reviewer-created-password"[^>]*value="([^"]+)"',renewed[3]).group(1))
    r.check('reset issues a different strong password',len(new_password)>=32 and new_password!=password)
    r.check('old password stops working',sign_in(h.opener(),username,password)[0],401)
    renewed_session=h.opener()
    r.check('new password restores limited reviewer access',sign_in(renewed_session,username,new_password)[0],302)
    r.check('renewal never adds administrator permissions',r.data(f"user_can({id_},'manage_options')"),False)
    r.action(admin,{'workspace_action':'revoke_reviewer','reviewer_id':id_})
    # Fixed 15-minute windows rate-limit account and IP using hashed identifiers only.
    h.limits();brute=h.opener()
    for i in range(5):r.check('generic failed login '+str(i+1),sign_in(brute,'missing-reviewer','bad-password')[0],401)
    result=sign_in(brute,'missing-reviewer','bad-password')
    r.check('sixth attempt is rate limited',result[0],429)
    r.check('rate limit supplies retry duration',result[1].get('Retry-After'),'900')
    r.check('rate-limit storage contains no plaintext login',r.data("(function(){global $wpdb;return (bool)$wpdb->get_var('SELECT COUNT(*) FROM '.\\AutoAgora\\Dealer\\Support::table('limits').\" WHERE bucket LIKE '%missing-reviewer%'\");})()"),False)
    h.limits()
    (h.PRIVATE/'reviewer-results.json').write_text(json.dumps(h.RESULTS,indent=2))
    print('Completed',len(h.RESULTS),'local reviewer checks.')

if __name__=='__main__':main()
