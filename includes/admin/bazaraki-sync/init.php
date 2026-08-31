<?php
/** Production Bazaraki-to-AutoAgora synchronization bootstrap. */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/BazarakiSyncSchema.php';
require_once __DIR__ . '/BazarakiSyncProfiles.php';
require_once __DIR__ . '/BazarakiSyncAuth.php';
require_once __DIR__ . '/BazarakiSyncQueue.php';
require_once __DIR__ . '/BazarakiSyncApplier.php';
require_once __DIR__ . '/BazarakiSyncProcessor.php';
require_once __DIR__ . '/BazarakiSyncRestController.php';
require_once __DIR__ . '/BazarakiSyncAdmin.php';

add_action('init', array('AutoAgora_Bazaraki_Sync_Schema', 'maybeInstall'), 5);
AutoAgora_Bazaraki_Sync_REST_Controller::register();
AutoAgora_Bazaraki_Sync_Admin::register();
