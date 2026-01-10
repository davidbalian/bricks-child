<?php
/**
 * My Listings Shortcode
 * 
 * @package Astra Child
 * @since 1.0.0
 */

// Register the shortcode
add_shortcode('my_listings', 'display_my_listings');

function display_my_listings($atts) {
    // Check if user is logged in
    if (!is_user_logged_in()) {
        $login_url = wp_login_url(get_permalink());
        return '<p>Please <a class="my-listings-login-link" href="' . esc_url($login_url) . '">log in</a> to view your listings.</p>';
    }

    // Get current user
    $current_user = wp_get_current_user();
    
    // Initialize refresh listing components
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingManager.php';
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingUI.php';
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingAjaxHandler.php';

    // AJAX handler for loading listings
    require_once get_stylesheet_directory() . '/includes/user-account/my-listings/MyListingsAjaxHandler.php';
    
    $refresh_manager = new RefreshListingManager();
    $refresh_ui = new RefreshListingUI($refresh_manager);
    
    // Enqueue jQuery
    wp_enqueue_script('jquery');
    
    // Enqueue refresh listing assets
    wp_enqueue_style(
        'refresh-listing-css',
        get_stylesheet_directory_uri() . '/includes/user-manage-listings/refresh-listing/refresh-listing.css',
        array(),
        '1.0.0'
    );
    
    wp_enqueue_script(
        'refresh-listing-js',
        get_stylesheet_directory_uri() . '/includes/user-manage-listings/refresh-listing/refresh-listing.js',
        array('jquery'),
        '1.0.0',
        true
    );
    
    // Prepare localized data for refresh listing script
    $toggle_status_nonce = wp_create_nonce('toggle_car_status_nonce');
    
    wp_localize_script('refresh-listing-js', 'refreshListingData', array(
        'ajaxUrl'   => admin_url('admin-ajax.php'),
        'ajaxAction'=> RefreshListingAjaxHandler::get_ajax_action(),
        'nonce'     => RefreshListingAjaxHandler::create_nonce()
    ));

    // Enqueue My Listings JS for AJAX loading and status toggling
    wp_enqueue_script(
        'my-listings-js',
        get_stylesheet_directory_uri() . '/includes/user-account/my-listings/my-listings.js',
        array('jquery'),
        '1.0.0',
        true
    );

    wp_localize_script('my-listings-js', 'myListingsData', array(
        'ajaxUrl'           => admin_url('admin-ajax.php'),
        'toggleNonce'       => $toggle_status_nonce,
        'listingsAjaxAction'=> MyListingsAjaxHandler::get_ajax_action(),
        'listingsNonce'     => MyListingsAjaxHandler::create_nonce(),
        'perPage'           => MyListingsAjaxHandler::DEFAULT_PER_PAGE,
        'isDevelopment'     => (defined('WP_DEBUG') && WP_DEBUG),
    ));
    
    // Start output buffering
    ob_start();
    ?>
    
    <div class="my-listings-container">
        <h2>My Car Listings</h2>
        
        <?php
        // Show success/error messages
        if (isset($_GET['deleted'])) {
            if ($_GET['deleted'] === 'success') {
                echo '<div class="notice notice-success"><p>Car listing deleted successfully.</p></div>';
            } elseif ($_GET['deleted'] === 'error') {
                echo '<div class="notice notice-error"><p>Error deleting car listing. Please try again.</p></div>';
            }
        }
        ?>
        
        <div class="listings-area">
            <?php
            // Get current filter from URL parameter
            $current_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
            
            // Add filter dropdown
            ?>
            <div class="listings-layout">

                <!-- LEFT: filters -->
                <div class="listings-filter">
                    <form method="get" class="status-filter-form">
                        <label for="status-filter">Filter by status:</label>
                        <select name="status" id="status-filter">
                            <option value="all" <?php selected($current_filter, 'all'); ?>>All Listings</option>
                            <option value="pending" <?php selected($current_filter, 'pending'); ?>>Pending</option>
                            <option value="publish" <?php selected($current_filter, 'publish'); ?>>Published</option>
                            <option value="sold" <?php selected($current_filter, 'sold'); ?>>Sold</option>
                        </select>
                    </form>
                    <div class="sort-container">
                        <label for="sort-select">Sort by:</label>
                        <select id="sort-select" class="sort-select">
                            <option value="newest" <?php selected($current_sort, 'newest'); ?>>Newest First</option>
                            <option value="oldest" <?php selected($current_sort, 'oldest'); ?>>Oldest First</option>
                            <option value="price-high" <?php selected($current_sort, 'price-high'); ?>>Price: High to Low</option>
                            <option value="price-low" <?php selected($current_sort, 'price-low'); ?>>Price: Low to High</option>
                        </select>
                    </div>
                    <div class="search-container">
                        <label for="listing-search">Search:</label>
                        <input type="text" id="listing-search" placeholder="Search listings..." class="search-input">
                    </div>
                </div>

                <?php
                // Get current sort from URL parameter
                $current_sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest';
                
                // Determine current page for initial server-side query
                $current_page = get_query_var('paged') ? get_query_var('paged') : 1;
                
                // Query for user's car listings - only first page to keep initial load fast
                $args = array(
                    'post_type'      => 'car',
                    'author'         => $current_user->ID,
                    'posts_per_page' => MyListingsAjaxHandler::DEFAULT_PER_PAGE,
                    'paged'          => $current_page,
                    'orderby'        => 'date',
                    'order'          => 'DESC',
                    'post_status'    => array('publish', 'pending')
                );

                // Apply sorting
                switch ($current_sort) {
                    case 'oldest':
                        $args['order'] = 'ASC';
                        break;
                    case 'price-high':
                        $args['meta_key'] = 'price';
                        $args['orderby'] = 'meta_value_num';
                        $args['order'] = 'DESC';
                        break;
                    case 'price-low':
                        $args['meta_key'] = 'price';
                        $args['orderby'] = 'meta_value_num';
                        $args['order'] = 'ASC';
                        break;
                    default: // newest
                        $args['orderby'] = 'date';
                        $args['order'] = 'DESC';
                }

                // Apply status filter
                if ($current_filter !== 'all') {
                    if ($current_filter === 'sold') {
                        $args['meta_query'] = array(
                            array(
                                'key' => 'is_sold',
                                'value' => '1',
                                'compare' => '='
                            )
                        );
                    } else {
                        $args['post_status'] = $current_filter;
                        if ($current_filter === 'publish') {
                            $args['meta_query'] = array(
                                'relation' => 'OR',
                                array(
                                    'key' => 'is_sold',
                                    'value' => '0',
                                    'compare' => '='
                                ),
                                array(
                                    'key' => 'is_sold',
                                    'compare' => 'NOT EXISTS'
                                )
                            );
                        }
                    }
                }
                
                $user_listings = new WP_Query($args);
                
                
                if ($user_listings->have_posts()) :
                ?>
                <div class="listings-results">
                    <!-- TOP pagination -->
                    <div class="my-listings-pagination-container my-listings-pagination-top">
                        <?php
                        MyListingsAjaxHandler::render_pagination(
                            (int) $current_page,
                            (int) $user_listings->max_num_pages
                        );
                        ?>
                    </div>

                    <div
                        class="listings-grid"
                        data-page="<?php echo esc_attr($current_page); ?>"
                        data-max-pages="<?php echo esc_attr($user_listings->max_num_pages); ?>"
                        data-per-page="<?php echo esc_attr(MyListingsAjaxHandler::DEFAULT_PER_PAGE); ?>"
                    >
                        <?php
                        while ($user_listings->have_posts()) :
                            $user_listings->the_post();
                            MyListingsAjaxHandler::render_listing_item(get_the_ID(), $refresh_ui);
                        endwhile;
                        ?>
                    </div>

                    <div class="my-listings-pagination-container">
                        <?php
                        MyListingsAjaxHandler::render_pagination(
                            (int) $current_page,
                            (int) $user_listings->max_num_pages
                        );
                        ?>
                    </div>
                </div>
                <?php 
                else :
                    echo '<p>You haven\'t created any car listings yet.</p>';
                    echo '<p><a href="' . esc_url(home_url('/add-listing/')) . '" class="btn btn-primary">Add New Listing</a></p>';
                endif;
                
                wp_reset_postdata();
                ?>
            </div>
        </div>
    </div>

    
    <?php
    // Return the buffered content
    return ob_get_clean();
}

/**
 * Handle frontend car listing deletion
 */
function handle_frontend_car_deletion() {
    // Input validation and sanitization
    $car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
    
    // Early exit for invalid input
    if ($car_id <= 0) {
        wp_die('Invalid car listing ID.');
    }
    
    // Verify nonce
    if (!wp_verify_nonce($nonce, 'delete_car_listing_' . $car_id)) {
        wp_die('Security check failed. Please try again.');
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_die('You must be logged in to delete listings.');
    }
    
    $current_user_id = get_current_user_id();
    
    // Check if car exists
    $car = get_post($car_id);
    if (!$car || $car->post_type !== 'car') {
        wp_die('Car listing not found.');
    }
    
    // Check if post is already in trash
    if ($car->post_status === 'trash') {
        wp_die('This car listing is already in the trash.');
    }
    
    // Check if user owns this car listing
    if ($car->post_author != $current_user_id) {
        wp_die('Access denied. You can only delete your own listings.');
    }
    
    // Check if user has permission to delete posts
    // Use multiple capability checks for better compatibility with custom roles
    $can_delete = current_user_can('delete_post', $car_id) || 
                  current_user_can('delete_posts') || 
                  current_user_can('delete_published_posts') || 
                  current_user_can('administrator');
    
    if (!$can_delete) {
        wp_die('Permission denied. You do not have sufficient privileges to delete this listing.');
    }
    
    // Clean any output buffers before redirect to prevent "headers already sent" errors
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Attempt deletion
    $deleted = wp_delete_post($car_id, true);
    
    if ($deleted) {
        // Redirect back to my listings with success message
        wp_redirect(add_query_arg('deleted', 'success', home_url('/my-listings/')));
    } else {
        // Redirect back with error message
        wp_redirect(add_query_arg('deleted', 'error', home_url('/my-listings/')));
    }
    exit;
}

// Add the delete handler for both logged in and non-logged in users
add_action('admin_post_delete_car_listing', 'handle_frontend_car_deletion');
add_action('admin_post_nopriv_delete_car_listing', 'handle_frontend_car_deletion');