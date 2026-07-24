<?php
/**
 * Single dealer profile template.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

$post_id = get_queried_object_id();
$listings_query = function_exists('autoagora_dealer_profile_active_listings_query')
    ? autoagora_dealer_profile_active_listings_query((int) $post_id, 12)
    : null;

if ($listings_query instanceof WP_Query && $listings_query->have_posts() && function_exists('car_card_enqueue_assets')) {
    car_card_enqueue_assets();
}

get_header();

if (have_posts()) :
    while (have_posts()) :
        the_post();

        $post_id = get_the_ID();
        $city = autoagora_dealer_profile_get_meta($post_id, 'dealer_city');
        $district = autoagora_dealer_profile_get_meta($post_id, 'dealer_district');
        $address = autoagora_dealer_profile_get_meta($post_id, 'dealer_address');
        $maps_address = autoagora_dealer_profile_get_meta($post_id, 'dealer_maps_address');
        $display_address = $address !== '' ? $address : $maps_address;
        $short_description = autoagora_dealer_profile_get_meta($post_id, 'dealer_short_description');
        $opening_hours = autoagora_dealer_profile_get_meta($post_id, 'dealer_opening_hours');
        $services = autoagora_dealer_profile_get_meta($post_id, 'dealer_services');
        $languages = autoagora_dealer_profile_get_meta($post_id, 'dealer_languages');
        $logo_url = autoagora_dealer_profile_get_meta($post_id, 'dealer_logo_url');
        $last_verified = autoagora_dealer_profile_get_meta($post_id, 'dealer_last_verified_at');
        $is_claimed = autoagora_dealer_profile_is_claimed($post_id);
        $links = autoagora_dealer_profile_contact_links($post_id);
        $listing_count = autoagora_dealer_profile_active_listing_count($post_id);
        ?>
        <main class="dealer-profile-single">
            <section class="dealer-profile-hero">
                <div class="dealer-profile-hero__content">
                    <div class="dealer-profile-hero__eyebrow">
                        <span class="dealer-profile-badge <?php echo $is_claimed ? 'is-claimed' : 'is-unclaimed'; ?>">
                            <?php echo esc_html($is_claimed ? __('Claimed dealer', 'bricks-child') : __('Unclaimed profile', 'bricks-child')); ?>
                        </span>
                        <?php if ($city !== '') : ?>
                            <span><?php echo esc_html($city); ?></span>
                        <?php endif; ?>
                    </div>
                    <h1><?php the_title(); ?></h1>
                    <?php if ($display_address !== '' || $city !== '' || $district !== '') : ?>
                        <p class="dealer-profile-hero__location">
                            <?php echo esc_html(implode(', ', array_filter(array($display_address, $city, $district)))); ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($short_description !== '') : ?>
                        <p class="dealer-profile-hero__summary"><?php echo esc_html($short_description); ?></p>
                    <?php else : ?>
                        <p class="dealer-profile-hero__summary">
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: 1: dealer name, 2: active listing count */
                                    _n('%1$s has %2$d active listing on AutoAgora.', '%1$s has %2$d active listings on AutoAgora.', $listing_count, 'bricks-child'),
                                    get_the_title(),
                                    $listing_count
                                )
                            );
                            ?>
                        </p>
                    <?php endif; ?>
                    <div class="dealer-profile-actions">
                        <?php foreach ($links as $link) : ?>
                            <a
                                class="btn <?php echo $link['key'] === 'dealer_phone' ? 'btn-primary' : 'btn-secondary'; ?>"
                                href="<?php echo esc_url($link['url']); ?>"
                                target="<?php echo (strpos($link['url'], 'tel:') === 0 || strpos($link['url'], 'mailto:') === 0) ? '_self' : '_blank'; ?>"
                                rel="noopener noreferrer"
                            >
                                <?php echo esc_html($link['label']); ?>
                            </a>
                        <?php endforeach; ?>
                        <?php if (!$is_claimed) : ?>
                            <a class="btn btn-link" href="<?php echo esc_url(autoagora_dealer_profile_claim_url($post_id)); ?>">
                                <?php esc_html_e('Claim this dealership', 'bricks-child'); ?>
                            </a>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="dealer-profile-hero__logo">
                    <?php if (has_post_thumbnail()) : ?>
                        <?php the_post_thumbnail('medium_large'); ?>
                    <?php elseif ($logo_url !== '') : ?>
                        <img src="<?php echo esc_url($logo_url); ?>" alt="<?php echo esc_attr(get_the_title()); ?>">
                    <?php else : ?>
                        <?php
                        $dealer_initial = function_exists('mb_substr')
                            ? mb_substr(get_the_title(), 0, 1, 'UTF-8')
                            : substr(get_the_title(), 0, 1);
                        ?>
                        <span><?php echo esc_html($dealer_initial); ?></span>
                    <?php endif; ?>
                </div>
            </section>

            <?php if (!$is_claimed) : ?>
                <section class="dealer-profile-claim">
                    <div>
                        <h2><?php esc_html_e('Own this dealership?', 'bricks-child'); ?></h2>
                        <p><?php esc_html_e('Claim the profile to manage business details and connect your AutoAgora listings.', 'bricks-child'); ?></p>
                    </div>
                    <a class="btn btn-primary" href="<?php echo esc_url(autoagora_dealer_profile_claim_url($post_id)); ?>">
                        <?php esc_html_e('Start claim', 'bricks-child'); ?>
                    </a>
                </section>
            <?php endif; ?>

            <section class="dealer-profile-details">
                <div class="dealer-profile-details__main">
                    <h2><?php esc_html_e('About this dealer', 'bricks-child'); ?></h2>
                    <?php if (trim(get_the_content()) !== '') : ?>
                        <div class="dealer-profile-content">
                            <?php the_content(); ?>
                        </div>
                    <?php elseif ($short_description !== '') : ?>
                        <p><?php echo esc_html($short_description); ?></p>
                    <?php else : ?>
                        <p><?php esc_html_e('This dealer profile includes public business information for Cyprus car shoppers.', 'bricks-child'); ?></p>
                    <?php endif; ?>
                    <?php if ($services !== '') : ?>
                        <div class="dealer-profile-text-block">
                            <h3><?php esc_html_e('Services', 'bricks-child'); ?></h3>
                            <p><?php echo nl2br(esc_html($services)); ?></p>
                        </div>
                    <?php endif; ?>
                </div>

                <aside class="dealer-profile-facts" aria-label="<?php esc_attr_e('Dealer profile facts', 'bricks-child'); ?>">
                    <h2><?php esc_html_e('Profile details', 'bricks-child'); ?></h2>
                    <?php if ($city !== '') : ?>
                        <div><span><?php esc_html_e('City', 'bricks-child'); ?></span><strong><?php echo esc_html($city); ?></strong></div>
                    <?php endif; ?>
                    <?php if ($district !== '') : ?>
                        <div><span><?php esc_html_e('District', 'bricks-child'); ?></span><strong><?php echo esc_html($district); ?></strong></div>
                    <?php endif; ?>
                    <?php if ($opening_hours !== '') : ?>
                        <div><span><?php esc_html_e('Opening hours', 'bricks-child'); ?></span><strong><?php echo nl2br(esc_html($opening_hours)); ?></strong></div>
                    <?php endif; ?>
                    <?php if ($languages !== '') : ?>
                        <div><span><?php esc_html_e('Languages', 'bricks-child'); ?></span><strong><?php echo esc_html($languages); ?></strong></div>
                    <?php endif; ?>
                    <div>
                        <span><?php esc_html_e('Profile', 'bricks-child'); ?></span>
                        <strong><?php echo esc_html($is_claimed ? __('Claimed', 'bricks-child') : __('Unclaimed', 'bricks-child')); ?></strong>
                    </div>
                    <?php if ($last_verified !== '') : ?>
                        <div><span><?php esc_html_e('Last checked', 'bricks-child'); ?></span><strong><?php echo esc_html($last_verified); ?></strong></div>
                    <?php endif; ?>
                    <div><span><?php esc_html_e('Active listings', 'bricks-child'); ?></span><strong><?php echo esc_html(number_format_i18n($listing_count)); ?></strong></div>
                </aside>
            </section>

            <section class="dealer-profile-listings">
                <div class="dealer-profile-section-heading">
                    <h2><?php esc_html_e('Active listings', 'bricks-child'); ?></h2>
                    <?php if ($is_claimed && $listing_count > 0) : ?>
                        <a href="<?php echo esc_url(add_query_arg('user_id', autoagora_dealer_profile_get_claimed_user_id($post_id), home_url('/cars/'))); ?>">
                            <?php esc_html_e('View all', 'bricks-child'); ?>
                        </a>
                    <?php endif; ?>
                </div>

                <?php if ($listings_query instanceof WP_Query && $listings_query->have_posts()) : ?>
                    <div class="car-listings-wrapper dealer-profile-listings__grid">
                        <?php
                        $listing_index = 0;
                        while ($listings_query->have_posts()) :
                            $listings_query->the_post();
                            render_car_card(get_the_ID(), array('listing_index' => $listing_index));
                            ++$listing_index;
                        endwhile;
                        wp_reset_postdata();
                        ?>
                    </div>
                <?php else : ?>
                    <div class="dealer-profile-empty">
                        <h3><?php esc_html_e('No active listings yet', 'bricks-child'); ?></h3>
                        <p><?php esc_html_e('Check back later for cars from this dealer, or browse all used cars in Cyprus.', 'bricks-child'); ?></p>
                        <a class="btn btn-secondary" href="<?php echo esc_url(home_url('/cars/')); ?>">
                            <?php esc_html_e('Browse cars', 'bricks-child'); ?>
                        </a>
                    </div>
                <?php endif; ?>
            </section>
        </main>
        <?php
    endwhile;
else :
    status_header(404);
    include get_404_template();
endif;

get_footer();
