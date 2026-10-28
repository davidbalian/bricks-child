#!/usr/bin/env python3
"""Exercise release activation in the disposable LOCAL fixture; never production."""
import json
import re
import time
import review_checks as r
h=r.h

def main():
    runtime=h.PRIVATE/'runtime.php'; original=runtime.read_text()
    old_option=r.data("get_option('autoagora_dealer_admin_release',false)")
    before=r.data("(function(){global $wpdb;return [(int)$wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->users}\"),(int)$wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->posts}\"),(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\\AutoAgora\\Dealer\\Support::table('clients')),(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\\AutoAgora\\Dealer\\Support::table('grants'))];})()")
    try:
        r.wp("delete_option('autoagora_dealer_admin_release');")
        runtime.write_text(original.replace("define('AUTOAGORA_DEALER_API_ENABLED', true)","define('AUTOAGORA_DEALER_API_ENABLED', false)"))
        time.sleep(3)
        admin=h.login('admin'); dealer=h.login('dealer_one')
        url=h.BASE+'/wp-admin/admin-post.php'
        action={'action':'ag_dealer_install_review'}
        r.check('installer rejects ordinary dealer',h.request(url,'POST',action,session=dealer,form=True)[0],404)
        r.check('installer rejects missing CSRF nonce',h.request(url,'POST',action,session=admin,form=True)[0],403)
        raw=h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-integrations',session=admin)[3]
        nonce=re.search(r'name="_wpnonce" value="([^"]+)"',raw).group(1)
        r.check('installer describes administrator-only activation','Enable administrator-only workspace' in raw)
        result=h.request(url,'POST',{**action,'_wpnonce':nonce},session=admin,form=True)
        (h.PRIVATE/'installation-last-response.html').write_text(result[3])
        r.check('explicit setup installs without fatal',result[0],302)
        r.check('setup persists administrator release boundary',r.data("(bool)get_option('autoagora_dealer_admin_release')"))
        # Simulate an unconfigured host: only the installer provides the module's config.
        minimal=re.sub(r"define\('AUTOAGORA_DEALER_[A-Z_]+',.*?\);\n?",'',original)
        minimal += "\ndefine('AUTOAGORA_DEALER_PUBLIC_ENABLED',true);\n"
        runtime.write_text(minimal)
        time.sleep(3)
        r.check('private release overrides a public-onboarding flag',r.data('\\AutoAgora\\Dealer\\Review::privateMode()'))
        r.check('private host configuration loads successfully',r.data('\\AutoAgora\\Dealer\\Support::ready()'))
        r.check('generated storage is outside served root',str(h.ROOT/'.staging/.autoagora-dealer/media'),r.data('\\AutoAgora\\Dealer\\Review::storage()'))
        r.check('hosted business-rule write gate remains disabled',r.data("defined('AUTOAGORA_DEALER_RULES_VERIFIED') && AUTOAGORA_DEALER_RULES_VERIFIED"),False)
        r.check('activation receipt renders without opening stock',h.request(h.BASE+'/wp-admin/tools.php?page=ag-dealer-integrations&activated=1',session=admin)[0],200)
        r.check('administrator can render activated workspace',r.page(admin)[0],200)
        r.check('visitor cannot see activated workspace',h.request(r.URL)[0],404)
        r.check('dealer cannot see activated workspace',r.page(dealer)[0],404)
        after=r.data("(function(){global $wpdb;return [(int)$wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->users}\"),(int)$wpdb->get_var(\"SELECT COUNT(*) FROM {$wpdb->posts}\"),(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\\AutoAgora\\Dealer\\Support::table('clients')),(int)$wpdb->get_var('SELECT COUNT(*) FROM '.\\AutoAgora\\Dealer\\Support::table('grants'))];})()")
        r.check('activation creates no users, posts, clients or connections',after,before)
        (h.PRIVATE/'installation-results.json').write_text(json.dumps(h.RESULTS,indent=2))
        print('Completed',len(h.RESULTS),'local installation checks.')
    finally:
        runtime.write_text(original)
        r.wp("delete_option('autoagora_dealer_admin_release');" if not old_option else "update_option('autoagora_dealer_admin_release',1,true);")
        time.sleep(3)

if __name__=='__main__': main()
