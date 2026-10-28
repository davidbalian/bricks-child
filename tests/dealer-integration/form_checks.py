#!/usr/bin/env python3
"""Focused compatibility checks for the actual website add/edit form handlers, locally only."""
import json
import re
import secrets
import urllib.error
import urllib.parse
import urllib.request

import http_checks as h

def multipart(fields):
    boundary = 'ag-fixture-' + secrets.token_hex(16)
    chunks = []
    for name, value in fields.items():
        chunks.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="' + name + '"\r\n\r\n' + str(value) + '\r\n').encode())
    image = (h.ROOT / 'screenshot.png').read_bytes()
    for i in range(2):
        chunks.append(('--' + boundary + '\r\nContent-Disposition: form-data; name="car_images[]"; filename="fixture-' + str(i) + '.png"\r\nContent-Type: image/png\r\n\r\n').encode() + image + b'\r\n')
    chunks.append(('--' + boundary + '--\r\n').encode())
    return b''.join(chunks), 'multipart/form-data; boundary=' + boundary

def main():
    if h.data('\\AutoAgora\\Dealer\\Review::privateMode()'):
        raise SystemExit('Public-contract form suite is disabled in private review mode. Run review_checks.py. See docs/dealer-integration/VERIFICATION.md.')
    h.limits()
    session = h.login()
    status, _, _, page = h.request(h.BASE + '/add-listing/', session=session)
    h.check('existing add-listing template renders locally', status, 200)
    nonce = re.search(r'name="add_car_listing_nonce" value="([^"]+)"', page)
    if not nonce: raise AssertionError('Native add form nonce was not rendered.')
    data = h.sample('native-form'); data.pop('external_stock_id'); data.pop('photos')
    location = data.pop('location')
    data.update({'car_' + key: value for key, value in location.items()})
    data.update({'action': 'add_new_car_listing', 'add_car_listing_nonce': nonce.group(1), 'drive_type': 'Front-Wheel Drive', 'interior_color': 'Black', 'number_of_doors': 4, 'number_of_seats': 5, 'hp': 150, 'numowners': 2, 'description': 'Native website form fixture', 'extras[0]': 'alloy_wheels', 'vehiclehistory[0]': 'regular_maintenance', 'isantique': '1'})
    payload, content_type = multipart(data)
    req = urllib.request.Request(h.BASE + '/wp-admin/admin-post.php', data=payload, headers={'Content-Type': content_type, 'Referer': h.BASE + '/add-listing/'}, method='POST')
    try: response = session.open(req, timeout=120)
    except urllib.error.HTTPError as error: response = error
    response.read()
    location = response.headers.get('Location', '')
    query = urllib.parse.parse_qs(urllib.parse.urlparse(location).query)
    h.check('native add submission returns success redirect', (response.code, query.get('listing_submitted')), (302, ['success']))
    listing_id = int(query['listing_id'][0])
    _, tokens, _, _ = h.authorize(client=h.OTHER)
    native = h.api(tokens, f'/listings/{listing_id}')[2]
    h.check('website-created stock is readable through authorized API', native['id'], listing_id)
    h.check('native form still creates pending stock', native['post_status'], 'pending')
    h.check('native gallery remains optimized and complete', len(native['photos']) == 2 and all(p['url'].endswith('.webp') for p in native['photos']), True)
    h.check('shared writer preserves optional checkbox values', (native['fields']['extras'], native['fields']['isantique']), (['alloy_wheels'], 1))

    edit_url = h.BASE + '/edit-listing/?car_id=' + str(listing_id)
    status, _, _, page = h.request(edit_url, session=session)
    h.check('existing edit template renders locally', status, 200)
    nonce = re.search(r'name="edit_car_listing_nonce" value="([^"]+)"', page)
    if not nonce: raise AssertionError('Native edit form nonce missing.')
    data['action'] = 'edit_car_listing'; data.pop('add_car_listing_nonce')
    data['edit_car_listing_nonce'] = nonce.group(1)
    data['car_id'] = listing_id
    data['year'] = 2021; data['price'] = 13000
    data['make'] = 'Immutable website make'; data['model'] = 'Immutable website model'
    for i, photo in enumerate(reversed(native['photos'])): data[f'image_order[{i}]'] = photo['id']
    status, _, _, _ = h.request(h.BASE + '/wp-admin/admin-post.php', 'POST', data, {'Referer': edit_url}, session=session, form=True)
    h.check('native edit submission redirects successfully', status, 302)
    edited = h.api(tokens, f'/listings/{listing_id}')[2]
    h.check('native edit saves specs without changing make/model', (edited['fields']['year'], edited['fields']['price'], edited['fields']['make'], edited['fields']['model']), (2021, 13000, 'BMW', '3 Series'))
    h.check('native edit preserves selected photo ordering', [p['id'] for p in edited['photos']], [p['id'] for p in reversed(native['photos'])])
    h.check('API patch preserves omitted form checkbox fields', h.api(tokens, f'/listings/{listing_id}', 'PATCH', {'description': 'API follow-up'}, edited['version'])[0], 200)
    patched = h.api(tokens, f'/listings/{listing_id}')[2]
    h.check('optional selections survive API patch', (patched['fields']['extras'], patched['fields']['isantique']), (['alloy_wheels'], 1))
    second = h.login('dealer_two')
    status, headers, _, _ = h.request(edit_url, session=second)
    h.check('non-owner is redirected away from native editor', status == 302 and '/my-listings/' in headers.get('Location', ''), True)
    print('Completed', len(h.RESULTS), 'native form compatibility checks, locally only.')
    (h.PRIVATE / 'form-results.json').write_text(json.dumps({'checks': h.RESULTS}, indent=2))

if __name__ == '__main__':
    try: main()
    except Exception as error:
        print('FAILED:', str(error))
        raise SystemExit(1)
