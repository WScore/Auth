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
        private PDO $pdo,
    ) {
    }

    /**
     * @param string|int $loginId
     * @param string $token
     */
    public function verifyRemember($loginId, $token): bool
    {
        return $this->getRemembered($loginId, $token) !== null;
    }

    /**
     * @param string|int $loginId
     * @param string|null $token
     * @return bool|string
     */
    public function generateToken($loginId, $token)
    {
        $found = $this->getRemembered($loginId, (string) $token);
        if ($found !== null) {
            return $found['token'];
        }
        $newToken = $this->calRememberToken();
        $this->saveIdWithToken((string) $loginId, $newToken);

        return $newToken;
    }

    /**
     * @return non-empty-string
     */
    protected function calRememberToken(): string
    {
        return bin2hex(random_bytes(32));
    }

    /**
     * @param non-empty-string $id
     * @param non-empty-string $token
     */
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

    /**
     * @param string|int $id
     * @param string $token
     * @return array{user_id: string, token: string}|null
     */
    private function getRemembered($id, string $token): ?array
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
}
