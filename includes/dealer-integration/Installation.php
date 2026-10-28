<?php
/** Explicit administrator deployment setup; never imports local fixture data. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

final class Installation
{
    public static function directory(): string
    {
        return dirname(rtrim(ABSPATH, '/\\')) . '/.autoagora-dealer';
    }
    public static function load(): void
    {
        if (!get_option('autoagora_dealer_admin_release')) { return; }
        $path = self::directory() . '/runtime.json';
        if (!is_readable($path)) { return; }
        $config = json_decode(file_get_contents($path), true);
        if (!is_array($config) || empty($config['encryption']) || strlen($config['encryption']) < 32) { return; }
        $values = [
            'AUTOAGORA_DEALER_API_ENABLED' => true,
            'AUTOAGORA_DEALER_API_PRODUCTION_APPROVED' => true,
            'AUTOAGORA_DEALER_PRIVATE_KEY' => self::directory() . '/private.pem',
            'AUTOAGORA_DEALER_PUBLIC_KEY' => self::directory() . '/public.pem',
            'AUTOAGORA_DEALER_ENCRYPTION_KEY' => $config['encryption'],
            'AUTOAGORA_DEALER_REVIEW_STORAGE' => self::directory() . '/media',
        ];
        foreach ($values as $key => $value) { if (!defined($key)) { define($key, $value); } }
        // Hosted listing business rules have not been reconciled; no public write gate is enabled here.
    }
    public static function bootstrap(): void
    {
        add_action('admin_post_ag_dealer_install_review', [self::class, 'install']);
        add_action('admin_menu', static function () {
            if (Support::enabled() || !Review::admin()) { return; }
            add_management_page('Dealer integrations', 'Dealer integrations', 'manage_options', 'ag-dealer-integrations', [self::class, 'screen']);
        });
    }
    public static function screen(): void
    {
        if (!Review::admin()) { wp_die('Not found.', '', ['response' => 404]); }
        echo '<div class="wrap"><h1>Dealer integrations</h1><p>Enable the administrator-only workspace. Public dealer onboarding stays disabled.</p><p>This installs empty integration tables and creates environment-specific keys and private media storage outside the web root. It does not import local accounts, connections, listings or photos.</p>';
        if (PHP_VERSION_ID < 80200 || !is_file(__DIR__ . '/vendor/autoload.php')) {
            echo '<div class="notice notice-error"><p>PHP 8.2 or later and the packaged integration dependencies are required. The public site remains unchanged.</p></div></div>'; return;
        }
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('ag-dealer-install-review');
        echo '<input type="hidden" name="action" value="ag_dealer_install_review">';
        submit_button('Enable administrator-only workspace');
        echo '</form></div>';
    }
    public static function install(): void
    {
        if (!Review::admin()) { wp_die('Not found.', '', ['response' => 404]); }
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') { wp_die('POST required.', '', ['response' => 405]); }
        check_admin_referer('ag-dealer-install-review');
        if (get_option('autoagora_dealer_admin_release') && Support::ready()) {
            wp_safe_redirect(admin_url('tools.php?page=ag-dealer-integrations&activated=1')); exit;
        }
        try {
            if (PHP_VERSION_ID < 80200 || !extension_loaded('openssl') || !extension_loaded('curl') || !is_file(__DIR__ . '/vendor/autoload.php')) { throw new \RuntimeException('PHP 8.2+, OpenSSL, cURL and packaged dependencies are required.'); }
            $root = realpath(ABSPATH);
            $parent = realpath(dirname(rtrim(ABSPATH, '/\\')));
            $dir = self::directory();
            if (!$root || !$parent || str_starts_with($parent . '/', $root . '/') || is_link($dir)) { throw new \RuntimeException('Private storage must be outside the web root.'); }
            if (!is_dir($dir) && !mkdir($dir, 0700)) { throw new \RuntimeException('The server cannot create private storage outside the web root.'); }
            if (realpath($dir) !== $parent . '/.autoagora-dealer' || !is_writable($dir) || !chmod($dir, 0700)) { throw new \RuntimeException('Private storage permissions could not be secured.'); }
            if (!is_dir($dir . '/media') && !mkdir($dir . '/media', 0700)) { throw new \RuntimeException('Private media storage could not be created.'); }
            $path = $dir . '/runtime.json';
            if (!is_file($path)) {
                // Exclusive writes prevent replacing existing environment credentials after a partial setup.
                if (file_exists($dir . '/private.pem') || file_exists($dir . '/public.pem')) { throw new \RuntimeException('Partial credentials exist. An administrator must recover them before retrying.'); }
                $key = openssl_pkey_new(['private_key_bits' => 2048, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
                $private = '';
                if (!$key || !openssl_pkey_export($key, $private)) { throw new \RuntimeException('Signing key generation failed.'); }
                $details = openssl_pkey_get_details($key);
                if (empty($details['key'])) { throw new \RuntimeException('Public key generation failed.'); }
                self::write($dir . '/private.pem', $private);
                self::write($dir . '/public.pem', $details['key']);
                self::write($path, wp_json_encode(['encryption' => bin2hex(random_bytes(32))]));
            }
            $config = json_decode(file_get_contents($path), true);
            if (!is_array($config) || strlen($config['encryption'] ?? '') < 32 || !is_readable($dir . '/private.pem') || !is_readable($dir . '/public.pem')) { throw new \RuntimeException('The private configuration is incomplete.'); }
            Schema::install();
            update_option('autoagora_dealer_admin_release', 1, true);
            update_option('autoagora_dealer_review_data_exists', 1, true);
            // Deliberately no clients, grants, tokens, test users, profiles, stock, mail or scheduled jobs.
            wp_safe_redirect(admin_url('tools.php?page=ag-dealer-integrations&activated=1')); exit;
        } catch (\Throwable $error) {
            wp_die(esc_html($error->getMessage()) . ' Public onboarding remains disabled. No test data has been imported.', 'Dealer integration setup', ['response' => 503, 'back_link' => true]);
        }
    }
    private static function write(string $path, string $contents): void
    {
        $old = umask(0077);
        $file = fopen($path, 'x');
        umask($old);
        if (!$file) { throw new \RuntimeException('A private credential file could not be created.'); }
        $written = fwrite($file, $contents);
        fclose($file);
        if ($written !== strlen($contents) || !chmod($path, 0600)) { throw new \RuntimeException('A private credential file could not be secured.'); }
    }
}
