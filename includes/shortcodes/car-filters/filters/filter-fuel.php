<?php
/**
 * Car Filter - Fuel Type
 * Dropdown filter for fuel types
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('car_filter_fuel', 'car_filter_fuel_shortcode');

/**
 * Fuel type filter shortcode
 *
 * Usage: [car_filter_fuel group="main" placeholder="All Fuel Types" show_count="true"]
 */
function car_filter_fuel_shortcode($atts) {
    $atts = shortcode_atts(array_merge(
        car_filter_get_default_atts(),
        array(
            'placeholder' => 'All Fuel Types',
            'show_count'  => 'true',
            'label'       => 'Fuel Type',
        )
    ), $atts, 'car_filter_fuel');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Get fuel type options from database
    $fuel_types = car_filter_get_meta_options('fuel_type');

    // Format options
    $options = array();
    foreach ($fuel_types as $fuel) {
        $options[] = array(
            'value' => $fuel->meta_value,
            'label' => ucfirst($fuel->meta_value),
            'slug'  => sanitize_title($fuel->meta_value),
            'count' => $fuel->count,
        );
    }

    // Check URL parameter
    $selected = '';
    if (isset($_GET['fuel_type'])) {
        $fuel_param = sanitize_text_field($_GET['fuel_type']);
        foreach ($options as $opt) {
            if ($opt['value'] === $fuel_param || $opt['slug'] === $fuel_param) {
                $selected = $opt['value'];
                break;
            }
        }
    }

    $instance_id = car_filter_generate_id('fuel');

    ob_start();
    ?>
    <div class="car-filter car-filter-fuel"
         data-filter-type="fuel_type"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">

        <?php if (!empty($atts['label'])) : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>

        <?php
        car_filter_render_dropdown(array(
            'id'          => $instance_id,
            'name'        => 'fuel_type',
            'placeholder' => $atts['placeholder'],
            'options'     => $options,
            'selected'    => $selected,
            'show_count'  => $atts['show_count'] === 'true',
            'searchable'  => false,
            'data_attrs'  => array(
                'filter-type' => 'fuel_type',
                'group'       => $atts['group'],
            ),
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}
