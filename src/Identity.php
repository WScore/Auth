<?php

declare(strict_types=1);

namespace WScore\Auth;

/**
 * @phpstan-type Credentials array<string, mixed>
 * @phpstan-type Options array<string, mixed>
 */
final readonly class Identity
{
    /**
     * @param Credentials $credentials
     * @param Options $options e.g. OAuth: ['provider' => 'google'], remember: ['remember' => true]
     */
    public function __construct(
        public AuthKind $kind,
        public array $credentials,
        public array $options = [],
    ) {
    }

    /**
     * @param Options $options e.g. ['remember' => true]
     */
    public static function newPassword(string|int $id, string $password, array $options = []): self
    {
        return new self(AuthKind::Password, [
            'id' => $id,
            'password' => $password,
        ], $options);
    }

    /** @param Options $options */
    public static function newForceLogin(string|int $id, array $options = []): self
    {
        return new self(AuthKind::ForceLogin, [
            'id' => $id,
        ], $options);
    }

    /**
     * @param Credentials $credentials e.g. ['sub' => '…', 'email' => '…']
     * @param Options $options merged after `provider` (cannot override provider key from the left)
     */
    public static function newOAuth(string $provider, array $credentials, array $options = []): self
    {
        return new self(AuthKind::OAuth, $credentials, ['provider' => $provider] + $options);
    }

    /** @param Options $options */
    public static function newOneTimeToken(string $token, array $options = []): self
    {
        return new self(AuthKind::OneTimeToken, [
            'token' => $token,
        ], $options);
    }

    /**
     * Remember-me 検証用（クッキーのユーザー ID + トークンなど）。
     *
     * @param Options $options
     */
    public static function newRemember(string|int $id, string $token, array $options = []): self
    {
        return new self(AuthKind::Remember, [
            'id' => $id,
            'token' => $token,
        ], $options);
    }
}
