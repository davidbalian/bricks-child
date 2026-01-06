<?php
/**
 * Car Filter - Price
 * Min/max range inputs for price filtering
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('car_filter_price', 'car_filter_price_shortcode');

/**
 * Price filter shortcode
 *
 * Usage: [car_filter_price group="main" label="Price" currency="€"]
 */
function car_filter_price_shortcode($atts) {
    $atts = shortcode_atts(array_merge(
        car_filter_get_default_atts(),
        array(
            'label'           => 'Price',
            'min_placeholder' => 'From',
            'max_placeholder' => 'To',
            'currency'        => '€',
        )
    ), $atts, 'car_filter_price');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Get price range from database
    $range = car_filter_get_meta_range('price');

    // Check URL parameters
    $min_value = isset($_GET['price_min']) ? intval($_GET['price_min']) : '';
    $max_value = isset($_GET['price_max']) ? intval($_GET['price_max']) : '';

    $instance_id = car_filter_generate_id('price');

    ob_start();
    ?>
    <div class="car-filter car-filter-price"
         data-filter-type="price"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">

        <?php if (!empty($atts['label'])) : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>

        <?php
        car_filter_render_range(array(
            'id'              => $instance_id,
            'name'            => 'price',
            'min_placeholder' => $atts['min_placeholder'],
            'max_placeholder' => $atts['max_placeholder'],
            'unit'            => $atts['currency'],
            'min_value'       => $min_value,
            'max_value'       => $max_value,
            'abs_min'         => $range['min'],
            'abs_max'         => $range['max'],
            'data_attrs'      => array(
                'filter-type' => 'price',
                'group'       => $atts['group'],
            ),
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}
