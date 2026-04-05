WScore.Auth
===========

**v2** は `Identity` と `UserProviderInterface` を中心にした設計です。

以前の **0.x 系（ベータ相当）とは API が互換ではありません。** アップグレードは実装の載せ替えを前提にしてください。破壊的変更を許容してよい前提で進めています。

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

（v2 リリース前は VCS / `@dev` / プレリリースタグに合わせて制約を指定してください。）

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
use WScore\Auth\AuthKind;
use WScore\Auth\Identity;

$ok = $auth->login(new Identity(AuthKind::Password, [
    'id' => $id,
    'password' => $password,
]));
```

Check login state:

```php
$auth->isLogin();
$user = $auth->user();
$id = $auth->getLoginId();
```

### Force Login

```php
use WScore\Auth\AuthKind;

$auth->forceLogin($id);
$auth->isLoginBy(AuthKind::ForceLogin);
```

`getLoginInfo()` includes `kind` (`AuthKind`), `loginId`, `type` (provider key), `time`.

UserProvider
------------

Implement `WScore\Auth\Contracts\UserProviderInterface`:

* `findByIdentity(Identity $identity): ?object` — resolve and verify credentials.
* `getUserId(object $user): string|int` — id stored in session.
* `findById(string|int $userId): ?object` — restore user from that id.
* `getProviderKey(): string` — session segment key (namespaces `Auth::KEY`).

Remember-Me Option
------------------

```php
$auth = new Auth($userProvider, $session, $rememberMe, $rememberCookie);
// or
$auth->setRememberMe($rememberMe, $rememberCookie);
```

`$rememberMe` implements `WScore\Auth\Contracts\RememberMeInterface`.

Enable on login:

```php
$auth->loginWithPassword($id, $password, true);
// or Identity with options: new Identity(..., ['remember' => true])
```
