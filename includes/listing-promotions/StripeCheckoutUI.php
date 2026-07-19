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
        'currency' => 'EUR',
        'locale' => str_replace('_', '-', get_locale()),
        'workingText' => 'Opening secure checkout...',
        'genericError' => 'Checkout could not be started. Please try again.',
    ));
}

function autoagora_render_promotion_purchase_controls($listing_id)
{
    $listing_id = (int) $listing_id;
    if (!AutoAgora_Stripe_Gateway::is_ready()) {
        return;
    }
    if (!AutoAgora_Stripe_Gateway::packages()) {
        return;
    }

    $current_tier = autoagora_get_listing_promotion_tier($listing_id);
    $tier_options = array();
    foreach (AutoAgora_Promotion_Manager::tiers() as $tier_key => $tier_config) {
        $one_day = AutoAgora_Stripe_Gateway::package_for($tier_key, 1);
        if ($one_day) {
            $tier_options[$tier_key] = array(
                'label' => $tier_config['label'],
                'daily_amount_minor' => (int) $one_day['daily_amount_minor'],
                'description' => $tier_key === AutoAgora_Promotion_Manager::TIER_SHOWCASE
                    ? 'Maximum visibility above Lift and regular listings'
                    : 'Stand out above regular listings',
            );
        }
    }
    if (!$tier_options) {
        return;
    }

    $default_tier = isset($tier_options[$current_tier]) ? $current_tier : AutoAgora_Promotion_Manager::TIER_PRIORITY;
    if (!isset($tier_options[$default_tier])) {
        $default_tier = (string) array_key_first($tier_options);
    }
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

        <div class="autoagora-promotion-purchase-panel" data-currency="EUR">
            <?php if ($is_test) : ?>
                <strong class="autoagora-stripe-test-label">Stripe sandbox test</strong>
            <?php endif; ?>

            <div class="autoagora-promotion-panel-heading">
                <strong>Choose your promotion</strong>
                <span>Select a visibility level and duration.</span>
            </div>

            <div class="autoagora-promotion-tier-options" role="group" aria-label="Promotion type">
                <?php foreach ($tier_options as $tier_key => $option) : ?>
                    <?php $is_selected = $tier_key === $default_tier; ?>
                    <button type="button"
                            class="autoagora-promotion-tier-option<?php echo $is_selected ? ' is-selected' : ''; ?>"
                            data-tier="<?php echo esc_attr($tier_key); ?>"
                            data-label="<?php echo esc_attr($option['label']); ?>"
                            data-daily-amount="<?php echo esc_attr($option['daily_amount_minor']); ?>"
                            aria-pressed="<?php echo $is_selected ? 'true' : 'false'; ?>">
                        <span class="autoagora-promotion-option-check" aria-hidden="true"><i class="fas fa-check"></i></span>
                        <strong><?php echo esc_html($option['label']); ?></strong>
                        <small><?php echo esc_html($option['description']); ?></small>
                        <span class="autoagora-promotion-daily-price">
                            &euro;<?php echo esc_html(number_format_i18n($option['daily_amount_minor'] / 100, 2)); ?> / day
                        </span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="autoagora-promotion-duration-heading">
                <strong>Duration</strong>
                <span>Each day is 24 hours</span>
            </div>
            <div class="autoagora-promotion-day-options" role="group" aria-label="Promotion duration">
                <?php foreach (AutoAgora_Stripe_Gateway::allowed_days() as $days) : ?>
                    <button type="button"
                            class="autoagora-promotion-day-option<?php echo $days === 1 ? ' is-selected' : ''; ?>"
                            data-days="<?php echo esc_attr($days); ?>"
                            aria-pressed="<?php echo $days === 1 ? 'true' : 'false'; ?>">
                        <strong><?php echo esc_html($days); ?></strong>
                        <span><?php echo esc_html(_n('day', 'days', $days, 'bricks-child')); ?></span>
                    </button>
                <?php endforeach; ?>
            </div>

            <div class="autoagora-promotion-total">
                <span>
                    <small>Total</small>
                    <strong class="autoagora-promotion-total-label"><?php echo esc_html($tier_options[$default_tier]['label']); ?> for 1 day</strong>
                </span>
                <strong class="autoagora-promotion-total-amount">
                    &euro;<?php echo esc_html(number_format_i18n($tier_options[$default_tier]['daily_amount_minor'] / 100, 2)); ?>
                </strong>
            </div>

            <button type="button"
                    class="btn btn-primary-gradient autoagora-buy-promotion"
                    data-listing-id="<?php echo esc_attr($listing_id); ?>">
                <i class="fas fa-lock" aria-hidden="true"></i>
                <span>Continue to secure checkout</span>
            </button>
            <span class="autoagora-promotion-checkout-status" role="status" aria-live="polite"></span>
        </div>
    </details>
    <?php
}
