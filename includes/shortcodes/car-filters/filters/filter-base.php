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
        'results_base_url' => '/cars/',
        'landing_make_slug' => '',
        'landing_model_slug' => '',
        'city_landing'      => 'false',
        'default_car_city'  => '',
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
        'multiselect' => false,
        'disabled'    => false,
        'show_count'  => true,
        'searchable'  => true,
        'data_attrs'  => array(),
    );
    $args = wp_parse_args($args, $defaults);

    // For multiselect, selected can be an array
    $selected_values = array();
    if ($args['multiselect'] && is_array($args['selected'])) {
        $selected_values = $args['selected'];
    } elseif (!empty($args['selected'])) {
        $selected_values = array((string)$args['selected']);
    }

    $disabled_class = $args['disabled'] ? ' car-filter-dropdown-disabled' : '';
    $disabled_attr = $args['disabled'] ? ' disabled' : '';

    // Build data attributes string
    $data_str = '';
    if ($args['multiselect']) {
        $data_str .= ' data-multiselect="true"';
    }
    foreach ($args['data_attrs'] as $key => $value) {
        $data_str .= ' data-' . esc_attr($key) . '="' . esc_attr($value) . '"';
    }

    // Find the selected option's label for display
    $selected_label = $args['placeholder'];
    $has_selection = false;
    if (!empty($selected_values)) {
        if (count($selected_values) === 1) {
            $single_val = $selected_values[0];
            // Check popular options first
            if (!empty($args['popular'])) {
                foreach ($args['popular'] as $option) {
                    if ((string)$option['value'] === $single_val) {
                        $selected_label = $option['label'];
                        $has_selection = true;
                        break;
                    }
                }
            }
            // Check regular options if not found in popular
            if (!$has_selection) {
                foreach ($args['options'] as $option) {
                    if ((string)$option['value'] === $single_val) {
                        $selected_label = $option['label'];
                        $has_selection = true;
                        break;
                    }
                }
            }
        } else {
            $selected_label = count($selected_values) . ' selected';
            $has_selection = true;
        }
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
            <span class="car-filter-dropdown-text<?php echo $has_selection ? '' : ' placeholder'; ?>"><?php echo esc_html($selected_label); ?></span>
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
                        class="car-filter-dropdown-option<?php echo empty($selected_values) ? ' selected' : ''; ?>"
                        role="option"
                        data-value=""
                        data-slug="">
                    <?php echo esc_html($args['placeholder']); ?>
                </button>

                <?php
                // Build list of popular values to exclude from main list
                $popular_values = array();
                if (!empty($args['popular'])) :
                    foreach ($args['popular'] as $pop) {
                        $popular_values[] = (string)$pop['value'];
                    }
                ?>
                    <div class="car-filter-section-header">Most Popular</div>
                    <?php foreach ($args['popular'] as $option) :
                        $is_selected = in_array((string)$option['value'], $selected_values, true);
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
                    // Skip options already shown in popular section
                    if (in_array((string)$option['value'], $popular_values, true)) {
                        continue;
                    }
                    $is_selected = in_array((string)$option['value'], $selected_values, true);
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
                        <?php if (in_array((string)$option['value'], $selected_values, true)) echo 'selected'; ?>>
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
    $transient_key = 'car_filter_makes_v1';
    $makes = wp_cache_get($cache_key, 'car_filters');
    if (false === $makes) {
        $makes = get_transient($transient_key);
    }

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
        wp_cache_set($cache_key, $makes, 'car_filters', 600);
        set_transient($transient_key, $makes, 600);
    }

    return $makes ? $makes : array();
}

/**
 * Invalidate cached make counts when car or taxonomy data changes.
 */
function car_filter_invalidate_makes_cache() {
    wp_cache_delete('car_filter_makes', 'car_filters');
    delete_transient('car_filter_makes_v1');
}
add_action('save_post_car', 'car_filter_invalidate_makes_cache');
add_action('before_delete_post', function($post_id) {
    if (get_post_type($post_id) === 'car') {
        car_filter_invalidate_makes_cache();
    }
});
add_action('set_object_terms', function($object_id, $terms, $tt_ids, $taxonomy) {
    if ($taxonomy === 'car_make' && get_post_type($object_id) === 'car') {
        car_filter_invalidate_makes_cache();
    }
}, 10, 4);

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
 * Get distinct meta values with counts (sorted by count descending)
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
            ORDER BY count DESC, pm.meta_value ASC
        ", $meta_key);

        $options = $wpdb->get_results($query);
        wp_cache_set($cache_key, $options, '', 300);
    }

    return $options ? $options : array();
}

/**
 * Build an array of post IDs matching current filters, excluding specified keys.
 * Used by cascading dropdowns to find what options remain valid.
 *
 * @param array $filters All current filter values
 * @param array $exclude_keys Filter keys to skip (the "exclude-self" pattern)
 * @return array Post IDs
 */
function car_filter_build_constrained_post_ids($filters, $exclude_keys = array()) {
    $args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'no_found_rows'  => true,
        'fields'         => 'ids',
    );

    $meta_query = array('relation' => 'AND');
    $tax_query = array();

    // Make/model taxonomy
    if (!in_array('model', $exclude_keys) && !empty($filters['model'])) {
        $tax_query[] = array(
            'taxonomy' => 'car_make',
            'field'    => 'term_id',
            'terms'    => intval($filters['model']),
        );
    } elseif (!in_array('make', $exclude_keys) && !empty($filters['make'])) {
        $models = car_filter_get_models(intval($filters['make']));
        if (!empty($models)) {
            $model_ids = wp_list_pluck($models, 'term_id');
            $tax_query[] = array(
                'taxonomy' => 'car_make',
                'field'    => 'term_id',
                'terms'    => $model_ids,
            );
        }
    }

    // Range filters: price, mileage, year
    $range_keys = array('price', 'mileage', 'year');
    foreach ($range_keys as $key) {
        if (in_array($key, $exclude_keys)) continue;

        $min = !empty($filters[$key . '_min']) ? intval($filters[$key . '_min']) : 0;
        $max = !empty($filters[$key . '_max']) ? intval($filters[$key . '_max']) : 0;

        if ($min > 0) {
            $meta_query[] = array(
                'key'     => $key,
                'value'   => $min,
                'compare' => '>=',
                'type'    => 'NUMERIC',
            );
        }
        if ($max > 0) {
            $meta_query[] = array(
                'key'     => $key,
                'value'   => $max,
                'compare' => '<=',
                'type'    => 'NUMERIC',
            );
        }
    }

    // Meta value filters: fuel_type, body_type (support comma-separated multi-values)
    $meta_value_keys = array('fuel_type', 'body_type');
    foreach ($meta_value_keys as $key) {
        if (in_array($key, $exclude_keys)) continue;

        // Check for pre-parsed array first, then fall back to comma-separated string
        $values = array();
        if (!empty($filters[$key . 's']) && is_array($filters[$key . 's'])) {
            $values = $filters[$key . 's'];
        } elseif (!empty($filters[$key])) {
            $values = array_map('trim', explode(',', sanitize_text_field($filters[$key])));
        }

        if (!empty($values)) {
            if (count($values) === 1) {
                $meta_query[] = array(
                    'key'     => $key,
                    'value'   => $values[0],
                    'compare' => '=',
                );
            } else {
                $meta_query[] = array(
                    'key'     => $key,
                    'value'   => $values,
                    'compare' => 'IN',
                );
            }
        }
    }

    $meta_query[] = ListingStateManager::meta_query_active_clause();

    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }
    if (!empty($tax_query)) {
        $args['tax_query'] = $tax_query;
    }

    $query = new WP_Query($args);
    return $query->posts;
}

/**
 * Get available options for all dropdown filters given current state.
 * Each dropdown's options are computed by excluding that dropdown's own filter
 * so it never hides its own selected value.
 *
 * @param array $filters Current filter values
 * @return array Associative array with makes, models, fuel_type, body_type
 */
function car_filter_get_available_options_data($filters) {
    global $wpdb;

    $result = array(
        'makes'     => array(),
        'models'    => null,
        'fuel_type' => array(),
        'body_type' => array(),
    );

    // --- Makes: exclude make and model from constraints ---
    $post_ids = car_filter_build_constrained_post_ids($filters, array('make', 'model'));
    if (!empty($post_ids)) {
        $ids_placeholder = implode(',', array_map('intval', $post_ids));
        $makes_sql = "
            SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT tr.object_id) as count
            FROM {$wpdb->terms} t
            INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
            INNER JOIN {$wpdb->term_taxonomy} tt_child ON tt_child.parent = t.term_id AND tt_child.taxonomy = 'car_make'
            INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt_child.term_taxonomy_id
            WHERE tt.taxonomy = 'car_make'
            AND tt.parent = 0
            AND tr.object_id IN ($ids_placeholder)
            GROUP BY t.term_id, t.name, t.slug
            ORDER BY t.name ASC
        ";
        $makes = $wpdb->get_results($makes_sql);
        if ($makes) {
            foreach ($makes as $make) {
                $result['makes'][] = array(
                    'term_id' => (int) $make->term_id,
                    'name'    => $make->name,
                    'slug'    => $make->slug,
                    'count'   => (int) $make->count,
                );
            }
        }
    }

    // --- Models: only if make is set; exclude model but keep make ---
    if (!empty($filters['make'])) {
        $model_post_ids = car_filter_build_constrained_post_ids($filters, array('model'));
        if (!empty($model_post_ids)) {
            $ids_placeholder = implode(',', array_map('intval', $model_post_ids));
            $make_term_id = intval($filters['make']);
            $models_sql = $wpdb->prepare("
                SELECT t.term_id, t.name, t.slug, COUNT(DISTINCT tr.object_id) as count
                FROM {$wpdb->terms} t
                INNER JOIN {$wpdb->term_taxonomy} tt ON t.term_id = tt.term_id
                INNER JOIN {$wpdb->term_relationships} tr ON tr.term_taxonomy_id = tt.term_taxonomy_id
                WHERE tt.taxonomy = 'car_make'
                AND tt.parent = %d
                AND tr.object_id IN ($ids_placeholder)
                GROUP BY t.term_id, t.name, t.slug
                ORDER BY t.name ASC
            ", $make_term_id);
            $models = $wpdb->get_results($models_sql);
            $result['models'] = array();
            if ($models) {
                foreach ($models as $model) {
                    $result['models'][] = array(
                        'term_id' => (int) $model->term_id,
                        'name'    => $model->name,
                        'slug'    => $model->slug,
                        'count'   => (int) $model->count,
                    );
                }
            }
        } else {
            $result['models'] = array();
        }
    }

    // --- Fuel type: exclude fuel_type from constraints ---
    $fuel_post_ids = car_filter_build_constrained_post_ids($filters, array('fuel_type'));
    if (!empty($fuel_post_ids)) {
        $ids_placeholder = implode(',', array_map('intval', $fuel_post_ids));
        $fuel_sql = "
            SELECT pm.meta_value, COUNT(DISTINCT pm.post_id) as count
            FROM {$wpdb->postmeta} pm
            WHERE pm.meta_key = 'fuel_type'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
            AND pm.post_id IN ($ids_placeholder)
            GROUP BY pm.meta_value
            ORDER BY count DESC, pm.meta_value ASC
        ";
        $fuels = $wpdb->get_results($fuel_sql);
        if ($fuels) {
            foreach ($fuels as $fuel) {
                $result['fuel_type'][] = array(
                    'value' => $fuel->meta_value,
                    'label' => ucfirst($fuel->meta_value),
                    'slug'  => sanitize_title($fuel->meta_value),
                    'count' => (int) $fuel->count,
                );
            }
        }
    }

    // --- Body type: exclude body_type from constraints ---
    $body_post_ids = car_filter_build_constrained_post_ids($filters, array('body_type'));
    if (!empty($body_post_ids)) {
        $ids_placeholder = implode(',', array_map('intval', $body_post_ids));
        $body_sql = "
            SELECT pm.meta_value, COUNT(DISTINCT pm.post_id) as count
            FROM {$wpdb->postmeta} pm
            WHERE pm.meta_key = 'body_type'
            AND pm.meta_value != ''
            AND pm.meta_value IS NOT NULL
            AND pm.post_id IN ($ids_placeholder)
            GROUP BY pm.meta_value
            ORDER BY count DESC, pm.meta_value ASC
        ";
        $bodies = $wpdb->get_results($body_sql);
        if ($bodies) {
            foreach ($bodies as $body) {
                $result['body_type'][] = array(
                    'value' => $body->meta_value,
                    'label' => ucfirst($body->meta_value),
                    'slug'  => sanitize_title($body->meta_value),
                    'count' => (int) $body->count,
                );
            }
        }
    }

    return $result;
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
