<?php
/**
 * Template Name: Custom Homepage
 * Template Post Type: page
 *
 * Repository-owned recreation of the AutoAgora homepage.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

$homepage_css = get_stylesheet_directory() . '/assets/css/custom-homepage.css';
$homepage_js  = get_stylesheet_directory() . '/assets/js/custom-homepage.js';

wp_enqueue_style(
    'autoagora-custom-homepage',
    get_stylesheet_directory_uri() . '/assets/css/custom-homepage.css',
    array('bricks-child-theme-css'),
    file_exists($homepage_css) ? filemtime($homepage_css) : '1.0.0'
);

wp_enqueue_script(
    'autoagora-custom-homepage',
    get_stylesheet_directory_uri() . '/assets/js/custom-homepage.js',
    array(),
    file_exists($homepage_js) ? filemtime($homepage_js) : '1.0.0',
    true
);

// Resolve shortcode/component assets in <head> instead of discovering them mid-render.
if (function_exists('car_filters_enqueue_assets')) {
    car_filters_enqueue_assets();
}
if (function_exists('car_card_enqueue_assets')) {
    car_card_enqueue_assets();
}

if (!function_exists('autoagora_custom_homepage_query')) {
    /**
     * Query active marketplace cars for one homepage collection.
     *
     * @param array $meta_clauses Additional WP_Query meta clauses.
     * @return WP_Query
     */
    function autoagora_custom_homepage_query(array $meta_clauses = array())
    {
        $args = array(
            'post_type'                     => 'car',
            'post_status'                   => 'publish',
            'posts_per_page'                => 8,
            'orderby'                       => 'date',
            'order'                         => 'DESC',
            '_car_listings_orderby'         => 'date',
            'ignore_sticky_posts'           => true,
            'no_found_rows'                 => true,
            'car_listing_state_active_only' => true,
        );

        if (!empty($meta_clauses)) {
            $args['meta_query'] = array_merge(array('relation' => 'AND'), $meta_clauses);
        }

        // Use the marketplace query pipeline so active paid promotions are ordered
        // first using the same tier/status/expiry rules as the /cars page.
        $query = function_exists('car_listings_execute_query')
            ? car_listings_execute_query($args)
            : new WP_Query($args);

        if (!empty($query->posts)) {
            update_postmeta_cache(wp_list_pluck($query->posts, 'ID'));
            update_post_thumbnail_cache($query);
        }

        return $query;
    }
}

if (!function_exists('autoagora_custom_homepage_collection')) {
    /**
     * Render a dynamic homepage car collection.
     *
     * @param string   $title        Visible heading.
     * @param string   $view_all_url Collection destination.
     * @param string   $view_all     Link label.
     * @param array    $meta_clauses Additional query filters.
     * @param bool     $is_latest    Use the larger latest-cars layout.
     * @return void
     */
    function autoagora_custom_homepage_collection($title, $view_all_url, $view_all, array $meta_clauses = array(), $is_latest = false)
    {
        $query      = autoagora_custom_homepage_query($meta_clauses);
        $section_id = 'homepage-cars-' . wp_unique_id();
        ?>
        <section class="custom-homepage-section custom-homepage-cars-section<?php echo $is_latest ? ' is-latest' : ''; ?>" aria-labelledby="<?php echo esc_attr($section_id); ?>-title">
            <div class="custom-homepage-container">
                <div class="custom-homepage-section-heading">
                    <h2 id="<?php echo esc_attr($section_id); ?>-title"><?php echo esc_html($title); ?></h2>
                    <?php if (!$is_latest) : ?>
                        <div class="custom-homepage-scroll-buttons" aria-label="<?php echo esc_attr($title); ?> carousel controls">
                            <button type="button" class="custom-homepage-scroll-button" data-scroll-direction="previous" data-scroll-target="<?php echo esc_attr($section_id); ?>" aria-label="Previous cars">
                                <svg viewBox="0 0 12 12" aria-hidden="true"><polyline points="8,2 4,6 8,10"/></svg>
                            </button>
                            <button type="button" class="custom-homepage-scroll-button" data-scroll-direction="next" data-scroll-target="<?php echo esc_attr($section_id); ?>" aria-label="Next cars">
                                <svg viewBox="0 0 12 12" aria-hidden="true"><polyline points="4,2 8,6 4,10"/></svg>
                            </button>
                        </div>
                    <?php endif; ?>
                </div>

                <?php if ($query->have_posts()) : ?>
                    <div class="custom-homepage-car-row-shell">
                        <div id="<?php echo esc_attr($section_id); ?>" class="custom-homepage-car-row">
                            <?php
                            $listing_index = 0;
                            while ($query->have_posts()) :
                                $query->the_post();
                                if (function_exists('render_car_card')) {
                                    render_car_card(get_the_ID(), array('listing_index' => $listing_index));
                                }
                                $listing_index++;
                            endwhile;
                            ?>
                        </div>
                    </div>
                <?php else : ?>
                    <p class="custom-homepage-empty"><?php esc_html_e('No cars are available in this collection right now.', 'bricks-child'); ?></p>
                <?php endif; ?>

                <div class="custom-homepage-view-all">
                    <a class="brxe-button main-cta-button bricks-button bricks-background-primary" href="<?php echo esc_url($view_all_url); ?>"><?php echo esc_html($view_all); ?></a>
                </div>
            </div>
        </section>
        <?php
        wp_reset_postdata();
    }
}

if (!function_exists('autoagora_custom_homepage_cta')) {
    /**
     * Render a blue editorial callout matching the live homepage.
     *
     * @param string $title      Heading.
     * @param array  $paragraphs Paragraph copy.
     * @param string $button     Button label.
     * @param string $url        Button URL.
     * @param string $variation  Gradient variation.
     * @return void
     */
    function autoagora_custom_homepage_cta($title, array $paragraphs, $button, $url, $variation = 'one')
    {
        ?>
        <section class="custom-homepage-section custom-homepage-cta-section">
            <div class="custom-homepage-container custom-homepage-cta custom-homepage-cta--<?php echo esc_attr($variation); ?>">
                <h2><?php echo esc_html($title); ?></h2>
                <div class="custom-homepage-cta-copy">
                    <?php foreach ($paragraphs as $paragraph) : ?>
                        <p><?php echo esc_html($paragraph); ?></p>
                    <?php endforeach; ?>
                </div>
                <a class="brxe-button main-cta-button white-cta-button bricks-button bricks-background-primary" href="<?php echo esc_url($url); ?>"><?php echo esc_html($button); ?></a>
            </div>
        </section>
        <?php
    }
}

get_header();
?>

<main id="brx-content" class="custom-homepage-main">
    <section
        class="custom-homepage-hero"
        aria-labelledby="custom-homepage-title"
        style="--custom-homepage-hero-image: url('https://autoagora.cy/wp-content/uploads/2025/04/hero-bg-1024x683.webp');"
    >
        <div class="custom-homepage-container custom-homepage-hero-inner">
            <div class="custom-homepage-search-card">
                <h1 id="custom-homepage-title"><?php esc_html_e('Find your next car in Cyprus', 'bricks-child'); ?></h1>
                <?php echo do_shortcode('[homepage_filters]'); ?>
            </div>
        </div>
    </section>

    <?php
    autoagora_custom_homepage_collection(
        __('Latest cars', 'bricks-child'),
        home_url('/cars/'),
        __('View All Used Cars in Cyprus', 'bricks-child'),
        array(),
        true
    );

    autoagora_custom_homepage_cta(
        __('Find Used Cars for Sale in Cyprus', 'bricks-child'),
        array(
            __('Tired of wrong specs? AutoAgora guarantees data integrity.', 'bricks-child'),
            __('Every listing for used cars for sale in Cyprus is cross-validated against official data. This means the model, engine size, and year are 100% correct.', 'bricks-child'),
            __('Search Nicosia, Limassol, and all of Cyprus with absolute confidence.', 'bricks-child'),
        ),
        __('View All Used Cars in Cyprus', 'bricks-child'),
        home_url('/cars/'),
        'one'
    );

    autoagora_custom_homepage_collection(
        __('Budget cars under €5,000', 'bricks-child'),
        home_url('/cars/budget-cars/'),
        __('View All Budget Cars in Cyprus', 'bricks-child'),
        array(
            array(
                'key'     => 'price',
                'value'   => 5000,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            ),
        )
    );

    autoagora_custom_homepage_collection(
        __('Family SUVs', 'bricks-child'),
        home_url('/cars/family-suvs/'),
        __('View All Family SUVs in Cyprus', 'bricks-child'),
        array(
            array(
                'key'     => 'body_type',
                'value'   => 'SUV',
                'compare' => '=',
            ),
        )
    );

    autoagora_custom_homepage_cta(
        __('Selling Your Used Car in Cyprus?', 'bricks-child'),
        array(
            __('Get maximum exposure for your vehicle through AutoAgora.', 'bricks-child'),
            __('We connect you directly with thousands of site visitors and potential buyers looking for used cars in Cyprus right now.', 'bricks-child'),
            __('Listing is fast, secure, and free. Reach buyers across Nicosia, Limassol, Paphos, and Larnaca instantly.', 'bricks-child'),
        ),
        __('Sell My Car', 'bricks-child'),
        home_url('/add-listing/'),
        'two'
    );

    autoagora_custom_homepage_collection(
        __('Luxury Cars Above €100,000', 'bricks-child'),
        home_url('/cars/luxury-cars/'),
        __('View All Luxury Cars in Cyprus', 'bricks-child'),
        array(
            array(
                'key'     => 'price',
                'value'   => 100000,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            ),
        )
    );

    autoagora_custom_homepage_collection(
        __('Sporty Coupes/Convertibles', 'bricks-child'),
        home_url('/cars/sporty-coupes-convertibles/'),
        __('View All Coupes/Convertibles in Cyprus', 'bricks-child'),
        array(
            array(
                'key'     => 'body_type',
                'value'   => array('Coupe', 'Convertible'),
                'compare' => 'IN',
            ),
        )
    );

    autoagora_custom_homepage_cta(
        __('Become an AutoAgora Dealer', 'bricks-child'),
        array(
            __('Are you a professional seller of used cars in Cyprus?', 'bricks-child'),
            __('Join our exclusive network of trusted dealerships. List your full inventory, reach thousands of qualified buyers, and start selling within 24 hours.', 'bricks-child'),
            __('The fastest way to join is through a quick chat.', 'bricks-child'),
        ),
        __('Become a Dealer', 'bricks-child'),
        home_url('/become-a-dealer/'),
        'three'
    );
    ?>
</main>

<?php get_footer(); ?>
