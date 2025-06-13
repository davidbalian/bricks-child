<?php
    /**
     * Detailed Car Listing Shortcode
     * 
     * @package Astra Child
     * @since 1.0.0
     */

    // Register the shortcode
    add_shortcode('car_listing_detailed', 'display_car_listing_detailed');

    function display_car_listing_detailed($atts) {
        // Start output buffering
        ob_start();

        // Get the car ID from the URL parameter
        $car_id = isset($_GET['car_id']) ? intval($_GET['car_id']) : 0;

        if (!$car_id) {
            return '<p class="error-message">No car listing specified.</p>';
        }

        // Get the car post
        $car = get_post($car_id);

        if (!$car || $car->post_type !== 'car') {
            return '<p class="error-message">Car listing not found.</p>';
        }

        // Get all car details
        $make = get_field('make', $car_id);
        $model = get_field('model', $car_id);
        $variant = get_field('variant', $car_id);
        $year = get_field('year', $car_id);
        $price = get_field('price', $car_id);
        $mileage = get_field('mileage', $car_id);
        $detail_car_city = get_field('car_city', $car_id);
        $detail_car_district = get_field('car_district', $car_id);
        $detail_display_location = '';
        if (!empty($detail_car_city) && !empty($detail_car_district)) {
            $detail_display_location = $detail_car_city . ' - ' . $detail_car_district;
        } elseif (!empty($detail_car_city)) {
            $detail_display_location = $detail_car_city;
        } elseif (!empty($detail_car_district)) {
            $detail_display_location = $detail_car_district;
        }
        $full_address_for_grid = get_field('car_address', $car_id);
        $engine_capacity = get_field('engine_capacity', $car_id);
        $fuel_type = get_field('fuel_type', $car_id);
        $transmission = get_field('transmission', $car_id);
        $exterior_color = get_field('exterior_color', $car_id);
        $interior_color = get_field('interior_color', $car_id);
        $description = get_field('description', $car_id);
        $body_type = get_field('body_type', $car_id);
        $drive_type = get_field('drive_type', $car_id);
        $number_of_doors = get_field('number_of_doors', $car_id);
        $number_of_seats = get_field('number_of_seats', $car_id);
        $motuntil = get_field('motuntil', $car_id);
        $extras = get_field('extras', $car_id);
        $vehiclehistory = get_field('vehiclehistory', $car_id);
        $publication_date = get_field('publication_date', $car_id);

        // Get all car images
        $featured_image = get_post_thumbnail_id($car_id);
        $additional_images = get_field('car_images', $car_id);
        $all_images = array();
        
        if ($featured_image) {
            $all_images[] = $featured_image;
        }
        
        if (is_array($additional_images)) {
            $all_images = array_merge($all_images, $additional_images);
        }
        ?>

        <div class="car-listing-detailed-container">
            <div class="car-listing-header">
                <a href="javascript:history.back()" class="back-to-results-btn">
                    ← Back to Results
                </a>
                <?php
                // Check if user has favorited this car
                $user_id = get_current_user_id();
                $favorite_cars = $user_id ? get_user_meta($user_id, 'favorite_cars', true) : array();
                $favorite_cars = is_array($favorite_cars) ? $favorite_cars : array();
                $is_favorite = $user_id ? in_array($car_id, $favorite_cars) : false;
                $button_class = $is_favorite ? 'favorite-btn active' : 'favorite-btn';
                $heart_class = $is_favorite ? 'fas fa-heart' : 'far fa-heart'; 
                ?>
                <div class="action-buttons">
                    <button class="<?php echo esc_attr($button_class); ?>" data-car-id="<?php echo esc_attr($car_id); ?>">
                        <i class="<?php echo esc_attr($heart_class); ?>"></i>
                    </button>
                    <button class="share-btn">
                        <i class="fas fa-share-alt"></i>
                    </button>
                </div>
            </div>
            <div class="car-listing-content">
                <div class="car-listing-top">
                    <!-- Image Gallery -->
                    <?php if (!empty($all_images)) : ?>
                        <div class="car-listing-gallery">
                            <div class="main-image">
                                <div class="image-count-overlay">
                                    <i class="fas fa-camera"></i>
                                    <span><?php echo count($all_images); ?> photos</span>
                                </div>
                                <button class="view-gallery-btn">
                                    <i class="fas fa-images"></i>
                                    View Gallery
                                </button>
                                <?php
                                $main_image_url = wp_get_attachment_image_url($all_images[0], 'large');
                                if ($main_image_url) :
                                ?>
                                    <img src="<?php echo esc_url($main_image_url); ?>" alt="<?php echo esc_attr($year . ' ' . $make . ' ' . $model); ?>" data-full-url="<?php echo esc_url($main_image_url); ?>">
                                <?php endif; ?>
                            </div>
                            
                            <?php if (count($all_images) > 1) : ?>
                                <div class="thumbnail-gallery">
                                    <?php foreach ($all_images as $index => $image_id) : 
                                        $thumb_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                        $full_url = wp_get_attachment_image_url($image_id, 'large');
                                        if ($thumb_url) :
                                    ?>
                                        <div class="thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-full-url="<?php echo esc_url($full_url); ?>">
                                            <img src="<?php echo esc_url($thumb_url); ?>" alt="Thumbnail <?php echo $index + 1; ?>">
                                        </div>
                                    <?php 
                                        endif;
                                    endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Car Details Right Side -->
                    <div class="car-listing-details-right">
                        <h1 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h1>

                        <?php if (get_field('car_status', $car_id) === 'sold') : ?>
                            <div class="sold-badge">SOLD</div>
                        <?php endif; ?>

                        <div class="car-specs">
                            <?php echo esc_html($engine_capacity); ?>L
                            <?php echo !empty($variant) ? ' ' . esc_html($variant) : ''; ?>
                            <?php 
                                echo !empty($body_type) ? ' ' . esc_html($body_type) : '';
                            ?>
                            <?php echo !empty($transmission) ? ' ' . esc_html($transmission) : ''; ?>
                            <?php 
                                echo !empty($drive_type) ? ' ' . esc_html($drive_type) : '';
                            ?>
                        </div>

                        <div class="car-info-boxes">
                            <div class="info-box">
                                <span class="info-value"><?php echo number_format($mileage); ?> km</span>
                            </div>
                            <div class="info-box">
                                <span class="info-value"><?php echo esc_html($year); ?></span>
                            </div>
                            <div class="info-box">
                                <span class="info-value"><?php echo esc_html($transmission); ?></span>
                            </div>
                            <div class="info-box">
                                <span class="info-value"><?php echo esc_html($fuel_type); ?></span>
                            </div>
                        </div>

                        <div class="car-price">€<?php echo number_format($price); ?></div>
                        <?php 
                        if (!$publication_date) {
                            $publication_date = get_the_date('Y-m-d H:i:s');
                            update_post_meta($car_id, 'publication_date', $publication_date);
                        }
                        $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                        echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                        ?>
                        <div class="car-location"><i class="fas fa-map-marker-alt"></i><?php echo esc_html($detail_display_location); ?></div>
                    </div>
                </div>

                <!-- Vehicle Information -->
                <div class="car-listing-details">
                    <div class="details-section">
                        <h2>Vehicle Information</h2>
                        <div class="details-grid">
                            <div class="detail-item">
                                <span class="detail-label">Make:</span>
                                <span class="detail-value"><?php echo esc_html($make); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Model:</span>
                                <span class="detail-value"><?php echo esc_html($model); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Variant:</span>
                                <span class="detail-value"><?php echo esc_html($variant); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Year:</span>
                                <span class="detail-value"><?php echo esc_html($year); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Mileage:</span>
                                <span class="detail-value"><?php echo number_format($mileage); ?> km</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Location:</span>
                                <span class="detail-value"><?php echo esc_html($full_address_for_grid); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Engine Capacity:</span>
                                <span class="detail-value"><?php echo esc_html($engine_capacity); ?>L</span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Fuel Type:</span>
                                <span class="detail-value"><?php echo esc_html($fuel_type); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Transmission:</span>
                                <span class="detail-value"><?php echo esc_html($transmission); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Exterior Color:</span>
                                <span class="detail-value"><?php echo esc_html($exterior_color); ?></span>
                            </div>
                            <div class="detail-item">
                                <span class="detail-label">Interior Color:</span>
                                <span class="detail-value"><?php echo esc_html($interior_color); ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description Section -->
                <div class="car-listing-details">
                    <div class="details-section">
                        <h2>Description</h2>
                        <div class="description-content">
                            <?php 
                            $description_length = strlen($description);
                            $max_length = 360;
                            $truncated_description = $description;
                            $show_read_more = false;

                            if ($description_length > $max_length) {
                                $truncated_description = substr($description, 0, $max_length);
                                $last_space = strrpos($truncated_description, ' ');
                                if ($last_space !== false) {
                                    $truncated_description = substr($truncated_description, 0, $last_space);
                                }
                                $truncated_description .= '...';
                                $show_read_more = true;
                            }
                            ?>
                            <p class="description-text"><?php echo esc_html($truncated_description); ?></p>
                            <?php if ($show_read_more): ?>
                                <p class="full-description" style="display: none;"><?php echo esc_html($description); ?></p>
                                <button class="read-more-btn">Read more</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <style>
            .car-listing-detailed-container {
                max-width: 1200px;
                margin: 0 auto;
                padding: 20px;
            }

            .car-listing-header {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin-bottom: 20px;
            }

            .car-listing-header h1 {
                margin: 0 0 10px 0;
                font-size: 2em;
            }

            .car-listing-price {
                font-size: 1.8em;
                font-weight: bold;
                color: #007bff;
            }

            .car-listing-content {
                display: flex;
                flex-direction: column;
                gap: 30px;
            }

            .car-listing-top {
                display: grid;
                grid-template-columns: 1fr 1fr;
                gap: 30px;
            }

            .car-listing-gallery {
                width: 100%;
            }

            .main-image {
                margin-bottom: 15px;
                position: relative;
            }

            .image-count-overlay {
                position: absolute;
                top: 15px;
                left: 15px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                padding: 8px 12px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 8px;
                z-index: 2;
            }

            .image-count-overlay i {
                font-size: 16px;
            }

            .image-count-overlay span {
                font-size: 14px;
                font-weight: 500;
            }

            .main-image img {
                width: 100%;
                height: auto;
                border-radius: 8px;
            }

            .thumbnail-gallery {
                display: flex;
                gap: 10px;
                overflow-x: auto;
                padding-bottom: 10px;
                width: 100%;
                justify-content: space-between;
            }

            .thumbnail {
                width: 180px;
                height: 135px;
                cursor: pointer;
                border: 2px solid transparent;
                border-radius: 4px;
                overflow: hidden;
                flex-shrink: 0;
            }

            .thumbnail.active {
                border-color: #007bff;
            }

            .thumbnail img {
                width: 100%;
                height: 100%;
                object-fit: cover;
            }

            .car-listing-details-right {
                padding: 20px;
                border-radius: 8px;
            }

            .car-title {
                margin: 0 0 15px 0;
                font-size: 2em;
                color: #333;
            }

            .description-content {
                margin-bottom: 20px;
                line-height: 1.6;
                color: #333;
            }

            .description-text {
                margin-bottom: 10px;
            }

            .car-specs {
                font-size: 1.1em;
                color: #666;
                margin-bottom: 15px;
            }

            .car-info-boxes {
                display: flex;
                gap: 10px;
                margin-bottom: 15px;
            }

            .info-box {
                flex: 1;
                background: #fff;
                border: 1px solid #ddd;
                border-radius: 4px;
                padding: 8px 12px;
                text-align: center;
            }

            .info-value {
                display: block;
                font-size: 0.95em;
                color: #333;
                font-weight: 500;
            }

            .car-price {
                font-size: 1.8em;
                font-weight: bold;
                color: #007bff;
                margin-bottom: 10px;
            }

            .car-location {
                color: #666;
                font-size: 1.1em;
            }

            .car-publication-date {
                color: #666;
                font-size: 1.1em;
                margin-bottom: 10px;
            }

            .car-listing-details {
                background: #f8f9fa;
                padding: 20px;
                border-radius: 8px;
            }

            .details-section {
                margin-bottom: 30px;
            }

            .details-section h2 {
                margin: 0 0 20px 0;
                font-size: 1.5em;
                color: #333;
            }

            .details-grid {
                display: grid;
                grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
                gap: 15px;
            }

            .detail-item {
                display: flex;
                flex-direction: column;
                gap: 5px;
            }

            .detail-label {
                font-weight: bold;
                color: #666;
            }

            .detail-value {
                color: #333;
            }

            @media (max-width: 768px) {
                .car-listing-top {
                    grid-template-columns: 1fr;
                }
            }

            .view-gallery-btn {
                position: absolute;
                bottom: 15px;
                right: 15px;
                background: white;
                color: #000;
                border: none;
                padding: 8px 16px;
                border-radius: 4px;
                display: flex;
                align-items: center;
                gap: 8px;
                cursor: pointer;
                font-size: 14px;
                font-weight: 500;
                transition: background-color 0.2s ease;
                z-index: 2;
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
            }

            .view-gallery-btn:hover {
                background: #e0e0e0;
            }

            .view-gallery-btn i,
            .view-gallery-btn span {
                color: #000;
            }

            .back-to-results-btn {
                display: inline-block;
                color: #007bff;
                text-decoration: none;
                margin-bottom: 20px;
                font-size: 14px;
                font-weight: 500;
            }

            .back-to-results-btn:hover {
                text-decoration: underline;
            }

            .action-buttons {
                display: flex;
                gap: 10px;
            }

            /* Override favorite button position for detailed page */
            .favorite-btn {
                position: static; /* Reset position */
            }

            .favorite-btn, .share-btn {
                background: none;
                border: none;
                cursor: pointer;
                padding: 8px;
                border-radius: 4px;
                transition: all 0.3s ease;
            }

            .favorite-btn:hover, .share-btn:hover {
                background: #f0f0f0;
            }

            .favorite-btn i, .share-btn i {
                font-size: 18px;
                color: #333;
            }

            .favorite-btn.active i {
                color: #ff0000;
            }

            .read-more-btn {
                background: none;
                border: none;
                color: #007bff;
                cursor: pointer;
                padding: 0;
                font-size: 14px;
                font-weight: 500;
            }

            .read-more-btn:hover {
                text-decoration: underline;
            }

            .sold-badge {
                display: inline-block;
                background-color: #dc3545;
                color: white;
                padding: 8px 16px;
                border-radius: 4px;
                font-size: 16px;
                font-weight: bold;
                margin: 10px 0;
            }
        </style>

        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const thumbnails = document.querySelectorAll('.thumbnail');
            const mainImage = document.querySelector('.main-image img');
            
            // Preload all images
            const preloadImages = () => {
                thumbnails.forEach(thumb => {
                    const img = new Image();
                    img.src = thumb.dataset.fullUrl;
                });
            };
            
            preloadImages();

            thumbnails.forEach(thumb => {
                thumb.addEventListener('click', function() {
                    // Remove active class from all thumbnails
                    thumbnails.forEach(t => t.classList.remove('active'));
                    
                    // Add active class to clicked thumbnail
                    this.classList.add('active');
                    
                    // Update main image
                    const newImageUrl = this.dataset.fullUrl;
                    if (newImageUrl && mainImage) {
                        mainImage.src = newImageUrl;
                    }
                });
            });

            // Read more functionality
            const readMoreBtn = document.querySelector('.read-more-btn');
            if (readMoreBtn) {
                readMoreBtn.addEventListener('click', function() {
                    const descriptionText = document.querySelector('.description-text');
                    const fullDescription = document.querySelector('.full-description');
                    
                    if (descriptionText && fullDescription) {
                        if (descriptionText.style.display === 'none') {
                            descriptionText.style.display = 'block';
                            fullDescription.style.display = 'none';
                            this.textContent = 'Read more';
                        } else {
                            descriptionText.style.display = 'none';
                            fullDescription.style.display = 'block';
                            this.textContent = 'Show less';
                        }
                    }
                });
            }

            // --- Favorite button functionality ---
            const favoriteBtn = document.querySelector('.favorite-btn');
            if (favoriteBtn) {
                favoriteBtn.addEventListener('click', function(e) {
                    e.preventDefault();
                    e.stopPropagation();

                    // Check if user is logged in (nonce should be available)
                    // Use the localized data object
                    if (typeof carListingsData === 'undefined' || typeof carListingsData.ajaxurl === 'undefined' || typeof carListingsData.nonce === 'undefined') {
                        // Maybe redirect to login or show a message
                        alert('Please log in to add favorites.');
                        return;
                    }

                    const carId = this.getAttribute('data-car-id');
                    const isActive = this.classList.contains('active');
                    const heartIcon = this.querySelector('i');

                    // Optimistic UI update
                    this.classList.toggle('active');
                    if (isActive) {
                        heartIcon.classList.remove('fas');
                        heartIcon.classList.add('far');
                    } else {
                        heartIcon.classList.remove('far');
                        heartIcon.classList.add('fas');
                    }

                    // Prepare AJAX data
                    const formData = new FormData();
                    formData.append('action', 'toggle_favorite_car');
                    formData.append('car_id', carId);
                    formData.append('is_favorite', !isActive ? '1' : '0');
                    formData.append('nonce', carListingsData.nonce); // Use the correct nonce variable

                    // Send AJAX request
                    fetch(carListingsData.ajaxurl, { // Use the correct ajaxurl variable
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin', // Important for logged-in AJAX
                    })
                    .then(response => {
                        if (!response.ok) {
                            throw new Error('Network response was not ok.');
                        }
                        return response.json();
                    })
                    .then(data => {
                        if (!data.success) {
                            // Revert UI on failure
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
                        } else {
                            // Optional: Show success feedback if needed
                            console.log('Favorite status updated successfully.');
                        }
                    })
                    .catch(error => {
                        // Revert UI on network/fetch error
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
        });
        </script>
        <?php

        // Return the buffered content
        return ob_get_clean();
    }