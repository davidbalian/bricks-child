<?php
/**
 * Car Filter - Year
 * Min/max range inputs for year filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('car_filter_year', 'car_filter_year_shortcode');

/**
 * Year filter shortcode
 *
 * Usage: [car_filter_year group="main" label="Year"]
 */
function car_filter_year_shortcode($atts) {
    $atts = shortcode_atts(array_merge(
        car_filter_get_default_atts(),
        array(
            'label'           => 'Year',
            'min_placeholder' => 'From',
            'max_placeholder' => 'To',
        )
    ), $atts, 'car_filter_year');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Get year range from database
    $range = car_filter_get_meta_range('year');

    // Check URL parameters
    $min_value = isset($_GET['year_min']) ? intval($_GET['year_min']) : '';
    $max_value = isset($_GET['year_max']) ? intval($_GET['year_max']) : '';

    $instance_id = car_filter_generate_id('year');

    ob_start();
    ?>
    <div class="car-filter car-filter-year"
         data-filter-type="year"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">

        <?php if (!empty($atts['label'])) : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>

        <?php
        car_filter_render_range(array(
            'id'              => $instance_id,
            'name'            => 'year',
            'min_placeholder' => $atts['min_placeholder'],
            'max_placeholder' => $atts['max_placeholder'],
            'unit'            => '',
            'min_value'       => $min_value,
            'max_value'       => $max_value,
            'abs_min'         => $range['min'],
            'abs_max'         => $range['max'],
            'data_attrs'      => array(
                'filter-type' => 'year',
                'group'       => $atts['group'],
            ),
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}
