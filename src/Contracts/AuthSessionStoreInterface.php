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
     * Reads the session payload for the given provider key.
     * 
     * @param string $segmentKey Per-provider slot (e.g. {@see UserProviderInterface::getProviderKey()}).
     * @return array<string, mixed>|null
     */
    public function read(string $segmentKey): ?array;

    /**
     * Writes the session payload for the given provider key.
     * 
     * @param string               $segmentKey Per-provider slot (e.g. {@see UserProviderInterface::getProviderKey()}).
     * @param array<string, mixed> $payload
     */
    public function write(string $segmentKey, array $payload): void;

    /**
     * Clears the session payload for the given provider key.
     * 
     * @param string $segmentKey Per-provider slot (e.g. {@see UserProviderInterface::getProviderKey()}).
     */
    public function clear(string $segmentKey): void;
}
