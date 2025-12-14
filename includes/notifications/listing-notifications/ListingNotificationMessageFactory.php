<?php
/**
 * Builds the copy for listing notification emails.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class ListingNotificationMessageFactory
{
    private const BRAND = 'AutoAgora';

    /**
     * Build message for contact click milestone.
     */
    public function buildContactClickNotification(string $listing_title, int $total_clicks): array
    {
        $subject = 'Buyers are contacting you about your car';
        $body = 'Your listing has received multiple contact requests. This is a strong sign that buyers are interested in your car.';
        $support = 'Listings with fresh photos and clear details tend to receive more enquiries.';

        $html = sprintf(
            '<p>%s</p><p>%s</p><p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 16px;border-radius:4px;text-decoration:none;font-weight:600;">My Listings</a></p><p><small>%s</small></p><p><small>%s</small></p>',
            esc_html($body),
            esc_html($support),
            esc_url($this->getListingsUrl()),
            esc_html($this->getGlobalFooter())
        );

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $body . "\n\n" . $support . "\n\nVisit: " . $this->getListingsUrl() . "\n" . $this->getGlobalFooter(),
        ];
    }

    /**
     * Build message for view milestone.
     */
    public function buildViewMilestoneNotification(string $listing_title, int $views, int $milestone): array
    {
        $copy = $this->getMilestoneCopy($listing_title, $views, $milestone);

        $subject = $copy['subject'];
        $body = $copy['body'];
        $text = $copy['text'];

        $html = sprintf(
            '<p>%s</p><p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 16px;border-radius:4px;text-decoration:none;font-weight:600;">My Listings</a></p>',
            esc_html($body),
            esc_url($this->getListingsUrl())
        );

        $support = $copy['support'] ?? '';

        $html = sprintf(
            '<p>%s</p><p>%s</p><p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 16px;border-radius:4px;text-decoration:none;font-weight:600;">My Listings</a></p><p><small>%s</small></p><p><small>%s</small></p>',
            esc_html($body),
            esc_html($support),
            esc_url($this->getListingsUrl()),
            esc_html($this->getGlobalFooter())
        );

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text . "\n\n" . $support . "\n\nVisit: " . $this->getListingsUrl() . "\n" . $this->getGlobalFooter(),
        ];
    }

    private function getMilestoneCopy(string $listing_title, int $views, int $milestone): array
    {
        $base = sprintf('%s just hit %d views—buyers are tuning in for real.', $listing_title, $views);

        $map = [
            150 => [
                'subject' => '150 views — strong interest in your listing',
                'body' => '150 people have already viewed your car. Listings with this level of attention often receive multiple enquiries.',
                'support' => 'Keeping your listing up to date can help you close faster.',
                'text' => '150 people have already viewed your car. Listings with this level of attention often receive multiple enquiries.'
            ],
            100 => [
                'subject' => '100 buyers have viewed your car',
                'body' => 'Your listing has passed 100 views. Buyers are clearly finding it and spending time reviewing it.',
                'support' => 'Highlighting your car’s best features can help turn views into enquiries.',
                'text' => 'Your listing has passed 100 views. Buyers are clearly finding it and spending time reviewing it.'
            ],
            50 => [
                'subject' => 'Your listing has reached 50 views',
                'body' => '50 people have viewed your car so far. This usually means your price and details are attracting attention.',
                'support' => 'A small update can help keep your listing visible and competitive.',
                'text' => '50 people have viewed your car so far. This usually means your price and details are attracting attention.'
            ],
            20 => [
                'subject' => 'Your listing has reached 20 views',
                'body' => '20 people have already viewed your car. This is a good early sign that buyers are finding your listing.',
                'support' => 'Early interest is a great time to double-check photos, price, and details.',
                'text' => '20 people have already viewed your car. This is a good early sign that buyers are finding your listing.'
            ],
        ];

        return $map[$milestone] ?? [
            'subject' => sprintf('Momentum building: %d views!', $views),
            'body' => $base,
            'support' => 'Stay active—fine-tune your listing so the interest keeps growing.',
            'text' => 'Stay active—fine-tune your listing so the interest keeps growing.'
        ];
    }

    private function getListingsUrl(): string
    {
        return home_url('/my-listings/');
    }



    /**
     * Build message for reminder emails.
     */
    public function buildReminderNotification(string $listing_title, int $reminder_count, string $refresh_url, string $mark_as_sold_url): array
    {
        $subject = 'Quick reminder to keep your listing fresh';
        $body = sprintf(
            '%s has been live for a while. Small updates can help it stand out again in search results.',
            $listing_title
        );
        $support = 'You can refresh your listing or mark it as sold at any time from your account.';

        $html = sprintf(
            '<p>%s</p><p>%s</p><p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 18px;border-radius:5px;text-decoration:none;font-weight:600;">My Listings</a></p><p><small>You are receiving reminder %d of 3.</small></p><p><small>%s</small></p>',
            esc_html($body),
            esc_html($support),
            esc_url($refresh_url),
            $reminder_count,
            esc_html($this->getGlobalFooter())
        );

        $text = $body . "\n\n" . $support . "\n\nVisit: " . $refresh_url . "\n" . $this->getGlobalFooter() . "\nYou are receiving reminder " . $reminder_count . " of 3.";

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text,
        ];
    }

    /**
     * Internal helper for uniform payloads.
     */
    private function buildEmailPayload(string $subject, string $text, array $overrides = []): array
    {
        $payload = [
            'subject' => $subject,
            'text' => $text,
            'html' => '<p>' . esc_html($text) . '</p>',
        ];

        return array_merge($payload, $overrides);
    }

    private function getGlobalFooter(): string
    {
        return 'You can manage or turn off these emails anytime in your account settings.';
    }
}

