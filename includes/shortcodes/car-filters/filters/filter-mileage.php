<?php
/**
 * Car Filter - Mileage
 * Min/max range inputs for mileage filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('car_filter_mileage', 'car_filter_mileage_shortcode');

/**
 * Mileage filter shortcode
 *
 * Usage: [car_filter_mileage group="main" label="Mileage" unit="km"]
 */
function car_filter_mileage_shortcode($atts) {
    $atts = shortcode_atts(array_merge(
        car_filter_get_default_atts(),
        array(
            'label'           => 'Mileage',
            'min_placeholder' => 'From',
            'max_placeholder' => 'To',
            'unit'            => 'km',
        )
    ), $atts, 'car_filter_mileage');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Get mileage range from database
    $range = car_filter_get_meta_range('mileage');

    // Check URL parameters
    $min_value = isset($_GET['mileage_min']) ? intval($_GET['mileage_min']) : '';
    $max_value = isset($_GET['mileage_max']) ? intval($_GET['mileage_max']) : '';

    $instance_id = car_filter_generate_id('mileage');

    ob_start();
    ?>
    <div class="car-filter car-filter-mileage"
         data-filter-type="mileage"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">

        <?php if (!empty($atts['label'])) : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>

        <?php
        car_filter_render_range(array(
            'id'              => $instance_id,
            'name'            => 'mileage',
            'min_placeholder' => $atts['min_placeholder'],
            'max_placeholder' => $atts['max_placeholder'],
            'unit'            => $atts['unit'],
            'min_value'       => $min_value,
            'max_value'       => $max_value,
            'abs_min'         => $range['min'],
            'abs_max'         => $range['max'],
            'data_attrs'      => array(
                'filter-type' => 'mileage',
                'group'       => $atts['group'],
            ),
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}
