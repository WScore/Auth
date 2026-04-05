<?php

declare(strict_types=1);

namespace WScore\Auth\Contracts;

use WScore\Auth\Identity;

interface UserProviderInterface
{
    /**
     * Resolves an {@see Identity} to a user object, or null if authentication fails.
     */
    public function findByIdentity(Identity $identity): ?object;

    /**
     * Stable id stored in session / remember-me (must match {@see findById}).
     */
    public function getUserId(object $user): string|int;

    public function findById(string|int $userId): ?object;

    /**
     * Isolates session storage when multiple providers exist (segment key under {@see \WScore\Auth\Auth::KEY}).
     *
     * @return non-empty-string
     */
    public function getProviderKey(): string;
}
