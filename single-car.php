<?php
/**
 * Repository-owned template for individual car listings.
 *
 * @package Bricks_Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once get_stylesheet_directory() . '/includes/single-car/single-car-helpers.php';

$post_id = (int) get_queried_object_id();
if ($post_id <= 0 || get_post_type($post_id) !== 'car') {
    include get_404_template();
    return;
}

$asset_path = get_stylesheet_directory() . '/includes/single-car/';
$asset_url  = get_stylesheet_directory_uri() . '/includes/single-car/';

wp_enqueue_style(
    'autoagora-single-car',
    $asset_url . 'single-car.css',
    array('bricks-child-theme-css'),
    filemtime($asset_path . 'single-car.css')
);
wp_enqueue_script(
    'autoagora-single-car',
    $asset_url . 'single-car.js',
    array(),
    filemtime($asset_path . 'single-car.js'),
    true
);
wp_localize_script(
    'autoagora-single-car',
    'autoagoraSingleCar',
    array(
        'readMore' => __('Read More', 'bricks-child'),
        'readLess' => __('Read Less', 'bricks-child'),
    )
);

$verification_css = get_stylesheet_directory() . '/includes/shortcodes/seller-verification/seller-verification.css';
if (file_exists($verification_css)) {
    wp_enqueue_style(
        'seller-verification-styles',
        get_stylesheet_directory_uri() . '/includes/shortcodes/seller-verification/seller-verification.css',
        array('autoagora-single-car'),
        filemtime($verification_css)
    );
}

if (function_exists('car_card_enqueue_assets')) {
    car_card_enqueue_assets();
}

$year             = autoagora_single_car_field($post_id, 'year');
$make             = autoagora_single_car_field($post_id, 'make');
$model            = autoagora_single_car_field($post_id, 'model');
$mileage          = autoagora_single_car_field($post_id, 'mileage');
$price            = autoagora_single_car_field($post_id, 'price');
$transmission     = autoagora_single_car_field($post_id, 'transmission');
$fuel_type        = autoagora_single_car_field($post_id, 'fuel_type');
$engine_capacity  = autoagora_single_car_field($post_id, 'engine_capacity');
$drive_type       = autoagora_single_car_field($post_id, 'drive_type');
$horsepower       = autoagora_single_car_field($post_id, 'hp');
$body_type        = autoagora_single_car_field($post_id, 'body_type');
$doors            = autoagora_single_car_field($post_id, 'number_of_doors');
$seats            = autoagora_single_car_field($post_id, 'number_of_seats');
$exterior_color   = autoagora_single_car_field($post_id, 'exterior_color');
$interior_color   = autoagora_single_car_field($post_id, 'interior_color');
$description      = autoagora_single_car_field($post_id, 'description');
$owners           = autoagora_single_car_field($post_id, 'numowners');
$mot_until        = autoagora_single_car_field($post_id, 'motuntil');
$is_antique       = autoagora_single_car_field($post_id, 'isantique');
$address          = autoagora_single_car_field($post_id, 'car_address');
$city             = autoagora_single_car_field($post_id, 'car_city');
$extras           = autoagora_single_car_list_labels(autoagora_single_car_list_values(autoagora_single_car_field($post_id, 'extras')), 'extras');
$vehicle_history  = autoagora_single_car_list_labels(autoagora_single_car_list_values(autoagora_single_car_field($post_id, 'vehiclehistory')), 'history');
$terms            = autoagora_single_car_terms($post_id);
$related_query    = autoagora_single_car_related_query($post_id, 8);
$author_id        = (int) get_post_field('post_author', $post_id);
$author           = get_userdata($author_id);
$author_name      = $author ? $author->display_name : '';
$author_url       = $author_id ? get_author_posts_url($author_id) : '';
$city_url         = autoagora_single_car_city_url((string) $city);
$posted_label     = autoagora_single_car_relative_date($post_id);
$formatted_price  = autoagora_single_car_number($price);
$formatted_miles  = autoagora_single_car_number($mileage);
$transmission_display = autoagora_single_car_translated_value($transmission);
$fuel_type_display = autoagora_single_car_translated_value($fuel_type);
$drive_type_display = autoagora_single_car_translated_value($drive_type);
$body_type_display = autoagora_single_car_translated_value($body_type);
$exterior_color_display = autoagora_single_car_translated_value($exterior_color);
$interior_color_display = autoagora_single_car_translated_value($interior_color);
$city_display      = autoagora_single_car_translated_value($city);
$page_title       = get_the_title($post_id);
$gallery_html     = do_shortcode('[single_car_template_gallery post_id="' . (int) $post_id . '"]');
$seller_reviews_html = $author_id
    ? do_shortcode('[seller_reviews seller_id="' . $author_id . '" show_reviews="false" show_form="true"]')
    : '';

if ($page_title === '') {
    $page_title = trim(implode(' ', array_filter(array($year, $make, $model))));
}

$summary_specs = array_filter(
    array(
        $year,
        autoagora_single_car_has_value($mileage) && $formatted_miles !== '' ? $formatted_miles . 'km' : '',
        $transmission_display,
        $fuel_type_display,
    ),
    static function ($value) {
        return autoagora_single_car_has_value($value);
    }
);

$performance_specs = array_filter(
    array(
        array('label' => __('Engine Capacity', 'bricks-child'), 'value' => autoagora_single_car_has_value($engine_capacity) ? $engine_capacity . 'L' : ''),
        array('label' => __('Fuel Type', 'bricks-child'), 'value' => $fuel_type_display),
        array('label' => __('Transmission', 'bricks-child'), 'value' => $transmission_display),
        array('label' => __('Drive Type', 'bricks-child'), 'value' => $drive_type_display),
        array('label' => __('Horsepower', 'bricks-child'), 'value' => autoagora_single_car_has_value($horsepower) ? $horsepower . 'hp' : ''),
    ),
    static function ($spec) {
        return autoagora_single_car_has_value($spec['value']);
    }
);

$design_specs = array_filter(
    array(
        array('label' => __('Body Type', 'bricks-child'), 'value' => $body_type_display),
        array('label' => __('Doors', 'bricks-child'), 'value' => autoagora_single_car_has_value($doors) ? $doors : ''),
        array('label' => __('Seats', 'bricks-child'), 'value' => autoagora_single_car_has_value($seats) ? $seats : ''),
        array('label' => __('Exterior Color', 'bricks-child'), 'value' => $exterior_color_display),
        array('label' => __('Interior Color', 'bricks-child'), 'value' => $interior_color_display),
    ),
    static function ($spec) {
        return autoagora_single_car_has_value($spec['value']);
    }
);

$background_specs = array_filter(
    array(
        array('label' => __('Number of Owners', 'bricks-child'), 'value' => autoagora_single_car_has_value($owners) ? $owners : ''),
        array('label' => __('MOT Until', 'bricks-child'), 'value' => autoagora_single_car_has_value($mot_until) ? autoagora_single_car_mot_label($mot_until) : ''),
        array('label' => __('Registered as an Antique', 'bricks-child'), 'value' => !empty($is_antique) ? __('Yes', 'bricks-child') : ''),
    ),
    static function ($spec) {
        return autoagora_single_car_has_value($spec['value']);
    }
);
$has_background = !empty($background_specs);

get_header();
?>
<main id="primary" class="autoagora-single-car">
    <div class="autoagora-single-car__container">
        <nav class="autoagora-single-car__breadcrumbs" aria-label="<?php esc_attr_e('Breadcrumb', 'bricks-child'); ?>">
            <a href="<?php echo esc_url(autoagora_localized_page_url()); ?>"><?php esc_html_e('Home', 'bricks-child'); ?></a>
            <span aria-hidden="true">/</span>
            <a href="<?php echo esc_url(autoagora_localized_page_url('cars')); ?>"><?php esc_html_e('Cars', 'bricks-child'); ?></a>
            <span aria-hidden="true">/</span>
            <span aria-current="page"><?php echo esc_html($page_title); ?></span>
        </nav>

        <section class="autoagora-single-car__hero" aria-labelledby="single-car-title">
            <div class="autoagora-single-car__gallery">
                <?php echo $gallery_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output. ?>
            </div>

            <aside class="autoagora-single-car__card autoagora-single-car__summary">
                <h1 id="single-car-title"><?php echo esc_html($page_title); ?></h1>

                <?php if ($summary_specs) : ?>
                    <div class="autoagora-single-car__summary-specs">
                        <?php foreach ($summary_specs as $spec) : ?>
                            <span><?php echo esc_html($spec); ?></span>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <?php if (autoagora_single_car_has_value($price) && $formatted_price !== '') : ?>
                    <p class="autoagora-single-car__price">&euro;<?php echo esc_html($formatted_price); ?></p>
                <?php endif; ?>

                <?php if ($posted_label !== '') : ?>
                    <p class="autoagora-single-car__posted"><span aria-hidden="true"></span><?php echo esc_html($posted_label); ?></p>
                <?php endif; ?>

                <div class="autoagora-single-car__seller">
                    <?php if ($author_id) : ?>
                        <?php $logo = do_shortcode('[dealership_logo user_id="' . $author_id . '" size="medium" class="autoagora-single-car__seller-logo-image"]'); ?>
                        <a class="autoagora-single-car__seller-profile" href="<?php echo esc_url($author_url); ?>">
                            <?php if ($logo !== '') : ?>
                                <span class="autoagora-single-car__seller-logo">
                                    <?php echo $logo; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output. ?>
                                </span>
                            <?php endif; ?>
                            <span class="autoagora-single-car__seller-copy">
                                <span class="autoagora-single-car__seller-name"><i class="fa-solid fa-user" aria-hidden="true"></i><?php echo esc_html($author_name); ?></span>
                                <?php echo do_shortcode('[dealership_verified user_id="' . $author_id . '"]'); ?>
                            </span>
                        </a>
                        <?php echo $seller_reviews_html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Shortcode output. ?>
                    <?php endif; ?>

                    <?php if ($address) : ?>
                        <p class="autoagora-single-car__address"><i class="fa-solid fa-location-dot" aria-hidden="true"></i><?php echo esc_html($address); ?></p>
                    <?php endif; ?>

                    <p class="autoagora-single-car__contact-note">ⓘ <?php esc_html_e('Help the seller by mentioning you found this on Autoagora', 'bricks-child'); ?></p>

                    <div class="autoagora-single-car__contact-actions">
                        <?php echo do_shortcode('[car_single_call_button]'); ?>
                        <?php echo do_shortcode('[car_single_whatsapp_button]'); ?>
                    </div>
                </div>

                <div class="autoagora-single-car__utility-row">
                    <div class="autoagora-single-car__views"><i class="fa-regular fa-eye" aria-hidden="true"></i><?php echo do_shortcode('[car_views_counter_single car_id="' . (int) $post_id . '"]'); ?></div>
                    <div class="autoagora-single-car__utility-actions">
                        <?php echo do_shortcode('[share_button design="single" size="normal"]'); ?>
                        <?php echo do_shortcode('[favorite_button car_id="' . (int) $post_id . '" design="single" size="normal"]'); ?>
                        <?php echo do_shortcode('[report_button car_id="' . (int) $post_id . '" design="single" size="normal"]'); ?>
                    </div>
                </div>
            </aside>
        </section>

        <section class="autoagora-single-car__card autoagora-single-car__specification-card" aria-label="<?php esc_attr_e('Car specifications', 'bricks-child'); ?>">
            <div class="autoagora-single-car__spec-tables">
                <?php if ($performance_specs) : ?>
                    <div class="autoagora-single-car__spec-section">
                        <h2><?php esc_html_e('Engine & Performance', 'bricks-child'); ?></h2>
                        <div class="autoagora-single-car__table-wrap">
                            <table class="autoagora-single-car__spec-table">
                                <tbody>
                                    <?php foreach ($performance_specs as $spec) : ?>
                                        <tr>
                                            <th scope="row"><?php echo esc_html($spec['label']); ?></th>
                                            <td><?php echo esc_html($spec['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($design_specs) : ?>
                    <div class="autoagora-single-car__spec-section">
                        <h2><?php esc_html_e('Body & Design', 'bricks-child'); ?></h2>
                        <div class="autoagora-single-car__table-wrap">
                            <table class="autoagora-single-car__spec-table">
                                <tbody>
                                    <?php foreach ($design_specs as $spec) : ?>
                                        <tr>
                                            <th scope="row"><?php echo esc_html($spec['label']); ?></th>
                                            <td><?php echo esc_html($spec['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>

            <?php if (($terms['model'] instanceof WP_Term) || ($city && $city_url)) : ?>
                <hr>
                <div class="autoagora-single-car__browse-links">
                    <?php if ($terms['model'] instanceof WP_Term) : ?>
                        <?php $model_url = get_term_link($terms['model']); ?>
                        <?php if (!is_wp_error($model_url)) : ?>
                            <a href="<?php echo esc_url($model_url); ?>"><?php echo esc_html(sprintf(__('View all %s cars', 'bricks-child'), $terms['model']->name)); ?></a>
                        <?php endif; ?>
                    <?php endif; ?>
                    <?php if ($city && $city_url) : ?>
                        <span><?php echo esc_html(sprintf(__('This car is in %s.', 'bricks-child'), $city_display)); ?></span>
                        <a href="<?php echo esc_url($city_url); ?>"><?php echo esc_html(sprintf(__('View all cars in %s', 'bricks-child'), $city_display)); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        </section>

        <?php if ($description || $has_background || $extras || $vehicle_history) : ?>
            <section class="autoagora-single-car__card autoagora-single-car__details-card">
                <?php if ($description) : ?>
                    <div class="autoagora-single-car__detail-section">
                        <h2><?php esc_html_e('Overview', 'bricks-child'); ?></h2>
                        <div class="autoagora-single-car__description" data-single-car-description>
                            <?php echo wpautop(wp_kses_post($description)); ?>
                        </div>
                        <button type="button" class="btn btn-primary autoagora-single-car__read-more" data-single-car-read-more hidden aria-expanded="false">
                            <?php esc_html_e('Read More', 'bricks-child'); ?>
                        </button>
                    </div>
                <?php endif; ?>

                <?php if ($has_background) : ?>
                    <hr>
                    <div class="autoagora-single-car__detail-section">
                        <h2><?php esc_html_e('Registration & Background Info', 'bricks-child'); ?></h2>
                        <div class="autoagora-single-car__table-wrap">
                            <table class="autoagora-single-car__spec-table">
                                <tbody>
                                    <?php foreach ($background_specs as $spec) : ?>
                                        <tr>
                                            <th scope="row"><?php echo esc_html($spec['label']); ?></th>
                                            <td><?php echo esc_html($spec['value']); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($extras) : ?>
                    <hr>
                    <div class="autoagora-single-car__detail-section">
                        <h2><?php esc_html_e('Extras', 'bricks-child'); ?></h2>
                        <ul class="autoagora-single-car__tag-list">
                            <?php foreach ($extras as $extra) : ?><li><?php echo esc_html($extra); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <?php if ($vehicle_history) : ?>
                    <hr>
                    <div class="autoagora-single-car__detail-section">
                        <h2><?php esc_html_e('Vehicle History', 'bricks-child'); ?></h2>
                        <ul class="autoagora-single-car__tag-list">
                            <?php foreach ($vehicle_history as $history_item) : ?><li><?php echo esc_html($history_item); ?></li><?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>
            </section>
        <?php endif; ?>
    </div>

    <?php if ($related_query instanceof WP_Query && $related_query->have_posts()) : ?>
        <section class="autoagora-single-car__related" aria-labelledby="single-car-related-heading">
            <div class="autoagora-single-car__container">
                <h2 id="single-car-related-heading"><?php esc_html_e('Related Cars', 'bricks-child'); ?></h2>
                <div class="autoagora-single-car__related-grid">
                    <?php
                    $related_index = 0;
                    while ($related_query->have_posts()) :
                        $related_query->the_post();
                        render_car_card(get_the_ID(), array('listing_index' => $related_index));
                        $related_index++;
                    endwhile;
                    wp_reset_postdata();
                    ?>
                </div>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php
get_footer();
