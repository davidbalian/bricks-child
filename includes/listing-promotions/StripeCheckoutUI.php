<?php
/**
 * Seller-facing Stripe Checkout controls for the My Listings page.
 */
if (!defined('ABSPATH')) {
    exit;
}

function autoagora_enqueue_stripe_checkout_assets()
{
    $base_path = __DIR__ . '/';
    $base_url = get_stylesheet_directory_uri() . '/includes/listing-promotions/';
    wp_enqueue_style(
        'autoagora-stripe-checkout',
        $base_url . 'stripe-checkout.css',
        array('bricks-child-my-listings-css'),
        file_exists($base_path . 'stripe-checkout.css') ? filemtime($base_path . 'stripe-checkout.css') : BRICKS_CHILD_THEME_VERSION
    );
    wp_enqueue_script(
        'autoagora-stripe-checkout',
        $base_url . 'stripe-checkout.js',
        array(),
        file_exists($base_path . 'stripe-checkout.js') ? filemtime($base_path . 'stripe-checkout.js') : BRICKS_CHILD_THEME_VERSION,
        true
    );
    wp_localize_script('autoagora-stripe-checkout', 'autoAgoraStripeCheckout', array(
        'ajaxUrl' => admin_url('admin-ajax.php'),
        'action' => AutoAgora_Stripe_Gateway::CHECKOUT_AJAX_ACTION,
        'nonce' => wp_create_nonce(AutoAgora_Stripe_Gateway::CHECKOUT_NONCE_ACTION),
        'workingText' => 'Opening secure checkout…',
        'genericError' => 'Checkout could not be started. Please try again.',
    ));
}

function autoagora_render_promotion_purchase_controls($listing_id)
{
    $listing_id = (int) $listing_id;
    if (!AutoAgora_Stripe_Gateway::is_ready()) {
        return;
    }
    $packages = AutoAgora_Stripe_Gateway::packages();
    if (!$packages) {
        return;
    }
    $current_tier = autoagora_get_listing_promotion_tier($listing_id);
    $is_test = AutoAgora_Stripe_Gateway::mode() === 'test';
    ?>
    <details class="autoagora-promotion-purchase">
        <summary class="btn btn-primary-gradient autoagora-promotion-trigger">
            <span class="autoagora-promotion-trigger-icon" aria-hidden="true">
                <i class="fas fa-bolt"></i>
            </span>
            <span><?php echo esc_html($current_tier === 'none' ? 'Promote listing' : 'Extend promotion'); ?></span>
            <i class="fas fa-chevron-up autoagora-promotion-trigger-chevron" aria-hidden="true"></i>
        </summary>
        <div class="autoagora-promotion-purchase-panel">
            <?php if ($is_test) : ?>
                <strong class="autoagora-stripe-test-label">Stripe sandbox test</strong>
            <?php endif; ?>
            <?php foreach ($packages as $package) : ?>
                <button type="button"
                        class="autoagora-buy-promotion"
                        data-listing-id="<?php echo esc_attr($listing_id); ?>"
                        data-tier="<?php echo esc_attr($package['tier']); ?>">
                    <span><?php echo esc_html($package['label']); ?></span>
                    <small>&euro;<?php echo esc_html(number_format_i18n($package['amount_minor'] / 100, 2)); ?> · <?php echo esc_html(AutoAgora_Stripe_Gateway::duration_label($package['duration_seconds'])); ?></small>
                </button>
            <?php endforeach; ?>
            <span class="autoagora-promotion-checkout-status" role="status" aria-live="polite"></span>
        </div>
    </details>
    <?php
}
