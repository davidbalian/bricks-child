<?php
/**
 * Favorites Toggle Button Shortcode [favorites_toggle_button]
 * Just the heart button with toggle functionality - not a link to favorites page
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit; // Exit if accessed directly.
}

// Register the shortcode
add_shortcode( 'favorites_toggle_button', 'favorites_toggle_button_shortcode' );

/**
 * Favorites Toggle Button Shortcode Function
 */
function favorites_toggle_button_shortcode( $atts ) {
    // Parse shortcode attributes
    $atts = shortcode_atts( array(
        'post_id' => null,
    ), $atts );

    // Get post ID - prioritize URL parameter, then attribute, then current post ID
    if (isset($_GET['car_id'])) {
        $car_id = intval($_GET['car_id']);
    } elseif ($atts['post_id']) {
        $car_id = intval($atts['post_id']);
    } else {
        $car_id = get_the_ID();
    }

    if ( ! $car_id || get_post_type( $car_id ) !== 'car' ) {
        return '<!-- Favorites Toggle Button: Not available for this post type -->';
    }

    // Check if user has favorited this car
    $user_id = get_current_user_id();
    $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
    $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
    $is_favorite = $user_id ? in_array($car_id, $favorite_cars) : false;
    $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';

    ob_start();
    ?>
    <div class="favorites-toggle-wrapper">
        <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>" title="<?php echo $is_favorite ? 'Remove from favorites' : 'Add to favorites'; ?>">
            <i class="<?php echo esc_attr($heart_class); ?>"></i>
            <span class="favorite-text"><?php echo $is_favorite ? 'Saved' : 'Save'; ?></span>
        </button>
    </div>

    <style>
    .favorites-toggle-wrapper {
        display: inline-block;
    }
    
    .favorites-toggle-wrapper .favorite-btn {
        display: flex;
        align-items: center;
        gap: 8px;
        background: #fff;
        border: 2px solid #e0e0e0;
        padding: 10px 16px;
        border-radius: 6px;
        cursor: pointer;
        transition: all 0.3s ease;
        font-size: 14px;
        font-weight: 500;
    }
    
    .favorites-toggle-wrapper .favorite-btn:hover {
        border-color: #ff0000;
        background: #fff5f5;
    }
    
    .favorites-toggle-wrapper .favorite-btn.active {
        border-color: #ff0000;
        background: #fff5f5;
    }
    
    .favorites-toggle-wrapper .favorite-btn i {
        color: #ff0000;
        font-size: 16px;
    }
    
    .favorites-toggle-wrapper .favorite-btn .favorite-text {
        color: #333;
    }
    
    .favorites-toggle-wrapper .favorite-btn.active .favorite-text {
        color: #ff0000;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        $('.favorites-toggle-wrapper .favorite-btn').on('click', function(e) {
            e.preventDefault();
            e.stopPropagation();

            // Check if user is logged in
            <?php if (!is_user_logged_in()) : ?>
                alert('Please log in to add favorites.');
                return;
            <?php endif; ?>

            const button = $(this);
            const carId = button.data('car-id');
            const isActive = button.hasClass('active');
            const heartIcon = button.find('i');
            const textSpan = button.find('.favorite-text');

            // Optimistic UI update
            button.toggleClass('active');
            if (isActive) {
                heartIcon.removeClass('fas').addClass('far');
                textSpan.text('Save');
            } else {
                heartIcon.removeClass('far').addClass('fas');
                textSpan.text('Saved');
            }

            // Prepare AJAX data
            const formData = new FormData();
            formData.append('action', 'toggle_favorite_car');
            formData.append('car_id', carId);
            formData.append('is_favorite', !isActive ? '1' : '0');
            formData.append('nonce', '<?php echo wp_create_nonce("toggle_favorite_car"); ?>');

            // Send AJAX request
            fetch('<?php echo admin_url("admin-ajax.php"); ?>', {
                method: 'POST',
                body: formData,
                credentials: 'same-origin',
            })
            .then(response => response.json())
            .then(data => {
                if (!data.success) {
                    // Revert UI on failure
                    button.toggleClass('active');
                    if (isActive) {
                        heartIcon.removeClass('far').addClass('fas');
                        textSpan.text('Saved');
                    } else {
                        heartIcon.removeClass('fas').addClass('far');
                        textSpan.text('Save');
                    }
                    console.error('Favorite toggle failed:', data);
                    alert('Failed to update favorites. Please try again.');
                }
            })
            .catch(error => {
                console.error('Error:', error);
                // Revert UI on error
                button.toggleClass('active');
                if (isActive) {
                    heartIcon.removeClass('far').addClass('fas');
                    textSpan.text('Saved');
                } else {
                    heartIcon.removeClass('fas').addClass('far');
                    textSpan.text('Save');
                }
                alert('Failed to update favorites. Please try again.');
            });
        });
    });
    </script>
    <?php
    
    return ob_get_clean();
} 