<?php
/**
 * Shortcode for rendering a user's account logo (e.g. dealership logo).
 *
 * Usage examples:
 *  - [dealership_logo]                   // Uses current post author as user.
 *  - [dealership_logo user_id="123"]     // Explicit user ID.
 *  - [dealership_logo size="medium"]     // Different image size.
 *
 * @package Bricks Child
 */

if (!defined('ABSPATH')) {
    exit;
}

final class DealershipLogoShortcode
{
    /**
     * Registers the shortcode.
     */
    public static function init(): void
    {
        add_shortcode('dealership_logo', array(__CLASS__, 'render'));
    }

    /**
     * Renders the logo <img> tag or an empty string if no logo.
     *
     * @param array<string,mixed> $atts Shortcode attributes.
     */
    public static function render($atts): string
    {
        $atts = shortcode_atts(
            array(
                'user_id' => 0,
                'size'    => 'thumbnail',
                'class'   => 'dealership-logo',
            ),
            $atts,
            'dealership_logo'
        );

        $user_id = (int) $atts['user_id'];

        // If no explicit user_id, fall back to current post author.
        if ($user_id <= 0) {
            $post_id = get_the_ID();

            if (!empty($post_id)) {
                $author_id = (int) get_post_field('post_author', $post_id);
                if ($author_id > 0) {
                    $user_id = $author_id;
                }
            }
        }

        if ($user_id <= 0) {
            return '';
        }

        // Ensure the manager class is available.
        if (!class_exists('UserLogoManager')) {
            require_once get_stylesheet_directory() . '/includes/user-account/my-account/UserLogoManager.php';
        }

        $manager  = new UserLogoManager();
        $logo_url = $manager->getUserLogoUrl($user_id, (string) $atts['size']);

        if ($logo_url === '') {
            return '';
        }

        $class_attr = $atts['class'] !== ''
            ? ' class="' . esc_attr((string) $atts['class']) . '"'
            : '';

        return sprintf(
            '<img src="%s"%s alt="%s">',
            esc_url($logo_url),
            $class_attr,
            esc_attr__('Dealership logo', 'bricks-child')
        );
    }
}

DealershipLogoShortcode::init();


