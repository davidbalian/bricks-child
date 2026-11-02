<?php
defined('ABSPATH') || exit;
use AutoAgora\Dealer\DealerDirectory;
?>
<div class="wrap"><h1>Seller onboarding</h1>
<p>Find existing dealership accounts before onboarding. A match does not grant access. Verify the requester using an independently known contact, then arrange their existing login; they must complete OAuth consent themselves.</p>
<form method="post"><?php wp_nonce_field('ag-onboarding-review'); ?><input type="hidden" name="operation" value="search">
<label>Dealership name <input class="regular-text" name="dealership_name" required minlength="3" maxlength="120" value="<?php echo esc_attr($name); ?>"></label>
<label>City <input name="dealership_city" maxlength="80" value="<?php echo esc_attr($city); ?>"></label> <button class="button button-primary">Find existing accounts</button></form>
<?php if ($name !== '') : ?><h2>Possible matches</h2><?php if (!$matches) : ?><p>No close matches found.</p><?php else : ?><table class="widefat striped"><thead><tr><th>Dealership</th><th>City</th><th>Name similarity</th><th>Account</th></tr></thead><tbody>
<?php foreach ($matches as $match) : ?><tr><td><?php echo esc_html($match['name']); ?></td><td><?php echo esc_html($match['city'] ?: '—'); ?></td><td><?php echo (int) $match['score']; ?>%</td><td><a href="<?php echo esc_url(get_edit_user_link($match['id'])); ?>">Review account #<?php echo (int) $match['id']; ?></a></td></tr><?php endforeach; ?></tbody></table><?php endif; endif; ?>
<h2>Access requests <span class="count">(<?php echo (int) $requests->found_posts; ?>)</span></h2>
<?php if (!$requests->posts) : ?><p>No pending requests.</p><?php endif; ?>
<?php foreach ($requests->posts as $request) :
    $meta = static fn ($key) => (string) get_post_meta($request->ID, '_ag_onboard_' . $key, true);
    $privateSeller = $meta('account_type') === 'private';
    $candidates = $privateSeller ? [] : DealerDirectory::search($request->post_title, $meta('city'), true);
?>
<details style="background:white;border:1px solid #c3c4c7;padding:16px;margin:12px 0;max-width:1000px"><summary><strong><?php echo esc_html($request->post_title); ?></strong> · <?php echo esc_html($meta('reference')); ?></summary>
<p><?php echo esc_html($meta('contact_name')); ?> · <?php echo esc_html($meta('contact')); ?> · <?php echo esc_html($meta('city')); ?></p>
<p>Account type: <?php echo $privateSeller ? 'Private seller' : 'Dealership'; ?> · Application: <?php echo esc_html($meta('client')); ?> · Submitted: <?php echo esc_html($request->post_date); ?></p>
<?php if (!$privateSeller) : ?><h3>Possible existing accounts</h3><ul><?php foreach ($candidates as $candidate) : ?><li><a href="<?php echo esc_url(get_edit_user_link($candidate['id'])); ?>"><?php echo esc_html($candidate['name']); ?></a> — <?php echo esc_html($candidate['city']); ?> (<?php echo (int) $candidate['score']; ?>%)</li><?php endforeach; ?></ul>
<?php if (!$candidates) : ?><p>No close account matches. Check the dealer’s details before creating an account through the existing Dealerships tool.</p><?php endif; else : ?><p>Verify the existing private seller through an independently known contact. Personal accounts are not exposed through fuzzy name search.</p><?php endif; ?>
<form method="post"><?php wp_nonce_field('ag-onboarding-review'); ?><input type="hidden" name="operation" value="resolve"><input type="hidden" name="request_id" value="<?php echo (int) $request->ID; ?>"><button class="button">Mark reviewed</button></form>
</details><?php endforeach; ?>
<?php echo wp_kses_post(paginate_links(['base'=>add_query_arg('paged','%#%',admin_url('tools.php?page=ag-dealer-onboarding')),'total'=>$requests->max_num_pages,'current'=>$page])); ?>
</div>
