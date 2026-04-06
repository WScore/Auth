<?php

declare(strict_types=1);

namespace WScore\Auth\RememberAdaptor;

use PDO;
use WScore\Auth\Contracts\RememberMeInterface;

/**
 * PDO に remember トークンを保存するサンプル実装。
 * スキーマ・SQL・エラー処理はアプリに合わせて置き換える前提で、**そのまま本番推奨とはしない**。
 */
class RememberMePdoSample implements RememberMeInterface
{
    /**
     * @param PDO $pdo
     */
    public function __construct(
        private readonly PDO $pdo,
    ) {
    }

    public function verifyRemember(int|string $loginId, string $token): bool
    {
        return $this->getRemembered($loginId, $token) !== null;
    }

    public function generateToken(int|string $loginId, ?string $token): bool|string
    {
        $found = $this->getRemembered($loginId, (string) $token);
        if ($found !== null) {
            return $found['token'];
        }
        $newToken = $this->calRememberToken();
        $this->saveIdWithToken((string) $loginId, $newToken);

        return $newToken;
    }

    protected function calRememberToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    protected function saveIdWithToken(string $id, string $token): bool
    {
        $table = $this->table;
        $idCol = $this->id_name;
        $tokenCol = $this->token_name;
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$table} ({$idCol}, {$tokenCol}) VALUES (?, ?)"
        );

        return $stmt->execute([$id, $token]);
    }

    private function getRemembered(int|string $id, string $token): ?array
    {
        $table = $this->table;
        $idCol = $this->id_name;
        $tokenCol = $this->token_name;
        $stmt = $this->pdo->prepare(
            "SELECT {$idCol} AS user_id, {$tokenCol} AS token
             FROM {$table}
             WHERE {$idCol} = ? AND {$tokenCol} = ?"
        );
        $stmt->execute([$id, $token]);
        $row = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!is_array($row)) {
            return null;
        }

        return $row;
    }

    /** @var non-empty-string */
    protected string $table = 'remember_tokens';

    /** @var non-empty-string */
    protected string $id_name = 'user_id';

    /** @var non-empty-string */
    protected string $token_name = 'token';

    public function removeToken(int|string $loginId): void
    {
        $table = $this->table;
        $idCol = $this->id_name;
        $stmt = $this->pdo->prepare(
        "DELETE FROM {$table} WHERE {$idCol}"
        );
        $stmt->execute([$loginId]);
    }
}
