<?php
/**
 * Single Car Display - Main HTML and PHP Logic
 * Separated from single-car.php for better organization
 */

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

if (have_posts()) :
    while (have_posts()) : the_post();
        $car_id = get_the_ID(); // Get the ID of the current car post

        // Ensure this is actually a 'car' post type, as a safeguard.
        if (get_post_type($car_id) === 'car') :

            // Get all car details (copied from car-listing-detailed.php and adapted)
            $make = get_field('make', $car_id);
            $model = get_field('model', $car_id);
            $variant = get_field('variant', $car_id);
            $year = get_field('year', $car_id);
            $price = get_field('price', $car_id);
            $mileage = get_field('mileage', $car_id);
            $location = get_field('location', $car_id);
            $engine_capacity = get_field('engine_capacity', $car_id);
            $fuel_type = get_field('fuel_type', $car_id);
            $transmission = get_field('transmission', $car_id);
            $exterior_color = get_field('exterior_color', $car_id);
            $interior_color = get_field('interior_color', $car_id);
            $description = get_field('description', $car_id);

            // Get all car images (copied from car-listing-detailed.php)
            $featured_image = get_post_thumbnail_id($car_id);
            $additional_images = get_field('car_images', $car_id); // ACF field
            $all_images = array();
            
            if ($featured_image) {
                $all_images[] = $featured_image;
            }
            
            if (is_array($additional_images)) {
                $all_images = array_merge($all_images, $additional_images);
            }
            ?>

            <div class="car-listing-detailed-container">
                <!-- Add Gallery Popup HTML -->
                <div class="gallery-popup" style="display: none;">
                    <div class="gallery-popup-content">
                        <button class="back-to-advert-btn">
                            <i class="fas fa-arrow-left"></i> Back to advert
                        </button>
                        <div class="gallery-main-image">
                            <?php 
                            // Set the first image as the initial main image
                            $first_image_url = wp_get_attachment_image_url($all_images[0], 'large');
                            ?>
                            <img src="<?php echo esc_url($first_image_url); ?>" alt="Gallery Image">
                        </div>
                        <div class="gallery-thumbnails">
                            <?php foreach ($all_images as $index => $image_id) : 
                                $thumb_url = wp_get_attachment_image_url($image_id, 'thumbnail');
                                $full_url = wp_get_attachment_image_url($image_id, 'large');
                                if ($thumb_url) :
                            ?>
                                <div class="gallery-thumbnail <?php echo $index === 0 ? 'active' : ''; ?>" data-full-url="<?php echo esc_url($full_url); ?>">
                                    <img src="<?php echo esc_url($thumb_url); ?>" alt="Gallery Thumbnail <?php echo $index + 1; ?>">
                                </div>
                            <?php 
                                endif;
                            endforeach; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Report Listing Modal -->
                <div class="report-modal" style="display: none;">
                    <div class="report-modal-content">
                        <div class="report-modal-header">
                            <h3>Report this listing</h3>
                            <button class="close-report-modal">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                        <form id="report-listing-form" method="post">
                            <div class="report-form-group">
                                <label for="report-reason">Reason for reporting:</label>
                                <select id="report-reason" name="report_reason" required>
                                    <option value="">Select a reason</option>
                                    <option value="fake_listing">Fake or fraudulent listing</option>
                                    <option value="inappropriate_content">Inappropriate content</option>
                                    <option value="spam">Spam</option>
                                    <option value="wrong_category">Wrong category</option>
                                    <option value="duplicate">Duplicate listing</option>
                                    <option value="sold_vehicle">Vehicle already sold</option>
                                    <option value="overpriced">Significantly overpriced</option>
                                    <option value="other">Other</option>
                                </select>
                            </div>
                            <div class="report-form-group">
                                <label for="report-details">Additional details (optional):</label>
                                <textarea id="report-details" name="report_details" rows="4" placeholder="Please provide any additional information that would help us review this report..."></textarea>
                            </div>
                            <div class="report-form-group">
                                <label for="reporter-email">Your email (optional):</label>
                                <input type="email" id="reporter-email" name="reporter_email" placeholder="your.email@example.com" value="<?php echo is_user_logged_in() ? esc_attr(wp_get_current_user()->user_email) : ''; ?>">
                                <small>We may contact you if we need more information</small>
                            </div>
                            <input type="hidden" name="reported_listing_id" value="<?php echo esc_attr($car_id); ?>">
                            <input type="hidden" name="action" value="submit_listing_report">
                            <?php wp_nonce_field('report_listing_nonce', 'report_nonce'); ?>
                            <div class="report-form-actions">
                                <button type="button" class="cancel-report-btn">Cancel</button>
                                <button type="submit" class="submit-report-btn">Submit Report</button>
                            </div>
                        </form>
                    </div>
                </div>
                
                <div class="car-listing-header">
                    <a href="javascript:history.back()" class="back-to-results-btn">
                        ← Back to Results
                    </a>
                    <?php
                    // Favorite button logic (copied from car-listing-detailed.php)
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
                        <button class="report-btn" data-car-id="<?php echo esc_attr($car_id); ?>" title="Report this listing">
                            <i class="fas fa-flag"></i>
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
                                        <img src="<?php echo esc_url($main_image_url); ?>" 
                                             alt="<?php echo esc_attr($year . ' ' . $make . ' ' . $model); ?>" 
                                             class="clickable-image"
                                             data-image-index="0">
                                    <?php endif; ?>
                                </div>
                                
                                <?php if (count($all_images) > 1) : ?>
                                    <div class="thumbnail-gallery">
                                        <?php 
                                        // Show up to 3 thumbnails (excluding the main image)
                                        $max_thumbnails = 3;
                                        $num_thumbnails = min(count($all_images) - 1, $max_thumbnails);
                                        for ($i = 1; $i <= $num_thumbnails; $i++) : 
                                            $thumb_url = wp_get_attachment_image_url($all_images[$i], 'medium');
                                            $full_url = wp_get_attachment_image_url($all_images[$i], 'large');
                                            if ($thumb_url) :
                                        ?>
                                            <div class="thumbnail" data-full-url="<?php echo esc_url($full_url); ?>">
                                                <img src="<?php echo esc_url($thumb_url); ?>" 
                                                     alt="Thumbnail <?php echo $i + 1; ?>"
                                                     class="clickable-image"
                                                     data-image-index="<?php echo $i; ?>">
                                            </div>
                                        <?php 
                                            endif;
                                        endfor; ?>
                                    </div>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>

                        <!-- Car Details Right Side -->
                        <div class="car-listing-details-right">
                            <h1 class="car-title"><?php echo esc_html($make . ' ' . $model); ?></h1>

                            <?php if (get_field('is_sold', $car_id)) : ?>
                                <div class="sold-badge">SOLD</div>
                            <?php endif; ?>

                            <div class="car-specs">
                                <?php echo esc_html($engine_capacity); ?>L
                                <?php echo !empty($variant) ? ' ' . esc_html($variant) : ''; ?>
                                <?php 
                                    $body_type = get_field('body_type', $car_id);
                                    echo !empty($body_type) ? ' ' . esc_html($body_type) : '';
                                ?>
                                <?php echo !empty($transmission) ? ' ' . esc_html($transmission) : ''; ?>
                                <?php 
                                    $drive_type = get_field('drive_type', $car_id);
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
                            $publication_date = get_field('publication_date', $car_id);
                            if (!$publication_date) {
                                $publication_date = get_the_date('Y-m-d H:i:s');
                                update_post_meta($car_id, 'publication_date', $publication_date);
                            }
                            $formatted_date = date_i18n('F j, Y', strtotime($publication_date));
                            echo '<div class="car-publication-date">Listed on ' . esc_html($formatted_date) . '</div>';
                            ?>
                            <div class="car-location"><i class="fas fa-map-marker-alt"></i><?php echo esc_html($location); ?></div>
                            <?php 
            // Get the author from post_author field
            $author_id = get_post_field('post_author', $car_id);
                            
                            $author_name = get_the_author_meta('display_name', $author_id);
                            $author_first_name = get_the_author_meta('first_name', $author_id);
                            $author_last_name = get_the_author_meta('last_name', $author_id);
                            $author_email = get_the_author_meta('user_email', $author_id);
                            $full_name = trim($author_first_name . ' ' . $author_last_name);
                            ?>
                            <div class="car-seller">
                                <i class="fas fa-phone"></i>
                                <div class="seller-info">
                                    <span class="seller-name"><?php echo esc_html($author_name); ?></span>
                                    <?php if (!empty($full_name)) : ?>
                                        <span class="seller-full-name"><?php echo esc_html($full_name); ?></span>
                                    <?php endif; ?>
                                    <span class="seller-email">
                                        <i class="fas fa-envelope"></i>
                                        <?php echo esc_html($author_email); ?>
                                    </span>
                                </div>
                            </div>
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
                                    <span class="detail-value"><?php echo esc_html($location); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Availability:</span>
                                    <span class="detail-value"><?php echo esc_html(get_field('availability', $car_id)); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">MOT Status:</span>
                                    <span class="detail-value"><?php 
                                        $mot_status = get_field('motuntil', $car_id);
                                        if ($mot_status === 'Expired') {
                                            echo 'Expired';
                                        } elseif (!empty($mot_status)) {
                                            echo date_i18n('F Y', strtotime($mot_status . '-01'));
                                        } else {
                                            echo 'Not specified';
                                        }
                                    ?></span>
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
                                <div class="detail-item">
                                    <span class="detail-label">Number of Doors:</span>
                                    <span class="detail-value"><?php echo esc_html(get_field('number_of_doors', $car_id)); ?></span>
                                </div>
                                <div class="detail-item">
                                    <span class="detail-label">Number of Seats:</span>
                                    <span class="detail-value"><?php echo esc_html(get_field('number_of_seats', $car_id)); ?></span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Specs and Features Section -->
                    <div class="car-listing-details">
                        <div class="details-section">
                            <button class="specs-features-toggle">
                                View Specs and Features <i class="fas fa-chevron-right"></i>
                            </button>
                            <div class="specs-features-content" style="display: none;">
                                <div class="extras-grid">
                                    <?php
                                    $extras = get_field('extras', $car_id);
                                    if (!empty($extras) && is_array($extras)) {
                                        foreach ($extras as $extra) {
                                            // Get the label from the ACF field
                                            $field = get_field_object('extras', $car_id);
                                            $label = '';
                                            if ($field && isset($field['choices'][$extra])) {
                                                $label = $field['choices'][$extra];
                                            }
                                            echo '<div class="extra-item">';
                                            echo '<i class="fas fa-check"></i>';
                                            echo '<span>' . esc_html($label ? $label : $extra) . '</span>';
                                            echo '</div>';
                                        }
                                    } else {
                                        echo '<p>No additional features specified.</p>';
                                    }
                                    ?>
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
                                // Description truncation logic (copied from car-listing-detailed.php)
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
                                <p class="description-text"><?php echo wp_kses_post($truncated_description); ?></p>
                                <?php if ($show_read_more): ?>
                                    <p class="full-description" style="display: none;"><?php echo wp_kses_post($description); ?></p>
                                    <button class="read-more-btn">Read more</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <?php

        else:
            // If for some reason a non-car post is accessed via this template.
            echo '<p class="error-message">This content is not a car listing.</p>';
        endif; // End check for 'car' post type

    endwhile;
else :
    // If no post is found (standard WordPress Loop practice)
    get_template_part('template-parts/content', 'none'); // Or your theme's way of showing "not found"
endif;
?> 