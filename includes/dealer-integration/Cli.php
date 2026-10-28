<?php
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

/** Operator commands never print credentials; secret output must be a private local file. */
final class Cli
{
    public function install(): void
    {
        Schema::install();
        if (wp_get_schedule('autoagora_dealer_cleanup') !== 'hourly') {
            wp_clear_scheduled_hook('autoagora_dealer_cleanup');
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'autoagora_dealer_cleanup');
        }
        \WP_CLI::success('Dealer integration tables installed.');
    }
    /**
     * Register one partner application in this environment.
     *
     * ## OPTIONS
     * <id>
     * --name=<name>
     * --redirect=<url>
     * --output=<absolute-path>
     */
    public function client(array $args, array $options): void
    {
        global $wpdb;
        $id = $args[0] ?? '';
        $url = $options['redirect'] ?? '';
        $parts = wp_parse_url($url);
        $local = wp_get_environment_type() !== 'production' && in_array($parts['host'] ?? '', ['127.0.0.1', 'localhost'], true);
        if (!preg_match('/^[a-z0-9_-]{3,64}$/D', $id) || !filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^[a-z0-9.-]+$/iD', $parts['host'] ?? '') || isset($parts['fragment']) || isset($parts['user']) || isset($parts['pass'])
            || !isset($parts['host']) || (!$local && ($parts['scheme'] ?? '') !== 'https')) {
            \WP_CLI::error('Supply a valid client ID and an exact HTTPS callback URL (loopback allowed in non-production).');
        }
        $path = $options['output'] ?? '';
        $parent = realpath(dirname($path));
        $root = realpath(ABSPATH);
        if (!$parent || !str_starts_with($path, '/') || file_exists($path) || str_starts_with($parent . '/', $root . '/')) {
            \WP_CLI::error('Output must be a new absolute file outside the web root.');
        }
        if (Clients::row($id)) { \WP_CLI::error('Client already exists. Use a different ID; no credential was replaced.'); }
        $secret = wp_generate_password(64, true, true);
        $old = umask(0077);
        $file = fopen($path, 'x');
        umask($old);
        if (!$file) { \WP_CLI::error('Could not create private credential file.'); }
        $data = ['client_id' => $id, 'client_secret' => $secret, 'redirect_uri' => $url, 'site_url' => home_url('/')];
        if (fwrite($file, wp_json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) === false) {
            fclose($file); unlink($path); \WP_CLI::error('Could not write private credentials.');
        }
        fclose($file);
        if (!$wpdb->insert(Support::table('clients'), ['id' => $id, 'name' => sanitize_text_field($options['name'] ?? $id),
            'secret_hash' => wp_hash_password($secret), 'redirects' => wp_json_encode([$url]), 'scopes' => wp_json_encode(array_keys(Support::SCOPES)), 'enabled' => 1])) {
            unlink($path); \WP_CLI::error('Client registration failed.');
        }
        \WP_CLI::success('Partner client registered. Credentials were written only to the requested private file.');
    }
    /**
     * Disable a partner and all existing credentials without deleting its listings.
     *
     * ## OPTIONS
     * <id>
     */
    public function disable(array $args): void
    {
        global $wpdb;
        $id = $args[0] ?? '';
        $wpdb->update(Support::table('clients'), ['enabled' => 0], ['id' => $id]);
        $wpdb->update(Support::table('grants'), ['revoked' => 1], ['client_id' => $id]);
        \WP_CLI::success('Partner disabled. Existing listings retained.');
    }
    public function cleanup(): void
    {
        Support::cleanup();
        \WP_CLI::success('Bounded integration cleanup completed.');
    }
}
if (defined('WP_CLI') && WP_CLI) { \WP_CLI::add_command('autoagora dealer', Cli::class); }
