<?php
/**
 * Dealership page call button shortcode.
 *
 * Usage:
 *   [dealership_page_call_button]
 *   [dealership_page_call_button user_id="123"]
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Renders a tel link for the dealership user (same phone rules as the car listing call button).
 */
final class DealershipPageCallButtonShortcode
{
    public static function init(): void
    {
        add_shortcode('dealership_page_call_button', array(__CLASS__, 'render'));
    }

    /**
     * @param array<string, mixed> $atts
     */
    public static function render($atts): string
    {
        $atts = shortcode_atts(
            array(
                'user_id' => 0,
                'post_id' => 0,
            ),
            $atts,
            'dealership_page_call_button'
        );

        $explicit_user_id = (int) $atts['user_id'];
        $explicit_post_id = (int) $atts['post_id'];

        $dealership_user_id = self::resolve_dealership_user_id($explicit_user_id, $explicit_post_id);
        if ($dealership_user_id <= 0) {
            return '';
        }

        $user_object = get_user_by('ID', $dealership_user_id);
        if (!$user_object) {
            return '';
        }

        $phone = self::build_phone_display_for_user($dealership_user_id, $user_object);
        if ($phone === null) {
            return '';
        }

        ob_start();
        ?>
        <a href="<?php echo esc_attr($phone['tel_href']); ?>"
           class="brx-button dealership-page-call-button"
           id="dealership-page-call-button"
           style="
            padding: .75rem 1.5rem;
            background-color: var(--bricks-color-iztoge);
            border-radius: var(--radius-sm);
            display: flex;
            justify-content: center;
            align-items: center;
            column-gap: .5rem;
            text-decoration: none;
            color: #ffffff;
        ">
            <i class="fas fa-phone" style="color: #ffffff; font-size: 1rem;"></i>
            <?php echo esc_html($phone['label']); ?>
        </a>
        <?php
        return (string) ob_get_clean();
    }

    private static function resolve_dealership_user_id(int $explicit_user_id, int $explicit_post_id): int
    {
        if ($explicit_user_id > 0) {
            $user = get_userdata($explicit_user_id);
            if ($user && in_array('dealership', (array) $user->roles, true)) {
                return $explicit_user_id;
            }
            return 0;
        }

        $post_id = $explicit_post_id > 0 ? $explicit_post_id : self::resolve_context_post_id();
        if ($post_id <= 0) {
            return 0;
        }

        $author_id = (int) get_post_field('post_author', $post_id);
        if ($author_id <= 0) {
            return 0;
        }

        $user = get_userdata($author_id);
        if (!$user || !in_array('dealership', (array) $user->roles, true)) {
            return 0;
        }

        return $author_id;
    }

    private static function resolve_context_post_id(): int
    {
        $post_id = (int) get_the_ID();
        if ($post_id > 0) {
            return $post_id;
        }

        global $wp_query;
        if (isset($wp_query->queried_object_id) && (int) $wp_query->queried_object_id > 0) {
            return (int) $wp_query->queried_object_id;
        }

        global $post;
        if (isset($post->ID) && (int) $post->ID > 0) {
            return (int) $post->ID;
        }

        return 0;
    }

    /**
     * @return array{tel_href: string, label: string}|null
     */
    private static function build_phone_display_for_user(int $user_id, WP_User $user_object): ?array
    {
        $tel_link_number = $user_object->user_login;
        $tel_link_number_secondary = '';
        if (function_exists('get_field')) {
            $tel_link_number_secondary = (string) get_field('secondary_phone', 'user_' . $user_id);
        }

        if ($tel_link_number_secondary !== '') {
            $raw_phone = $tel_link_number_secondary;
            $display_phone = preg_replace('/[^0-9+]/', '', $tel_link_number_secondary);
            $display_phone = preg_replace('/^(.{3})(.+)/', '$1 $2', $display_phone);
        } else {
            $raw_phone = $tel_link_number;
            $display_phone = preg_replace('/[^0-9+]/', '', $tel_link_number);
            $display_phone = preg_replace('/^(.{3})(.+)/', '$1 $2', $display_phone);
        }

        if ($raw_phone === '' || $display_phone === '') {
            return null;
        }

        return array(
            'tel_href' => 'tel:+' . $raw_phone,
            'label'    => '+' . $display_phone,
        );
    }
}

DealershipPageCallButtonShortcode::init();
