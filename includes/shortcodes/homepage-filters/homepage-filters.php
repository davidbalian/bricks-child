<?php
/**
 * Homepage Filters Shortcode
 * Displays car search filters with hierarchical make/model selects and price/mileage sliders
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Register the shortcode
 */
add_shortcode('homepage_filters', 'homepage_filters_shortcode');

/**
 * Main shortcode function
 */
function homepage_filters_shortcode($atts) {
    // Enqueue assets
    homepage_filters_enqueue_assets();
    
    // Get car makes (parent terms)
    $makes = get_terms(array(
        'taxonomy' => 'car_make',
        'parent' => 0,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    // Get initial min/max values for price and mileage
    $ranges = homepage_filters_get_ranges();
    
    ob_start();
    ?>
    <div class="homepage-filters-container">
        <div class="homepage-filters-row homepage-filters-selects">
            <div class="homepage-filters-select-wrapper">
                <label for="homepage-filter-make">Make</label>
                <div class="homepage-filters-dropdown" data-filter="make">
                    <button type="button" class="homepage-filters-dropdown-button" id="homepage-filter-make-button" aria-haspopup="listbox" aria-expanded="false">
                        <span class="homepage-filters-dropdown-text placeholder">Select Make</span>
                        <span class="homepage-filters-dropdown-arrow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </span>
                    </button>
                    <div class="homepage-filters-dropdown-menu" id="homepage-filter-make-menu" role="listbox">
                        <input type="text" class="homepage-filters-search" placeholder="Search makes..." id="homepage-filter-make-search">
                        <div class="homepage-filters-dropdown-options" id="homepage-filter-make-options">
                            <?php if (!is_wp_error($makes) && !empty($makes)) : ?>
                                <?php foreach ($makes as $make) : ?>
                                    <button type="button" class="homepage-filters-dropdown-option" role="option" data-value="<?php echo esc_attr($make->term_id); ?>" data-slug="<?php echo esc_attr($make->slug); ?>">
                                        <?php echo esc_html($make->name); ?>
                                    </button>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </div>
                    </div>
                    <select id="homepage-filter-make" class="homepage-filters-select-hidden" data-filter="make" aria-hidden="true" tabindex="-1">
                        <option value="">Select Make</option>
                        <?php if (!is_wp_error($makes) && !empty($makes)) : ?>
                            <?php foreach ($makes as $make) : ?>
                                <option value="<?php echo esc_attr($make->term_id); ?>" data-slug="<?php echo esc_attr($make->slug); ?>">
                                    <?php echo esc_html($make->name); ?>
                                </option>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </select>
                </div>
            </div>
            
            <div class="homepage-filters-select-wrapper">
                <label for="homepage-filter-model">Model</label>
                <div class="homepage-filters-dropdown" data-filter="model">
                    <button type="button" class="homepage-filters-dropdown-button homepage-filters-dropdown-button-disabled" id="homepage-filter-model-button" aria-haspopup="listbox" aria-expanded="false" disabled>
                        <span class="homepage-filters-dropdown-text placeholder">Select Model</span>
                        <span class="homepage-filters-dropdown-arrow">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M19 9l-7 7-7-7"></path>
                            </svg>
                        </span>
                    </button>
                    <div class="homepage-filters-dropdown-menu" id="homepage-filter-model-menu" role="listbox">
                        <input type="text" class="homepage-filters-search" placeholder="Search models..." id="homepage-filter-model-search" disabled>
                        <div class="homepage-filters-dropdown-options" id="homepage-filter-model-options">
                            <!-- Options loaded dynamically -->
                        </div>
                    </div>
                    <select id="homepage-filter-model" class="homepage-filters-select-hidden" data-filter="model" aria-hidden="true" tabindex="-1" disabled>
                        <option value="">Select Model</option>
                    </select>
                </div>
            </div>
        </div>
        
        <div class="homepage-filters-row homepage-filters-price">
            <label for="homepage-filter-price-slider">Price</label>
            <div class="homepage-filters-slider-container">
                <div id="homepage-filter-price-slider" class="homepage-filters-slider"></div>
                <div class="homepage-filters-slider-inputs">
                    <div class="homepage-filters-input-wrapper">
                        <label for="homepage-filter-price-min" class="homepage-filters-input-label">From (€)</label>
                        <input type="text" id="homepage-filter-price-min" class="homepage-filters-input" placeholder="Min">
                    </div>
                    <div class="homepage-filters-input-wrapper">
                        <label for="homepage-filter-price-max" class="homepage-filters-input-label">To (€)</label>
                        <input type="text" id="homepage-filter-price-max" class="homepage-filters-input" placeholder="Max">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="homepage-filters-row homepage-filters-mileage">
            <label for="homepage-filter-mileage-slider">Mileage</label>
            <div class="homepage-filters-slider-container">
                <div id="homepage-filter-mileage-slider" class="homepage-filters-slider"></div>
                <div class="homepage-filters-slider-inputs">
                    <div class="homepage-filters-input-wrapper">
                        <label for="homepage-filter-mileage-min" class="homepage-filters-input-label">From (km)</label>
                        <input type="text" id="homepage-filter-mileage-min" class="homepage-filters-input" placeholder="Min">
                    </div>
                    <div class="homepage-filters-input-wrapper">
                        <label for="homepage-filter-mileage-max" class="homepage-filters-input-label">To (km)</label>
                        <input type="text" id="homepage-filter-mileage-max" class="homepage-filters-input" placeholder="Max">
                    </div>
                </div>
            </div>
        </div>
        
        <div class="homepage-filters-row homepage-filters-button">
            <button type="button" id="homepage-filters-search-btn" class="homepage-filters-search-btn">
                Search Cars
            </button>
        </div>
    </div>
    
    <script type="application/json" id="homepage-filters-data">
    {
        "ranges": {
            "price": {
                "min": <?php echo esc_js($ranges['price']['min']); ?>,
                "max": <?php echo esc_js($ranges['price']['max']); ?>
            },
            "mileage": {
                "min": <?php echo esc_js($ranges['mileage']['min']); ?>,
                "max": <?php echo esc_js($ranges['mileage']['max']); ?>
            }
        },
        "ajaxUrl": "<?php echo esc_url(admin_url('admin-ajax.php')); ?>",
        "nonce": "<?php echo esc_js(wp_create_nonce('homepage_filters_nonce')); ?>",
        "baseUrl": "<?php echo esc_url(home_url('/cars/filter/')); ?>"
    }
    </script>
    <?php
    return ob_get_clean();
}

/**
 * Get min/max ranges for price and mileage
 * Optionally filtered by make/model taxonomy terms
 * 
 * @param array $term_ids Optional array of taxonomy term IDs to filter by
 * @return array Array with 'price' and 'mileage' keys, each containing 'min' and 'max'
 */
function homepage_filters_get_ranges($term_ids = array()) {
    global $wpdb;
    
    // Build query to get min/max values
    $where_clauses = array(
        "p.post_type = 'car'",
        "p.post_status = 'publish'"
    );
    
    $join_clauses = array();
    
    // If taxonomy filter is provided, join term relationships
    if (!empty($term_ids) && is_array($term_ids)) {
        $term_ids = array_map('intval', $term_ids);
        $term_ids_placeholders = implode(',', array_fill(0, count($term_ids), '%d'));
        
        $join_clauses[] = "INNER JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id";
        $where_clauses[] = $wpdb->prepare("tr.term_taxonomy_id IN ($term_ids_placeholders)", $term_ids);
    }
    
    $join_sql = !empty($join_clauses) ? implode(' ', $join_clauses) : '';
    $where_sql = implode(' AND ', $where_clauses);
    
    // Get price min/max
    $price_query = "
        SELECT 
            MIN(CAST(pm_price.meta_value AS UNSIGNED)) as min_price,
            MAX(CAST(pm_price.meta_value AS UNSIGNED)) as max_price
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_price ON p.ID = pm_price.post_id AND pm_price.meta_key = 'price'
        $join_sql
        WHERE $where_sql
        AND pm_price.meta_value != ''
        AND pm_price.meta_value IS NOT NULL
    ";
    
    $price_result = $wpdb->get_row($price_query);
    
    // Get mileage min/max
    $mileage_query = "
        SELECT 
            MIN(CAST(pm_mileage.meta_value AS UNSIGNED)) as min_mileage,
            MAX(CAST(pm_mileage.meta_value AS UNSIGNED)) as max_mileage
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->postmeta} pm_mileage ON p.ID = pm_mileage.post_id AND pm_mileage.meta_key = 'mileage'
        $join_sql
        WHERE $where_sql
        AND pm_mileage.meta_value != ''
        AND pm_mileage.meta_value IS NOT NULL
    ";
    
    $mileage_result = $wpdb->get_row($mileage_query);
    
    // Set defaults if no results
    $price_min = isset($price_result->min_price) && $price_result->min_price !== null ? (int)$price_result->min_price : 0;
    $price_max = isset($price_result->max_price) && $price_result->max_price !== null ? (int)$price_result->max_price : 1000000;
    $mileage_min = isset($mileage_result->min_mileage) && $mileage_result->min_mileage !== null ? (int)$mileage_result->min_mileage : 0;
    $mileage_max = isset($mileage_result->max_mileage) && $mileage_result->max_mileage !== null ? (int)$mileage_result->max_mileage : 500000;
    
    return array(
        'price' => array(
            'min' => $price_min,
            'max' => $price_max
        ),
        'mileage' => array(
            'min' => $mileage_min,
            'max' => $mileage_max
        )
    );
}

/**
 * AJAX handler to get updated ranges based on make/model selection
 */
function homepage_filters_ajax_get_ranges() {
    check_ajax_referer('homepage_filters_nonce', 'nonce');
    
    $make_term_id = isset($_POST['make_term_id']) ? intval($_POST['make_term_id']) : 0;
    $model_term_id = isset($_POST['model_term_id']) ? intval($_POST['model_term_id']) : 0;
    
    $term_ids = array();
    
    if ($make_term_id > 0) {
        $term_ids[] = $make_term_id;
    }
    
    if ($model_term_id > 0) {
        $term_ids[] = $model_term_id;
    }
    
    $ranges = homepage_filters_get_ranges($term_ids);
    
    wp_send_json_success($ranges);
}
add_action('wp_ajax_homepage_filters_get_ranges', 'homepage_filters_ajax_get_ranges');
add_action('wp_ajax_nopriv_homepage_filters_get_ranges', 'homepage_filters_ajax_get_ranges');

/**
 * AJAX handler to get models for a selected make
 */
function homepage_filters_ajax_get_models() {
    check_ajax_referer('homepage_filters_nonce', 'nonce');
    
    $make_term_id = isset($_POST['make_term_id']) ? intval($_POST['make_term_id']) : 0;
    
    if ($make_term_id <= 0) {
        wp_send_json_error(array('message' => 'Invalid make term ID'));
        return;
    }
    
    $models = get_terms(array(
        'taxonomy' => 'car_make',
        'parent' => $make_term_id,
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (is_wp_error($models)) {
        wp_send_json_error(array('message' => $models->get_error_message()));
        return;
    }
    
    $model_data = array();
    foreach ($models as $model) {
        $model_data[] = array(
            'term_id' => $model->term_id,
            'name' => $model->name,
            'slug' => $model->slug
        );
    }
    
    wp_send_json_success($model_data);
}
add_action('wp_ajax_homepage_filters_get_models', 'homepage_filters_ajax_get_models');
add_action('wp_ajax_nopriv_homepage_filters_get_models', 'homepage_filters_ajax_get_models');

/**
 * Enqueue CSS and JS assets
 */
function homepage_filters_enqueue_assets() {
    // Only enqueue if not already enqueued
    static $enqueued = false;
    if ($enqueued) {
        return;
    }
    $enqueued = true;
    
    // Enqueue noUiSlider CSS
    wp_enqueue_style(
        'nouislider-css',
        'https://cdn.jsdelivr.net/npm/nouislider@15.7.1/dist/nouislider.min.css',
        array(),
        '15.7.1'
    );
    
    // Enqueue custom CSS
    wp_enqueue_style(
        'homepage-filters-css',
        get_stylesheet_directory_uri() . '/includes/shortcodes/homepage-filters/homepage-filters.css',
        array('nouislider-css'),
        filemtime(get_stylesheet_directory() . '/includes/shortcodes/homepage-filters/homepage-filters.css')
    );
    
    // Enqueue noUiSlider JS
    wp_enqueue_script(
        'nouislider-js',
        'https://cdn.jsdelivr.net/npm/nouislider@15.7.1/dist/nouislider.min.js',
        array(),
        '15.7.1',
        true
    );
    
    // Enqueue custom JS
    wp_enqueue_script(
        'homepage-filters-js',
        get_stylesheet_directory_uri() . '/includes/shortcodes/homepage-filters/homepage-filters.js',
        array('jquery', 'nouislider-js'),
        filemtime(get_stylesheet_directory() . '/includes/shortcodes/homepage-filters/homepage-filters.js'),
        true
    );
}

