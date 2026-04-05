WScore.Auth
===========

**v2** は `Identity` と `UserProviderInterface` を中心にした設計です。

以前の **0.x 系（ベータ相当）とは API が互換ではありません。** アップグレードは実装の載せ替えを前提にしてください。破壊的変更を許容してよい前提で進めています。

### ライセンス

MIT License

### PSR

PSR-1、PSR-2、PSR-4。

### 動作環境

PHP 8.2 以上

### インストール

```sh
composer require "wscore/auth:^2.0"
```

（v2 が Packagist に出る前は、VCS / `@dev` / プレリリースタグに合わせて制約を指定してください。）

はじめに
--------

`Auth` には `UserProviderInterface`（`WScore\Auth\Contracts`）の実装が必要です。

```php
$auth = new Auth($userProvider);
$auth->setSession($session); // optional; default is active `$_SESSION` or an internal array
```

パスワードログイン（簡易 API）:

```php
use WScore\Auth\Auth;

if ($auth->loginWithPassword($id, $password)) {
    echo 'login success!';
}
```

または `Identity` 値オブジェクトで:

```php
use WScore\Auth\Identity;

$ok = $auth->login(Identity::newPassword($id, $password));

// OAuth: プロバイダが付与するユーザー ID（生レスポンスの sub / id 等はブリッジで取り出す）
$ok = $auth->login(Identity::newOAuth('google', $googleUserId, [
    'email' => $email,
]));
// UserProvider 側では credentials[Identity::CREDENTIAL_PROVIDER_USER_ID] で参照
```

ログイン状態の確認:

```php
$auth->isLogin();
$user = $auth->user();
$id = $auth->getLoginId();
$auth->getUserProvider(); // the UserProviderInterface instance
```

`user()` の解決順は、メモリ上のキャッシュ → セッション → Remember クッキー（`setRememberMe` を設定済みの場合）です。

```php
$auth->logout(); // clears session segment and in-memory state (remember-me cookies are not cleared here—expire them in app code if needed)
```

### AuthKind

`getLoginInfo()['kind']` と `isLoginBy()` は `WScore\Auth\AuthKind` を使います: `Password`、`ForceLogin`、`OAuth`、`OneTimeToken`、`Remember`。

- **`Remember`** — セッションが Remember クッキーの検証だけで確立された場合（このときパスワードは送っていない）。ログイン画面で「ログイン状態を保持」にチェックしたパスワードログインは引き続き **`Password`** です。
- その区別には `isLoginBy(AuthKind::Remember)`、または `getLoginInfo()['kind']` の確認を使います。

その他の `Identity` ファクトリ: `Identity::newForceLogin`、`Identity::newOneTimeToken`、`Identity::newRemember`（`findByIdentity` で Remember の組を解決するプロバイダ向け）。

### 強制ログイン（Force Login）

```php
use WScore\Auth\AuthKind;

$auth->forceLogin($id);
$auth->isLoginBy(AuthKind::ForceLogin);
```

`getLoginInfo()` には `kind`（`AuthKind`）、`loginId`、`type`（プロバイダキー）、`time` が含まれます。

## UserProvider

`WScore\Auth\Contracts\UserProviderInterface` を実装します。

* `findByIdentity(Identity $identity): ?object` — 資格情報の解決と検証。
* `getUserId(object $user): string|int` — セッションに保存する ID。
* `findById(string|int $userId): ?object` — その ID からユーザーを復元。
* `getProviderKey(): string` — セッションのセグメントキー（`Auth::KEY` 配下を名前空間化）。

## Remember-Me オプション

Remember 系は **`Auth` のコンストラクタでは渡さず**、必ず **`setRememberMe()`** でまとめて設定します（DI コンテナのファクトリから `Auth` を生成したあとに束ねる想定）。

`RememberCookie` は HTTP クッキー（id + token）と **有効日数**（既定 7 日）を扱います。

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

`$rememberMe` は `WScore\Auth\Contracts\RememberMeInterface` です。PDO のサンプル実装は `WScore\Auth\RememberAdaptor\RememberMePdoSample`（参考用・本番は自前実装推奨）。無効化する場合は `setRememberMe(null)` です。

### 上級

`setAuthSessionStore(WScore\Auth\Contracts\AuthSessionStoreInterface $store)` — 既定の `ArrayAuthSessionStore` を差し替えます（統合テストや配列以外のセッション保存先など）。

ログイン時に Remember を有効にする例:

```php
$auth->loginWithPassword($id, $password, true);
// or Identity::newPassword($id, $password, ['remember' => true])
```

---

英語版: [README.md](README.md).
