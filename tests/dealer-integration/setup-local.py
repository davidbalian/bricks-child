#!/usr/bin/env python3
"""Initialize a fresh isolated local WP database; refuse to overwrite an existing setup.

Requires WP core, the Bricks parent and ACF code in .staging/wp; see README.
All generated credentials remain in ignored .private files, never stdout.
"""
import json
import os
from pathlib import Path
import secrets
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[2]
os.environ['AUTOAGORA_SOURCE_ROOT'] = str(ROOT)
WEB = ROOT / '.staging/wp'
PRIVATE = ROOT / '.private'
PRIVATE.mkdir(mode=0o700, exist_ok=True)
os.umask(0o077)
if (PRIVATE / 'test-accounts.json').exists() or (WEB / 'wp-config.php').exists():
    raise SystemExit('Existing setup detected. No database or credentials were changed.')
if not (WEB / 'wp-load.php').exists() or not (WEB / 'wp-content/themes/bricks/functions.php').exists():
    raise SystemExit('Install WordPress and the existing Bricks parent before setup.')

def wp(*args):
    result = subprocess.run(['wp', '--path=' + str(WEB), *args])
    if result.returncode: raise SystemExit('A local WordPress setup step failed; credentials were not included in this error.')

def php(value):
    return "'" + str(value).replace('\\', '\\\\').replace("'", "\\'") + "'"

db = 'ag_dealer_stage_' + secrets.token_hex(4)
user = 'ag_stage_' + secrets.token_hex(4)
password = secrets.token_urlsafe(36)
sql = f"CREATE DATABASE `{db}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE USER '{user}'@'localhost' IDENTIFIED BY '{password}'; GRANT ALL PRIVILEGES ON `{db}`.* TO '{user}'@'localhost';"
subprocess.run(['mysql', '--user=root'], input=sql, text=True, check=True)

for name in ['private.pem', 'public.pem']:
    if (PRIVATE / name).exists():
        raise SystemExit('Existing signing key found. Refusing replacement.')
subprocess.run(['openssl', 'genpkey', '-algorithm', 'RSA', '-pkeyopt', 'rsa_keygen_bits:2048', '-out', str(PRIVATE / 'private.pem')], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)
subprocess.run(['openssl', 'pkey', '-in', str(PRIVATE / 'private.pem'), '-pubout', '-out', str(PRIVATE / 'public.pem')], check=True, stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL)

accounts = {
    'admin': {'username': 'stage-admin', 'password': secrets.token_urlsafe(28)},
    'dealer_one': {'username': 'dealer-one-test', 'password': secrets.token_urlsafe(28)},
    'dealer_two': {'username': 'dealer-two-test', 'password': secrets.token_urlsafe(28)},
    'catalog_source': '/Users/davidbalian/Downloads/AutoAgora-Listing-Data-Specification.md',
    'database': db, 'database_user': user,
}
(PRIVATE / 'test-accounts.json').write_text(json.dumps(accounts, indent=2))
values = {
    'DB_NAME': db, 'DB_USER': user, 'DB_PASSWORD': password, 'DB_HOST': 'localhost', 'DB_CHARSET': 'utf8mb4', 'DB_COLLATE': '',
    'WP_HOME': 'http://127.0.0.1:8899', 'WP_SITEURL': 'http://127.0.0.1:8899', 'WP_ENVIRONMENT_TYPE': 'local',
    'AUTOAGORA_DEALER_PRIVATE_KEY': PRIVATE / 'private.pem', 'AUTOAGORA_DEALER_PUBLIC_KEY': PRIVATE / 'public.pem',
    'AUTOAGORA_DEALER_ENCRYPTION_KEY': secrets.token_urlsafe(48),
    'AUTOAGORA_DEALER_REVIEW_STORAGE': PRIVATE / 'review-media',
}
for salt in ['AUTH_KEY', 'SECURE_AUTH_KEY', 'LOGGED_IN_KEY', 'NONCE_KEY', 'AUTH_SALT', 'SECURE_AUTH_SALT', 'LOGGED_IN_SALT', 'NONCE_SALT']:
    values[salt] = secrets.token_urlsafe(64)
runtime = '<?php\n' + '\n'.join(f'define({php(k)}, {php(v)});' for k, v in values.items())
runtime += "\ndefine('AUTOAGORA_DEALER_API_ENABLED', true);\ndefine('AUTOAGORA_DEALER_RULES_VERIFIED', true);\ndefine('DISABLE_WP_CRON', true);\ndefine('WP_DEBUG', true);\ndefine('WP_DEBUG_DISPLAY', false);\ndefine('WP_DEBUG_LOG', " + php(PRIVATE / 'wordpress-debug.log') + ");\n"
(PRIVATE / 'runtime.php').write_text(runtime)
config = '<?php\nrequire ' + php(PRIVATE / 'runtime.php') + ";\n$table_prefix = 'wp_';\nif (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\nrequire_once ABSPATH . 'wp-settings.php';\n"
(WEB / 'wp-config.php').write_text(config)
mu = WEB / 'wp-content/mu-plugins'
mu.mkdir(parents=True, exist_ok=True)
shutil.copyfile(ROOT / 'tests/dealer-integration/staging-safety.php', mu / 'ag-fixture.php')
subprocess.run(['python3', str(ROOT / 'tests/dealer-integration/sync-local.py')], check=True)
wp('core', 'install', '--url=http://127.0.0.1:8899', '--title=AutoAgora integration staging', '--admin_user=' + accounts['admin']['username'], '--admin_password=' + accounts['admin']['password'], '--admin_email=admin@example.invalid', '--skip-email')
wp('plugin', 'activate', 'advanced-custom-fields-pro')
wp('theme', 'activate', 'bricks-child')
wp('autoagora', 'dealer', 'install')
wp('eval-file', str(ROOT / 'tests/dealer-integration/seed.php'))
wp('autoagora', 'dealer', 'client', 'stock-partner-test', '--name=Stock partner test', '--redirect=http://127.0.0.1:8898/callback', '--output=' + str(PRIVATE / 'partner.json'))
wp('autoagora', 'dealer', 'client', 'second-partner-test', '--name=Second partner test', '--redirect=http://127.0.0.1:8898/callback', '--output=' + str(PRIVATE / 'second-partner.json'))
wp('--user=stage-admin', 'eval-file', str(ROOT / 'tests/dealer-integration/prepare-review.php'))
print('Local fixture ready. Credentials are in ignored private files; none printed.')
