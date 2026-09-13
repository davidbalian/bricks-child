<?php
/** WordPress administrator page for the scoped dealership onboarding token. */

if (!defined('ABSPATH')) {
    exit;
}

final class AutoAgora_Dealer_Onboarding_Admin
{
    private const PAGE = 'autoagora-dealer-onboarding-api';

    public static function register(): void
    {
        add_action('admin_menu', array(__CLASS__, 'menu'));
        add_action('admin_post_autoagora_generate_dealer_onboarding_token', array(__CLASS__, 'generate'));
        add_action('admin_post_autoagora_revoke_dealer_onboarding_token', array(__CLASS__, 'revoke'));
    }

    public static function menu(): void
    {
        add_management_page(
            __('Dealer Onboarding API', 'bricks-child'),
            __('Dealer Onboarding API', 'bricks-child'),
            'manage_options',
            self::PAGE,
            array(__CLASS__, 'render')
        );
    }

    public static function generate(): void
    {
        self::requirePermission();
        check_admin_referer('autoagora_generate_dealer_onboarding_token');
        $token = AutoAgora_Dealer_Onboarding_Token::generate(get_current_user_id());
        if (is_wp_error($token)) {
            wp_die(esc_html($token->get_error_message()));
        }

        set_transient(self::transientKey(), $token, 5 * MINUTE_IN_SECONDS);
        self::redirect(array('generated' => 1));
    }

    public static function revoke(): void
    {
        self::requirePermission();
        check_admin_referer('autoagora_revoke_dealer_onboarding_token');
        AutoAgora_Dealer_Onboarding_Token::revoke();
        delete_transient(self::transientKey());
        self::redirect(array('revoked' => 1));
    }

    public static function render(): void
    {
        self::requirePermission();
        $status = AutoAgora_Dealer_Onboarding_Token::status();
        $new_token = get_transient(self::transientKey());
        if (is_string($new_token) && $new_token !== '') {
            delete_transient(self::transientKey());
        } else {
            $new_token = '';
        }

        echo '<div class="wrap"><h1>' . esc_html__('Dealer Onboarding API', 'bricks-child') . '</h1>';
        echo '<p>' . esc_html__('This dedicated token can access only AutoAgora dealership onboarding endpoints. It does not enable WordPress Application Passwords.', 'bricks-child') . '</p>';

        if (isset($_GET['revoked'])) {
            echo '<div class="notice notice-success"><p>' . esc_html__('The onboarding token was revoked.', 'bricks-child') . '</p></div>';
        }
        if ($new_token !== '') {
            echo '<div class="notice notice-warning"><p><strong>' . esc_html__('Copy this token now. It will not be shown again.', 'bricks-child') . '</strong></p>';
            echo '<input id="autoagora-onboarding-token" type="text" class="large-text code" readonly autocomplete="off" value="' . esc_attr($new_token) . '">';
            echo '</div>';
        }

        if (!empty($status['configured'])) {
            $creator = get_userdata((int) $status['created_by']);
            echo '<p><strong>' . esc_html__('Status:', 'bricks-child') . '</strong> ' . esc_html__('Configured', 'bricks-child') . '</p>';
            echo '<p>' . esc_html(sprintf(
                __('Created %1$s by %2$s. Token prefix: %3$s…', 'bricks-child'),
                (string) $status['created_at'],
                $creator instanceof WP_User ? $creator->display_name : __('Unknown administrator', 'bricks-child'),
                (string) $status['prefix']
            )) . '</p>';
        } else {
            echo '<p><strong>' . esc_html__('Status:', 'bricks-child') . '</strong> ' . esc_html__('Not configured', 'bricks-child') . '</p>';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        echo '<input type="hidden" name="action" value="autoagora_generate_dealer_onboarding_token">';
        wp_nonce_field('autoagora_generate_dealer_onboarding_token');
        submit_button(
            !empty($status['configured']) ? __('Replace onboarding token', 'bricks-child') : __('Generate onboarding token', 'bricks-child'),
            'primary',
            'submit',
            false
        );
        echo '</form>';

        if (!empty($status['configured'])) {
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top: 12px;">';
            echo '<input type="hidden" name="action" value="autoagora_revoke_dealer_onboarding_token">';
            wp_nonce_field('autoagora_revoke_dealer_onboarding_token');
            submit_button(__('Revoke onboarding token', 'bricks-child'), 'secondary', 'submit', false);
            echo '</form>';
        }
        echo '</div>';
    }

    private static function requirePermission(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission to manage dealership onboarding access.', 'bricks-child'));
        }
    }

    private static function transientKey(): string
    {
        return 'autoagora_dealer_onboarding_new_token_' . get_current_user_id();
    }

    /** @param array<string,int> $args */
    private static function redirect(array $args): void
    {
        wp_safe_redirect(add_query_arg(array_merge(array('page' => self::PAGE), $args), admin_url('tools.php')));
        exit;
    }
}
