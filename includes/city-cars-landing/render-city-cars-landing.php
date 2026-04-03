<?php
/**
 * Renders the city cars browse page (TCP layout, car_city filter, preset map radius).
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/CityCarsLandingCatalog.php';

/**
 * @param string $slug Catalog key: nicosia, limassol, larnaca, paphos.
 */
function autoagora_render_city_cars_landing($slug) {
    $city = CityCarsLandingCatalog::get($slug);
    if (!$city) {
        status_header(404);
        nocache_headers();
        include get_404_template();
        return;
    }

    $group       = 'city-cars-landing-' . sanitize_html_class($city['slug']);
    $listings_id = 'city-cars-landing-' . sanitize_html_class($city['slug']);

    $listing_atts = array(
        'posts_per_page'     => 24,
        'offset'             => 0,
        'featured'           => 'false',
        'favorites'          => 'false',
        'user_id'            => '',
        'author'             => '',
        'orderby'            => 'date',
        'order'              => 'DESC',
        'show_sold'          => 'false',
        'id'                 => $listings_id,
        'filter_group'       => $group,
        'card_type'          => 'car_card',
        'default_make_slug'  => '',
        'default_model_slug' => '',
        'default_car_city'   => $city['car_city_value'],
        'layout'             => 'grid',
        'infinite_scroll'    => 'false',
    );

    $listing_atts = car_listings_apply_request_sort_to_atts($listing_atts);
    $query_args   = car_listings_build_query_args($listing_atts);
    $cars_query   = car_listings_execute_query($query_args);
    $current_page = max(1, (int) $cars_query->get('paged'));

    if (function_exists('car_card_enqueue_assets')) {
        car_card_enqueue_assets();
    }

    get_header();

    $car_city_esc = esc_attr($city['car_city_value']);
    ?>

<!-- Filters bar -->
<div class="tcp-filters-bar">
    <div class="tcp-filters-bar-inner">
        <button type="button" class="tcp-filters-btn" id="tcp-filters-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/><circle cx="4" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="8" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
            Filters
        </button>
        <button type="button" class="tcp-filters-btn" id="tcp-location-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 21s7-5.373 7-11a7 7 0 1 0-14 0c0 5.627 7 11 7 11z"/><circle cx="12" cy="10" r="2.5"/></svg>
            Location
        </button>
        <div class="tcp-active-filters" id="tcp-active-filters"></div>

        <div class="tcp-sort" id="tcp-sort">
            <button type="button" class="tcp-sort-btn" id="tcp-sort-btn">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M11 5h10"/><path d="M11 9h7"/><path d="M11 13h4"/><path d="M3 17l3 3 3-3"/><path d="M6 18V4"/></svg>
                <span id="tcp-sort-label"><?php esc_html_e('Newest', 'bricks-child'); ?></span>
            </button>
            <div class="tcp-sort-menu" id="tcp-sort-menu">
                <button type="button" class="tcp-sort-option selected" data-orderby="date" data-order="DESC"><?php esc_html_e('Newest', 'bricks-child'); ?></button>
                <button type="button" class="tcp-sort-option" data-orderby="date" data-order="ASC"><?php esc_html_e('Oldest', 'bricks-child'); ?></button>
                <button type="button" class="tcp-sort-option" data-orderby="price" data-order="ASC"><?php esc_html_e('Price: Low to High', 'bricks-child'); ?></button>
                <button type="button" class="tcp-sort-option" data-orderby="price" data-order="DESC"><?php esc_html_e('Price: High to Low', 'bricks-child'); ?></button>
                <button type="button" class="tcp-sort-option" data-orderby="mileage" data-order="ASC"><?php esc_html_e('Mileage: Low to High', 'bricks-child'); ?></button>
                <button type="button" class="tcp-sort-option" data-orderby="mileage" data-order="DESC"><?php esc_html_e('Mileage: High to Low', 'bricks-child'); ?></button>
                <button type="button" class="tcp-sort-option" data-orderby="year" data-order="DESC"><?php esc_html_e('Year: Newest', 'bricks-child'); ?></button>
                <button type="button" class="tcp-sort-option" data-orderby="year" data-order="ASC"><?php esc_html_e('Year: Oldest', 'bricks-child'); ?></button>
            </div>
        </div>
    </div>
</div>

<!-- Filters modal -->
<div class="tcp-filters-modal-overlay" id="tcp-filters-modal-overlay">
    <div class="tcp-filters-modal">
        <div class="tcp-filters-modal-header">
            <h2><?php esc_html_e('Filters', 'bricks-child'); ?></h2>
            <button type="button" class="tcp-filters-modal-close" id="tcp-filters-modal-close" aria-label="<?php esc_attr_e('Close', 'bricks-child'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="tcp-filters-modal-body">
            <?php
            echo do_shortcode(
                sprintf(
                    '[car_filters filters="make,model,price,mileage,fuel,body,year" mode="ajax" target="%1$s" layout="vertical" show_button="false" group="%2$s" city_landing="true" default_car_city="%3$s" results_base_url="/cars/"]',
                    esc_attr($listings_id),
                    esc_attr($group),
                    $car_city_esc
                )
            );
            ?>
        </div>
        <div class="tcp-filters-modal-footer">
            <button type="button" class="tcp-modal-apply-btn" id="tcp-modal-apply-btn"><?php esc_html_e('Apply Filters', 'bricks-child'); ?></button>
            <button type="button" class="tcp-modal-clear-btn" id="tcp-modal-clear-btn"><?php esc_html_e('Clear All', 'bricks-child'); ?></button>
        </div>
    </div>
</div>

<!-- Location modal -->
<div class="tcp-filters-modal-overlay" id="tcp-location-modal-overlay">
    <div class="tcp-filters-modal tcp-location-modal">
        <div class="tcp-filters-modal-header">
            <h2><?php esc_html_e('Location Radius', 'bricks-child'); ?></h2>
            <button type="button" class="tcp-filters-modal-close" id="tcp-location-modal-close" aria-label="<?php esc_attr_e('Close', 'bricks-child'); ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="tcp-filters-modal-body">
            <div class="tcp-location-search-wrap">
                <input type="text" id="tcp-location-search" class="tcp-location-search" placeholder="<?php esc_attr_e('Search location in Cyprus', 'bricks-child'); ?>">
            </div>
            <div class="tcp-location-map-wrap">
                <div class="tcp-location-map" id="tcp-location-map"></div>
                <div class="tcp-location-center-pin" aria-hidden="true"></div>
            </div>
        </div>
        <div class="tcp-filters-modal-footer">
            <div class="tcp-location-radius-row">
                <div class="tcp-location-radius-presets">
                    <button type="button" class="tcp-radius-preset" data-radius="5">5 km</button>
                    <button type="button" class="tcp-radius-preset" data-radius="10">10 km</button>
                    <button type="button" class="tcp-radius-preset" data-radius="25">25 km</button>
                    <button type="button" class="tcp-radius-preset" data-radius="50">50 km</button>
                    <button type="button" class="tcp-radius-preset" data-radius="100">100 km</button>
                    <button type="button" class="tcp-radius-preset" data-radius="200">200 km</button>
                </div>
            </div>
            <button type="button" class="tcp-modal-apply-btn" id="tcp-location-apply-btn"><?php esc_html_e('Apply Location', 'bricks-child'); ?></button>
            <button type="button" class="tcp-modal-clear-btn" id="tcp-location-clear-btn"><?php esc_html_e('Clear Location', 'bricks-child'); ?></button>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="tcp-main">
    <h1 class="tcp-heading"><?php echo esc_html($city['h1']); ?></h1>
    <p class="tcp-heading-browse-all">
        <?php echo esc_html($city['browse_lead']); ?>
        <a class="tcp-heading-browse-all-link" href="<?php echo esc_url(trailingslashit(home_url('/cars/'))); ?>">
            <?php echo esc_html($city['browse_link']); ?>
        </a>
    </p>
    <p class="tcp-results-count" id="tcp-results-count">
        <?php echo esc_html(number_format_i18n((int) $cars_query->found_posts) . ' ' . __('results found', 'bricks-child')); ?>
    </p>
    <div class="car-listings-container"
         id="<?php echo esc_attr($listings_id); ?>"
         data-atts="<?php echo esc_attr(wp_json_encode($listing_atts)); ?>"
         data-page="<?php echo esc_attr((string) $current_page); ?>"
         data-max-pages="<?php echo esc_attr($cars_query->max_num_pages); ?>"
         data-server-filtered="true">

        <div class="car-listings-wrapper tcp-grid">
            <?php
            if ($cars_query->have_posts()) :
                $post_ids = wp_list_pluck($cars_query->posts, 'ID');
                update_postmeta_cache($post_ids);

                $listing_card_index = 0;
                while ($cars_query->have_posts()) :
                    $cars_query->the_post();
                    render_car_card(get_the_ID(), array('listing_index' => $listing_card_index));
                    $listing_card_index++;
                endwhile;
            else :
                ?>
                <p class="car-listings-no-results"><?php esc_html_e('No listings match this selection yet.', 'bricks-child'); ?></p>
                <?php
            endif;
            wp_reset_postdata();
            ?>
        </div>

        <div class="tcp-pagination">
            <?php
            if ($cars_query->max_num_pages > 1) {
                echo paginate_links(
                    array(
                        'total'     => $cars_query->max_num_pages,
                        'current'   => $current_page,
                        'prev_text' => __('Previous', 'bricks-child'),
                        'next_text' => __('Next', 'bricks-child'),
                        'type'      => 'list',
                        'base'      => '#%#%',
                        'format'    => '%#%',
                    )
                );
            }
            ?>
        </div>
    </div>

    <?php if (!empty($city['intro']) && is_array($city['intro'])) : ?>
    <div class="cars-seo-content">
        <section class="cars-intro">
            <h2 class="cars-intro-heading"><?php echo esc_html($city['intro_heading']); ?></h2>
            <?php foreach ($city['intro'] as $paragraph) : ?>
                <p><?php echo esc_html($paragraph); ?></p>
            <?php endforeach; ?>
        </section>
    </div>
    <?php endif; ?>
</div>
<?php
get_footer();
}
