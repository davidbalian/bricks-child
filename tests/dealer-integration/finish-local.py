#!/usr/bin/env python3
"""Resume fixture installation without replacing the database or its credentials."""
import json
import os
from pathlib import Path
import shutil
import subprocess

ROOT = Path(__file__).resolve().parents[2]
WEB = ROOT / '.staging/wp'
PRIVATE = ROOT / '.private'
os.environ['AUTOAGORA_SOURCE_ROOT'] = str(ROOT)
accounts = json.loads((PRIVATE / 'test-accounts.json').read_text())
def wp(*args):
    result = subprocess.run(['wp', '--path=' + str(WEB), *args])
    if result.returncode: raise SystemExit('A local installation step failed. See the preceding non-sensitive error.')

shutil.copyfile(ROOT / 'tests/dealer-integration/staging-safety.php', WEB / 'wp-content/mu-plugins/ag-fixture.php')
subprocess.run(['python3', str(ROOT / 'tests/dealer-integration/sync-local.py')], check=True)
installed = subprocess.run(['wp', '--path=' + str(WEB), 'core', 'is-installed'], stdout=subprocess.DEVNULL, stderr=subprocess.DEVNULL).returncode == 0
if not installed:
    wp('core', 'install', '--url=http://127.0.0.1:8899', '--title=AutoAgora integration staging', '--admin_user=' + accounts['admin']['username'], '--admin_password=' + accounts['admin']['password'], '--admin_email=admin@example.invalid', '--skip-email')
wp('plugin', 'activate', 'advanced-custom-fields-pro')
wp('theme', 'activate', 'bricks-child')
wp('autoagora', 'dealer', 'install')
wp('eval-file', str(ROOT / 'tests/dealer-integration/seed.php'))
for client_id, label, filename in [('stock-partner-test', 'Stock partner test', 'partner.json'), ('second-partner-test', 'Second partner test', 'second-partner.json')]:
    if not (PRIVATE / filename).exists():
        wp('autoagora', 'dealer', 'client', client_id, '--name=' + label, '--redirect=http://127.0.0.1:8898/callback', '--output=' + str(PRIVATE / filename))
wp('--user=stage-admin', 'eval-file', str(ROOT / 'tests/dealer-integration/prepare-review.php'))
print('Local fixture installation complete; credentials remain private.')
