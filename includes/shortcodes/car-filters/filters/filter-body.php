<?php
/**
 * Car Filter - Body Type
 * Dropdown filter for body types
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('car_filter_body', 'car_filter_body_shortcode');

/**
 * Body type filter shortcode
 *
 * Usage: [car_filter_body group="main" placeholder="All Body Types" show_count="true"]
 */
function car_filter_body_shortcode($atts) {
    $atts = shortcode_atts(array_merge(
        car_filter_get_default_atts(),
        array(
            'placeholder' => 'All Body Types',
            'show_count'  => 'true',
            'label'       => 'Body Type',
        )
    ), $atts, 'car_filter_body');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Get body type options from database
    $body_types = car_filter_get_meta_options('body_type');

    // Format options
    $options = array();
    foreach ($body_types as $body) {
        $options[] = array(
            'value' => $body->meta_value,
            'label' => ucfirst($body->meta_value),
            'slug'  => sanitize_title($body->meta_value),
            'count' => $body->count,
        );
    }

    // Check URL parameter
    $selected = '';
    if (isset($_GET['body_type'])) {
        $body_param = sanitize_text_field($_GET['body_type']);
        foreach ($options as $opt) {
            if ($opt['value'] === $body_param || $opt['slug'] === $body_param) {
                $selected = $opt['value'];
                break;
            }
        }
    }

    $instance_id = car_filter_generate_id('body');

    ob_start();
    ?>
    <div class="car-filter car-filter-body"
         data-filter-type="body_type"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">

        <?php if (!empty($atts['label'])) : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>

        <?php
        car_filter_render_dropdown(array(
            'id'          => $instance_id,
            'name'        => 'body_type',
            'placeholder' => $atts['placeholder'],
            'options'     => $options,
            'selected'    => $selected,
            'show_count'  => $atts['show_count'] === 'true',
            'searchable'  => false,
            'data_attrs'  => array(
                'filter-type' => 'body_type',
                'group'       => $atts['group'],
            ),
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}
