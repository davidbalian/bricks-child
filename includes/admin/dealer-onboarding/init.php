<?php
/** Authenticated bulk dealership onboarding bootstrap. */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DealerOnboardingRestController.php';

AutoAgora_Dealer_Onboarding_REST_Controller::register();
