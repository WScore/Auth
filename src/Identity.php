<?php

declare(strict_types=1);

namespace WScore\Auth;

/**
 * @phpstan-type Credentials array<string, mixed>
 * @phpstan-type Options array<string, mixed>
 */
final readonly class Identity
{
    public const PROVIDER_USER_ID_KEY = 'provider_user_id';

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
     * Social / OIDC でプロバイダが付与する **ユーザー固有情報の主キー**（OIDC の `sub`、各 API の `id` 等。
     * ブリッジ側で生データから取り出し、ここへ文字列として渡す）。
     *
     * `credentials` 内では常に {@see self::PROVIDER_USER_ID_KEY} で参照できるようにする。
     *
     * @param Credentials $extraCredentials 例: email, name, avatar など（プロバイダ任せ）
     * @param Options $options `provider` は第1引数とマージされ、左側が優先（上書き不可）
     */
    public static function newOAuth(
        string $provider,
        string $providerUserId,
        array $extraCredentials = [],
        array $options = [],
    ): self {
        $credentials = [self::PROVIDER_USER_ID_KEY => $providerUserId] + $extraCredentials;

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
