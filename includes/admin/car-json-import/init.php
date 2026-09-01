<?php
/**
 * Bootstrap for the guarded car JSON + images importer.
 */

if (!defined('ABSPATH')) {
    exit;
}

require_once __DIR__ . '/CarJsonImportValidator.php';
require_once __DIR__ . '/CarJsonImportRunner.php';
require_once __DIR__ . '/CarJsonImportAdmin.php';

AutoAgora_Car_Json_Import_Runner::registerNotificationSuppression();
AutoAgora_Car_Json_Import_Admin::register();
