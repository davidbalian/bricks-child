#!/usr/bin/env python3
"""Real HTTP lifecycle checks against the disposable loopback WordPress fixture.
Never points at production. Does not print tokens, passwords or authorization codes.
"""
import base64
import copy
import concurrent.futures
import hashlib
import html
import http.cookiejar
import json
from pathlib import Path
import re
import secrets
import subprocess
import time
import urllib.error
import urllib.parse
import urllib.request

ROOT = Path(__file__).resolve().parents[2]
BASE = 'http://127.0.0.1:8899'
API = BASE + '/wp-json/autoagora-dealer/v1'
PRIVATE = ROOT / '.private'
ACCOUNTS = json.loads((PRIVATE / 'test-accounts.json').read_text())
CLIENT = json.loads((PRIVATE / 'partner.json').read_text())
OTHER = json.loads((PRIVATE / 'second-partner.json').read_text())
RESULTS = []

class NoRedirect(urllib.request.HTTPRedirectHandler):
    def redirect_request(self, req, fp, code, msg, headers, newurl): return None

def opener():
    return urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()), NoRedirect())

def request(url, method='GET', body=None, headers=None, session=None, form=False):
    headers = dict(headers or {})
    data = None
    if body is not None:
        data = urllib.parse.urlencode(body).encode() if form else json.dumps(body, separators=(',', ':')).encode()
        headers['Content-Type'] = 'application/x-www-form-urlencoded' if form else 'application/json'
    req = urllib.request.Request(url, data=data, headers=headers, method=method)
    try: response = (session or opener()).open(req, timeout=110)
    except urllib.error.HTTPError as error: response = error
    raw = response.read().decode('utf-8', 'replace')
    try: parsed = json.loads(raw)
    except ValueError: parsed = None
    return response.code, dict(response.headers), parsed, raw

def check(name, actual, expected):
    if actual != expected: raise AssertionError(f'{name}: expected {expected!r}, got {actual!r}')
    RESULTS.append({'check': name, 'passed': True})
    print('PASS', name)

def wp(code):
    cmd = ['wp', '--path=' + str(ROOT / '.staging/wp'), 'eval', "if(wp_get_environment_type()!=='local'){throw new Exception('Local only');}" + code]
    result = subprocess.run(cmd, capture_output=True, text=True)
    if result.returncode: raise AssertionError('Local fixture data operation failed; inspect private logs.')

def limits(): wp("global $wpdb;$wpdb->query('DELETE FROM ' . \\AutoAgora\\Dealer\\Support::table('limits'));".replace('\\\\', '\\'))

def data(expression):
    path = str(PRIVATE / 'data-check.json')
    wp('file_put_contents(' + json.dumps(path) + ',wp_json_encode(' + expression + '));')
    return json.loads((PRIVATE / 'data-check.json').read_text())

def extra_checks(tokens, dealer2, listing_id, dealer_session):
    limits()
    check('bearer grants no general WordPress REST authentication', request(BASE + '/wp-json/wp/v2/users/me', headers={'Authorization': 'Bearer ' + tokens['access_token']})[0], 401)
    check('malformed OAuth client parameter returns 400', request(API + '/oauth/token', 'POST', {'grant_type': 'refresh_token', 'client_id[]': 'invalid'}, form=True)[0], 400)
    query = {'autoagora_connect': 'authorize', 'response_type': 'code', 'client_id': CLIENT['client_id'], 'redirect_uri': CLIENT['redirect_uri'], 'scope': 'stock:read', 'state': 'local-nonce-test', 'code_challenge': 'a' * 43, 'code_challenge_method': 'S256'}
    consent_url = BASE + '/?' + urllib.parse.urlencode(query)
    status, _, _, raw = request(consent_url, session=dealer_session)
    check('existing signed-in dealer can review consent', status, 200)
    nonce = re.search(r'name="_wpnonce" value="([^"]+)"', raw).group(1)
    check('consent requires a valid WordPress nonce', request(consent_url, 'POST', {'decision': 'approve'}, session=dealer_session, form=True)[0], 403)
    status, headers, _, _ = request(consent_url, 'POST', {'decision': 'deny', '_wpnonce': nonce}, session=dealer_session, form=True)
    check('cancel returns an OAuth denial to the approved callback', (status, urllib.parse.parse_qs(urllib.parse.urlparse(headers.get('Location', '')).query).get('error')), (302, ['access_denied']))
    query['redirect_uri'] = 'https://unapproved.example/callback'
    status, headers, _, _ = request(BASE + '/?' + urllib.parse.urlencode(query), session=dealer_session)
    check('unapproved callback is rejected without redirect', (status, bool(headers.get('Location'))), (400, False))
    check('catalog fixture contains every reference term', int(data("wp_count_terms(['taxonomy'=>'car_make','hide_empty'=>false])")), 2674)
    before = int(data("wp_count_posts('attachment')->inherit"))
    for name, urls in [('oversize', ['https://images.example.com/oversize.png', 'https://images.example.com/fixture-two.png']),
                       ('wrong-mime', ['https://images.example.com/not-an-image.png', 'https://images.example.com/fixture-two.png']),
                       ('truncated', ['https://images.example.com/truncated.png', 'https://images.example.com/fixture-two.png']),
                       ('partial-create', ['https://images.example.com/fixture-one.png', 'https://images.example.com/not-an-image.png'])]:
        check(name + ' image batch is rejected', api(tokens, '/listings', 'POST', sample(name + secrets.token_hex(4), urls))[0], 502)
    after = int(data("wp_count_posts('attachment')->inherit"))
    check('partial failure cleans new attachments', after, before)
    invalid = sample('too-many'); invalid['photos'] = [{'url': 'https://images.example.com/' + str(i) + '.png'} for i in range(26)]
    check('maximum 25 images enforced', api(tokens, '/listings', 'POST', invalid)[0], 422)
    showroom = {'address': 'Synthetic default showroom', 'city': 'Nicosia', 'latitude': 35.17271234, 'longitude': 33.36391234}
    check('showroom accepts an optional district', api(tokens, '/showroom', 'PUT', {'location': showroom})[0], 200)
    saved = api(tokens, '/showroom')[2]['location']
    check('showroom coordinates round to six decimals', (saved['latitude'], saved['longitude']), (35.172712, 33.363912))
    limits()
    payload = sample('showroom-' + secrets.token_hex(6)); payload.pop('location')
    status, _, created, _ = api(tokens, '/listings', 'POST', payload)
    check('new listing uses its saved showroom', status == 201 and api(tokens, '/listings/' + str(created['id']))[2]['location']['address'], showroom['address'])
    created_id = created['id']
    override = {'address': 'Synthetic second showroom', 'city': 'Limassol', 'district': '', 'latitude': 34.6786, 'longitude': 33.0413}
    latest = api(tokens, '/listings/' + str(created_id))[2]
    check('per-listing override is accepted', api(tokens, '/listings/' + str(created_id), 'PATCH', {'location': override}, latest['version'])[0], 200)
    check('per-listing override leaves showroom unchanged', api(tokens, '/showroom')[2]['location']['address'], showroom['address'])
    latest = api(tokens, '/listings/' + str(created_id))[2]
    status, _, hidden, _ = api(tokens, f'/listings/{created_id}/status', 'POST', {'status': 'withdrawn'}, latest['version'])
    check('active withdrawal retains previous active state', (status, hidden['listing_state']), (200, 'active'))
    status, _, restored, _ = api(tokens, f'/listings/{created_id}', 'PATCH', {'description': 'Returned stock'}, hidden['version'])
    check('withdrawn active stock resubmits to pending', (status, restored['post_status'], restored['listing_state']), (200, 'pending', 'active'))

    wp("update_user_meta(get_user_by('login','dealer-one-test')->ID,'_ag_fixture_quota_blocked',1);")
    try: check('configured allowance rule cannot be bypassed', api(tokens, '/listings', 'POST', sample('quota-blocked'))[0], 403)
    finally: wp("delete_user_meta(get_user_by('login','dealer-one-test')->ID,'_ag_fixture_quota_blocked');")
    wp("update_user_meta(get_user_by('login','dealer-one-test')->ID,'_autoagora_dealer_api_blocked',1);")
    try: check('dealer suspension blocks an existing token', api(tokens, '/listings')[0], 401)
    finally: wp("delete_user_meta(get_user_by('login','dealer-one-test')->ID,'_autoagora_dealer_api_blocked');")

    # Generate an already-expired JWT with the same established OAuth entity/signing implementation.
    expired_path = PRIVATE / 'expired-access-token.txt'
    code = "$client=(new \\AutoAgora\\Dealer\\Clients())->getClientEntity('stock-partner-test');$repo=new \\AutoAgora\\Dealer\\AccessTokens();$token=$repo->getNewToken($client,[new \\AutoAgora\\Dealer\\Scope('stock:read')],(string)get_user_by('login','dealer-one-test')->ID);$token->setIdentifier(bin2hex(random_bytes(40)));$token->setExpiryDateTime(new DateTimeImmutable('-5 minutes'));$token->setPrivateKey(new \\League\\OAuth2\\Server\\CryptKey('file://' . AUTOAGORA_DEALER_PRIVATE_KEY));$repo->persistNewAccessToken($token);file_put_contents(" + json.dumps(str(expired_path)) + ",$token->toString());chmod(" + json.dumps(str(expired_path)) + ",0600);"
    wp(code)
    check('expired signed access token is rejected', api({'access_token': expired_path.read_text()}, '/listings')[0], 401)

    limits()
    payload = sample('concurrent-' + secrets.token_hex(6)); key = 'concurrent-' + secrets.token_hex(8)
    with concurrent.futures.ThreadPoolExecutor(max_workers=2) as pool:
        replies = list(pool.map(lambda _: api(tokens, '/listings', 'POST', payload, key=key), range(2)))
    check('concurrent retries return success or an explicit busy response', all(r[0] in [201, 409] for r in replies), True)
    status, _, recovered, _ = api(tokens, '/listings', 'POST', payload, key=key)
    check('concurrent retries resolve to one stock listing', (status, api(tokens, '/listings?external_stock_id=' + payload['external_stock_id'])[2]['total']), (201, 1))
    receipt_key = hashlib.sha256((CLIENT['client_id'] + ':' + str(data("get_user_by('login','dealer-one-test')->ID")) + ':POST:/autoagora-dealer/v1/listings:' + key).encode()).hexdigest()
    wp("global $wpdb;$wpdb->update(\\AutoAgora\\Dealer\\Support::table('requests'),['response'=>null],['request_key'=>" + json.dumps(receipt_key) + "]);")
    check('interrupted create receipt recovers the same listing', api(tokens, '/listings', 'POST', payload, key=key)[2]['id'], recovered['id'])

    runtime_path = PRIVATE / 'runtime.php'
    runtime = runtime_path.read_text()
    try:
        runtime_path.write_text(runtime.replace("define('AUTOAGORA_DEALER_RULES_VERIFIED', true)", "define('AUTOAGORA_DEALER_RULES_VERIFIED', false)"))
        # The host's CLI opcache may revalidate changed private configuration every two seconds.
        time.sleep(3)
        limits()
        check('unverified business rules disable listing writes', api(tokens, '/listings', 'POST', sample('unverified'))[0], 503)
        latest = api(tokens, f'/listings/{listing_id}')[2]
        check('unverified business rules disable status mutations', api(tokens, f'/listings/{listing_id}/status', 'POST', {'status': 'withdrawn'}, latest['version'])[0], 503)
    finally: runtime_path.write_text(runtime)

    limits()
    dealer_id = int(data("get_user_by('login','dealer-one-test')->ID"))
    bucket = hashlib.sha256(('api:' + CLIENT['client_id'] + ':' + str(dealer_id) + ':' + str(int(time.time()) // 60)).encode()).hexdigest()
    wp("global $wpdb;$wpdb->insert(\\AutoAgora\\Dealer\\Support::table('limits'),['bucket'=>" + json.dumps(bucket) + ",'hits'=>59,'expires_at'=>time()+120]);")
    check('last request within the limit succeeds', api(tokens, '/listings?per_page=1')[0], 200)
    status, headers, _, _ = api(tokens, '/listings?per_page=1')
    check('request limit returns 429 and Retry-After', (status, headers.get('Retry-After')), (429, '60'))
    limits()

    # Use the other partner so the primary connection can still exercise refresh/revocation below.
    _, disconnect_tokens, _, _ = authorize(client=OTHER)
    status, _, _, page = request(BASE + '/?autoagora_connect=connections', session=dealer_session)
    nonce = re.search(r'name="_wpnonce" value="([^"]+)"', page).group(1)
    check('dealer can review authorized applications', status, 200)
    check('dealer can disconnect through first-party UI', request(BASE + '/?autoagora_connect=connections', 'POST', {'_wpnonce': nonce, 'client_id': OTHER['client_id']}, session=dealer_session, form=True)[0], 302)
    check('UI disconnection invalidates partner access', api(disconnect_tokens, '/listings')[0], 401)
    check('disconnecting one partner preserves another', api(tokens, '/listings')[0], 200)

def login(account='dealer_one', auth_url=None):
    session = opener()
    request(BASE + '/wp-login.php?admin_access=true', session=session)
    target = auth_url or BASE + '/'
    status, headers, _, _ = request(BASE + '/wp-login.php', 'POST', {'log': ACCOUNTS[account]['username'], 'pwd': ACCOUNTS[account]['password'], 'wp-submit': 'Log In', 'redirect_to': target, 'testcookie': '1'}, session=session, form=True)
    if status != 302: raise AssertionError('Dealer login failed; no credentials printed.')
    if auth_url is not None: check('login returns to consent on AutoAgora', headers.get('Location'), auth_url)
    return session

def authorize(account='dealer_one', client=CLIENT, scopes='stock:read stock:write stock:status catalog:read showroom:write', approve=True, challenge_method='S256'):
    verifier = secrets.token_urlsafe(48)
    challenge = base64.urlsafe_b64encode(hashlib.sha256(verifier.encode()).digest()).rstrip(b'=').decode()
    state = secrets.token_urlsafe(24)
    query = {'autoagora_connect': 'authorize', 'response_type': 'code', 'client_id': client['client_id'], 'redirect_uri': client['redirect_uri'], 'scope': scopes, 'state': state, 'code_challenge': challenge, 'code_challenge_method': challenge_method}
    url = BASE + '/?' + urllib.parse.urlencode(query)
    session = login(account, url)
    status, _, _, raw = request(url, session=session)
    if challenge_method != 'S256': return status, None, None, session
    if status != 200: raise AssertionError('Consent page failed to render.')
    nonce = re.search(r'name="_wpnonce" value="([^"]+)"', raw)
    if not nonce: raise AssertionError('Consent nonce missing.')
    status, headers, _, _ = request(url, 'POST', {'_wpnonce': nonce.group(1), 'decision': 'approve' if approve else 'deny'}, session=session, form=True)
    if not approve: return status, headers, None, session
    if status != 302: raise AssertionError('Consent did not return to the registered partner callback.')
    callback = urllib.parse.urlparse(headers['Location'])
    params = urllib.parse.parse_qs(callback.query)
    check('OAuth returns original state', params.get('state', [None])[0], state)
    token_body = {'grant_type': 'authorization_code', 'code': params['code'][0], 'redirect_uri': client['redirect_uri'], 'client_id': client['client_id'], 'client_secret': client['client_secret'], 'code_verifier': verifier}
    status, _, tokens, _ = request(API + '/oauth/token', 'POST', token_body, form=True)
    if status != 200 or not tokens or 'access_token' not in tokens: raise AssertionError(f'Token exchange failed with HTTP {status}; no credential response printed.')
    return status, tokens, token_body, session

def api(tokens, path, method='GET', body=None, version=None, key=None):
    headers = {'Authorization': 'Bearer ' + tokens['access_token']}
    if method != 'GET': headers['Idempotency-Key'] = key or secrets.token_urlsafe(18)
    if version is not None: headers['If-Match'] = version
    return request(API + path, method, body, headers)

def main():
    if data('\\AutoAgora\\Dealer\\Review::privateMode()'):
        raise SystemExit('Public-contract regression suite is disabled in private review mode. Run review_checks.py. See docs/dealer-integration/VERIFICATION.md.')
    limits()
    status, headers, _, _ = request(API + '/listings')
    check('unauthenticated API denied', status, 401)
    check('API is not cacheable', headers.get('Cache-Control'), 'no-store, private')
    for path in ['/.private/test-accounts.json', '/wp-content/themes/bricks-child/.private/test-accounts.json', '/wp-content/themes/bricks-child/.git/config']:
        check('private fixture path inaccessible: ' + path, request(BASE + path)[0], 404)

    status, tokens, code_body, dealer_session = authorize()
    check('authorization code with PKCE issues access and refresh tokens', bool(tokens.get('refresh_token')) and status, 200)
    check('authorization code cannot be reused', request(API + '/oauth/token', 'POST', code_body, form=True)[0], 400)
    check('plain PKCE rejected', authorize(challenge_method='plain')[0], 400)
    _, dealer2, _, _ = authorize('dealer_two')
    _, partner2, _, _ = authorize(client=OTHER)
    _, read_only, _, _ = authorize('dealer_two', OTHER, 'stock:read')
    check('read-only grant cannot create stock', api(read_only, '/listings', 'POST', {})[0], 403)
    check('catalog reports current fixture counts', api(tokens, '/catalog')[2]['total'], 126)
    check('invalid pagination rejected', api(tokens, '/listings?per_page=999')[0], 400)
    check('non-HTTPS image URL blocked before download', api(tokens, '/listings', 'POST', sample('bad-http', ['http://127.0.0.1/private.jpg', 'https://example.org/a.jpg']))[0], 502)
    check('private destination blocked', api(tokens, '/listings', 'POST', sample('bad-private', ['https://127.0.0.1/private.jpg', 'https://example.org/a.jpg']))[0], 502)
    bad = sample('bad-model'); bad['model'] = 'Not a model'
    check('invalid model relationship rejected', api(tokens, '/listings', 'POST', bad)[0], 422)
    bad = sample('bad-van'); bad['body_type'] = 'Van'
    check('generic Van is not silently mapped', api(tokens, '/listings', 'POST', bad)[0], 422)
    bad = sample('bad-fuel'); bad['fuel_type'] = 'LPG'
    check('LPG-only value is not silently mapped', api(tokens, '/listings', 'POST', bad)[0], 422)
    bad = sample('bad-price'); bad['price'] = '12,500.50'
    check('formatted or decimal price rejected', api(tokens, '/listings', 'POST', bad)[0], 422)
    bad = sample('bad-photos'); bad['photos'] = bad['photos'][:1]
    limits()
    check('minimum two photos enforced', api(tokens, '/listings', 'POST', bad)[0], 422)
    run_id = secrets.token_hex(6)
    payload = sample('fixture-' + run_id)
    key = 'create-' + run_id
    status, _, listing, _ = api(tokens, '/listings', 'POST', payload, key=key)
    check('complete image batch creates pending listing', status, 201)
    check('API never claims immediate publication', listing['status'], 'pending')
    listing_id = listing['id']; version = listing['version']
    status, headers, again, _ = api(tokens, '/listings', 'POST', payload, key=key)
    check('same request is replayed safely', (status, headers.get('Idempotency-Replayed'), again['id']), (201, 'true', listing_id))
    altered = copy.deepcopy(payload); altered['price'] += 1
    check('same key cannot carry a new payload', api(tokens, '/listings', 'POST', altered, key=key)[0], 409)
    check('external stock ID cannot create a duplicate', api(tokens, '/listings', 'POST', payload)[0], 409)
    check('external ID lookup returns original listing', api(tokens, '/listings?external_stock_id=' + payload['external_stock_id'])[2]['items'][0]['id'], listing_id)
    check('other dealer cannot read listing', api(dealer2, f'/listings/{listing_id}')[0], 404)
    check('other dealer cannot edit listing', api(dealer2, f'/listings/{listing_id}', 'PATCH', {'price': 1000}, version)[0], 404)
    check('other dealer cannot see external stock ID', api(dealer2, '/listings?external_stock_id=' + payload['external_stock_id'])[2]['total'], 0)
    limits()
    partner_payload = sample(payload['external_stock_id'])
    status, _, second, _ = api(partner2, '/listings', 'POST', partner_payload)
    check('same external ID is independently scoped to another partner', status == 201 and second['id'] != listing_id, True)
    check('other dealer may use the same external ID', api(dealer2, '/listings', 'POST', partner_payload)[0], 201)
    read = api(tokens, f'/listings/{listing_id}')[2]
    photos = read['photos']; old_price = read['fields']['price']
    bad_update = {'photos': [{'id': photos[0]['id']}, {'url': 'https://127.0.0.1/fail.jpg'}], 'price': 1000}
    check('failed photo replacement rejects update', api(tokens, f'/listings/{listing_id}', 'PATCH', bad_update, version)[0], 502)
    unchanged = api(tokens, f'/listings/{listing_id}')[2]
    check('photo failure preserves previous gallery and price', (unchanged['photos'], unchanged['fields']['price']), (photos, old_price))
    check('missing If-Match rejected', api(tokens, f'/listings/{listing_id}', 'PATCH', {'price': 13000})[0], 412)
    status, _, changed, _ = api(tokens, f'/listings/{listing_id}', 'PATCH', {'price': 13000, 'photos': [{'id': p['id']} for p in reversed(photos)]}, version)
    check('price and image order update succeeds', status, 200)
    check('old ETag cannot overwrite a later edit', api(tokens, f'/listings/{listing_id}', 'PATCH', {'price': 12000}, version)[0], 412)
    latest = api(tokens, f'/listings/{listing_id}')[2]
    check('dealer image order preserved', [p['id'] for p in latest['photos']], [p['id'] for p in reversed(photos)])
    version = latest['version']
    # Approve only this synthetic listing in the isolated DB to check real public templates and redirects.
    wp(f"wp_update_post(['ID'=>{listing_id},'post_status'=>'publish']);")
    latest = api(tokens, f'/listings/{listing_id}')[2]; version = latest['version']
    check('approved synthetic listing renders frontend', request(latest['url'])[0], 200)
    limits()
    status, _, sold, _ = api(tokens, f'/listings/{listing_id}/status', 'POST', {'status': 'sold'}, version)
    check('sold transition succeeds', status, 200)
    check('sold frontend follows existing redirect', request(sold['url'])[0], 301)
    status, _, withdrawn, _ = api(tokens, f'/listings/{listing_id}/status', 'POST', {'status': 'withdrawn'}, sold['version'])
    check('withdrawal hides without deletion', (status, withdrawn['status'], withdrawn['post_status']), (200, 'withdrawn', 'draft'))
    check('withdrawn frontend unavailable', request(withdrawn['url'])[0], 404)
    retained = api(tokens, f'/listings/{listing_id}')[2]
    check('withdrawal retains all photos', len(retained['photos']), 2)
    check('views do not return invented zeros', api(tokens, f'/listings/{listing_id}/views')[2]['available'], False)
    # A withdrawn SOLD listing remains sold on resubmission; API does not resurrect sold/expired state.
    status, _, resubmitted, _ = api(tokens, f'/listings/{listing_id}', 'PATCH', {'description': 'Resubmitted test stock'}, withdrawn['version'])
    check('resubmission follows moderation and preserves sold state', (status, resubmitted['post_status'], resubmitted['listing_state']), (200, 'pending', 'sold'))
    wp(f"\\ListingStateManager::assign_state({listing_id}, 'expired');")
    latest = api(tokens, f'/listings/{listing_id}')[2]
    check('expired listing cannot be marked sold', api(tokens, f'/listings/{listing_id}/status', 'POST', {'status': 'sold'}, latest['version'])[0], 409)

    extra_checks(tokens, dealer2, listing_id, dealer_session)
    limits()
    refresh_body = {'grant_type': 'refresh_token', 'refresh_token': tokens['refresh_token'], 'client_id': CLIENT['client_id'], 'client_secret': CLIENT['client_secret']}
    status, _, renewed, _ = request(API + '/oauth/token', 'POST', refresh_body, form=True)
    check('token refresh succeeds with rotation', status == 200 and renewed['refresh_token'] != tokens['refresh_token'], True)
    check('rotated refresh token cannot be reused', request(API + '/oauth/token', 'POST', refresh_body, form=True)[0], 400)
    check('new access token can read dealer stock', api(renewed, '/listings')[0], 200)
    revoke = {'client_id': CLIENT['client_id'], 'client_secret': CLIENT['client_secret'], 'token': renewed['refresh_token']}
    check('refresh token revokes dealer grant', request(API + '/oauth/revoke', 'POST', revoke, form=True)[0], 200)
    check('revoked access token immediately fails', api(renewed, '/listings')[0], 401)
    refresh_body['refresh_token'] = renewed['refresh_token']
    check('revoked grant cannot refresh', request(API + '/oauth/token', 'POST', refresh_body, form=True)[0], 400)
    check('revocation preserves second dealer connection', api(dealer2, '/listings')[0], 200)
    print('Completed', len(RESULTS), 'checks. All listings exist only in the isolated local database.')
    (ROOT / 'tests/dealer-integration/results.local.json').write_text(json.dumps({'checked_at': time.strftime('%Y-%m-%dT%H:%M:%SZ', time.gmtime()), 'checks': RESULTS}, indent=2))

def sample(external, urls=None):
    urls = urls or ['https://images.example.com/fixture-one.png', 'https://images.example.com/fixture-two.png']
    return {'external_stock_id': external, 'make': 'BMW', 'model': '3 Series', 'year': 2020, 'mileage': 85000, 'price': 12500,
        'availability': 'In Stock', 'engine_capacity': '2.0', 'fuel_type': 'Petrol', 'transmission': 'Automatic', 'body_type': 'Saloon', 'exterior_color': 'Black',
        'location': {'address': 'Synthetic showroom, Nicosia', 'city': 'Nicosia', 'district': 'Nicosia', 'latitude': 35.1727, 'longitude': 33.3639},
        'photos': [{'url': url} for url in urls]}

if __name__ == '__main__':
    try: main()
    except Exception as error:
        print('FAILED:', str(error))
        (ROOT / 'tests/dealer-integration/results.local.json').write_text(json.dumps({'checks': RESULTS, 'failure': str(error)}, indent=2))
        raise SystemExit(1)
