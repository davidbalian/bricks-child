<?php
/** Local fixture only; never deploy this file to the live theme or production MU plugins. */
defined('ABSPATH') || exit;
if (!defined('GOOGLE_MAPS_API_KEY')) { define('GOOGLE_MAPS_API_KEY', ''); }
if (wp_get_environment_type() !== 'local' || !defined('WP_HOME') || !in_array(wp_parse_url(WP_HOME, PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) {
    wp_die('This fixture may run only on loopback in the local environment.');
}
add_filter('pre_option_blog_public', '__return_zero');
add_filter('pre_wp_mail', '__return_true');
add_filter('pre_http_request', static function ($pre, $args, $url) {
    return new WP_Error('staging_outbound_disabled', 'External WordPress HTTP integrations are disabled in this fixture.');
}, -100, 3);
add_action('send_headers', static function () {
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    // Keep browser-side maps, analytics, fonts and other external integrations offline too.
    header("Content-Security-Policy: default-src 'self' data: blob:; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self' data:; connect-src 'self'; frame-src 'none'; form-action 'self'; frame-ancestors 'none'");
});
add_filter('autoagora_dealer_local_image_fixture', static function ($fixture, $url) {
    if (in_array($url, ['https://images.example.com/fixture-one.png', 'https://images.example.com/fixture-two.png'], true)) {
        return file_get_contents(get_stylesheet_directory() . '/screenshot.png');
    }
    if ($url === 'https://images.example.com/oversize.png') { return str_repeat('x', 12582913); }
    if ($url === 'https://images.example.com/not-an-image.png') { return 'This is not an image.'; }
    if ($url === 'https://images.example.com/truncated.png') { return substr(file_get_contents(get_stylesheet_directory() . '/screenshot.png'), 0, 33); }
    return $fixture;
}, 10, 2);
add_filter('autoagora_dealer_api_can_save', static function ($allowed, $dealer) {
    return get_user_meta($dealer, '_ag_fixture_quota_blocked', true)
        ? new WP_Error('fixture_quota', 'Synthetic allowance has been exhausted.', ['status' => 403]) : $allowed;
}, 10, 2);
add_action('init', static function () {
    // Production owns this schema outside the theme. A minimal fixture is explicitly used here.
    register_post_type('car', ['public' => true, 'show_in_rest' => true, 'supports' => ['title', 'custom-fields', 'author'], 'rewrite' => ['slug' => 'car']]);
    register_taxonomy('car_make', 'car', ['hierarchical' => true, 'public' => true, 'show_in_rest' => true]);
    if (function_exists('acf_add_local_field_group')) {
        $fields = [];
        foreach (['make', 'model', 'year', 'mileage', 'price', 'availability', 'engine_capacity', 'fuel_type', 'transmission', 'body_type', 'drive_type', 'hp', 'exterior_color', 'interior_color', 'description', 'number_of_doors', 'number_of_seats', 'motuntil', 'numowners', 'isantique', 'car_address', 'car_city', 'car_district', 'car_latitude', 'car_longitude', 'listing_state'] as $name) {
            $fields[] = ['key' => 'field_ag_fixture_' . $name, 'name' => $name, 'label' => $name, 'type' => 'text'];
        }
        foreach (['extras', 'vehiclehistory'] as $name) { $fields[] = ['key' => 'field_ag_fixture_' . $name, 'name' => $name, 'label' => $name, 'type' => 'checkbox']; }
        $fields[] = ['key' => 'field_ag_fixture_car_images', 'name' => 'car_images', 'label' => 'Photos', 'type' => 'gallery', 'return_format' => 'id'];
        acf_add_local_field_group(['key' => 'group_ag_fixture', 'title' => 'Local car fixture', 'fields' => $fields, 'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'car']]]]);
    }
});
