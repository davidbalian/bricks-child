<?php
/**
 * Coordinates sending listing notifications when milestones are reached.
 *
 * @package Bricks Child
 */

// Prevent direct access.
if (!defined('ABSPATH')) {
    exit;
}

final class ListingNotificationManager
{
    private ListingNotificationEmailResolver $emailResolver;
    private ListingNotificationPreferences $preferences;
    private ListingNotificationStateRepository $stateRepository;
    private ListingNotificationMessageFactory $messageFactory;

    public function __construct()
    {
        $this->emailResolver   = new ListingNotificationEmailResolver();
        $this->preferences     = new ListingNotificationPreferences();
        $this->stateRepository = new ListingNotificationStateRepository();
        $this->messageFactory  = new ListingNotificationMessageFactory();
    }

    public function maybeSendContactClickNotification(int $car_id): bool
    {
        if (!$this->isSendAllowed($car_id)) {
            return false;
        }

        if ($this->stateRepository->hasContactClickNotificationBeenSent($car_id)) {
            return false;
        }

        $totalClicks = $this->getContactClicks($car_id);

        if ($totalClicks < 3) {
            return false;
        }

        $subjectData = $this->messageFactory->buildContactClickNotification(
            $this->getListingTitle($car_id),
            $totalClicks
        );

        if ($this->sendEmailToOwner($car_id, $subjectData, false)) {
            $this->stateRepository->markContactClickNotificationSent($car_id);
            return true;
        }

        return false;
    }

    public function maybeSendViewMilestoneNotification(int $car_id): bool
    {
        if (!$this->isSendAllowed($car_id)) {
            return false;
        }

        $views = $this->getTotalViews($car_id);
        $milestonesSent = $this->stateRepository->getViewMilestonesSent($car_id);

        foreach ($this->getViewMilestones() as $milestone) {
            if ($views >= $milestone && !in_array($milestone, $milestonesSent, true)) {
                $payload = $this->messageFactory->buildViewMilestoneNotification(
                    $this->getListingTitle($car_id),
                    $views,
                    $milestone
                );

        if ($this->sendEmailToOwner($car_id, $payload, false)) {
                    $this->stateRepository->markViewMilestoneSent($car_id, $milestone);
                    return true;
                }
            }
        }

        return false;
    }

    public function maybeSendReminderNotification(int $car_id, string $refresh_url, string $mark_as_sold_url): bool
    {
        if (!$this->isSendAllowed($car_id)) {
            return false;
        }

        $owner_id = $this->getListingOwnerId($car_id);
        if (!$this->preferences->isReminderNotificationsEnabled($owner_id)) {
            return false;
        }

        if ($this->stateRepository->getReminderCount($car_id) >= 3) {
            return false;
        }

        $payload = $this->messageFactory->buildReminderNotification(
            $this->getListingTitle($car_id),
            $this->stateRepository->getReminderCount($car_id) + 1,
            $refresh_url,
            $mark_as_sold_url
        );

        if ($this->sendEmailToOwner($car_id, $payload, true)) {
            $this->stateRepository->incrementReminderCount($car_id);
            $this->stateRepository->updateLastReminderTimestamp($car_id);
            return true;
        }

        return false;
    }

    private function sendEmailToOwner(int $car_id, array $payload, bool $isReminder): bool
    {
        $owner_id = $this->getListingOwnerId($car_id);

        if (!$owner_id) {
            return false;
        }

        if (!$isReminder && !$this->preferences->isActivityNotificationsEnabled($owner_id)) {
            return false;
        }

        if ($isReminder && !$this->preferences->isReminderNotificationsEnabled($owner_id)) {
            return false;
        }

        $email = $this->emailResolver->resolveVerifiedEmail($owner_id);

        if (!$email) {
            return false;
        }

        return send_app_email($email, $payload['subject'], $payload['html'], $payload['text'] ?? '');
    }

    private function getTotalViews(int $car_id): int
    {
        return max(0, absint(get_post_meta($car_id, 'total_views_count', true)));
    }

    private function getContactClicks(int $car_id): int
    {
        $phone = absint(get_post_meta($car_id, 'call_button_clicks', true));
        $whatsapp = absint(get_post_meta($car_id, 'whatsapp_button_clicks', true));
        return $phone + $whatsapp;
    }

    private function getListingOwnerId(int $car_id): int
    {
        return absint(get_post_field('post_author', $car_id));
    }

    private function getListingTitle(int $car_id): string
    {
        return get_the_title($car_id) ?: sprintf(__('Car listing #%d', 'bricks-child'), $car_id);
    }

    private function isSendAllowed(int $car_id): bool
    {
        $post = get_post($car_id);

        if (!$post || $post->post_type !== 'car' || $post->post_status !== 'publish') {
            return false;
        }

        $owner_id = $this->getListingOwnerId($car_id);

        if (!$owner_id) {
            return false;
        }

        if (!$this->emailResolver->hasVerifiedEmail($owner_id)) {
            return false;
        }

        return true;
    }

    private function getViewMilestones(): array
    {
        return [150, 100, 50, 20];
    }
}

