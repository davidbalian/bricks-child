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
        return '<p>' . sprintf(
            wp_kses_post(__('Please <a class="my-listings-login-link" href="%s">log in</a> to view your listings.', 'bricks-child')),
            esc_url($login_url)
        ) . '</p>';
    }

    // Get current user
    $current_user = wp_get_current_user();
    
    // Initialize refresh listing components
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingManager.php';
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingUI.php';
    require_once get_stylesheet_directory() . '/includes/user-manage-listings/refresh-listing/RefreshListingAjaxHandler.php';

    // AJAX handler for loading listings
    require_once get_stylesheet_directory() . '/includes/user-account/my-listings/MyListingsAjaxHandler.php';
    require_once get_stylesheet_directory() . '/includes/user-account/my-listings/MyListingsStatsManager.php';
    
    $refresh_manager = new RefreshListingManager();
    $refresh_ui = new RefreshListingUI($refresh_manager);
    $stats_manager = new MyListingsStatsManager();
    $user_stats = $stats_manager->get_stats_for_user((int) $current_user->ID);
    $stale_listings_count = (int) $user_stats['stale_listings'];
    
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
        array('jquery', 'autoagora-i18n'),
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
        array('jquery', 'autoagora-i18n'),
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

    if (function_exists('autoagora_enqueue_stripe_checkout_assets')) {
        autoagora_enqueue_stripe_checkout_assets();
    }
    
    // Start output buffering
    ob_start();
    ?>
    
    <div class="my-listings-container">
        <h2><?php esc_html_e('My Car Listings', 'bricks-child'); ?></h2>
        
        <?php
        // Show success/error messages
        if (isset($_GET['deleted'])) {
            if ($_GET['deleted'] === 'success') {
                echo '<div class="notice notice-success"><p>' . esc_html__('Car listing deleted successfully.', 'bricks-child') . '</p></div>';
            } elseif ($_GET['deleted'] === 'error') {
                echo '<div class="notice notice-error"><p>' . esc_html__('Error deleting car listing. Please try again.', 'bricks-child') . '</p></div>';
            }
        }
        if (isset($_GET['promotion_payment'])) {
            $promotion_payment = sanitize_key(wp_unslash($_GET['promotion_payment']));
            if ($promotion_payment === 'success') {
                echo '<div class="notice notice-success"><p>' . esc_html__('Payment completed. Stripe is confirming your promotion; it should appear shortly.', 'bricks-child') . '</p></div>';
            } elseif ($promotion_payment === 'cancelled') {
                echo '<div class="notice notice-warning"><p>' . esc_html__('Promotion checkout was cancelled. You were not charged.', 'bricks-child') . '</p></div>';
            }
        }
        ?>
        
        <div class="listings-area">
            <?php
            // Get current filter/sort from URL parameters
            $current_filter = isset($_GET['status']) ? sanitize_text_field($_GET['status']) : 'all';
            $current_sort = isset($_GET['sort']) ? sanitize_text_field($_GET['sort']) : 'newest';
            
            // Add filter dropdown
            ?>
            <div class="listings-layout">

                <div class="my-listings-stats" aria-label="<?php esc_attr_e('My listing performance statistics', 'bricks-child'); ?>">
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Total cars posted', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) $user_stats['total_listings'])); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Active listings', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) $user_stats['active_listings'])); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Pending approval', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) $user_stats['pending_listings'])); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Sold listings', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) $user_stats['sold_listings'])); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Expired listings', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) ($user_stats['expired_listings'] ?? 0))); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Total views generated', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) $user_stats['total_views'])); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Unique visitors', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) $user_stats['unique_views'])); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Contact Action Clicks', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((int) $user_stats['total_leads'])); ?></strong>
                    </div>
                    <div class="my-listings-stat-card">
                        <span class="my-listings-stat-label"><?php esc_html_e('Avg. views per listing', 'bricks-child'); ?></span>
                        <strong class="my-listings-stat-value"><?php echo esc_html(number_format_i18n((float) $user_stats['average_views_per_listing'], 1)); ?></strong>
                    </div>
                </div>

                <?php if ($stale_listings_count > 0) : ?>
                    <div class="notice notice-warning my-listings-stale-notice" role="status">
                        <p>
                            <?php
                            echo esc_html(
                                sprintf(
                                    /* translators: %d: number of stale listings */
                                    _n(
                                        'You have %d listing that is old. Refresh it using the Refresh Listing button so your listing can start appearing at the top of the search results again.',
                                        'You have %d listings that are old. Refresh them using the Refresh Listing button so your listing can start appearing at the top of the search results again.',
                                        $stale_listings_count,
                                        'bricks-child'
                                    ),
                                    $stale_listings_count
                                )
                            );
                            ?>
                        </p>
                    </div>
                <?php endif; ?>

                <div class="listings-filter">
                    <form method="get" class="status-filter-form">
                        <label for="status-filter"><?php esc_html_e('Filter by status:', 'bricks-child'); ?></label>
                        <select name="status" id="status-filter">
                            <option value="all" <?php selected($current_filter, 'all'); ?>><?php esc_html_e('All Listings', 'bricks-child'); ?></option>
                            <option value="pending" <?php selected($current_filter, 'pending'); ?>><?php esc_html_e('Pending', 'bricks-child'); ?></option>
                            <option value="publish" <?php selected($current_filter, 'publish'); ?>><?php esc_html_e('Published', 'bricks-child'); ?></option>
                            <option value="sold" <?php selected($current_filter, 'sold'); ?>><?php esc_html_e('Sold', 'bricks-child'); ?></option>
                        </select>
                    </form>
                    <div class="sort-container">
                        <label for="sort-select"><?php esc_html_e('Sort by:', 'bricks-child'); ?></label>
                        <select id="sort-select" class="sort-select">
                            <option value="newest" <?php selected($current_sort, 'newest'); ?>><?php esc_html_e('Newest First', 'bricks-child'); ?></option>
                            <option value="oldest" <?php selected($current_sort, 'oldest'); ?>><?php esc_html_e('Oldest First', 'bricks-child'); ?></option>
                            <option value="price-high" <?php selected($current_sort, 'price-high'); ?>><?php esc_html_e('Price: High to Low', 'bricks-child'); ?></option>
                            <option value="price-low" <?php selected($current_sort, 'price-low'); ?>><?php esc_html_e('Price: Low to High', 'bricks-child'); ?></option>
                        </select>
                    </div>
                    <div class="search-container">
                        <label for="listing-search"><?php esc_html_e('Search:', 'bricks-child'); ?></label>
                        <input type="text" id="listing-search" placeholder="<?php esc_attr_e('Search listings...', 'bricks-child'); ?>" class="search-input">
                    </div>
                </div>

                <?php
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
                        $args['meta_query'] = ListingStateManager::meta_query_sold_only();
                    } else {
                        $args['post_status'] = $current_filter;
                        if ($current_filter === 'publish') {
                            $args['meta_query'] = ListingStateManager::meta_query_active_only();
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
                    echo '<p>' . esc_html__("You haven't created any car listings yet.", 'bricks-child') . '</p>';
                    echo '<p><a href="' . esc_url(autoagora_localized_page_url('add-listing')) . '" class="btn btn-primary">' . esc_html__('Add New Listing', 'bricks-child') . '</a></p>';
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
        wp_die(esc_html__('Invalid car listing ID.', 'bricks-child'));
    }
    
    // Verify nonce
    if (!wp_verify_nonce($nonce, 'delete_car_listing_' . $car_id)) {
        wp_die(esc_html__('Security check failed. Please try again.', 'bricks-child'));
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_die(esc_html__('You must be logged in to delete listings.', 'bricks-child'));
    }
    
    $current_user_id = get_current_user_id();
    
    // Check if car exists
    $car = get_post($car_id);
    if (!$car || $car->post_type !== 'car') {
        wp_die(esc_html__('Car listing not found.', 'bricks-child'));
    }
    
    // Check if post is already in trash
    if ($car->post_status === 'trash') {
        wp_die(esc_html__('This car listing is already in the trash.', 'bricks-child'));
    }
    
    // Check if user owns this car listing
    if ($car->post_author != $current_user_id) {
        wp_die(esc_html__('Access denied. You can only delete your own listings.', 'bricks-child'));
    }
    
    // Check if user has permission to delete posts
    // Use multiple capability checks for better compatibility with custom roles
    $can_delete = current_user_can('delete_post', $car_id) || 
                  current_user_can('delete_posts') || 
                  current_user_can('delete_published_posts') || 
                  current_user_can('administrator');
    
    if (!$can_delete) {
        wp_die(esc_html__('Permission denied. You do not have sufficient privileges to delete this listing.', 'bricks-child'));
    }
    
    // Clean any output buffers before redirect to prevent "headers already sent" errors
    if (ob_get_length()) {
        ob_clean();
    }
    
    // Attempt deletion
    $deleted = wp_delete_post($car_id, true);
    
    if ($deleted) {
        // Redirect back to my listings with success message
        wp_redirect(add_query_arg('deleted', 'success', autoagora_localized_page_url('my-listings')));
    } else {
        // Redirect back with error message
        wp_redirect(add_query_arg('deleted', 'error', autoagora_localized_page_url('my-listings')));
    }
    exit;
}

// Add the delete handler for both logged in and non-logged in users
add_action('admin_post_delete_car_listing', 'handle_frontend_car_deletion');
add_action('admin_post_nopriv_delete_car_listing', 'handle_frontend_car_deletion');
