<?php

declare(strict_types=1);

namespace WScore\Auth;

use WScore\Auth\Contracts\AuthSessionStoreInterface;
use WScore\Auth\Contracts\RememberMeInterface;
use WScore\Auth\Contracts\UserProviderInterface;
use WScore\Auth\RememberAdaptor\RememberCookie;
use WScore\Auth\Session\ArrayAuthSessionStore;

class Auth
{
    public const KEY = 'WS-Auth';

    /** @var array<string, mixed> */
    private array $loginInfo = [];

    private UserProviderInterface $userProvider;

    private AuthSessionStoreInterface $sessionStore;

    /**
     * @var array<mixed>
     */
    private array $localSession = [];

    /**
     * @var array<mixed>
     */
    private $sessionRef;

    private ?RememberMeInterface $rememberMe = null;

    private RememberCookie $rememberCookie;

    private string|int|null $loginId = null;

    private ?object $currentUser = null;

    /**
     * @param array|null $session session bucket (by ref); null uses $_SESSION when active else an internal array
     */
    public function __construct(
        UserProviderInterface $userProvider,
        &$session = null,
    ) {
        $this->userProvider = $userProvider;
        $this->rememberCookie = RememberCookie::forBrowser();
        $this->bindSession($session);
    }

    /**
     * Advanced: supply a custom session store (e.g. tests). Replaces array-based store.
     */
    public function setAuthSessionStore(AuthSessionStoreInterface $store): void
    {
        $this->sessionStore = $store;
    }

    /**
     * @param array|null $session
     */
    public function setSession(&$session = null): void
    {
        $this->bindSession($session);
    }

    /**
     * @param array|null $session
     */
    private function bindSession(&$session): void
    {
        if ($session === null) {
            if (session_status() === PHP_SESSION_ACTIVE) {
                $this->sessionRef = &$_SESSION;
            } else {
                $this->sessionRef = &$this->localSession;
            }
        } else {
            $this->sessionRef = &$session;
        }
        $this->sessionStore = new ArrayAuthSessionStore(
            $this->sessionRef,
            self::KEY,
            $this->userProvider->getProviderKey(),
        );
    }

    /**
     * Remember-me を有効化する（トークン保存 + クッキー）。本番ではここで DI／ファクトリから渡す想定。
     *
     * @param positive-int|null $rememberCookieLifetimeDays `$cookie` が null のとき `RememberCookie::forBrowser($days)` を使う（両方 null ならクッキー設定は据え置き）
     */
    public function setRememberMe(
        ?RememberMeInterface $rememberMe,
        ?RememberCookie $cookie = null,
        ?int $rememberCookieLifetimeDays = null,
    ): void {
        $this->rememberMe = $rememberMe;
        if ($cookie !== null) {
            $this->rememberCookie = $cookie;
        } elseif ($rememberCookieLifetimeDays !== null) {
            $this->rememberCookie = RememberCookie::forBrowser($rememberCookieLifetimeDays);
        }
    }

    public function login(Identity $identity): bool
    {
        $user = $this->userProvider->findByIdentity($identity);
        if ($user === null) {
            return false;
        }
        $this->applySuccessfulLogin($user, $identity->kind);
        if ($identity->options['remember'] ?? false) {
            $this->persistRemember($this->loginId);
        }

        return true;
    }

    public function loginWithPassword(string $loginId, string $password, bool $remember = false): bool
    {
        $options = $remember ? ['remember' => true] : [];

        return $this->login(new Identity(AuthKind::Password, [
            'id' => $loginId,
            'password' => $password,
        ], $options));
    }

    public function forceLogin(string $loginId): bool
    {
        return $this->login(new Identity(AuthKind::ForceLogin, [
            'id' => $loginId,
        ]));
    }

    /**
     * Currently logged-in user (resolved from memory, session, or remember-me).
     */
    public function user(): ?object
    {
        if ($this->currentUser !== null) {
            return $this->currentUser;
        }
        if ($this->restoreFromSession()) {
            return $this->currentUser;
        }
        if ($this->checkRemembered()) {
            return $this->currentUser;
        }

        return null;
    }

    /** @deprecated use {@see user()} */
    public function getLoginUser(): ?object
    {
        return $this->user();
    }

    public function isLogin(): bool
    {
        return $this->user() !== null;
    }

    public function isLoginBy(AuthKind $kind): bool
    {
        if (!$this->isLogin()) {
            return false;
        }
        $current = $this->loginInfo['kind'] ?? null;

        return $current instanceof AuthKind && $current === $kind;
    }

    public function getUserProvider(): UserProviderInterface
    {
        return $this->userProvider;
    }

    public function getLoginId(): string|int|null
    {
        return $this->loginId;
    }

    /**
     * @return array<string, mixed> Includes `kind` ({@see AuthKind}), `loginId`, `type` (provider key), `time`.
     */
    public function getLoginInfo(): array
    {
        return $this->loginInfo;
    }

    public function logout(): void
    {
        $this->loginId = null;
        $this->currentUser = null;
        $this->loginInfo = [];
        $this->sessionStore->clear();
    }

    private function applySuccessfulLogin(object $user, AuthKind $kind): void
    {
        $this->currentUser = $user;
        $this->loginId = $this->userProvider->getUserId($user);
        $time = date('Y-m-d H:i:s');
        $payload = [
            'userId' => $this->loginId,
            'providerKey' => $this->userProvider->getProviderKey(),
            'loginKind' => $kind->name,
            'time' => $time,
        ];
        $this->sessionStore->write($payload);
        $this->loginInfo = $this->buildLoginInfo($this->loginId, $kind, $time);
    }

    /**
     * @return array<string, mixed>
     */
    private function buildLoginInfo(string|int $loginId, AuthKind $kind, string $time): array
    {
        return [
            'loginId' => $loginId,
            'kind' => $kind,
            'type' => $this->userProvider->getProviderKey(),
            'time' => $time,
        ];
    }

    private function restoreFromSession(): bool
    {
        $payload = $this->sessionStore->read();
        if ($payload === null) {
            return false;
        }
        if (($payload['providerKey'] ?? '') !== $this->userProvider->getProviderKey()) {
            return false;
        }
        $userId = $payload['userId'] ?? null;
        if ($userId === null) {
            return false;
        }
        $user = $this->userProvider->findById($userId);
        if ($user === null) {
            $this->sessionStore->clear();

            return false;
        }
        $kind = $this->parseLoginKindFromPayload($payload);
        $this->currentUser = $user;
        $this->loginId = $userId;
        $this->loginInfo = $this->buildLoginInfo(
            $userId,
            $kind,
            $payload['time'] ?? date('Y-m-d H:i:s'),
        );

        return true;
    }

    /**
     * @param array<string, mixed> $payload
     */
    private function parseLoginKindFromPayload(array $payload): AuthKind
    {
        $name = $payload['loginKind'] ?? '';
        foreach (AuthKind::cases() as $case) {
            if ($case->name === $name) {
                return $case;
            }
        }

        return AuthKind::Password;
    }

    private function checkRemembered(): bool
    {
        if ($this->rememberMe === null) {
            return false;
        }
        $loginId = $this->rememberCookie->retrieveId();
        if ($loginId === null || $loginId === '') {
            return false;
        }
        $token = $this->rememberCookie->retrieveToken();
        if ($token === null || $token === '') {
            return false;
        }
        if (!$this->rememberMe->verifyRemember($loginId, $token)) {
            return false;
        }
        $user = $this->userProvider->findById($loginId);
        if ($user === null) {
            return false;
        }
        $this->currentUser = $user;
        $this->loginId = $this->userProvider->getUserId($user);
        $time = date('Y-m-d H:i:s');
        $kind = AuthKind::Remember;
        $payload = [
            'userId' => $this->loginId,
            'providerKey' => $this->userProvider->getProviderKey(),
            'loginKind' => $kind->name,
            'time' => $time,
        ];
        $this->sessionStore->write($payload);
        $this->loginInfo = $this->buildLoginInfo($this->loginId, $kind, $time);
        $this->persistRemember($this->loginId, $token);

        return true;
    }

    /**
     * @param string|int|null $loginId
     */
    private function persistRemember(string|int|null $loginId, ?string $existingToken = null): void
    {
        if ($this->rememberMe === null || $loginId === null) {
            return;
        }
        $token = $this->rememberMe->generateToken($loginId, $existingToken);
        if ($token !== false && $token !== null && $token !== '') {
            $this->rememberCookie->save((string) $loginId, (string) $token);
        }
    }
}
