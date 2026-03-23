<?php
/**
 * Car Filter - Make
 * Dropdown filter for car makes (brands)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('car_filter_make', 'car_filter_make_shortcode');

/**
 * Make filter shortcode
 *
 * Usage: [car_filter_make group="main" placeholder="All Brands" show_count="true" show_popular="true"]
 */
function car_filter_make_shortcode($atts) {
    $atts = shortcode_atts(array_merge(
        car_filter_get_default_atts(),
        array(
            'placeholder'  => 'All Brands',
            'show_count'   => 'true',
            'show_popular' => 'true',
            'label'        => 'Brand',
        )
    ), $atts, 'car_filter_make');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Get makes
    $makes = car_filter_get_makes();

    // Format options
    $options = array();
    foreach ($makes as $make) {
        $options[] = array(
            'value' => $make->term_id,
            'label' => $make->name,
            'slug'  => $make->slug,
            'count' => $make->car_count,
        );
    }

    // Get popular makes (top 3 by count)
    $popular = array();
    if ($atts['show_popular'] === 'true' && !empty($options)) {
        $sorted = $options;
        usort($sorted, function($a, $b) {
            return $b['count'] - $a['count'];
        });
        $popular = array_slice($sorted, 0, 3);
    }

    $request_context = function_exists('autoagora_get_active_car_filter_context')
        ? autoagora_get_active_car_filter_context()
        : array();

    // Check for active request filter first, then landing defaults
    $selected = '';
    $make_param = '';

    if (!empty($request_context['make_slug'])) {
        $make_param = $request_context['make_slug'];
    } elseif (!empty($atts['landing_make_slug'])) {
        $make_param = sanitize_title($atts['landing_make_slug']);
    } elseif (isset($_GET['make'])) {
        $make_param = sanitize_text_field(wp_unslash($_GET['make']));
    }

    if ($make_param !== '') {
        foreach ($options as $opt) {
            if ($opt['slug'] === $make_param || (string)$opt['value'] === $make_param) {
                $selected = $opt['value'];
                break;
            }
        }
    }

    $instance_id = car_filter_generate_id('make');

    ob_start();
    ?>
    <div class="car-filter car-filter-make"
         data-filter-type="make"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">

        <?php if (!empty($atts['label'])) : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>

        <?php
        car_filter_render_dropdown(array(
            'id'          => $instance_id,
            'name'        => 'make',
            'placeholder' => $atts['placeholder'],
            'options'     => $options,
            'popular'     => $popular,
            'selected'    => $selected,
            'show_count'  => $atts['show_count'] === 'true',
            'searchable'  => true,
            'data_attrs'  => array(
                'filter-type' => 'make',
                'group'       => $atts['group'],
            ),
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}
