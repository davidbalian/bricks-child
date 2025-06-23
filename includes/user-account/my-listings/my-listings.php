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
        return '<p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to view your listings.</p>';
    }

    // Get current user
    $current_user = wp_get_current_user();
    
    // Enqueue jQuery
    wp_enqueue_script('jquery');
    
    // Prepare localized data
    $ajax_nonce = wp_create_nonce('toggle_car_status_nonce');
    
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
            <div class="listings-filter">
                <form method="get" class="status-filter-form">
                    <label for="status-filter">Filter by status:</label>
                    <select name="status" id="status-filter" onchange="this.form.submit()">
                        <option value="all" <?php selected($current_filter, 'all'); ?>>All Listings</option>
                        <option value="pending" <?php selected($current_filter, 'pending'); ?>>Pending</option>
                        <option value="publish" <?php selected($current_filter, 'publish'); ?>>Published</option>
                        <option value="sold" <?php selected($current_filter, 'sold'); ?>>Sold</option>
                    </select>
                </form>
                <div class="sort-container">
                    <label for="sort-select">Sort by:</label>
                    <select id="sort-select" class="sort-select">
                        <option value="newest">Newest First</option>
                        <option value="oldest">Oldest First</option>
                        <option value="price-high">Price: High to Low</option>
                        <option value="price-low">Price: Low to High</option>
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
            
            // Query for user's car listings
            $args = array(
                'post_type' => 'car',
                'author' => $current_user->ID,
                'posts_per_page' => -1,
                'orderby' => 'date',
                'order' => 'DESC',
                'post_status' => array('publish', 'pending')
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
                <div class="listings-grid">
                    <?php while ($user_listings->have_posts()) : $user_listings->the_post(); 
                        $post_id = get_the_ID();
                        $price = get_field('price', $post_id);
                        
                        // Get all car images
                        $featured_image = get_post_thumbnail_id($post_id);
                        $additional_images = get_field('car_images', $post_id);
                        $all_images = array();
                        
                        if ($featured_image) {
                            $all_images[] = $featured_image;
                        }
                        
                        if (is_array($additional_images)) {
                            $all_images = array_merge($all_images, $additional_images);
                        }
                    ?>
                        <div class="listing-item">
                            <div class="listing-image-container">
                                <?php if (!empty($all_images)) : 
                                    $main_image_url = wp_get_attachment_image_url($all_images[0], 'large');
                                ?>
                                    <a href="<?php echo esc_url(add_query_arg('car_id', $post_id, get_permalink($post_id))); ?>" class="listing-image-link">
                                        <img src="<?php echo esc_url($main_image_url); ?>" alt="<?php the_title(); ?>" class="listing-image">
                                        <div class="image-count">
                                            <i class="fas fa-camera"></i>
                                            <span><?php echo count($all_images); ?></span>
                                        </div>
                                    </a>
                                <?php endif; ?>
                            </div>
                            <div class="listing-details">
                                <div class="title-and-price">
                                    <a href="<?php echo esc_url(add_query_arg('car_id', $post_id, get_permalink($post_id))); ?>" class="listing-title-link">
                                        <h3 class="listing-title"><?php the_title(); ?></h3>
                                    </a>
                                    <h4 class="listing-price">€<?php echo number_format(floatval(str_replace(',', '', $price))); ?></h4>
                                </div>
                                <div class="listing-meta">
                                    <span class="listing-date">Published: <?php echo get_the_date(); ?></span>
                                    <span class="listing-status<?php 
                                        if (get_field('is_sold', $post_id)) {
                                            echo ' status-sold';
                                        } elseif (get_post_status() === 'pending') {
                                            echo ' status-pending';
                                        } elseif (get_post_status() === 'publish') {
                                            echo ' status-published';
                                        }
                                    ?>">Status: <?php 
                                        $is_sold = get_field('is_sold', $post_id);
                                        if ($is_sold) {
                                            echo 'SOLD';
                                        } else {
                                            echo get_post_status() === 'publish' ? 'Published' : ucfirst(get_post_status());
                                        }
                                    ?></span>
                                </div>
                                <div class="listing-actions">
                                    <a href="<?php echo esc_url(add_query_arg('car_id', $post_id, home_url('/edit-listing/'))); ?>" class="button"><i class="fas fa-pencil-alt"></i> Edit</a>
                                    <?php 
                                    if (get_post_status() === 'publish') {
                                        $is_sold = get_field('is_sold', $post_id);
                                        $button_text = $is_sold ? ' Mark as Available' : ' Mark as Sold';
                                        $button_class = $is_sold ? 'button available-button' : 'button sold-button';
                                        $icon_class = $is_sold ? 'fas fa-undo-alt' : 'fas fa-check-circle';
                                        ?>
                                        <button class="<?php echo esc_attr($button_class); ?>" 
                                                onclick="toggleCarStatus(<?php echo $post_id; ?>, <?php echo $is_sold ? 'false' : 'true'; ?>)">
                                            <i class="<?php echo esc_attr($icon_class); ?>"></i><?php echo esc_html($button_text); ?>
                                        </button>
                                    <?php } ?>
                                    <?php 
                                    // Create custom frontend delete URL
                                    $delete_url = add_query_arg(array(
                                        'action' => 'delete_car_listing',
                                        'car_id' => $post_id,
                                        '_wpnonce' => wp_create_nonce('delete_car_listing_' . $post_id)
                                    ), admin_url('admin-post.php'));
                                    ?>
                                    <a href="<?php echo esc_url($delete_url); ?>" class="button delete-button" onclick="return confirm('Are you sure you want to delete this listing? This action cannot be undone.');"><i class="fas fa-trash-alt"></i> Delete</a>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php 
            else :
                echo '<p>You haven\'t created any car listings yet.</p>';
                echo '<p><a href="' . esc_url(home_url('/add-listing/')) . '" class="button">Add New Listing</a></p>';
            endif;
            
            wp_reset_postdata();
            ?>
        </div>
    </div>

    <script>
    // Define myListingsData object with localized data
    const myListingsData = {
        ajaxurl: '<?php echo admin_url('admin-ajax.php'); ?>',
        nonce: '<?php echo $ajax_nonce; ?>'
    };
    
    function toggleCarStatus(carId, markAsSold) {
        console.log('Toggle function called with:', { carId, markAsSold });
        
        if (!confirm(markAsSold ? 'Are you sure you want to mark this car as sold?' : 'Are you sure you want to mark this car as available?')) {
            return;
        }

        const data = {
            action: 'toggle_car_status',
            car_id: carId,
            mark_as_sold: markAsSold,
            nonce: myListingsData.nonce
        };

        console.log('Sending AJAX request with data:', data);

        jQuery.post(myListingsData.ajaxurl, data, function(response) {
            console.log('AJAX response:', response);
            if (response.success) {
                location.reload();
            } else {
                alert('Error updating car status. Please try again.');
            }
        }).fail(function(jqXHR, textStatus, errorThrown) {
            console.error('AJAX request failed:', { textStatus, errorThrown });
            alert('Error updating car status. Please try again.');
        });
    }

    // Add search and sort functionality
    document.addEventListener('DOMContentLoaded', function() {
        const searchInput = document.getElementById('listing-search');
        const sortSelect = document.getElementById('sort-select');
        const listingsGrid = document.querySelector('.listings-grid');
        const listingItems = document.querySelectorAll('.listing-item');

        // Set initial sort value from URL
        const urlParams = new URLSearchParams(window.location.search);
        const sortParam = urlParams.get('sort');
        if (sortParam) {
            sortSelect.value = sortParam;
        }

        // Search functionality
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.toLowerCase().trim();
            
            listingItems.forEach(item => {
                const title = item.querySelector('.listing-title').textContent.toLowerCase();
                const price = item.querySelector('.listing-title').textContent.toLowerCase();
                const date = item.querySelector('.listing-date').textContent.toLowerCase();
                const status = item.querySelector('.listing-status').textContent.toLowerCase();
                
                const isVisible = title.includes(searchTerm) || 
                                price.includes(searchTerm) || 
                                date.includes(searchTerm) || 
                                status.includes(searchTerm);
                
                item.style.display = isVisible ? '' : 'none';
            });
        });

        // Sort functionality
        sortSelect.addEventListener('change', function() {
            const sortValue = this.value;
            const items = Array.from(listingItems);
            
            items.sort((a, b) => {
                switch(sortValue) {
                    case 'newest':
                        return new Date(b.querySelector('.listing-date').textContent.split(': ')[1]) - 
                               new Date(a.querySelector('.listing-date').textContent.split(': ')[1]);
                    case 'oldest':
                        return new Date(a.querySelector('.listing-date').textContent.split(': ')[1]) - 
                               new Date(b.querySelector('.listing-date').textContent.split(': ')[1]);
                    case 'price-high':
                    case 'price-low':
                        const priceTextA = a.querySelector('.listing-price').textContent.match(/€([\d,.]+)/)[1];
                        const priceTextB = b.querySelector('.listing-price').textContent.match(/€([\d,.]+)/)[1];
                        const priceA = parseFloat(priceTextA.replace(/,/g, ''));
                        const priceB = parseFloat(priceTextB.replace(/,/g, ''));
                        return sortValue === 'price-high' ? priceB - priceA : priceA - priceB;
                    default:
                        return 0;
                }
            });

            // Reorder items in the DOM
            items.forEach(item => {
                if (item.style.display !== 'none') {
                    listingsGrid.appendChild(item);
                }
            });

            // Update URL with sort parameter
            const url = new URL(window.location.href);
            url.searchParams.set('sort', sortValue);
            window.history.pushState({}, '', url);
        });
    });
    </script>
    
    <?php
    // Return the buffered content
    return ob_get_clean();
}

/**
 * Handle frontend car listing deletion - ROBUST VERSION
 */
function handle_frontend_car_deletion() {
    // Enable detailed error logging
    error_log('=== CAR DELETION DEBUG START ===');
    error_log('Raw GET data: ' . print_r($_GET, true));
    error_log('Request URI: ' . $_SERVER['REQUEST_URI']);
    error_log('User Agent: ' . ($_SERVER['HTTP_USER_AGENT'] ?? 'Not set'));
    
    // Input validation and sanitization
    $car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;
    $nonce = isset($_GET['_wpnonce']) ? sanitize_text_field($_GET['_wpnonce']) : '';
    
    error_log('Parsed car_id: ' . $car_id);
    error_log('Parsed nonce: ' . $nonce);
    
    // Early exit for invalid input
    if ($car_id <= 0) {
        error_log('ERROR: Invalid car listing ID');
        wp_die('Invalid car listing ID.');
    }
    
    // Verify nonce
    $nonce_action = 'delete_car_listing_' . $car_id;
    error_log('Nonce action: ' . $nonce_action);
    
    if (!wp_verify_nonce($nonce, $nonce_action)) {
        error_log('ERROR: Nonce verification failed for action: ' . $nonce_action);
        wp_die('Security check failed. Please try again.');
    }
    error_log('SUCCESS: Nonce verified');
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        error_log('ERROR: User not logged in');
        wp_die('You must be logged in to delete listings.');
    }
    
    $current_user_id = get_current_user_id();
    $current_user = wp_get_current_user();
    error_log('Current user ID: ' . $current_user_id);
    error_log('Current user roles: ' . print_r($current_user->roles, true));
    
    // Check if car exists and get fresh data to avoid race conditions
    $car = get_post($car_id);
    if (!$car) {
        error_log('ERROR: Car post not found with ID: ' . $car_id);
        wp_die('Car listing not found. It may have already been deleted.');
    }
    error_log('Car post found: ' . $car->post_title . ' (ID: ' . $car->ID . ')');
    error_log('Car post type: ' . $car->post_type);
    error_log('Car post status: ' . $car->post_status);
    error_log('Car post author: ' . $car->post_author);
    
    if ($car->post_type !== 'car') {
        error_log('ERROR: Invalid post type: ' . $car->post_type);
        wp_die('Invalid post type. Only car listings can be deleted through this interface.');
    }
    
    // Check if post is already in trash
    if ($car->post_status === 'trash') {
        error_log('ERROR: Car already in trash');
        wp_die('This car listing is already in the trash.');
    }
    
    // Check if user owns this car listing
    if ($car->post_author != $current_user_id) {
        error_log('ERROR: User does not own this car. Owner: ' . $car->post_author . ', Current user: ' . $current_user_id);
        wp_die('Access denied. You can only delete your own listings.');
    }
    error_log('SUCCESS: User owns the car listing');
    
    // Check if user has permission to delete posts
    $can_delete = current_user_can('delete_post', $car_id);
    error_log('User can delete post: ' . ($can_delete ? 'YES' : 'NO'));
    
    // Alternative capability checks for dealerships
    $can_delete_posts = current_user_can('delete_posts');
    $can_delete_published = current_user_can('delete_published_posts');
    $is_admin = current_user_can('administrator');
    
    error_log('User can delete_posts: ' . ($can_delete_posts ? 'YES' : 'NO'));
    error_log('User can delete_published_posts: ' . ($can_delete_published ? 'YES' : 'NO'));
    error_log('User is admin: ' . ($is_admin ? 'YES' : 'NO'));
    
    // For dealerships, use a more permissive check since they should be able to delete their own posts
    $can_delete_own = ($can_delete || $can_delete_posts || $can_delete_published || $is_admin);
    
    if (!$can_delete_own) {
        error_log('ERROR: User lacks delete capabilities for car_id: ' . $car_id);
        
        // Let's also check what capabilities the user has
        $user_caps = $current_user->get_role_caps();
        error_log('User capabilities: ' . print_r($user_caps, true));
        
        wp_die('Permission denied. You do not have sufficient privileges to delete this listing.');
    }
    error_log('SUCCESS: User has delete permissions');
    
    // Log the deletion attempt for audit purposes
    error_log(sprintf(
        'Car listing deletion attempt: User %d attempting to delete car %d ("%s")',
        $current_user_id,
        $car_id,
        $car->post_title
    ));
    
    // Attempt deletion with error handling
    error_log('Attempting wp_delete_post...');
    $deleted = wp_delete_post($car_id, true);
    error_log('wp_delete_post result: ' . print_r($deleted, true));
    
    if ($deleted) {
        // Log successful deletion
        error_log(sprintf(
            'Car listing deletion successful: User %d deleted car %d ("%s")',
            $current_user_id,
            $car_id,
            $car->post_title
        ));
        
        error_log('Redirecting to success page...');
        // Redirect back to my listings with success message
        wp_redirect(add_query_arg('deleted', 'success', home_url('/my-listings/')));
    } else {
        // Log failed deletion
        error_log(sprintf(
            'Car listing deletion failed: User %d failed to delete car %d ("%s") - wp_delete_post returned false',
            $current_user_id,
            $car_id,
            $car->post_title
        ));
        
        error_log('Redirecting to error page...');
        // Redirect back with error message
        wp_redirect(add_query_arg('deleted', 'error', home_url('/my-listings/')));
    }
    
    error_log('=== CAR DELETION DEBUG END ===');
    exit;
}

// Add the delete handler for both logged in and non-logged in users
add_action('admin_post_delete_car_listing', 'handle_frontend_car_deletion');
add_action('admin_post_nopriv_delete_car_listing', 'handle_frontend_car_deletion');

// Temporary debug handler to test if admin_post hooks are working
function debug_admin_post_action() {
    error_log('DEBUG: admin_post hook triggered for action: ' . ($_GET['action'] ?? 'none'));
    error_log('DEBUG: All GET parameters: ' . print_r($_GET, true));
    wp_die('DEBUG: admin_post action received. Action: ' . ($_GET['action'] ?? 'none'));
}

// Add debug handler for testing (remove after debugging)
add_action('admin_post_test_debug', 'debug_admin_post_action');
add_action('admin_post_nopriv_test_debug', 'debug_admin_post_action');