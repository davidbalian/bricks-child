<?php
/**
 * Car Filter - Model
 * Dropdown filter for car models (depends on make selection)
 */

if (!defined('ABSPATH')) {
    exit;
}

add_shortcode('car_filter_model', 'car_filter_model_shortcode');

/**
 * Model filter shortcode
 *
 * Usage: [car_filter_model group="main" placeholder="All Models" show_count="true"]
 */
function car_filter_model_shortcode($atts) {
    $atts = shortcode_atts(array_merge(
        car_filter_get_default_atts(),
        array(
            'placeholder' => 'All Models',
            'show_count'  => 'true',
            'label'       => 'Model',
        )
    ), $atts, 'car_filter_model');

    // Enqueue assets
    car_filters_enqueue_assets();

    // Model options are loaded dynamically via AJAX when make is selected
    // But if there's a make in URL, pre-populate
    $options = array();
    $selected = '';
    $disabled = true;

    if (isset($_GET['make'])) {
        $make_param = sanitize_text_field($_GET['make']);

        // Find make term
        $make_term = get_term_by('slug', $make_param, 'car_make');
        if (!$make_term) {
            $make_term = get_term_by('id', intval($make_param), 'car_make');
        }

        if ($make_term && $make_term->parent === 0) {
            $models = car_filter_get_models($make_term->term_id);
            foreach ($models as $model) {
                $options[] = array(
                    'value' => $model->term_id,
                    'label' => $model->name,
                    'slug'  => $model->slug,
                    'count' => $model->count,
                );
            }
            $disabled = false;

            // Check for selected model in URL
            if (isset($_GET['model'])) {
                $model_param = sanitize_text_field($_GET['model']);
                foreach ($options as $opt) {
                    if ($opt['slug'] === $model_param || (string)$opt['value'] === $model_param) {
                        $selected = $opt['value'];
                        break;
                    }
                }
            }
        }
    }

    $instance_id = car_filter_generate_id('model');

    ob_start();
    ?>
    <div class="car-filter car-filter-model"
         data-filter-type="model"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">

        <?php if (!empty($atts['label'])) : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>

        <?php
        car_filter_render_dropdown(array(
            'id'          => $instance_id,
            'name'        => 'model',
            'placeholder' => $atts['placeholder'],
            'options'     => $options,
            'selected'    => $selected,
            'disabled'    => $disabled,
            'show_count'  => $atts['show_count'] === 'true',
            'searchable'  => true,
            'data_attrs'  => array(
                'filter-type' => 'model',
                'group'       => $atts['group'],
            ),
        ));
        ?>
    </div>
    <?php
    return ob_get_clean();
}
