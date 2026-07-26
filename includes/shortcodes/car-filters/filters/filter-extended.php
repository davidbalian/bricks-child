<?php
/**
 * Restored car-detail filters.
 *
 * These fields use the same meta keys and values as the add/edit listing forms.
 */

if (!defined('ABSPATH')) {
    exit;
}

function car_filter_extended_options(array $values): array
{
    $options = array();
    foreach ($values as $value => $label) {
        if (is_array($label) && isset($label['value'], $label['label'])) {
            $value = $label['value'];
            $label = $label['label'];
        } elseif (is_int($value)) {
            $value = $label;
        }
        $options[] = array(
            'value' => (string) $value,
            'label' => (string) $label,
            'slug'  => sanitize_title((string) $value),
        );
    }

    return $options;
}

function car_filter_extended_selected(string $filter_key, bool $multiselect)
{
    if (!isset($_GET[$filter_key])) {
        return $multiselect ? array() : '';
    }

    $raw = sanitize_text_field(wp_unslash($_GET[$filter_key]));
    if (!$multiselect) {
        return $raw;
    }

    return array_values(array_filter(array_map('trim', explode(',', $raw)), 'strlen'));
}

function car_filter_render_extended_dropdown(array $atts, string $shortcode, array $config): string
{
    $atts = shortcode_atts(
        array_merge(
            car_filter_get_default_atts(),
            array(
                'placeholder' => $config['placeholder'],
                'label'       => $config['label'],
            )
        ),
        $atts,
        $shortcode
    );

    car_filters_enqueue_assets();

    $multiselect = !empty($config['multiselect']);
    $instance_id = car_filter_generate_id($config['class']);
    $options = car_filter_extended_options($config['options']);
    $selected = car_filter_extended_selected($config['filter_key'], $multiselect);

    ob_start();
    ?>
    <div class="car-filter car-filter-<?php echo esc_attr($config['class']); ?>"
         data-filter-type="<?php echo esc_attr($config['filter_key']); ?>"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">
        <?php if ($atts['label'] !== '') : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>
        <?php
        car_filter_render_dropdown(
            array(
                'id'          => $instance_id,
                'name'        => $config['filter_key'],
                'placeholder' => $atts['placeholder'],
                'options'     => $options,
                'selected'    => $selected,
                'multiselect' => $multiselect,
                'show_count'  => false,
                'searchable'  => count($options) > 8,
                'data_attrs'  => array(
                    'filter-type' => $config['filter_key'],
                    'group'       => $atts['group'],
                ),
            )
        );
        ?>
    </div>
    <?php
    return ob_get_clean();
}

function car_filter_render_extended_range(array $atts, string $shortcode, array $config): string
{
    $atts = shortcode_atts(
        array_merge(
            car_filter_get_default_atts(),
            array('label' => $config['label'])
        ),
        $atts,
        $shortcode
    );

    car_filters_enqueue_assets();

    $filter_key = $config['filter_key'];
    $min_value = isset($_GET[$filter_key . '_min'])
        ? sanitize_text_field(wp_unslash($_GET[$filter_key . '_min']))
        : '';
    $max_value = isset($_GET[$filter_key . '_max'])
        ? sanitize_text_field(wp_unslash($_GET[$filter_key . '_max']))
        : '';
    $instance_id = car_filter_generate_id($config['class']);

    ob_start();
    ?>
    <div class="car-filter car-filter-<?php echo esc_attr($config['class']); ?>"
         data-filter-type="<?php echo esc_attr($filter_key); ?>"
         data-group="<?php echo esc_attr($atts['group']); ?>"
         data-mode="<?php echo esc_attr($atts['mode']); ?>">
        <?php if ($atts['label'] !== '') : ?>
            <label class="car-filter-label"><?php echo esc_html($atts['label']); ?></label>
        <?php endif; ?>
        <?php
        car_filter_render_range(
            array(
                'id'              => $instance_id,
                'name'            => $filter_key,
                'min_placeholder' => __('From', 'bricks-child'),
                'max_placeholder' => __('To', 'bricks-child'),
                'unit'            => $config['unit'],
                'min_value'       => $min_value,
                'max_value'       => $max_value,
                'inputmode'       => !empty($config['decimal']) ? 'decimal' : 'numeric',
                'data_attrs'      => array(
                    'filter-type' => $filter_key,
                    'group'       => $atts['group'],
                ),
            )
        );
        ?>
    </div>
    <?php
    return ob_get_clean();
}

function car_filter_engine_shortcode($atts)
{
    return car_filter_render_extended_range(
        (array) $atts,
        'car_filter_engine',
        array(
            'filter_key' => 'engine_capacity',
            'class'      => 'engine',
            'label'      => __('Engine size', 'bricks-child'),
            'unit'       => 'L',
            'decimal'    => true,
        )
    );
}
add_shortcode('car_filter_engine', 'car_filter_engine_shortcode');

function car_filter_hp_shortcode($atts)
{
    return car_filter_render_extended_range(
        (array) $atts,
        'car_filter_hp',
        array(
            'filter_key' => 'hp',
            'class'      => 'hp',
            'label'      => __('Horsepower', 'bricks-child'),
            'unit'       => 'hp',
        )
    );
}
add_shortcode('car_filter_hp', 'car_filter_hp_shortcode');

function car_filter_owners_shortcode($atts)
{
    return car_filter_render_extended_range(
        (array) $atts,
        'car_filter_owners',
        array(
            'filter_key' => 'numowners',
            'class'      => 'owners',
            'label'      => __('Number of owners', 'bricks-child'),
            'unit'       => '',
        )
    );
}
add_shortcode('car_filter_owners', 'car_filter_owners_shortcode');

function car_filter_transmission_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_transmission',
        array(
            'filter_key'  => 'transmission',
            'class'       => 'transmission',
            'label'       => __('Transmission', 'bricks-child'),
            'placeholder' => __('Any transmission', 'bricks-child'),
            'options'     => array('Automatic', 'Manual'),
        )
    );
}
add_shortcode('car_filter_transmission', 'car_filter_transmission_shortcode');

function car_filter_drive_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_drive',
        array(
            'filter_key'  => 'drive_type',
            'class'       => 'drive',
            'label'       => __('Drive type', 'bricks-child'),
            'placeholder' => __('Any drive type', 'bricks-child'),
            'multiselect' => true,
            'options'     => array('Front-Wheel Drive', 'Rear-Wheel Drive', 'All-Wheel Drive', '4-Wheel Drive'),
        )
    );
}
add_shortcode('car_filter_drive', 'car_filter_drive_shortcode');

function car_filter_exterior_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_exterior',
        array(
            'filter_key'  => 'exterior_color',
            'class'       => 'exterior',
            'label'       => __('Exterior colour', 'bricks-child'),
            'placeholder' => __('Any exterior colour', 'bricks-child'),
            'multiselect' => true,
            'options'     => array('Black', 'White', 'Silver', 'Gray', 'Red', 'Blue', 'Green', 'Yellow', 'Brown', 'Beige', 'Orange', 'Purple', 'Gold', 'Bronze'),
        )
    );
}
add_shortcode('car_filter_exterior', 'car_filter_exterior_shortcode');

function car_filter_interior_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_interior',
        array(
            'filter_key'  => 'interior_color',
            'class'       => 'interior',
            'label'       => __('Interior colour', 'bricks-child'),
            'placeholder' => __('Any interior colour', 'bricks-child'),
            'multiselect' => true,
            'options'     => array('Black', 'Gray', 'Beige', 'Brown', 'White', 'Red', 'Blue', 'Tan', 'Cream'),
        )
    );
}
add_shortcode('car_filter_interior', 'car_filter_interior_shortcode');

function car_filter_doors_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_doors',
        array(
            'filter_key'  => 'number_of_doors',
            'class'       => 'doors',
            'label'       => __('Number of doors', 'bricks-child'),
            'placeholder' => __('Any number of doors', 'bricks-child'),
            'options'     => array('0', '2', '3', '4', '5', '6', '7'),
        )
    );
}
add_shortcode('car_filter_doors', 'car_filter_doors_shortcode');

function car_filter_seats_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_seats',
        array(
            'filter_key'  => 'number_of_seats',
            'class'       => 'seats',
            'label'       => __('Number of seats', 'bricks-child'),
            'placeholder' => __('Any number of seats', 'bricks-child'),
            'options'     => array('1', '2', '3', '4', '5', '6', '7', '8'),
        )
    );
}
add_shortcode('car_filter_seats', 'car_filter_seats_shortcode');

function car_filter_availability_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_availability',
        array(
            'filter_key'  => 'availability',
            'class'       => 'availability',
            'label'       => __('Availability', 'bricks-child'),
            'placeholder' => __('Any availability', 'bricks-child'),
            'options'     => array('In Stock', 'In Transit'),
        )
    );
}
add_shortcode('car_filter_availability', 'car_filter_availability_shortcode');

function car_filter_antique_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_antique',
        array(
            'filter_key'  => 'isantique',
            'class'       => 'antique',
            'label'       => __('Registration', 'bricks-child'),
            'placeholder' => __('Any registration', 'bricks-child'),
            'options'     => array(
                array(
                    'value' => '1',
                    'label' => __('Registered antique', 'bricks-child'),
                ),
            ),
        )
    );
}
add_shortcode('car_filter_antique', 'car_filter_antique_shortcode');

function car_filter_extras_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_extras',
        array(
            'filter_key'  => 'extras',
            'class'       => 'extras',
            'label'       => __('Features and extras', 'bricks-child'),
            'placeholder' => __('Any features', 'bricks-child'),
            'multiselect' => true,
            'options'     => array(
                'alloy_wheels' => __('Alloy wheels', 'bricks-child'),
                'cruise_control' => __('Cruise control', 'bricks-child'),
                'disabled_accessible' => __('Disabled accessible', 'bricks-child'),
                'keyless_start' => __('Keyless start', 'bricks-child'),
                'rear_view_camera' => __('Rear-view camera', 'bricks-child'),
                'start_stop' => __('Start/stop', 'bricks-child'),
                'sunroof' => __('Sunroof', 'bricks-child'),
                'heated_seats' => __('Heated seats', 'bricks-child'),
                'android_auto' => __('Android Auto', 'bricks-child'),
                'apple_carplay' => __('Apple CarPlay', 'bricks-child'),
                'folding_mirrors' => __('Folding mirrors', 'bricks-child'),
                'leather_seats' => __('Leather seats', 'bricks-child'),
                'panoramic_roof' => __('Panoramic roof', 'bricks-child'),
                'parking_sensors' => __('Parking sensors', 'bricks-child'),
                'camera_360' => __('360-degree camera', 'bricks-child'),
                'adaptive_cruise_control' => __('Adaptive cruise control', 'bricks-child'),
                'blind_spot_mirror' => __('Blind-spot monitoring', 'bricks-child'),
                'lane_assist' => __('Lane assist', 'bricks-child'),
                'power_tailgate' => __('Power tailgate', 'bricks-child'),
            ),
        )
    );
}
add_shortcode('car_filter_extras', 'car_filter_extras_shortcode');

function car_filter_history_shortcode($atts)
{
    return car_filter_render_extended_dropdown(
        (array) $atts,
        'car_filter_history',
        array(
            'filter_key'  => 'vehiclehistory',
            'class'       => 'history',
            'label'       => __('Vehicle history', 'bricks-child'),
            'placeholder' => __('Any vehicle history', 'bricks-child'),
            'multiselect' => true,
            'options'     => array(
                'no_accidents' => __('No accidents', 'bricks-child'),
                'minor_accidents' => __('Minor accidents', 'bricks-child'),
                'major_accidents' => __('Major accidents', 'bricks-child'),
                'regular_maintenance' => __('Regular maintenance', 'bricks-child'),
                'engine_overhaul' => __('Engine overhaul', 'bricks-child'),
                'transmission_replacement' => __('Transmission replacement', 'bricks-child'),
                'repainted' => __('Repainted', 'bricks-child'),
                'bodywork_repair' => __('Bodywork repair', 'bricks-child'),
                'rust_treatment' => __('Rust treatment', 'bricks-child'),
                'no_modifications' => __('No modifications', 'bricks-child'),
                'performance_upgrades' => __('Performance upgrades', 'bricks-child'),
                'cosmetic_modifications' => __('Cosmetic modifications', 'bricks-child'),
                'flood_damage' => __('Flood damage', 'bricks-child'),
                'fire_damage' => __('Fire damage', 'bricks-child'),
                'hail_damage' => __('Hail damage', 'bricks-child'),
                'clear_title' => __('Clear title', 'bricks-child'),
                'no_known_issues' => __('No known issues', 'bricks-child'),
                'odometer_replacement' => __('Odometer replacement', 'bricks-child'),
            ),
        )
    );
}
add_shortcode('car_filter_history', 'car_filter_history_shortcode');
