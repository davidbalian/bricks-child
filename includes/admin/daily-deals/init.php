<?php
/**
 * Bootstrap Daily Deals admin tools.
 */
if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/DailyDealsFirstImageResolver.php';
require_once __DIR__ . '/DailyDealsDealPicker.php';
require_once __DIR__ . '/DailyDealsSocialCopyBuilder.php';
require_once __DIR__ . '/DailyDealsDownloadHandler.php';
require_once __DIR__ . '/DailyDealsAdminPage.php';

DailyDealsDownloadHandler::bootstrap();
DailyDealsAdminPage::bootstrap();
