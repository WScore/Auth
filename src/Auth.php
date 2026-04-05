<?php

declare(strict_types=1);

namespace WScore\Auth;

use WScore\Auth\Contracts\AuthSessionStoreInterface;
use WScore\Auth\Contracts\RememberMeInterface;
use WScore\Auth\Contracts\UserProviderInterface;
use WScore\Auth\Session\ArrayAuthSessionStore;

class Auth
{
    public const KEY = 'WS-Auth';

    public const AUTH_NONE = 0;
    public const AUTH_OK = 1;
    public const AUTH_FAILED = -1;

    public const BY_POST = 'WITH_PWD';
    public const BY_REMEMBER = 'REMEMBER';
    public const BY_FORCED = 'FORCED';
    public const BY_OAUTH = 'OAUTH';

    private int $status = self::AUTH_NONE;

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
        ?RememberMeInterface $rememberMe = null,
        ?RememberCookie $rememberCookie = null,
    ) {
        $this->userProvider = $userProvider;
        $this->rememberMe = $rememberMe;
        $this->rememberCookie = $rememberCookie ?? new RememberCookie();
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

    public function setRememberMe(?RememberMeInterface $rememberMe, $cookie = null): void
    {
        $this->rememberMe = $rememberMe;
        if ($cookie !== null) {
            $this->rememberCookie = $cookie;
        }
    }

    public function login(Identity $identity): bool
    {
        $user = $this->userProvider->findByIdentity($identity);
        if ($user === null) {
            $this->status = self::AUTH_FAILED;

            return false;
        }
        $this->applySuccessfulLogin($user, $identity);
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

    public function isLoginBy(string $by): bool
    {
        if (!$this->isLogin()) {
            return false;
        }

        return ($this->loginInfo['loginBy'] ?? null) === $by;
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
     * @return array<string, mixed>
     */
    public function getLoginInfo(): array
    {
        return $this->loginInfo;
    }

    public function logout(): void
    {
        $this->loginId = null;
        $this->currentUser = null;
        $this->status = self::AUTH_NONE;
        $this->loginInfo = [];
        $this->sessionStore->clear();
    }

    private function applySuccessfulLogin(object $user, Identity $identity): void
    {
        $this->currentUser = $user;
        $this->loginId = $this->userProvider->getUserId($user);
        $this->status = self::AUTH_OK;
        $time = date('Y-m-d H:i:s');
        $loginBy = $this->mapKindToLoginBy($identity->kind);
        $payload = [
            'userId' => $this->loginId,
            'providerKey' => $this->userProvider->getProviderKey(),
            'loginKind' => $identity->kind->name,
            'loginBy' => $loginBy,
            'time' => $time,
        ];
        $this->sessionStore->write($payload);
        $this->loginInfo = [
            'loginId' => $this->loginId,
            'loginBy' => $loginBy,
            'type' => $this->userProvider->getProviderKey(),
            'time' => $time,
        ];
    }

    private function mapKindToLoginBy(AuthKind $kind): string
    {
        return match ($kind) {
            AuthKind::Password => self::BY_POST,
            AuthKind::ForceLogin => self::BY_FORCED,
            AuthKind::OAuth => self::BY_OAUTH,
            AuthKind::OneTimeToken => 'ONETIMETOKEN',
        };
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
        $this->currentUser = $user;
        $this->loginId = $userId;
        $this->status = self::AUTH_OK;
        $this->loginInfo = [
            'loginId' => $userId,
            'loginBy' => $payload['loginBy'] ?? self::BY_POST,
            'type' => $this->userProvider->getProviderKey(),
            'time' => $payload['time'] ?? date('Y-m-d H:i:s'),
        ];

        return true;
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
        $this->status = self::AUTH_OK;
        $time = date('Y-m-d H:i:s');
        $payload = [
            'userId' => $this->loginId,
            'providerKey' => $this->userProvider->getProviderKey(),
            'loginKind' => AuthKind::Password->name,
            'loginBy' => self::BY_REMEMBER,
            'time' => $time,
        ];
        $this->sessionStore->write($payload);
        $this->loginInfo = [
            'loginId' => $this->loginId,
            'loginBy' => self::BY_REMEMBER,
            'type' => $this->userProvider->getProviderKey(),
            'time' => $time,
        ];
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
