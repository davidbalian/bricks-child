<?php
/**
 * User Favorites Column
 * 
 * Adds a column to the WordPress users list table to display favorite cars count.
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Exit if accessed directly
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Add Favorite Cars column to users list table
 */
function add_favorite_cars_column($columns) {
    $columns['favorite_cars'] = __('Favorite Cars', 'astra-child');
    return $columns;
}
add_filter('manage_users_columns', 'add_favorite_cars_column');

/**
 * Display favorite cars count in the column
 */
function display_favorite_cars_column($value, $column_name, $user_id) {
    if ($column_name === 'favorite_cars') {
        $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
        
        if (empty($favorite_cars) || !is_array($favorite_cars)) {
            return '0';
        }
        
        $count = count($favorite_cars);
        $admin_url = admin_url('users.php?page=favorite-listings&user_id=' . $user_id);
        
        return '<a href="' . esc_url($admin_url) . '">' . esc_html($count) . '</a>';
    }
    
    return $value;
}
add_action('manage_users_custom_column', 'display_favorite_cars_column', 10, 3);

/**
 * Make the column sortable
 */
function make_favorite_cars_column_sortable($columns) {
    $columns['favorite_cars'] = 'favorite_cars';
    return $columns;
}
add_filter('manage_users_sortable_columns', 'make_favorite_cars_column_sortable');

/**
 * Handle the sorting of the favorite cars column
 */
function sort_favorite_cars_column($query) {
    if (!is_admin() || !$query->is_main_query() || $query->get('orderby') !== 'favorite_cars') {
        return;
    }
    
    $query->set('meta_key', 'favorite_cars');
    $query->set('orderby', 'meta_value_num');
    
    // Add a filter to count the number of items in the array
    add_filter('get_user_metadata', 'count_favorite_cars_for_sorting', 10, 4);
}
add_action('pre_get_users', 'sort_favorite_cars_column');

/**
 * Count the number of favorite cars for sorting
 */
function count_favorite_cars_for_sorting($value, $object_id, $meta_key, $single) {
    if ($meta_key === 'favorite_cars' && is_array($value)) {
        return count($value);
    }
    return $value;
}

/**
 * Add a page to view a user's favorite cars
 */
function add_favorite_cars_page() {
    add_submenu_page(
        null, // No parent menu
        __('User Favorite Cars', 'astra-child'),
        __('Favorite Cars', 'astra-child'),
        'list_users',
        'favorite-listings',
        'display_user_favorite_cars_page'
    );
}
add_action('admin_menu', 'add_favorite_cars_page');

/**
 * Display the user's favorite cars page
 */
function display_user_favorite_cars_page() {
    // Check if user has permission
    if (!current_user_can('list_users')) {
        wp_die(__('You do not have sufficient permissions to access this page.'));
    }
    
    // Get user ID from URL
    $user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;
    if (!$user_id) {
        wp_die(__('Invalid user ID.'));
    }
    
    // Get user data
    $user = get_userdata($user_id);
    if (!$user) {
        wp_die(__('User not found.'));
    }
    
    // Get user's favorite cars
    $favorite_cars = get_user_meta($user_id, 'favorite_cars', true);
    if (!is_array($favorite_cars)) {
        $favorite_cars = array();
    }
    
    // Start output
    ?>
    <div class="wrap">
        <h1><?php printf(__('Favorite Cars for %s', 'astra-child'), $user->display_name); ?></h1>
        
        <div class="tablenav top">
            <div class="alignleft actions">
                <a href="<?php echo esc_url(admin_url('users.php')); ?>" class="button"><?php _e('Back to Users', 'astra-child'); ?></a>
            </div>
        </div>
        
        <?php if (empty($favorite_cars)) : ?>
            <p><?php _e('This user has no favorite cars.', 'astra-child'); ?></p>
        <?php else : ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th><?php _e('ID', 'astra-child'); ?></th>
                        <th><?php _e('Title', 'astra-child'); ?></th>
                        <th><?php _e('Make', 'astra-child'); ?></th>
                        <th><?php _e('Model', 'astra-child'); ?></th>
                        <th><?php _e('Year', 'astra-child'); ?></th>
                        <th><?php _e('Price', 'astra-child'); ?></th>
                        <th><?php _e('Actions', 'astra-child'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($favorite_cars as $car_id) : 
                        $car = get_post($car_id);
                        if (!$car || $car->post_type !== 'car') continue;
                        
                        $make = get_field('make', $car_id);
                        $model = get_field('model', $car_id);
                        $year = get_field('year', $car_id);
                        $price = get_field('price', $car_id);
                    ?>
                        <tr>
                            <td><?php echo esc_html($car_id); ?></td>
                            <td><?php echo esc_html($car->post_title); ?></td>
                            <td><?php echo esc_html($make); ?></td>
                            <td><?php echo esc_html($model); ?></td>
                            <td><?php echo esc_html($year); ?></td>
                            <td><?php echo esc_html($price); ?></td>
                            <td>
                                <a href="<?php echo esc_url(get_edit_post_link($car_id)); ?>" class="button button-small"><?php _e('Edit', 'astra-child'); ?></a>
                                <a href="<?php echo esc_url(get_permalink($car_id)); ?>" class="button button-small" target="_blank"><?php _e('View', 'astra-child'); ?></a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    <?php
}