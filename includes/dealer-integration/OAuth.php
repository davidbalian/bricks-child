<?php
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

use League\OAuth2\Server\AuthorizationServer;
use League\OAuth2\Server\ResourceServer;
use League\OAuth2\Server\Grant\AuthCodeGrant;
use League\OAuth2\Server\Grant\RefreshTokenGrant;
use League\OAuth2\Server\Exception\OAuthServerException;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7Server\ServerRequestCreator;
use Psr\Http\Message\ResponseInterface;

final class OAuth
{
    public static function loginReturn($url): string
    {
        if (!is_string($url) || !Support::enabled()) { return ''; }
        $parts = wp_parse_url($url);
        $home = wp_parse_url(home_url('/'));
        if (!$parts || ($parts['scheme'] ?? '') !== $home['scheme'] || ($parts['host'] ?? '') !== $home['host']
            || ($parts['port'] ?? null) !== ($home['port'] ?? null) || ($parts['path'] ?? '/') !== ($home['path'] ?? '/')
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) { return ''; }
        parse_str($parts['query'] ?? '', $query);
        return in_array($query['autoagora_connect'] ?? '', ['authorize', 'connections'], true) ? $url : '';
    }
    public static function request(): \Psr\Http\Message\ServerRequestInterface
    {
        $factory = new Psr17Factory();
        return (new ServerRequestCreator($factory, $factory, $factory, $factory))->fromGlobals();
    }
    public static function server(): AuthorizationServer
    {
        $server = new AuthorizationServer(new Clients(), new AccessTokens(), new Scopes(),
            'file://' . AUTOAGORA_DEALER_PRIVATE_KEY, AUTOAGORA_DEALER_ENCRYPTION_KEY);
        $code = new AuthCodeGrant(new AuthCodes(), new RefreshTokens(), new \DateInterval('PT5M'));
        $code->setRefreshTokenTTL(new \DateInterval('P30D'));
        $refresh = new RefreshTokenGrant(new RefreshTokens());
        $refresh->setRefreshTokenTTL(new \DateInterval('P30D'));
        foreach ([$code, $refresh] as $grant) { $server->enableGrantType($grant, new \DateInterval('PT1H')); }
        return $server;
    }
    public static function context(\WP_REST_Request $request, string $scope, bool $rate = true)
    {
        if (!Support::ready()) { return Support::error('integration_unavailable', 'Dealer integration is not configured.', 503); }
        try {
            $server = new ResourceServer(new AccessTokens(), 'file://' . AUTOAGORA_DEALER_PUBLIC_KEY);
            $verified = $server->validateAuthenticatedRequest(self::request()->withHeader('Authorization', $request->get_header('authorization')));
            $dealer = (int) $verified->getAttribute('oauth_user_id');
            $client = (string) $verified->getAttribute('oauth_client_id');
            if (!Support::eligible($dealer)) { return Support::error('dealer_ineligible', 'An eligible dealership account is required.', 403); }
            if (!in_array($scope, (array) $verified->getAttribute('oauth_scopes'), true)) {
                return Support::error('insufficient_scope', 'This connection has not been granted the required permission.', 403, ['required_scope' => $scope]);
            }
            if ($rate && !Support::rate('api:' . $client . ':' . $dealer, 60)) { return Support::error('rate_limit', 'Request limit exceeded. Retry in 60 seconds.', 429); }
            return ['dealer' => $dealer, 'client' => $client];
        } catch (\Throwable $error) {
            return Support::error('invalid_token', 'Access token is missing, expired, or revoked.', 401);
        }
    }
    public static function token(\WP_REST_Request $request)
    {
        if (!Support::ready()) { return Support::error('integration_unavailable', 'Dealer integration is not configured.', 503); }
        if (strlen($request->get_body()) > 32768) { return Support::error('request_too_large', 'OAuth requests are limited to 32 KiB.', 413); }
        $body = $request->get_body_params();
        foreach ($body as $value) {
            if (!is_string($value)) { return new \WP_REST_Response(['error' => 'invalid_request'], 400); }
        }
        $psr = self::request()->withParsedBody($body);
        $client = (string) ($body['client_id'] ?? ($_SERVER['PHP_AUTH_USER'] ?? ''));
        if (!Support::rate('token:' . $client, 30)) { return Support::error('rate_limit', 'Too many token requests.', 429); }
        return Support::lock('oauth:' . $client, static function () use ($psr) {
            global $wpdb;
            AccessTokens::$issued = RefreshTokens::$issued = null;
            $wpdb->query('START TRANSACTION');
            try {
                $response = self::server()->respondToAccessTokenRequest($psr, new \Nyholm\Psr7\Response());
                $body = json_decode((string) $response->getBody(), true);
                foreach (['access_token' => AccessTokens::$issued, 'refresh_token' => RefreshTokens::$issued] as $field => $id) {
                    if ($id && isset($body[$field]) && $wpdb->update(Support::table('tokens'), ['wire_hash' => hash('sha256', $body[$field])], ['id' => $id]) === false) {
                        throw new \RuntimeException('Could not store token reference.');
                    }
                }
                $wpdb->query('COMMIT');
                return self::restResponse($response);
            } catch (OAuthServerException $error) {
                $wpdb->query('ROLLBACK');
                return self::restResponse($error->generateHttpResponse(new \Nyholm\Psr7\Response()));
            } catch (\Throwable $error) {
                $wpdb->query('ROLLBACK');
                return Support::error('token_failed', 'Could not issue tokens. Please reconnect.', 500);
            }
        });
    }
    public static function revoke(\WP_REST_Request $request)
    {
        global $wpdb;
        if (!Support::ready()) { return Support::error('integration_unavailable', 'Dealer integration is not configured.', 503); }
        $body = $request->get_body_params();
        foreach ($body as $value) {
            if (!is_string($value)) { return new \WP_REST_Response(['error' => 'invalid_request'], 400); }
        }
        $client = (string) ($body['client_id'] ?? ($_SERVER['PHP_AUTH_USER'] ?? ''));
        $secret = $body['client_secret'] ?? ($_SERVER['PHP_AUTH_PW'] ?? null);
        if (!Support::rate('revoke:' . $client, 30)) { return Support::error('rate_limit', 'Too many requests.', 429); }
        if (!(new Clients())->validateClient($client, is_string($secret) ? $secret : null, null)) {
            return new \WP_REST_Response(['error' => 'invalid_client'], 401, ['Cache-Control' => 'no-store']);
        }
        if (!isset($body['token']) || !is_string($body['token']) || strlen($body['token']) > 16384) {
            return new \WP_REST_Response(['error' => 'invalid_request'], 400, ['Cache-Control' => 'no-store']);
        }
        $row = $wpdb->get_row($wpdb->prepare('SELECT dealer_id FROM ' . Support::table('tokens') . ' WHERE wire_hash = %s AND client_id = %s', hash('sha256', $body['token']), $client), ARRAY_A);
        if ($row) {
            $result = Support::lock('oauth:' . $client, static function () use ($client, $row) { Grants::revoke($client, (int) $row['dealer_id']); return true; });
            if (is_wp_error($result)) { return $result; }
        }
        return new \WP_REST_Response(null, 200, ['Cache-Control' => 'no-store']);
    }
    private static function restResponse(ResponseInterface $response): \WP_REST_Response
    {
        return new \WP_REST_Response(json_decode((string) $response->getBody(), true), $response->getStatusCode(),
            ['Cache-Control' => 'no-store', 'Pragma' => 'no-cache']);
    }
    public static function browser(): void
    {
        $action = isset($_GET['autoagora_connect']) && is_string($_GET['autoagora_connect']) ? $_GET['autoagora_connect'] : '';
        if (!in_array($action, ['authorize', 'connections'], true)) { return; }
        nocache_headers();
        header('Referrer-Policy: no-referrer');
        header('X-Robots-Tag: noindex, nofollow, noarchive');
        header('X-Frame-Options: DENY');
        header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; form-action 'self'; frame-ancestors 'none'");
        if (!Support::ready()) { wp_die('Dealer integration is not configured.', 'Connect AutoAgora', ['response' => 503]); }
        if (!is_user_logged_in()) {
            wp_safe_redirect(wp_login_url(home_url('/?' . ($_SERVER['QUERY_STRING'] ?? ''))));
            exit;
        }
        $dealer = get_current_user_id();
        if ($action === 'connections') { self::connections($dealer); exit; }
        if (!Support::eligible($dealer)) { wp_die('An eligible AutoAgora dealership account is required.', 'Connect AutoAgora', ['response' => 403]); }
        $auth = null;
        try {
            $psr = self::request();
            $query = $psr->getQueryParams();
            foreach (['state', 'scope', 'redirect_uri', 'code_challenge'] as $required) {
                if (!isset($query[$required]) || !is_string($query[$required]) || $query[$required] === '' || strlen($query[$required]) > 2048) {
                    throw OAuthServerException::invalidRequest($required);
                }
            }
            if (($query['code_challenge_method'] ?? '') !== 'S256' || strlen($query['code_challenge']) !== 43) {
                throw OAuthServerException::invalidRequest('code_challenge_method', 'S256 PKCE is required.');
            }
            $server = self::server();
            $auth = $server->validateAuthorizationRequest($psr);
            // Browsers can apply form-action to redirects after POST consent too.
            // Permit only the origin of the exact callback already validated by the library.
            $callback = wp_parse_url($query['redirect_uri']);
            $callbackOrigin = $callback['scheme'] . '://' . $callback['host'] . (isset($callback['port']) ? ':' . $callback['port'] : '');
            header("Content-Security-Policy: default-src 'self'; style-src 'unsafe-inline'; form-action 'self' " . $callbackOrigin . "; frame-ancestors 'none'");
            $client = $auth->getClient()->getIdentifier();
            $scopes = array_map(static fn ($s) => $s->getIdentifier(), $auth->getScopes());
            $allowed = json_decode(Clients::row($client)['scopes'], true);
            if (!$scopes || array_diff($scopes, $allowed)) { throw OAuthServerException::invalidScope(''); }
            $nonceAction = 'ag-consent:' . hash('sha256', wp_json_encode($query));
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
                if (!isset($_POST['_wpnonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), $nonceAction)) {
                    wp_die('The authorization form expired. Please reconnect.', 'Connect AutoAgora', ['response' => 403]);
                }
                $approved = ($_POST['decision'] ?? '') === 'approve';
                $response = Support::lock('oauth:' . $client, static function () use ($approved, $client, $dealer, $scopes, $auth, $server) {
                    if ($approved) { Grants::create($client, $dealer, $scopes); }
                    $auth->setUser(new Dealer($dealer));
                    $auth->setAuthorizationApproved($approved);
                    return $server->completeAuthorizationRequest($auth, new \Nyholm\Psr7\Response());
                });
                if (is_wp_error($response)) { wp_die(esc_html($response->get_error_message()), 'Connect AutoAgora', ['response' => 409]); }
                // The library has validated this exact registered redirect; external redirects are intentional here.
                wp_redirect($response->getHeaderLine('Location'), 302, 'AutoAgora OAuth');
                exit;
            }
            self::head('Connect AutoAgora');
            echo '<h1>Connect ' . esc_html($auth->getClient()->getName()) . '</h1><p>Signed in as ' . esc_html(wp_get_current_user()->display_name) . '. This application will be able to:</p><ul>';
            foreach ($scopes as $scope) { echo '<li>' . esc_html(Support::SCOPES[$scope]) . '</li>'; }
            echo '</ul><p>Your password stays with AutoAgora. You can disconnect this application at any time.</p><form method="post">';
            wp_nonce_field($nonceAction);
            echo '<button class="btn btn-primary" name="decision" value="approve">Authorize connection</button> <button class="btn btn-secondary" name="decision" value="deny">Cancel</button></form></main></body></html>';
            exit;
        } catch (OAuthServerException $error) {
            if ($auth !== null && ($_POST['decision'] ?? '') === 'deny') {
                $denied = $error->generateHttpResponse(new \Nyholm\Psr7\Response());
                if ($denied->getHeaderLine('Location') !== '') { wp_redirect($denied->getHeaderLine('Location'), 302, 'AutoAgora OAuth'); exit; }
            }
            // Never redirect errors to an unvalidated URI or expose credential material.
            wp_die('Authorization failed: ' . esc_html($error->getErrorType()) . '. Return to the partner and try again.', 'Connect AutoAgora', ['response' => 400]);
        } catch (\Throwable $error) {
            wp_die('Authorization is temporarily unavailable.', 'Connect AutoAgora', ['response' => 503]);
        }
    }
    private static function connections(int $dealer): void
    {
        global $wpdb;
        if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
            check_admin_referer('ag-connections');
            $client = isset($_POST['client_id']) && is_string($_POST['client_id']) ? sanitize_text_field(wp_unslash($_POST['client_id'])) : '';
            $result = Support::lock('oauth:' . $client, static function () use ($client, $dealer) { Grants::revoke($client, $dealer); return true; });
            if (is_wp_error($result)) { wp_die('The connection is busy. Please try disconnecting again shortly.', 'Connected applications', ['response' => 409]); }
            wp_safe_redirect(home_url('/?autoagora_connect=connections'));
            exit;
        }
        self::head('Connected applications');
        echo '<h1>Connected applications</h1><p>Disconnecting immediately stops listing access and token refresh. Your listings and photos remain.</p>';
        $rows = $wpdb->get_results($wpdb->prepare('SELECT g.*, c.name FROM ' . Support::table('grants') . ' g JOIN ' . Support::table('clients') . ' c ON g.client_id = c.id WHERE g.dealer_id = %d AND g.revoked = 0', $dealer));
        if (!$rows) { echo '<p>No connected applications.</p>'; }
        foreach ($rows as $row) {
            echo '<section><h2>' . esc_html($row->name) . '</h2><ul>';
            foreach (json_decode($row->scopes, true) as $scope) { echo '<li>' . esc_html(Support::SCOPES[$scope] ?? $scope) . '</li>'; }
            echo '</ul><form method="post">'; wp_nonce_field('ag-connections');
            echo '<input type="hidden" name="client_id" value="' . esc_attr($row->client_id) . '"><button class="btn btn-danger">Disconnect</button></form></section>';
        }
        echo '</main></body></html>';
    }
    private static function head(string $title): void
    {
        header('Content-Type: text/html; charset=utf-8');
        echo '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"><title>' . esc_html($title) . '</title><style>body{font:17px/1.6 system-ui;background:#f6f7f9;color:#192335;margin:0}main{max-width:640px;margin:6vh auto;background:white;padding:32px;border-radius:16px}.btn{font:inherit;padding:10px 18px;border:1px solid #ccd2dc;border-radius:8px;cursor:pointer}.btn-primary{background:#3154e8;color:white}section{border-top:1px solid #ddd;margin-top:24px}</style></head><body><main>';
    }
}
