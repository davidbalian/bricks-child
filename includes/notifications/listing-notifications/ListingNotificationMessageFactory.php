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
        $subject = sprintf('Nice news: %s hit %d views!', $listing_title, $milestone);
        $body = sprintf(
            '%s has now reached %d views. That is the kind of traction every seller wants.',
            $listing_title,
            $views
        );

        return $this->buildEmailPayload($subject, $body, [
            'html' => sprintf('<p>%s</p><p>Want even more attention? Refresh your listing with updated info or pricing.</p>', esc_html($body)),
            'text' => $body . "\n\nTry refreshing your listing to keep the attention rolling."
        ]);
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

