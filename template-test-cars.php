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
                <span id="tcp-sort-label">Newest</span>
            </button>
            <div class="tcp-sort-menu" id="tcp-sort-menu">
                <button type="button" class="tcp-sort-option selected" data-orderby="date" data-order="DESC">Newest</button>
                <button type="button" class="tcp-sort-option" data-orderby="date" data-order="ASC">Oldest</button>
                <button type="button" class="tcp-sort-option" data-orderby="price" data-order="ASC">Price: Low to High</button>
                <button type="button" class="tcp-sort-option" data-orderby="price" data-order="DESC">Price: High to Low</button>
                <button type="button" class="tcp-sort-option" data-orderby="mileage" data-order="ASC">Mileage: Low to High</button>
                <button type="button" class="tcp-sort-option" data-orderby="mileage" data-order="DESC">Mileage: High to Low</button>
                <button type="button" class="tcp-sort-option" data-orderby="year" data-order="DESC">Year: Newest</button>
                <button type="button" class="tcp-sort-option" data-orderby="year" data-order="ASC">Year: Oldest</button>
            </div>
        </div>
    </div>
</div>

<!-- Filters modal -->
<div class="tcp-filters-modal-overlay" id="tcp-filters-modal-overlay">
    <div class="tcp-filters-modal">
        <div class="tcp-filters-modal-header">
            <h2>Filters</h2>
            <button type="button" class="tcp-filters-modal-close" id="tcp-filters-modal-close" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="tcp-filters-modal-body">
            <?php echo do_shortcode( '[car_filters filters="make,model,price,mileage,fuel,body,year" mode="ajax" target="test-cars-listings" layout="vertical" show_button="false"]' ); ?>
        </div>
        <div class="tcp-filters-modal-footer">
            <button type="button" class="tcp-modal-apply-btn" id="tcp-modal-apply-btn">Apply Filters</button>
            <button type="button" class="tcp-modal-clear-btn" id="tcp-modal-clear-btn">Clear All</button>
        </div>
    </div>
</div>

<!-- Location modal -->
<div class="tcp-filters-modal-overlay" id="tcp-location-modal-overlay">
    <div class="tcp-filters-modal tcp-location-modal">
        <div class="tcp-filters-modal-header">
            <h2>Location Radius</h2>
            <button type="button" class="tcp-filters-modal-close" id="tcp-location-modal-close" aria-label="Close">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>
        <div class="tcp-filters-modal-body">
            <div class="tcp-location-search-wrap">
                <input type="text" id="tcp-location-search" class="tcp-location-search" placeholder="Search location in Cyprus">
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
            <button type="button" class="tcp-modal-apply-btn" id="tcp-location-apply-btn">Apply Location</button>
            <button type="button" class="tcp-modal-clear-btn" id="tcp-location-clear-btn">Clear Location</button>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="tcp-main">
    <h1 class="tcp-heading">Used Cars for Sale in Cyprus</h1>
    <p class="tcp-results-count" id="tcp-results-count">
        <?php echo esc_html( number_format_i18n( (int) $cars_query->found_posts ) . ' results found' ); ?>
    </p>
    <div class="car-listings-container"
         id="test-cars-listings"
         data-atts="<?php echo esc_attr( wp_json_encode( $listing_atts ) ); ?>"
         data-page="<?php echo esc_attr( (string) $current_page ); ?>"
         data-max-pages="<?php echo esc_attr( $cars_query->max_num_pages ); ?>"
         data-server-filtered="true">

        <div class="car-listings-wrapper tcp-grid">
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
                    'prev_text' => 'Previous',
                    'next_text' => 'Next',
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
            <h2 class="cars-intro-heading">Buying a Used Car in Cyprus on AutoAgora</h2>
            <p>
                Browse <strong>600+ used cars for sale in Cyprus</strong> from trusted dealerships and private sellers across Nicosia, Limassol, Larnaca, and Paphos. Whether you're looking for a fuel-efficient hatchback for city driving, a family SUV, or a luxury sedan, AutoAgora makes it easy to compare prices, specs, and photos - all in one place.
            </p>
            <p>
                Use the filters above to narrow your search by make, model, price range, fuel type, mileage, and more. Every listing includes full vehicle details, high-quality photos, and direct contact with the seller. Can't find what you're looking for? <a href="/buyer-requests/">Post a buyer request</a> and let dealers across Cyprus come to you.
            </p>
        </section>

        <section class="cars-faq">
            <h2 class="cars-faq-heading">Frequently Asked Questions About Buying a Used Car in Cyprus</h2>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    How much does a used car cost in Cyprus?
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p>Used car prices in Cyprus vary widely depending on the make, model, age, and mileage. Budget-friendly options like the Nissan Note or Toyota Yaris typically start around €8,000–€13,000, while popular SUVs like the Mazda CX-5 or Volkswagen Tiguan range from €15,000–€30,000. Luxury and performance vehicles can go well above €50,000. You can use the price filter above to browse cars within your budget.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    Where can I buy a used car in Cyprus?
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p>You can buy used cars from licensed dealerships or private sellers across all major cities in Cyprus, including Nicosia, Limassol, Larnaca, and Paphos. AutoAgora lists vehicles from verified dealers and individuals across the island, so you can compare options from multiple sources without visiting each one in person.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    What should I check before buying a used car in Cyprus?
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p>Before purchasing, you should verify the vehicle's service history and mileage, check for any outstanding finance or liens, inspect the car for accident damage or rust (especially underbody), confirm the MOT (road worthiness) status, and make sure the registration documents match the seller's details. It's also a good idea to take the car for a test drive and have a trusted mechanic inspect it if possible.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    Can I finance a used car purchase in Cyprus?
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p>Yes, most banks in Cyprus offer car loans for used vehicles. Typical loan terms range from 1 to 7 years, and interest rates depend on the bank and your credit profile. Some dealerships on AutoAgora also offer in-house financing options. It's worth comparing offers from multiple lenders before committing.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    Are used cars in Cyprus left-hand drive or right-hand drive?
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p>Cyprus drives on the left side of the road, and the vast majority of cars on the island are right-hand drive (RHD) - meaning the steering wheel is on the right. A lot of vehicles are imported from the UK or Japan, where driving is also on the left. You'll find some left-hand drive cars imported from mainland Europe, but RHD is the standard in Cyprus.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    What are the most popular used cars in Cyprus?
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p>The most popular used cars in Cyprus include the Toyota Yaris, Nissan Note, Mazda CX-5, BMW 3 Series, Mercedes-Benz A-Class, Volkswagen Golf, and Nissan Qashqai. SUVs and compact hatchbacks tend to be the most in-demand body types, followed by saloons. Petrol hybrids have been growing in popularity in recent years.</p>
                </div>
            </div>

            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    How do I sell my car on AutoAgora?
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p>Selling your car on AutoAgora is free and straightforward. Simply create an account, click "Sell My Car," and fill in your vehicle's details including photos, price, mileage, and specifications. Your listing will be visible to buyers across Cyprus. For more details, visit our <a href="/how-to-sell-your-car/">guide on how to sell your car</a>.</p>
                </div>
            </div>
        </section>

    </div><!-- .cars-seo-content -->
</div>

<style>
body {
    background-color: var(--bricks-color-lgsrvt);
}

/* ============================================
   Filters Bar
   ============================================ */
.tcp-filters-bar {
    border-bottom: 1px solid #e5e7eb;
    background: #fff;
    position: sticky;
    top: 0;
    z-index: 100;
}
.tcp-filters-bar-inner {
    max-width: var(--max-width);
    margin: 0 auto;
    padding: 0.75rem 1rem;
    display: flex;
    align-items: center;
    gap: 0.75rem;
}
.tcp-filters-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    padding: 0.5rem 1rem;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    background: #fff;
    color: #2a3546;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: border-color 0.15s, background 0.15s;
    flex-shrink: 0;
}
.tcp-filters-btn:hover {
    border-color: #bbb;
    background: #f9fafb;
}

/* Sort dropdown */
.tcp-sort {
    position: relative;
    flex-shrink: 0;
    margin-left: auto;
}
.tcp-sort-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.5rem 1rem;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    background: #fff;
    color: #2a3546;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: border-color 0.15s, background 0.15s;
}
.tcp-sort-btn:hover {
    border-color: #bbb;
    background: #f9fafb;
}
.tcp-sort-menu {
    display: none;
    position: absolute;
    top: calc(100% + 0.25rem);
    right: 0;
    min-width: 200px;
    background: #fff;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
    z-index: 200;
    padding: 0.25rem 0;
}
.tcp-sort.open .tcp-sort-menu {
    display: block;
}
.tcp-sort-option {
    display: block;
    width: 100%;
    padding: 0.6rem 1rem;
    border: none;
    background: none;
    color: #2a3546;
    font-size: 0.875rem;
    text-align: left;
    cursor: pointer;
    transition: background 0.1s;
}
.tcp-sort-option:hover {
    background: rgba(13, 134, 227, 0.08);
}
.tcp-sort-option.selected {
    font-weight: 700;
    background: rgba(13, 134, 227, 0.12);
}

/* Active filter chips */
.tcp-active-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    overflow-x: auto;
    flex: 1;
    -ms-overflow-style: none;
    scrollbar-width: none;
}
.tcp-active-filters::-webkit-scrollbar {
    display: none;
}
.tcp-chip {
    display: inline-flex;
    align-items: center;
    gap: 0.35rem;
    padding: 0.3rem 0.6rem;
    border-radius: 2rem;
    background: #f0f4f8;
    color: #2a3546;
    font-size: 0.8125rem;
    font-weight: 500;
    white-space: nowrap;
    flex-shrink: 0;
}
.tcp-chip-remove {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 1rem;
    height: 1rem;
    border: none;
    border-radius: 50%;
    background: rgba(0,0,0,0.1);
    color: #2a3546;
    font-size: 0.75rem;
    line-height: 0;
    cursor: pointer;
    padding: 0;
    padding-bottom: 1px;
    flex-shrink: 0;
}
.tcp-chip-remove:hover {
    background: rgba(0,0,0,0.2);
}
.tcp-chip-clear {
    display: inline-flex;
    align-items: center;
    padding: 0.3rem 0.6rem;
    border: none;
    border-radius: 2rem;
    background: none;
    color: #0d86e3;
    font-size: 0.8125rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    flex-shrink: 0;
}
.tcp-chip-clear:hover {
    text-decoration: underline;
}

/* ============================================
   Filters Modal
   ============================================ */
.tcp-filters-modal-overlay {
    display: none;
    position: fixed;
    inset: 0;
    z-index: 10000;
    background: rgba(0, 0, 0, 0.5);
    justify-content: center;
    align-items: center;
    padding: 2rem 1rem;
    overflow-y: auto;
}
.tcp-filters-modal-overlay.open {
    display: flex;
}
.tcp-filters-modal {
    background: #fff;
    border-radius: 1rem;
    width: 100%;
    max-width: 600px;
    max-height: 90vh;
    overflow: hidden;
    box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    display: flex;
    flex-direction: column;
}
.tcp-filters-modal-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 1rem 1.25rem;
    border-bottom: 1px solid #e5e7eb;
}
.tcp-filters-modal-header h2 {
    margin: 0;
    font-size: 1.125rem;
    font-weight: 700;
    color: #2a3546;
}
.tcp-filters-modal-close {
    display: flex;
    align-items: center;
    justify-content: center;
    width: 2rem;
    height: 2rem;
    border: none;
    border-radius: 50%;
    background: #f0f0f0;
    color: #333;
    cursor: pointer;
    padding: 0;
}
.tcp-filters-modal-close:hover {
    background: #e0e0e0;
}
.tcp-filters-modal-body {
    padding: 1.25rem;
    overflow-y: auto;
    flex: 1;
    min-height: 0;
}
.tcp-filters-modal-header {
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}
.tcp-filters-modal-footer {
    flex-shrink: 0;
    position: relative;
    z-index: 2;
}
.tcp-filters-modal-body .car-filters-container {
    width: 100%;
}
.tcp-filters-modal-footer {
    padding: 1rem 1.25rem;
    border-top: 1px solid #e5e7eb;
    display: flex;
    flex-direction: column;
    gap: 0.5rem;
}
.tcp-modal-apply-btn {
    width: 100%;
    padding: 0.75rem 1.5rem;
    border: none;
    border-radius: 0.5rem;
    background-color: #0d86e3;
    color: #fff;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    transition: background-color 0.2s ease;
}
.tcp-modal-apply-btn:hover {
    background-color: #0b75c9;
}
.tcp-modal-apply-btn:active {
    background-color: #0965b0;
}
.tcp-modal-clear-btn {
    width: 100%;
    padding: 0.75rem 1.5rem;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    background: #fff;
    color: #2a3546;
    font-size: 16px;
    font-weight: 600;
    cursor: pointer;
    text-align: center;
    transition: border-color 0.15s, background 0.15s;
}
.tcp-modal-clear-btn:hover {
    border-color: #bbb;
    background: #f9fafb;
}
.tcp-filters-modal-body .car-filters-wrapper {
    gap: 1rem;
}
.tcp-filters-modal-body .car-filters-item {
    width: 100%;
    min-width: 0;
}
.tcp-filters-modal-body .car-filters-wrapper {
    flex-wrap: wrap;
    flex-direction: row;
}
.tcp-filters-modal-body .car-filters-item {
    flex: 0 0 100%;
}
.tcp-filters-modal-body .car-filters-item-make,
.tcp-filters-modal-body .car-filters-item-model,
.tcp-filters-modal-body .car-filters-item-fuel,
.tcp-filters-modal-body .car-filters-item-body {
    flex: 0 0 calc(50% - 0.5rem);
}
.tcp-location-modal {
    max-width: 860px;
    width: min(92vw, 860px);
    height: min(92vh, 860px);
}
.tcp-location-modal .tcp-filters-modal-body {
    display: flex;
    flex-direction: column;
    overflow: hidden;
    padding: 1rem 1rem 0.75rem;
}
.tcp-location-modal .tcp-filters-modal-footer {
    padding: 0.75rem 1rem 1rem;
    gap: 0.45rem;
}
.tcp-location-search-wrap {
    margin-bottom: 0.6rem;
}
.tcp-location-search {
    width: 100%;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    padding: 0.6rem 0.8rem;
    font-size: 0.95rem;
}
.tcp-location-map {
    width: 100%;
    height: 100%;
    border: 1px solid #dfe2e6;
    border-radius: 0.75rem;
    overflow: hidden;
    background: #f8fafc;
}
.tcp-location-map-wrap {
    position: relative;
    flex: 1 1 auto;
    min-height: 360px;
}
.tcp-location-center-pin {
    position: absolute;
    left: 50%;
    top: 50%;
    width: 20px;
    height: 20px;
    transform: translate(-50%, -100%);
    pointer-events: none;
    z-index: 5;
}
.tcp-location-center-pin::before {
    content: '';
    position: absolute;
    inset: 0;
    border-radius: 50% 50% 50% 0;
    background: #0d86e3;
    transform: rotate(-45deg);
}
.tcp-location-center-pin::after {
    content: '';
    position: absolute;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #fff;
    left: 6px;
    top: 6px;
}
.tcp-location-radius-row {
    margin-top: 0;
}
.tcp-location-radius-presets {
    margin-top: 0;
    display: grid !important;
    grid-template-columns: repeat(6, minmax(0, 1fr));
    gap: 0.45rem;
    align-items: stretch;
    justify-content: stretch;
    position: relative;
    z-index: 3;
    width: 100%;
}
.tcp-radius-preset {
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    border: 1px solid #dfe2e6;
    background: #fff;
    color: #2a3546;
    border-radius: 999px;
    padding: 0.32rem 0.55rem;
    font-size: 0.78rem;
    font-weight: 600;
    cursor: pointer;
    line-height: 1.2;
    min-height: 30px;
    white-space: nowrap;
    width: 100%;
}
.tcp-radius-preset:hover {
    background: #f8fafc;
}
.tcp-radius-preset.active {
    border-color: #0d86e3;
    color: #0d86e3;
    background: rgba(13, 134, 227, 0.08);
}
.pac-container {
    z-index: 10050 !important;
}

@media (max-width: 768px) {
    #tcp-sort-label {
        display: none;
    }
    .tcp-filters-bar-inner {
        flex-wrap: wrap;
        row-gap: 0;
    }
    .tcp-active-filters {
        order: 10;
        flex-basis: 100%;
        width: 100%;
        padding-top: 1rem;
        padding-bottom: 0.5rem;
        border-top: 1px solid #e5e7eb;
        margin-top: 1rem;
    }
    .tcp-active-filters:empty {
        display: none;
    }
    .tcp-filters-modal {
        max-width: 100%;
        max-height: 100%;
        height: 100%;
        border-radius: 0;
    }
    .tcp-filters-modal-overlay {
        padding: 0;
    }
    .tcp-location-map {
        height: 100%;
    }
    .tcp-location-modal {
        width: 100%;
        height: 100%;
        max-width: 100%;
    }
    .tcp-location-modal .tcp-filters-modal-body {
        padding: 0.75rem 0.75rem 0.5rem;
    }
    .tcp-location-modal .tcp-filters-modal-footer {
        padding: 0.6rem 0.75rem 0.75rem;
    }
    .tcp-location-map-wrap {
        min-height: 320px;
    }
    .tcp-location-radius-presets {
        display: grid !important;
        grid-template-columns: repeat(3, minmax(0, 1fr));
        gap: 0.35rem;
    }
    .tcp-radius-preset {
        width: 100%;
        min-height: 34px;
        font-size: 0.76rem;
        padding: 0.28rem 0.35rem;
    }
}

/* ============================================
   No-results clear button
   ============================================ */
.car-listings-no-results {
    grid-column: 1 / -1;
}
.tcp-clear-all-filters-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.4rem;
    padding: 0.55rem 1.25rem;
    border: 2px solid #dfe2e6;
    border-radius: 0.5rem;
    background: #fff;
    color: #2a3546;
    font-size: 0.9375rem;
    font-weight: 600;
    cursor: pointer;
    transition: border-color 0.15s, background 0.15s;
    grid-column: 1 / -1;
    justify-self: start;
    margin-top: 0.5rem;
}
.tcp-clear-all-filters-btn:hover {
    border-color: #bbb;
    background: #f9fafb;
}

/* ============================================
   Main Content
   ============================================ */
.tcp-main {
    max-width: 2000px;
    margin: 0 auto;
    padding: 1.5rem 1rem 6rem;
    background-color: var(--bricks-color-lgsrvt);
}
.tcp-heading {
    font-size: 1.5rem;
    font-weight: 500;
    color: #2a3546;
    margin: 0 0 1.25rem;
}
.tcp-results-count {
    margin: -0.5rem 0 1.25rem;
    font-size: 0.95rem;
    font-weight: 500;
    color: #475569;
}
.tcp-grid {
    display: grid;
    justify-content: start;
    justify-items: stretch;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
}
@media (max-width: 767px) {
    .tcp-grid {
        gap: 1rem;
    }
}
@media (max-width: 479px) {
    .tcp-grid {
        grid-template-columns: 1fr;
    }
}

/* ============================================
   Pagination
   ============================================ */
.tcp-pagination {
    margin-top: 2rem;
    text-align: center;
}
.tcp-pagination .page-numbers {
    list-style: none;
    display: flex;
    justify-content: center;
    align-items: stretch;
    gap: 0.35rem;
    padding: 0;
    margin: 0;
    flex-wrap: wrap;
}
.tcp-pagination .page-numbers li {
    list-style: none;
    display: flex;
}
.tcp-pagination .page-numbers a,
.tcp-pagination .page-numbers span {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 2.5rem;
    height: 2.5rem;
    padding: 0 0.75rem;
    border-radius: 0.5rem;
    text-decoration: none;
    font-size: 0.875rem;
    font-weight: 500;
    color: #fff;
    background: #0b72c1;
    cursor: pointer;
    transition: opacity 0.15s;
}
.tcp-pagination .page-numbers a:hover {
    opacity: 0.85;
}
.tcp-pagination .page-numbers .current {
    background: #084a7e;
    color: #fff;
    pointer-events: none;
    font-weight: 700;
}
.tcp-pagination .page-numbers .dots {
    background: transparent;
    color: #0b72c1;
    cursor: default;
    min-width: auto;
    padding: 0 0.25rem;
}

/* ============================================
   Loading state
   ============================================ */
.car-listings-wrapper.car-listings-loading {
    opacity: 0.5;
    pointer-events: none;
    transition: opacity 0.15s;
}

/* ============================================
   SEO Content: Intro + FAQ
   ============================================ */
.cars-seo-content {
    max-width: 800px;
    margin: 3rem auto 0;
    padding: 3rem 1rem 5rem;
}

.cars-intro {
    margin-bottom: 2.5rem;
}
.cars-intro-heading {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2a3546;
    margin: 0 0 0.75rem;
}
.cars-intro p {
    font-size: 1rem;
    line-height: 1.7;
    color: #2a3546;
    margin: 0 0 1rem;
}
.cars-intro p:last-child {
    margin-bottom: 0;
}
.cars-intro a {
    color: #0d86e3;
    text-decoration: none;
    font-weight: 500;
}
.cars-intro a:hover {
    text-decoration: underline;
}

.cars-faq-heading {
    font-size: 1.25rem;
    font-weight: 700;
    color: #2a3546;
    margin: 0 0 1.5rem;
}
.cars-faq .faq-item:first-child {
    border-top: none;
}

.faq-item {
    border-top: 1px solid #e5e7eb;
}
.faq-item:last-child {
    border-bottom: 1px solid #e5e7eb;
}

.faq-trigger {
    display: flex;
    align-items: center;
    justify-content: space-between;
    width: 100%;
    padding: 1rem 0;
    border: none;
    background: none;
    color: #2a3546;
    font-size: 0.9375rem;
    font-weight: 600;
    text-align: left;
    cursor: pointer;
    gap: 1rem;
    line-height: 1.4;
}
.faq-trigger:hover {
    color: #0d86e3;
}
.faq-trigger:hover .faq-chevron {
    stroke: #0d86e3;
}

.faq-chevron {
    flex-shrink: 0;
    transition: transform 0.2s ease;
    stroke: #2a3546;
}
.faq-item.open .faq-chevron {
    transform: rotate(180deg);
}

.faq-answer {
    overflow: hidden;
    max-height: 0;
    transition: max-height 0.25s ease;
}
.faq-item.open .faq-answer {
    max-height: 600px;
}
.faq-answer p {
    margin: 0 0 1.25rem;
    font-size: 0.9375rem;
    line-height: 1.7;
    color: #4b5563;
}
.faq-answer a {
    color: #0d86e3;
    text-decoration: none;
    font-weight: 500;
}
.faq-answer a:hover {
    text-decoration: underline;
}
</style>

<script>
(function($) {
    'use strict';

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
        $results.text(count.toLocaleString() + ' results found');
        updateClearAllButton(count);
    }

    function updateClearAllButton(count) {
        $wrapper.find('.tcp-clear-all-filters-btn').remove();
        if (count === 0) {
            var $noResults = $wrapper.find('.car-listings-no-results');
            if ($noResults.length) {
                $noResults.after('<button type="button" class="tcp-clear-all-filters-btn" id="tcp-no-results-clear-btn">Clear all filters</button>');
            }
        }
    }

    function resetSort() {
        $sort.find('.tcp-sort-option').removeClass('selected');
        $sort.find('.tcp-sort-option').first().addClass('selected');
        $sortLabel.text('Newest');
        var atts = $container.data('atts') || {};
        atts.orderby = 'date';
        atts.order = 'DESC';
        $container.data('atts', atts);
        $container.attr('data-atts', JSON.stringify(atts));
    }

    // Filter label map for chips
    var filterLabels = {
        make: 'Brand',
        model: 'Model',
        price_min: 'Price min',
        price_max: 'Price max',
        mileage_min: 'Mileage min',
        mileage_max: 'Mileage max',
        year_min: 'Year min',
        year_max: 'Year max',
        fuel_type: 'Fuel',
        body_type: 'Body',
        location_radius: 'Location'
    };

    /* ── Modal open/close ── */
    $('#tcp-filters-btn').on('click', function() {
        $overlay.addClass('open');
        $('body').css('overflow', 'hidden');
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

        // Make
        if (state.make && state.make.value) {
            var makeLabel = getMakeLabel(state.make.value);
            html += chip('make', makeLabel);
            hasAny = true;
        }
        // Model
        if (state.model && state.model.value) {
            var modelLabel = getModelLabel(state.model.value);
            html += chip('model', modelLabel);
            hasAny = true;
        }
        // Range filters
        ['price', 'mileage', 'year'].forEach(function(key) {
            var min = state[key + '_min'];
            var max = state[key + '_max'];
            var noComma = (key === 'year');
            if (min) {
                html += chip(key + '_min', filterLabels[key + '_min'] + ': ' + formatNum(min, noComma));
                hasAny = true;
            }
            if (max) {
                html += chip(key + '_max', filterLabels[key + '_max'] + ': ' + formatNum(max, noComma));
                hasAny = true;
            }
        });
        // Simple selects
        ['fuel_type', 'body_type'].forEach(function(key) {
            if (state[key]) {
                html += chip(key, filterLabels[key] + ': ' + state[key]);
                hasAny = true;
            }
        });
        if (locationState.active && locationState.lat && locationState.lng && locationState.radiusKm > 0) {
            var locationLabel = locationState.label || 'Selected area';
            html += chip('location_radius', filterLabels.location_radius + ': ' + locationLabel);
            hasAny = true;
        }

        if (hasAny) {
            html += '<button type="button" class="tcp-chip-clear" id="tcp-clear-all">Clear all</button>';
        }

        $chips.html(html);
    }

    function chip(key, label) {
        return '<span class="tcp-chip" data-filter="' + key + '">' +
               label +
               '<button type="button" class="tcp-chip-remove" data-filter="' + key + '" aria-label="Remove">&times;</button>' +
               '</span>';
    }

    function formatNum(val, raw) {
        var n = parseInt(String(val).replace(/,/g, ''), 10);
        if (!n) return val;
        return raw ? String(n) : n.toLocaleString();
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
        ['make', 'model', 'price_min', 'price_max', 'mileage_min', 'mileage_max',
         'year_min', 'year_max', 'fuel_type', 'body_type', 'location_radius'].forEach(function(key) {
            clearFilter(key);
        });
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
            var filterCls = field === 'fuel_type' ? 'fuel' : (field === 'body_type' ? 'body' : field);
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
            var filterCls = key === 'fuel_type' ? 'fuel' : (key === 'body_type' ? 'body' : key);
            var $dd = $('.car-filter-' + filterCls + ' .car-filter-dropdown');
            $dd.find('.car-filter-dropdown-option').removeClass('selected');
            $dd.find('.car-filter-dropdown-option[data-value=""]').addClass('selected');
            $dd.find('.car-filter-dropdown-text').addClass('placeholder').text($dd.find('select option:first').text());
            $dd.find('select').val('');
        }
    }

    /* ── No-results clear all button ── */
    $wrapper.on('click', '#tcp-no-results-clear-btn', function() {
        ['make', 'model', 'price_min', 'price_max', 'mileage_min', 'mileage_max',
         'year_min', 'year_max', 'fuel_type', 'body_type', 'location_radius'].forEach(function(key) {
            clearFilter(key);
        });
        resetSort();
        CarFilters.triggerFilter(group);
    });

    /* ── Modal apply / clear buttons ── */
    $('#tcp-modal-apply-btn').on('click', function() {
        CarFilters.triggerFilter(group);
    });
    $('#tcp-modal-clear-btn').on('click', function() {
        ['make', 'model', 'price_min', 'price_max', 'mileage_min', 'mileage_max',
         'year_min', 'year_max', 'fuel_type', 'body_type'].forEach(function(key) {
            clearFilter(key);
        });
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
            locationState.label = $('#tcp-location-search').val() || 'Selected area';
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
    });
    $(document).on('click', function(e) {
        if (!$(e.target).closest('#tcp-sort').length) {
            $sort.removeClass('open');
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

        $wrapper.addClass('car-listings-loading');

        $.ajax({
            url: carFiltersConfig.ajaxUrl,
            type: 'POST',
            data: $.extend({
                action: 'car_filters_filter_listings',
                nonce: carFiltersConfig.nonce,
                page: page,
                listing_atts: JSON.stringify(listingAtts),
                location_lat: locationState.active ? locationState.lat : '',
                location_lng: locationState.active ? locationState.lng : '',
                location_radius_km: locationState.active ? locationState.radiusKm : ''
            }, filterData),
            success: function(response) {
                if (response.success) {
                    $wrapper.html(response.data.html);
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
                console.error('Pagination AJAX error:', error);
            },
            complete: function() {
                $wrapper.removeClass('car-listings-loading');
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

    // Scroll modal body to bottom when fuel/body dropdowns are opened
    $(document).on('click', '.tcp-filters-modal-body .car-filters-item-fuel .car-filter-dropdown-button, .tcp-filters-modal-body .car-filters-item-body .car-filter-dropdown-button', function() {
        var modalBody = document.querySelector('.tcp-filters-modal-body');
        if (!modalBody) return;
        setTimeout(function() {
            modalBody.scrollTo({ top: modalBody.scrollHeight, behavior: 'smooth' });
        }, 50);
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
