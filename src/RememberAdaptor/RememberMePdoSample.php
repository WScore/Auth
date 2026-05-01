<?php

declare(strict_types=1);

namespace WScore\Auth\RememberAdaptor;

use PDO;
use WScore\Auth\Contracts\RememberMeInterface;

/**
 * Sample implementation that stores remember-me tokens in a PDO-backed table.
 *
 * This is a reference implementation only. Replace schema/SQL/error handling for your application.
 *
 * Expected schema (example):
 * - user_id (string/int)
 * - token (string)
 * - created_at (int; unix epoch seconds when the token was issued)
 *
 * Validity is checked as `time() <= created_at + $tokenLifetimeSeconds`, so changing
 * `$tokenLifetimeSeconds` applies the new policy to existing rows (e.g. shortening
 * lifetime immediately invalidates older tokens).
 */
class RememberMePdoSample implements RememberMeInterface
{
    /**
     * @param positive-int $tokenLifetimeSeconds current max age in seconds (rolling policy for verification)
     */
    public function __construct(
        private readonly PDO $pdo,
        private readonly int $tokenLifetimeSeconds = 60 * 60 * 24 * 7,
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
        $createdCol = $this->created_at_name;
        $createdAt = time();
        $stmt = $this->pdo->prepare(
            "INSERT INTO {$table} ({$idCol}, {$tokenCol}, {$createdCol}) VALUES (?, ?, ?)"
        );

        return $stmt->execute([$id, $token, $createdAt]);
    }

    private function getRemembered(int|string $id, string $token): ?array
    {
        $table = $this->table;
        $idCol = $this->id_name;
        $tokenCol = $this->token_name;
        $createdCol = $this->created_at_name;
        $now = time();
        $lifetime = $this->tokenLifetimeSeconds;
        $stmt = $this->pdo->prepare(
            "SELECT {$idCol} AS user_id, {$tokenCol} AS token, {$createdCol} AS created_at
             FROM {$table}
             WHERE {$idCol} = ? AND {$tokenCol} = ? AND ? <= {$createdCol} + ?"
        );
        $stmt->execute([$id, $token, $now, $lifetime]);
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

    /** @var non-empty-string */
    protected string $created_at_name = 'created_at';

    public function removeToken(int|string $loginId): void
    {
        $table = $this->table;
        $idCol = $this->id_name;
        $stmt = $this->pdo->prepare(
            "DELETE FROM {$table} WHERE {$idCol} = ?"
        );
        $stmt->execute([$loginId]);
    }
}
