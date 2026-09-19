<?php
// Standalone contract tests; no WordPress database or live requests.
define('ABSPATH', __DIR__);
class WP_Error { public function __construct(public string $code, public string $message, public array $data = array()) {} }
function is_wp_error($v) { return $v instanceof WP_Error; }
function __($s, $domain = '') { return $s; }
function get_posts($args) { return array(); }
function get_post_field($key, $id) { return $GLOBALS['owner']; }
function get_post_meta($id, $key, $single = true) { return $GLOBALS['meta'][$key] ?? ''; }
function update_post_meta($id, $key, $value) { $GLOBALS['meta'][$key] = $value; }
function delete_post_meta($id, $key) { unset($GLOBALS['meta'][$key]); }
function current_time($type, $utc = false) { return '2026-09-19 00:00:00'; }
function do_action(...$args) { $GLOBALS['actions'][] = $args; }
function absint($v) { return abs((int) $v); }
class AutoAgora_Car_Json_Import_Validator {
    const MAX_MANIFEST_BYTES = 10485760;
    public static function validatePackage($path, $defaults) { return array('rows' => $GLOBALS['rows']); }
    public static function findExistingImport($platform, $id) { return 123; }
}
class AutoAgora_Car_Json_Import_Runner {
    public static function beginAdminNotificationSuppression() { $GLOBALS['suppressed'] = true; }
    public static function endAdminNotificationSuppression() { $GLOBALS['suppressed'] = false; }
    public static function updateField($key, $value, $id) { $GLOBALS['fields'][$key] = $value; }
}
class AutoAgora_Bazaraki_Sync_Profiles {
    public static function defaults($profile) { return array(); }
    public static function get($id) { return array('author_id' => 7); }
}
require __DIR__ . '/../includes/admin/bazaraki-sync/BazarakiSyncRestController.php';
require __DIR__ . '/../includes/admin/bazaraki-sync/BazarakiSyncApplier.php';
function check($condition, $message) { if (!$condition) throw new RuntimeException($message); }
$GLOBALS['owner'] = 7;
$GLOBALS['meta'] = array('car_images' => array(10, 11), 'description' => 'Keep me');
$GLOBALS['fields'] = array();
$status = AutoAgora_Bazaraki_Sync_Applier::apply(array('source_id'=>'100', 'profile_id'=>'dealer', 'action'=>'price', 'payload'=>array('price'=>12500)), array('author_id'=>7), '');
check($status === 'complete', 'Price job must complete');
check($GLOBALS['fields'] === array('price'=>12500.0), 'Only price field may change');
check($GLOBALS['meta']['car_images'] === array(10,11), 'Images preserved');
check($GLOBALS['meta']['description'] === 'Keep me', 'Description preserved');
check(!$GLOBALS['suppressed'], 'Notification suppression must unwind');
$GLOBALS['owner'] = 8;
try {
    AutoAgora_Bazaraki_Sync_Applier::apply(array('source_id'=>'100','profile_id'=>'dealer','action'=>'price','payload'=>array('price'=>1)), array('author_id'=>7), '');
    throw new RuntimeException('Cross-owner update was allowed');
} catch (RuntimeException $e) { check(str_contains($e->getMessage(),'another dealer'), 'Owner guard'); }
check(!$GLOBALS['suppressed'], 'Suppression unwinds after exception');
$changes = array('profile_id'=>'dealer','run_id'=>'test-run','efficient'=>true,'present_source_ids'=>array('1','2','3'),
    'chunked'=>true,'final_chunk'=>false,'created'=>array(array('source_id'=>'1','listing'=>array('sync_image_hashes'=>array(str_repeat('a',64)))), array('source_id'=>'2')),
    'price_updates'=>array(array('source_id'=>'3','price'=>15000)));
$GLOBALS['rows'] = array(
    array('listing'=>array('source_id'=>'1','car_images'=>array('image.jpg')),'valid'=>true),
    array('listing'=>array('source_id'=>'2'),'valid'=>false,'errors'=>array('Missing model')),
);
$file = tempnam(sys_get_temp_dir(), 'aa-sync-');
try {
    $zip = new ZipArchive();
    $zip->open($file, ZipArchive::OVERWRITE);
    $zip->addFromString('changes.json', json_encode($changes));
    $zip->close();
    $method = new ReflectionMethod(AutoAgora_Bazaraki_Sync_REST_Controller::class, 'preparePackage');
    $prepared = $method->invoke(null, $file, 'dealer', 'test-run', array());
    check(!is_wp_error($prepared), 'Invalid row must not reject entire package');
    check(array_column($prepared['jobs'],'action') === array('upsert','reject','price'), 'Valid/new/rejected/price jobs separated');
    check($prepared['jobs'][1]['payload']['error'] === 'Missing model', 'Rejection preserves reason');
    check($prepared['suppress_summary'] === true, 'Intermediate chunks suppress summary email');
} finally { unlink($file); }
echo "Bazaraki efficient sync PHP contract tests passed.\n";
