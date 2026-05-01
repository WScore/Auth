<?php

declare(strict_types=1);

namespace WScore\Auth;

/**
 * Keys for the `credentials` array. 
 * Keep these aligned with {@see \WScore\Auth\Contracts\UserProviderInterface::findByIdentity} implementations.
 *
 * @phpstan-type Credentials array<string, mixed>
 * @phpstan-type Options array<string, mixed>
 */
final readonly class Identity
{
    /** Identifier for password login (email, username, or any login id your app uses). */
    public const CREDENTIAL_LOGIN = 'login';

    public const CREDENTIAL_PASSWORD = 'password';

    /** Subject user for ForceLogin (e.g. internal `user_id`; meaning is app-defined). */
    public const CREDENTIAL_FORCE_USER_ID = 'user_id';

    /** Stable per-user key from the provider for social / OIDC sign-in. */
    public const CREDENTIAL_PROVIDER_USER_ID = 'provider_user_id';

    public const CREDENTIAL_ONE_TIME_TOKEN = 'token';

    /** Client-side user identifier for remember-me verification (e.g. from a cookie). */
    public const CREDENTIAL_REMEMBER_USER_ID = 'remember_user_id';

    public const CREDENTIAL_REMEMBER_TOKEN = 'remember_token';

    /**
     * @param Credentials $credentials
     * @param Options $options e.g., OAuth: ['provider' => 'google'], remember: ['remember' => true]
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
    public static function newPassword(string|int $login, string $password, array $options = []): self
    {
        return new self(AuthKind::Password, [
            self::CREDENTIAL_LOGIN => $login,
            self::CREDENTIAL_PASSWORD => $password,
        ], $options);
    }

    /** @param Options $options */
    public static function newForceLogin(string|int $userId, array $options = []): self
    {
        return new self(AuthKind::ForceLogin, [
            self::CREDENTIAL_FORCE_USER_ID => $userId,
        ], $options);
    }

    /**
     * Stable **per-user key** from the provider (OIDC `sub`, vendor `id`, etc.).
     * Your bridge layer should extract it from raw provider data and pass it here as a string.
     *
     * @param Credentials $extraCredentials Optional fields (e.g. email, name, avatar); provider-specific.
     * @param Options     $options          Merged with `['provider' => $provider]`; that entry wins and cannot be overridden.
     */
    public static function newOAuth(
        string $provider,
        string $providerUserId,
        array $extraCredentials = [],
        array $options = [],
    ): self {
        $credentials = [self::CREDENTIAL_PROVIDER_USER_ID => $providerUserId] + $extraCredentials;

        return new self(AuthKind::OAuth, $credentials, ['provider' => $provider] + $options);
    }

    /** @param Options $options */
    public static function newOneTimeToken(string $token, array $options = []): self
    {
        return new self(AuthKind::OneTimeToken, [
            self::CREDENTIAL_ONE_TIME_TOKEN => $token,
        ], $options);
    }

    /**
     * Remember-me verification (user id + token as stored in cookies or similar).
     *
     * @param Options $options
     */
    public static function newRemember(string|int $userId, string $token, array $options = []): self
    {
        return new self(AuthKind::Remember, [
            self::CREDENTIAL_REMEMBER_USER_ID => $userId,
            self::CREDENTIAL_REMEMBER_TOKEN => $token,
        ], $options);
    }
}
