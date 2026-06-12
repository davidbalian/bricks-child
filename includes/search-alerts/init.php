<?php
/**
 * Buyer search alerts.
 *
 * Lets logged-in buyers save a filtered search and receive an email when a
 * newly published active car matches it.
 */

if (!defined('ABSPATH')) {
    exit;
}

const AUTOAGORA_SEARCH_ALERT_POST_TYPE = 'autoagora_alert';
const AUTOAGORA_SEARCH_ALERT_STATUS_META = '_autoagora_alert_status';
const AUTOAGORA_SEARCH_ALERT_CRITERIA_META = '_autoagora_alert_criteria';
const AUTOAGORA_SEARCH_ALERT_SENT_META = '_autoagora_alert_sent_car_ids';

add_action('init', 'autoagora_search_alerts_register_post_type');
add_shortcode('search_alert_form', 'autoagora_search_alert_form_shortcode');
add_action('admin_post_autoagora_save_search_alert', 'autoagora_search_alerts_handle_save');
add_action('admin_post_autoagora_delete_search_alert', 'autoagora_search_alerts_handle_delete');
add_action('transition_post_status', 'autoagora_search_alerts_maybe_notify_for_listing', 40, 3);

function autoagora_search_alerts_register_post_type(): void {
    register_post_type(
        AUTOAGORA_SEARCH_ALERT_POST_TYPE,
        array(
            'label' => 'Search Alerts',
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'edit.php?post_type=car',
            'supports' => array('title', 'author'),
            'capability_type' => 'post',
            'map_meta_cap' => true,
        )
    );
}

function autoagora_search_alert_form_shortcode($atts): string {
    $atts = shortcode_atts(
        array(
            'show_existing' => 'true',
            'title' => 'Get alerts for this search',
        ),
        $atts,
        'search_alert_form'
    );

    autoagora_search_alerts_enqueue_assets();

    ob_start();
    $message = autoagora_search_alerts_current_message();
    ?>
    <section class="autoagora-search-alert">
        <div class="autoagora-search-alert__header">
            <div>
                <h2 class="autoagora-search-alert__title"><?php echo esc_html($atts['title']); ?></h2>
                <p class="autoagora-search-alert__hint">We will email you when matching cars are published.</p>
            </div>
        </div>

        <?php if ($message !== '') : ?>
            <?php echo $message; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Built from escaped values. ?>
        <?php endif; ?>

        <?php if (!is_user_logged_in()) : ?>
            <p class="autoagora-search-alert__message">Sign in to save this search and receive alerts.</p>
            <a class="btn btn-primary" href="<?php echo esc_url(wp_login_url(autoagora_search_alerts_current_url())); ?>">Sign in</a>
        <?php else : ?>
            <?php
            $user_id = get_current_user_id();
            $email = autoagora_search_alerts_resolve_verified_email($user_id);
            if ($email === null) :
            ?>
                <p class="autoagora-search-alert__message autoagora-search-alert__message--error">
                    Verify your email in My Account before creating search alerts.
                </p>
                <a class="btn btn-primary" href="<?php echo esc_url(home_url('/my-account/')); ?>">Verify email</a>
            <?php else : ?>
                <?php autoagora_search_alerts_render_form(); ?>
            <?php endif; ?>

            <?php if ($atts['show_existing'] === 'true') : ?>
                <?php autoagora_search_alerts_render_existing_alerts($user_id); ?>
            <?php endif; ?>
        <?php endif; ?>
    </section>
    <?php
    return ob_get_clean();
}

function autoagora_search_alerts_render_form(): void {
    $defaults = autoagora_search_alerts_defaults_from_request();
    ?>
    <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
        <input type="hidden" name="action" value="autoagora_save_search_alert">
        <input type="hidden" name="redirect_to" value="<?php echo esc_attr(autoagora_search_alerts_current_url()); ?>">
        <?php wp_nonce_field('autoagora_save_search_alert', 'autoagora_search_alert_nonce'); ?>

        <div class="autoagora-search-alert__grid">
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-make">Make</label>
                <input id="autoagora-alert-make" type="text" name="make" value="<?php echo esc_attr($defaults['make']); ?>" placeholder="BMW">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-model">Model</label>
                <input id="autoagora-alert-model" type="text" name="model" value="<?php echo esc_attr($defaults['model']); ?>" placeholder="330i">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-year-min">Year from</label>
                <input id="autoagora-alert-year-min" type="number" min="1948" max="2035" name="year_min" value="<?php echo esc_attr($defaults['year_min']); ?>" placeholder="2019">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-year-max">Year to</label>
                <input id="autoagora-alert-year-max" type="number" min="1948" max="2035" name="year_max" value="<?php echo esc_attr($defaults['year_max']); ?>" placeholder="2026">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-price-min">Price from</label>
                <input id="autoagora-alert-price-min" type="number" min="0" step="100" name="price_min" value="<?php echo esc_attr($defaults['price_min']); ?>" placeholder="10000">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-price-max">Price up to</label>
                <input id="autoagora-alert-price-max" type="number" min="0" step="100" name="price_max" value="<?php echo esc_attr($defaults['price_max']); ?>" placeholder="25000">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-mileage-min">Mileage from</label>
                <input id="autoagora-alert-mileage-min" type="number" min="0" step="1000" name="mileage_min" value="<?php echo esc_attr($defaults['mileage_min']); ?>" placeholder="0">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-mileage-max">Mileage up to</label>
                <input id="autoagora-alert-mileage-max" type="number" min="0" step="1000" name="mileage_max" value="<?php echo esc_attr($defaults['mileage_max']); ?>" placeholder="100000">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-city">City</label>
                <input id="autoagora-alert-city" type="text" name="car_city" value="<?php echo esc_attr($defaults['car_city']); ?>" placeholder="Nicosia">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-fuel">Fuel</label>
                <input id="autoagora-alert-fuel" type="text" name="fuel_type" value="<?php echo esc_attr($defaults['fuel_type']); ?>" placeholder="Petrol">
            </div>
            <div class="autoagora-search-alert__field">
                <label for="autoagora-alert-body">Body</label>
                <input id="autoagora-alert-body" type="text" name="body_type" value="<?php echo esc_attr($defaults['body_type']); ?>" placeholder="Sedan">
            </div>
        </div>

        <div class="autoagora-search-alert__actions">
            <button type="submit" class="btn btn-primary-gradient">Create alert</button>
        </div>
    </form>
    <?php
}

function autoagora_search_alerts_render_existing_alerts(int $user_id): void {
    $alerts = autoagora_search_alerts_get_user_alerts($user_id);
    if (empty($alerts)) {
        return;
    }
    ?>
    <div class="autoagora-search-alert__list" aria-label="Saved search alerts">
        <?php foreach ($alerts as $alert_id) : ?>
            <?php $criteria = autoagora_search_alerts_get_criteria($alert_id); ?>
            <div class="autoagora-search-alert__item">
                <div>
                    <div class="autoagora-search-alert__item-title"><?php echo esc_html(get_the_title($alert_id)); ?></div>
                    <div class="autoagora-search-alert__item-meta"><?php echo esc_html(autoagora_search_alerts_describe_criteria($criteria)); ?></div>
                </div>
                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                    <input type="hidden" name="action" value="autoagora_delete_search_alert">
                    <input type="hidden" name="alert_id" value="<?php echo esc_attr((string) $alert_id); ?>">
                    <input type="hidden" name="redirect_to" value="<?php echo esc_attr(autoagora_search_alerts_current_url()); ?>">
                    <?php wp_nonce_field('autoagora_delete_search_alert_' . $alert_id, 'autoagora_delete_search_alert_nonce'); ?>
                    <button type="submit" class="autoagora-search-alert__delete">Delete</button>
                </form>
            </div>
        <?php endforeach; ?>
    </div>
    <?php
}

function autoagora_search_alerts_handle_save(): void {
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(autoagora_search_alerts_post_redirect()));
        exit;
    }

    if (
        !isset($_POST['autoagora_search_alert_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['autoagora_search_alert_nonce'])), 'autoagora_save_search_alert')
    ) {
        autoagora_search_alerts_redirect_with_status('error');
    }

    $user_id = get_current_user_id();
    if (autoagora_search_alerts_resolve_verified_email($user_id) === null) {
        autoagora_search_alerts_redirect_with_status('verify_email');
    }

    $criteria = autoagora_search_alerts_sanitize_posted_criteria($_POST);
    if (!autoagora_search_alerts_has_meaningful_criteria($criteria)) {
        autoagora_search_alerts_redirect_with_status('empty');
    }

    $title = autoagora_search_alerts_describe_criteria($criteria);
    $alert_id = wp_insert_post(
        array(
            'post_type' => AUTOAGORA_SEARCH_ALERT_POST_TYPE,
            'post_status' => 'publish',
            'post_title' => $title,
            'post_author' => $user_id,
        ),
        true
    );

    if (is_wp_error($alert_id) || !$alert_id) {
        autoagora_search_alerts_redirect_with_status('error');
    }

    update_post_meta($alert_id, AUTOAGORA_SEARCH_ALERT_STATUS_META, 'active');
    update_post_meta($alert_id, AUTOAGORA_SEARCH_ALERT_CRITERIA_META, $criteria);
    update_post_meta($alert_id, AUTOAGORA_SEARCH_ALERT_SENT_META, array());

    autoagora_search_alerts_redirect_with_status('created');
}

function autoagora_search_alerts_handle_delete(): void {
    if (!is_user_logged_in()) {
        wp_safe_redirect(wp_login_url(autoagora_search_alerts_post_redirect()));
        exit;
    }

    $alert_id = isset($_POST['alert_id']) ? absint(wp_unslash($_POST['alert_id'])) : 0;
    if (
        $alert_id <= 0 ||
        !isset($_POST['autoagora_delete_search_alert_nonce']) ||
        !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['autoagora_delete_search_alert_nonce'])), 'autoagora_delete_search_alert_' . $alert_id)
    ) {
        autoagora_search_alerts_redirect_with_status('error');
    }

    $alert = get_post($alert_id);
    if (
        !$alert ||
        $alert->post_type !== AUTOAGORA_SEARCH_ALERT_POST_TYPE ||
        ((int) $alert->post_author !== get_current_user_id() && !current_user_can('manage_options'))
    ) {
        autoagora_search_alerts_redirect_with_status('error');
    }

    wp_trash_post($alert_id);
    autoagora_search_alerts_redirect_with_status('deleted');
}

function autoagora_search_alerts_maybe_notify_for_listing(string $new_status, string $old_status, WP_Post $post): void {
    if ($post->post_type !== 'car' || $new_status !== 'publish' || $old_status === 'publish') {
        return;
    }

    $car_id = (int) $post->ID;
    if (class_exists('ListingStateManager') && ListingStateManager::resolve_state($car_id) !== ListingStateManager::STATE_ACTIVE) {
        return;
    }

    $alerts = autoagora_search_alerts_get_active_alerts();
    if (empty($alerts)) {
        return;
    }

    foreach ($alerts as $alert_id) {
        $criteria = autoagora_search_alerts_get_criteria($alert_id);
        if (!autoagora_search_alerts_listing_matches($car_id, $criteria)) {
            continue;
        }

        $sent_ids = get_post_meta($alert_id, AUTOAGORA_SEARCH_ALERT_SENT_META, true);
        $sent_ids = is_array($sent_ids) ? array_map('intval', $sent_ids) : array();
        if (in_array($car_id, $sent_ids, true)) {
            continue;
        }

        $alert = get_post($alert_id);
        if (!$alert || (int) $alert->post_author === (int) $post->post_author) {
            continue;
        }

        $email = autoagora_search_alerts_resolve_verified_email((int) $alert->post_author);
        if ($email === null) {
            continue;
        }

        if (autoagora_search_alerts_send_match_email($email, $alert_id, $car_id, $criteria)) {
            $sent_ids[] = $car_id;
            $sent_ids = array_slice(array_values(array_unique(array_map('intval', $sent_ids))), -200);
            update_post_meta($alert_id, AUTOAGORA_SEARCH_ALERT_SENT_META, $sent_ids);
        }
    }
}

function autoagora_search_alerts_send_match_email(string $email, int $alert_id, int $car_id, array $criteria): bool {
    if (!function_exists('send_app_email')) {
        return false;
    }

    $listing_title = get_the_title($car_id);
    $listing_url = get_permalink($car_id);
    $alert_url = autoagora_search_alerts_build_browse_url($criteria);
    $price = autoagora_search_alerts_numeric_meta($car_id, 'price');
    $year = autoagora_search_alerts_numeric_meta($car_id, 'year');
    $subject = 'New car matching your AutoAgora alert';

    $summary = array_filter(
        array(
            $year > 0 ? (string) $year : '',
            $price > 0 ? 'EUR ' . number_format($price) : '',
        )
    );

    $html = sprintf(
        '<p>A new listing matches your saved search: <strong>%s</strong>.</p><p><strong>%s</strong>%s</p><p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 16px;border-radius:4px;text-decoration:none;font-weight:600;">View listing</a> <a href="%s" style="color:#0073aa;margin-left:12px;">See matching cars</a></p><p><small>You are receiving this because you created the AutoAgora search alert "%s". You can delete alerts from the page where the search alert block is shown.</small></p>',
        esc_html(autoagora_search_alerts_describe_criteria($criteria)),
        esc_html($listing_title),
        !empty($summary) ? esc_html(' - ' . implode(' - ', $summary)) : '',
        esc_url($listing_url),
        esc_url($alert_url),
        esc_html(get_the_title($alert_id))
    );

    $text = "A new listing matches your AutoAgora search alert.\n\n";
    $text .= $listing_title . (!empty($summary) ? ' - ' . implode(' - ', $summary) : '') . "\n";
    $text .= "View listing: " . $listing_url . "\n";
    $text .= "See matching cars: " . $alert_url . "\n";

    return send_app_email($email, $subject, $html, $text);
}

function autoagora_search_alerts_sanitize_posted_criteria(array $source): array {
    $make_value = isset($source['make']) ? sanitize_text_field(wp_unslash($source['make'])) : '';
    $model_value = isset($source['model']) ? sanitize_text_field(wp_unslash($source['model'])) : '';

    $make_term = autoagora_search_alerts_resolve_make_term($make_value);
    $model_term = autoagora_search_alerts_resolve_model_term($model_value, $make_term ? (int) $make_term->term_id : 0);
    if (!$make_term && $model_term && (int) $model_term->parent > 0) {
        $make_term = get_term((int) $model_term->parent, 'car_make');
    }

    return array(
        'make_term_id' => $make_term && !is_wp_error($make_term) ? (int) $make_term->term_id : 0,
        'make_label' => $make_term && !is_wp_error($make_term) ? (string) $make_term->name : $make_value,
        'model_term_id' => $model_term && !is_wp_error($model_term) ? (int) $model_term->term_id : 0,
        'model_label' => $model_term && !is_wp_error($model_term) ? (string) $model_term->name : $model_value,
        'price_min' => autoagora_search_alerts_posted_absint($source, 'price_min'),
        'price_max' => autoagora_search_alerts_posted_absint($source, 'price_max'),
        'year_min' => autoagora_search_alerts_posted_absint($source, 'year_min'),
        'year_max' => autoagora_search_alerts_posted_absint($source, 'year_max'),
        'mileage_min' => autoagora_search_alerts_posted_absint($source, 'mileage_min'),
        'mileage_max' => autoagora_search_alerts_posted_absint($source, 'mileage_max'),
        'fuel_type' => isset($source['fuel_type']) ? sanitize_text_field(wp_unslash($source['fuel_type'])) : '',
        'body_type' => isset($source['body_type']) ? sanitize_text_field(wp_unslash($source['body_type'])) : '',
        'car_city' => isset($source['car_city']) ? sanitize_text_field(wp_unslash($source['car_city'])) : '',
    );
}

function autoagora_search_alerts_posted_absint(array $source, string $key): int {
    if (!isset($source[$key]) || $source[$key] === '') {
        return 0;
    }

    return absint(str_replace(',', '', (string) wp_unslash($source[$key])));
}

function autoagora_search_alerts_defaults_from_request(): array {
    $defaults = array(
        'make' => '',
        'model' => '',
        'price_min' => isset($_GET['price_min']) ? absint(wp_unslash($_GET['price_min'])) : '',
        'price_max' => isset($_GET['price_max']) ? absint(wp_unslash($_GET['price_max'])) : '',
        'year_min' => isset($_GET['year_min']) ? absint(wp_unslash($_GET['year_min'])) : '',
        'year_max' => isset($_GET['year_max']) ? absint(wp_unslash($_GET['year_max'])) : '',
        'mileage_min' => isset($_GET['mileage_min']) ? absint(wp_unslash($_GET['mileage_min'])) : '',
        'mileage_max' => isset($_GET['mileage_max']) ? absint(wp_unslash($_GET['mileage_max'])) : '',
        'fuel_type' => isset($_GET['fuel_type']) ? sanitize_text_field(wp_unslash($_GET['fuel_type'])) : '',
        'body_type' => isset($_GET['body_type']) ? sanitize_text_field(wp_unslash($_GET['body_type'])) : '',
        'car_city' => isset($_GET['car_city']) ? sanitize_text_field(wp_unslash($_GET['car_city'])) : '',
    );

    $context = function_exists('autoagora_get_active_car_filter_context')
        ? autoagora_get_active_car_filter_context()
        : array();

    $make_slug = !empty($_GET['make']) ? sanitize_title(wp_unslash($_GET['make'])) : (!empty($context['make_slug']) ? $context['make_slug'] : '');
    $model_slug = !empty($_GET['model']) ? sanitize_title(wp_unslash($_GET['model'])) : (!empty($context['model_slug']) ? $context['model_slug'] : '');

    if ($make_slug !== '') {
        $make_term = get_term_by('slug', $make_slug, 'car_make');
        $defaults['make'] = $make_term && !is_wp_error($make_term) ? $make_term->name : $make_slug;
    }

    if ($model_slug !== '') {
        $model_term = get_term_by('slug', $model_slug, 'car_make');
        $defaults['model'] = $model_term && !is_wp_error($model_term) ? $model_term->name : $model_slug;
        if ($defaults['make'] === '' && $model_term && !is_wp_error($model_term) && (int) $model_term->parent > 0) {
            $parent = get_term((int) $model_term->parent, 'car_make');
            $defaults['make'] = $parent && !is_wp_error($parent) ? $parent->name : '';
        }
    }

    return $defaults;
}

function autoagora_search_alerts_resolve_make_term(string $value) {
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (ctype_digit($value)) {
        $term = get_term((int) $value, 'car_make');
        return $term && !is_wp_error($term) && (int) $term->parent === 0 ? $term : null;
    }

    $term = get_term_by('slug', sanitize_title($value), 'car_make');
    if ($term && !is_wp_error($term) && (int) $term->parent === 0) {
        return $term;
    }

    $terms = get_terms(
        array(
            'taxonomy' => 'car_make',
            'name' => $value,
            'parent' => 0,
            'hide_empty' => false,
            'number' => 1,
        )
    );

    return !empty($terms) && !is_wp_error($terms) ? $terms[0] : null;
}

function autoagora_search_alerts_resolve_model_term(string $value, int $make_term_id = 0) {
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    if (ctype_digit($value)) {
        $term = get_term((int) $value, 'car_make');
        return $term && !is_wp_error($term) && (int) $term->parent > 0 ? $term : null;
    }

    $term = get_term_by('slug', sanitize_title($value), 'car_make');
    if ($term && !is_wp_error($term) && (int) $term->parent > 0) {
        if ($make_term_id <= 0 || (int) $term->parent === $make_term_id) {
            return $term;
        }
    }

    $query = array(
        'taxonomy' => 'car_make',
        'name' => $value,
        'hide_empty' => false,
        'number' => 1,
    );
    if ($make_term_id > 0) {
        $query['parent'] = $make_term_id;
    }

    $terms = get_terms($query);
    return !empty($terms) && !is_wp_error($terms) ? $terms[0] : null;
}

function autoagora_search_alerts_listing_matches(int $car_id, array $criteria): bool {
    if (empty($criteria)) {
        return false;
    }

    if (!empty($criteria['model_term_id']) && !has_term((int) $criteria['model_term_id'], 'car_make', $car_id)) {
        return false;
    }

    if (empty($criteria['model_term_id']) && !empty($criteria['make_term_id']) && !autoagora_search_alerts_listing_has_make($car_id, (int) $criteria['make_term_id'])) {
        return false;
    }

    foreach (array('price', 'year', 'mileage') as $field) {
        $value = autoagora_search_alerts_numeric_meta($car_id, $field);
        $min_key = $field . '_min';
        $max_key = $field . '_max';

        if (!empty($criteria[$min_key]) && ($value <= 0 || $value < (int) $criteria[$min_key])) {
            return false;
        }
        if (!empty($criteria[$max_key]) && ($value <= 0 || $value > (int) $criteria[$max_key])) {
            return false;
        }
    }

    foreach (array('fuel_type', 'body_type', 'car_city') as $field) {
        if (empty($criteria[$field])) {
            continue;
        }

        $listing_value = strtolower(trim((string) get_post_meta($car_id, $field, true)));
        $wanted_values = array_filter(array_map('trim', explode(',', strtolower((string) $criteria[$field]))));
        if ($listing_value === '' || empty($wanted_values) || !in_array($listing_value, $wanted_values, true)) {
            return false;
        }
    }

    return true;
}

function autoagora_search_alerts_listing_has_make(int $car_id, int $make_term_id): bool {
    if (has_term($make_term_id, 'car_make', $car_id)) {
        return true;
    }

    $terms = wp_get_post_terms($car_id, 'car_make');
    if (is_wp_error($terms) || empty($terms)) {
        return false;
    }

    foreach ($terms as $term) {
        if ((int) $term->parent === $make_term_id) {
            return true;
        }
    }

    return false;
}

function autoagora_search_alerts_numeric_meta(int $post_id, string $key): int {
    $raw = get_post_meta($post_id, $key, true);
    if ($raw === '' || $raw === null) {
        return 0;
    }

    return (int) preg_replace('/[^0-9]/', '', (string) $raw);
}

function autoagora_search_alerts_get_active_alerts(): array {
    $query = new WP_Query(
        array(
            'post_type' => AUTOAGORA_SEARCH_ALERT_POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'no_found_rows' => true,
            'meta_query' => array(
                array(
                    'key' => AUTOAGORA_SEARCH_ALERT_STATUS_META,
                    'value' => 'active',
                    'compare' => '=',
                ),
            ),
        )
    );

    return array_map('intval', $query->posts);
}

function autoagora_search_alerts_get_user_alerts(int $user_id): array {
    $query = new WP_Query(
        array(
            'post_type' => AUTOAGORA_SEARCH_ALERT_POST_TYPE,
            'post_status' => 'publish',
            'author' => $user_id,
            'posts_per_page' => 10,
            'fields' => 'ids',
            'no_found_rows' => true,
            'orderby' => 'date',
            'order' => 'DESC',
        )
    );

    return array_map('intval', $query->posts);
}

function autoagora_search_alerts_get_criteria(int $alert_id): array {
    $criteria = get_post_meta($alert_id, AUTOAGORA_SEARCH_ALERT_CRITERIA_META, true);
    return is_array($criteria) ? $criteria : array();
}

function autoagora_search_alerts_has_meaningful_criteria(array $criteria): bool {
    foreach ($criteria as $key => $value) {
        if (in_array($key, array('make_label', 'model_label'), true)) {
            continue;
        }
        if (!empty($value)) {
            return true;
        }
    }

    return false;
}

function autoagora_search_alerts_describe_criteria(array $criteria): string {
    $parts = array();

    if (!empty($criteria['make_label'])) {
        $parts[] = (string) $criteria['make_label'];
    }
    if (!empty($criteria['model_label'])) {
        $parts[] = (string) $criteria['model_label'];
    }
    if (!empty($criteria['year_min'])) {
        $parts[] = 'from ' . (int) $criteria['year_min'];
    }
    if (!empty($criteria['year_max'])) {
        $parts[] = 'to ' . (int) $criteria['year_max'];
    }
    if (!empty($criteria['price_min'])) {
        $parts[] = 'from EUR ' . number_format((int) $criteria['price_min']);
    }
    if (!empty($criteria['price_max'])) {
        $parts[] = 'under EUR ' . number_format((int) $criteria['price_max']);
    }
    if (!empty($criteria['mileage_min'])) {
        $parts[] = 'over ' . number_format((int) $criteria['mileage_min']) . ' km';
    }
    if (!empty($criteria['mileage_max'])) {
        $parts[] = 'under ' . number_format((int) $criteria['mileage_max']) . ' km';
    }
    if (!empty($criteria['fuel_type'])) {
        $parts[] = (string) $criteria['fuel_type'];
    }
    if (!empty($criteria['body_type'])) {
        $parts[] = (string) $criteria['body_type'];
    }
    if (!empty($criteria['car_city'])) {
        $parts[] = (string) $criteria['car_city'];
    }

    return !empty($parts) ? implode(' ', $parts) : 'Any matching car';
}

function autoagora_search_alerts_build_browse_url(array $criteria): string {
    $base = home_url('/cars/');
    $query = array();

    $term_id = !empty($criteria['model_term_id']) ? (int) $criteria['model_term_id'] : (!empty($criteria['make_term_id']) ? (int) $criteria['make_term_id'] : 0);
    if ($term_id > 0) {
        $term = get_term($term_id, 'car_make');
        if ($term && !is_wp_error($term)) {
            $base = home_url('/cars/filter/make:' . $term->slug . '/');
        }
    }

    foreach (array('price_min', 'price_max', 'year_min', 'year_max', 'mileage_min', 'mileage_max', 'fuel_type', 'body_type', 'car_city') as $key) {
        if (!empty($criteria[$key])) {
            $query[$key] = $criteria[$key];
        }
    }

    return !empty($query) ? add_query_arg($query, $base) : $base;
}

function autoagora_search_alerts_resolve_verified_email(int $user_id): ?string {
    if (class_exists('ListingNotificationEmailResolver')) {
        $resolver = new ListingNotificationEmailResolver();
        return $resolver->resolveVerifiedEmail($user_id);
    }

    $user = get_userdata($user_id);
    if (!$user || get_user_meta($user_id, 'email_verified', true) !== '1') {
        return null;
    }

    $email = sanitize_email($user->user_email);
    return $email !== '' ? $email : null;
}

function autoagora_search_alerts_current_message(): string {
    if (empty($_GET['search_alert'])) {
        return '';
    }

    $status = sanitize_key(wp_unslash($_GET['search_alert']));
    $map = array(
        'created' => array('Search alert created.', 'success'),
        'deleted' => array('Search alert deleted.', 'success'),
        'empty' => array('Choose at least one alert filter.', 'error'),
        'verify_email' => array('Verify your email before creating search alerts.', 'error'),
        'error' => array('Unable to update your search alert. Please try again.', 'error'),
    );

    if (!isset($map[$status])) {
        return '';
    }

    $class = $map[$status][1] === 'success'
        ? 'autoagora-search-alert__message autoagora-search-alert__message--success'
        : 'autoagora-search-alert__message autoagora-search-alert__message--error';

    return '<p class="' . esc_attr($class) . '">' . esc_html($map[$status][0]) . '</p>';
}

function autoagora_search_alerts_redirect_with_status(string $status): void {
    wp_safe_redirect(add_query_arg('search_alert', sanitize_key($status), autoagora_search_alerts_post_redirect()));
    exit;
}

function autoagora_search_alerts_post_redirect(): string {
    if (!empty($_POST['redirect_to'])) {
        return esc_url_raw(wp_unslash($_POST['redirect_to']));
    }

    return home_url('/cars/');
}

function autoagora_search_alerts_current_url(): string {
    $request_uri = isset($_SERVER['REQUEST_URI']) ? wp_unslash($_SERVER['REQUEST_URI']) : '/';
    return home_url($request_uri);
}

function autoagora_search_alerts_enqueue_assets(): void {
    $path = get_stylesheet_directory() . '/includes/search-alerts/search-alerts.css';
    wp_enqueue_style(
        'autoagora-search-alerts',
        get_stylesheet_directory_uri() . '/includes/search-alerts/search-alerts.css',
        array(),
        file_exists($path) ? filemtime($path) : BRICKS_CHILD_THEME_VERSION
    );
}
