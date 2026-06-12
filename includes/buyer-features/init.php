<?php
/**
 * Buyer-side marketplace features.
 */

if (!defined('ABSPATH')) {
    exit;
}

const AUTOAGORA_PRICE_HISTORY_META = '_autoagora_price_history';
const AUTOAGORA_LAST_PRICE_DROP_AMOUNT_META = '_autoagora_last_price_drop_amount';
const AUTOAGORA_LAST_PRICE_DROP_DATE_META = '_autoagora_last_price_drop_date';

// Buyer feature rollout disabled.
add_action('added_post_meta', 'autoagora_track_price_history_from_meta_hook', 20, 4);
add_action('updated_post_meta', 'autoagora_track_price_history_from_meta_hook', 20, 4);
//
add_shortcode('similar_cars', 'autoagora_similar_cars_shortcode');
add_shortcode('price_history', 'autoagora_price_history_shortcode');
add_shortcode('price_drop_cars', 'autoagora_price_drop_cars_shortcode');
// add_shortcode('new_listings_since_last_visit', 'autoagora_new_listings_since_last_visit_shortcode');
// add_shortcode('dealer_trust_indicators', 'autoagora_dealer_trust_indicators_shortcode');
// add_shortcode('compare_button', 'autoagora_compare_button_shortcode');
// add_shortcode('compare_cars', 'autoagora_compare_cars_shortcode');

function autoagora_buyer_features_enqueue_assets(): void {
    $base_path = get_stylesheet_directory() . '/includes/buyer-features/';
    $base_url = get_stylesheet_directory_uri() . '/includes/buyer-features/';

    wp_enqueue_style(
        'autoagora-buyer-features',
        $base_url . 'buyer-features.css',
        array(),
        file_exists($base_path . 'buyer-features.css') ? filemtime($base_path . 'buyer-features.css') : BRICKS_CHILD_THEME_VERSION
    );

    wp_enqueue_script(
        'autoagora-buyer-features',
        $base_url . 'buyer-features.js',
        array(),
        file_exists($base_path . 'buyer-features.js') ? filemtime($base_path . 'buyer-features.js') : BRICKS_CHILD_THEME_VERSION,
        true
    );
}

function autoagora_track_price_history_from_meta_hook($meta_id, $post_id, $meta_key, $meta_value): void {
    if ($meta_key !== 'price' || get_post_type((int) $post_id) !== 'car') {
        return;
    }

    autoagora_record_price_history((int) $post_id, autoagora_buyer_features_parse_int($meta_value));
}

function autoagora_record_price_history(int $post_id, int $price): void {
    static $guard = array();
    if ($post_id <= 0 || $price <= 0 || !empty($guard[$post_id])) {
        return;
    }

    $guard[$post_id] = true;
    $history = get_post_meta($post_id, AUTOAGORA_PRICE_HISTORY_META, true);
    $history = is_array($history) ? array_values($history) : array();
    $last = !empty($history) ? end($history) : null;
    $last_price = is_array($last) && !empty($last['price']) ? (int) $last['price'] : 0;

    if ($last_price === $price) {
        unset($guard[$post_id]);
        return;
    }

    $history[] = array(
        'price' => $price,
        'date' => current_time('mysql'),
    );
    $history = array_slice($history, -20);
    update_post_meta($post_id, AUTOAGORA_PRICE_HISTORY_META, $history);

    if ($last_price > $price) {
        update_post_meta($post_id, AUTOAGORA_LAST_PRICE_DROP_AMOUNT_META, $last_price - $price);
        update_post_meta($post_id, AUTOAGORA_LAST_PRICE_DROP_DATE_META, current_time('mysql'));
    }

    unset($guard[$post_id]);
}

function autoagora_similar_cars_shortcode($atts): string {
    $atts = shortcode_atts(array('post_id' => 0, 'limit' => 4, 'title' => 'Similar cars you may like'), $atts, 'similar_cars');
    $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
    if (!$post_id || get_post_type($post_id) !== 'car') {
        return '';
    }

    autoagora_buyer_features_enqueue_assets();
    $query = autoagora_get_similar_cars_query($post_id, max(1, min(8, (int) $atts['limit'])));
    if (!$query->have_posts()) {
        return '';
    }

    ob_start();
    ?>
    <section class="autoagora-feature-section autoagora-similar-cars">
        <h2 class="autoagora-feature-title"><?php echo esc_html($atts['title']); ?></h2>
        <div class="autoagora-card-grid">
            <?php
            $index = 0;
            while ($query->have_posts()) :
                $query->the_post();
                render_car_card(get_the_ID(), array('listing_index' => $index));
                $index++;
            endwhile;
            wp_reset_postdata();
            ?>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function autoagora_get_similar_cars_query(int $post_id, int $limit): WP_Query {
    $make_term = autoagora_get_listing_make_term($post_id);
    $price = autoagora_buyer_features_parse_int(get_post_meta($post_id, 'price', true));
    $year = autoagora_buyer_features_parse_int(get_post_meta($post_id, 'year', true));

    $args = array(
        'post_type' => 'car',
        'post_status' => 'publish',
        'posts_per_page' => $limit,
        'post__not_in' => array($post_id),
        'orderby' => 'date',
        'order' => 'DESC',
        'no_found_rows' => true,
    );

    $meta_query = array('relation' => 'AND');
    if (class_exists('ListingStateManager')) {
        $meta_query[] = ListingStateManager::meta_query_active_clause();
    }
    if ($price > 0) {
        $meta_query[] = array('key' => 'price', 'value' => array((int) floor($price * 0.8), (int) ceil($price * 1.2)), 'compare' => 'BETWEEN', 'type' => 'NUMERIC');
    }
    if ($year > 0) {
        $meta_query[] = array('key' => 'year', 'value' => array($year - 3, $year + 3), 'compare' => 'BETWEEN', 'type' => 'NUMERIC');
    }
    if (count($meta_query) > 1) {
        $args['meta_query'] = $meta_query;
    }
    if ($make_term) {
        $args['tax_query'] = array(array('taxonomy' => 'car_make', 'field' => 'term_id', 'terms' => array((int) $make_term->term_id), 'include_children' => true));
    }

    $query = new WP_Query($args);
    if ($query->post_count > 0 || (!$price && !$year)) {
        return $query;
    }

    unset($args['meta_query']);
    if (class_exists('ListingStateManager')) {
        $args['meta_query'] = array(ListingStateManager::meta_query_active_clause());
    }

    return new WP_Query($args);
}

function autoagora_price_history_shortcode($atts): string {
    $atts = shortcode_atts(array('post_id' => 0, 'title' => 'Price history'), $atts, 'price_history');
    $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
    if (!$post_id || get_post_type($post_id) !== 'car') {
        return '';
    }

    autoagora_buyer_features_enqueue_assets();
    $history = autoagora_get_price_history($post_id);
    if (count($history) < 2) {
        return '';
    }

    $history = array_reverse($history);
    ob_start();
    ?>
    <section class="autoagora-feature-section autoagora-price-history">
        <h2 class="autoagora-feature-title"><?php echo esc_html($atts['title']); ?></h2>
        <ul class="autoagora-price-history__list">
            <?php foreach ($history as $index => $row) : ?>
                <?php $previous = isset($history[$index + 1]) ? (int) $history[$index + 1]['price'] : 0; ?>
                <li class="autoagora-price-history__item">
                    <span>
                        <span class="autoagora-price-history__price">EUR <?php echo esc_html(number_format((int) $row['price'])); ?></span>
                        <?php if (!empty($row['date'])) : ?>
                            <small><?php echo esc_html(date_i18n(get_option('date_format'), strtotime((string) $row['date']))); ?></small>
                        <?php endif; ?>
                    </span>
                    <?php if ($previous > (int) $row['price']) : ?>
                        <span class="autoagora-price-history__drop">Down EUR <?php echo esc_html(number_format($previous - (int) $row['price'])); ?></span>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
    <?php
    return ob_get_clean();
}

function autoagora_price_drop_cars_shortcode($atts): string {
    $atts = shortcode_atts(array('limit' => 12, 'title' => 'Recently reduced prices'), $atts, 'price_drop_cars');
    autoagora_buyer_features_enqueue_assets();

    $args = array(
        'post_type' => 'car',
        'post_status' => 'publish',
        'posts_per_page' => max(1, min(24, (int) $atts['limit'])),
        'meta_key' => AUTOAGORA_LAST_PRICE_DROP_DATE_META,
        'orderby' => 'meta_value',
        'order' => 'DESC',
        'meta_query' => array(
            array('key' => AUTOAGORA_LAST_PRICE_DROP_AMOUNT_META, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC'),
        ),
    );
    if (class_exists('ListingStateManager')) {
        $args['meta_query'][] = ListingStateManager::meta_query_active_clause();
    }

    $query = new WP_Query($args);
    ob_start();
    ?>
    <section class="autoagora-feature-section autoagora-price-drops">
        <h2 class="autoagora-feature-title"><?php echo esc_html($atts['title']); ?></h2>
        <?php if ($query->have_posts()) : ?>
            <div class="autoagora-card-grid">
                <?php
                $index = 0;
                while ($query->have_posts()) :
                    $query->the_post();
                    render_car_card(get_the_ID(), array('listing_index' => $index));
                    $index++;
                endwhile;
                wp_reset_postdata();
                ?>
            </div>
        <?php else : ?>
            <p class="autoagora-empty-note">No recent price drops yet.</p>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

function autoagora_new_listings_since_last_visit_shortcode($atts): string {
    $atts = shortcode_atts(array('link' => '/cars/'), $atts, 'new_listings_since_last_visit');
    autoagora_buyer_features_enqueue_assets();

    $last = isset($_COOKIE['autoagora_last_cars_visit']) ? absint($_COOKIE['autoagora_last_cars_visit']) : 0;
    $count = 0;
    if ($last > 0) {
        $args = array(
            'post_type' => 'car',
            'post_status' => 'publish',
            'posts_per_page' => 1,
            'fields' => 'ids',
            'date_query' => array(array('after' => wp_date('Y-m-d H:i:s', $last), 'inclusive' => false)),
        );
        if (class_exists('ListingStateManager')) {
            $args['meta_query'] = array(ListingStateManager::meta_query_active_clause());
        }
        $query = new WP_Query($args);
        $count = (int) $query->found_posts;
    }

    add_action('wp_footer', 'autoagora_new_listings_since_last_visit_cookie_script', 30);
    if ($count <= 0) {
        return '';
    }

    return sprintf(
        '<div class="autoagora-new-since"><strong>%s new cars since your last visit</strong><a class="btn btn-primary" href="%s">View new cars</a></div>',
        esc_html(number_format($count)),
        esc_url(home_url($atts['link']))
    );
}

function autoagora_new_listings_since_last_visit_cookie_script(): void {
    ?>
    <script>
    document.cookie = 'autoagora_last_cars_visit=' + Math.floor(Date.now() / 1000) + '; path=/; max-age=31536000; SameSite=Lax';
    </script>
    <?php
}

function autoagora_dealer_trust_indicators_shortcode($atts): string {
    $atts = shortcode_atts(array('user_id' => 0, 'post_id' => 0, 'title' => 'Seller trust signals'), $atts, 'dealer_trust_indicators');
    $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
    $user_id = $atts['user_id'] ? absint($atts['user_id']) : 0;
    if (!$user_id && $post_id) {
        $user_id = (int) get_post_field('post_author', $post_id);
    }
    $user = $user_id ? get_userdata($user_id) : null;
    if (!$user) {
        return '';
    }

    autoagora_buyer_features_enqueue_assets();
    $metrics = autoagora_get_seller_trust_metrics($user_id);
    ob_start();
    ?>
    <section class="autoagora-feature-section autoagora-dealer-trust">
        <h2 class="autoagora-feature-title"><?php echo esc_html($atts['title']); ?></h2>
        <div class="autoagora-trust-grid">
            <div class="autoagora-trust-metric"><span class="autoagora-trust-metric__label">Listings active</span><span class="autoagora-trust-metric__value"><?php echo esc_html(number_format($metrics['active'])); ?></span></div>
            <div class="autoagora-trust-metric"><span class="autoagora-trust-metric__label">Member since</span><span class="autoagora-trust-metric__value"><?php echo esc_html(date_i18n('Y', strtotime($user->user_registered))); ?></span></div>
            <div class="autoagora-trust-metric"><span class="autoagora-trust-metric__label">Response signal</span><span class="autoagora-trust-metric__value"><?php echo esc_html($metrics['interest']); ?></span></div>
            <div class="autoagora-trust-metric"><span class="autoagora-trust-metric__label">Average views/listing</span><span class="autoagora-trust-metric__value"><?php echo esc_html(number_format($metrics['avg_views'])); ?></span></div>
        </div>
    </section>
    <?php
    return ob_get_clean();
}

function autoagora_compare_button_shortcode($atts): string {
    $atts = shortcode_atts(array('post_id' => 0), $atts, 'compare_button');
    $post_id = $atts['post_id'] ? absint($atts['post_id']) : get_the_ID();
    return autoagora_render_compare_button($post_id, 'autoagora-compare-btn');
}

function autoagora_compare_cars_shortcode(): string {
    autoagora_buyer_features_enqueue_assets();
    return '<button type="button" class="autoagora-compare-btn" onclick="window.autoagoraCompareRender && window.autoagoraCompareRender()">Open compare</button>';
}

function autoagora_render_compare_button(int $post_id, string $class = 'car-card-compare-btn'): string {
    if (!$post_id || get_post_type($post_id) !== 'car') {
        return '';
    }
    autoagora_buyer_features_enqueue_assets();
    $data = autoagora_get_compare_data($post_id);
    $attrs = '';
    foreach ($data as $key => $value) {
        $attrs .= ' data-' . esc_attr($key) . '="' . esc_attr((string) $value) . '"';
    }
    return '<button type="button" class="' . esc_attr($class) . '" data-autoagora-compare="1"' . $attrs . '>Compare</button>';
}

function autoagora_get_compare_data(int $post_id): array {
    return array(
        'car-id' => $post_id,
        'title' => get_the_title($post_id),
        'url' => get_permalink($post_id),
        'price' => autoagora_format_eur(get_post_meta($post_id, 'price', true)),
        'mileage' => autoagora_format_km(get_post_meta($post_id, 'mileage', true)),
        'year' => (string) get_post_meta($post_id, 'year', true),
        'power' => get_post_meta($post_id, 'hp', true) ? get_post_meta($post_id, 'hp', true) . ' hp' : '',
        'fuel' => (string) get_post_meta($post_id, 'fuel_type', true),
        'transmission' => (string) get_post_meta($post_id, 'transmission', true),
    );
}

function autoagora_get_price_history(int $post_id): array {
    $history = get_post_meta($post_id, AUTOAGORA_PRICE_HISTORY_META, true);
    $history = is_array($history) ? $history : array();
    $current_price = autoagora_buyer_features_parse_int(get_post_meta($post_id, 'price', true));
    if ($current_price > 0) {
        $last = !empty($history) ? end($history) : null;
        $last_price = is_array($last) && !empty($last['price']) ? (int) $last['price'] : 0;
        if ($last_price !== $current_price) {
            $history[] = array('price' => $current_price, 'date' => get_post_modified_time('Y-m-d H:i:s', false, $post_id));
        }
    }
    return array_values($history);
}

function autoagora_get_listing_make_term(int $post_id) {
    $terms = wp_get_post_terms($post_id, 'car_make');
    if (is_wp_error($terms) || empty($terms)) {
        return null;
    }
    foreach ($terms as $term) {
        if ((int) $term->parent === 0) {
            return $term;
        }
    }
    foreach ($terms as $term) {
        if ((int) $term->parent > 0) {
            $parent = get_term((int) $term->parent, 'car_make');
            return $parent && !is_wp_error($parent) ? $parent : null;
        }
    }
    return null;
}

function autoagora_get_seller_trust_metrics(int $user_id): array {
    $args = array(
        'post_type' => 'car',
        'post_status' => 'publish',
        'author' => $user_id,
        'posts_per_page' => -1,
        'fields' => 'ids',
        'no_found_rows' => true,
    );
    if (class_exists('ListingStateManager')) {
        $args['meta_query'] = array(ListingStateManager::meta_query_active_clause());
    }
    $query = new WP_Query($args);
    $active = count($query->posts);
    $views = 0;
    $clicks = 0;
    foreach ($query->posts as $car_id) {
        $views += autoagora_buyer_features_parse_int(get_post_meta((int) $car_id, 'total_views_count', true));
        $clicks += autoagora_buyer_features_parse_int(get_post_meta((int) $car_id, 'call_button_clicks', true));
        $clicks += autoagora_buyer_features_parse_int(get_post_meta((int) $car_id, 'whatsapp_button_clicks', true));
    }
    $avg_views = $active > 0 ? (int) round($views / $active) : 0;
    $interest = $clicks >= 20 ? 'High' : ($clicks >= 5 ? 'Medium' : 'New');
    return array('active' => $active, 'avg_views' => $avg_views, 'interest' => $interest);
}

function autoagora_buyer_features_parse_int($value): int {
    if (is_array($value)) {
        return 0;
    }
    return (int) preg_replace('/[^0-9]/', '', (string) $value);
}

function autoagora_format_eur($value): string {
    $n = autoagora_buyer_features_parse_int($value);
    return $n > 0 ? 'EUR ' . number_format($n) : '';
}

function autoagora_format_km($value): string {
    $n = autoagora_buyer_features_parse_int($value);
    return $n > 0 ? number_format($n) . ' km' : '';
}
