<?php
/**
 * Favorites Toggle Button Shortcode [favorites_toggle_button]
 * Exact replica of the favorites button from single-car.php
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

    // Check if user has favorited this car - EXACT same logic as single-car.php
    $user_id = get_current_user_id();
    $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
    $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
    $is_favorite = $user_id ? in_array($car_id, $favorite_cars) : false;
    $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
    $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart';

    ob_start();
    ?>
    <div class="car-listing-detailed-container">
        <div class="action-buttons">
            <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>">
                <i class="<?php echo esc_attr($heart_class); ?>"></i>
            </button>
            <button class="share-btn">
                <i class="fas fa-share-alt"></i>
            </button>
            <button class="report-btn" data-car-id="<?php echo esc_attr($car_id); ?>" title="Report this listing">
                <i class="fas fa-flag"></i>
            </button>
        </div>
    </div>

    <style>
    /* EXACT CSS from single-car.php */
    .car-listing-detailed-container .action-buttons .favorite-btn:hover,
    .car-listing-detailed-container .action-buttons .share-btn:hover {
        background: #f0f0f0 !important;
        border-color: #007bff !important;
    }

    .car-listing-detailed-container .action-buttons .favorite-btn i,
    .car-listing-detailed-container .action-buttons .share-btn i {
        font-size: 18px !important;
        color: #333 !important;
    }

    .car-listing-detailed-container .action-buttons .favorite-btn.active i {
        color: #ff0000 !important;
    }

    /* Override favorite button position for detailed page - needs to be more specific */
    .car-listing-detailed-container .action-buttons .favorite-btn {
        position: static !important; /* Reset position from car-listings.css absolute positioning */
        top: auto !important;
        right: auto !important;
        background: rgba(255, 255, 255, 0.9) !important;
        border: 1px solid #ddd !important;
        padding: 10px !important;
        width: auto !important;
        height: auto !important;
        border-radius: 4px !important;
        min-width: 40px !important;
        min-height: 40px !important;
    }
    
    .car-listing-detailed-container .action-buttons {
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    .car-listing-detailed-container .action-buttons .share-btn,
    .car-listing-detailed-container .action-buttons .report-btn {
        position: static !important;
        background: rgba(255, 255, 255, 0.9) !important;
        border: 1px solid #ddd !important;
        padding: 10px !important;
        border-radius: 4px !important;
        min-width: 40px !important;
        min-height: 40px !important;
        cursor: pointer;
    }
    </style>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // EXACT JavaScript from single-car.php
        const favoriteBtn = document.querySelector('.favorite-btn');
        if (favoriteBtn) {
            favoriteBtn.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();

                if (typeof carListingsData === 'undefined' || typeof carListingsData.ajaxurl === 'undefined' || typeof carListingsData.nonce === 'undefined') {
                    alert('Please log in to add favorites. (Error: Script data missing)');
                    return;
                }

                const carId = this.getAttribute('data-car-id');
                const isActive = this.classList.contains('active');
                const heartIcon = this.querySelector('i');

                this.classList.toggle('active');
                if (isActive) {
                    heartIcon.classList.remove('fas');
                    heartIcon.classList.add('far');
                } else {
                    heartIcon.classList.remove('far');
                    heartIcon.classList.add('fas');
                }

                const formData = new FormData();
                formData.append('action', 'toggle_favorite_car');
                formData.append('car_id', carId);
                formData.append('is_favorite', !isActive ? '1' : '0');
                formData.append('nonce', carListingsData.nonce);

                fetch(carListingsData.ajaxurl, {
                    method: 'POST',
                    body: formData,
                    credentials: 'same-origin',
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Network response was not ok.');
                    }
                    return response.json();
                })
                .then(data => {
                    if (!data.success) {
                        this.classList.toggle('active');
                        if (isActive) {
                            heartIcon.classList.remove('far');
                            heartIcon.classList.add('fas');
                        } else {
                            heartIcon.classList.remove('fas');
                            heartIcon.classList.add('far');
                        }
                        console.error('Favorite toggle failed:', data);
                        alert('Failed to update favorites. Please try again.');
                    }
                })
                .catch(error => {
                    this.classList.toggle('active');
                    if (isActive) {
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                    } else {
                        heartIcon.classList.remove('fas');
                        heartIcon.classList.add('far');
                    }
                    console.error('Error:', error);
                    alert('Failed to update favorites. An error occurred.');
                });
            });
        }

        const toggleBtn = document.querySelector('.specs-features-toggle');
        const content = document.querySelector('.specs-features-content');
        
        if (toggleBtn && content) {
            toggleBtn.addEventListener('click', function() {
                const isHidden = content.style.display === 'none';
                content.style.display = isHidden ? 'block' : 'none';
                toggleBtn.classList.toggle('active');
            });
        }
    });
    </script>
    <?php
    
    return ob_get_clean();
} 