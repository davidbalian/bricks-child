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
        $subject = 'Your car is getting attention!';
        $body = sprintf(
            '%s just recorded %d contact clicks. That means buyers are interested—nice work!',
            $listing_title,
            $total_clicks
        );

        return $this->buildEmailPayload($subject, $body, [
            'html' => sprintf('<p>%s</p><p>Keep the momentum by refreshing your listing or adding a photo.</p>', esc_html($body)),
            'text' => $body . "\n\nRefresh your listing to stay at the top."
        ]);
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

        return [
            'subject' => $subject,
            'html' => $html,
            'text' => $text . "\n\nVisit: " . $this->getListingsUrl(),
        ];
    }

    private function getMilestoneCopy(string $listing_title, int $views, int $milestone): array
    {
        $base = sprintf('%s just hit %d views—buyers are tuning in for real.', $listing_title, $views);

        $map = [
            150 => [
                'subject' => '🔥 Huge momentum: 150 views!',
                'body' => 'Your listing is getting serious attention—150 visitors have already checked it out. Keep the details crisp to close the deal.',
                'text' => 'Keep the momentum by refreshing photos or highlighting upgrades.'
            ],
            100 => [
                'subject' => '✅ 100 views and counting!',
                'body' => '100 people have peeked at your car. That’s buyers noticing the value—play it up with a quick refresh.',
                'text' => 'Need ideas? Update the description or highlight new perks.'
            ],
            50 => [
                'subject' => 'Good news: 50 views reached!',
                'body' => 'Half a hundred buyers have seen your listing. That’s proof your car is being considered.',
                'text' => 'Give it another boost with a price tweak or fresh angle.'
            ],
            20 => [
                'subject' => 'Nice start: 20 views already!',
                'body' => '20 people have clicked through—early traction is the best time to polish the listing.',
                'text' => 'Refresh the headline or add a friendly note so even more buyers keep coming back.'
            ],
        ];

        return $map[$milestone] ?? [
            'subject' => sprintf('Momentum building: %d views!', $views),
            'body' => $base,
            'text' => 'Stay active—fine-tune your listing so the interest keeps growing.'
        ];
    }

    private function getListingsUrl(): string
    {
        $next = home_url('/my-listings/');

        if (strpos(home_url(), 'staging4.autoagora.cy') !== false) {
            return 'https://staging4.autoagora.cy/my-listings/';
        }

        return $next;
    }

    /**
     * Build message for reminder emails.
     */
    public function buildReminderNotification(string $listing_title, int $reminder_count, string $refresh_url, string $mark_as_sold_url): array
    {
        $subject = 'Quick reminder to keep your listing fresh';
        $body = sprintf(
            '%s could use a fresh look. Update the price, headline, or photos to help it stand out.',
            $listing_title
        );

        $html = sprintf(
            '<p>%s</p><p><a href="%s" style="background:#0073aa;color:#fff;padding:10px 18px;border-radius:5px;text-decoration:none;margin-right:10px;">Refresh listing</a><a href="%s" style="background:#444;color:#fff;padding:10px 18px;border-radius:5px;text-decoration:none;">Mark as sold</a></p><p>You are receiving reminder %d of 3.</p>',
            esc_html($body),
            esc_url($refresh_url),
            esc_url($mark_as_sold_url),
            $reminder_count
        );

        $text = $body . "\n\nRefresh listing: " . $refresh_url . "\nMark as sold: " . $mark_as_sold_url;

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
}

