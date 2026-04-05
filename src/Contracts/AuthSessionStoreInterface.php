<?php

declare(strict_types=1);

namespace WScore\Auth\Contracts;

/**
 * Persists the authenticated session payload for {@see \WScore\Auth\Auth}.
 *
 * @phpstan-type AuthPayload array{
 *   userId: string|int,
 *   providerKey: string,
 *   loginKind: string,
 *   time: string,
 * }
 */
interface AuthSessionStoreInterface
{
    /**
     * @return array<string, mixed>|null
     */
    public function read(): ?array;

    /**
     * @param array<string, mixed> $payload
     */
    public function write(array $payload): void;

    public function clear(): void;
}
