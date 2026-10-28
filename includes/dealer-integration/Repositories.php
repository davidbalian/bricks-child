<?php
/** Persistence adapters; the League library owns token generation and verification. */
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

use League\OAuth2\Server\Entities as E;
use League\OAuth2\Server\Repositories as R;
use League\OAuth2\Server\Exception\OAuthServerException;

final class Clients implements R\ClientRepositoryInterface
{
    public static function row(string $id): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Support::table('clients') . ' WHERE id = %s AND enabled = 1', $id), ARRAY_A) ?: null;
    }
    public function getClientEntity(string $clientIdentifier): ?E\ClientEntityInterface
    {
        $row = self::row($clientIdentifier);
        return $row ? new Client($row) : null;
    }
    public function validateClient(string $clientIdentifier, ?string $clientSecret, ?string $grantType): bool
    {
        $row = self::row($clientIdentifier);
        return $row && in_array($grantType, [null, 'authorization_code', 'refresh_token'], true)
            && is_string($clientSecret) && wp_check_password($clientSecret, $row['secret_hash']);
    }
}

final class Grants
{
    public static function active(string $client, int $dealer): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT * FROM ' . Support::table('grants') . ' WHERE client_id = %s AND dealer_id = %d AND revoked = 0 ORDER BY created_at DESC LIMIT 1', $client, $dealer), ARRAY_A) ?: null;
    }
    public static function revoke(string $client, int $dealer): void
    {
        global $wpdb;
        $updated = $wpdb->update(Support::table('grants'), ['revoked' => 1], ['client_id' => $client, 'dealer_id' => $dealer, 'revoked' => 0]);
        if ($updated === false) {
            throw new \RuntimeException('Could not revoke authorization.');
        }
        if ($updated > 0) { Support::audit('authorization_revoked', $client, $dealer); }
    }
    public static function create(string $client, int $dealer, array $scopes): void
    {
        global $wpdb;
        self::revoke($client, $dealer);
        $ok = $wpdb->insert(Support::table('grants'), [
            'id' => wp_generate_uuid4(), 'client_id' => $client, 'dealer_id' => $dealer,
            'scopes' => wp_json_encode($scopes), 'revoked' => 0, 'created_at' => time(),
        ]);
        if (!$ok) { throw new \RuntimeException('Could not save authorization.'); }
        Support::audit('authorization_granted', $client, $dealer);
    }
}

final class Scopes implements R\ScopeRepositoryInterface
{
    public function getScopeEntityByIdentifier(string $identifier): ?E\ScopeEntityInterface
    {
        return isset(Support::SCOPES[$identifier]) ? new Scope($identifier) : null;
    }
    public function finalizeScopes(array $scopes, string $grantType, E\ClientEntityInterface $clientEntity, ?string $userIdentifier = null, ?string $authCodeId = null): array
    {
        $row = Clients::row($clientEntity->getIdentifier());
        $allowed = $row ? json_decode($row['scopes'], true) : [];
        if ($userIdentifier !== null) {
            if (!Support::eligible((int) $userIdentifier)) { throw OAuthServerException::accessDenied('Dealer account is not eligible.'); }
            $grant = Grants::active($clientEntity->getIdentifier(), (int) $userIdentifier);
            $allowed = $grant ? array_intersect($allowed, json_decode($grant['scopes'], true)) : [];
        }
        foreach ($scopes as $scope) {
            if (!in_array($scope->getIdentifier(), $allowed, true)) { throw OAuthServerException::invalidScope($scope->getIdentifier()); }
        }
        if (!$scopes) { throw OAuthServerException::invalidScope(''); }
        return $scopes;
    }
}

final class Ledger
{
    public static function save(string $id, string $kind, string $client, int $dealer, int $expires): void
    {
        global $wpdb;
        $grant = Grants::active($client, $dealer);
        if (!$grant || !Support::eligible($dealer)) { throw OAuthServerException::accessDenied(); }
        if (!$wpdb->insert(Support::table('tokens'), [
            'id' => $id, 'kind' => $kind, 'grant_id' => $grant['id'], 'client_id' => $client,
            'dealer_id' => $dealer, 'expires_at' => $expires, 'revoked' => 0,
        ])) { throw new \RuntimeException('Could not persist OAuth credential.'); }
    }
    public static function row(string $id, string $kind): ?array
    {
        global $wpdb;
        return $wpdb->get_row($wpdb->prepare('SELECT t.* FROM ' . Support::table('tokens') . ' t JOIN ' . Support::table('grants') . ' g ON g.id = t.grant_id WHERE t.id = %s AND t.kind = %s AND t.revoked = 0 AND t.expires_at > %d AND g.revoked = 0', $id, $kind, time()), ARRAY_A) ?: null;
    }
    public static function revoked(string $id, string $kind): bool
    {
        $row = self::row($id, $kind);
        return !$row || !Clients::row($row['client_id']) || !Support::eligible((int) $row['dealer_id']);
    }
    public static function revoke(string $id): void
    {
        global $wpdb;
        if ($wpdb->update(Support::table('tokens'), ['revoked' => 1], ['id' => $id]) === false) {
            throw new \RuntimeException('Could not revoke OAuth credential.');
        }
    }
}

final class AccessTokens implements R\AccessTokenRepositoryInterface
{
    public static ?string $issued = null;
    public function getNewToken(E\ClientEntityInterface $clientEntity, array $scopes, ?string $userIdentifier = null): E\AccessTokenEntityInterface
    {
        $token = new AccessToken();
        $token->setClient($clientEntity);
        $token->setUserIdentifier($userIdentifier);
        foreach ($scopes as $scope) { $token->addScope($scope); }
        return $token;
    }
    public function persistNewAccessToken(E\AccessTokenEntityInterface $accessTokenEntity): void
    {
        Ledger::save($accessTokenEntity->getIdentifier(), 'access', $accessTokenEntity->getClient()->getIdentifier(), (int) $accessTokenEntity->getUserIdentifier(), $accessTokenEntity->getExpiryDateTime()->getTimestamp());
        self::$issued = $accessTokenEntity->getIdentifier();
    }
    public function revokeAccessToken(string $tokenId): void { Ledger::revoke($tokenId); }
    public function isAccessTokenRevoked(string $tokenId): bool { return Ledger::revoked($tokenId, 'access'); }
}

final class AuthCodes implements R\AuthCodeRepositoryInterface
{
    public function getNewAuthCode(): E\AuthCodeEntityInterface { return new AuthCode(); }
    public function persistNewAuthCode(E\AuthCodeEntityInterface $authCodeEntity): void
    {
        Ledger::save($authCodeEntity->getIdentifier(), 'code', $authCodeEntity->getClient()->getIdentifier(), (int) $authCodeEntity->getUserIdentifier(), $authCodeEntity->getExpiryDateTime()->getTimestamp());
    }
    public function revokeAuthCode(string $codeId): void { Ledger::revoke($codeId); }
    public function isAuthCodeRevoked(string $codeId): bool { return Ledger::revoked($codeId, 'code'); }
}

final class RefreshTokens implements R\RefreshTokenRepositoryInterface
{
    public static ?string $issued = null;
    public function getNewRefreshToken(): ?E\RefreshTokenEntityInterface { return new RefreshToken(); }
    public function persistNewRefreshToken(E\RefreshTokenEntityInterface $refreshTokenEntity): void
    {
        $access = $refreshTokenEntity->getAccessToken();
        Ledger::save($refreshTokenEntity->getIdentifier(), 'refresh', $access->getClient()->getIdentifier(), (int) $access->getUserIdentifier(), $refreshTokenEntity->getExpiryDateTime()->getTimestamp());
        self::$issued = $refreshTokenEntity->getIdentifier();
    }
    public function revokeRefreshToken(string $tokenId): void { Ledger::revoke($tokenId); }
    public function isRefreshTokenRevoked(string $tokenId): bool { return Ledger::revoked($tokenId, 'refresh'); }
}
