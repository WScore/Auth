WScore.Auth
===========

**v2** is built around `Identity` and `UserProviderInterface`.

It is **not API-compatible** with earlier **0.x (beta-era)** releases. Treat upgrades as a rewrite; breaking changes are expected.

### License

MIT License

### PSR

PSR-1, PSR-2, and PSR-4.

### Requirements

PHP 8.2+

### Installation

```sh
composer require "wscore/auth:^2.0"
```

Before v2 is tagged on Packagist, pin VCS / `@dev` / pre-release tags as needed.

Getting Started
--------------

`Auth` requires a `UserProviderInterface` (`WScore\Auth\Contracts`) implementation.

```php
$auth = new Auth($userProvider);
$auth->setSession($session); // optional; default is active `$_SESSION` or an internal array
```

Password login (convenience):

```php
use WScore\Auth\Auth;

if ($auth->loginWithPassword($id, $password)) {
    echo 'login success!';
}
```

Or with an `Identity` value object:

```php
use WScore\Auth\Identity;

$ok = $auth->login(Identity::newPassword($id, $password));

// OAuth: provider user id (extract sub / id from raw responses in your bridge)
$ok = $auth->login(Identity::newOAuth('google', $googleUserId, [
    'email' => $email,
]));
// In UserProvider, read credentials[Identity::CREDENTIAL_PROVIDER_USER_ID]
```

Check login state:

```php
$auth->isLogin();
$user = $auth->user();
$id = $auth->getLoginId();
$auth->getUserProvider(); // the UserProviderInterface instance
```

`user()` resolves in order: in-memory cache → session → remember-me cookie (if `setRememberMe` was configured).

**Note:** Calling `isLogin()` or `user()` may trigger an automatic login attempt via remember-me cookies if no active session exists. This can result in new cookies being sent or backend tokens being rotated.

```php
$auth->logout(); // clears session segment, in-memory state, and remember-me cookies/tokens.
```

### AuthKind

`getLoginInfo()['kind']` and `isLoginBy()` use `WScore\Auth\AuthKind`: `Password`, `ForceLogin`, `OAuth`, `OneTimeToken`, `Remember`.

- **`Remember`** — the session was established via remember-me cookie validation (no password submitted on this login). This differs from password login with the “remember me” box checked, which stays **`Password`**.
- Use `isLoginBy(AuthKind::Remember)` (or inspect `getLoginInfo()['kind']`) to distinguish that path from interactive logins.

Other `Identity` constructors: `Identity::newForceLogin`, `Identity::newOneTimeToken`, `Identity::newRemember` (for providers that resolve remember pairs in `findByIdentity`).

### Force Login

```php
use WScore\Auth\AuthKind;

$auth->forceLogin($id);
$auth->isLoginBy(AuthKind::ForceLogin);
```

`getLoginInfo()` includes `kind` (`AuthKind`), `loginId`, `type` (provider key), `time`.

## UserProvider

Implement `WScore\Auth\Contracts\UserProviderInterface`:

* `findByIdentity(Identity $identity): ?object` — resolve and verify credentials.
* `getUserId(object $user): string|int` — id stored in session.
* `findById(string|int $userId): ?object` — restore user from that id.
* `getProviderKey(): string` — session segment key (namespaces `Auth::KEY`).

## Remember-Me Option

Do **not** pass remember-me dependencies into the `Auth` constructor. Configure everything with **`setRememberMe()`** (e.g. after constructing `Auth` from a DI container factory).

`RememberCookie` handles HTTP cookies (id + token) and **lifetime in days** (default 7).

```php
use WScore\Auth\RememberAdaptor\RememberCookie;

$auth = new Auth($userProvider, $session);

// Production: 30-day browser cookie (uses RememberCookie::forBrowser(30) internally)
$auth->setRememberMe($rememberMe, null, 30);

// Or pass an explicit RememberCookie
$auth->setRememberMe($rememberMe, RememberCookie::forBrowser(30));

// Tests: bag + replace setSetCookie
$bag = new \ArrayObject();
$cookie = new RememberCookie($bag, 7);
$cookie->setSetCookie($mockSetter);
$auth->setRememberMe($rememberMe, $cookie);
```

`$rememberMe` implements `WScore\Auth\Contracts\RememberMeInterface`. A PDO sample lives at `WScore\Auth\RememberAdaptor\RememberMePdoSample` (reference only—use your own in production). Call `setRememberMe(null)` to disable.

### Advanced

`setAuthSessionStore(WScore\Auth\Contracts\AuthSessionStoreInterface $store)` — replace the default `ArrayAuthSessionStore` (e.g. integration tests or a non-array session backend).

#### Session Regeneration

By default, `Auth::login()` calls `session_regenerate_id(true)` for security (session fixation prevention). However, this can cause session loss on unstable networks or specific browser environments. You can disable this behavior if needed:

```php
$auth->setRegenerateSessionOnLogin(false);
```

Enable on login:

```php
$auth->loginWithPassword($id, $password, true);
// or Identity::newPassword($id, $password, ['remember' => true])
```

---

Japanese documentation: [README.ja.md](README.ja.md).
