<?php
namespace AutoAgora\Dealer;

defined('ABSPATH') || exit;

use League\OAuth2\Server\Entities;
use League\OAuth2\Server\Entities\Traits;

final class Client implements Entities\ClientEntityInterface
{
    use Traits\EntityTrait;
    use Traits\ClientTrait;
    public function __construct(array $row)
    {
        $this->setIdentifier($row['id']);
        $this->name = $row['name'];
        $this->redirectUri = json_decode($row['redirects'], true);
        $this->isConfidential = true;
    }
}

final class Scope implements Entities\ScopeEntityInterface
{
    use Traits\EntityTrait;
    use Traits\ScopeTrait;
    public function __construct(string $id) { $this->setIdentifier($id); }
}

final class Dealer implements Entities\UserEntityInterface
{
    use Traits\EntityTrait;
    public function __construct(int $id) { $this->setIdentifier((string) $id); }
}

final class AccessToken implements Entities\AccessTokenEntityInterface
{
    use Traits\EntityTrait;
    use Traits\TokenEntityTrait;
    use Traits\AccessTokenTrait;
}

final class AuthCode implements Entities\AuthCodeEntityInterface
{
    use Traits\EntityTrait;
    use Traits\TokenEntityTrait;
    use Traits\AuthCodeTrait;
}

final class RefreshToken implements Entities\RefreshTokenEntityInterface
{
    use Traits\EntityTrait;
    use Traits\RefreshTokenTrait;
}
