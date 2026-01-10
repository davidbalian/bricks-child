<?php
/**
 * My Listings AJAX Handler
 *
 * Handles AJAX requests for loading a user's car listings on the
 * "My Listings" account page.
 *
 * @package Bricks Child
 * @since 1.0.0
 */

// If this file is called directly, abort.
if (!defined('WPINC')) {
    die;
}

/**
 * Class MyListingsAjaxHandler
 *
 * Single responsibility:
 * - Build the query for a user's listings based on simple filters
 * - Render listing items into HTML for AJAX responses
 */
class MyListingsAjaxHandler {

    /**
     * AJAX action name for loading listings
     *
     * @var string
     */
    const AJAX_ACTION = 'my_listings_load';

    /**
     * Nonce action name for loading listings
     *
     * @var string
     */
    const NONCE_ACTION = 'my_listings_load_nonce';

    /**
     * Default number of listings per page
     *
     * @var int
     */
    const DEFAULT_PER_PAGE = 10;

    /**
     * Register AJAX hooks
     *
     * @return void
     */
    public static function register(): void {
        add_action('wp_ajax_' . self::AJAX_ACTION, array(__CLASS__, 'handle_ajax_request'));
    }

    /**
     * Create nonce for frontend use
     *
     * @return string
     */
    public static function create_nonce(): string {
        return wp_create_nonce(self::NONCE_ACTION);
    }

    /**
     * Get AJAX action name
     *
     * @return string
     */
    public static function get_ajax_action(): string {
        return self::AJAX_ACTION;
    }

    /**
     * Handle AJAX request for loading listings
     *
     * @return void
     */
    public static function handle_ajax_request(): void {
        // Verify nonce
        if (
            !isset($_POST['nonce']) ||
            !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['nonce'])), self::NONCE_ACTION)
        ) {
            wp_send_json_error(array('message' => 'Invalid security token.'));
        }

        // Ensure user is logged in
        if (!is_user_logged_in()) {
            wp_send_json_error(array('message' => 'You must be logged in to view listings.'));
        }

        $current_user_id = get_current_user_id();

        // Pagination and filters
        $page = isset($_POST['page']) ? max(1, intval($_POST['page'])) : 1;
        $per_page = isset($_POST['per_page']) ? intval($_POST['per_page']) : self::DEFAULT_PER_PAGE;
        if ($per_page <= 0) {
            $per_page = self::DEFAULT_PER_PAGE;
        }

        $status = isset($_POST['status']) ? sanitize_text_field(wp_unslash($_POST['status'])) : 'all';
        $sort = isset($_POST['sort']) ? sanitize_text_field(wp_unslash($_POST['sort'])) : 'newest';
        $search = isset($_POST['search']) ? sanitize_text_field(wp_unslash($_POST['search'])) : '';

        $query_args = self::build_query_args(
            $current_user_id,
            $status,
            $sort,
            $search,
            $per_page,
            $page
        );

        $user_listings = new WP_Query($query_args);

        // Prepare refresh UI for reuse of existing markup
        $refresh_manager = new RefreshListingManager();
        $refresh_ui = new RefreshListingUI($refresh_manager);

        ob_start();

        if ($user_listings->have_posts()) {
            while ($user_listings->have_posts()) {
                $user_listings->the_post();
                self::render_listing_item(get_the_ID(), $refresh_ui);
            }
        }

        $html = ob_get_clean();
        wp_reset_postdata();

        wp_send_json_success(array(
            'html'         => $html,
            'has_more'     => $page < (int) $user_listings->max_num_pages,
            'max_pages'    => (int) $user_listings->max_num_pages,
            'current_page' => $page,
            'found_posts'  => (int) $user_listings->found_posts,
        ));
    }

    /**
     * Build query arguments for a user's listings
     *
     * @param int    $user_id   Current user ID.
     * @param string $status    Listing status filter.
     * @param string $sort      Sorting option.
     * @param string $search    Search term.
     * @param int    $per_page  Posts per page.
     * @param int    $page      Current page.
     * @return array
     */
    private static function build_query_args(
        int $user_id,
        string $status,
        string $sort,
        string $search,
        int $per_page,
        int $page
    ): array {
        $args = array(
            'post_type'      => 'car',
            'author'         => $user_id,
            'posts_per_page' => $per_page,
            'paged'          => $page,
            'orderby'        => 'date',
            'order'          => 'DESC',
            'post_status'    => array('publish', 'pending'),
        );

        // Apply search
        if ($search !== '') {
            $args['s'] = $search;
        }

        // Apply sorting
        switch ($sort) {
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
            default:
                $args['orderby'] = 'date';
                $args['order'] = 'DESC';
        }

        // Apply status filter
        if ($status !== 'all') {
            if ($status === 'sold') {
                $args['meta_query'] = array(
                    array(
                        'key'     => 'is_sold',
                        'value'   => '1',
                        'compare' => '=',
                    ),
                );
            } else {
                $args['post_status'] = $status;
                if ($status === 'publish') {
                    $args['meta_query'] = array(
                        'relation' => 'OR',
                        array(
                            'key'     => 'is_sold',
                            'value'   => '0',
                            'compare' => '=',
                        ),
                        array(
                            'key'     => 'is_sold',
                            'compare' => 'NOT EXISTS',
                        ),
                    );
                }
            }
        }

        return $args;
    }

    /**
     * Render a single listing item
     *
     * @param int               $post_id     Listing post ID.
     * @param RefreshListingUI  $refresh_ui  Refresh UI helper.
     * @return void
     */
    private static function render_listing_item(int $post_id, RefreshListingUI $refresh_ui): void {
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

        $is_sold = get_field('is_sold', $post_id);
        $post_status = get_post_status($post_id);

        // Create custom frontend delete URL
        $delete_url = add_query_arg(
            array(
                'action'  => 'delete_car_listing',
                'car_id'  => $post_id,
                '_wpnonce' => wp_create_nonce('delete_car_listing_' . $post_id),
            ),
            admin_url('admin-post.php')
        );
        ?>
        <div class="listing-item">
            <div class="listing-image-container">
                <?php
                if (!empty($all_images)) {
                    $main_image_url = wp_get_attachment_image_url($all_images[0], 'large');
                    ?>
                    <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="listing-image-link">
                        <img src="<?php echo esc_url($main_image_url); ?>" alt="<?php echo esc_attr(get_the_title($post_id)); ?>" class="listing-image">
                        <div class="image-count">
                            <i class="fas fa-camera"></i>
                            <span><?php echo count($all_images); ?></span>
                        </div>
                    </a>
                    <?php
                }
                ?>
            </div>
            <div class="listing-details">
                <div class="title-and-price">
                    <a href="<?php echo esc_url(get_permalink($post_id)); ?>" class="listing-title-link">
                        <h3 class="listing-title"><?php echo esc_html(get_the_title($post_id)); ?></h3>
                    </a>
                    <h4 class="listing-price">
                        €<?php echo number_format(floatval(str_replace(',', '', (string) $price))); ?>
                    </h4>
                </div>
                <div class="listing-meta">
                    <span class="listing-date">
                        Published: <?php echo esc_html(get_the_date('', $post_id)); ?>
                    </span>
                    <span class="listing-status
                        <?php
                        if ($is_sold) {
                            echo ' status-sold';
                        } elseif ($post_status === 'pending') {
                            echo ' status-pending';
                        } elseif ($post_status === 'publish') {
                            echo ' status-published';
                        }
                        ?>">
                        Status:
                        <?php
                        if ($is_sold) {
                            echo 'SOLD';
                        } else {
                            echo $post_status === 'publish' ? 'Published' : esc_html(ucfirst($post_status));
                        }
                        ?>
                    </span>
                    <?php
                    // Show refresh status for published listings
                    if ($post_status === 'publish') {
                        echo $refresh_ui->render_refresh_status($post_id);
                    }
                    ?>
                </div>
                <div class="listing-actions">
                    <a href="<?php echo esc_url(add_query_arg('car_id', $post_id, home_url('/edit-listing/'))); ?>" class="btn btn-primary">
                        <i class="fas fa-pencil-alt"></i> Edit
                    </a>
                    <?php
                    // Show refresh button for published, unsold listings
                    if ($post_status === 'publish' && !$is_sold) {
                        echo $refresh_ui->render_refresh_button($post_id);
                    }

                    if ($post_status === 'publish') {
                        $button_text = $is_sold ? ' Mark as Available' : ' Mark as Sold';
                        $button_class = $is_sold
                            ? 'btn btn-primary available-button'
                            : 'btn btn-success sold-button';
                        $icon_class = $is_sold ? 'fas fa-undo-alt' : 'fas fa-check-circle';
                        ?>
                        <button
                            class="<?php echo esc_attr($button_class); ?>"
                            data-car-id="<?php echo esc_attr($post_id); ?>"
                            data-is-sold="<?php echo $is_sold ? '1' : '0'; ?>"
                        >
                            <i class="<?php echo esc_attr($icon_class); ?>"></i><?php echo esc_html($button_text); ?>
                        </button>
                        <?php
                    }
                    ?>
                    <a
                        href="<?php echo esc_url($delete_url); ?>"
                        class="btn btn-danger delete-button"
                        onclick="return confirm('Are you sure you want to delete this listing? This action cannot be undone.');"
                    >
                        <i class="fas fa-trash-alt"></i> Delete
                    </a>
                </div>
            </div>
        </div>
        <?php
    }
}

// Register AJAX handler.
MyListingsAjaxHandler::register();


