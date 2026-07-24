<?php
/**
 * Dealer profile public entity and SEO surface.
 *
 * Dealer profiles are public business profiles. They are intentionally separate
 * from dealership user accounts until a real dealer claims the profile.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

if (!defined('AUTOAGORA_DEALER_PROFILE_POST_TYPE')) {
    define('AUTOAGORA_DEALER_PROFILE_POST_TYPE', 'dealer_profile');
}

if (!defined('AUTOAGORA_DEALER_PROFILE_REWRITE_VERSION')) {
    define('AUTOAGORA_DEALER_PROFILE_REWRITE_VERSION', '2026-07-24-1');
}

function autoagora_dealer_profile_city_options(): array
{
    return array(
        'nicosia'  => __('Nicosia', 'bricks-child'),
        'limassol' => __('Limassol', 'bricks-child'),
        'larnaca'  => __('Larnaca', 'bricks-child'),
        'paphos'   => __('Paphos', 'bricks-child'),
        'famagusta'=> __('Famagusta', 'bricks-child'),
    );
}

function autoagora_register_dealer_profile_post_type(): void
{
    register_post_type(
        AUTOAGORA_DEALER_PROFILE_POST_TYPE,
        array(
            'labels' => array(
                'name'               => __('Dealer profiles', 'bricks-child'),
                'singular_name'      => __('Dealer profile', 'bricks-child'),
                'add_new_item'       => __('Add dealer profile', 'bricks-child'),
                'edit_item'          => __('Edit dealer profile', 'bricks-child'),
                'new_item'           => __('New dealer profile', 'bricks-child'),
                'view_item'          => __('View dealer profile', 'bricks-child'),
                'search_items'       => __('Search dealer profiles', 'bricks-child'),
                'not_found'          => __('No dealer profiles found.', 'bricks-child'),
                'not_found_in_trash' => __('No dealer profiles found in Trash.', 'bricks-child'),
                'menu_name'          => __('Dealer Profiles', 'bricks-child'),
            ),
            'public'             => true,
            'publicly_queryable' => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_in_rest'       => true,
            'menu_icon'          => 'dashicons-store',
            'has_archive'        => 'dealers',
            'rewrite'            => array(
                'slug'       => 'dealers',
                'with_front' => false,
            ),
            'supports'           => array('title', 'editor', 'thumbnail', 'custom-fields'),
            'capability_type'    => 'post',
        )
    );
}
add_action('init', 'autoagora_register_dealer_profile_post_type');

function autoagora_register_dealer_profile_rewrite_rules(): void
{
    add_rewrite_rule(
        '^dealers/(nicosia|limassol|larnaca|paphos|famagusta)/?$',
        'index.php?post_type=' . AUTOAGORA_DEALER_PROFILE_POST_TYPE . '&dealer_city=$matches[1]',
        'top'
    );
}
add_action('init', 'autoagora_register_dealer_profile_rewrite_rules', 12);

function autoagora_dealer_profile_query_vars(array $vars): array
{
    $vars[] = 'dealer_city';

    return $vars;
}
add_filter('query_vars', 'autoagora_dealer_profile_query_vars');

function autoagora_dealer_profile_maybe_flush_rewrites(): void
{
    if (get_option('autoagora_dealer_profile_rewrite_version') === AUTOAGORA_DEALER_PROFILE_REWRITE_VERSION) {
        return;
    }

    flush_rewrite_rules(false);
    update_option('autoagora_dealer_profile_rewrite_version', AUTOAGORA_DEALER_PROFILE_REWRITE_VERSION);
}
add_action('admin_init', 'autoagora_dealer_profile_maybe_flush_rewrites');

function autoagora_sanitize_dealer_profile_url($value): string
{
    $value = trim((string) $value);
    if ($value === '') {
        return '';
    }

    $url = esc_url_raw($value);
    $parts = wp_parse_url($url);
    if (empty($parts['scheme']) || !in_array($parts['scheme'], array('http', 'https'), true)) {
        return '';
    }

    return $url;
}

function autoagora_sanitize_dealer_profile_claim_status($value): string
{
    $value = sanitize_key((string) $value);
    $allowed = array('unclaimed', 'pending', 'claimed', 'rejected');

    return in_array($value, $allowed, true) ? $value : 'unclaimed';
}

function autoagora_sanitize_dealer_profile_bool($value): bool
{
    return in_array($value, array(true, 1, '1', 'yes', 'on', 'true'), true);
}

function autoagora_register_dealer_profile_meta(): void
{
    $meta = array(
        'dealer_city'            => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        'dealer_city_slug'       => array('type' => 'string', 'sanitize_callback' => 'sanitize_title'),
        'dealer_district'        => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        'dealer_address'         => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        'dealer_maps_url'        => array('type' => 'string', 'sanitize_callback' => 'autoagora_sanitize_dealer_profile_url'),
        'dealer_website'         => array('type' => 'string', 'sanitize_callback' => 'autoagora_sanitize_dealer_profile_url'),
        'dealer_instagram'       => array('type' => 'string', 'sanitize_callback' => 'autoagora_sanitize_dealer_profile_url'),
        'dealer_facebook'        => array('type' => 'string', 'sanitize_callback' => 'autoagora_sanitize_dealer_profile_url'),
        'dealer_phone'           => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        'dealer_source_name'     => array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        'dealer_source_url'      => array('type' => 'string', 'sanitize_callback' => 'autoagora_sanitize_dealer_profile_url'),
        'dealer_import_source_id'=> array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
        'dealer_claim_status'    => array('type' => 'string', 'sanitize_callback' => 'autoagora_sanitize_dealer_profile_claim_status'),
        'dealer_claimed_user_id' => array('type' => 'integer', 'sanitize_callback' => 'absint'),
        'dealer_indexable'       => array('type' => 'boolean', 'sanitize_callback' => 'autoagora_sanitize_dealer_profile_bool'),
        'dealer_last_verified_at'=> array('type' => 'string', 'sanitize_callback' => 'sanitize_text_field'),
    );

    foreach ($meta as $key => $args) {
        register_post_meta(
            AUTOAGORA_DEALER_PROFILE_POST_TYPE,
            $key,
            array(
                'type'              => $args['type'],
                'single'            => true,
                'show_in_rest'      => true,
                'sanitize_callback' => $args['sanitize_callback'],
                'auth_callback'     => static function () {
                    return current_user_can('edit_posts');
                },
            )
        );
    }
}
add_action('init', 'autoagora_register_dealer_profile_meta');

function autoagora_dealer_profile_normalize_city_slug(string $city): string
{
    $city = trim($city);
    if ($city === '') {
        return '';
    }

    $slug = sanitize_title($city);
    $aliases = array(
        'lefkosia' => 'nicosia',
        'lemesos'  => 'limassol',
        'ammochostos' => 'famagusta',
    );

    return $aliases[$slug] ?? $slug;
}

function autoagora_dealer_profile_get_meta(int $post_id, string $key): string
{
    $value = get_post_meta($post_id, $key, true);

    return is_scalar($value) ? (string) $value : '';
}

function autoagora_dealer_profile_get_claim_status(int $post_id): string
{
    return autoagora_sanitize_dealer_profile_claim_status(
        autoagora_dealer_profile_get_meta($post_id, 'dealer_claim_status')
    );
}

function autoagora_dealer_profile_get_claimed_user_id(int $post_id): int
{
    return absint(get_post_meta($post_id, 'dealer_claimed_user_id', true));
}

function autoagora_dealer_profile_is_claimed(int $post_id): bool
{
    return autoagora_dealer_profile_get_claim_status($post_id) === 'claimed'
        && autoagora_dealer_profile_get_claimed_user_id($post_id) > 0;
}

function autoagora_dealer_profile_has_public_quality(int $post_id): bool
{
    $name = trim(get_the_title($post_id));
    $location = trim(
        autoagora_dealer_profile_get_meta($post_id, 'dealer_city')
        . autoagora_dealer_profile_get_meta($post_id, 'dealer_district')
        . autoagora_dealer_profile_get_meta($post_id, 'dealer_address')
    );
    $presence = trim(
        autoagora_dealer_profile_get_meta($post_id, 'dealer_website')
        . autoagora_dealer_profile_get_meta($post_id, 'dealer_maps_url')
        . autoagora_dealer_profile_get_meta($post_id, 'dealer_phone')
        . autoagora_dealer_profile_get_meta($post_id, 'dealer_instagram')
        . autoagora_dealer_profile_get_meta($post_id, 'dealer_facebook')
    );

    return $name !== '' && $location !== '' && $presence !== '';
}

function autoagora_dealer_profile_is_indexable(int $post_id): bool
{
    if (get_post_status($post_id) !== 'publish') {
        return false;
    }

    $explicitly_indexable = autoagora_sanitize_dealer_profile_bool(
        get_post_meta($post_id, 'dealer_indexable', true)
    );

    return $explicitly_indexable && autoagora_dealer_profile_has_public_quality($post_id);
}

function autoagora_dealer_profile_claim_url(int $post_id): string
{
    return add_query_arg(
        array(
            'claim_dealer_profile' => $post_id,
            'dealer' => sanitize_title(get_the_title($post_id)),
        ),
        home_url('/contact/')
    );
}

function autoagora_dealer_profile_city_archive_url(string $city_slug): string
{
    return trailingslashit(home_url('/dealers/' . sanitize_title($city_slug)));
}

function autoagora_dealer_profile_active_listing_count(int $post_id): int
{
    $user_id = autoagora_dealer_profile_get_claimed_user_id($post_id);
    if ($user_id <= 0) {
        return 0;
    }

    $args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => 1,
        'fields'         => 'ids',
    );

    if (class_exists('ListingStateManager')) {
        $args['meta_query'] = array(ListingStateManager::meta_query_active_clause());
    }

    $query = new WP_Query($args);

    return (int) $query->found_posts;
}

function autoagora_dealer_profile_active_listings_query(int $post_id, int $limit = 12): ?WP_Query
{
    $user_id = autoagora_dealer_profile_get_claimed_user_id($post_id);
    if ($user_id <= 0) {
        return null;
    }

    $args = array(
        'post_type'      => 'car',
        'post_status'    => 'publish',
        'author'         => $user_id,
        'posts_per_page' => max(1, min(24, $limit)),
        'orderby'        => 'date',
        'order'          => 'DESC',
    );

    if (class_exists('ListingStateManager')) {
        $args['meta_query'] = array(ListingStateManager::meta_query_active_clause());
    }

    return new WP_Query($args);
}

function autoagora_dealer_profile_contact_links(int $post_id): array
{
    $links = array();
    $fields = array(
        'dealer_website'   => __('Website', 'bricks-child'),
        'dealer_maps_url'  => __('Map', 'bricks-child'),
        'dealer_instagram' => __('Instagram', 'bricks-child'),
        'dealer_facebook'  => __('Facebook', 'bricks-child'),
    );

    foreach ($fields as $key => $label) {
        $url = autoagora_dealer_profile_get_meta($post_id, $key);
        if ($url !== '') {
            $links[] = array(
                'label' => $label,
                'url'   => $url,
                'key'   => $key,
            );
        }
    }

    $phone = autoagora_dealer_profile_get_meta($post_id, 'dealer_phone');
    if ($phone !== '') {
        $tel = preg_replace('/[^0-9+]/', '', $phone);
        if (is_string($tel) && $tel !== '') {
            $links[] = array(
                'label' => __('Call', 'bricks-child'),
                'url'   => 'tel:' . $tel,
                'key'   => 'dealer_phone',
            );
        }
    }

    return $links;
}

function autoagora_dealer_profile_pre_get_posts(WP_Query $query): void
{
    if (is_admin() || !$query->is_main_query()) {
        return;
    }

    $post_type = $query->get('post_type');
    $city_slug = sanitize_title((string) get_query_var('dealer_city'));
    $is_dealer_archive = $query->is_post_type_archive(AUTOAGORA_DEALER_PROFILE_POST_TYPE)
        || ($post_type === AUTOAGORA_DEALER_PROFILE_POST_TYPE && $city_slug !== '');

    if (!$is_dealer_archive) {
        return;
    }

    $query->set('post_type', AUTOAGORA_DEALER_PROFILE_POST_TYPE);
    $query->set('posts_per_page', 24);
    $query->set('orderby', 'title');
    $query->set('order', 'ASC');

    if ($city_slug === '') {
        return;
    }

    $meta_query = (array) $query->get('meta_query');
    $meta_query[] = array(
        'key'     => 'dealer_city_slug',
        'value'   => $city_slug,
        'compare' => '=',
    );
    $query->set('meta_query', $meta_query);
}
add_action('pre_get_posts', 'autoagora_dealer_profile_pre_get_posts');

function autoagora_add_dealer_profile_meta_boxes(): void
{
    add_meta_box(
        'autoagora-dealer-profile-details',
        __('Dealer profile details', 'bricks-child'),
        'autoagora_render_dealer_profile_details_meta_box',
        AUTOAGORA_DEALER_PROFILE_POST_TYPE,
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'autoagora_add_dealer_profile_meta_boxes');

function autoagora_render_dealer_profile_text_input(int $post_id, string $key, string $label, string $type = 'text', string $placeholder = ''): void
{
    $value = autoagora_dealer_profile_get_meta($post_id, $key);
    ?>
    <p class="autoagora-dealer-profile-admin-field">
        <label for="<?php echo esc_attr($key); ?>"><strong><?php echo esc_html($label); ?></strong></label>
        <input
            type="<?php echo esc_attr($type); ?>"
            id="<?php echo esc_attr($key); ?>"
            name="autoagora_dealer_profile[<?php echo esc_attr($key); ?>]"
            value="<?php echo esc_attr($value); ?>"
            placeholder="<?php echo esc_attr($placeholder); ?>"
            class="widefat"
        />
    </p>
    <?php
}

function autoagora_render_dealer_profile_details_meta_box(WP_Post $post): void
{
    wp_nonce_field('autoagora_save_dealer_profile', 'autoagora_dealer_profile_nonce');

    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_city', __('City', 'bricks-child'), 'text', 'Nicosia');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_district', __('District', 'bricks-child'), 'text', 'Nicosia');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_address', __('Address', 'bricks-child'), 'text', 'Full public address');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_website', __('Website URL', 'bricks-child'), 'url', 'https://example.com');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_maps_url', __('Google Maps URL', 'bricks-child'), 'url', 'https://maps.google.com/...');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_instagram', __('Instagram URL', 'bricks-child'), 'url', 'https://instagram.com/...');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_facebook', __('Facebook URL', 'bricks-child'), 'url', 'https://facebook.com/...');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_phone', __('Phone number', 'bricks-child'), 'tel', '+357...');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_source_name', __('Source name', 'bricks-child'), 'text', 'CYTA Yellow Pages');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_source_url', __('Source URL', 'bricks-child'), 'url', 'https://...');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_import_source_id', __('Import source ID', 'bricks-child'), 'text', 'source-specific-id');
    autoagora_render_dealer_profile_text_input($post->ID, 'dealer_last_verified_at', __('Last verified date', 'bricks-child'), 'date', '2026-07-24');

    $claim_status = autoagora_dealer_profile_get_claim_status($post->ID);
    $claimed_user_id = autoagora_dealer_profile_get_claimed_user_id($post->ID);
    $indexable = autoagora_sanitize_dealer_profile_bool(get_post_meta($post->ID, 'dealer_indexable', true));
    ?>
    <p class="autoagora-dealer-profile-admin-field">
        <label for="dealer_claim_status"><strong><?php esc_html_e('Claim status', 'bricks-child'); ?></strong></label>
        <select id="dealer_claim_status" name="autoagora_dealer_profile[dealer_claim_status]" class="widefat">
            <?php foreach (array('unclaimed', 'pending', 'claimed', 'rejected') as $status) : ?>
                <option value="<?php echo esc_attr($status); ?>" <?php selected($claim_status, $status); ?>>
                    <?php echo esc_html(ucfirst($status)); ?>
                </option>
            <?php endforeach; ?>
        </select>
    </p>
    <p class="autoagora-dealer-profile-admin-field">
        <label for="dealer_claimed_user_id"><strong><?php esc_html_e('Claimed user ID', 'bricks-child'); ?></strong></label>
        <input
            type="number"
            min="0"
            id="dealer_claimed_user_id"
            name="autoagora_dealer_profile[dealer_claimed_user_id]"
            value="<?php echo esc_attr((string) $claimed_user_id); ?>"
            class="widefat"
        />
    </p>
    <p class="autoagora-dealer-profile-admin-field">
        <label>
            <input
                type="checkbox"
                name="autoagora_dealer_profile[dealer_indexable]"
                value="1"
                <?php checked($indexable); ?>
            />
            <strong><?php esc_html_e('Allow this profile to be indexed', 'bricks-child'); ?></strong>
        </label>
        <span class="description">
            <?php esc_html_e('The page still noindexes itself unless it has a name, location, and at least one public contact/presence field.', 'bricks-child'); ?>
        </span>
    </p>
    <style>
        .autoagora-dealer-profile-admin-field {
            margin: 0 0 14px;
        }
        .autoagora-dealer-profile-admin-field label {
            display: block;
            margin-bottom: 4px;
        }
    </style>
    <?php
}

function autoagora_save_dealer_profile_meta(int $post_id): void
{
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (wp_is_post_revision($post_id)) {
        return;
    }
    if (get_post_type($post_id) !== AUTOAGORA_DEALER_PROFILE_POST_TYPE) {
        return;
    }
    if (!isset($_POST['autoagora_dealer_profile_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['autoagora_dealer_profile_nonce'])), 'autoagora_save_dealer_profile')) {
        return;
    }
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    $raw = isset($_POST['autoagora_dealer_profile']) ? wp_unslash($_POST['autoagora_dealer_profile']) : array();
    if (!is_array($raw)) {
        $raw = array();
    }

    $url_keys = array('dealer_maps_url', 'dealer_website', 'dealer_instagram', 'dealer_facebook', 'dealer_source_url');
    $text_keys = array('dealer_city', 'dealer_district', 'dealer_address', 'dealer_phone', 'dealer_source_name', 'dealer_import_source_id', 'dealer_last_verified_at');

    foreach ($text_keys as $key) {
        $value = isset($raw[$key]) ? sanitize_text_field((string) $raw[$key]) : '';
        $value !== '' ? update_post_meta($post_id, $key, $value) : delete_post_meta($post_id, $key);
    }

    foreach ($url_keys as $key) {
        $value = isset($raw[$key]) ? autoagora_sanitize_dealer_profile_url($raw[$key]) : '';
        $value !== '' ? update_post_meta($post_id, $key, $value) : delete_post_meta($post_id, $key);
    }

    $claim_status = isset($raw['dealer_claim_status'])
        ? autoagora_sanitize_dealer_profile_claim_status($raw['dealer_claim_status'])
        : 'unclaimed';
    update_post_meta($post_id, 'dealer_claim_status', $claim_status);

    $claimed_user_id = isset($raw['dealer_claimed_user_id']) ? absint($raw['dealer_claimed_user_id']) : 0;
    $claimed_user_id > 0
        ? update_post_meta($post_id, 'dealer_claimed_user_id', $claimed_user_id)
        : delete_post_meta($post_id, 'dealer_claimed_user_id');

    update_post_meta($post_id, 'dealer_indexable', !empty($raw['dealer_indexable']) ? '1' : '0');

    $city = isset($raw['dealer_city']) ? sanitize_text_field((string) $raw['dealer_city']) : '';
    $city_slug = autoagora_dealer_profile_normalize_city_slug($city);
    $city_slug !== ''
        ? update_post_meta($post_id, 'dealer_city_slug', $city_slug)
        : delete_post_meta($post_id, 'dealer_city_slug');
}
add_action('save_post_' . AUTOAGORA_DEALER_PROFILE_POST_TYPE, 'autoagora_save_dealer_profile_meta');

function autoagora_render_dealer_profile_card(int $post_id): void
{
    $city = autoagora_dealer_profile_get_meta($post_id, 'dealer_city');
    $address = autoagora_dealer_profile_get_meta($post_id, 'dealer_address');
    $is_claimed = autoagora_dealer_profile_is_claimed($post_id);
    $listing_count = autoagora_dealer_profile_active_listing_count($post_id);
    $links = autoagora_dealer_profile_contact_links($post_id);
    ?>
    <article class="dealer-profile-card">
        <div class="dealer-profile-card__body">
            <div class="dealer-profile-card__heading">
                <h2><a href="<?php echo esc_url(get_permalink($post_id)); ?>"><?php echo esc_html(get_the_title($post_id)); ?></a></h2>
                <span class="dealer-profile-badge <?php echo $is_claimed ? 'is-claimed' : 'is-unclaimed'; ?>">
                    <?php echo esc_html($is_claimed ? __('Claimed', 'bricks-child') : __('Unclaimed', 'bricks-child')); ?>
                </span>
            </div>
            <?php if ($city !== '' || $address !== '') : ?>
                <p class="dealer-profile-card__location">
                    <?php echo esc_html(trim($address !== '' ? $address : $city)); ?>
                </p>
            <?php endif; ?>
            <p class="dealer-profile-card__count">
                <?php
                echo esc_html(
                    sprintf(
                        /* translators: %d: active listing count */
                        _n('%d active listing', '%d active listings', $listing_count, 'bricks-child'),
                        $listing_count
                    )
                );
                ?>
            </p>
            <?php if (!empty($links)) : ?>
                <div class="dealer-profile-card__links">
                    <?php foreach (array_slice($links, 0, 3) as $link) : ?>
                        <a href="<?php echo esc_url($link['url']); ?>" target="<?php echo strpos($link['url'], 'tel:') === 0 ? '_self' : '_blank'; ?>" rel="noopener noreferrer">
                            <?php echo esc_html($link['label']); ?>
                        </a>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
        </div>
        <a class="btn btn-primary dealer-profile-card__action" href="<?php echo esc_url(get_permalink($post_id)); ?>">
            <?php esc_html_e('View profile', 'bricks-child'); ?>
        </a>
    </article>
    <?php
}

function autoagora_dealer_profile_get_seo_context(): array
{
    if (is_singular(AUTOAGORA_DEALER_PROFILE_POST_TYPE)) {
        $post_id = get_queried_object_id();
        if (!$post_id) {
            return array();
        }

        $name = get_the_title($post_id);
        $city = autoagora_dealer_profile_get_meta($post_id, 'dealer_city');
        $place = $city !== '' ? $city . ', Cyprus' : 'Cyprus';
        $title = sprintf(
            /* translators: 1: dealer name, 2: place */
            __('%1$s - Car Dealer in %2$s | AutoAgora', 'bricks-child'),
            $name,
            $place
        );
        $description = sprintf(
            /* translators: 1: dealer name, 2: place */
            __('View %1$s dealership information, contact links, location details, and active car listings in %2$s on AutoAgora.', 'bricks-child'),
            $name,
            $place
        );

        return array(
            'title'       => $title,
            'description' => $description,
            'canonical'   => get_permalink($post_id),
            'robots'      => autoagora_dealer_profile_is_indexable($post_id) ? 'index, follow' : 'noindex, follow',
            'schema'      => autoagora_dealer_profile_structured_data($post_id),
        );
    }

    if (is_post_type_archive(AUTOAGORA_DEALER_PROFILE_POST_TYPE)) {
        $city_slug = sanitize_title((string) get_query_var('dealer_city'));
        $city_options = autoagora_dealer_profile_city_options();
        $city = $city_options[$city_slug] ?? '';
        $title = $city !== ''
            ? sprintf(__('Car Dealers in %s, Cyprus | AutoAgora', 'bricks-child'), $city)
            : __('Car Dealers in Cyprus | AutoAgora', 'bricks-child');
        $description = $city !== ''
            ? sprintf(__('Browse car dealer profiles in %s, Cyprus. Find dealership locations, websites, social links, and active car listings on AutoAgora.', 'bricks-child'), $city)
            : __('Browse car dealer profiles across Cyprus. Find dealership locations, websites, social links, and active car listings on AutoAgora.', 'bricks-child');
        $canonical = $city !== ''
            ? autoagora_dealer_profile_city_archive_url($city_slug)
            : trailingslashit(home_url('/dealers/'));

        return array(
            'title'       => $title,
            'description' => $description,
            'canonical'   => $canonical,
            'robots'      => 'index, follow',
            'schema'      => '',
        );
    }

    return array();
}

function autoagora_dealer_profile_structured_data(int $post_id): string
{
    if (!autoagora_dealer_profile_has_public_quality($post_id)) {
        return '';
    }

    $city = autoagora_dealer_profile_get_meta($post_id, 'dealer_city');
    $address = autoagora_dealer_profile_get_meta($post_id, 'dealer_address');
    $phone = autoagora_dealer_profile_get_meta($post_id, 'dealer_phone');
    $website = autoagora_dealer_profile_get_meta($post_id, 'dealer_website');
    $same_as = array_filter(
        array(
            autoagora_dealer_profile_get_meta($post_id, 'dealer_instagram'),
            autoagora_dealer_profile_get_meta($post_id, 'dealer_facebook'),
        )
    );

    $schema = array(
        '@context' => 'https://schema.org',
        '@type'    => 'AutoDealer',
        'name'     => get_the_title($post_id),
        'url'      => get_permalink($post_id),
        'areaServed' => array(
            '@type' => 'Country',
            'name'  => 'Cyprus',
        ),
    );

    if ($website !== '') {
        $schema['sameAs'] = array_values(array_unique(array_merge(array($website), $same_as)));
    } elseif (!empty($same_as)) {
        $schema['sameAs'] = array_values(array_unique($same_as));
    }

    if ($phone !== '') {
        $schema['telephone'] = $phone;
    }

    if ($address !== '' || $city !== '') {
        $schema['address'] = array_filter(
            array(
                '@type'           => 'PostalAddress',
                'streetAddress'   => $address,
                'addressLocality' => $city,
                'addressCountry'  => 'CY',
            )
        );
    }

    if (has_post_thumbnail($post_id)) {
        $image = wp_get_attachment_image_url(get_post_thumbnail_id($post_id), 'full');
        if ($image) {
            $schema['image'] = $image;
        }
    }

    $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    return $json !== false ? $json : '';
}

function autoagora_dealer_profile_filter_document_title_parts(array $parts): array
{
    $seo = autoagora_dealer_profile_get_seo_context();
    if (!empty($seo['title'])) {
        $parts['title'] = $seo['title'];
    }

    return $parts;
}
add_filter('document_title_parts', 'autoagora_dealer_profile_filter_document_title_parts', 20);

function autoagora_dealer_profile_filter_pre_get_document_title(string $title): string
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['title']) ? $seo['title'] : $title;
}
add_filter('pre_get_document_title', 'autoagora_dealer_profile_filter_pre_get_document_title', 20);

function autoagora_dealer_profile_filter_wpseo_title($title)
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['title']) ? $seo['title'] : $title;
}
add_filter('wpseo_title', 'autoagora_dealer_profile_filter_wpseo_title', 20);

function autoagora_dealer_profile_filter_wpseo_metadesc($description)
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['description']) ? $seo['description'] : $description;
}
add_filter('wpseo_metadesc', 'autoagora_dealer_profile_filter_wpseo_metadesc', 20);

function autoagora_dealer_profile_filter_wpseo_canonical($canonical)
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['canonical']) ? $seo['canonical'] : $canonical;
}
add_filter('wpseo_canonical', 'autoagora_dealer_profile_filter_wpseo_canonical', 20);

function autoagora_dealer_profile_filter_wpseo_robots($robots)
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['robots']) ? str_replace(' ', '', $seo['robots']) : $robots;
}
add_filter('wpseo_robots', 'autoagora_dealer_profile_filter_wpseo_robots', 20);

function autoagora_dealer_profile_filter_rank_math_title($title)
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['title']) ? $seo['title'] : $title;
}
add_filter('rank_math/frontend/title', 'autoagora_dealer_profile_filter_rank_math_title', 20);

function autoagora_dealer_profile_filter_rank_math_description($description)
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['description']) ? $seo['description'] : $description;
}
add_filter('rank_math/frontend/description', 'autoagora_dealer_profile_filter_rank_math_description', 20);

function autoagora_dealer_profile_filter_rank_math_canonical($canonical)
{
    $seo = autoagora_dealer_profile_get_seo_context();

    return !empty($seo['canonical']) ? $seo['canonical'] : $canonical;
}
add_filter('rank_math/frontend/canonical', 'autoagora_dealer_profile_filter_rank_math_canonical', 20);

function autoagora_dealer_profile_filter_rank_math_robots($robots)
{
    $seo = autoagora_dealer_profile_get_seo_context();
    if (empty($seo['robots'])) {
        return $robots;
    }

    if (is_array($robots)) {
        $robots['index'] = strpos($seo['robots'], 'noindex') !== false ? 'noindex' : 'index';
        $robots['follow'] = strpos($seo['robots'], 'nofollow') !== false ? 'nofollow' : 'follow';

        return $robots;
    }

    return str_replace(' ', '', $seo['robots']);
}
add_filter('rank_math/frontend/robots', 'autoagora_dealer_profile_filter_rank_math_robots', 20);

function autoagora_dealer_profile_output_seo_meta(): void
{
    $seo = autoagora_dealer_profile_get_seo_context();
    if (empty($seo)) {
        return;
    }

    if (!empty($seo['description'])) {
        echo '<meta name="description" content="' . esc_attr($seo['description']) . '">' . "\n";
    }
    if (!empty($seo['canonical'])) {
        echo '<link rel="canonical" href="' . esc_url($seo['canonical']) . '">' . "\n";
    }
    if (!empty($seo['robots'])) {
        echo '<meta name="robots" content="' . esc_attr($seo['robots']) . '">' . "\n";
    }
    if (!empty($seo['schema'])) {
        echo '<script type="application/ld+json">' . $seo['schema'] . '</script>' . "\n";
    }
}
add_action('wp_head', 'autoagora_dealer_profile_output_seo_meta', 2);

function autoagora_dealer_profile_filter_wp_robots(array $robots): array
{
    $seo = autoagora_dealer_profile_get_seo_context();
    if (empty($seo['robots'])) {
        return $robots;
    }

    if (strpos($seo['robots'], 'noindex') !== false) {
        unset($robots['index']);
        $robots['noindex'] = true;
    } else {
        unset($robots['noindex']);
        $robots['index'] = true;
    }

    unset($robots['nofollow'], $robots['follow']);
    $robots[strpos($seo['robots'], 'nofollow') !== false ? 'nofollow' : 'follow'] = true;

    return $robots;
}
add_filter('wp_robots', 'autoagora_dealer_profile_filter_wp_robots', 20);

function autoagora_dealer_profile_disable_default_canonical(): void
{
    if (is_singular(AUTOAGORA_DEALER_PROFILE_POST_TYPE) || is_post_type_archive(AUTOAGORA_DEALER_PROFILE_POST_TYPE)) {
        remove_action('wp_head', 'rel_canonical');
    }
}
add_action('wp', 'autoagora_dealer_profile_disable_default_canonical');

function autoagora_enqueue_dealer_profile_assets(): void
{
    if (!is_singular(AUTOAGORA_DEALER_PROFILE_POST_TYPE) && !is_post_type_archive(AUTOAGORA_DEALER_PROFILE_POST_TYPE)) {
        return;
    }

    $path = get_stylesheet_directory() . '/assets/css/dealer-profiles.css';
    if (!file_exists($path)) {
        return;
    }

    wp_enqueue_style(
        'autoagora-dealer-profiles',
        get_stylesheet_directory_uri() . '/assets/css/dealer-profiles.css',
        array('bricks-child-theme-css'),
        filemtime($path)
    );
}
add_action('wp_enqueue_scripts', 'autoagora_enqueue_dealer_profile_assets', 25);
