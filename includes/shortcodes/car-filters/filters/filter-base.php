<?php
/**
 * Car Filters - Base Functions
 * Shared utilities for all filter shortcodes
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Generate a unique instance ID for a filter
 */
function car_filter_generate_id($type) {
    return 'car-filter-' . $type . '-' . wp_rand(1000, 9999);
}

/**
 * Get default attributes shared by all filters
 */
function car_filter_get_default_atts() {
    return array(
        'group'       => 'default',
        'mode'        => 'ajax',      // ajax or redirect
        'target'      => '',          // target car_listings instance ID
        'redirect_url' => '',         // URL for redirect mode
    );
}

/**
 * Render a custom dropdown with search functionality
 *
 * @param array $args {
 *     @type string $id           Unique ID for the dropdown
 *     @type string $name         Name attribute
 *     @type string $placeholder  Placeholder text
 *     @type array  $options      Array of options: [['value' => '', 'label' => '', 'slug' => '', 'count' => 0]]
 *     @type array  $popular      Optional array of popular options (shown first)
 *     @type string $selected     Currently selected value
 *     @type bool   $disabled     Whether dropdown is disabled
 *     @type bool   $show_count   Whether to show counts
 *     @type bool   $searchable   Whether to show search input
 *     @type array  $data_attrs   Additional data attributes
 * }
 */
function car_filter_render_dropdown($args) {
    $defaults = array(
        'id'          => '',
        'name'        => '',
        'placeholder' => 'Select...',
        'options'     => array(),
        'popular'     => array(),
        'selected'    => '',
        'disabled'    => false,
        'show_count'  => true,
        'searchable'  => true,
        'data_attrs'  => array(),
    );
    $args = wp_parse_args($args, $defaults);

    $disabled_class = $args['disabled'] ? ' car-filter-dropdown-disabled' : '';
    $disabled_attr = $args['disabled'] ? ' disabled' : '';

    // Build data attributes string
    $data_str = '';
    foreach ($args['data_attrs'] as $key => $value) {
        $data_str .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
    }
    ?>
    <div class="car-filter-dropdown<?php echo esc_attr($disabled_class); ?>"
         id="<?php echo esc_attr($args['id']); ?>-wrapper"
         <?php echo $data_str; ?>>

        <button type="button"
                class="car-filter-dropdown-button"
                id="<?php echo esc_attr($args['id']); ?>-button"
                aria-haspopup="listbox"
                aria-expanded="false"
                <?php echo $disabled_attr; ?>>
            <span class="car-filter-dropdown-text placeholder"><?php echo esc_html($args['placeholder']); ?></span>
            <span class="car-filter-dropdown-arrow">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 9l-7 7-7-7"></path>
                </svg>
            </span>
        </button>

        <div class="car-filter-dropdown-menu" id="<?php echo esc_attr($args['id']); ?>-menu" role="listbox">
            <?php if ($args['searchable']) : ?>
                <input type="text"
                       class="car-filter-dropdown-search"
                       placeholder="Search..."
                       id="<?php echo esc_attr($args['id']); ?>-search"
                       <?php echo $disabled_attr; ?>>
            <?php endif; ?>

            <div class="car-filter-dropdown-options" id="<?php echo esc_attr($args['id']); ?>-options">
                <!-- All option -->
                <button type="button"
                        class="car-filter-dropdown-option<?php echo empty($args['selected']) ? ' selected' : ''; ?>"
                        role="option"
                        data-value=""
                        data-slug="">
                    <?php echo esc_html($args['placeholder']); ?>
                </button>

                <?php if (!empty($args['popular'])) : ?>
                    <div class="car-filter-section-header">Most Popular</div>
                    <?php foreach ($args['popular'] as $option) :
                        $is_selected = (string)$option['value'] === (string)$args['selected'];
                    ?>
                        <button type="button"
                                class="car-filter-dropdown-option<?php echo $is_selected ? ' selected' : ''; ?>"
                                role="option"
                                data-value="<?php echo esc_attr($option['value']); ?>"
                                data-slug="<?php echo esc_attr($option['slug'] ?? ''); ?>">
                            <?php echo esc_html($option['label']); ?>
                            <?php if ($args['show_count'] && isset($option['count'])) : ?>
                                <span class="car-filter-count">(<?php echo esc_html($option['count']); ?>)</span>
                            <?php endif; ?>
                        </button>
                    <?php endforeach; ?>
                    <div class="car-filter-separator"></div>
                <?php endif; ?>

                <?php foreach ($args['options'] as $option) :
                    $is_selected = (string)$option['value'] === (string)$args['selected'];
                ?>
                    <button type="button"
                            class="car-filter-dropdown-option<?php echo $is_selected ? ' selected' : ''; ?>"
                            role="option"
                            data-value="<?php echo esc_attr($option['value']); ?>"
                            data-slug="<?php echo esc_attr($option['slug'] ?? ''); ?>">
                        <?php echo esc_html($option['label']); ?>
                        <?php if ($args['show_count'] && isset($option['count'])) : ?>
                            <span class="car-filter-count">(<?php echo esc_html($option['count']); ?>)</span>
                        <?php endif; ?>
                    </button>
                <?php endforeach; ?>

                <div class="car-filter-no-results hidden">No matching results</div>
            </div>
        </div>

        <!-- Hidden select for form submission -->
        <select id="<?php echo esc_attr($args['id']); ?>"
                name="<?php echo esc_attr($args['name']); ?>"
                class="car-filter-select-hidden"
                aria-hidden="true"
                tabindex="-1"
                <?php echo $disabled_attr; ?>>
            <option value=""><?php echo esc_html($args['placeholder']); ?></option>
            <?php foreach ($args['options'] as $option) : ?>
                <option value="<?php echo esc_attr($option['value']); ?>"
                        data-slug="<?php echo esc_attr($option['slug'] ?? ''); ?>"
                        <?php selected($args['selected'], $option['value']); ?>>
                    <?php echo esc_html($option['label']); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <?php
}

/**
 * Render a range input (min/max)
 *
 * @param array $args {
 *     @type string $id            Unique ID prefix
 *     @type string $name          Name prefix for inputs
 *     @type string $label         Label text
 *     @type string $min_placeholder  Min input placeholder
 *     @type string $max_placeholder  Max input placeholder
 *     @type string $unit          Unit label (e.g., '€', 'km')
 *     @type int    $min_value     Current min value
 *     @type int    $max_value     Current max value
 *     @type int    $abs_min       Absolute minimum
 *     @type int    $abs_max       Absolute maximum
 *     @type array  $data_attrs    Additional data attributes
 * }
 */
function car_filter_render_range($args) {
    $defaults = array(
        'id'              => '',
        'name'            => '',
        'label'           => '',
        'min_placeholder' => 'Min',
        'max_placeholder' => 'Max',
        'unit'            => '',
        'min_value'       => '',
        'max_value'       => '',
        'abs_min'         => 0,
        'abs_max'         => 0,
        'data_attrs'      => array(),
    );
    $args = wp_parse_args($args, $defaults);

    // Build data attributes string
    $data_str = '';
    foreach ($args['data_attrs'] as $key => $value) {
        $data_str .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
    }

    $unit_label = $args['unit'] ? ' (' . esc_html($args['unit']) . ')' : '';
    ?>
    <div class="car-filter-range" id="<?php echo esc_attr($args['id']); ?>-wrapper" <?php echo $data_str; ?>>
        <div class="car-filter-range-inputs">
            <div class="car-filter-input-wrapper">
                <label for="<?php echo esc_attr($args['id']); ?>-min" class="car-filter-input-label">
                    <?php echo esc_html($args['min_placeholder']); ?><?php echo $unit_label; ?>
                </label>
                <input type="text"
                       id="<?php echo esc_attr($args['id']); ?>-min"
                       name="<?php echo esc_attr($args['name']); ?>_min"
                       class="car-filter-input car-filter-input-min"
                       placeholder="<?php echo esc_attr($args['min_placeholder']); ?>"
                       value="<?php echo esc_attr($args['min_value']); ?>"
                       data-abs-min="<?php echo esc_attr($args['abs_min']); ?>"
                       data-abs-max="<?php echo esc_attr($args['abs_max']); ?>"
                       inputmode="numeric">
            </div>
            <div class="car-filter-input-wrapper">
                <label for="<?php echo esc_attr($args['id']); ?>-max" class="car-filter-input-label">
                    <?php echo esc_html($args['max_placeholder']); ?><?php echo $unit_label; ?>
                </label>
                <input type="text"
                       id="<?php echo esc_attr($args['id']); ?>-max"
                       name="<?php echo esc_attr($args['name']); ?>_max"
                       class="car-filter-input car-filter-input-max"
                       placeholder="<?php echo esc_attr($args['max_placeholder']); ?>"
                       value="<?php echo esc_attr($args['max_value']); ?>"
                       data-abs-min="<?php echo esc_attr($args['abs_min']); ?>"
                       data-abs-max="<?php echo esc_attr($args['abs_max']); ?>"
                       inputmode="numeric">
            </div>
        </div>
    </div>
    <?php
}

/**
 * Get car makes (parent terms) with aggregated counts from child models
 * Cached for performance
 */
function car_filter_get_makes() {
    $cache_key = 'car_filter_makes';
    $makes = wp_cache_get($cache_key);

    if (false === $makes) {
        global $wpdb;

        $query = "
            SELECT
                t.term_id,
                t.name,
                t.slug,
                COALESCE(SUM(tt_child.count), 0) as car_count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            LEFT JOIN {$wpdb->term_taxonomy} tt_child ON tt_child.parent = t.term_id AND tt_child.taxonomy = 'car_make'
            WHERE tt.taxonomy = 'car_make'
            AND tt.parent = 0
            GROUP BY t.term_id, t.name, t.slug
            HAVING car_count > 0
            ORDER BY t.name ASC
        ";

        $makes = $wpdb->get_results($query);
        wp_cache_set($cache_key, $makes, '', 300); // Cache for 5 minutes
    }

    return $makes ? $makes : array();
}

/**
 * Get models for a specific make
 */
function car_filter_get_models($make_term_id) {
    if (!$make_term_id) {
        return array();
    }

    $models = get_terms(array(
        'taxonomy'   => 'car_make',
        'parent'     => intval($make_term_id),
        'hide_empty' => true,
        'orderby'    => 'name',
        'order'      => 'ASC'
    ));

    return is_wp_error($models) ? array() : $models;
}

/**
 * Get distinct meta values with counts
 */
function car_filter_get_meta_options($meta_key) {
    global $wpdb;

    $cache_key = 'car_filter_meta_' . $meta_key;
    $options = wp_cache_get($cache_key);

    if (false === $options) {
        $query = $wpdb->prepare("
            SELECT pm.meta_value, COUNT(*) as count
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s
            AND p.post_type = 'car'
            AND p.post_status = 'publish'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
            GROUP BY pm.meta_value
            ORDER BY pm.meta_value ASC
        ", $meta_key);

        $options = $wpdb->get_results($query);
        wp_cache_set($cache_key, $options, '', 300);
    }

    return $options ? $options : array();
}

/**
 * Get models for a specific make by slug
 */
function car_filter_get_make_by_slug($slug) {
    if (!$slug) {
        return null;
    }
    return get_term_by('slug', $slug, 'car_make');
}

/**
 * Get min/max values for a numeric meta field
 */
function car_filter_get_meta_range($meta_key) {
    global $wpdb;

    $cache_key = 'car_filter_range_' . $meta_key;
    $range = wp_cache_get($cache_key);

    if (false === $range) {
        $query = $wpdb->prepare("
            SELECT
                MIN(CAST(pm.meta_value AS UNSIGNED)) as min_val,
                MAX(CAST(pm.meta_value AS UNSIGNED)) as max_val
            FROM {$wpdb->postmeta} pm
            INNER JOIN {$wpdb->posts} p ON pm.post_id = p.ID
            WHERE pm.meta_key = %s
            AND p.post_type = 'car'
            AND p.post_status = 'publish'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
            AND pm.meta_value REGEXP '^[0-9]+$'
        ", $meta_key);

        $result = $wpdb->get_row($query);

        $range = array(
            'min' => $result && $result->min_val !== null ? (int)$result->min_val : 0,
            'max' => $result && $result->max_val !== null ? (int)$result->max_val : 0,
        );

        wp_cache_set($cache_key, $range, '', 300);
    }

    return $range;
}
