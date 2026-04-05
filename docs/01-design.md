> **注意:** このファイルは**初期草案と追記をそのまま残したアーカイブ**です。重複や経緯による差異があります。**単一の整合した設計方針は [`02-design.md`](02-design.md) を参照してください。**

---

Auth開発
========

現在の認証ライブラリを最初から設計しなおす。

方針としては、既存のID/PWによる認証とOAuth2を融合する。

- いわゆるWebログイン
  - ID/PWを使った認証
  - 管理者などによるPW無しでのログイン
- OAuth2によるログイン
  - Php LeagueのOAuth2クライアントを利用した、Google、X、Lineなどでのログイン。
  - 未ログインからのOAuth2ログイン、ログイン済みからのOAuth2との連携、などに対応。

> **「Identity（識別子）による抽象化」「UserProviderによるオブジェクト通訳」「PSR-7/15への疎結合」**——を凝縮した、新 `WScore/Auth v2` の設計ガイドラインをMarkdown形式でまとめます。

OAuth2認証については、[過去のまとめ](https://qiita.com/asaokamei/items/efe34b6cfd2b5a8f2f9b)を参照。

---

# WScore/Auth v2 設計方針書 (Draft)

## 1. 設計思想
「認証（Authentication）」と「識別（Identification）」を完全に分離し、あらゆるログイン方式（PW, OAuth2, MagicLink等）を同一のフローで扱う。

* **Stateless (状態を持たない):** Providerは検索に徹し、キャッシュやセッションの状態を持たない。
* **Object Agnostic (オブジェクト非依存):** ユーザーモデルに特定の `Interface` 実装を強制しない。Eloquent等のActiveRecordをそのまま受け入れる。
* **Framework Agnostic:** HTTPメッセージ（PSR-7等）への依存をコアから排除し、Bridge層で吸収する。

---

## 2. コア・コンポーネント

### 2.1 Identity (Value Object)
認証に必要な「手がかり」を包むオブジェクト。
```php
readonly class Identity {
    public function __construct(
        public string $type,         // 'password', 'google', 'github' など
        public array $credentials,   // 照合用データ (['email'=>'...', 'password'=>'...'] など)
        public array $options = []   // 追加コンテキスト
    ) {}
}
```

### 2.2 UserProviderInterface (The Translator)
「ユーザーを探す」と「オブジェクトを通訳する」の二役を担う。
```php
interface UserProviderInterface {
    /** 識別子からユーザーを特定・検証して返す */
    public function findByIdentity(Identity $identity): ?object;

    /** ユーザーオブジェクトからセッション保存用の一意識別子(ID)を抽出する */
    public function getUserId(object $user): string|int;

    /** セッションIDからユーザーオブジェクトを復元する */
    public function findById(string|int $userId): ?object;
}
```

### 2.3 Auth (The Manager)
セッション管理と、取得したユーザーオブジェクトのインメモリキャッシュを担う。
```php
class Auth {
    private ?object $currentUser = null;

    public function login(Identity $identity): bool; // Provider経由でログイン
    public function user(): ?object;                // キャッシュまたはセッションから復元
    public function logout(): void;
}
```

---

## 3. 認証シーケンス（OAuth2の例）

1.  **Bridge層:** Controller/MiddlewareがOAuth2プロバイダからユーザー情報を取得。
2.  **Identity生成:** `new Identity('google', ['sub' => '...', 'email' => '...'])` を作成。
3.  **特定:** `Auth::login($identity)` を実行。内部で `Provider::findByIdentity()` が呼ばれる。
4.  **永続化連携:** Providerが `user_connects` テーブル等を検索し、対応する `User` モデル（Eloquent等）を返す。
5.  **セッション保存:** `Auth` が `Provider::getUserId($user)` を呼び、得られた値をセッションに保存。



---

## 4. WScore/Auth v1 からの主な変更点

| 項目 | v1 (現状) | v2 (新設計) |
| :--- | :--- | :--- |
| **User型** | `UserInterface` 強制 | `object` (任意モデル) |
| **認証方式** | ID/PW 密結合 | `Identity` による抽象化 |
| **Providerの責務** | 検索 + 認証ロジック | 検索 + ID抽出 (通訳) |
| **キャッシュ** | Provider側で持ちがち | Authクラス側で一元管理 |
| **OAuth2連携** | 困難（またはパッチ的） | `Identity` の一形態としてネイティブ対応 |

---

## 5. 実装上の注記
* **PHP Version:** PHP 8.2以上推奨 (readonly properties, intersection typesの活用)。
* **Middleware:** Slim4等で使用する場合、`Identity` を生成して `Auth::login()` を叩く薄い Middleware を提供する。
* **SocialAuthService:** 以前 Qiita で設計したロジックは、この `UserProvider::findByIdentity` 内、あるいはそれを利用するサービス層として再利用する。

---

いかがでしょうか。この設計なら、以前議論した「EloquentにInterfaceを切りたくない」という問題も、`getUserId(object $user)` という通訳メソッドを置くことで美しく解決できています。

新しいブランチでの開発、楽しみにしています！この設計書を元に進める中で、何か矛盾や「もっと削ぎ落とせる」部分が見つかったら、また議論しましょう。


# WScore/Auth v2 — 設計方針の結論（追記）

`01-design.md` のドラフトを踏まえ、実装に向けて次の方針で進める。

---

## パスワード検証

- **Provider 内で行う**（ハッシュ照合は `UserProvider::findByIdentity` の責務に含める）。
- 共通処理が多い場合は **`CredentialValidator` を必須にはせず**、**Trait または小さな `final` ヘルパ**でサンプル実装を提供する。
- Trait は薄いラッパに留め、ハッシュアルゴリズム・移行方針はアプリ側で差し替え可能にする。
- これは「Provider はリクエスト間で状態を持たない」と矛盾しない（リクエスト内の純粋な検証）。

---

## セッション管理

「`$_SESSION` を参照渡しで受け取り直接書き換える」現状のアプローチは引き続き有効。v2 では次の選択肢を検討する。

| 方式 | 内容 |
|------|------|
| **A. `array` 参照渡し** | 現状どおり。実装が単純。 |
| **B. `ArrayAccess`** | ラッパーと `$_SESSION` を同型で扱える。`[]` の挙動に注意。 |
| **C. 専用の細いインターフェース** | `get` / `set` / `remove` など、Auth が触る操作だけ。テストしやすい。 |
| **D. キー空間の契約** | 例: ライブラリは決めたキー配下のみ読み書き。上位は配列でも `ArrayAccess` でも可。 |

**推奨の整理:** コアは **C を第一候補**とし、**A 向けアダプタ**（配列をラップ）を併せて提供する形がバランスよい。公開 API を `array|ArrayAccess` だけに寄せるより、**Auth が必要とする最小 API** を先に定義し、配列はアダプタ経由にした方が、フレームワークのセッションへ載せ替えやすい。

**セッション固定:** 対策は **アプリ責務**。ただしログイン成功直後の `session_regenerate_id` は定石なので、Bridge / ミドルウェアの推奨フローとして文書化してよい（ライブラリが強制する必要はない）。

---

## メールアドレス等の検証

- **ライブラリの責務外**（初期開発スコープ）。形式・存在確認・「ログインに使うか」はアプリが決める。
- OAuth 等でメールが返っても **不透明な文字列として `Identity` に載せる**だけにし、検証用依存を増やさない。

---

## `Identity::type`（列挙型で絞る）

- **メカニズム**を enum で表す: 例 `Password`, `ForceLogin`, `OAuth`, `OneTimeToken` など。
- **OAuth のプロバイダ名**（Google / LINE / X 等）は **`type` に全部並べるより**、別フィールド（`string` またはプロバイダ用の小さな enum）に分ける方が拡張しやすい。コアは `OAuth` + `provider: 'google'` のように拡張可能な形を推奨。
- `ForceLogin` は危険度が高いため、**必ずアプリ側でゲートする**（IP・ロール・ワンタイム署名など）。認可をライブラリに入れないこととは別に、**誤用しやすい入口**である旨をドキュメントに書く。

---

## 認可・多要素・レートリミット

- **認可（ロール・権限）:** 責務外。
- **多要素（MFA）:** 当面は責務外（プロトコル・UX・リカバリが重く、別レイヤに近い）。
- **レートリミット・セッション固定対策:** アプリ責務。

---

## Remember Me

- **対応する**（v1 にも近い概念あり）。
- 長期トークン・ストレージ・ローテーションを伴うため、**Provider のステートレス**とは別に、**Auth 周辺のオプションコンポーネント**として切り、必要な永続化の契約を明示する。

---

## あまり推奨しにくいパターン（注意）

1. OAuth プロバイダをすべて `Identity::type` の enum ケースに載せると、プロバイダ追加のたびにコア enum が増えがち。**メカニズムとプロバイダ ID の二段**が無難。
2. Trait にロジックを詰め込みすぎない（パスワード検証の薄いヘルパに留める）。
3. `ArrayAccess` だけを中心 API にすると責務がぼやける。**専用インターフェース + 配列アダプタ**の方が長期的には扱いやすい。

---

## 次に文書化すると実装が進めやすい項目

- セッション用インターフェースの **メソッド一覧**（名前は仮でよい）。
- Remember Me が **UserProvider** および **トークンストレージ**に要求する **最小の契約**。
