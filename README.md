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
use WScore\Auth\Identity;

$ok = $auth->login(Identity::newPassword($id, $password));

// OAuth: プロバイダが付与するユーザー ID（生レスポンスの sub / id 等はブリッジで取り出す）
$ok = $auth->login(Identity::newOAuth('google', $googleUserId, [
    'email' => $email,
]));
// UserProvider 側では credentials[Identity::CREDENTIAL_PROVIDER_USER_ID] で参照
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

Remember 系は **`Auth` のコンストラクタでは渡さず**、必ず **`setRememberMe()`** でまとめて設定する（DI コンテナのファクトリから `Auth` を作ったあとに束ねる想定）。

`RememberCookie` は HTTP クッキー（id + token）と **有効日数**（既定 7 日）を扱う。

```php
use WScore\Auth\RememberAdaptor\RememberCookie;

$auth = new Auth($userProvider, $session);

// 本番: 30 日のブラウザ用クッキー（内部で RememberCookie::forBrowser(30)）
$auth->setRememberMe($rememberMe, null, 30);

// または明示的に組み立てた RememberCookie を渡す
$auth->setRememberMe($rememberMe, RememberCookie::forBrowser(30));

// テスト: バッグ + setSetCookie を差し替え
$bag = new \ArrayObject();
$cookie = new RememberCookie($bag, 7);
$cookie->setSetCookie($mockSetter);
$auth->setRememberMe($rememberMe, $cookie);
```

`$rememberMe` は `WScore\Auth\Contracts\RememberMeInterface`。PDO のサンプル実装は `WScore\Auth\RememberAdaptor\RememberMePdoSample`（参考用・本番は自前実装推奨）。無効化する場合は `setRememberMe(null)`。

Enable on login:

```php
$auth->loginWithPassword($id, $password, true);
// or Identity with options: new Identity(..., ['remember' => true])
```
