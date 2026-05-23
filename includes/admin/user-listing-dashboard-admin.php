<?php
/**
 * Admin dashboard for viewing per-user listing stats.
 *
 * Reuses the My Listings stats engine so admins see the same numbers users see
 * on the frontend account dashboard.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once dirname(__DIR__) . '/user-account/my-listings/MyListingsStatsManager.php';

final class AutoAgoraUserListingDashboardAdminPage
{
    private const SLUG = 'autoagora-user-listing-dashboard';
    private const MAX_USERS_PER_GROUP = 200;

    private MyListingsStatsManager $statsManager;

    private function __construct(MyListingsStatsManager $statsManager)
    {
        $this->statsManager = $statsManager;
    }

    public static function bootstrap(): void
    {
        $instance = new self(new MyListingsStatsManager());
        add_action('admin_menu', array($instance, 'registerMenu'));
    }

    public function registerMenu(): void
    {
        add_users_page(
            __('User Listing Dashboards', 'bricks-child'),
            __('User Listing Dashboards', 'bricks-child'),
            'manage_options',
            self::SLUG,
            array($this, 'renderPage')
        );
    }

    public function renderPage(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die(__('You do not have permission to access this page.', 'bricks-child'));
        }

        $selectedUserId = isset($_GET['user_id']) ? absint(wp_unslash($_GET['user_id'])) : 0;
        $selectedUser = $selectedUserId > 0 ? get_user_by('ID', $selectedUserId) : false;
        $search = isset($_GET['user_search']) ? sanitize_text_field(wp_unslash($_GET['user_search'])) : '';

        $dealershipUsers = $this->fetchUsers(true, $search);
        $nonDealershipUsers = $this->fetchUsers(false, $search);

        ?>
        <div class="wrap autoagora-user-listing-dashboard">
            <?php $this->renderStyles(); ?>
            <h1><?php esc_html_e('User listing dashboards', 'bricks-child'); ?></h1>
            <p class="description">
                <?php esc_html_e('View the same listing performance dashboard a user sees in My Listings, separated by dealership and non-dealership accounts.', 'bricks-child'); ?>
            </p>

            <form method="get" class="autoagora-user-listing-dashboard__search">
                <input type="hidden" name="page" value="<?php echo esc_attr(self::SLUG); ?>" />
                <label for="autoagora-user-search"><?php esc_html_e('Search users', 'bricks-child'); ?></label>
                <input
                    type="search"
                    id="autoagora-user-search"
                    name="user_search"
                    value="<?php echo esc_attr($search); ?>"
                    placeholder="<?php esc_attr_e('Name, username, or email', 'bricks-child'); ?>"
                />
                <?php submit_button(__('Search', 'bricks-child'), 'secondary', '', false); ?>
                <?php if ($search !== '') : ?>
                    <a class="button" href="<?php echo esc_url(admin_url('users.php?page=' . self::SLUG)); ?>">
                        <?php esc_html_e('Clear', 'bricks-child'); ?>
                    </a>
                <?php endif; ?>
            </form>

            <?php if ($selectedUserId > 0 && !$selectedUser) : ?>
                <div class="notice notice-error">
                    <p><?php esc_html_e('Selected user was not found.', 'bricks-child'); ?></p>
                </div>
            <?php elseif ($selectedUser instanceof WP_User) : ?>
                <?php $this->renderSelectedUserDashboard($selectedUser); ?>
            <?php endif; ?>

            <div class="autoagora-user-listing-dashboard__groups">
                <?php
                $this->renderUserGroup(
                    __('Dealership users', 'bricks-child'),
                    $dealershipUsers,
                    $selectedUserId,
                    $search,
                    true
                );
                $this->renderUserGroup(
                    __('Non-dealership users', 'bricks-child'),
                    $nonDealershipUsers,
                    $selectedUserId,
                    $search,
                    false
                );
                ?>
            </div>
        </div>
        <?php
    }

    /**
     * @return array<int, WP_User>
     */
    private function fetchUsers(bool $dealershipOnly, string $search): array
    {
        $args = array(
            'number'  => self::MAX_USERS_PER_GROUP,
            'orderby' => 'display_name',
            'order'   => 'ASC',
            'fields'  => 'all',
        );

        if ($dealershipOnly) {
            $args['role'] = 'dealership';
        } else {
            $args['role__not_in'] = array('dealership');
        }

        if ($search !== '') {
            $args['search'] = '*' . $search . '*';
            $args['search_columns'] = array('user_login', 'user_email', 'display_name');
        }

        $query = new WP_User_Query($args);
        $users = $query->get_results();

        return is_array($users) ? $users : array();
    }

    private function renderSelectedUserDashboard(WP_User $user): void
    {
        $stats = $this->statsManager->get_stats_for_user((int) $user->ID);
        ?>
        <section class="autoagora-user-listing-dashboard__selected" aria-label="<?php esc_attr_e('Selected user listing dashboard', 'bricks-child'); ?>">
            <div class="autoagora-user-listing-dashboard__selected-header">
                <div>
                    <h2><?php echo esc_html($this->formatUserName($user)); ?></h2>
                    <p class="description">
                        <?php
                        echo esc_html(
                            sprintf(
                                /* translators: 1: user ID, 2: roles */
                                __('User ID %1$d | Roles: %2$s', 'bricks-child'),
                                (int) $user->ID,
                                $this->formatRoles($user)
                            )
                        );
                        ?>
                    </p>
                </div>
                <a class="button" href="<?php echo esc_url(get_edit_user_link((int) $user->ID)); ?>">
                    <?php esc_html_e('Edit user', 'bricks-child'); ?>
                </a>
            </div>
            <div class="autoagora-admin-card-grid">
                <?php
                $this->renderStatCard(__('Total cars posted', 'bricks-child'), number_format_i18n((int) $stats['total_listings']));
                $this->renderStatCard(__('Active listings', 'bricks-child'), number_format_i18n((int) $stats['active_listings']));
                $this->renderStatCard(__('Pending approval', 'bricks-child'), number_format_i18n((int) $stats['pending_listings']));
                $this->renderStatCard(__('Sold listings', 'bricks-child'), number_format_i18n((int) $stats['sold_listings']));
                $this->renderStatCard(__('Expired listings', 'bricks-child'), number_format_i18n((int) ($stats['expired_listings'] ?? 0)));
                $this->renderStatCard(__('Stale listings', 'bricks-child'), number_format_i18n((int) $stats['stale_listings']));
                $this->renderStatCard(__('Total views generated', 'bricks-child'), number_format_i18n((int) $stats['total_views']));
                $this->renderStatCard(__('Unique visitors', 'bricks-child'), number_format_i18n((int) $stats['unique_views']));
                $this->renderStatCard(__('Contact action clicks', 'bricks-child'), number_format_i18n((int) $stats['total_leads']));
                $this->renderStatCard(__('Avg. views per listing', 'bricks-child'), number_format_i18n((float) $stats['average_views_per_listing'], 1));
                ?>
            </div>
        </section>
        <?php
    }

    /**
     * @param array<int, WP_User> $users
     */
    private function renderUserGroup(string $title, array $users, int $selectedUserId, string $search, bool $showOutreachColumns): void
    {
        ?>
        <section class="autoagora-user-listing-dashboard__group">
            <h2><?php echo esc_html($title); ?></h2>
            <?php if (empty($users)) : ?>
                <p><?php esc_html_e('No matching users found.', 'bricks-child'); ?></p>
                <?php return; ?>
            <?php endif; ?>

            <table class="widefat striped">
                <thead>
                    <tr>
                        <th><?php esc_html_e('User', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Roles', 'bricks-child'); ?></th>
                        <th><?php esc_html_e('Registered', 'bricks-child'); ?></th>
                        <?php if ($showOutreachColumns) : ?>
                            <th><?php esc_html_e('Outreach Status', 'bricks-child'); ?></th>
                            <th><?php esc_html_e('Priority', 'bricks-child'); ?></th>
                            <th><?php esc_html_e('Last Contacted', 'bricks-child'); ?></th>
                            <th><?php esc_html_e('Contact Count', 'bricks-child'); ?></th>
                        <?php endif; ?>
                        <th><?php esc_html_e('Actions', 'bricks-child'); ?></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($users as $user) : ?>
                        <?php
                        $outreachStatus = $showOutreachColumns ? $this->getUserField((int) $user->ID, 'outreach_status') : '';
                        $outreachPriority = $showOutreachColumns ? $this->getUserField((int) $user->ID, 'outreach_priority') : '';
                        $lastContacted = $showOutreachColumns ? $this->getUserField((int) $user->ID, 'last_contacted') : '';
                        $contactCount = $showOutreachColumns ? absint($this->getUserField((int) $user->ID, 'contact_count')) : 0;
                        ?>
                        <tr<?php echo (int) $user->ID === $selectedUserId ? ' class="is-selected-user"' : ''; ?>>
                            <td>
                                <strong><?php echo esc_html($this->formatUserName($user)); ?></strong>
                                <div class="autoagora-user-listing-dashboard__muted">
                                    <?php echo esc_html($user->user_login); ?> | ID <?php echo esc_html((string) $user->ID); ?>
                                </div>
                            </td>
                            <td><?php echo esc_html($this->formatRoles($user)); ?></td>
                            <td><?php echo esc_html(mysql2date('Y-m-d', $user->user_registered)); ?></td>
                            <?php if ($showOutreachColumns) : ?>
                                <td><?php $this->renderOutreachStatusBadge($outreachStatus); ?></td>
                                <td><?php $this->renderPriorityBadge($outreachPriority); ?></td>
                                <td><?php echo esc_html($this->formatDateField($lastContacted)); ?></td>
                                <td><?php echo esc_html(number_format_i18n($contactCount)); ?></td>
                            <?php endif; ?>
                            <td>
                                <div class="autoagora-user-listing-dashboard__actions">
                                    <a class="button button-small" href="<?php echo esc_url($this->dashboardUrl((int) $user->ID, $search)); ?>">
                                        <?php esc_html_e('View dashboard', 'bricks-child'); ?>
                                    </a>
                                    <?php if ($showOutreachColumns) : ?>
                                        <?php $this->renderWhatsAppButton($user); ?>
                                        <a class="button button-small" href="<?php echo esc_url(get_edit_user_link((int) $user->ID)); ?>">
                                            <?php esc_html_e('Edit Notes', 'bricks-child'); ?>
                                        </a>
                                    <?php endif; ?>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php if (count($users) >= self::MAX_USERS_PER_GROUP) : ?>
                <p class="description">
                    <?php esc_html_e('Showing the first matching users. Use search to find a specific account.', 'bricks-child'); ?>
                </p>
            <?php endif; ?>
        </section>
        <?php
    }

    private function getUserField(int $userId, string $fieldName): string
    {
        if (function_exists('get_field')) {
            $acfValue = get_field($fieldName, 'user_' . $userId);
            $normalizedAcfValue = $this->normalizeFieldValue($acfValue);
            if ($normalizedAcfValue !== '') {
                return $normalizedAcfValue;
            }
        }

        $metaValue = get_user_meta($userId, $fieldName, true);

        return $this->normalizeFieldValue($metaValue);
    }

    /**
     * @param mixed $value
     */
    private function normalizeFieldValue($value): string
    {
        if (is_scalar($value)) {
            return sanitize_text_field((string) $value);
        }

        if (is_array($value)) {
            foreach (array('value', 'label') as $key) {
                if (isset($value[$key]) && is_scalar($value[$key])) {
                    return sanitize_text_field((string) $value[$key]);
                }
            }
        }

        return '';
    }

    private function renderOutreachStatusBadge(string $status): void
    {
        $normalized = $this->normalizeBadgeValue($status);
        $labels = array(
            'contacted'      => __('Contacted', 'bricks-child'),
            'interested'     => __('Interested', 'bricks-child'),
            'claimed'        => __('Claimed', 'bricks-child'),
            'not_interested' => __('Not interested', 'bricks-child'),
            'opted_out'      => __('Opted out', 'bricks-child'),
        );

        $label = $labels[$normalized] ?? __('Not set', 'bricks-child');
        $class = isset($labels[$normalized]) ? 'is-status-' . $normalized : 'is-empty';

        $this->renderBadge($label, $class);
    }

    private function renderPriorityBadge(string $priority): void
    {
        $normalized = $this->normalizeBadgeValue($priority);
        $labels = array(
            'high'   => __('High', 'bricks-child'),
            'medium' => __('Medium', 'bricks-child'),
            'low'    => __('Low', 'bricks-child'),
        );

        $label = $labels[$normalized] ?? __('Not set', 'bricks-child');
        $class = isset($labels[$normalized]) ? 'is-priority-' . $normalized : 'is-empty';

        $this->renderBadge($label, $class);
    }

    private function renderBadge(string $label, string $class): void
    {
        ?>
        <span class="autoagora-outreach-badge <?php echo esc_attr($class); ?>"><?php echo esc_html($label); ?></span>
        <?php
    }

    private function renderWhatsAppButton(WP_User $user): void
    {
        $url = $this->buildWhatsAppUrl($user);
        if ($url === '') {
            ?>
            <span class="button button-small disabled" aria-disabled="true"><?php esc_html_e('WhatsApp', 'bricks-child'); ?></span>
            <?php
            return;
        }
        ?>
        <a class="button button-small" href="<?php echo esc_url($url); ?>" target="_blank" rel="noopener noreferrer">
            <?php esc_html_e('WhatsApp', 'bricks-child'); ?>
        </a>
        <?php
    }

    private function buildWhatsAppUrl(WP_User $user): string
    {
        $phone = $this->normalizeWhatsAppPhone($this->getUserPhone($user));
        if ($phone === '') {
            return '';
        }

        return sprintf(
            'https://wa.me/%s?text=%s',
            rawurlencode($phone),
            rawurlencode($this->buildWhatsAppMessage($user))
        );
    }

    private function buildWhatsAppMessage(WP_User $user): string
    {
        return sprintf(
            __('Hi %s, this is AutoAgora. I wanted to follow up about your dealership account and listings.', 'bricks-child'),
            $this->formatUserName($user)
        );
    }

    private function getUserPhone(WP_User $user): string
    {
        $usernamePhone = sanitize_text_field((string) $user->user_login);
        if ($usernamePhone !== '') {
            return $usernamePhone;
        }

        $metaPhone = $this->getUserField((int) $user->ID, 'phone_number');
        if ($metaPhone !== '') {
            return $metaPhone;
        }

        return $this->getUserField((int) $user->ID, 'phone');
    }

    private function normalizeWhatsAppPhone(string $phone): string
    {
        $digits = preg_replace('/\D+/', '', $phone);
        if (!is_string($digits) || $digits === '') {
            return '';
        }

        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        if (strlen($digits) === 8) {
            $digits = '357' . $digits;
        }

        return $digits;
    }

    private function normalizeBadgeValue(string $value): string
    {
        $value = strtolower(trim($value));
        $value = str_replace(array('-', ' '), '_', $value);

        return sanitize_key($value);
    }

    private function formatDateField(string $date): string
    {
        $date = trim($date);
        if ($date === '') {
            return __('Not set', 'bricks-child');
        }

        $timestamp = strtotime($date);
        if (!$timestamp && preg_match('/^\d{8}$/', $date) === 1) {
            $timestamp = strtotime(substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2));
        }

        if (!$timestamp) {
            return $date;
        }

        return date_i18n('Y-m-d', $timestamp);
    }

    private function renderStatCard(string $label, string $value): void
    {
        ?>
        <div class="autoagora-admin-card">
            <div class="autoagora-admin-card__label"><?php echo esc_html($label); ?></div>
            <div class="autoagora-admin-card__value"><?php echo esc_html($value); ?></div>
        </div>
        <?php
    }

    private function dashboardUrl(int $userId, string $search): string
    {
        $args = array(
            'page'    => self::SLUG,
            'user_id' => $userId,
        );

        if ($search !== '') {
            $args['user_search'] = $search;
        }

        return add_query_arg($args, admin_url('users.php'));
    }

    private function formatUserName(WP_User $user): string
    {
        $name = trim((string) $user->display_name);
        if ($name === '') {
            $name = trim((string) $user->user_login);
        }

        return $name !== '' ? $name : sprintf(__('User #%d', 'bricks-child'), (int) $user->ID);
    }

    private function formatRoles(WP_User $user): string
    {
        $wpRoles = wp_roles();
        $roles = array();

        foreach ((array) $user->roles as $role) {
            $roleName = $wpRoles->roles[$role]['name'] ?? $role;
            $roles[] = translate_user_role($roleName);
        }

        return !empty($roles) ? implode(', ', $roles) : __('No role', 'bricks-child');
    }

    private function renderStyles(): void
    {
        ?>
        <style>
            .autoagora-user-listing-dashboard__search {
                display: flex;
                flex-wrap: wrap;
                gap: 8px;
                align-items: center;
                margin: 1rem 0 1.25rem;
            }
            .autoagora-user-listing-dashboard__search label {
                font-weight: 600;
            }
            .autoagora-user-listing-dashboard__search input[type="search"] {
                min-width: 260px;
            }
            .autoagora-user-listing-dashboard__selected {
                margin: 1.25rem 0 1.75rem;
                padding: 16px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-radius: 6px;
            }
            .autoagora-user-listing-dashboard__selected-header {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: flex-start;
                justify-content: space-between;
                margin-bottom: 14px;
            }
            .autoagora-user-listing-dashboard__selected-header h2 {
                margin: 0 0 4px;
            }
            .autoagora-user-listing-dashboard__groups {
                display: grid;
                grid-template-columns: repeat(auto-fit, minmax(360px, 1fr));
                gap: 18px;
            }
            .autoagora-user-listing-dashboard__group h2 {
                margin-top: 0;
            }
            .autoagora-user-listing-dashboard__group {
                overflow-x: auto;
            }
            .autoagora-user-listing-dashboard__muted {
                color: #646970;
                font-size: 12px;
            }
            .autoagora-user-listing-dashboard__actions {
                display: flex;
                flex-wrap: wrap;
                gap: 6px;
                align-items: center;
            }
            .autoagora-outreach-badge {
                display: inline-block;
                padding: 3px 8px;
                border-radius: 4px;
                font-size: 12px;
                font-weight: 600;
                line-height: 1.4;
                white-space: nowrap;
            }
            .autoagora-outreach-badge.is-status-contacted {
                background: #fcf3cf;
                color: #7a5a00;
            }
            .autoagora-outreach-badge.is-status-interested {
                background: #e8f2ff;
                color: #0a4b78;
            }
            .autoagora-outreach-badge.is-status-claimed {
                background: #edfaef;
                color: #1e4620;
            }
            .autoagora-outreach-badge.is-status-not_interested {
                background: #fcf0f1;
                color: #8a2424;
            }
            .autoagora-outreach-badge.is-status-opted_out {
                background: #50575e;
                color: #fff;
            }
            .autoagora-outreach-badge.is-priority-high {
                background: #fcf0f1;
                color: #b32d2e;
            }
            .autoagora-outreach-badge.is-priority-medium {
                background: #fff4e5;
                color: #8a4b00;
            }
            .autoagora-outreach-badge.is-priority-low,
            .autoagora-outreach-badge.is-empty {
                background: #f0f0f1;
                color: #50575e;
            }
            .autoagora-user-listing-dashboard tr.is-selected-user td {
                background: #f0f6fc;
            }
            .autoagora-admin-card-grid {
                display: flex;
                flex-wrap: wrap;
                gap: 12px;
                align-items: stretch;
            }
            .autoagora-admin-card {
                flex: 1 1 175px;
                min-width: 175px;
                max-width: 260px;
                padding: 14px 16px;
                background: #fff;
                border: 1px solid #c3c4c7;
                border-left: 4px solid #72aee6;
                border-radius: 6px;
                box-sizing: border-box;
            }
            .autoagora-admin-card__label {
                margin-bottom: 6px;
                color: #646970;
                font-size: 12px;
                line-height: 1.3;
                text-transform: uppercase;
            }
            .autoagora-admin-card__value {
                color: #1d2327;
                font-size: 28px;
                font-weight: 600;
                line-height: 1.2;
            }
            @media (max-width: 782px) {
                .autoagora-user-listing-dashboard__groups {
                    grid-template-columns: 1fr;
                }
                .autoagora-admin-card {
                    flex-basis: 100%;
                    max-width: none;
                }
            }
        </style>
        <?php
    }
}

AutoAgoraUserListingDashboardAdminPage::bootstrap();
