<?php

declare(strict_types=1);

namespace WScore\Auth;

/**
 * `credentials` 配列のキー。{@see UserProviderInterface::findByIdentity} 実装と揃える。
 *
 * @phpstan-type Credentials array<string, mixed>
 * @phpstan-type Options array<string, mixed>
 */
final readonly class Identity
{
    /** パスワードログイン時の識別子（メール・ログイン名・任意のログイン ID 等） */
    public const CREDENTIAL_LOGIN = 'login';

    public const CREDENTIAL_PASSWORD = 'password';

    /** ForceLogin 時の対象（内部 user_id 等、アプリの解釈に従う） */
    public const CREDENTIAL_FORCE_USER_ID = 'user_id';

    /** Social / OIDC でプロバイダが付与するユーザー固有情報の主キー */
    public const CREDENTIAL_PROVIDER_USER_ID = 'provider_user_id';

    public const CREDENTIAL_ONE_TIME_TOKEN = 'token';

    /** Remember-me 検証用（クッキー等に載せたユーザー側の識別子） */
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
     * Social / OIDC でプロバイダが付与する **ユーザー固有情報の主キー**（OIDC の `sub`、各 API の `id` 等。
     * ブリッジ側で生データから取り出し、ここへ文字列として渡す）。
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
     * Remember-me 検証用（クッキーのユーザー ID + トークンなど）。
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
