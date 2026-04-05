# WScore/Auth v2 設計方針書

現在の認証ライブラリを最初から設計し直し、**ID/PW による認証**と **OAuth2** を同一の流れで扱う。

- **Web ログイン:** ID/PW、管理者などによるパスワードなしログイン（ForceLogin 等）
- **OAuth2:** League OAuth2 クライアントを用いた Google、X、LINE など。未ログインからのログイン、ログイン済みアカウントとの連携に対応。

コアの考え方は **「Identity による抽象化」「UserProvider によるオブジェクト通訳」「HTTP は Bridge で吸収（PSR-7/15 等への疎結合）」**。

OAuth2 の詳細な流れは [過去のまとめ (Qiita)](https://qiita.com/asaokamei/items/efe34b6cfd2b5a8f2f9b) も参照。

---

## 1. 設計思想

「認証（Authentication）」と「識別（誰をログインさせるかの手がかり）」を整理し、PW・OAuth2・マジックリンク等を **同じ入口**から扱う。

- **Provider はステートレスで実装できる設計を目指す。**  
  リクエストをまたいで **セッション・キャッシュ・ログイン済みユーザーの保持**を Provider が行わない。パスワード照合や OAuth 連携テーブル参照は、`findByIdentity` 内の **そのリクエスト限りの処理**としてよい（DB 読み書きは「状態」ではなく永続化の検索・検証）。
- **Object Agnostic:** ユーザーモデルに特定の `Interface` を強制しない。Eloquent 等をそのまま扱う。
- **Framework Agnostic:** コアは HTTP を知らない。PSR-7/15 やフレームワークは **Bridge** で `Identity` 生成・`Auth::login()` 呼び出しに接続する。

---

## 2. コア・コンポーネント

### 2.1 Identity（Value Object）

認証に必要な「手がかり」を包む。**認証のメカニズム**と **OAuth プロバイダ名**は分ける。

- **メカニズム**は列挙型で絞る（例: `Password`, `ForceLogin`, `OAuth`, `OneTimeToken`）。拡張しやすいよう、OAuth プロバイダごとに enum ケースを増やしすぎない。
- **OAuth** のときは `options` 等に `provider: 'google'` のように **プロバイダ ID** を載せる（`type` に `google` だけを載せる設計は避ける）。

```php
readonly class Identity {
    public function __construct(
        public AuthKind $kind,       // enum: Password, ForceLogin, OAuth, OneTimeToken, …
        public array $credentials,   // 照合用: ['email'=>'…', 'password'=>'…'] 等
        public array $options = [],  // OAuth の provider、コンテキスト等
    ) {}
}

// OAuth の例（概念）— プロバイダ固有情報の主キーは Identity::PROVIDER_USER_ID_KEY（ファクトリ newOAuth が設定）
// Identity::newOAuth('google', $providerUserId, ['email' => '…'])
```

`ForceLogin` は **誤用しやすい**。必ずアプリ側でゲートする（IP、ロール、ワンタイム署名など）。認可をライブラリに含めないこととは別に、ドキュメントで注意喚起する。

### 2.2 UserProviderInterface（通訳）

**ユーザーの検索・資格情報の検証**と、**任意オブジェクトと永続化 ID の通訳**を担う。

- `findByIdentity`: パスワードならハッシュ照合も **ここで行う**（専用 `CredentialValidator` クラスは必須にしない。共通処理は **Trait または小さな `final` ヘルパ**でサンプル提供可。薄いラッパに留め、アルゴリズム・移行はアプリで差し替え可能にする）。
- `getUserId` / `findById`: セッション等に載せる ID とユーザーオブジェクトの変換。

```php
interface UserProviderInterface {
    public function findByIdentity(Identity $identity): ?object;
    public function getUserId(object $user): string|int;
    public function findById(string|int $userId): ?object;
}
```

### 2.3 Auth（マネージャ）

**セッションへの永続化**と、**同一リクエスト内のユーザーオブジェクトキャッシュ**を担う。Remember Me は **Auth 周辺のオプション**（別コンポーネント）として切り、長期トークン・ストレージ・ローテーションの契約を明示する。

```php
class Auth {
    public function login(Identity $identity): bool;
    public function user(): ?object;
    public function logout(): void;
}
```

セッションの注入形は後述。**Remember Me の具体的な API・契約**は別途文書化する。

セッションおよび `getLoginInfo()` では **別名の `loginBy` 文字列は持たず**、**`AuthKind` だけ**で「どの経路でログインしたか」を表す（例: `Password`, `OAuth`, `Remember` は Remember Me クッキーでセッションを張った場合）。`isLoginBy(AuthKind $kind)` と一箇所に寄せる。

---

## 3. 認証シーケンス（OAuth2 の例）

1. **Bridge:** Middleware / Controller が OAuth プロバイダからトークン・ユーザー情報を取得（state 等は Bridge またはアプリの責務）。
2. **Identity 生成:** 例: `Identity::newOAuth('google', $providerUserId, ['email' => '…'])`（生レスポンスの `sub` / `id` 等は Bridge で取り出す）。
3. **ログイン:** `Auth::login($identity)` → 内部で `UserProvider::findByIdentity()`。
4. **永続化連携:** Provider が `user_connects` 等を参照し、対応する `User` を返す。
5. **セッション:** `Auth` が `getUserId($user)` で ID を得てセッションに保存。

---

## 4. セッション

コアは **Auth が必要とする最小の読み書き**をインターフェースで受け取ることを第一候補とし、**PHP の配列セッション**はアダプタで包む。

| 方式 | 内容 |
|------|------|
| A. `array` 参照渡し | 実装が単純（v1 に近い）。 |
| B. `ArrayAccess` | ラッパーと `$_SESSION` を同型で扱える。 |
| C. 専用の細いインターフェース | `get` / `set` / `remove` 等。テストしやすい。**推奨の中心。** |
| D. キー空間の契約 | ライブラリは決めたキー配下のみ触る。 |

**セッション固定対策**はアプリ責務。ログイン成功直後の `session_regenerate_id` は Bridge / ミドルウェアの **推奨フロー**として文書化してよい（ライブラリが強制しない）。

---

## 5. ライブラリのスコープ

| 項目 | 方針 |
|------|------|
| メール形式・存在確認など | **責務外。** OAuth のメールは不透明な文字列として `credentials` に載せるだけ。 |
| 認可（ロール・権限） | **責務外。** |
| 多要素（MFA） | **当面は責務外。** |
| レートリミット | **アプリ責務。** |
| Remember Me | **対応する**（Auth 周辺コンポーネントとして）。 |

---

## 6. WScore/Auth v1 からの主な変更点

| 項目 | v1 | v2 |
|------|----|----|
| User 型 | `UserInterface` 強制 | `object`（任意モデル） |
| 認証方式 | ID/PW 密結合 | `Identity` による抽象化 |
| Provider の責務 | 検索 + 認証ロジック | **検索・資格情報の検証** + ID 通訳（ステートレスに実装可能な形） |
| キャッシュ | Provider 側に寄りがち | **Auth** が同一リクエスト内を一元管理 |
| OAuth2 | パッチ的になりがち | `Identity`（例: `AuthKind::OAuth`）として扱う |

---

## 7. 実装上の注記

- **PHP:** 8.2 以上推奨（`readonly`、enum、交差型など）。
- **Middleware:** Slim 4 等では `Identity` を組み立てて `Auth::login()` を呼ぶ薄い層を Bridge 側で提供する想定。
- **既存ロジック:** Qiita で整理した Social 系の流れは、`UserProvider::findByIdentity` 内、またはそれを呼ぶサービス層に載せ替え可能。

---

## 8. 推奨しにくいパターン

1. OAuth プロバイダをすべて `AuthKind` の列挙子に増やし続ける（**メカニズムと `provider` ID の二段**が無難）。
2. パスワード用 Trait にロジックを詰め込みすぎる。
3. セッションを `ArrayAccess` のみで表し、Auth の契約がぼやける（**専用 IF + 配列アダプタ**が扱いやすい）。

---

## 9. 次に文書化するとよい項目

- セッション用インターフェースの **メソッド一覧**（仮名で可）。
- Remember Me が **UserProvider** および **トークンストレージ**に要求する **最小の契約**。
