<?php
/**
 * Template Name: Create Buyer Request
 *
 * @package Bricks Child
 * @since 1.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit; // Exit if accessed directly.
}

// If the user is not logged in, redirect to login
if ( ! is_user_logged_in() ) {
    $login_url = add_query_arg(
        'redirect_to',
        urlencode( get_permalink() ),
        wp_login_url()
    );
    wp_safe_redirect( $login_url );
    exit;
}

// Include filter base functions for custom dropdown rendering
require_once get_stylesheet_directory() . '/includes/shortcodes/car-filters/filters/filter-base.php';

// Get all makes (parent terms) from car_make taxonomy for the custom dropdown
$make_terms = get_terms(array(
    'taxonomy' => 'car_make',
    'hide_empty' => false,
    'parent' => 0,
    'orderby' => 'name',
    'order' => 'ASC'
));

// Format makes for the custom dropdown
$buyer_request_make_options = array();
if (!is_wp_error($make_terms) && !empty($make_terms)) {
    foreach ($make_terms as $make_term) {
        $buyer_request_make_options[] = array(
            'value'   => $make_term->name,
            'label'   => $make_term->name,
            'slug'    => $make_term->slug,
            'term_id' => $make_term->term_id,
        );
    }
}

// Ensure jQuery is loaded
wp_enqueue_script('jquery');

// Enqueue car-filters CSS for custom dropdown styling
wp_enqueue_style(
    'car-filters-css',
    get_stylesheet_directory_uri() . '/includes/shortcodes/car-filters/car-filters.css',
    array(),
    filemtime(get_stylesheet_directory() . '/includes/shortcodes/car-filters/car-filters.css')
);

// Enqueue buyer request form styles
wp_enqueue_style(
    'buyer-request-form-css',
    get_stylesheet_directory_uri() . '/includes/user-manage-listings/buyer-request-form.css',
    array('car-filters-css'),
    filemtime(get_stylesheet_directory() . '/includes/user-manage-listings/buyer-request-form.css')
);

get_header(); ?>

<div class="bricks-container">
    <div class="bricks-content">
        <?php
        // Display success message
        if ( isset( $_GET['request_submitted'] ) && $_GET['request_submitted'] == 'success' ) {
            ?>
            <div class="buyer-request-success-message">
                <h2><?php esc_html_e( 'Your buyer request has been submitted successfully!', 'bricks-child' ); ?></h2>
                <p><?php esc_html_e( 'Thank you for submitting your buyer request. It is now visible to all sellers.', 'bricks-child' ); ?></p>
                <div class="buyer-request-success-buttons">
                    <a href="<?php echo esc_url( home_url( '/buyer-requests' ) ); ?>" class="btn btn-primary">
                        <?php esc_html_e( 'View All Requests', 'bricks-child' ); ?>
                    </a>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="btn btn-secondary">
                        <?php esc_html_e( 'Return to Home', 'bricks-child' ); ?>
                    </a>
                </div>
            </div>
            <?php
        } else {
            // Display error messages if any
            if ( isset( $_GET['error'] ) && ! empty( $_GET['error'] ) ) {
                $error_messages = array();
                
                if ( $_GET['error'] === 'validation' ) {
                    $error_messages[] = esc_html__( 'Please fill in all required fields', 'bricks-child' );
                    
                    if ( isset( $_GET['fields'] ) && ! empty( $_GET['fields'] ) ) {
                        $missing_fields = explode( ',', sanitize_text_field( $_GET['fields'] ) );
                        echo '<div class="form-error-message">';
                        echo '<p>' . esc_html__( 'The following fields are required:', 'bricks-child' ) . '</p>';
                        echo '<ul>';
                        foreach ( $missing_fields as $field ) {
                            $field_label = str_replace( 'buyer_', '', $field );
                            $field_label = str_replace( '_', ' ', $field_label );
                            echo '<li>' . esc_html( ucfirst( $field_label ) ) . '</li>';
                        }
                        echo '</ul>';
                        echo '</div>';
                    }
                } elseif ( $_GET['error'] === 'invalid_year' ) {
                    echo '<div class="form-error-message">';
                    echo '<p>' . esc_html__( 'Please enter a valid year between 1900 and ' . ( date( 'Y' ) + 1 ), 'bricks-child' ) . '</p>';
                    echo '</div>';
                } elseif ( $_GET['error'] === 'invalid_price' ) {
                    echo '<div class="form-error-message">';
                    echo '<p>' . esc_html__( 'Please enter a valid price greater than 0.', 'bricks-child' ) . '</p>';
                    echo '</div>';
                } elseif ( $_GET['error'] === 'post_creation' ) {
                    echo '<div class="form-error-message">';
                    echo '<p>' . esc_html__( 'We encountered an issue creating your request. Please try again.', 'bricks-child' ) . '</p>';
                    echo '</div>';
                } elseif ( $_GET['error'] === 'not_logged_in' ) {
                    echo '<div class="form-error-message">';
                    echo '<p>' . esc_html__( 'You must be logged in to create a buyer request.', 'bricks-child' ) . '</p>';
                    echo '</div>';
                }
            }
            
            ?>
            <h1><?php esc_html_e( 'Create Your Buyer Request', 'bricks-child' ); ?></h1>
            <p class="buyer-request-note">
                <?php esc_html_e( 'Tell sellers what car you\'re looking for. Fill in the details below to create your buyer request.', 'bricks-child' ); ?>
            </p>
            
            <form id="create-buyer-request-form" class="buyer-request-form" method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
                <?php wp_nonce_field( 'add_buyer_request_nonce', 'add_buyer_request_nonce' ); ?>
                <input type="hidden" name="action" value="add_new_buyer_request">
                
                <div class="form-section input-wrapper">
                    <h2><?php echo get_svg_icon('circle-info'); ?> <?php esc_html_e( 'Car Details', 'bricks-child' ); ?></h2>
                    
                    <div class="form-row form-row-halves">
                        <div class="form-half">
                            <label><?php echo get_svg_icon('car-side'); ?> <?php esc_html_e( 'Make', 'bricks-child' ); ?><span class="required">*</span></label>
                            <?php
                            car_filter_render_dropdown(array(
                                'id'          => 'buyer-request-make',
                                'name'        => 'buyer_make',
                                'placeholder' => __('Select Make', 'bricks-child'),
                                'options'     => $buyer_request_make_options,
                                'selected'    => '',
                                'show_count'  => false,
                                'searchable'  => true,
                                'data_attrs'  => array(
                                    'filter-type' => 'make',
                                ),
                            ));
                            ?>
                        </div>
                        
                        <div class="form-half">
                            <label for="buyer_model"><?php echo get_svg_icon('car'); ?> <?php esc_html_e( 'Model', 'bricks-child' ); ?> <span class="optional">(<?php esc_html_e( 'Optional', 'bricks-child' ); ?>)</span></label>
                            <input type="text" id="buyer_model" name="buyer_model" class="form-control" placeholder="<?php esc_attr_e( 'E.g. 5 Series, A4, C-Class', 'bricks-child' ); ?>">
                        </div>
                    </div>
                    
                    <div class="form-row form-row-halves">
                        <div class="form-half">
                            <label for="buyer_year"><?php echo get_svg_icon('calendar'); ?> <?php esc_html_e( 'Year', 'bricks-child' ); ?><span class="required">*</span></label>
                            <?php
                            $year_options = array();
                            $current_year = (int) date('Y');
                            for ($year = $current_year + 1; $year >= 1900; $year--) {
                                $year_options[] = array('value' => $year, 'label' => $year);
                            }
                            car_filter_render_dropdown(array(
                                'id'          => 'buyer-request-year',
                                'name'        => 'buyer_year',
                                'placeholder' => __('Select Year', 'bricks-child'),
                                'options'     => $year_options,
                                'selected'    => '',
                                'show_count'  => false,
                                'searchable'  => true,
                            ));
                            ?>
                        </div>
                        
                        <div class="form-half">
                            <label for="buyer_price"><?php echo get_svg_icon('euro-sign'); ?> <?php esc_html_e( 'Maximum Price', 'bricks-child' ); ?><span class="required">*</span></label>
                            <div class="input-with-suffix">
                                <input type="number" id="buyer_price" name="buyer_price" class="form-control" min="1" step="1" placeholder="<?php esc_attr_e( 'E.g. 10000', 'bricks-child' ); ?>" required>
                                <span class="input-suffix">EUR</span>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="form-section input-wrapper">
                    <h2><?php echo get_svg_icon('align-left'); ?> <?php esc_html_e( 'Description', 'bricks-child' ); ?> <span class="optional">(<?php esc_html_e( 'Optional', 'bricks-child' ); ?>)</span></h2>
                    <p class="description-guidelines">
                        <?php esc_html_e( 'Add any additional details about what you\'re looking for (condition, mileage, features, etc.)', 'bricks-child' ); ?>
                    </p>
                    
                    <div class="form-row">
                        <?php
                        // Use WYSIWYG editor for description
                        $content = '';
                        $editor_id = 'buyer_description';
                        $settings = array(
                            'textarea_name' => 'buyer_description',
                            'textarea_rows' => 8,
                            'media_buttons' => false,
                            'teeny' => true,
                            'quicktags' => false,
                        );
                        wp_editor( $content, $editor_id, $settings );
                        ?>
                    </div>
                </div>
                
                <div class="form-row">
                    <button type="submit" class="btn btn-primary-gradient btn-lg">
                        <?php esc_html_e( 'Submit Request', 'bricks-child' ); ?>
                    </button>
                </div>
            </form>
            <?php
        }
        ?>
    </div>
</div>

<?php get_footer(); ?>

