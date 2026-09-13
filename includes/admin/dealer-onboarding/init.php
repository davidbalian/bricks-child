<?php
/** Authenticated bulk dealership onboarding bootstrap. */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DealerOnboardingToken.php';
require_once __DIR__ . '/DealerOnboardingRestController.php';
require_once __DIR__ . '/DealerOnboardingAdmin.php';

AutoAgora_Dealer_Onboarding_REST_Controller::register();
AutoAgora_Dealer_Onboarding_Admin::register();
