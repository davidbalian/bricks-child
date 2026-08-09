<?php
/**
 * Template Name: New Cars Page Template
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Google Maps: loaded on first Location modal open via autoagora-car-browse-maps-loader (see includes/core/car-browse-assets.php).

/**
 * Critical CLS guard, printed inline at the very top of <head>.
 *
 * Both modal overlays are markup siblings that sit directly above .tcp-main in the
 * document flow. They are only taken out of flow by cars-page.css, so until that
 * stylesheet applies they render in flow at roughly 2500px tall and push .tcp-main
 * down the page. Any paint that happens before the stylesheet lands therefore costs
 * a full-viewport layout shift.
 *
 * Inlining the one rule that matters removes the network dependency entirely. The
 * .open state in cars-page.css uses a two-class selector, so it still wins on
 * specificity and the modals continue to open normally.
 */
add_action( 'wp_head', function() {
    if ( ! is_page_template( 'template-test-cars.php' ) ) {
        return;
    }
    echo "<style id=\"tcp-critical-cls-guard\">.tcp-filters-modal-overlay{display:none}.tcp-filters-bar{position:sticky;top:calc(var(--aag-site-header-top,0px) + var(--aag-site-header-height,0px));font-family:Inter,sans-serif}.tcp-main{width:100%;max-width:2000px;margin:0 auto;padding:1.5rem 1rem 6rem;box-sizing:border-box;font-family:Inter,sans-serif}</style>\n";
}, 1 );

add_action( 'wp_head', function() {
    if ( ! is_page_template( 'template-test-cars.php' ) ) {
        return;
    }
    ?>
    <script type="application/ld+json">
    {
      "@context": "https://schema.org",
      "@type": "FAQPage",
      "mainEntity": [
        {
          "@type": "Question",
          "name": "How much does a used car cost in Cyprus?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Used car prices in Cyprus vary widely depending on the make, model, age, and mileage. Budget-friendly options like the Nissan Note or Toyota Yaris typically start around €8,000–€13,000, while popular SUVs like the Mazda CX-5 or Volkswagen Tiguan range from €15,000–€30,000. Luxury and performance vehicles can go well above €50,000."
          }
        },
        {
          "@type": "Question",
          "name": "Where can I buy a used car in Cyprus?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "You can buy used cars from licensed dealerships or private sellers across all major cities in Cyprus, including Nicosia, Limassol, Larnaca, and Paphos. AutoAgora lists vehicles from verified dealers and individuals across the island, so you can compare options from multiple sources without visiting each one in person."
          }
        },
        {
          "@type": "Question",
          "name": "What should I check before buying a used car in Cyprus?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Before purchasing, you should verify the vehicle's service history and mileage, check for any outstanding finance or liens, inspect the car for accident damage or rust (especially underbody), confirm the MOT (road worthiness) status, and make sure the registration documents match the seller's details. It's also a good idea to take the car for a test drive and have a trusted mechanic inspect it if possible."
          }
        },
        {
          "@type": "Question",
          "name": "Can I finance a used car purchase in Cyprus?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Yes, most banks in Cyprus offer car loans for used vehicles. Typical loan terms range from 1 to 7 years, and interest rates depend on the bank and your credit profile. Some dealerships on AutoAgora also offer in-house financing options. It's worth comparing offers from multiple lenders before committing."
          }
        },
        {
          "@type": "Question",
          "name": "Are used cars in Cyprus left-hand drive or right-hand drive?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Cyprus drives on the left side of the road, and the vast majority of cars on the island are right-hand drive (RHD) - meaning the steering wheel is on the right. A lot of vehicles are imported from the UK or Japan, where driving is also on the left. You'll find some left-hand drive cars imported from mainland Europe, but RHD is the standard in Cyprus."
          }
        },
        {
          "@type": "Question",
          "name": "What are the most popular used cars in Cyprus?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "The most popular used cars in Cyprus include the Toyota Yaris, Nissan Note, Mazda CX-5, BMW 3 Series, Mercedes-Benz A-Class, Volkswagen Golf, and Nissan Qashqai. SUVs and compact hatchbacks tend to be the most in-demand body types, followed by saloons. Petrol hybrids have been growing in popularity in recent years."
          }
        },
        {
          "@type": "Question",
          "name": "How do I sell my car on AutoAgora?",
          "acceptedAnswer": {
            "@type": "Answer",
            "text": "Selling your car on AutoAgora is free and straightforward. Simply create an account, click 'Sell My Car,' and fill in your vehicle's details including photos, price, mileage, and specifications. Your listing will be visible to buyers across Cyprus."
          }
        }
      ]
    }
    </script>
    <?php
} );

get_header();

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
    'id'                 => 'test-cars-listings',
    'filter_group'       => '',
    'card_type'          => 'car_card',
    'default_make_slug'  => '',
    'default_model_slug' => '',
    'default_car_city'   => '',
    'layout'             => 'grid',
    'infinite_scroll'    => 'false',
);

if ( isset( $_GET['car_city'] ) && $_GET['car_city'] !== '' ) {
    $listing_atts['default_car_city'] = sanitize_text_field( wp_unslash( $_GET['car_city'] ) );
}

// Merge URL sort params (e.g., after redirect from car make landing page)
$listing_atts = car_listings_apply_request_sort_to_atts( $listing_atts );

// Build query applying all URL filter params (make, model, price, mileage, body_type, etc.)
$query_args = car_listings_build_query_args( $listing_atts );
$cars_query   = car_listings_execute_query( $query_args );
$current_page = max( 1, (int) $cars_query->get( 'paged' ) );
$request_ctx = function_exists( 'autoagora_get_active_car_filter_context' )
    ? autoagora_get_active_car_filter_context()
    : array();
$has_server_filters = !empty( $request_ctx['make_slug'] ) || !empty( $request_ctx['model_slug'] );
if ( ! $has_server_filters ) {
    foreach ( array( 'make', 'model', 'price_min', 'price_max', 'mileage_min', 'mileage_max', 'year_min', 'year_max', 'fuel_type', 'body_type', 'engine_capacity_min', 'engine_capacity_max', 'hp_min', 'hp_max', 'numowners_min', 'numowners_max', 'transmission', 'drive_type', 'exterior_color', 'interior_color', 'number_of_doors', 'number_of_seats', 'availability', 'isantique', 'extras', 'vehiclehistory', 'car_city', 'loc_lat', 'loc_lng', 'loc_radius' ) as $k ) {
        if ( isset( $_GET[ $k ] ) && wp_unslash( $_GET[ $k ] ) !== '' ) {
            $has_server_filters = true;
            break;
        }
    }
}

// Ensure car-card assets load even when the initial query returns zero posts (AJAX may inject cards next).
if ( isset( $listing_atts['card_type'] ) && $listing_atts['card_type'] === 'car_card' && function_exists( 'car_card_enqueue_assets' ) ) {
    car_card_enqueue_assets();
}
?>

<!-- Filters bar -->
<div class="tcp-filters-bar">
	<div class="tcp-page-nav">
		<a class="tcp-page-nav__action tcp-page-nav__back" href="<?php echo esc_url( autoagora_localized_page_url() ); ?>" data-tcp-back aria-label="<?php esc_attr_e( 'Go back', 'bricks-child' ); ?>">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m12 19-7-7 7-7"/><path d="M19 12H5"/></svg>
		</a>
		<h1 class="tcp-page-heading"><?php esc_html_e('Used Cars for Sale in Cyprus', 'bricks-child'); ?></h1>
		<button type="button" class="tcp-page-nav__action tcp-page-nav__saved" aria-label="<?php esc_attr_e( 'Favourites', 'bricks-child' ); ?>" aria-pressed="false">
			<svg viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="M2 9.5a5.5 5.5 0 0 1 9.591-3.676.56.56 0 0 0 .818 0A5.49 5.49 0 0 1 22 9.5c0 2.29-1.5 4-3 5.5l-5.492 5.313a2 2 0 0 1-3 .019L5 15c-1.5-1.5-3-3.2-3-5.5"/></svg>
		</button>
    </div>
    <div class="tcp-filters-bar-inner">
		<button type="button" class="tcp-filters-btn" id="tcp-filters-btn">
			<svg class="lucide lucide-sliders-horizontal" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><line x1="21" x2="14" y1="4" y2="4"/><line x1="10" x2="3" y1="4" y2="4"/><line x1="21" x2="12" y1="12" y2="12"/><line x1="8" x2="3" y1="12" y2="12"/><line x1="21" x2="16" y1="20" y2="20"/><line x1="12" x2="3" y1="20" y2="20"/><line x1="14" x2="14" y1="2" y2="6"/><line x1="8" x2="8" y1="10" y2="14"/><line x1="16" x2="16" y1="18" y2="22"/></svg>
			<?php esc_html_e('Filters', 'bricks-child'); ?>
        </button>
        <div class="tcp-quick-filters" aria-label="<?php esc_attr_e('Quick car filters', 'bricks-child'); ?>">
            <button type="button" class="tcp-quick-filter tcp-quick-filter--favourites" data-filter-target="favorites" data-default-label="<?php esc_attr_e('Favourites', 'bricks-child'); ?>" hidden><span class="tcp-quick-filter__label"><?php esc_html_e('Favourites', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="make" data-default-label="<?php esc_attr_e('Brand and model', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Brand and model', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="price" data-default-label="<?php esc_attr_e('Price', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Price', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="year" data-default-label="<?php esc_attr_e('Year', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Year', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="mileage" data-default-label="<?php esc_attr_e('Mileage', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Mileage', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="transmission" data-default-label="<?php esc_attr_e('Transmission', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Transmission', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="body" data-default-label="<?php esc_attr_e('Body type', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Body type', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="fuel" data-default-label="<?php esc_attr_e('Fuel type', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Fuel type', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="engine" data-default-label="<?php esc_attr_e('Engine size', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Engine size', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
            <button type="button" class="tcp-quick-filter" data-filter-target="location" data-default-label="<?php esc_attr_e('Location', 'bricks-child'); ?>"><span class="tcp-quick-filter__label"><?php esc_html_e('Location', 'bricks-child'); ?></span><span class="tcp-quick-filter__clear" aria-hidden="true"><?php echo autoagora_code_header_icon( 'close' ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?></span></button>
        </div>
    </div>
    <div class="tcp-active-filters-row">
        <div class="tcp-active-filters" id="tcp-active-filters"></div>
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
            $tcp_modal_filter_sections = array(
                __('Search essentials', 'bricks-child') => array(
                    'make' => 'car_filter_make',
                    'model' => 'car_filter_model',
                    'price' => 'car_filter_price',
                    'year' => 'car_filter_year',
                    'mileage' => 'car_filter_mileage',
                ),
                __('Vehicle details', 'bricks-child') => array(
                    'transmission' => 'car_filter_transmission',
                    'body' => 'car_filter_body',
                    'fuel' => 'car_filter_fuel',
                    'engine' => 'car_filter_engine',
                    'hp' => 'car_filter_hp',
                    'drive' => 'car_filter_drive',
                    'exterior' => 'car_filter_exterior',
                    'interior' => 'car_filter_interior',
                    'doors' => 'car_filter_doors',
                    'seats' => 'car_filter_seats',
                ),
                __('More options', 'bricks-child') => array(
                    'availability' => 'car_filter_availability',
                    'owners' => 'car_filter_owners',
                    'antique' => 'car_filter_antique',
                    'extras' => 'car_filter_extras',
                    'history' => 'car_filter_history',
                ),
            );
            ?>
            <div class="car-filters-container car-filters-vertical"
                 data-group="default"
                 data-mode="ajax"
                 data-target="test-cars-listings"
                 data-redirect-url="/cars/"
                 data-results-base-url="/cars/">
                <?php foreach ( $tcp_modal_filter_sections as $section_title => $section_filters ) : ?>
                    <section class="tcp-filter-section">
                        <h3><?php echo esc_html( $section_title ); ?></h3>
                        <div class="car-filters-wrapper">
                            <?php foreach ( $section_filters as $filter_name => $filter_shortcode ) : ?>
                                <div class="car-filters-item car-filters-item-<?php echo esc_attr( $filter_name ); ?>">
                                    <?php
                                    echo do_shortcode(
                                        sprintf(
                                            '[%s group="default" mode="ajax" target="test-cars-listings"]',
                                            $filter_shortcode
                                        )
                                    );
                                    ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
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
	<div class="tcp-results-toolbar">
		<p class="tcp-results-count" id="tcp-results-count">
			<?php
			echo esc_html(sprintf(
				_n('%s car found', '%s cars found', (int) $cars_query->found_posts, 'bricks-child'),
				number_format_i18n((int) $cars_query->found_posts)
			));
			?>
		</p>
		<div class="tcp-sort" id="tcp-sort">
			<button type="button" class="tcp-sort-btn" id="tcp-sort-btn" aria-expanded="false" aria-controls="tcp-sort-menu">
				<span class="tcp-sort-prefix"><?php esc_html_e('Sort by:', 'bricks-child'); ?></span>
				<strong id="tcp-sort-label"><?php esc_html_e('Newest', 'bricks-child'); ?></strong>
				<svg class="tcp-sort-chevron" viewBox="0 0 24 24" aria-hidden="true" focusable="false"><path d="m6 9 6 6 6-6"/></svg>
			</button>
			<div class="tcp-sort-menu" id="tcp-sort-menu">
				<button type="button" class="tcp-sort-option selected" data-orderby="date" data-order="DESC"><?php esc_html_e('Newest', 'bricks-child'); ?></button>
				<button type="button" class="tcp-sort-option" data-orderby="score" data-order="DESC"><?php esc_html_e('Best Match', 'bricks-child'); ?></button>
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
    <div class="car-listings-container"
         id="test-cars-listings"
         data-atts="<?php echo esc_attr( wp_json_encode( $listing_atts ) ); ?>"
         data-page="<?php echo esc_attr( (string) $current_page ); ?>"
         data-max-pages="<?php echo esc_attr( $cars_query->max_num_pages ); ?>"
         data-server-filtered="<?php echo $has_server_filters ? 'true' : 'false'; ?>">

        <div class="car-listings-wrapper tcp-grid car-card-grid">
            <?php
            if ( $cars_query->have_posts() ) :
                $post_ids = wp_list_pluck( $cars_query->posts, 'ID' );
                update_postmeta_cache( $post_ids );
                update_post_thumbnail_cache( $cars_query );

                $listing_card_index = 0;
                while ( $cars_query->have_posts() ) :
                    $cars_query->the_post();
                    render_car_card( get_the_ID(), array( 'listing_index' => $listing_card_index ) );
                    $listing_card_index++;
                endwhile;
            else :
                ?>
                <p class="car-listings-no-results"><?php esc_html_e( 'No car listings found.', 'bricks-child' ); ?></p>
                <?php
            endif;
            wp_reset_postdata();
            ?>
        </div>

        <div class="tcp-pagination">
            <?php
            if ( $cars_query->max_num_pages > 1 ) {
                echo paginate_links( array(
                    'total'     => $cars_query->max_num_pages,
                    'current'   => $current_page,
                    'prev_text' => __('Previous', 'bricks-child'),
                    'next_text' => __('Next', 'bricks-child'),
                    'type'      => 'list',
                    'base'      => '#%#%',
                    'format'    => '%#%',
                ) );
            }
            ?>
        </div>
    </div>

    <!-- SEO Content: Intro + FAQ -->
    <div class="cars-seo-content">

        <section class="cars-intro">
            <h2 class="cars-intro-heading"><?php esc_html_e('Buying a Used Car in Cyprus on AutoAgora', 'bricks-child'); ?></h2>
            <p><?php echo wp_kses_post(__('Browse <strong>600+ used cars for sale in Cyprus</strong> from trusted dealerships and private sellers across Nicosia, Limassol, Larnaca, and Paphos. Whether you are looking for a fuel-efficient hatchback for city driving, a family SUV, or a luxury sedan, AutoAgora makes it easy to compare prices, specs, and photos - all in one place.', 'bricks-child')); ?></p>
            <p><?php esc_html_e('Use the filters above to narrow your search by make, model, price range, fuel type, mileage, and more. Every listing includes full vehicle details, high-quality photos, and direct contact with the seller.', 'bricks-child'); ?></p>
        </section>

        <section class="cars-faq">
            <h2 class="cars-faq-heading"><?php esc_html_e('Frequently Asked Questions About Buying a Used Car in Cyprus', 'bricks-child'); ?></h2>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php esc_html_e('How much does a used car cost in Cyprus?', 'bricks-child'); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php esc_html_e('Used car prices in Cyprus vary widely depending on the make, model, age, and mileage. Budget-friendly options like the Nissan Note or Toyota Yaris typically start around €8,000–€13,000, while popular SUVs like the Mazda CX-5 or Volkswagen Tiguan range from €15,000–€30,000. Luxury and performance vehicles can go well above €50,000. You can use the price filter above to browse cars within your budget.', 'bricks-child'); ?></p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php esc_html_e('Where can I buy a used car in Cyprus?', 'bricks-child'); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php esc_html_e('You can buy used cars from licensed dealerships or private sellers across all major cities in Cyprus, including Nicosia, Limassol, Larnaca, and Paphos. AutoAgora lists vehicles from verified dealers and individuals across the island, so you can compare options from multiple sources without visiting each one in person.', 'bricks-child'); ?></p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php esc_html_e('What should I check before buying a used car in Cyprus?', 'bricks-child'); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php esc_html_e("Before purchasing, you should verify the vehicle's service history and mileage, check for any outstanding finance or liens, inspect the car for accident damage or rust (especially underbody), confirm the MOT (road worthiness) status, and make sure the registration documents match the seller's details. It's also a good idea to take the car for a test drive and have a trusted mechanic inspect it if possible.", 'bricks-child'); ?></p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php esc_html_e('Can I finance a used car purchase in Cyprus?', 'bricks-child'); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php esc_html_e("Yes, most banks in Cyprus offer car loans for used vehicles. Typical loan terms range from 1 to 7 years, and interest rates depend on the bank and your credit profile. Some dealerships on AutoAgora also offer in-house financing options. It's worth comparing offers from multiple lenders before committing.", 'bricks-child'); ?></p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php esc_html_e('Are used cars in Cyprus left-hand drive or right-hand drive?', 'bricks-child'); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php esc_html_e("Cyprus drives on the left side of the road, and the vast majority of cars on the island are right-hand drive (RHD) - meaning the steering wheel is on the right. A lot of vehicles are imported from the UK or Japan, where driving is also on the left. You'll find some left-hand drive cars imported from mainland Europe, but RHD is the standard in Cyprus.", 'bricks-child'); ?></p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php esc_html_e('What are the most popular used cars in Cyprus?', 'bricks-child'); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php esc_html_e('The most popular used cars in Cyprus include the Toyota Yaris, Nissan Note, Mazda CX-5, BMW 3 Series, Mercedes-Benz A-Class, Volkswagen Golf, and Nissan Qashqai. SUVs and compact hatchbacks tend to be the most in-demand body types, followed by saloons. Petrol hybrids have been growing in popularity in recent years.', 'bricks-child'); ?></p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php esc_html_e('How do I sell my car on AutoAgora?', 'bricks-child'); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php
                    printf(
                        wp_kses_post(__('Selling your car on AutoAgora is free and straightforward. Simply create an account, click "Sell My Car," and fill in your vehicle details including photos, price, mileage, and specifications. Your listing will be visible to buyers across Cyprus. For more details, visit our <a href="%s">guide on how to sell your car</a>.', 'bricks-child')),
                        esc_url(autoagora_localized_page_url('how-to-sell-your-car'))
                    );
                    ?></p>
                </div>
            </div>
        </section>

    </div><!-- .cars-seo-content -->
</div>


<script>
(function() {
    var backLink = document.querySelector('[data-tcp-back]');

    if (backLink) {
        var fallbackUrl = backLink.href;
        var previousUrl = '';

        if (document.referrer) {
            try {
                previousUrl = new URL(document.referrer, window.location.href).href;
            } catch (error) {
                previousUrl = '';
            }
        }

        if (previousUrl === window.location.href) {
            previousUrl = '';
        }

        if (previousUrl) {
            backLink.href = previousUrl;
        }

        backLink.addEventListener('click', function(event) {
            if (event.button !== 0 || event.metaKey || event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            // AJAX filtering adds pushState entries. Navigating to the original
            // referrer avoids making users step through each filter state first.
            if (previousUrl) {
                window.location.assign(previousUrl);
                return;
            }

            if (window.history.length > 1) {
                window.history.back();
                return;
            }

            window.location.assign(fallbackUrl);
        }, true);
    }
})();

(function($) {
    'use strict';

    var t = window.autoagoraTranslate || function(source) { return source; };

    var $container  = $('#test-cars-listings');
    var $wrapper    = $container.find('.car-listings-wrapper');
    var $pagination = $container.find('.tcp-pagination');
    var $overlay    = $('#tcp-filters-modal-overlay');
    var $locationOverlay = $('#tcp-location-modal-overlay');
    var $chips      = $('#tcp-active-filters');
    var $results    = $('#tcp-results-count');
    var group       = 'default';
    var MIN_ROWS     = 4;
    var MIN_PER_PAGE = 12;
    var CARD_MIN_W   = 280; // matches minmax(280px, 1fr)
    var GRID_GAP     = 24;  // 1.5rem
    var locationState = {
        lat: null,
        lng: null,
        radiusKm: 25,
        label: '',
        active: false
    };
    var locationMap = null;
    var locationCircle = null;
    var locationAutocomplete = null;
    var locationGeocoder = null;
    var reverseGeocodeTimer = null;
    var favoritesOnly = String(($container.data('atts') || {}).favorites) === 'true';
    var rangeFilterKeys = ['price', 'mileage', 'year', 'engine_capacity', 'hp', 'numowners'];
    var selectFilterKeys = ['fuel_type', 'body_type', 'transmission', 'drive_type', 'exterior_color', 'interior_color', 'number_of_doors', 'number_of_seats', 'availability', 'isantique', 'extras', 'vehiclehistory'];
    var allFilterKeys = ['make', 'model'];
    rangeFilterKeys.forEach(function(key) {
        allFilterKeys.push(key + '_min', key + '_max');
    });
    allFilterKeys = allFilterKeys.concat(selectFilterKeys);
    var filterClassMap = {
        fuel_type: 'fuel',
        body_type: 'body',
        engine_capacity: 'engine',
        drive_type: 'drive',
        exterior_color: 'exterior',
        interior_color: 'interior',
        number_of_doors: 'doors',
        number_of_seats: 'seats',
        numowners: 'owners',
        vehiclehistory: 'history',
        isantique: 'antique'
    };
    var quickRangeFilterKeys = ['price', 'mileage', 'year', 'engine_capacity'];
    var quickSelectFilterKeys = ['fuel_type', 'body_type', 'transmission'];

    /**
     * Calculate posts_per_page as a multiple of current column count
     * so every row is fully filled. At least 4 rows and at least 12 cards.
     */
    function calcPostsPerPage() {
        var gridW = $wrapper.width() || $container.width();
        if (!gridW) return MIN_PER_PAGE;
        var cols = Math.max(1, Math.floor((gridW + GRID_GAP) / (CARD_MIN_W + GRID_GAP)));
        return Math.max(MIN_PER_PAGE, cols * MIN_ROWS);
    }

    function syncPostsPerPage() {
        var atts = $container.data('atts') || {};
        atts.posts_per_page = calcPostsPerPage();
        $container.data('atts', atts);
        $container.attr('data-atts', JSON.stringify(atts));
    }

    function updateResultsCount(total) {
        var count = parseInt(total, 10);
        if (isNaN(count) || count < 0) {
            count = 0;
        }
        $results.text(t(count === 1 ? '%s car found' : '%s cars found', count.toLocaleString()));
        updateClearAllButton(count);
    }

    function updateClearAllButton(count) {
        $wrapper.find('.tcp-clear-all-filters-btn').remove();
        if (count === 0) {
            var $noResults = $wrapper.find('.car-listings-no-results');
            if ($noResults.length) {
                $noResults.after('<button type="button" class="tcp-clear-all-filters-btn" id="tcp-no-results-clear-btn">' + escapeHtml(t('Clear all filters')) + '</button>');
            }
        }
    }

    function resetSort() {
        $sort.find('.tcp-sort-option').removeClass('selected');
        $sort.find('.tcp-sort-option').first().addClass('selected');
        $sortLabel.text(t('Newest'));
        var atts = $container.data('atts') || {};
        atts.orderby = 'date';
        atts.order = 'DESC';
        $container.data('atts', atts);
        $container.attr('data-atts', JSON.stringify(atts));
    }

    // Filter label map for chips
    var filterLabels = {
        make: t('Brand'),
        model: t('Model'),
        price_min: t('Price min'),
        price_max: t('Price max'),
        mileage_min: t('Mileage min'),
        mileage_max: t('Mileage max'),
        year_min: t('Year min'),
        year_max: t('Year max'),
        fuel_type: t('Fuel'),
        body_type: t('Body'),
        engine_capacity_min: t('Engine min'),
        engine_capacity_max: t('Engine max'),
        hp_min: t('Power min'),
        hp_max: t('Power max'),
        numowners_min: t('Owners min'),
        numowners_max: t('Owners max'),
        transmission: t('Transmission'),
        drive_type: t('Drive'),
        exterior_color: t('Exterior'),
        interior_color: t('Interior'),
        number_of_doors: t('Doors'),
        number_of_seats: t('Seats'),
        availability: t('Availability'),
        isantique: t('Registration'),
        extras: t('Features'),
        vehiclehistory: t('History'),
        location_radius: t('Location')
    };

    /* ── Modal open/close ── */
    function openFilterModal(filterTarget) {
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
        if (!filterTarget) return;

        window.setTimeout(function() {
            var $item = $overlay.find('.car-filters-item-' + filterTarget).first();
            if (!$item.length) return;

            var body = $overlay.find('.tcp-filters-modal-body')[0];
            if (body) {
                var bodyRect = body.getBoundingClientRect();
                var itemRect = $item[0].getBoundingClientRect();
                body.scrollTo({
                    top: Math.max(0, body.scrollTop + itemRect.top - bodyRect.top - 16),
                    behavior: 'smooth'
                });
            }

            $overlay.find('.car-filters-item').removeClass('is-quick-target');
            $item.addClass('is-quick-target');
            window.setTimeout(function() {
                $item.removeClass('is-quick-target');
            }, 1800);

            var $dropdown = $item.find('.car-filter-dropdown').first();
            if ($dropdown.length && !$dropdown.hasClass('car-filter-dropdown-disabled')) {
                $('.car-filter-dropdown.open').removeClass('open')
                    .find('.car-filter-dropdown-button').attr('aria-expanded', 'false');
                $dropdown.addClass('open');
                $dropdown.find('.car-filter-dropdown-button').attr('aria-expanded', 'true');
                var $search = $dropdown.find('.car-filter-dropdown-search:not(:disabled)');
                ($search.length ? $search : $dropdown.find('.car-filter-dropdown-button')).first().focus();
            } else {
                $item.find('.car-filter-input').first().focus();
            }
        }, 80);
    }
    $('#tcp-filters-btn').on('click', function() {
        openFilterModal('');
    });
    function closeModal() {
        $overlay.removeClass('open');
        $('body').css('overflow', '');
    }
    $('#tcp-filters-modal-close').on('click', closeModal);
    $overlay.on('click', function(e) {
        if (e.target === this) closeModal();
    });

    function openLocationModal() {
        $locationOverlay.addClass('open');
        $('body').css('overflow', 'hidden');
        function afterMapsReady() {
            initLocationMap();
            setTimeout(function() {
                if (!locationMap || typeof google === 'undefined' || !google.maps) return;
                google.maps.event.trigger(locationMap, 'resize');
                if (locationState.lat && locationState.lng) {
                    locationMap.setCenter({ lat: locationState.lat, lng: locationState.lng });
                }
            }, 50);
        }
        if (typeof google !== 'undefined' && google.maps) {
            afterMapsReady();
        } else if (typeof window.autoagoraLoadCarBrowseMaps === 'function') {
            window.autoagoraLoadCarBrowseMaps(afterMapsReady);
        } else {
            afterMapsReady();
        }
    }

    function closeLocationModal() {
        $locationOverlay.removeClass('open');
        $('body').css('overflow', '');
    }

    $('#tcp-location-btn').on('click', openLocationModal);
    $('.tcp-page-nav__saved').on('click', function() {
        setFavoritesOnly(!favoritesOnly, true);
    });

    $('.tcp-quick-filter').on('click', function(e) {
        var target = String($(this).data('filter-target') || '');
        if ($(e.target).closest('.tcp-quick-filter__clear').length || target === 'favorites') {
            clearQuickFilter(target);
            return;
        }
        if (target === 'location') {
            openLocationModal();
            return;
        }
        openFilterModal(target);
    });

    function setFavoritesOnly(isActive, reloadListings) {
        favoritesOnly = !!isActive;
        var atts = $container.data('atts') || {};
        atts.favorites = favoritesOnly ? 'true' : 'false';
        $container.data('atts', atts);
        $container.attr('data-atts', JSON.stringify(atts));
        buildChips();

        if (reloadListings) {
            loadPage(1, { scroll: false });
        }
    }

    function clearQuickFilter(target) {
        var targetKeys = {
            make: ['model', 'make'],
            price: ['price_min', 'price_max'],
            year: ['year_min', 'year_max'],
            mileage: ['mileage_min', 'mileage_max'],
            transmission: ['transmission'],
            body: ['body_type'],
            fuel: ['fuel_type'],
            engine: ['engine_capacity_min', 'engine_capacity_max'],
            location: ['location_radius']
        };

        if (target === 'favorites') {
            setFavoritesOnly(false, true);
            return;
        }

        (targetKeys[target] || []).forEach(function(key) {
            clearFilter(key);
        });
        buildChips();
        CarFilters.triggerFilter(group);
    }
    $('#tcp-location-modal-close').on('click', closeLocationModal);
    $locationOverlay.on('click', function(e) {
        if (e.target === this) closeLocationModal();
    });

    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $overlay.hasClass('open')) closeModal();
        if (e.key === 'Escape' && $locationOverlay.hasClass('open')) closeLocationModal();
    });

    /* ── Active filter chips ── */
    function buildChips() {
        if (!window.CarFilters) return;
        var state = CarFilters.getState(group);
        var html = '';
        var hasAny = false;

        // Quick-filter values render inside their existing pills. Only advanced
        // filters without a dedicated pill are rendered in this auxiliary row.
        rangeFilterKeys.forEach(function(key) {
            if (quickRangeFilterKeys.indexOf(key) !== -1) return;
            var min = state[key + '_min'];
            var max = state[key + '_max'];
            var noComma = (key === 'year' || key === 'engine_capacity');
            var suffix = key === 'engine_capacity' ? 'L' : (key === 'hp' ? ' hp' : '');
            if (min) {
                html += chip(key + '_min', filterLabels[key + '_min'] + ': ' + formatNum(min, noComma) + suffix);
                hasAny = true;
            }
            if (max) {
                html += chip(key + '_max', filterLabels[key + '_max'] + ': ' + formatNum(max, noComma) + suffix);
                hasAny = true;
            }
        });
        // Simple selects
        selectFilterKeys.forEach(function(key) {
            if (quickSelectFilterKeys.indexOf(key) !== -1) return;
            if (state[key]) {
                html += chip(key, filterLabels[key] + ': ' + getFilterValueLabel(key, state[key]));
                hasAny = true;
            }
        });
        if (hasAny) {
            html += '<button type="button" class="tcp-chip-clear" id="tcp-clear-all">' + escapeHtml(t('Clear all')) + '</button>';
        }

        $chips.html(html);
        $chips.closest('.tcp-active-filters-row').toggleClass('has-active', hasAny);
        updateQuickFilterStates(state);
    }

    function chip(key, label) {
        return '<span class="tcp-chip" data-filter="' + key + '">' +
               escapeHtml(label) +
               '<button type="button" class="tcp-chip-remove" data-filter="' + key + '" aria-label="' + escapeHtml(t('Remove')) + '">&times;</button>' +
               '</span>';
    }

    function escapeHtml(value) {
        return $('<div>').text(String(value)).html();
    }

    function formatNum(val, raw) {
        var n = raw ? parseFloat(String(val).replace(/,/g, '')) : parseInt(String(val).replace(/,/g, ''), 10);
        if (!n) return val;
        return raw ? String(n) : n.toLocaleString();
    }

    function getFilterValueLabel(key, value) {
        var filterClass = filterClassMap[key] || key;
        return String(value).split(',').map(function(item) {
            var $option = $('.car-filter-' + filterClass + ' .car-filter-dropdown-option').filter(function() {
                return String($(this).data('value')) === item;
            }).first();
            return $option.length
                ? $option.clone().children('.car-filter-count').remove().end().text().trim()
                : item.replace(/_/g, ' ');
        }).join(', ');
    }

    function updateQuickFilterStates(state) {
        $('.tcp-quick-filter').each(function() {
            var $button = $(this);
            var target = String($button.data('filter-target') || '');
            var defaultLabel = String($button.attr('data-default-label') || $button.text());
            var active = false;
            var selectedLabel = '';

            if (target === 'favorites') {
                active = favoritesOnly;
                selectedLabel = defaultLabel;
            } else if (target === 'make') {
                active = !!((state.make && state.make.value) || (state.model && state.model.value));
                if (active) {
                    var makeLabel = state.make && state.make.value ? getMakeLabel(state.make.value) : '';
                    var modelLabel = state.model && state.model.value ? getModelLabel(state.model.value) : '';
                    selectedLabel = makeLabel && modelLabel && modelLabel.toLowerCase().indexOf(makeLabel.toLowerCase()) === 0
                        ? modelLabel
                        : [makeLabel, modelLabel].filter(Boolean).join(' ');
                }
            } else if (target === 'location') {
                active = locationState.active;
                selectedLabel = locationState.label || t('Selected area');
            } else if (target === 'price') {
                selectedLabel = formatQuickRange(state.price_min, state.price_max, { prefix: '€' });
                active = selectedLabel !== '';
            } else if (target === 'year') {
                selectedLabel = formatQuickRange(state.year_min, state.year_max, { raw: true });
                active = selectedLabel !== '';
            } else if (target === 'mileage') {
                selectedLabel = formatQuickRange(state.mileage_min, state.mileage_max, { suffix: ' km' });
                active = selectedLabel !== '';
            } else if (target === 'engine') {
                selectedLabel = formatQuickRange(state.engine_capacity_min, state.engine_capacity_max, { raw: true, suffix: 'L' });
                active = selectedLabel !== '';
            } else {
                var stateKey = Object.keys(filterClassMap).find(function(key) {
                    return filterClassMap[key] === target;
                }) || target;
                active = !!state[stateKey];
                selectedLabel = active ? getFilterValueLabel(stateKey, state[stateKey]) : '';
            }

            $button
                .toggleClass('is-active', active)
                .prop('hidden', target === 'favorites' && !active)
                .attr('aria-label', active && selectedLabel ? defaultLabel + ': ' + selectedLabel : defaultLabel);
            $button.find('.tcp-quick-filter__label').text(active && selectedLabel ? selectedLabel : defaultLabel);

            if (target === 'favorites') {
                $('.tcp-page-nav__saved')
                    .toggleClass('is-active', active)
                    .attr('aria-pressed', active ? 'true' : 'false');
            }
        });
    }

    function formatQuickRange(min, max, options) {
        options = options || {};
        if (!min && !max) return '';

        var formatValue = function(value) {
            return (options.prefix || '') + formatNum(value, !!options.raw);
        };
        var suffix = options.suffix || '';

        if (min && max) return formatValue(min) + ' – ' + formatValue(max) + suffix;
        if (min) return formatValue(min) + '+' + suffix;
        return '≤ ' + formatValue(max) + suffix;
    }

    function getMakeLabel(termId) {
        var $opt = $('.car-filter-make .car-filter-dropdown-option[data-value="' + termId + '"]').first();
        if ($opt.length) {
            return $opt.clone().children('.car-filter-count').remove().end().text().trim();
        }
        return 'Brand: ' + termId;
    }

    function getModelLabel(termId) {
        var $opt = $('.car-filter-model .car-filter-dropdown-option[data-value="' + termId + '"]').first();
        if ($opt.length) {
            return $opt.clone().children('.car-filter-count').remove().end().text().trim();
        }
        return 'Model: ' + termId;
    }

    function updateLocationRadiusUI(radiusKm) {
        $('#tcp-location-radius-value').text(radiusKm + ' km');
        $('.tcp-radius-preset').removeClass('active');
        $('.tcp-radius-preset[data-radius="' + radiusKm + '"]').addClass('active');
    }

    function syncLocationParamsToUrl() {
        if (typeof window === 'undefined' || !window.history || !window.URLSearchParams) {
            return;
        }
        var url = new URL(window.location.href);
        var params = url.searchParams;

        params.delete('loc_lat');
        params.delete('loc_lng');
        params.delete('loc_radius');
        params.delete('loc_label');

        if (locationState.active && locationState.lat && locationState.lng && locationState.radiusKm > 0) {
            params.set('loc_lat', Number(locationState.lat).toFixed(6));
            params.set('loc_lng', Number(locationState.lng).toFixed(6));
            params.set('loc_radius', String(parseInt(locationState.radiusKm, 10)));
            if (locationState.label) {
                params.set('loc_label', locationState.label);
            }
        }

        window.history.replaceState({}, '', url.toString());
    }

    function hydrateLocationFromUrl() {
        if (typeof window === 'undefined' || !window.URLSearchParams) {
            return false;
        }

        var params = new URLSearchParams(window.location.search);
        var lat = parseFloat(params.get('loc_lat') || '');
        var lng = parseFloat(params.get('loc_lng') || '');
        var radius = parseInt(params.get('loc_radius') || '', 10);
        var label = params.get('loc_label') || '';

        if (isNaN(lat) || isNaN(lng)) {
            return false;
        }

        if (isNaN(radius) || radius < 1) {
            radius = 25;
        } else if (radius > 200) {
            radius = 200;
        }

        locationState.lat = lat;
        locationState.lng = lng;
        locationState.radiusKm = radius;
        locationState.label = label;
        locationState.active = true;

        var searchInput = document.getElementById('tcp-location-search');
        if (searchInput && label) {
            searchInput.value = label;
        }

        return true;
    }

    function getZoomForRadius(radiusKm) {
        if (radiusKm <= 1) return 12.8;
        if (radiusKm <= 2) return 12.2;
        if (radiusKm <= 3) return 11.8;
        if (radiusKm <= 5) return 11.4;
        if (radiusKm <= 10) return 10.6;
        if (radiusKm <= 25) return 9.7;
        if (radiusKm <= 50) return 8.8;
        if (radiusKm <= 100) return 7.9;
        return 7.0;
    }

    function syncLocationVisuals(shouldAdjustZoom) {
        if (!locationMap || !locationCircle || !locationState.lat || !locationState.lng) {
            return;
        }
        var center = { lat: locationState.lat, lng: locationState.lng };
        locationCircle.setCenter(center);
        locationCircle.setRadius(locationState.radiusKm * 1000);
        if (shouldAdjustZoom) {
            locationMap.setZoom(getZoomForRadius(locationState.radiusKm));
        }
    }

    function setLocationPoint(lat, lng, shouldAdjustZoom) {
        locationState.lat = lat;
        locationState.lng = lng;
        if (locationCircle && locationCircle.getMap() === null) {
            locationCircle.setMap(locationMap);
        }
        locationMap.panTo({ lat: lat, lng: lng });
        syncLocationVisuals(!!shouldAdjustZoom);
    }

    function getLocationLabelFromComponents(components) {
        var comps = components || [];
        function get(type) {
            var comp = comps.find(function(c) {
                return c.types && c.types.indexOf(type) !== -1;
            });
            return comp ? comp.long_name : '';
        }

        var districtMap = {
            'Lemesos': 'Limassol',
            'Lefkosia': 'Nicosia',
            'Larnaka': 'Larnaca',
            'Ammochostos': 'Famagusta',
            'Pafos': 'Paphos'
        };

        var locality = get('locality') || get('postal_town') || get('administrative_area_level_2');
        var admin1 = get('administrative_area_level_1');
        var admin1Mapped = districtMap[admin1] || admin1;
        return locality || admin1Mapped || '';
    }

    function reverseGeocodeCenter() {
        if (!locationMap || !locationGeocoder) {
            return;
        }

        var center = locationMap.getCenter();
        if (!center) {
            return;
        }

        locationGeocoder.geocode(
            { location: center, region: 'CY', language: 'en' },
            function(results, status) {
                if (status !== 'OK' || !results || !results.length) {
                    return;
                }
                var result = results[0];
                var searchInput = document.getElementById('tcp-location-search');
                if (searchInput) {
                    searchInput.value = result.formatted_address || '';
                }
                locationState.label = getLocationLabelFromComponents(result.address_components || []) || (result.formatted_address || '');
            }
        );
    }

    function initLocationMap() {
        if (typeof google === 'undefined' || !google.maps) {
            return;
        }

        var mapEl = document.getElementById('tcp-location-map');
        if (!mapEl) {
            return;
        }

        if (!locationMap) {
            var defaultCenter = { lat: 35.1856, lng: 33.3823 };
            locationMap = new google.maps.Map(mapEl, {
                center: defaultCenter,
                zoom: 8,
                mapTypeControl: false,
                streetViewControl: false,
                fullscreenControl: false
            });
            locationGeocoder = new google.maps.Geocoder();

            locationCircle = new google.maps.Circle({
                map: locationMap,
                strokeColor: '#0d86e3',
                strokeOpacity: 0.8,
                strokeWeight: 2,
                fillColor: '#0d86e3',
                fillOpacity: 0.15,
                radius: locationState.radiusKm * 1000
            });

            locationMap.addListener('click', function(e) {
                setLocationPoint(e.latLng.lat(), e.latLng.lng(), false);
            });

            locationMap.addListener('center_changed', function() {
                if (!locationMap) return;
                var center = locationMap.getCenter();
                if (!center) return;
                locationState.lat = center.lat();
                locationState.lng = center.lng();
                syncLocationVisuals(false);
            });

            locationMap.addListener('idle', function() {
                if (reverseGeocodeTimer) {
                    clearTimeout(reverseGeocodeTimer);
                }
                reverseGeocodeTimer = setTimeout(reverseGeocodeCenter, 120);
            });

            var searchInput = document.getElementById('tcp-location-search');
            if (searchInput) {
                locationAutocomplete = new google.maps.places.Autocomplete(searchInput, {
                    componentRestrictions: { country: 'cy' },
                    fields: ['geometry', 'formatted_address', 'address_components'],
                    types: ['geocode']
                });

                locationAutocomplete.addListener('place_changed', function() {
                    var place = locationAutocomplete.getPlace();
                    if (!place.geometry || !place.geometry.location) {
                        return;
                    }
                    locationState.label = getLocationLabelFromComponents(place.address_components || []) || (place.formatted_address || searchInput.value || '');
                    if (place.formatted_address) {
                        searchInput.value = place.formatted_address;
                    }
                    setLocationPoint(place.geometry.location.lat(), place.geometry.location.lng(), true);
                });
            }
        }

        var shouldUseSaved = locationState.lat && locationState.lng;
        if (shouldUseSaved) {
            locationMap.setCenter({ lat: locationState.lat, lng: locationState.lng });
            syncLocationVisuals(true);
        } else {
            var currentCenter = locationMap.getCenter();
            if (currentCenter) {
                locationState.lat = currentCenter.lat();
                locationState.lng = currentCenter.lng();
                syncLocationVisuals(true);
            }
        }

        updateLocationRadiusUI(locationState.radiusKm);
    }

    // Remove single filter chip
    $chips.on('click', '.tcp-chip-remove', function(e) {
        e.stopPropagation();
        var key = $(this).data('filter');
        clearFilter(key);
        CarFilters.triggerFilter(group);
    });

    // Clear all
    $chips.on('click', '#tcp-clear-all', function() {
        allFilterKeys.concat(['location_radius']).forEach(function(key) {
            clearFilter(key);
        });
        setFavoritesOnly(false, false);
        resetSort();
        CarFilters.triggerFilter(group);
    });

    function clearFilter(key) {
        if (key === 'make' || key === 'model') {
            CarFilters.setState(group, key, '', '');
            // Reset dropdown UI
            var cls = key === 'make' ? '.car-filter-make' : '.car-filter-model';
            $(cls + ' .car-filter-dropdown-option').removeClass('selected');
            $(cls + ' .car-filter-dropdown-option[data-value=""]').addClass('selected');
            $(cls + ' .car-filter-dropdown-text').addClass('placeholder').text(key === 'make' ? 'All Brands' : 'All Models');
            $(cls + ' select').val('');
            if (key === 'make') {
                $(document).trigger('carFilters:makeChanged', [group, '']);
            }
        } else if (key.match(/_(min|max)$/)) {
            CarFilters.setState(group, key, '');
            // Clear input
            var parts = key.split('_');
            var bound = parts.pop(); // min or max
            var field = parts.join('_');
            var filterCls = filterClassMap[field] || field;
            $('.car-filter-' + filterCls + ' .car-filter-input-' + bound).val('');
        } else if (key === 'location_radius') {
            locationState.active = false;
            locationState.radiusKm = 25;
            locationState.label = '';
            updateLocationRadiusUI(locationState.radiusKm);
            if (locationCircle) {
                locationCircle.setRadius(locationState.radiusKm * 1000);
            }
            syncLocationParamsToUrl();
        } else {
            CarFilters.setState(group, key, '');
            var filterCls = filterClassMap[key] || key;
            var $dd = $('.car-filter-' + filterCls + ' .car-filter-dropdown');
            $dd.find('.car-filter-dropdown-option').removeClass('selected');
            $dd.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
            $dd.find('.car-filter-dropdown-text').addClass('placeholder').text($dd.find('select option:first').text());
            $dd.find('select').val('');
        }
    }

    /* ── No-results clear all button ── */
    $wrapper.on('click', '#tcp-no-results-clear-btn', function() {
        allFilterKeys.concat(['location_radius']).forEach(function(key) {
            clearFilter(key);
        });
        setFavoritesOnly(false, false);
        resetSort();
        CarFilters.triggerFilter(group);
    });

    /* ── Modal apply / clear buttons ── */
    $('#tcp-modal-apply-btn').on('click', function() {
        CarFilters.triggerFilter(group);
    });
    $('#tcp-modal-clear-btn').on('click', function() {
        allFilterKeys.forEach(function(key) {
            clearFilter(key);
        });
        setFavoritesOnly(false, false);
        resetSort();
        CarFilters.triggerFilter(group);
    });

    $('.tcp-location-radius-presets').on('click', '.tcp-radius-preset', function() {
        var radius = parseInt($(this).data('radius'), 10);
        if (isNaN(radius) || radius <= 0) return;
        locationState.radiusKm = radius;
        updateLocationRadiusUI(radius);
        if (locationCircle) {
            locationCircle.setRadius(radius * 1000);
        }
        if (locationMap) {
            locationMap.setZoom(getZoomForRadius(radius));
        }
    });

    $('#tcp-location-apply-btn').on('click', function() {
        if (!locationState.lat || !locationState.lng) {
            closeLocationModal();
            return;
        }
        if (!locationState.label) {
            locationState.label = $('#tcp-location-search').val() || t('Selected area');
        }
        locationState.active = true;
        closeLocationModal();
        buildChips();
        syncLocationParamsToUrl();
        CarFilters.triggerFilter(group);
    });

    $('#tcp-location-clear-btn').on('click', function() {
        clearFilter('location_radius');
        closeLocationModal();
        buildChips();
        syncLocationParamsToUrl();
        CarFilters.triggerFilter(group);
    });

    // Rebuild chips whenever filters update
    $(document).on('carFilters:updated', function(e, g, data) {
        if (data.pagination_html !== undefined) {
            $pagination.html(data.pagination_html || '');
        }
        $container.data('page', data.current_page || 1);
        $container.data('max-pages', data.max_pages || 1);
        if (data.found_posts !== undefined) {
            updateResultsCount(data.found_posts);
        }
        syncLocationParamsToUrl();
        buildChips();
        closeModal();
    });

    // Also rebuild chips when state changes (before AJAX completes)
    if (window.CarFilters) {
        CarFilters.subscribe(group, function() {
            buildChips();
        });
    }

    /* ── Sort dropdown ── */
    var $sort = $('#tcp-sort');
    var $sortBtn = $('#tcp-sort-btn');
    var $sortLabel = $('#tcp-sort-label');

    $sortBtn.on('click', function(e) {
        e.stopPropagation();
        $sort.toggleClass('open');
        $sortBtn.attr('aria-expanded', $sort.hasClass('open') ? 'true' : 'false');
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#tcp-sort').length) {
            $sort.removeClass('open');
            $sortBtn.attr('aria-expanded', 'false');
        }
    });

    $sort.on('click', '.tcp-sort-option', function() {
        var $opt = $(this);
        var orderby = $opt.data('orderby');
        var order = $opt.data('order');

        // Update UI
        $sort.find('.tcp-sort-option').removeClass('selected');
        $opt.addClass('selected');
        $sortLabel.text($opt.text());
        $sort.removeClass('open');
        $sortBtn.attr('aria-expanded', 'false');

        // Update listing_atts and reload page 1
        var atts = $container.data('atts') || {};
        atts.orderby = orderby;
        atts.order = order;
        $container.data('atts', atts);
        // Also update the data attribute for future reads
        $container.attr('data-atts', JSON.stringify(atts));

        loadPage(1, { scroll: false });
    });

    /* ── AJAX pagination ── */
    function loadPage(page, opts) {
        opts = opts || {};
        syncPostsPerPage();
        var filterData = (window.CarFilters && CarFilters.getFilterData)
            ? CarFilters.getFilterData(group)
            : {};
        var listingAtts = $container.data('atts') || {};

        $wrapper.removeClass('car-listings-loading-cleared').addClass('car-listings-loading');
        var loadingWatchdog = window.setTimeout(function() {
            if ($wrapper.hasClass('car-listings-loading')) {
                console.error('[AutoAgora cars template] loading watchdog cleared stuck state', { page: page });
                $wrapper.removeClass('car-listings-loading').addClass('car-listings-loading-cleared');
            }
        }, 20000);
        console.info('[AutoAgora cars template] ajax start', {
            page: page,
            filters: filterData,
            listingAtts: listingAtts,
            locationActive: locationState.active
        });

        $.ajax({
            url: carFiltersConfig.ajaxUrl,
            type: 'POST',
            timeout: 15000,
            data: $.extend({
                action: 'car_filters_filter_listings',
                nonce: carFiltersConfig.nonce,
                response_format: 'json',
                page: page,
                listing_atts: JSON.stringify(listingAtts),
                location_lat: locationState.active ? locationState.lat : '',
                location_lng: locationState.active ? locationState.lng : '',
                location_radius_km: locationState.active ? locationState.radiusKm : ''
            }, filterData),
            success: function(response) {
                console.info('[AutoAgora cars template] ajax response', response);
                if (response.success) {
                    try {
                        if (response.data.cards && window.carListingCardsRender) {
                            window.carListingCardsRender.renderInto($wrapper[0], response.data.cards);
                        } else if (response.data.html) {
                            $wrapper.html(response.data.html);
                        }
                    } catch (renderError) {
                        console.error('[AutoAgora cars template] render error', renderError);
                        $wrapper.html('<p class="car-listings-no-results">' + escapeHtml(t('Unable to render listings. Please refresh the page.')) + '</p>');
                    }
                    $pagination.html(response.data.pagination_html || '');
                    $container.data('page', response.data.current_page);
                    $container.data('max-pages', response.data.max_pages);
                    if (response.data.found_posts !== undefined) {
                        updateResultsCount(response.data.found_posts);
                    }
                    syncLocationParamsToUrl();
                    if (opts.scroll !== false) {
                        $('html, body').animate({ scrollTop: $container.offset().top - 20 }, 300);
                    }
                }
            },
            error: function(xhr, status, error) {
                console.error('[AutoAgora cars template] ajax error', {
                    status: status,
                    error: error,
                    responseText: xhr && xhr.responseText ? xhr.responseText.slice(0, 800) : ''
                });
                $wrapper.html('<p class="car-listings-no-results">' + escapeHtml(t('Unable to load listings. Please refresh the page.')) + '</p>');
            },
            complete: function() {
                window.clearTimeout(loadingWatchdog);
                $wrapper.removeClass('car-listings-loading');
                console.info('[AutoAgora cars template] ajax complete', { page: page });
            }
        });
    }

    $container.on('click', '.tcp-pagination a.page-numbers', function(e) {
        e.preventDefault();
        var href = $(this).attr('href');
        var page = parseInt(href.replace('#', ''), 10);
        if (page && page > 0) {
            loadPage(page);
        }
    });

    // Sync posts_per_page before filter AJAX fires
    $(document).on('ajaxSend', function(e, xhr, settings) {
        if (settings.data && typeof settings.data === 'string' &&
            settings.data.indexOf('action=car_filters_filter_listings') !== -1) {
            syncPostsPerPage();
            // Re-inject updated listing_atts into the request data
            var atts = $container.data('atts') || {};
            settings.data = settings.data.replace(
                /listing_atts=[^&]*/,
                'listing_atts=' + encodeURIComponent(JSON.stringify(atts))
            );
            var latParam = 'location_lat=' + encodeURIComponent(locationState.active ? locationState.lat : '');
            var lngParam = 'location_lng=' + encodeURIComponent(locationState.active ? locationState.lng : '');
            var radiusParam = 'location_radius_km=' + encodeURIComponent(locationState.active ? locationState.radiusKm : '');
            settings.data = settings.data.replace(/&location_lat=[^&]*/g, '');
            settings.data = settings.data.replace(/&location_lng=[^&]*/g, '');
            settings.data = settings.data.replace(/&location_radius_km=[^&]*/g, '');
            settings.data += '&' + latParam + '&' + lngParam + '&' + radiusParam;
            if (settings.data.indexOf('response_format=') === -1) {
                settings.data += '&response_format=json';
            }
        }
    });

    // Build initial chips on load
    $(document).ready(function() {
        var hasLocationFromUrl = hydrateLocationFromUrl();
        setTimeout(buildChips, 100);
        updateLocationRadiusUI(locationState.radiusKm);
        var initialCountText = parseInt($results.text(), 10);
        if (!isNaN(initialCountText)) {
            updateClearAllButton(initialCountText);
        }
        if (hasLocationFromUrl) {
            var initialListingsPage = (window.CarFilters && CarFilters.resolveListingsPageFromContainerOrUrl)
                ? CarFilters.resolveListingsPageFromContainerOrUrl($container)
                : (parseInt($container.attr('data-page'), 10) || 1);
            loadPage(initialListingsPage, { scroll: false });
        }
    });

})(jQuery);

/* ── FAQ accordion ── */
document.querySelectorAll('.faq-trigger').forEach(function(btn) {
    btn.addEventListener('click', function() {
        var item = this.closest('.faq-item');
        var isOpen = item.classList.contains('open');
        // Close all
        document.querySelectorAll('.faq-item.open').forEach(function(el) {
            el.classList.remove('open');
            el.querySelector('.faq-trigger').setAttribute('aria-expanded', 'false');
        });
        // Open clicked if it was closed
        if (!isOpen) {
            item.classList.add('open');
            this.setAttribute('aria-expanded', 'true');
        }
    });
});
</script>

<?php get_footer(); ?>
