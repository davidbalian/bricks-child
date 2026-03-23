<?php
/**
 * Managed car_make landing template.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

$landing = autoagora_get_current_car_make_landing();
if (!$landing) {
    status_header(404);
    nocache_headers();
    include get_404_template();
    return;
}

$group       = 'car-make-landing-' . sanitize_html_class($landing['slug']);
$listings_id = 'car-make-landing-results';

add_action('wp_head', function() use ($landing) {
    echo '<meta name="description" content="' . esc_attr($landing['meta_description']) . '">' . "\n";
    echo '<link rel="canonical" href="' . esc_url($landing['canonical']) . '">' . "\n";

    $schema = array(
        '@context'   => 'https://schema.org',
        '@type'      => 'FAQPage',
        'mainEntity' => array(),
    );
    foreach ($landing['faqs'] as $faq) {
        $schema['mainEntity'][] = array(
            '@type'          => 'Question',
            'name'           => $faq['question'],
            'acceptedAnswer' => array(
                '@type' => 'Answer',
                'text'  => $faq['answer'],
            ),
        );
    }
    echo '<script type="application/ld+json">' . "\n";
    echo wp_json_encode($schema, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    echo "\n</script>\n";
}, 5);

add_filter('pre_get_document_title', function() use ($landing) {
    return $landing['title'];
}, 20);

$listing_atts = array(
    'posts_per_page'     => 24,
    'orderby'            => 'date',
    'order'              => 'DESC',
    'card_type'          => 'car_card',
    'default_make_slug'  => $landing['make_slug'],
    'default_model_slug' => $landing['model_slug'],
);

// Build taxonomy filter: try model term first, fall back to all models of the make.
$tax_terms = array();
$model_term = get_term_by('slug', $landing['model_slug'], 'car_make');
if ($model_term && !is_wp_error($model_term)) {
    $tax_terms = array($model_term->term_id);
} else {
    $make_term = get_term_by('slug', $landing['make_slug'], 'car_make');
    if ($make_term && !is_wp_error($make_term)) {
        $child_ids = get_terms(array(
            'taxonomy'   => 'car_make',
            'parent'     => $make_term->term_id,
            'hide_empty' => false,
            'fields'     => 'ids',
        ));
        $tax_terms = (!is_wp_error($child_ids) && !empty($child_ids))
            ? $child_ids
            : array($make_term->term_id);
    }
}

$query_args = array(
    'post_type'      => 'car',
    'post_status'    => 'publish',
    'posts_per_page' => 24,
    'paged'          => 1,
    'orderby'        => 'date',
    'order'          => 'DESC',
    'meta_query'     => array(
        'relation' => 'OR',
        array(
            'key'     => 'is_sold',
            'compare' => 'NOT EXISTS',
        ),
        array(
            'key'     => 'is_sold',
            'value'   => '1',
            'compare' => '!=',
        ),
    ),
);
if (!empty($tax_terms)) {
    $query_args['tax_query'] = array(
        array(
            'taxonomy'         => 'car_make',
            'field'            => 'term_id',
            'terms'            => $tax_terms,
            'include_children' => false,
        ),
    );
}

$cars_query = car_listings_execute_query($query_args);

// Enqueue car card assets before get_header() so CSS lands in <head>.
if (function_exists('car_card_enqueue_assets')) {
    car_card_enqueue_assets();
}

get_header();
?>

<!-- Filters bar -->
<div class="tcp-filters-bar">
    <div class="tcp-filters-bar-inner">
        <button type="button" class="tcp-filters-btn" id="tcp-filters-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="4" y1="6" x2="20" y2="6"/><line x1="8" y1="12" x2="20" y2="12"/><line x1="12" y1="18" x2="20" y2="18"/><circle cx="4" cy="12" r="1" fill="currentColor" stroke="none"/><circle cx="8" cy="18" r="1" fill="currentColor" stroke="none"/></svg>
            Filters
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
            <?php
            echo do_shortcode(sprintf(
                '[car_filters filters="make,model,price,mileage,fuel,body,year" mode="ajax" target="%1$s" layout="vertical" show_button="false" group="%2$s" landing_make_slug="%3$s" landing_model_slug="%4$s" results_base_url="/cars/"]',
                esc_attr($listings_id),
                esc_attr($group),
                esc_attr($landing['make_slug']),
                esc_attr($landing['model_slug'])
            ));
            ?>
        </div>
        <div class="tcp-filters-modal-footer">
            <button type="button" class="tcp-modal-apply-btn" id="tcp-modal-apply-btn">Apply Filters</button>
            <button type="button" class="tcp-modal-clear-btn" id="tcp-modal-clear-btn">Clear All</button>
        </div>
    </div>
</div>

<!-- Main content -->
<div class="tcp-main">
    <h1 class="tcp-heading"><?php echo esc_html($landing['h1']); ?></h1>

    <div class="car-listings-container"
         id="<?php echo esc_attr($listings_id); ?>"
         data-atts="<?php echo esc_attr(wp_json_encode($listing_atts)); ?>"
         data-page="1"
         data-max-pages="<?php echo esc_attr($cars_query->max_num_pages); ?>">

        <div class="car-listings-wrapper tcp-grid">
            <?php
            if ($cars_query->have_posts()) :
                $post_ids = wp_list_pluck($cars_query->posts, 'ID');
                update_postmeta_cache($post_ids);

                while ($cars_query->have_posts()) : $cars_query->the_post();
                    render_car_card(get_the_ID());
                endwhile;
            else :
                ?>
                <p class="car-listings-no-results"><?php esc_html_e('No listings found for this model.', 'bricks-child'); ?></p>
                <?php
            endif;
            wp_reset_postdata();
            ?>
        </div>

        <div class="tcp-pagination">
            <?php
            if ($cars_query->max_num_pages > 1) {
                echo paginate_links(array(
                    'total'     => $cars_query->max_num_pages,
                    'current'   => 1,
                    'prev_text' => 'Previous',
                    'next_text' => 'Next',
                    'type'      => 'list',
                    'base'      => '#%#%',
                    'format'    => '%#%',
                ));
            }
            ?>
        </div>
    </div>

    <!-- SEO Content: Intro + FAQ -->
    <div class="cars-seo-content">

        <section class="cars-intro">
            <h2 class="cars-intro-heading">Buying insights for Cyprus shoppers</h2>
            <?php foreach ($landing['intro'] as $paragraph) : ?>
                <p><?php echo esc_html($paragraph); ?></p>
            <?php endforeach; ?>
        </section>

        <section class="cars-faq">
            <h2 class="cars-faq-heading">Common questions about the <?php echo esc_html($landing['model_name']); ?></h2>

            <?php foreach ($landing['faqs'] as $faq) : ?>
            <div class="faq-item">
                <button class="faq-trigger" aria-expanded="false">
                    <?php echo esc_html($faq['question']); ?>
                    <svg class="faq-chevron" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
                <div class="faq-answer">
                    <p><?php echo esc_html($faq['answer']); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </section>

    </div><!-- .cars-seo-content -->
</div>

<style>
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

@media (max-width: 768px) {
    #tcp-sort-label {
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
.tcp-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    gap: 1.5rem;
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
</style>

<script>
(function($) {
    'use strict';

    var $container  = $('#<?php echo esc_js($listings_id); ?>');
    var $wrapper    = $container.find('.car-listings-wrapper');
    var $pagination = $container.find('.tcp-pagination');
    var $overlay    = $('#tcp-filters-modal-overlay');
    var $chips      = $('#tcp-active-filters');
    var group       = '<?php echo esc_js($group); ?>';
    var MIN_ROWS     = 4;
    var MIN_PER_PAGE = 12;
    var CARD_MIN_W   = 280;
    var GRID_GAP     = 24;

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

    var filterLabels = {
        price_min:   'Price min',
        price_max:   'Price max',
        mileage_min: 'Mileage min',
        mileage_max: 'Mileage max',
        year_min:    'Year min',
        year_max:    'Year max',
        fuel_type:   'Fuel',
        body_type:   'Body'
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
    $(document).on('keydown', function(e) {
        if (e.key === 'Escape' && $overlay.hasClass('open')) closeModal();
    });

    /* ── Active filter chips (make/model excluded — locked on landing pages) ── */
    function buildChips() {
        if (!window.CarFilters) return;
        var state = CarFilters.getState(group);
        var html = '';
        var hasAny = false;

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
        ['fuel_type', 'body_type'].forEach(function(key) {
            if (state[key]) {
                html += chip(key, filterLabels[key] + ': ' + state[key]);
                hasAny = true;
            }
        });

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

    // Remove single filter chip
    $chips.on('click', '.tcp-chip-remove', function(e) {
        e.stopPropagation();
        var key = $(this).data('filter');
        clearFilter(key);
        CarFilters.triggerFilter(group);
    });

    // Clear all (non-make/model filters only)
    $chips.on('click', '#tcp-clear-all', function() {
        ['price_min', 'price_max', 'mileage_min', 'mileage_max',
         'year_min', 'year_max', 'fuel_type', 'body_type'].forEach(function(key) {
            clearFilter(key);
        });
        CarFilters.triggerFilter(group);
    });

    function clearFilter(key) {
        if (key.match(/_(min|max)$/)) {
            CarFilters.setState(group, key, '');
            var parts = key.split('_');
            var bound = parts.pop();
            var field = parts.join('_');
            var filterCls = field === 'fuel_type' ? 'fuel' : (field === 'body_type' ? 'body' : field);
            $('.car-filter-' + filterCls + ' .car-filter-input-' + bound).val('');
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

    /* ── Modal apply / clear buttons ── */
    $('#tcp-modal-apply-btn').on('click', function() {
        CarFilters.triggerFilter(group);
    });
    $('#tcp-modal-clear-btn').on('click', function() {
        ['price_min', 'price_max', 'mileage_min', 'mileage_max',
         'year_min', 'year_max', 'fuel_type', 'body_type'].forEach(function(key) {
            clearFilter(key);
        });
        CarFilters.triggerFilter(group);
    });

    // Rebuild chips whenever filters update
    $(document).on('carFilters:updated', function(e, g, data) {
        if (g !== group) return;
        if (data.pagination_html !== undefined) {
            $pagination.html(data.pagination_html || '');
        }
        $container.data('page', data.current_page || 1);
        $container.data('max-pages', data.max_pages || 1);
        buildChips();
        closeModal();
    });

    if (window.CarFilters) {
        CarFilters.subscribe(group, function() {
            buildChips();
        });
    }

    /* ── Sort dropdown ── */
    var $sort     = $('#tcp-sort');
    var $sortBtn  = $('#tcp-sort-btn');
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
        var $opt   = $(this);
        var orderby = $opt.data('orderby');
        var order   = $opt.data('order');

        $sort.find('.tcp-sort-option').removeClass('selected');
        $opt.addClass('selected');
        $sortLabel.text($opt.text());
        $sort.removeClass('open');

        var atts = $container.data('atts') || {};
        atts.orderby = orderby;
        atts.order   = order;
        $container.data('atts', atts);
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
                action:       'car_filters_filter_listings',
                nonce:        carFiltersConfig.nonce,
                page:         page,
                listing_atts: JSON.stringify(listingAtts)
            }, filterData),
            success: function(response) {
                if (response.success) {
                    $wrapper.html(response.data.html);
                    $pagination.html(response.data.pagination_html || '');
                    $container.data('page', response.data.current_page);
                    $container.data('max-pages', response.data.max_pages);
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

    function hideOrphans() {
        var gridW = $wrapper.width();
        if (!gridW) return;
        var cols = Math.max(1, Math.floor((gridW + GRID_GAP) / (CARD_MIN_W + GRID_GAP)));
        var $cards = $wrapper.children('.car-card');
        var total = $cards.length;
        var overflow = total % cols;
        $cards.show();
        if (overflow > 0) {
            $cards.slice(total - overflow).hide();
        }
    }
    $(document).ready(function() {
        hideOrphans();
    });

    $(document).on('ajaxSend', function(e, xhr, settings) {
        if (settings.data && typeof settings.data === 'string' &&
            settings.data.indexOf('action=car_filters_filter_listings') !== -1) {
            syncPostsPerPage();
            var atts = $container.data('atts') || {};
            settings.data = settings.data.replace(
                /listing_atts=[^&]*/,
                'listing_atts=' + encodeURIComponent(JSON.stringify(atts))
            );
        }
    });

    $(document).ready(function() {
        setTimeout(buildChips, 100);
    });

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
        document.querySelectorAll('.faq-item.open').forEach(function(el) {
            el.classList.remove('open');
            el.querySelector('.faq-trigger').setAttribute('aria-expanded', 'false');
        });
        if (!isOpen) {
            item.classList.add('open');
            this.setAttribute('aria-expanded', 'true');
        }
    });
});
</script>

<?php get_footer(); ?>
